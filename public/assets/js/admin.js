(function () {
  'use strict';

  var t = function (key, params) {
    return window.LoreI18n ? LoreI18n.t(key, params) : key;
  };

  var confirmModal = function (options) {
    return window.ConfirmModal ? ConfirmModal.confirm(options) : Promise.resolve(false);
  };

  /** Перевод с запасным текстом: если ключа нет в словаре, показываем fallback, а не сам ключ. */
  var tx = function (key, fallback, params) {
    var value = t(key, params);
    return (value && value !== key) ? value : fallback;
  };

  var notify = function (text, type) {
    if (window.Messages) window.Messages.show(text, { type: type || 'success' });
    else console.warn(text);
  };

  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function currentLang() {
    return String(window.LORE_LOCALE || document.documentElement.lang || 'en');
  }

  /** Название языка по коду: Intl.DisplayNames (без хардкода en/ru). */
  function langName(code, fallback) {
    var value = String(code || '');
    if (!value) return '';
    try {
      var names = new Intl.DisplayNames([LoreI18n ? LoreI18n.locale : currentLang()], { type: 'language' });
      var named = names.of(value);
      if (named) return named;
    } catch (_) {}
    return fallback || value.toUpperCase();
  }

  /* ============================================================
     BLOCK USER — кнопка «Block» в таблице пользователей
     ============================================================ */
  function blockUser(btn) {
    var id   = btn.getAttribute('data-user-id') || '';
    var name = btn.getAttribute('data-user-name') || '';

    confirmModal({
      title:       t('admin.block_title'),
      text:        t('admin.block_text'),
      confirmText: t('admin.block_confirm'),
      danger:      true,
    }).then(function (confirmed) {
      if (!confirmed) return;

      console.warn('[admin] User block route is not yet defined in routes.php. Applied locally for user:', id, name);

      var row = btn.closest('tr');
      var statusCell = row && row.querySelector('[data-status-cell]');
      if (statusCell) {
        statusCell.innerHTML =
          '<span class="admin-badge admin-badge--danger">' +
          t('admin.status_blocked') +
          '</span>';
      }
      btn.disabled = true;
      notify(t('admin.block_success'), 'success');
    });
  }

  /* ============================================================
     DELETE COMMENT — кнопка «Delete» в таблице комментариев
     ============================================================ */
  function deleteComment(btn) {
    var id = btn.getAttribute('data-comment-id') || '';

    confirmModal({
      title:       t('admin.delete_title'),
      text:        t('admin.delete_text'),
      confirmText: t('admin.action_delete'),
      danger:      true,
    }).then(function (confirmed) {
      if (!confirmed) return;

      var run = (window.LoreCsrf && LoreCsrf.fetch) ? LoreCsrf.fetch : fetch;
      run('/api/posts/' + id, {
        method: 'DELETE',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      }).then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        var row = btn.closest('tr');
        if (row) row.remove();
        notify(tx('admin.delete_comment_success', 'Comment deleted'), 'success');
      }).catch(function (err) {
        console.warn('[admin] DELETE /api/posts/' + id + ' failed', err);
        notify(tx('errors.common.request_failed', 'Request failed'), 'error');
      });
    });
  }

  /* ============================================================
     REPORTS — модалка решений по жалобам
     ============================================================ */
  var reportState = {
    modal: null,
    row: null,
    id: '',
    submitting: false,
    lastFocus: null
  };

  var reportsApi = (function () {
    var BASE = '/api/admin/reports';
    var status = 'unknown';

    function table() { return document.querySelector('[data-admin-table="reports"]'); }

    function setStatus(next) {
      if (status === next) return;
      status = next;
      var el = table();
      if (el) el.setAttribute('data-reports-api', next);
      if (next === 'mock') {
        console.warn('[admin] reports API is not available yet — decisions stay on page mocks');
      }
    }

    function request(method, path, body) {
      var url = BASE + (path || '');
      var options = {
        method: method,
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      };
      if (body !== undefined && body !== null) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
      }

      var run = (window.LoreCsrf && LoreCsrf.fetch) ? LoreCsrf.fetch : fetch;
      var result = function (st, ok, raw) {
        var data = null;
        var isJson = false;
        if (raw && /^[\s]*[\[{]/.test(raw)) {
          try { data = JSON.parse(raw); isJson = true; } catch (_) { data = null; }
        }
        return { status: st, ok: ok, data: data, raw: raw || '', isJson: isJson };
      };

      return Promise.resolve()
        .then(function () { return run(url, options); })
        .then(function (res) {
          return res.text().then(
            function (raw) { return result(res.status, res.ok, raw); },
            function () { return result(res.status, res.ok, ''); }
          );
        })
        .catch(function (err) {
          return { status: 0, ok: false, data: null, raw: '', isJson: false, networkError: err };
        });
    }

    function classify(r) {
      if (r.networkError) return 'mock';
      var looksHtml = /^\s*</.test(r.raw || '');
      if (r.ok) return looksHtml ? 'mock' : 'ok';
      if (looksHtml || !r.isJson) return 'mock';
      if (r.status === 404 || r.status === 405) return 'mock';
      if (r.status === 403) return 'forbidden';
      return 'error';
    }

    function errorText(r) {
      var fallback = null;
      if (window.LoreI18n) fallback = LoreI18n.t('errors.common.request_failed', { status: r.status });
      if (window.Messages && Messages.describe) return Messages.describe(r.status, r.data, r.raw, fallback);
      return fallback || ('HTTP ' + r.status);
    }

    return {
      get status() { return status; },
      decide: function (id, decision, note) {
        return request('PATCH', '/' + id, { decision: decision, note: note }).then(function (r) {
          var kind = classify(r);
          if (kind === 'ok') { setStatus('live'); return { mode: 'api', status: r.status }; }
          if (kind === 'mock') { setStatus('mock'); return { mode: 'mock' }; }
          if (kind === 'forbidden') return { mode: 'forbidden', status: r.status };
          return { mode: 'error', status: r.status, message: errorText(r) };
        });
      }
    };
  })();

  function reportRoot() {
    if (!reportState.modal) reportState.modal = document.querySelector('[data-admin-report-modal]');
    return reportState.modal;
  }

  function isReportOpen() {
    var m = reportRoot();
    return !!(m && !m.hidden);
  }

  function rq(sel) {
    var m = reportRoot();
    return m ? m.querySelector(sel) : null;
  }

  function setReportsStatus(text) {
    var el = document.querySelector('[data-admin-reports-status]');
    if (!el) return;
    el.textContent = text || '';
    el.hidden = !text;
  }

  function setReportButtonsDisabled(disabled) {
    ['[data-admin-report-reject]', '[data-admin-report-delete]'].forEach(function (sel) {
      var btn = rq(sel);
      if (btn) btn.disabled = disabled;
    });
  }

  function closeReportModal() {
    var m = reportRoot();
    if (m) m.hidden = true;
    document.body.classList.remove('is-modal-open');
    reportState.row = null;
    reportState.id = '';
    reportState.submitting = false;

    ['[data-admin-report-reject]', '[data-admin-report-delete]', '[data-admin-report-cancel]'].forEach(function (sel) {
      var btn = rq(sel);
      if (btn) btn.disabled = false;
    });

    if (reportState.lastFocus && reportState.lastFocus.isConnected) {
      try { reportState.lastFocus.focus(); } catch (_) {}
    }
    reportState.lastFocus = null;
  }

  function openReportModal(btn) {
    var m = reportRoot();
    var row = btn.closest('tr');
    if (!m || !row) return;

    var text = function (sel, value) {
      var el = rq(sel);
      if (el) el.textContent = value || '';
    };

    reportState.row = row;
    reportState.id = row.getAttribute('data-report-id') || '';

    var title = rq('[data-admin-report-title]');
    if (title) title.textContent = t('admin.decision_title', { id: reportState.id });

    text('[data-admin-report-kind]', t('report.kind_' + (row.getAttribute('data-report-target-type') || 'comment')));
    text('[data-admin-report-target]', row.getAttribute('data-report-target-label'));
    text('[data-admin-report-reason]', t('report.reason_' + (row.getAttribute('data-report-reason') || 'other')));
    text('[data-admin-report-reporter]', row.getAttribute('data-report-reporter'));
    text('[data-admin-report-created]', row.getAttribute('data-report-created'));

    var note = rq('[data-admin-report-note]');
    if (note) note.value = '';

    setReportsStatus('');
    reportState.lastFocus = document.activeElement;
    m.hidden = false;
    document.body.classList.add('is-modal-open');

    var cancel = rq('[data-admin-report-cancel]');
    if (cancel) cancel.focus();
  }

  function applyReportDecision(row, action) {
    var status = action === 'delete' ? 'deleted' : 'rejected';
    row.setAttribute('data-status', status);

    var statusCell = row.querySelector('[data-status-cell]');
    if (statusCell) {
      statusCell.innerHTML =
        '<span class="admin-badge ' + (action === 'delete' ? 'admin-badge--danger' : 'admin-badge--muted') + '">' +
        t('admin.status_' + status) +
        '</span>';
    }

    var reviewBtn = row.querySelector('[data-admin-report-review]');
    if (reviewBtn) reviewBtn.disabled = true;
  }

  function decideReport(action) {
    var row = reportState.row;
    if (!row || reportState.submitting) return;
    reportState.submitting = true;
    setReportButtonsDisabled(true);

    var note = rq('[data-admin-report-note]');
    var noteText = note ? note.value.trim() : '';

    reportsApi.decide(reportState.id, action, noteText).then(function (result) {
      if (result.mode === 'forbidden') {
        notify(t('js.no_rights'), 'error');
        setReportsStatus(t('js.no_rights'));
        setReportButtonsDisabled(false);
        return;
      }
      if (result.mode === 'error') {
        notify(result.message, 'error');
        setReportsStatus(result.message);
        setReportButtonsDisabled(false);
        return;
      }

      applyReportDecision(row, action);
      var toast = action === 'delete' ? 'admin.delete_report_success' : 'admin.reject_success';
      closeReportModal();
      notify(toast, 'success');
    }).catch(function (err) {
      console.warn('[admin] report decision failed', err);
      notify(t('errors.common.network_error'), 'error');
      setReportsStatus(t('errors.common.network_error'));
      setReportButtonsDisabled(false);
    }).then(function () {
      reportState.submitting = false;
    });
  }

  /* ============================================================
     ROLES — модалка прав системной роли (admin/roles)
     ============================================================ */
  var ROLE_GROUPS = {
    system: ['manageAccounts', 'manageAdditionals', 'managePeople', 'manageRoles'],
    posts:  ['create', 'update', 'delete'],
    pubs:   ['create', 'updateMeta', 'updateContent', 'delete', 'read', 'managePeople', 'manageRoles']
  };

  var roleState = {
    modal: null,
    titleEl: null,
    loading: null,
    save: null,
    items: [],
    current: null,
    draft: null,
    submitting: false,
    lastFocus: null
  };

  function normalizeRole(raw) {
    if (!raw || typeof raw !== 'object') return null;
    var role = (raw.role && typeof raw.role === 'object') ? raw.role : raw;
    var id = Number(role.id);
    if (!id && id !== 0) return null;
    return {
      id: id,
      title: String(role.title || ''),
      color: normalizeColor(role.color),
      createdAt: String(role.createdAt || ''),
      canManage: !!raw.canManage,
      status: String(role.status || raw.status || ''),
      version: raw.version != null ? raw.version : 1,
      systemPermissions: Object.assign({}, raw.systemPermissions || {}),
      postPermissions: Object.assign({}, raw.postPermissions || {}),
      pubPermissions: Object.assign({}, raw.pubPermissions || {})
    };
  }

  function rolesFrom(payload) {
    var inner = payload;
    if (payload && typeof payload === 'object' && payload.data && typeof payload.data === 'object') {
      inner = payload.data;
    }
    var list = null;
    if (Array.isArray(inner)) list = inner;
    else if (inner && Array.isArray(inner.items)) list = inner.items;
    else if (inner && Array.isArray(inner.data)) list = inner.data;
    if (!list || !list.length) return list ? [] : null;
    var items = list.map(normalizeRole).filter(function (item) { return item !== null; });
    return items.length ? items : null;
  }

  var rolesApi = (function () {
    var BASE = '/api/system/roles';
    var NAME_BASE = '/api/additional/roles';
    var status = 'unknown';

    function table() { return document.querySelector('[data-roles-table]'); }

    function setStatus(next) {
      if (status === next) return;
      status = next;
      var el = table();
      if (el) el.setAttribute('data-roles-api', next);
      if (next === 'mock') {
        console.warn('[admin] ?mock=1 — roles API is bypassed, changes stay local until reload');
      }
    }

    var forceMock = false;
    try { forceMock = new URLSearchParams(window.location.search).has('mock'); } catch (_) {}

    function request(method, path, body, headers, base) {
      if (forceMock) {
        return Promise.resolve({ status: 0, ok: false, data: null, raw: '', isJson: false, headers: null, mocked: true });
      }
      var url = (base || BASE) + (path || '');
      var options = {
        method: method,
        credentials: 'same-origin',
        headers: Object.assign(
          { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          headers || {}
        )
      };
      if (body !== undefined && body !== null) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
      }

      var run = (window.LoreCsrf && LoreCsrf.fetch) ? LoreCsrf.fetch : fetch;
      var result = function (st, ok, raw, hdrs) {
        var data = null;
        var isJson = false;
        if (raw && /^[\s]*[\[{]/.test(raw)) {
          try { data = JSON.parse(raw); isJson = true; } catch (_) { data = null; }
        }
        return { status: st, ok: ok, data: data, raw: raw || '', isJson: isJson, headers: hdrs || null };
      };

      return Promise.resolve()
        .then(function () { return run(url, options); })
        .then(function (res) {
          return res.text().then(
            function (raw) { return result(res.status, res.ok, raw, res.headers); },
            function () { return result(res.status, res.ok, '', res.headers); }
          );
        })
        .catch(function (err) {
          return { status: 0, ok: false, data: null, raw: '', isJson: false, headers: null, networkError: err };
        });
    }

    function classify(r) {
      if (r.mocked) return 'mock';
      if (r.networkError) return 'error';
      var looksHtml = /^\s*</.test(r.raw || '');
      if (r.ok) return looksHtml ? 'error' : 'ok';
      if (r.status === 403) return 'forbidden';
      return 'error';
    }

    function errorText(r) {
      if (r.networkError) return tx('errors.common.network_error', 'Network error. Try again.');
      if (r.ok) return tx('errors.common.server_error', 'Server error. Please try again later.');
      var fallback = null;
      if (window.LoreI18n) fallback = LoreI18n.t('errors.common.request_failed', { status: r.status });
      if (window.Messages && Messages.describe) return Messages.describe(r.status, r.data, r.raw, fallback);
      return fallback || ('HTTP ' + r.status);
    }

    var lastError = '';

    return {
      get status() { return status; },
      get lastError() { return lastError; },

      list: function (query, statusFilter) {
        lastError = '';
        if (status === 'mock') return Promise.resolve(null);
        var parts = [];
        if (query) parts.push('q=' + encodeURIComponent(query));
        if (statusFilter && statusFilter !== 'all') parts.push('status=' + encodeURIComponent(statusFilter));
        var qs = parts.length ? '?' + parts.join('&') : '';
        return request('GET', qs).then(function (r) {
          var kind = classify(r);
          if (kind === 'mock') { setStatus('mock'); return null; }
          if (kind === 'ok') {
            var items = rolesFrom(r.data);
            if (items === null) {
              lastError = tx('errors.common.server_error', 'Server error. Please try again later.');
              return null;
            }
            setStatus('live');
            return items;
          }
          lastError = kind === 'forbidden' ? tx('js.no_rights', 'You have no rights for this action') : errorText(r);
          return null;
        });
      },

      createName: function (body) {
        return request('POST', '', body, null, NAME_BASE).then(function (r) {
          var kind = classify(r);
          if (kind === 'ok') {
            setStatus('live');
            var location = r.headers && r.headers.get ? r.headers.get('Location') : null;
            return { mode: 'api', data: r.data, location: location, status: r.status };
          }
          if (kind === 'mock') { setStatus('mock'); return { mode: 'mock' }; }
          if (kind === 'forbidden') return { mode: 'forbidden', status: r.status };
          return { mode: 'error', status: r.status, message: errorText(r), fields: parseFieldErrors(r.data) };
        });
      },

      create: function (body) {
        return request('POST', '', body).then(function (r) {
          var kind = classify(r);
          if (kind === 'ok') {
            setStatus('live');
            var etag = r.headers && r.headers.get ? r.headers.get('ETag') : null;
            return { mode: 'api', data: r.data, etag: etag, status: r.status };
          }
          if (kind === 'mock') { setStatus('mock'); return { mode: 'mock' }; }
          if (kind === 'forbidden') return { mode: 'forbidden', status: r.status };
          return { mode: 'error', status: r.status, message: errorText(r) };
        });
      },

      save: function (id, version, body) {
        return request('PUT', '/' + id, body, { 'If-Match': '"' + String(version) + '"' })
          .then(function (r) {
            var kind = classify(r);
            if (kind === 'ok') {
              setStatus('live');
              var etag = r.headers && r.headers.get ? r.headers.get('ETag') : null;
              return { mode: 'api', etag: etag, status: r.status };
            }
            if (kind === 'mock') { setStatus('mock'); return { mode: 'mock' }; }
            if (kind === 'forbidden') return { mode: 'forbidden', status: r.status };
            if (r.status === 412) return { mode: 'conflict', status: r.status };
            return { mode: 'error', status: r.status, message: errorText(r) };
          });
      },

      move: function (id, version, body) {
        var headers = (version != null && version !== '') ? { 'If-Match': '"' + String(version) + '"' } : {};
        return request('PATCH', '/' + id + '/position', body, headers).then(function (r) {
          var kind = classify(r);
          if (kind === 'ok') {
            setStatus('live');
            var etag = r.headers && r.headers.get ? r.headers.get('ETag') : null;
            return { mode: 'api', etag: etag, status: r.status };
          }
          if (kind === 'mock') { setStatus('mock'); return { mode: 'mock' }; }
          if (kind === 'forbidden') return { mode: 'forbidden', status: r.status };
          if (r.status === 412) return { mode: 'conflict', status: r.status };
          return { mode: 'error', status: r.status, message: errorText(r) };
        });
      },

      remove: function (id, version) {
        var headers = (version != null && version !== '') ? { 'If-Match': '"' + String(version) + '"' } : {};
        return request('DELETE', '/' + id, null, headers).then(function (r) {
          var kind = classify(r);
          if (kind === 'ok') { setStatus('live'); return { mode: 'api', status: r.status }; }
          if (kind === 'mock') { setStatus('mock'); return { mode: 'mock' }; }
          if (kind === 'forbidden') return { mode: 'forbidden', status: r.status };
          if (r.status === 412) return { mode: 'conflict', status: r.status };
          return { mode: 'error', status: r.status, message: errorText(r) };
        });
      }
    };
  })();

  function roleRoot() { return roleState.modal; }
  function isRoleOpen() { return !!(roleState.modal && !roleState.modal.hidden); }
  function rr(sel) { var m = roleRoot(); return m ? m.querySelector(sel) : null; }

  function findRole(id) {
    for (var i = 0; i < roleState.items.length; i++) {
      if (Number(roleState.items[i].id) === Number(id)) return roleState.items[i];
    }
    return null;
  }

  function countPerms(obj, keys) {
    var n = 0;
    keys.forEach(function (k) { if (obj && obj[k]) n++; });
    return n;
  }

  function totalPerms(item) {
    return countPerms(item.systemPermissions, ROLE_GROUPS.system) +
           countPerms(item.postPermissions,   ROLE_GROUPS.posts) +
           countPerms(item.pubPermissions,    ROLE_GROUPS.pubs);
  }

  function permsLabel(n) {
    var category = n === 1 ? 'one' : 'other';
    try {
      var locale = (window.LoreI18n && LoreI18n.locale) || currentLang();
      category = new Intl.PluralRules(locale).select(n);
    } catch (_) {}

    var fallback = n + (n === 1 ? ' permission' : ' permissions');
    var forms = [category, 'other'];
    for (var i = 0; i < forms.length; i++) {
      var key = 'admin.role_sys_perms_' + forms[i];
      var value = t(key, { n: n });
      if (value && value !== key) return value;
    }
    return fallback;
  }

  function setRoleLoading(on) {
    var el = rr('[data-role-loading]');
    if (el) el.hidden = !on;
    Object.keys(ROLE_GROUPS).forEach(function (g) {
      var wrap = rr('[data-role-group="' + g + '"]');
      if (wrap) wrap.hidden = on;
    });
  }

  function setRoleSaveEnabled(enabled) {
    var btn = rr('[data-role-save]');
    if (btn) btn.disabled = !enabled;
  }

  function renderRoleLists() {
    Object.keys(ROLE_GROUPS).forEach(function (group) {
      var list = rr('[data-role-list="' + group + '"]');
      if (!list) return;
      var draftGroup = (roleState.draft && roleState.draft[group]) || {};
      list.innerHTML = ROLE_GROUPS[group].map(function (key) {
        var id = 'role_' + group + '_' + key;
        return '<label class="admin-role__perm" for="' + id + '">' +
            '<input type="checkbox" id="' + id + '"' +
              ' data-role-perm' +
              ' data-role-group="' + group + '"' +
              ' data-role-key="' + key + '"' +
              (draftGroup[key] ? ' checked' : '') + '>' +
            '<span>' + esc(t('admin.perm_' + key)) + '</span>' +
          '</label>';
      }).join('');
    });
    setRoleLoading(false);
  }

  function updateRoleRow(item) {
    if (allRoles.table) {
      var mirrored = allRoles.items.find(function (r) { return Number(r.id) === Number(item.id); });
      if (mirrored) {
        mirrored.systemPermissions = item.systemPermissions;
        mirrored.postPermissions   = item.postPermissions;
        mirrored.pubPermissions    = item.pubPermissions;
        mirrored.version           = item.version;
        mirrored.permsKnown        = true;
      }
      renderAllRoles();
    }
    var row = document.querySelector('[data-roles-rows] [data-role-row-id="' + item.id + '"]');
    if (!row) return;
    var badge = row.querySelector('[data-role-syscount]');
    if (badge) badge.textContent = permsLabel(totalPerms(item));
  }

  function renderRoleRows(list) {
    var tbody = document.querySelector('[data-roles-rows]');
    if (!tbody) return;
    var emptyRow = tbody.querySelector('[data-admin-empty]');

    var table = document.querySelector('[data-roles-table]');
    if (table) table.setAttribute('data-roles-dnd', 'on');

    var locked = lockedCount();
    var lockEndId = (locked > 0 && locked < roleState.items.length) ? Number(roleState.items[locked - 1].id) : -1;
    var pending = moveQueue.isPending;

    tbody.innerHTML = (list || roleState.items).map(function (item) {
      var id = Number(item.id) || 0;
      var search = String(item.title || '').toLowerCase();
      var isLocked = item.canManage === false;
      var rowClass = 'admin-role-row' +
        (isLocked ? ' is-locked' : '') +
        (id === lockEndId ? ' is-lock-end' : '') +
        (dnd.active && dnd.id === id ? ' is-dragging' : '') +
        (pending(id) ? ' is-pending' : '');

      var handle = isLocked
        ? '<span class="admin-role__handle is-locked" title="' + esc(tx('admin.role_locked', 'Pinned: rank is above yours')) + '" aria-hidden="true">' + GRIP + '</span>'
        : '<button type="button" class="admin-role__handle" data-role-handle' +
            ' title="' + esc(tx('admin.role_drag', 'Drag to reorder')) + '"' +
            ' aria-label="' + esc(tx('admin.role_drag', 'Drag to reorder')) + '">' + GRIP + '</button>';

      return '<tr class="' + rowClass + '" data-role-row-id="' + id + '" data-search="' + esc(search) + '">' +
          '<td class="admin-table__drag" data-col="drag">' + handle + '</td>' +
          '<td class="admin-table__id" data-col="id">' + id + '</td>' +
          '<td data-col="title"><span class="admin-role__title">' +
            (item.color ? '<span class="admin-role__dot" style="background:' + esc(item.color) + '"></span>' : '') +
            esc(item.title) +
          '</span></td>' +
          '<td data-col="perms"><div class="admin-actions">' +
            '<span class="admin-badge admin-badge--accent" data-role-syscount>' +
              esc(permsLabel(totalPerms(item))) +
            '</span>' +
            '<button type="button" class="btn btn--secondary" data-role-edit data-role-id="' + id + '">' +
              esc(t('admin.action_permissions')) +
            '</button>' +
          '</div></td>' +
          '<td data-col="delete"><div class="admin-actions">' +
            '<button type="button" class="btn btn--danger admin-icon-btn" data-role-delete data-role-id="' + id + '"' +
              ' title="' + esc(t('admin.action_delete')) + '" aria-label="' + esc(t('admin.action_delete')) + '"' +
              (item.canManage === false ? ' disabled' : '') + '>' +
              TRASH +
            '</button>' +
          '</div></td>' +
        '</tr>';
    }).join('');

    if (emptyRow && !roleState.items.length) {
      emptyRow.hidden = false;
      tbody.appendChild(emptyRow);
    }
  }

  function deleteRole(btn) {
    var id = btn.getAttribute('data-role-id');
    var item = findRole(id);
    if (!item) return;
    if (item.canManage === false) {
      notify(tx('js.no_rights', 'You do not have permission to do this'), 'error');
      return;
    }

    confirmModal({
      title:       tx('admin.role_delete_title', 'Delete role?'),
      text:        tx('admin.role_delete_text',
                      'The role "' + item.title + '" will be permanently deleted. This action cannot be undone.',
                      { name: item.title }),
      confirmText: tx('admin.action_delete', 'Delete'),
      danger:      true
    }).then(function (confirmed) {
      if (!confirmed) return;

      btn.disabled = true;
      return rolesApi.remove(item.id, item.version).then(function (result) {
        if (result.mode === 'forbidden') {
          notify(tx('js.no_rights', 'You do not have permission to do this'), 'error');
          btn.disabled = false;
          return;
        }
        if (result.mode === 'conflict') {
          notify(tx('admin.role_conflict', 'The role was changed by someone else. Refresh the list and try again'), 'error');
          btn.disabled = false;
          return;
        }
        if (result.mode === 'error') {
          notify(result.message, 'error');
          btn.disabled = false;
          return;
        }

        roleState.items = roleState.items.filter(function (r) { return Number(r.id) !== Number(item.id); });
        renderRoleRows();
        notify(tx('admin.role_deleted', 'Role deleted'), 'success');
      });
    }).catch(function (err) {
      console.warn('[admin] role delete failed', err);
      notify(tx('errors.common.network_error', 'Network error. Check your connection and try again'), 'error');
      btn.disabled = false;
    });
  }

  function openRoleModal(id, stub) {
    if (!roleState.modal) return;
    
    var item = stub || findRole(id);
    if (!item && allRoles && allRoles.items) {
      item = allRoles.items.find(function(r) { return Number(r.id) === Number(id); });
    }
    
    if (!item) return;

    roleState.current = item;
    roleState.draft = {
      system: Object.assign({}, item.systemPermissions),
      posts:  Object.assign({}, item.postPermissions),
      pubs:   Object.assign({}, item.pubPermissions)
    };

    if (roleState.titleEl) {
      roleState.titleEl.textContent = t('admin.role_modal_title') + ' — ' + item.title;
    }

    var dropdown = roleState.modal.querySelector('[data-role-dropdown]');
    if (dropdown) dropdown.hidden = true;
    closeRoleDd();
    roleState.modal.setAttribute('data-role-new', 'false');

    setRoleLoading(true);
    renderRoleLists();
    setRoleSaveEnabled(true);

    roleState.lastFocus = document.activeElement;
    roleState.modal.hidden = false;
    roleState.modal.setAttribute('data-role-id', String(item.id));
    document.body.classList.add('is-modal-open');

    var first = rr('[data-role-perm]');
    if (first) first.focus();
  }

  /* ---------- дропдаун выбора роли (модалка «+» на roles) ---------- */
  function ddEl(sel) { return roleState.modal ? roleState.modal.querySelector(sel) : null; }

  function closeRoleDd() {
    var wrap = ddEl('[data-role-dd]');
    if (!wrap) return;
    wrap.classList.remove('is-open');
    var menu = ddEl('[data-role-dd-menu]');
    if (menu) menu.hidden = true;
    var toggle = ddEl('[data-role-dd-toggle]');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
  }

  function isRoleDdOpen() {
    var wrap = ddEl('[data-role-dd]');
    return !!(wrap && wrap.classList.contains('is-open'));
  }

  function toggleRoleDd() {
    var wrap = ddEl('[data-role-dd]');
    var toggle = ddEl('[data-role-dd-toggle]');
    var menu = ddEl('[data-role-dd-menu]');
    if (!wrap || !toggle || !menu || toggle.disabled) return;
    var open = !wrap.classList.contains('is-open');
    wrap.classList.toggle('is-open', open);
    menu.hidden = !open;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) {
      var search = ddEl('[data-role-dd-search]');
      if (search) search.focus();
    }
  }

  function renderRoleDdValue() {
    var valueEl = ddEl('[data-role-dd-value]');
    if (!valueEl) return;
    var cur = roleState.current;
    if (cur && cur.id) {
      valueEl.classList.remove('is-placeholder');
      valueEl.innerHTML = (cur.color ? '<span class="admin-role__dot" style="background:' + esc(cur.color) + '"></span>' : '') +
        '<span class="admin-role__dd-text">' + esc(cur.title) + '</span>';
    } else {
      valueEl.classList.add('is-placeholder');
      valueEl.textContent = (roleState.ddMessage || tx('admin.role_select_placeholder', 'Choose a role'));
    }
  }

  /** Роли проекта, которых ещё нет в списке (системные уже добавленные скрываем). */
  function freeRoles(names) {
    var taken = {};
    roleState.items.forEach(function (r) { taken[Number(r.id)] = true; });
    return names.filter(function (r) { return !taken[Number(r.id)]; });
  }

  function renderRoleDdMenu() {
    var list = ddEl('[data-role-dd-list]');
    if (!list) return;
    var shown = roleState.ddOptions || [];
    var curId = roleState.current ? Number(roleState.current.id) : 0;
    if (!shown.length) {
      var msg = roleState.ddSearching
        ? tx('common.common.loading', 'Loading...')
        : (roleState.ddQuery
            ? tx('admin.role_dd_nothing', 'Nothing found')
            : (roleState.ddMessage || tx('admin.role_none_available', 'No roles available')));
      list.innerHTML = '<li class="admin-role__dd-note">' + esc(msg) + '</li>';
      return;
    }
    list.innerHTML = shown.map(function (r) {
      var selected = Number(r.id) === curId;
      return '<li role="presentation"><button type="button" class="admin-role__dd-item' + (selected ? ' is-selected' : '') + '"' +
          ' role="option" aria-selected="' + (selected ? 'true' : 'false') + '" data-role-dd-pick="' + r.id + '">' +
          (r.color ? '<span class="admin-role__dot" style="background:' + esc(r.color) + '"></span>' : '') +
          '<span class="admin-role__dd-text">' + esc(r.title) + '</span>' +
        '</button></li>';
    }).join('');
  }

  var roleDdSeq = 0;

  /** Серверный поиск ролей для дропдауна: GET /api/additional/roles?q=… — только по Enter. */
  function searchRoleDd(query) {
    var q = String(query || '').trim();
    if (q === (roleState.ddQuery || '') && !roleState.ddSearching) return;
    roleState.ddQuery = q;
    roleState.ddSearching = true;
    renderRoleDdMenu();
    var seq = ++roleDdSeq;
    allRolesApi.list(q, 'all').then(function (names) {
      if (seq !== roleDdSeq) return;
      roleState.ddSearching = false;
      if (names === null) {
        notify(tx('admin.roles_load_error', 'Failed to load roles'), 'error');
        return;
      }
      roleState.ddOptions = freeRoles(names);
      renderRoleDdMenu();
    });
  }

  function pickRoleDd(id) {
    var cur = roleState.current;
    if (!cur || !cur.attach) return;
    var picked = (roleState.ddOptions || []).filter(function (r) { return Number(r.id) === Number(id); })[0];
    if (!picked) return;
    cur.id = Number(picked.id);
    cur.title = picked.title;
    cur.color = picked.color || '';
    renderRoleDdValue();
    renderRoleDdMenu();
    closeRoleDd();
    setRoleSaveEnabled(true);
  }

  /** «+» на странице roles: выбрать роль проекта из справочника и задать ей права. */
  function openRoleAttach() {
    if (!roleState.modal) return;

    var stub = {
      id: 0, title: '', color: '', canManage: true, status: '', version: null,
      isNew: true, attach: true, insertAt: null,
      systemPermissions: {}, postPermissions: {}, pubPermissions: {}
    };
    roleState.current = stub;
    roleState.draft = { system: {}, posts: {}, pubs: {} };
    roleState.ddOptions = [];
    roleState.ddQuery = '';
    roleState.ddSearching = false;
    roleDdSeq++;
    roleState.ddMessage = tx('common.common.loading', 'Loading...');
    var ddSearchInput = ddEl('[data-role-dd-search]');
    if (ddSearchInput) ddSearchInput.value = '';

    if (roleState.titleEl) roleState.titleEl.textContent = tx('admin.role_attach_title', 'Add role to project');

    var dropdown = ddEl('[data-role-dropdown]');
    if (dropdown) dropdown.hidden = false;
    roleState.modal.setAttribute('data-role-new', 'true');
    var toggle = ddEl('[data-role-dd-toggle]');
    if (toggle) toggle.disabled = true;
    closeRoleDd();
    renderRoleDdValue();
    renderRoleDdMenu();

    renderRoleLists();
    setRoleSaveEnabled(false);

    roleState.lastFocus = document.activeElement;
    roleState.modal.hidden = false;
    roleState.modal.setAttribute('data-role-id', '');
    document.body.classList.add('is-modal-open');

    // Роли, которые отдаёт бэк (GET /api/additional/roles), без уже добавленных в проект
    Promise.all([allRolesApi.list('', 'all'), rolesApi.list()]).then(function (res) {
      if (roleState.current !== stub) return;
      var names = res[0], system = res[1];
      if (system) roleState.items = system;
      if (toggle) toggle.disabled = false;

      if (names === null) {
        roleState.ddMessage = tx('admin.roles_load_error', 'Failed to load roles');
      } else {
        roleState.ddOptions = freeRoles(names);
        roleState.ddMessage = roleState.ddOptions.length ? '' : tx('admin.role_none_available', 'No roles available');
      }
      renderRoleDdValue();
      renderRoleDdMenu();
      if (toggle) toggle.focus();
    });
  }

  function closeRoleModal() {
    if (!roleState.modal || roleState.modal.hidden) return;
    closeRoleDd();
    roleState.modal.hidden = true;
    roleState.modal.setAttribute('data-role-id', '');
    document.body.classList.remove('is-modal-open');
    var skipped = !!(roleState.current && roleState.current.isNew && !roleState.current.attach);
    roleState.current = null;
    roleState.draft = null;
    roleState.submitting = false;
    setRoleSaveEnabled(true);
    if (skipped) notify(tx('admin.role_perms_skipped', 'The role name is saved, but its permissions are not set yet'), 'warning');

    var target = roleState.lastFocus;
    roleState.lastFocus = null;
    if (target && target.isConnected && target.offsetParent !== null) {
      try { target.focus(); } catch (_) {}
    }
  }

  function saveRole() {
    var item = roleState.current;
    if (!item || roleState.submitting) return;
    if (item.attach && !item.id) {
      notify(tx('admin.role_select_placeholder', 'Choose a role'), 'error');
      return;
    }
    roleState.submitting = true;
    setRoleSaveEnabled(false);

    var full = function (group) {
      var out = {};
      ROLE_GROUPS[group].forEach(function (key) { out[key] = !!(roleState.draft[group] && roleState.draft[group][key]); });
      return out;
    };
    var body = {
      systemPermissions: full('system'),
      postPermissions:   full('posts'),
      pubPermissions:    full('pubs')
    };

    var call = item.isNew
      ? rolesApi.create(newRoleRequest(item, body))
      : rolesApi.save(item.id, item.version, body);

    call.then(function (result) {
      if (result.mode === 'forbidden') { notify(t('js.no_rights'), 'error'); return; }
      if (result.mode === 'conflict')  { notify(t('admin.role_conflict'), 'error'); return; }
      if (result.mode === 'error')     { notify(result.message, 'error'); return; }
      if (item.isNew) return finishNewRole(item, body, result);

      item.systemPermissions = body.systemPermissions;
      item.postPermissions   = body.postPermissions;
      item.pubPermissions    = body.pubPermissions;

      var parsed = result.etag ? parseInt(String(result.etag).replace(/[^0-9]/g, ''), 10) : NaN;
      item.version = isNaN(parsed) ? (Number(item.version) + 1) : parsed;

      updateRoleRow(item);
      closeRoleModal();
      notify(t('admin.role_saved'), 'success');
    })
    .catch(function (err) {
      console.warn('[admin] role save failed', err);
      notify(t('errors.common.network_error'), 'error');
    })
    .then(function () {
      roleState.submitting = false;
      setRoleSaveEnabled(!(roleState.current && roleState.current.attach && !roleState.current.id));
    });
  }

  function setRolesStatus(text) {
    var el = document.querySelector('[data-roles-status]');
    if (!el) return;
    el.textContent = text || '';
    el.hidden = !text;
  }

  function loadRoles() {
    setRolesStatus(tx('admin.roles_loading', 'Loading roles…'));
    var emptyRow = document.querySelector('[data-roles-rows] [data-admin-empty]');
    if (emptyRow) emptyRow.hidden = true;
    return rolesApi.list().then(function (items) {
      if (items === null) {
        setRolesStatus(rolesApi.status === 'mock' ? '' :
          (tx('admin.roles_load_error', 'Failed to load roles') + (rolesApi.lastError ? ': ' + rolesApi.lastError : '')));
        renderRoleRows();
        return;
      }
      roleState.items = items;
      setRolesStatus('');
      renderRoleRows();
    });
  }

  /* ============================================================
     ROLE USER ASSIGN MODAL — назначение ролей пользователям
     ============================================================ */
  var roleUserAssign = {
    modal: null,
    form: null,
    select: null,
    rolesList: null,
    loading: null,
    saveBtn: null,
    errorEl: null,
    users: [],
    initialUserRoles: []
  };

  function initRoleUserModal() {
    var m = document.querySelector('[data-role-user-modal]');
    if (!m) return;

    roleUserAssign.modal = m;
    roleUserAssign.form = m.querySelector('[data-role-user-form]');
    roleUserAssign.select = m.querySelector('[data-role-user-select]');
    roleUserAssign.rolesList = m.querySelector('[data-role-user-roles-list]');
    roleUserAssign.loading = m.querySelector('[data-role-user-loading]');
    roleUserAssign.saveBtn = m.querySelector('[data-role-user-save]');
    roleUserAssign.errorEl = m.querySelector('[data-role-user-error]');

    if (roleUserAssign.select) {
      roleUserAssign.select.addEventListener('change', function () {
        var userId = roleUserAssign.select.value;
        if (!userId) {
          if (roleUserAssign.saveBtn) roleUserAssign.saveBtn.disabled = true;
          uncheckAllUserRoles();
          return;
        }
        loadUserCurrentRoles(userId);
      });
    }

    if (roleUserAssign.form) {
      roleUserAssign.form.addEventListener('submit', function (e) {
        e.preventDefault();
        saveUserRoles();
      });
    }
  }

  function uncheckAllUserRoles() {
    if (!roleUserAssign.rolesList) return;
    Array.prototype.forEach.call(roleUserAssign.rolesList.querySelectorAll('input[type="checkbox"]'), function (cb) {
      cb.checked = false;
    });
  }

  function renderModalRolesCheckboxes(preselectedRoleId) {
    if (!roleUserAssign.rolesList) return;
    roleUserAssign.rolesList.innerHTML = roleState.items.map(function (r) {
      var isChecked = preselectedRoleId && Number(preselectedRoleId) === Number(r.id);
      return '<label class="admin-role__perm" for="user_role_' + r.id + '">' +
          '<input type="checkbox" id="user_role_' + r.id + '" value="' + r.id + '"' + (isChecked ? ' checked' : '') + '>' +
          (r.color ? '<span class="admin-role__dot" style="background:' + esc(r.color) + '"></span> ' : '') +
          '<span>' + esc(r.title) + '</span>' +
        '</label>';
    }).join('');
  }

  function fetchUsersList() {
    if (roleUserAssign.users.length) return Promise.resolve(roleUserAssign.users);
    var run = (window.LoreCsrf && LoreCsrf.fetch) ? LoreCsrf.fetch : fetch;
    return run('/api/users', { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var items = (data && data.items) ? data.items : (Array.isArray(data) ? data : []);
        roleUserAssign.users = items.map(function (u) {
          return {
            id: u.id,
            name: u.username || u.name || u.login || u.email || ('User #' + u.id)
          };
        });
        return roleUserAssign.users;
      }).catch(function () { return []; });
  }

  function openRoleUserModal(preselectedRoleId) {
    if (!roleUserAssign.modal) return;

    renderModalRolesCheckboxes(preselectedRoleId);
    if (roleUserAssign.errorEl) roleUserAssign.errorEl.hidden = true;
    if (roleUserAssign.saveBtn) roleUserAssign.saveBtn.disabled = true;

    fetchUsersList().then(function (users) {
      if (!roleUserAssign.select) return;
      roleUserAssign.select.innerHTML = '<option value="">— ' + esc(tx('admin.role_choose_user', 'Choose a user')) + ' —</option>' +
        users.map(function (u) {
          return '<option value="' + u.id + '">' + esc(u.name) + ' (ID: ' + u.id + ')</option>';
        }).join('');
    });

    roleUserAssign.modal.hidden = false;
    document.body.classList.add('is-modal-open');
  }

  function closeRoleUserModal() {
    if (!roleUserAssign.modal) return;
    roleUserAssign.modal.hidden = true;
    document.body.classList.remove('is-modal-open');
  }

  function loadUserCurrentRoles(userId) {
    if (roleUserAssign.loading) roleUserAssign.loading.hidden = false;
    if (roleUserAssign.saveBtn) roleUserAssign.saveBtn.disabled = true;
    var run = (window.LoreCsrf && LoreCsrf.fetch) ? LoreCsrf.fetch : fetch;
    run('/api/system/users/' + userId + '/roles', { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        var roles = (res && res.items) ? res.items : (Array.isArray(res) ? res : []);
        roleUserAssign.initialUserRoles = roles.map(function (x) {
          return Number(x.id !== undefined ? x.id : (x.roleId !== undefined ? x.roleId : x));
        });
        Array.prototype.forEach.call(roleUserAssign.rolesList.querySelectorAll('input[type="checkbox"]'), function (cb) {
          cb.checked = roleUserAssign.initialUserRoles.indexOf(Number(cb.value)) !== -1;
        });
      })
      .catch(function () {})
      .then(function () {
        if (roleUserAssign.loading) roleUserAssign.loading.hidden = true;
        if (roleUserAssign.saveBtn) roleUserAssign.saveBtn.disabled = false;
      });
  }

  function saveUserRoles() {
    var userId = roleUserAssign.select.value;
    if (!userId) return;

    roleUserAssign.saveBtn.disabled = true;
    var checkedBoxes = roleUserAssign.rolesList.querySelectorAll('input[type="checkbox"]:checked');
    var currentSelected = Array.prototype.map.call(checkedBoxes, function (cb) { return Number(cb.value); });

    var toAdd = currentSelected.filter(function (id) { return roleUserAssign.initialUserRoles.indexOf(id) === -1; });
    var toRemove = roleUserAssign.initialUserRoles.filter(function (id) { return currentSelected.indexOf(id) === -1; });

    var run = (window.LoreCsrf && LoreCsrf.fetch) ? LoreCsrf.fetch : fetch;
    var requests = [];

    toAdd.forEach(function (roleId) {
      requests.push(run('/api/system/users/' + userId + '/roles', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ roleId: roleId })
      }));
    });

    toRemove.forEach(function (roleId) {
      requests.push(run('/api/system/users/' + userId + '/roles/' + roleId, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json' }
      }));
    });

    Promise.all(requests).then(function () {
      notify(tx('admin.role_saved', 'Roles updated'), 'success');
      closeRoleUserModal();
    }).catch(function () {
      notify(tx('errors.common.request_failed', 'Request failed'), 'error');
    }).then(function () {
      if (roleUserAssign.saveBtn) roleUserAssign.saveBtn.disabled = false;
    });
  }

  /* ============================================================
     ROLES DND — порядок ролей
     ============================================================ */
  var DND_EDGE = 10;
  var DND_SCROLL_ZONE = 64;
  var DND_SCROLL_STEP = 14;
  var GRIP = '\u283F';
  /** Иконка мусорки для кнопок удаления (текст остаётся в title/aria-label). */
  var TRASH = '<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false">' +
    '<path d="M2.5 4h11M6 4V2.5h4V4M4 4l.7 9a1 1 0 0 0 1 .9h4.6a1 1 0 0 0 1-.9L12 4M6.5 7v4M9.5 7v4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';

  var dnd = {
    wrap: null, line: null, plus: null,
    active: false, id: null, raw: -1, lastY: 0, offsetY: 0, pointerId: null,
    ghost: null, raf: 0,
    insertAt: null
  };

  function roleIndex(id) {
    for (var i = 0; i < roleState.items.length; i++) {
      if (Number(roleState.items[i].id) === Number(id)) return i;
    }
    return -1;
  }

  function lockedCount() {
    var n = 0;
    while (n < roleState.items.length && roleState.items[n].canManage === false) n++;
    return n;
  }

  function isBaseRole(item) {
    return !!item && Number(item.id) === 0;
  }

  /** Раньше базовая роль (id 0) была прибита в конец; теперь двигается как все, хвоста нет. */
  function tailCount() {
    return 0;
  }

  function neighborsAt(list, index) {
    return {
      aboveId: index > 0 ? list[index - 1].id : null,
      belowId: index < list.length - 1 ? list[index + 1].id : null
    };
  }

  function neighborsOf(id) {
    var i = roleIndex(id);
    return i < 0 ? null : neighborsAt(roleState.items, i);
  }

  function footprint(id, n) {
    var out = [Number(id)];
    if (n.aboveId != null) out.push(Number(n.aboveId));
    if (n.belowId != null) out.push(Number(n.belowId));
    return out;
  }

  function moveBody(n) {
    return {
      aboveId: n.aboveId != null ? n.aboveId : null,
      belowId: n.belowId != null ? n.belowId : null
    };
  }

  function nextVersion(etag, current) {
    var parsed = etag ? parseInt(String(etag).replace(/[^0-9]/g, ''), 10) : NaN;
    return isNaN(parsed) ? (Number(current) + 1) : parsed;
  }

  function rolesStatusValue() {
    var el = document.querySelector('[data-roles-filter-status] input:checked');
    return el && el.value ? el.value : 'all';
  }

  function filterRolesByStatus(items, status) {
    if (!status || status === 'all') return items;
    return items.filter(function (item) { return !item.status || item.status === status; });
  }

  function applyRolesFilters(items, q) {
    return filterRolesByStatus(filterRolesByTitle(items, q), rolesStatusValue());
  }

  function rerenderRoles() {
    renderRoleRows(applyRolesFilters(roleState.items, searchState ? searchState.serverQuery : ''));
  }

  function dndOn() {
    return true; // На roles.php фильтры убраны, список всегда полный
  }

  function rollbackMove(entry) {
    var items = roleState.items;
    var idx = roleIndex(entry.id);
    if (idx < 0) return;
    var item = items.splice(idx, 1)[0];

    var at = -1;
    if (entry.prevAboveId != null) { var a = roleIndex(entry.prevAboveId); if (a >= 0) at = a + 1; }
    if (at < 0 && entry.prevBelowId != null) { var b = roleIndex(entry.prevBelowId); if (b >= 0) at = b; }
    if (at < 0) at = entry.prevIndex;
    at = Math.max(lockedCount(), Math.min(items.length - tailCount(), at));

    items.splice(at, 0, item);
    rerenderRoles();
  }

  var moveQueue = (function () {
    var inflight = [];
    var waiting = [];
    var idleCallbacks = [];
    var hadFailure = false;

    function mark(set, ids) { ids.forEach(function (id) { set[id] = true; }); }
    function hits(set, ids) { return ids.some(function (id) { return set[id] === true; }); }

    function pendingIds() {
      var set = {};
      inflight.concat(waiting).forEach(function (e) { set[e.id] = true; });
      return set;
    }

    function markRows() {
      var set = pendingIds();
      Array.prototype.forEach.call(document.querySelectorAll('[data-roles-rows] [data-role-row-id]'), function (row) {
        row.classList.toggle('is-pending', set[row.getAttribute('data-role-row-id')] === true);
      });
    }

    function failMessage(result) {
      if (result.mode === 'forbidden') return tx('js.no_rights', 'You do not have permission to do this');
      if (result.mode === 'conflict')  return tx('admin.role_conflict', 'The role was changed by someone else. Refresh the list and try again');
      return result.message || tx('admin.role_move_failed', 'Could not move the role');
    }

    function send(entry) {
      var item = findRole(entry.id);
      var version = item ? item.version : null;

      rolesApi.move(entry.id, version, moveBody(entry.body)).then(function (result) {
        inflight.splice(inflight.indexOf(entry), 1);

        if (result.mode === 'api' || result.mode === 'mock') {
          var current = findRole(entry.id);
          if (current) current.version = nextVersion(result.etag, current.version);
        } else {
          hadFailure = true;
          rollbackMove(entry);
          notify(failMessage(result), 'error');
        }
        pump();
      });
    }

    function pump() {
      var blocked = {};
      inflight.forEach(function (e) { mark(blocked, e.footprint); });

      var rest = [];
      var toSend = [];
      waiting.forEach(function (e) {
        var n = neighborsOf(e.id);
        if (!n) return;
        var fp = footprint(e.id, n);
        var mustWait = hits(blocked, fp);
        mark(blocked, fp);
        if (mustWait) { rest.push(e); return; }
        e.footprint = fp;
        e.body = n;
        inflight.push(e);
        toSend.push(e);
      });
      waiting = rest;
      markRows();
      toSend.forEach(send);
      settle();
    }

    function settle() {
      if (inflight.length || waiting.length) return;
      if (hadFailure) { hadFailure = false; resyncRoles(); }
      var cbs = idleCallbacks;
      idleCallbacks = [];
      cbs.forEach(function (cb) { cb(); });
    }

    return {
      enqueue: function (entry) { waiting.push(entry); pump(); },
      busy: function () { return inflight.length > 0 || waiting.length > 0; },
      isPending: function (id) { return pendingIds()[Number(id)] === true; },
      onIdle: function (cb) { if (this.busy()) idleCallbacks.push(cb); else cb(); },
      markRows: markRows
    };
  })();

  function resyncRoles() {
    if (allRoles.table) { refreshAllRoles(); return; }
    rolesApi.list().then(function (items) {
      if (!items || moveQueue.busy()) return;
      roleState.items = items;
      rerenderRoles();
    });
  }

  function commitMove(id, to) {
    var items = roleState.items;
    var from = roleIndex(id);
    if (from < 0) return false;
    var locked = lockedCount();
    if (items[from].canManage === false) return false;
    to = Math.max(locked, Math.min(items.length - 1 - tailCount(), to));
    if (to === from) return false;

    var prev = neighborsAt(items, from);
    var item = items.splice(from, 1)[0];
    items.splice(to, 0, item);
    rerenderRoles();
    moveQueue.enqueue({ id: item.id, prevAboveId: prev.aboveId, prevBelowId: prev.belowId, prevIndex: from });
    return true;
  }

  function placeCreatedRole(item, insertAt) {
    var items = roleState.items;
    var cur = roleIndex(item.id);
    if (cur < 0) return;
    var prev = neighborsAt(items, cur);
    items.splice(cur, 1);

    var at = -1;
    if (insertAt.aboveId != null) { var a = roleIndex(insertAt.aboveId); if (a >= 0) at = a + 1; }
    if (at < 0 && insertAt.belowId != null) { var b = roleIndex(insertAt.belowId); if (b >= 0) at = b; }
    if (at < 0) at = cur;
    at = Math.max(lockedCount(), Math.min(items.length - tailCount(), at));

    items.splice(at, 0, item);
    if (at === cur) return;
    moveQueue.enqueue({ id: item.id, prevAboveId: prev.aboveId, prevBelowId: prev.belowId, prevIndex: cur });
  }

  function dndRows() {
    return Array.prototype.filter.call(
      document.querySelectorAll('[data-roles-rows] [data-role-row-id]'),
      function (row) { return !row.hidden; }
    );
  }

  function rawIndexAt(rows, y) {
    for (var i = 0; i < rows.length; i++) {
      var r = rows[i].getBoundingClientRect();
      if (y < r.top + r.height / 2) return i;
    }
    return rows.length;
  }

  function boundaryY(rows, idx) {
    if (!rows.length) return 0;
    if (idx <= 0) return rows[0].getBoundingClientRect().top;
    return rows[Math.min(idx, rows.length) - 1].getBoundingClientRect().bottom;
  }

  function wrapTop(y, half) {
    var rect = dnd.wrap.getBoundingClientRect();
    var top = y - rect.top - dnd.wrap.clientTop + dnd.wrap.scrollTop;
    var table = dnd.wrap.querySelector('table');
    var content = table ? table.offsetTop + table.offsetHeight : dnd.wrap.clientHeight;
    var max = content - half; // граница ≤ низа таблицы, так что кружок стоит по центру линии
    return Math.max(0, Math.min(max, top - half));
  }

  function showLine(rows, idx) {
    if (!dnd.line) return;
    dnd.line.style.top = wrapTop(boundaryY(rows, idx), 1) + 'px';
    dnd.line.style.left = dnd.wrap.scrollLeft + 'px';
    dnd.line.hidden = false;
  }

  function hideLine() { if (dnd.line) dnd.line.hidden = true; }

  function hideInsert() {
    if (dnd.plus) {
      dnd.plus.hidden = true;
      dnd.plus.style.display = 'none'; 
    }
    dnd.insertAt = null;
    if (dnd.wrap) dnd.wrap.classList.remove('is-insert-tail');
    if (!dnd.active) hideLine();
  }

  /** Вставка роли «в это место» отключена на мобильной раскладке (≤720px) — там карточки. */
  function insertDisabled() {
    return !!(window.matchMedia && window.matchMedia('(max-width: 720px)').matches);
  }

  function onWrapPointerMove(e) {
    if (dnd.active || e.pointerType !== 'mouse') return;
    if (insertDisabled()) { hideInsert(); return; }
    if (e.target.closest && e.target.closest('[data-role-insert]')) return;
    if (!dndOn()) { hideInsert(); return; }

    var rows = dndRows();
    if (!rows.length) { hideInsert(); return; }

    var best = -1;
    var bestDist = DND_EDGE + 1;
    for (var b = 0; b <= rows.length; b++) {
      var d = Math.abs(e.clientY - boundaryY(rows, b));
      if (d < bestDist) { bestDist = d; best = b; }
    }
    if (best < 0 || best < lockedCount() || best > rows.length - tailCount()) { hideInsert(); return; }

    dnd.insertAt = {
      aboveId: best > 0 ? Number(rows[best - 1].getAttribute('data-role-row-id')) : null,
      belowId: best < rows.length ? Number(rows[best].getAttribute('data-role-row-id')) : null
    };
    // нижняя граница: добавляем место под таблицей, чтобы кружок «+» влез целиком
    dnd.wrap.classList.toggle('is-insert-tail', best === rows.length);
    dnd.plus.style.top = wrapTop(boundaryY(rows, best), 12) + 'px';
    dnd.plus.style.left = (dnd.wrap.scrollLeft + Math.max(0, (dnd.wrap.clientWidth - 24) / 2)) + 'px';
    dnd.plus.style.display = 'flex';
    dnd.plus.hidden = false;
    showLine(rows, best);
  }

  function makeGhost(row, y) {
    var rect = row.getBoundingClientRect();
    var ghost = document.createElement('div');
    ghost.className = 'admin-role-ghost';
    ghost.setAttribute('aria-hidden', 'true');
    ghost.style.width = rect.width + 'px';
    ghost.style.left = rect.left + 'px';
    ghost.style.top = rect.top + 'px';
    var title = row.querySelector('.admin-role__title');
    ghost.innerHTML = '<span class="admin-role-ghost__grip">' + GRIP + '</span>' + (title ? title.outerHTML : '');
    document.body.appendChild(ghost);
    dnd.ghost = ghost;
    dnd.offsetY = y - rect.top;
  }

  function dndFrame() {
    dnd.raf = 0;
    if (!dnd.active) return;
    var y = dnd.lastY;

    if (dnd.ghost) dnd.ghost.style.top = (y - dnd.offsetY) + 'px';

    var rows = dndRows();
    var raw = rawIndexAt(rows, y);
    var from = roleIndex(dnd.id);
    var valid = from >= 0 && raw >= lockedCount() && raw <= rows.length - tailCount() &&
                raw !== from && raw !== from + 1;
    dnd.raw = valid ? raw : -1;
    if (valid) showLine(rows, raw); else hideLine();

    var dy = 0;
    if (y < DND_SCROLL_ZONE) dy = -DND_SCROLL_STEP;
    else if (y > window.innerHeight - DND_SCROLL_ZONE) dy = DND_SCROLL_STEP;
    if (dy) {
      window.scrollBy(0, dy);
      dnd.raf = window.requestAnimationFrame(dndFrame);
    }
  }

  function scheduleFrame() {
    if (!dnd.raf) dnd.raf = window.requestAnimationFrame(dndFrame);
  }

  function onDragMove(e) {
    if (!dnd.active || e.pointerId !== dnd.pointerId) return;
    e.preventDefault();
    dnd.lastY = e.clientY;
    scheduleFrame();
  }

  function onDragUp(e) {
    if (!dnd.active || e.pointerId !== dnd.pointerId) return;
    dnd.lastY = e.clientY;
    dndFrame();
    endDrag(e.type !== 'pointercancel');
  }

  function onDragKey(e) {
    if (dnd.active && e.key === 'Escape') { e.preventDefault(); endDrag(false); }
  }

  function endDrag(commit) {
    var id = dnd.id;
    var raw = dnd.raw;

    document.removeEventListener('pointermove', onDragMove);
    document.removeEventListener('pointerup', onDragUp);
    document.removeEventListener('pointercancel', onDragUp);
    document.removeEventListener('keydown', onDragKey, true);
    if (dnd.raf) { window.cancelAnimationFrame(dnd.raf); dnd.raf = 0; }
    try { dnd.wrap.releasePointerCapture(dnd.pointerId); } catch (_) {}
    if (dnd.ghost && dnd.ghost.parentNode) dnd.ghost.parentNode.removeChild(dnd.ghost);
    dnd.ghost = null;
    hideLine();
    document.body.classList.remove('is-role-dragging');
    Array.prototype.forEach.call(document.querySelectorAll('[data-roles-rows] .is-dragging'), function (row) {
      row.classList.remove('is-dragging');
    });

    dnd.active = false;
    dnd.id = null;
    dnd.raw = -1;

    if (commit && raw >= 0) {
      var from = roleIndex(id);
      if (from >= 0) commitMove(id, raw > from ? raw - 1 : raw);
    }
  }

  function startDrag(id, e) {
    var row = document.querySelector('[data-roles-rows] [data-role-row-id="' + id + '"]');
    if (!row) return;

    hideInsert();
    dnd.active = true;
    dnd.id = Number(id);
    dnd.raw = -1;
    dnd.pointerId = e.pointerId;
    dnd.lastY = e.clientY;

    makeGhost(row, e.clientY);
    row.classList.add('is-dragging');
    document.body.classList.add('is-role-dragging');
    try { dnd.wrap.setPointerCapture(e.pointerId); } catch (_) {}

    document.addEventListener('pointermove', onDragMove, { passive: false });
    document.addEventListener('pointerup', onDragUp);
    document.addEventListener('pointercancel', onDragUp);
    document.addEventListener('keydown', onDragKey, true);
  }

  function onWrapPointerDown(e) {
    var handle = e.target.closest && e.target.closest('[data-role-handle]');
    if (!handle || dnd.active) return;
    if (e.pointerType === 'mouse' && e.button !== 0) return;
    if (!dndOn()) return;

    var row = handle.closest('[data-role-row-id]');
    var id = row ? Number(row.getAttribute('data-role-row-id')) : NaN;
    var item = isNaN(id) ? null : findRole(id);
    if (!item || item.canManage === false) return;

    e.preventDefault();
    startDrag(id, e);
  }

  function onWrapKeyDown(e) {
    var handle = e.target.closest && e.target.closest('[data-role-handle]');
    if (!handle || (e.key !== 'ArrowUp' && e.key !== 'ArrowDown')) return;
    e.preventDefault();
    if (!dndOn()) return;

    var row = handle.closest('[data-role-row-id]');
    var id = row ? Number(row.getAttribute('data-role-row-id')) : NaN;
    var from = isNaN(id) ? -1 : roleIndex(id);
    if (from < 0) return;

    if (commitMove(id, e.key === 'ArrowUp' ? from - 1 : from + 1)) {
      var again = document.querySelector('[data-roles-rows] [data-role-row-id="' + id + '"] [data-role-handle]');
      if (again) again.focus();
    }
  }

  function initRoleDnd() {
    var table = document.querySelector('[data-roles-table]');
    var wrap = table ? table.closest('.admin-table-wrap') : null;
    if (!wrap) return;
    dnd.wrap = wrap;

    dnd.line = document.createElement('div');
    dnd.line.className = 'admin-role-dropline';
    dnd.line.hidden = true;
    wrap.appendChild(dnd.line);

    dnd.plus = document.createElement('button');
    dnd.plus.type = 'button';
    dnd.plus.className = 'admin-role-insert';
    dnd.plus.setAttribute('data-role-insert', '');
    dnd.plus.setAttribute('aria-label', tx('admin.role_insert_here', 'Add a role here'));
    dnd.plus.title = tx('admin.role_insert_here', 'Add a role here');
    dnd.plus.innerHTML = '<svg viewBox="0 0 12 12" width="12" height="12" aria-hidden="true" focusable="false">' +
      '<path d="M6 1.5v9M1.5 6h9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
    dnd.plus.style.display = 'none'; 
    dnd.plus.hidden = true;
    wrap.appendChild(dnd.plus);

    wrap.addEventListener('pointerdown', onWrapPointerDown);
    wrap.addEventListener('pointermove', onWrapPointerMove);
    wrap.addEventListener('pointerleave', function () { if (!dnd.active) hideInsert(); });
    wrap.addEventListener('keydown', onWrapKeyDown);
    wrap.addEventListener('scroll', hideInsert);
    window.addEventListener('scroll', function () { if (!dnd.active) hideInsert(); }, { passive: true });

    var statusSel = document.querySelector('[data-roles-filter-status]');
    if (statusSel) {
      statusSel.addEventListener('change', function () {
        runRolesSearch(searchState ? searchState.serverQuery : '');
      });
    }
  }

  /* ============================================================
     ROLES SEARCH — серверный поиск
     ============================================================ */
  var rolesSearchSeq = 0;
  var ROLES_SEARCH_SERVER = false;

  function filterRolesByTitle(items, q) {
    var low = String(q || '').toLowerCase();
    return items.filter(function (item) {
      return !low || String(item.title || '').toLowerCase().indexOf(low) !== -1;
    });
  }

  function runRolesSearch(query) {
    if (moveQueue.busy()) { moveQueue.onIdle(function () { runRolesSearch(query); }); return; }

    var q = String(query || '').trim();
    var seq = ++rolesSearchSeq;

    rolesApi.list(ROLES_SEARCH_SERVER ? q : '', rolesStatusValue()).then(function (items) {
      if (seq !== rolesSearchSeq) return;

      if (items === null) {
        if (rolesApi.status === 'mock') { renderRoleRows(applyRolesFilters(roleState.items, q)); return; }
        notify(rolesApi.lastError || t('errors.common.network_error'), 'error');
        return;
      }
      roleState.items = items;
      renderRoleRows(ROLES_SEARCH_SERVER ? filterRolesByStatus(items, rolesStatusValue()) : applyRolesFilters(items, q));
    });
  }

  /* ============================================================
     ROLE CREATE — окно «Новая роль» (на all-roles.php)
     ============================================================ */
  var ROLE_COLORS = ['#D64545', '#1C7ED6', '#2F9E44', '#F08C00', '#7048E8', '#0CA678', '#D6336C', '#495057'];

  var roleNew = {
    modal: null, form: null, nameInput: null, list: null, menu: null,
    dd: null, error: null,
    languages: null,
    draft: [],
    errors: { name: '', visibility: '', color: '', langs: {} },
    insertAt: null,
    submitting: false,
    lastFocus: null
  };

  function isRoleNewOpen() { return !!(roleNew.modal && !roleNew.modal.hidden); }

  function rn(sel) { return roleNew.modal ? roleNew.modal.querySelector(sel) : null; }

  function setRoleNewError(text) {
    if (!roleNew.error) return;
    roleNew.error.textContent = text || '';
    roleNew.error.hidden = !text;
  }

  function setRoleNewBusy(busy) {
    roleNew.submitting = busy;
    if (roleNew.form) roleNew.form.classList.toggle('is-busy', busy);
    Array.prototype.forEach.call(roleNew.modal.querySelectorAll('[data-rolenew-submit]'), function (btn) {
      btn.disabled = busy;
    });
  }

  function closeRoleNewMenu() {
    if (!roleNew.dd) return;
    roleNew.dd.classList.remove('is-open');
    if (roleNew.menu) roleNew.menu.hidden = true;
    var toggle = roleNew.dd.querySelector('[data-rolenew-lang-toggle]');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
  }

  function renderRoleNewLangs() {
    if (!roleNew.list) return;
    roleNew.list.innerHTML = roleNew.draft.map(function (row, index) {
      if (index === 0) return '';
      var code = String(row.langCode || '');
      var name = langName(code);
      var langErr = roleNew.errors.langs[index] || '';
      return '<li class="admin-rolenew__lang" data-rolenew-index="' + index + '">' +
          '<span class="admin-badge admin-rolenew__code" title="' + esc(name) + '">' + esc(code) + '</span>' +
          '<input class="form-field__input" type="text" data-rolenew-title maxlength="100" autocomplete="off"' +
            ' value="' + esc(row.title) + '"' +
            ' placeholder="' + esc(name) + '"' +
            ' aria-label="' + esc(name) + '"' + (langErr ? ' aria-invalid="true"' : '') + '>' +
          '<button type="button" class="admin-rolenew__remove" data-rolenew-lang-remove' +
            ' aria-label="' + esc(tx('admin.dict_lang_remove', 'Remove language')) + '">&times;</button>' +
          (langErr ? '<p class="admin-rolenew__field-error" role="alert">' + esc(langErr) + '</p>' : '') +
        '</li>';
    }).join('');
    renderRoleNewMenu();
  }

  function renderRoleNewMenu() {
    if (!roleNew.menu) return;
    var used = roleNew.draft.map(function (row) { return String(row.langCode || '').toLowerCase(); });
    var all = roleNew.languages || [];
    var free = all.filter(function (lang) { return used.indexOf(String(lang.code).toLowerCase()) === -1; });

    if (!free.length) {
      roleNew.menu.innerHTML = '<li class="admin-rolenew__note">' +
        esc(all.length ? tx('admin.dict_lang_all_added', 'All languages added') : tx('admin.dict_lang_none', 'No languages available')) + '</li>';
      return;
    }
    roleNew.menu.innerHTML = free.map(function (lang) {
      return '<li><button type="button" class="admin-rolenew__item" role="option" data-rolenew-lang-pick="' + esc(lang.code) + '">' +
        esc(lang.name || langName(lang.code)) + ' (' + esc(lang.code) + ')</button></li>';
    }).join('');
  }

  function parseFieldErrors(data) {
    var out = [];
    var codeOf = function (c) {
      if (typeof c === 'string') return c;
      if (c && typeof c === 'object') return String(c.message || c.code || c.error || '');
      return '';
    };
    var listOf = function (node) { return node.errors || node.messages || node.codes || node.error || node.message; };
    var add = function (field, value) {
      var list = Array.isArray(value) ? value : (value == null ? [] : [value]);
      var codes = list.map(codeOf).filter(function (c) { return c !== ''; });
      if (codes.length) out.push({ field: String(field || ''), codes: codes });
    };
    var walk = function (prefix, node) {
      if (Array.isArray(node)) {
        if (node.every(function (x) { return typeof x === 'string'; })) { add(prefix, node); return; }
        node.forEach(function (item, i) {
          if (item && typeof item === 'object') walk(prefix ? prefix + '.' + i : String(i), item);
          else add(prefix, item);
        });
        return;
      }
      if (node && typeof node === 'object') {
        if (node.field !== undefined) { add(node.field, listOf(node)); return; }
        Object.keys(node).forEach(function (k) { walk(prefix ? prefix + '.' + k : k, node[k]); });
        return;
      }
      add(prefix, node);
    };

    var root = data;
    if (root && typeof root === 'object' && !Array.isArray(root) &&
        root.field === undefined && root.errors === undefined &&
        root.data && typeof root.data === 'object') {
      root = root.data;
    }
    if (!root || typeof root !== 'object') return out;
    if (!Array.isArray(root) && root.field === undefined) {
      root = root.errors !== undefined ? root.errors
           : (root.error && typeof root.error === 'object' && root.error.details !== undefined) ? root.error.details
           : root.details !== undefined ? root.details
           : null;
    }
    if (root) walk('', root);

    if (!out.length) {
      var scan = function (node) {
        if (Array.isArray(node)) { node.forEach(scan); return; }
        if (!node || typeof node !== 'object') return;
        var field = node.field !== undefined ? node.field
                  : node.name !== undefined ? node.name
                  : node.path !== undefined ? node.path
                  : node.param;
        if (typeof field === 'string') {
          var arr = Object.keys(node).map(function (k) { return node[k]; })
            .filter(function (v) { return Array.isArray(v); })[0];
          add(field, arr !== undefined ? arr : node.message);
          return;
        }
        Object.keys(node).forEach(function (k) { scan(node[k]); });
      };
      scan(data);
    }
    return out;
  }

  function fieldErrorText(code) {
    var key = String(code);
    if (/^errors\.[\w.]+$/.test(key)) {
      var value = t(key);
      if (value && value !== key) return value;
      var last = key.split('.').pop().replace(/_/g, ' ');
      return last.charAt(0).toUpperCase() + last.slice(1);
    }
    return key;
  }

  function mapRoleNewField(field, rows) {
    var f = String(field || '').replace(/\[(\w+)\]/g, '.$1').replace(/^\./, '').toLowerCase();
    if (f === 'visibility') return { type: 'visibility' };
    if (f === 'color') return { type: 'color' };
    if (f === 'title' || f === 'name') return { type: 'name' };
    var m = f.match(/^translations(?:\.([\w-]+))?(?:\.(?:title|name|lang_code|langcode|lang))?$/);
    if (m) {
      var di = 0;
      if (m[1] !== undefined) {
        var sent = /^\d+$/.test(m[1])
          ? rows[Number(m[1])]
          : rows.filter(function (row) { return String(row.langCode).toLowerCase() === m[1]; })[0];
        di = sent ? sent.draftIndex : 0;
      }
      return di === 0 ? { type: 'name' } : { type: 'lang', index: di };
    }
    return null;
  }

  function roleNewFieldHolder(key) {
    if (!roleNew.modal) return null;
    var holder = roleNew.modal.querySelector('[data-rolenew-field-error="' + key + '"]');
    if (holder) return holder;
    var anchor = key === 'name' ? roleNew.nameInput
      : key === 'visibility' ? roleNew.modal.querySelector('.admin-rolenew__pill')
      : roleNew.modal.querySelector('.admin-rolenew__colors');
    if (!anchor) return null;
    holder = document.createElement('p');
    holder.className = 'admin-rolenew__field-error';
    holder.setAttribute('data-rolenew-field-error', key);
    holder.setAttribute('role', 'alert');
    holder.hidden = true;
    anchor.insertAdjacentElement('afterend', holder);
    return holder;
  }

  function setRoleNewFieldError(key, text) {
    var holder = roleNewFieldHolder(key);
    if (holder) { holder.textContent = text || ''; holder.hidden = !text; }
    if (key === 'name' && roleNew.nameInput) {
      if (text) roleNew.nameInput.setAttribute('aria-invalid', 'true');
      else roleNew.nameInput.removeAttribute('aria-invalid');
    }
  }

  function resetRoleNewErrors() {
    roleNew.errors = { name: '', visibility: '', color: '', langs: {} };
    ['name', 'visibility', 'color'].forEach(function (key) { setRoleNewFieldError(key, ''); });
  }

  function showRoleNewErrors(result, rows) {
    var fields = result.fields || [];
    resetRoleNewErrors();
    var general = [];

    fields.forEach(function (fe) {
      var seen = {};
      var parts = [];
      fe.codes.forEach(function (code) {
        var text = fieldErrorText(code);
        if (!seen[text]) { seen[text] = true; parts.push(text); }
      });
      var text = parts.join('. ');
      var target = mapRoleNewField(fe.field, rows);
      if (!target) { general.push((fe.field ? fe.field + ': ' : '') + text); return; }
      if (target.type === 'lang') roleNew.errors.langs[target.index] = text;
      else roleNew.errors[target.type] = roleNew.errors[target.type] ? roleNew.errors[target.type] + ' ' + text : text;
    });

    ['name', 'visibility', 'color'].forEach(function (key) { setRoleNewFieldError(key, roleNew.errors[key]); });
    renderRoleNewLangs();
    setRoleNewError(fields.length ? general.join('\n') : result.message);

    var firstBad = roleNew.modal.querySelector('[aria-invalid="true"]');
    if (firstBad) firstBad.focus();
  }

  function openRoleNew(insertAt) {
    if (!roleNew.modal) return;
    roleNew.insertAt = insertAt || null;
    roleNew.draft = [{ langCode: 'en', title: '' }];
    resetRoleNewErrors();
    roleNew.form.reset();
    setRoleNewError('');
    setRoleNewBusy(false);
    closeRoleNewMenu();
    renderRoleNewLangs();

    roleNew.lastFocus = document.activeElement;
    roleNew.modal.hidden = false;
    document.body.classList.add('is-modal-open');
    if (roleNew.nameInput) roleNew.nameInput.focus();

    loadLanguages().then(function (languages) {
      roleNew.languages = languages;
      renderRoleNewMenu();
    });
  }

  function closeRoleNew() {
    if (!isRoleNewOpen()) return;
    roleNew.modal.hidden = true;
    document.body.classList.remove('is-modal-open');
    closeRoleNewMenu();
    var target = roleNew.lastFocus;
    roleNew.lastFocus = null;
    if (target && target.isConnected && target.offsetParent !== null) {
      try { target.focus(); } catch (_) {}
    }
  }

  function roleNewChoice(name) {
    var el = roleNew.form.querySelector('input[name="' + name + '"]:checked');
    return el ? el.value : '';
  }

  function pickRoleTitle(rows) {
    var lang = currentLang().slice(0, 2).toLowerCase();
    for (var i = 0; i < rows.length; i++) {
      if (String(rows[i].langCode).slice(0, 2).toLowerCase() === lang) return rows[i].title;
    }
    return rows.length ? rows[0].title : '';
  }

  function buildRoleCreateBody(rows) {
    var translations = {};
    rows.forEach(function (row) { translations[row.langCode] = row.title; });
    return {
      translations: translations,
      visibility: roleNewChoice('visibility') || 'public',
      color: colorForApi(roleNewChoice('color') || ROLE_COLORS[0])
    };
  }

  function colorForApi(value) {
    return String(value || '').replace(/^#/, '');
  }

  function normalizeColor(value) {
    var c = String(value || '');
    return /^[0-9a-f]{6}$/i.test(c) ? '#' + c : c;
  }

  function createdRoleId(result) {
    var candidates = [];
    var d = result.data;
    if (d && typeof d === 'object') {
      var inner = (d.data && typeof d.data === 'object') ? d.data : d;
      var node = inner.item || inner.role || inner;
      candidates.push(inner.createdId, node.createdId, node.id, node.roleId, inner.id, inner.roleId);
    }
    if (result.location) {
      var m = String(result.location).match(/(\d+)\/?$/);
      if (m) candidates.push(m[1]);
    }
    for (var i = 0; i < candidates.length; i++) {
      var c = candidates[i];
      if (c == null || c === '') continue;
      var n = Number(c);
      if (isFinite(n) && n > 0) return n;
    }
    return null;
  }

  var SAVE_ONLY_ATTACHES_EMPTY_PERMS = true;

  function emptyPermissionsBody() {
    var names = { system: 'systemPermissions', posts: 'postPermissions', pubs: 'pubPermissions' };
    var out = {};
    Object.keys(ROLE_GROUPS).forEach(function (group) {
      out[names[group]] = {};
      ROLE_GROUPS[group].forEach(function (key) { out[names[group]][key] = false; });
    });
    return out;
  }

  function newRolePosition(insertAt) {
    if (insertAt) {
      return {
        aboveId: insertAt.aboveId != null ? insertAt.aboveId : null,
        belowId: insertAt.belowId != null ? insertAt.belowId : null
      };
    }
    var items = roleState.items;
    var tail = tailCount();
    var last = items.length - tail - 1;
    return {
      aboveId: last >= 0 ? items[last].id : null,
      belowId: tail > 0 ? items[items.length - tail].id : null
    };
  }

  function newRoleRequest(item, perms) {
    return Object.assign({ roleId: item.id }, newRolePosition(item.insertAt), perms);
  }

  function attachEmptyRole(stub) {
    var perms = emptyPermissionsBody();
    return rolesApi.create(newRoleRequest(stub, perms)).then(function (result) {
      if (result.mode === 'forbidden') {
        notify(tx('js.no_rights', 'You do not have permission to do this'), 'error');
      } else if (result.mode === 'error') {
        notify(result.message, 'error');
      } else {
        return finishNewRole(stub, perms, result);
      }
      notify(tx('admin.role_perms_skipped', 'The role name is saved, but its permissions are not set yet'), 'warning');
    }).catch(function (err) {
      console.warn('[admin] attach role failed', err);
      notify(tx('errors.common.network_error', 'Network error. Check your connection and try again'), 'error');
    });
  }

  function newRoleStub(created, body, insertAt) {
    return {
      id: created.id, title: created.title, color: normalizeColor(body.color), createdAt: '',
      canManage: true, status: body.visibility, version: null,
      isNew: true, insertAt: insertAt || null,
      systemPermissions: {}, postPermissions: {}, pubPermissions: {}
    };
  }

  function finishNewRole(item, body, result) {
    item.systemPermissions = body.systemPermissions;
    item.postPermissions   = body.postPermissions;
    item.pubPermissions    = body.pubPermissions;
    item.isNew = false;
    var insertAt = item.insertAt;
    item.insertAt = null;

    var done = function () {
      var searchInput = document.querySelector('[data-admin-search]');
      if (searchInput) searchInput.value = '';
      if (searchState) searchState.serverQuery = '';
      renderRoleRows();
      refreshAllRoles();
      closeRoleModal();
      notify(tx('admin.role_created', 'Role created'), 'success');
    };
    var addLocal = function () {
      item.version = nextVersion(result.etag, 0);
      roleState.items.splice(roleState.items.length - tailCount(), 0, item);
      return item;
    };

    if (result.mode === 'mock') {
      addLocal();
      if (insertAt) placeCreatedRole(item, insertAt);
      done();
      return Promise.resolve();
    }

    return rolesApi.list().then(function (items) {
      var placed = null;
      if (items) {
        roleState.items = items;
        placed = findRole(item.id);
      }
      if (!placed) placed = addLocal();
      done();
    });
  }

  function submitRoleNew() {
    if (roleNew.submitting) return;

    var main = roleNew.draft[0];
    if (!main || !String(main.title || '').trim()) {
      setRoleNewError(tx('errors.roles.name_required', 'Enter the role name'));
      if (roleNew.nameInput) roleNew.nameInput.focus();
      return;
    }
    var rows = roleNew.draft
      .map(function (row, i) { return { draftIndex: i, langCode: String(row.langCode || '').trim(), title: String(row.title || '').trim() }; })
      .filter(function (row) { return row.langCode !== '' && row.title !== ''; });

    setRoleNewError('');
    resetRoleNewErrors();
    setRoleNewBusy(true);
    var body = buildRoleCreateBody(rows);

    rolesApi.createName(body).then(function (result) {
      if (result.mode === 'forbidden') { notify(tx('js.no_rights', 'You do not have permission to do this'), 'error'); return null; }
      if (result.mode === 'error')     { showRoleNewErrors(result, rows); return null; }

      var id;
      if (result.mode === 'mock') {
        id = roleState.items.reduce(function (m, r) { return Math.max(m, Number(r.id) || 0); }, 0) + 1;
      } else {
        id = createdRoleId(result);
        if (id === null) {
          console.warn('[admin] POST /api/additional/roles: could not read the new role id', result);
          setRoleNewError(tx('errors.common.server_error', 'Server error. Please try again later.'));
          return null;
        }
      }
      return { id: id, title: pickRoleTitle(rows) };
    }).then(function (created) {
      if (!created) return;

      var insertAt = roleNew.insertAt;
      roleNew.insertAt = null;
      closeRoleNew();
      var stub = newRoleStub(created, body, insertAt);

      return ensureSystemRoles().then(function (ok) {
        if (!ok) {
          notify(tx('admin.role_name_saved', 'Role name saved'), 'success');
          notify(tx('admin.roles_load_error', 'Failed to load roles') + (rolesApi.lastError ? ': ' + rolesApi.lastError : ''), 'error');
          refreshAllRoles();
          return;
        }
        if (SAVE_ONLY_ATTACHES_EMPTY_PERMS) {
          return attachEmptyRole(stub);
        } else {
          notify(tx('admin.role_name_saved', 'Role name saved'), 'success');
          resyncRoles();
        }
      });
    }).catch(function (err) {
      console.warn('[admin] role create failed', err);
      setRoleNewError(tx('errors.common.network_error', 'Network error. Check your connection and try again'));
    }).then(function () {
      if (roleNew.modal) setRoleNewBusy(false);
    });
  }

  function initRoleNew() {
    if (roleNew.modal) return; // уже инициализировано
    var modal = document.querySelector('[data-rolenew-modal]');
    if (!modal) return;
    roleNew.modal = modal;
    roleNew.form = modal.querySelector('[data-rolenew-form]');
    roleNew.nameInput = modal.querySelector('[data-rolenew-name]');
    roleNew.list = modal.querySelector('[data-rolenew-langs]');
    roleNew.menu = modal.querySelector('[data-rolenew-menu]');
    roleNew.dd = modal.querySelector('[data-rolenew-dd]');
    roleNew.error = modal.querySelector('[data-rolenew-error]');

    roleNew.form.addEventListener('submit', function (e) {
      e.preventDefault();
      submitRoleNew();
    });

    roleNew.form.addEventListener('change', function (e) {
      var radio = e.target && e.target.name;
      if (radio === 'visibility' || radio === 'color') setRoleNewFieldError(radio, '');
    });

    roleNew.form.addEventListener('input', function (e) {
      var main = e.target.closest && e.target.closest('[data-rolenew-name]');
      if (main) { roleNew.draft[0].title = main.value; setRoleNewError(''); setRoleNewFieldError('name', ''); return; }
      var input = e.target.closest && e.target.closest('[data-rolenew-title]');
      if (!input) return;
      var row = input.closest('[data-rolenew-index]');
      var index = row ? Number(row.getAttribute('data-rolenew-index')) : -1;
      if (index > 0 && roleNew.draft[index]) roleNew.draft[index].title = input.value;
      if (row && roleNew.errors.langs[index]) {
        delete roleNew.errors.langs[index];
        var langErrEl = row.querySelector('.admin-rolenew__field-error');
        if (langErrEl) langErrEl.remove();
        input.removeAttribute('aria-invalid');
      }
    });

    document.addEventListener('keydown', function (e) {
      if (!isRoleNewOpen()) return;
      if (e.key === 'Escape') {
        e.preventDefault();
        if (roleNew.dd.classList.contains('is-open')) closeRoleNewMenu();
        else closeRoleNew();
        return;
      }
      if (e.key !== 'Tab') return;
      var dialog = roleNew.modal.querySelector('.modal__dialog');
      if (!dialog) return;
      var items = Array.prototype.filter.call(
        dialog.querySelectorAll('button, [href], input, select, textarea, [tabindex]'),
        function (el) { return !el.disabled && el.offsetParent !== null; }
      );
      if (!items.length) return;
      var first = items[0], last = items[items.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
  }

  function handleRoleNewClick(target) {
    if (target.closest('[data-rolenew-open]')) { openRoleNew(null); return true; }
    if (!isRoleNewOpen()) return false;

    if (target.closest('[data-rolenew-close]')) { closeRoleNew(); return true; }

    var toggle = target.closest('[data-rolenew-lang-toggle]');
    if (toggle) {
      var open = !roleNew.dd.classList.contains('is-open');
      roleNew.dd.classList.toggle('is-open', open);
      roleNew.menu.hidden = !open;
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      return true;
    }

    var pick = target.closest('[data-rolenew-lang-pick]');
    if (pick) {
      roleNew.draft.push({ langCode: pick.getAttribute('data-rolenew-lang-pick'), title: '' });
      closeRoleNewMenu();
      renderRoleNewLangs();
      var inputs = roleNew.list.querySelectorAll('[data-rolenew-title]');
      if (inputs.length) inputs[inputs.length - 1].focus();
      return true;
    }

    var remove = target.closest('[data-rolenew-lang-remove]');
    if (remove) {
      var row = remove.closest('[data-rolenew-index]');
      var index = row ? Number(row.getAttribute('data-rolenew-index')) : -1;
      if (index > 0) { roleNew.draft.splice(index, 1); renderRoleNewLangs(); }
      return true;
    }

    if (target.closest('[data-rolenew-submit]')) {
      return false;
    }

    if (!target.closest('[data-rolenew-dd]')) closeRoleNewMenu();
    return false;
  }

  function initRoles() {
    var table = document.querySelector('[data-roles-table]');
    if (!table) return;

    roleState.items = [];
    // Модалка прав роли (admin-role-modal.php). Назначение роли пользователю —
    // отдельная модалка (admin-role-user-modal.php), с ней не смешиваем.
    roleState.modal = document.querySelector('[data-role-modal]');
    if (roleState.modal) {
      roleState.titleEl = roleState.modal.querySelector('[data-role-title]');
    }

    initRoleNew();
    initRoleDnd();
    loadRoles();
  }

  /* ============================================================
     DICTIONARY — CRUD справочников через реальный роут /api/additional/
     ============================================================ */
  var dict = {
    modal: null,
    form: null,
    rows: null,
    list: null,
    menu: null,
    error: null,
    status: null,
    emptyHint: null,
    titleEl: null,
    resource: '',
    nameOne: '',
    items: [],
    editId: null,
    draft: [],
    languages: null,
    languagesLoading: null,
    submitting: false,
    touched: false,
    lastFocus: null
  };

  var FALLBACK_LANGUAGES = [
    { code: 'en', name: 'English' },
    { code: 'ru', name: 'Русский' },
    { code: 'es', name: 'Español' }
  ];

  var dictApi = (function () {
    var BASE = '/api/additional/';
    var status = 'unknown';

    function table() { return document.querySelector('[data-dict-table]'); }

    function setStatus(next) {
      if (status === next) return;
      status = next;
      var el = table();
      if (el) el.setAttribute('data-dict-api', next);
    }

    function markUnknown() {
      var el = table();
      if (el && status === 'unknown') el.setAttribute('data-dict-api', 'unknown');
    }

    function request(method, path, body) {
      var url = BASE + path;
      var options = {
        method: method,
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      };
      if (body !== undefined && body !== null) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
      }

      var run = (window.LoreCsrf && LoreCsrf.fetch) ? LoreCsrf.fetch : fetch;
      var result = function (st, ok, raw) {
        var data = null;
        var isJson = false;
        if (raw && /^[\s]*[\[{]/.test(raw)) {
          try { data = JSON.parse(raw); isJson = true; } catch (_) { data = null; }
        }
        return { status: st, ok: ok, data: data, raw: raw || '', isJson: isJson };
      };

      return Promise.resolve()
        .then(function () { return run(url, options); })
        .then(function (res) {
          return res.text().then(
            function (raw) { return result(res.status, res.ok, raw); },
            function () { return result(res.status, res.ok, ''); }
          );
        })
        .catch(function (err) {
          return { status: 0, ok: false, data: null, raw: '', isJson: false, networkError: err };
        });
    }

    function classify(r) {
      if (r.networkError) return 'mock';
      var looksHtml = /^\s*</.test(r.raw || '');
      if (r.ok) return looksHtml ? 'mock' : 'ok';
      if (looksHtml || !r.isJson) return 'mock';
      if (r.status === 404 || r.status === 405) return 'mock';
      if (r.status === 403) return 'forbidden';
      return 'error';
    }

    function unwrap(payload) {
      if (payload && typeof payload === 'object' && payload.data && typeof payload.data === 'object') {
        return payload.data;
      }
      return payload;
    }

    function normalizeItem(raw) {
      if (!raw || typeof raw !== 'object') return null;
      var id = Number(raw.id);
      if (!id) return null;
      var translations = Array.isArray(raw.translations) ? raw.translations : [];
      var rows = [];
      translations.forEach(function (row) {
        if (!row || typeof row !== 'object') return;
        var code = String(row.langCode || row.lang_code || row.lang || row.locale || '');
        if (!code) return;
        var title = row.title;
        if (title == null) title = row.name;
        rows.push({ langCode: code, title: String(title == null ? '' : title) });
      });
      return { id: id, translations: rows };
    }

    function itemsFrom(payload) {
      var inner = unwrap(payload);
      var list = null;
      if (Array.isArray(inner)) list = inner;
      else if (inner && Array.isArray(inner.items)) list = inner.items;
      else if (inner && Array.isArray(inner.data)) list = inner.data;
      if (!list || !list.length) return list ? [] : null;
      var items = list.map(normalizeItem).filter(function (item) { return item !== null; });
      return items.length ? items : null;
    }

    function errorText(r) {
      var fallback = null;
      if (window.LoreI18n) fallback = LoreI18n.t('errors.common.request_failed', { status: r.status });
      if (window.Messages && Messages.describe) return Messages.describe(r.status, r.data, r.raw, fallback);
      return fallback || ('HTTP ' + r.status);
    }

    return {
      get status() { return status; },
      markUnknown: markUnknown,

      list: function (resource) {
        if (status === 'mock') return Promise.resolve(null);
        return request('GET', resource).then(function (r) {
          var kind = classify(r);
          if (kind === 'ok') { setStatus('live'); return itemsFrom(r.data); }
          if (kind === 'mock') { setStatus('mock'); return null; }
          setStatus('live');
          console.warn('[admin] GET ' + BASE + resource + ' failed with HTTP ' + r.status);
          return null;
        });
      },

      mutate: function (method, resource, id, body) {
        var path = resource + (id ? '/' + id : '');
        return request(method, path, body).then(function (r) {
          var kind = classify(r);
          if (kind === 'ok') {
            setStatus('live');
            var item = normalizeItem(unwrap(r.data));
            return { mode: 'api', item: item, status: r.status };
          }
          if (kind === 'mock') { setStatus('mock'); return { mode: 'mock' }; }
          if (kind === 'forbidden') return { mode: 'forbidden', status: r.status };
          return { mode: 'error', status: r.status, message: errorText(r) };
        });
      }
    };
  })();

  function translationTitles(item) {
    var translations = (item && item.translations) || [];
    return translations
      .map(function (row) { return String((row && row.title) || '').trim(); })
      .filter(function (title) { return title !== ''; });
  }

  function findItem(id) {
    for (var i = 0; i < dict.items.length; i++) {
      if (Number(dict.items[i].id) === id) return dict.items[i];
    }
    return null;
  }

  function setError(text) {
    if (!dict.error) return;
    dict.error.textContent = text || '';
    dict.error.hidden = !text;
  }

  function setStatus(text) {
    if (!dict.status) return;
    dict.status.textContent = text || '';
    dict.status.hidden = !text;
  }

  function renderRows() {
    if (!dict.rows) return;
    var emptyRow = dict.rows.querySelector('[data-admin-empty]');

    dict.rows.innerHTML = dict.items.map(function (item) {
      var id = Number(item.id) || 0;
      var titles = translationTitles(item);
      var search = titles.join(' ').toLowerCase();

      return '<tr data-dict-row-id="' + id + '" data-search="' + esc(search) + '">' +
          '<td class="admin-table__id">' + id + '</td>' +
          '<td><span class="admin-dict__translations">' + esc(titles.join(' / ')) + '</span></td>' +
          '<td><div class="admin-actions">' +
            '<button type="button" class="btn btn--secondary" data-admin-dict-edit data-dict-row-id="' + id + '">' +
              esc(t('admin.action_edit')) +
            '</button>' +
            '<button type="button" class="btn btn--danger" data-admin-dict-delete data-dict-row-id="' + id + '">' +
              esc(t('admin.action_delete')) +
            '</button>' +
          '</div></td>' +
        '</tr>';
    }).join('');

    if (emptyRow) dict.rows.appendChild(emptyRow);
    applySearch();
  }

  function renderLangs() {
    if (!dict.list) return;

    dict.list.innerHTML = dict.draft.map(function (row, index) {
      var code = String(row.langCode || '');
      var name = langName(code);

      return '<li class="admin-dict__lang-row" data-lang="' + esc(code) + '" data-lang-index="' + index + '">' +
          '<span class="admin-badge admin-dict__lang-code" title="' + esc(name) + '">' + esc(code) + '</span>' +
          '<div class="form-field admin-dict__lang-field">' +
            '<input class="form-field__input" type="text" data-lang-title autocomplete="off"' +
              ' value="' + esc(row.title) + '"' +
              ' placeholder="' + esc(t('admin.col_title')) + '"' +
              ' aria-label="' + esc(name + ': ' + t('admin.col_title')) + '">' +
          '</div>' +
          '<button type="button" class="admin-dict__lang-remove" data-dict-lang-remove' +
            ' title="' + esc(t('admin.dict_lang_remove')) + '"' +
            ' aria-label="' + esc(t('admin.dict_lang_remove')) + '">&times;</button>' +
        '</li>';
    }).join('');

    if (dict.emptyHint) dict.emptyHint.hidden = dict.draft.length > 0;
    renderLangMenu();
  }

  function renderLangMenu() {
    if (!dict.menu) return;

    var used = dict.draft.map(function (row) { return String(row.langCode || '').toLowerCase(); });
    var all = dict.languages || [];
    var free = all.filter(function (lang) {
      return used.indexOf(String(lang.code).toLowerCase()) === -1;
    });

    if (!all.length) {
      dict.menu.innerHTML = '<li class="admin-dict__menu-note">' + esc(t('admin.dict_lang_none')) + '</li>';
      return;
    }
    if (!free.length) {
      dict.menu.innerHTML = '<li class="admin-dict__menu-note">' + esc(t('admin.dict_lang_all_added')) + '</li>';
      return;
    }

    dict.menu.innerHTML = free.map(function (lang) {
      var name = lang.name || langName(lang.code);
      return '<li><button type="button" class="dropdown__item" data-dict-lang-pick="' + esc(lang.code) + '">' +
        esc(name) + ' (' + esc(lang.code) + ')' +
        '</button></li>';
    }).join('');
  }

  function loadLanguages() {
    if (dict.languages) return Promise.resolve(dict.languages);
    if (dict.languagesLoading) return dict.languagesLoading;

    setStatus(t('admin.dict_lang_loading'));

    dict.languagesLoading = fetch('/api/additional/languages?page=1&ps=50', {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (res) {
      return res.text().then(function (raw) {
        if (!res.ok || /^\s*</.test(raw)) throw new Error('HTTP ' + res.status);
        var data = raw ? JSON.parse(raw) : null;
        var list = (data && Array.isArray(data.items)) ? data.items
                 : (Array.isArray(data) ? data : []);
        var languages = list.map(function (entry) {
          return {
            code: String((entry && (entry.code || entry.langCode || entry.value)) || ''),
            name: entry && entry.name ? String(entry.name) : ''
          };
        }).filter(function (entry) { return entry.code !== ''; });

        if (!languages.length) throw new Error('empty languages list');
        return languages;
      });
    }).then(function (languages) {
      dict.languages = languages;
      return languages;
    }, function (err) {
      console.warn('[admin] GET /api/additional/languages failed, using fallback', err);
      dict.languages = FALLBACK_LANGUAGES.slice();
      return dict.languages;
    }).then(function (languages) {
      dict.languagesLoading = null;
      setStatus('');
      renderLangMenu();
      return languages;
    });

    return dict.languagesLoading;
  }

  function addLanguage(code) {
    var value = String(code || '').trim();
    if (!value) return;
    var duplicate = dict.draft.some(function (row) {
      return String(row.langCode || '').toLowerCase() === value.toLowerCase();
    });
    if (duplicate) return;

    dict.draft.push({ langCode: value, title: '' });
    setError('');
    renderLangs();

    var rows = dict.list.querySelectorAll('[data-lang-index]');
    var last = rows[rows.length - 1];
    var input = last && last.querySelector('[data-lang-title]');
    if (input) input.focus();
  }

  function removeLanguage(btn) {
    var row = btn.closest('[data-lang-index]');
    var index = row ? Number(row.getAttribute('data-lang-index')) : -1;
    if (index < 0 || !dict.draft[index]) return;
    dict.draft.splice(index, 1);
    renderLangs();
  }

  function openDictForm(id) {
    if (!dict.modal) return;

    dict.editId = id ? Number(id) : null;
    var item = dict.editId ? findItem(dict.editId) : null;

    dict.draft = item
      ? (item.translations || []).map(function (row) {
          return { langCode: String(row.langCode || ''), title: String(row.title || '') };
        })
      : [{ langCode: currentLang(), title: '' }];

    if (dict.titleEl) {
      dict.titleEl.textContent = item
        ? t('admin.dict_edit_title', { name: dict.nameOne })
        : t('admin.dict_add_title', { name: dict.nameOne });
    }

    setError('');
    renderLangs();

    dict.lastFocus = document.activeElement;
    dict.modal.hidden = false;
    document.body.classList.add('is-modal-open');

    var first = dict.list ? dict.list.querySelector('[data-lang-title]') : null;
    if (first) { first.focus(); try { first.select(); } catch (_) {} }

    loadLanguages();
  }

  function closeDictForm() {
    if (!dict.modal || dict.modal.hidden) return;

    dict.modal.hidden = true;
    document.body.classList.remove('is-modal-open');

    var dropdown = dict.modal.querySelector('[data-dropdown].is-open');
    if (dropdown) dropdown.classList.remove('is-open');

    var target = dict.lastFocus;
    dict.lastFocus = null;
    if (target && target.isConnected && target.offsetParent !== null) {
      try { target.focus(); } catch (_) {}
    }
  }

  function isDictOpen() { return !!(dict.modal && !dict.modal.hidden); }

  function setSaveBusy(busy) {
    if (dict.form) dict.form.classList.toggle('is-busy', busy);
    var save = dict.modal && dict.modal.querySelector('[data-dict-save]');
    if (save) save.disabled = busy;
  }

  function nextLocalId() {
    return dict.items.reduce(function (max, row) {
      return Math.max(max, Number(row.id) || 0);
    }, 0) + 1;
  }

  function applyLocal(isEdit, filled, serverItem) {
    var translations = (serverItem && serverItem.translations && serverItem.translations.length)
      ? serverItem.translations
      : filled;

    if (isEdit) {
      var item = findItem(dict.editId);
      if (item) item.translations = translations;
      else if (serverItem) dict.items.push(serverItem);
    } else {
      var id = (serverItem && serverItem.id) ? serverItem.id : nextLocalId();
      dict.items.push({ id: id, translations: translations });
    }
    dict.touched = true;
  }

  function syncFromApi() {
    return dictApi.list(dict.resource).then(function (items) {
      if (items === null) return false;
      dict.items = items;
      renderRows();
      return true;
    });
  }

  function refreshFromApi() {
    dictApi.markUnknown();
    return dictApi.list(dict.resource).then(function (items) {
      if (items === null) return false;
      var note = document.querySelector('.admin-note[data-dict-stub]');
      if (note) note.hidden = true;
      if (dict.touched) return false;
      dict.items = items;
      renderRows();
      return true;
    });
  }

  function saveDictForm() {
    var filled = dict.draft
      .map(function (row) {
        return {
          langCode: String(row.langCode || '').trim(),
          title: String(row.title || '').trim()
        };
      })
      .filter(function (row) { return row.langCode !== '' && row.title !== ''; });

    if (!filled.length) {
      setError(t('admin.dict_validation'));
      return;
    }
    setError('');

    if (dict.submitting) return;
    dict.submitting = true;
    setSaveBusy(true);

    var isEdit = !!dict.editId;
    var payload = {
      translations: filled.map(function (row) {
        return { lang_code: row.langCode, title: row.title };
      })
    };

    dictApi.mutate(isEdit ? 'PUT' : 'POST', dict.resource, isEdit ? dict.editId : null, payload)
      .then(function (result) {
        if (result.mode === 'forbidden') {
          notify(t('js.no_rights'), 'error');
          return;
        }
        if (result.mode === 'error') {
          setError(result.message);
          return;
        }

        applyLocal(isEdit, filled, result.item);
        renderRows();
        closeDictForm();
        notify(t('admin.dict_saved'), 'success');
        if (result.mode === 'api' && !isEdit && !(result.item && result.item.id)) syncFromApi();
      })
      .catch(function (err) {
        console.warn('[admin] dictionary save failed', err);
        setError(t('errors.common.network_error'));
      })
      .then(function () {
        dict.submitting = false;
        setSaveBusy(false);
      });
  }

  function deleteDictItem(btn) {
    var id = Number(btn.getAttribute('data-dict-row-id') || 0);

    confirmModal({
      title:       t('admin.dict_delete_title'),
      text:        t('admin.dict_delete_text'),
      confirmText: t('admin.action_delete'),
      danger:      true,
    }).then(function (confirmed) {
      if (!confirmed) return;

      btn.disabled = true;
      return dictApi.mutate('DELETE', dict.resource, id, null).then(function (result) {
        if (result.mode === 'forbidden') {
          notify(t('js.no_rights'), 'error');
          btn.disabled = false;
          return;
        }
        if (result.mode === 'error') {
          notify(result.message, 'error');
          btn.disabled = false;
          return;
        }

        dict.items = dict.items.filter(function (item) { return Number(item.id) !== id; });
        dict.touched = true;
        renderRows();
        notify(t('admin.dict_deleted'), 'success');
      });
    });
  }

  function initDictionary() {
    var dataEl = document.querySelector('[data-dict-data]');
    var table  = document.querySelector('[data-dict-table]');
    var modal  = document.querySelector('[data-dict-modal]');
    if (!dataEl || !table || !modal) return;

    var payload = null;
    try {
      payload = JSON.parse(dataEl.textContent || '{}');
    } catch (err) {
      console.warn('[admin] dictionary payload is not valid JSON', err);
    }
    if (!payload) return;

    dict.rows = table.querySelector('[data-dict-rows]');
    dict.modal = modal;
    dict.form = modal.querySelector('[data-dict-form]');
    dict.list = modal.querySelector('[data-dict-langs]');
    dict.menu = modal.querySelector('[data-dict-lang-menu]');
    dict.error = modal.querySelector('[data-dict-error]');
    dict.status = modal.querySelector('[data-dict-lang-status]');
    dict.emptyHint = modal.querySelector('[data-dict-langs-empty]');
    dict.titleEl = modal.querySelector('[data-dict-title]');
    dict.resource = table.getAttribute('data-dict-resource') || payload.resource || '';
    dict.nameOne = modal.getAttribute('data-dict-name') || '';
    dict.items = Array.isArray(payload.items) ? payload.items : [];

    renderRows();
    refreshFromApi();

    if (dict.form) {
      dict.form.addEventListener('submit', function (e) {
        e.preventDefault();
        saveDictForm();
      });
    }

    if (dict.list) {
      dict.list.addEventListener('input', function (e) {
        var input = e.target.closest && e.target.closest('[data-lang-title]');
        if (!input) return;
        var row = input.closest('[data-lang-index]');
        var index = row ? Number(row.getAttribute('data-lang-index')) : -1;
        if (index >= 0 && dict.draft[index]) dict.draft[index].title = input.value;
        setError('');
      });
    }

    loadLanguages();
  }

  /* ============================================================
     SEARCH — клиентский поиск в таблицах админки
     ============================================================ */
  var searchState = null;

  function initSearch() {
    var input = document.querySelector('[data-admin-search]');
    var table = document.querySelector('[data-admin-table]');
    
    if (!input || !table || table.hasAttribute('data-allroles-table')) return;

    var serverMode = table.getAttribute('data-admin-table') === 'roles';
    var statusSel = document.querySelector('[data-admin-status]');
    var emptyRow = table.querySelector('[data-admin-empty]');
    var emptyText = emptyRow ? (emptyRow.querySelector('td') || emptyRow).textContent.trim() : '';

    searchState = {
      input: input,
      table: table,
      status: statusSel,
      count: document.querySelector('[data-admin-count]'),
      emptyRow: emptyRow,
      emptyText: emptyText,
      serverQuery: '',
      apply: function () {
        var rawQuery = serverMode ? searchState.serverQuery : (searchState.input.value || '').trim();
        var q = rawQuery.toLowerCase();
        var status = searchState.status ? String(searchState.status.value || '') : '';
        var rows = searchState.table.querySelectorAll('tbody tr[data-search]');
        var shown = 0;

        Array.prototype.forEach.call(rows, function (row) {
          var haystack = (row.getAttribute('data-search') || '').toLowerCase();
          var hitText = serverMode || q === '' || haystack.indexOf(q) !== -1;
          var hitStatus = status === '' || (row.getAttribute('data-status') || '') === status;
          var hit = hitText && hitStatus;
          row.hidden = !hit;
          if (hit) shown++;
        });

        if (searchState.count) searchState.count.textContent = String(shown);

        if (searchState.emptyRow) {
          if (shown > 0) {
            searchState.emptyRow.hidden = true;
          } else {
            searchState.emptyRow.hidden = false;
            var cell = searchState.emptyRow.querySelector('td') || searchState.emptyRow;
            cell.textContent = q
              ? t('admin.no_results', { query: rawQuery })
              : searchState.emptyText;
          }
        }
      }
    };

    if (serverMode) {
      input.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        searchState.serverQuery = (input.value || '').trim();
        runRolesSearch(searchState.serverQuery);
      });
      input.addEventListener('search', function () {
        var value = (input.value || '').trim();
        if (value === '' && searchState.serverQuery !== '') {
          searchState.serverQuery = '';
          runRolesSearch('');
        }
      });
    } else {
      input.addEventListener('input', searchState.apply);
    }
    if (statusSel) statusSel.addEventListener('change', searchState.apply);
    searchState.apply();
  }

  function applySearch() {
    if (searchState) searchState.apply();
  }

  /* ============================================================
     ALL ROLES — страница ВСЕХ названий ролей (admin/all-roles)
     ============================================================ */
  var allRoles = {
    table: null, rows: null, empty: null, status: null, count: null, search: null,
    filterStatus: null,
    query: '',
    seq: 0,
    items: []
  };

  var allRolesApi = (function () {
    var BASE = '/api/additional/roles';
    var status = 'unknown';

    function table() { return document.querySelector('[data-allroles-table]'); }
    function setStatus(next) {
      if (status === next) return;
      status = next;
      var el = table();
      if (el) el.setAttribute('data-allroles-api', next);
    }

    function request(method, path, body) {
      var url = BASE + (path || '');
      var options = {
        method: method,
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      };
      if (body !== undefined && body !== null) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
      }
      var run = (window.LoreCsrf && LoreCsrf.fetch) ? LoreCsrf.fetch : fetch;
      var result = function (st, ok, raw) {
        var data = null, isJson = false;
        if (raw && /^[\s]*[\[{]/.test(raw)) { try { data = JSON.parse(raw); isJson = true; } catch (_) {} }
        return { status: st, ok: ok, data: data, raw: raw || '', isJson: isJson };
      };
      return Promise.resolve()
        .then(function () { return run(url, options); })
        .then(function (res) {
          return res.text().then(
            function (raw) { return result(res.status, res.ok, raw); },
            function () { return result(res.status, res.ok, ''); }
          );
        })
        .catch(function (err) {
          return { status: 0, ok: false, data: null, raw: '', isJson: false, networkError: err };
        });
    }

    function classify(r) {
      if (r.networkError) return 'mock';
      var looksHtml = /^\s*</.test(r.raw || '');
      if (r.ok) return looksHtml ? 'mock' : 'ok';
      if (looksHtml || !r.isJson) return 'mock';
      if (r.status === 404 || r.status === 405) return 'mock';
      if (r.status === 403) return 'forbidden';
      return 'error';
    }

    var warnedVisibility = false;

    /** visibility роли: строка (public/private) или булев флаг; бывает и во вложенном role */
    function visibilityOf(raw) {
      var nodes = [raw, raw.role, raw.item];
      for (var i = 0; i < nodes.length; i++) {
        var o = nodes[i];
        if (!o || typeof o !== 'object') continue;
        var v = o.visibility != null ? o.visibility
              : o.status != null ? o.status
              : o.access != null ? o.access
              : o.scope != null ? o.scope : null;
        if (typeof v === 'string' && v) {
          v = v.toLowerCase();
          if (v.indexOf('priv') !== -1 || v === 'hidden') return 'private';
          if (v.indexOf('pub') !== -1) return 'public';
          return v;
        }
        if (o.isPrivate === true || o.private === true) return 'private';
        if (o.isPublic === true || o.public === true) return 'public';
        if (o.isPrivate === false || o.private === false) return 'public';
        if (o.isPublic === false || o.public === false) return 'private';
      }
      if (!warnedVisibility) {
        warnedVisibility = true;
        console.warn('[admin] all-roles: no visibility field in role item, keys:', Object.keys(raw), raw);
      }
      return '';
    }

    function normalize(raw) {
      if (!raw || typeof raw !== 'object') return null;
      var id = Number(raw.id);
      if (!id && id !== 0) return null;
      
      var title = raw.title || raw.name || '';
      var translations = Array.isArray(raw.translations) ? raw.translations : [];
      
      if (!title && translations.length > 0) {
         var lang = currentLang().slice(0, 2).toLowerCase();
         var found = translations.find(function(tr) { return String(tr.langCode || tr.lang_code || tr.lang).slice(0, 2).toLowerCase() === lang; });
         title = found ? (found.title || found.name) : (translations[0].title || translations[0].name);
      }
      
      if (!title && raw.translations && typeof raw.translations === 'object' && !Array.isArray(raw.translations)) {
         var transKeys = Object.keys(raw.translations);
         title = raw.translations[currentLang()] || (transKeys.length ? raw.translations[transKeys[0]] : '') || '';
      }

      var p = raw.permissions || {};
      var systemPermissions = raw.systemPermissions || p.system || p.systemPermissions || {};
      var postPermissions = raw.postPermissions || p.posts || p.postPermissions || {};
      var pubPermissions = raw.pubPermissions || p.pubs || p.publications || p.pubPermissions || {};

      return {
        id: id,
        title: String(title || ''),
        visibility: visibilityOf(raw),
        color: normalizeColor(raw.color),
        canManage: raw.canManage !== false,
        version: raw.version != null ? raw.version : 1,
        systemPermissions: typeof systemPermissions === 'object' ? systemPermissions : {},
        postPermissions: typeof postPermissions === 'object' ? postPermissions : {},
        pubPermissions: typeof pubPermissions === 'object' ? pubPermissions : {}
      };
    }

    function fromPayload(payload) {
      var inner = payload && typeof payload === 'object' && payload.data && typeof payload.data === 'object' ? payload.data : payload;
      var list = Array.isArray(inner) ? inner
               : (inner && Array.isArray(inner.items)) ? inner.items
               : (inner && Array.isArray(inner.data)) ? inner.data
               : null;
      if (!list) return null;
      return list.map(normalize).filter(function (x) { return x !== null; });
    }

    return {
      get status() { return status; },
      list: function (query, statusFilter) {
        var parts = [];
        if (query) parts.push('q=' + encodeURIComponent(query));
        if (statusFilter && statusFilter !== 'all') parts.push('status=' + encodeURIComponent(statusFilter));
        var qs = parts.length ? '?' + parts.join('&') : '';
        return request('GET', qs).then(function (r) {
          var kind = classify(r);
          if (kind === 'ok') {
            var items = fromPayload(r.data);
            if (items === null) return null;
            setStatus('live');
            return items;
          }
          if (kind === 'mock') { setStatus('mock'); return null; }
          return null;
        });
      },
      remove: function (id) {
        return request('DELETE', '/' + id).then(function (r) {
          var kind = classify(r);
          if (kind === 'ok') { setStatus('live'); return { mode: 'api', status: r.status }; }
          if (kind === 'mock') { setStatus('mock'); return { mode: 'mock' }; }
          if (kind === 'forbidden') return { mode: 'forbidden', status: r.status };
          return { mode: 'error', status: r.status, message: 'HTTP ' + r.status };
        });
      }
    };
  })();

  function setAllRolesStatus(text) {
    if (!allRoles.status) return;
    allRoles.status.textContent = text || '';
    allRoles.status.hidden = !text;
  }

  function applyAllRolesFilters() {
    if (!allRoles.table) return;
    // Текст ищет бэк (по Enter, allRoles.query); локально — только visibility
    var q = allRoles.query || '';
    var statusVal = allRoles.filterStatus ? (allRoles.filterStatus.querySelector('input:checked') || {}).value : 'all';
    
    var rows = allRoles.table.querySelectorAll('tbody tr[data-search]');
    var shown = 0;
    
    Array.prototype.forEach.call(rows, function (row) {
      var rowStatus = row.getAttribute('data-status') || '';
      var hit = statusVal === 'all' || rowStatus === statusVal;
      
      row.hidden = !hit;
      if (hit) shown++;
    });
    
    if (allRoles.count) allRoles.count.textContent = String(shown);
    if (allRoles.empty) {
      allRoles.empty.hidden = shown > 0;
      if (shown === 0) {
        var cell = allRoles.empty.querySelector('td') || allRoles.empty;
        cell.textContent = q ? t('admin.no_results', { query: q }) : t('admin.empty');
      }
    }
  }

  /** В списке All roles убраны права и выведена кнопка Delete */
  function renderAllRoles() {
    if (!allRoles.rows) return;
    
    var html = allRoles.items.map(function (item) {
      var id = Number(item.id) || 0;
      var titleHtml = item.color ? '<span class="admin-role__dot" style="background:' + esc(item.color) + '"></span> ' : '';
      titleHtml += esc(item.title || 'Без названия');

      var isPrivate = item.visibility === 'private';
      var visHtml = !item.visibility
        ? '<span class="admin-badge admin-badge--muted">—</span>'
        : '<span class="admin-badge ' + (isPrivate ? 'admin-badge--muted' : 'admin-badge--success') + '">' +
            esc(isPrivate ? tx('admin.role_private', 'Private') : tx('admin.role_public', 'Public')) +
          '</span>';

      return '<tr data-allroles-row-id="' + id + '" data-search="' + esc(item.title.toLowerCase()) + '" data-status="' + esc(item.visibility) + '">' +
          '<td class="admin-table__id" data-col="id">' + id + '</td>' +
          '<td data-col="title"><span class="admin-role__title">' + titleHtml + '</span></td>' +
          '<td data-col="vis">' + visHtml + '</td>' +
          '<td data-col="delete"><div class="admin-actions">' + 
            '<button type="button" class="btn btn--danger admin-icon-btn" data-allroles-delete data-allroles-row-id="' + id + '"' +
              ' title="' + esc(t('admin.action_delete') || 'Delete') + '" aria-label="' + esc(t('admin.action_delete') || 'Delete') + '">' +
              TRASH +
            '</button>' +
          '</div></td>' +
        '</tr>';
    }).join('');
    
    var emptyRow = allRoles.rows.querySelector('[data-allroles-empty]');
    allRoles.rows.innerHTML = html;
    if (emptyRow) allRoles.rows.appendChild(emptyRow);
    
    applyAllRolesFilters();
  }

  function ensureSystemRoles() {
    if (!allRoles.table) return Promise.resolve(true);
    return rolesApi.list().then(function (items) {
      if (!items) return false;
      roleState.items = items;
      return true;
    });
  }

  function loadAllRoles(query, status) {
    return Promise.all([allRolesApi.list(query, status), rolesApi.list()]).then(function (res) {
      var names = res[0], system = res[1];
      if (names === null) return null;
      if (system) roleState.items = system;
      names.forEach(function (item) {
        var sys = system ? system.find(function (r) { return Number(r.id) === Number(item.id); }) : null;
        item.permsKnown = !!system;
        if (!sys) return;
        item.systemPermissions = sys.systemPermissions;
        item.postPermissions   = sys.postPermissions;
        item.pubPermissions    = sys.pubPermissions;
        item.version           = sys.version;
        item.canManage         = sys.canManage;
        if (!item.visibility && sys.status) item.visibility = String(sys.status).toLowerCase();
      });
      return names;
    });
  }

  function refreshAllRoles() {
    if (!allRoles.table) return Promise.resolve();
    return loadAllRoles().then(function (items) {
      if (!items) return;
      allRoles.items = items;
      renderAllRoles();
    });
  }

  function deleteAllRole(btn) {
    var id = Number(btn.getAttribute('data-allroles-row-id') || 0);
    confirmModal({
      title:       tx('admin.role_delete_title', 'Delete role?'),
      text:        tx('admin.role_delete_text', 'The role will be permanently deleted. This action cannot be undone.'),
      confirmText: tx('admin.action_delete', 'Delete'),
      danger:      true
    }).then(function (confirmed) {
      if (!confirmed) return;
      btn.disabled = true;
      return allRolesApi.remove(id).then(function (result) {
        if (result.mode === 'forbidden') { notify(tx('js.no_rights', 'You do not have permission to do this'), 'error'); btn.disabled = false; return; }
        if (result.mode === 'error') { notify(result.message, 'error'); btn.disabled = false; return; }
        allRoles.items = allRoles.items.filter(function (x) { return Number(x.id) !== id; });
        renderAllRoles();
        notify(tx('admin.role_deleted', 'Role deleted'), 'success');
      });
    }).catch(function (err) {
      console.warn('[admin] all-roles delete failed', err);
      notify(tx('errors.common.network_error', 'Network error'), 'error');
      btn.disabled = false;
    });
  }

  function allRolesStatusValue() {
    var el = allRoles.filterStatus ? allRoles.filterStatus.querySelector('input:checked') : null;
    return el ? el.value : 'all';
  }

  function runAllRolesSearch() {
    var seq = ++allRoles.seq;
    return loadAllRoles(allRoles.query, allRolesStatusValue()).then(function (items) {
      if (seq !== allRoles.seq) return;
      if (items === null) {
        notify(tx('admin.roles_load_error', 'Failed to load roles'), 'error');
        return;
      }
      allRoles.items = items;
      renderAllRoles();
    });
  }

  function initAllRoles() {
    var table = document.querySelector('[data-allroles-table]');
    var rows = document.querySelector('[data-allroles-rows]');
    if (!table || !rows) return;

    allRoles.table = table;
    allRoles.rows = rows;
    allRoles.empty = rows.querySelector('[data-allroles-empty]');
    allRoles.status = document.querySelector('[data-allroles-status]');
    allRoles.count = document.querySelector('[data-allroles-count]');
    allRoles.search = document.querySelector('[data-allroles-search]');
    allRoles.filterStatus = document.querySelector('[data-allroles-filter-status]');

    if (allRoles.search) {
      allRoles.search.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        allRoles.query = (allRoles.search.value || '').trim();
        runAllRolesSearch();
      });
      allRoles.search.addEventListener('search', function () {
        if ((allRoles.search.value || '').trim() === '' && allRoles.query !== '') {
          allRoles.query = '';
          runAllRolesSearch();
        }
      });
    }
    if (allRoles.filterStatus) {
      allRoles.filterStatus.addEventListener('change', function () {
        runAllRolesSearch();
      });
    }

    setAllRolesStatus(tx('admin.roles_loading', 'Loading roles…'));
    loadAllRoles('', 'all').then(function (items) {
      if (items === null) {
        setAllRolesStatus(tx('admin.roles_load_error', 'Failed to load roles'));
        return;
      }
      allRoles.items = items;
      setAllRolesStatus('');
      renderAllRoles();
    });
  }

  /* ============================================================
     USER ROLES — вложенный список ролей пользователя (admin/users)
     GET  /api/system/users/{id}/roles   — роли пользователя
     POST /api/system/users/{id}/roles   — { roleId }
     ============================================================ */
  var userRoles = {
    table: null, modal: null,
    cache: {},          // userId -> [{ id, title, color }]
    catalog: null,      // id -> { title, color } по системным ролям
    systemIds: null,    // id -> true (null, если список не получили)
    current: null,      // { id, name }
    selected: null,
    options: [],
    query: '',
    searching: false,
    seq: 0,
    submitting: false,
    lastFocus: null
  };

  function um(sel) { return userRoles.modal ? userRoles.modal.querySelector(sel) : null; }
  function isUserRoleOpen() { return !!(userRoles.modal && !userRoles.modal.hidden); }

  function urFetch(method, url, body) {
    var run = (window.LoreCsrf && LoreCsrf.fetch) ? LoreCsrf.fetch : fetch;
    var options = {
      method: method,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    };
    if (body !== undefined && body !== null) {
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(body);
    }
    return Promise.resolve().then(function () { return run(url, options); }).then(function (res) {
      return res.text().then(function (raw) {
        var data = null;
        if (raw && /^[\s]*[\[{]/.test(raw)) { try { data = JSON.parse(raw); } catch (_) {} }
        return { ok: res.ok && !/^\s*</.test(raw || ''), status: res.status, data: data, raw: raw || '' };
      });
    }).catch(function () {
      return { ok: false, status: 0, data: null, raw: '', networkError: true };
    });
  }

  function urError(r) {
    if (r.networkError) return tx('errors.common.network_error', 'Network error. Try again.');
    if (r.status === 403) return tx('js.no_rights', 'You do not have permission to do this');
    var fallback = window.LoreI18n ? LoreI18n.t('errors.common.request_failed', { status: r.status }) : null;
    if (window.Messages && Messages.describe) return Messages.describe(r.status, r.data, r.raw, fallback);
    return fallback || ('HTTP ' + r.status);
  }

  function urCatalog() {
    if (userRoles.catalog) return Promise.resolve(userRoles.catalog);
    return rolesApi.list().then(function (items) {
      var map = {};
      var ids = {};
      (items || []).forEach(function (r) { map[r.id] = { title: r.title, color: r.color }; ids[r.id] = true; });
      if (items) { userRoles.catalog = map; userRoles.systemIds = ids; }
      return map;
    });
  }

  function urNormalize(x, catalog) {
    var role = (x && typeof x === 'object' && x.role && typeof x.role === 'object') ? x.role : x;
    // Если запись — связка «пользователь–роль», её собственный id НЕ id роли:
    // приоритет у roleId, затем id вложенной роли, и только потом id самой записи.
    var rawId;
    if (x && typeof x === 'object' && x.roleId != null) rawId = x.roleId;
    else if (role && typeof role === 'object') rawId = role.id != null ? role.id : role.roleId;
    else rawId = role;
    var id = Number(rawId);
    if (rawId == null || isNaN(id)) return null;
    var cat = catalog[id] || {};
    var obj = (role && typeof role === 'object') ? role : {};
    return {
      id: id,
      title: String(obj.title || obj.name || cat.title || ('#' + id)),
      color: normalizeColor(obj.color || cat.color || '')
    };
  }

  function urLoad(userId) {
    return Promise.all([urFetch('GET', '/api/system/users/' + encodeURIComponent(userId) + '/roles'), urCatalog()]).then(function (res) {
      var r = res[0], catalog = res[1];
      if (!r.ok) return null;
      var inner = (r.data && r.data.data && typeof r.data.data === 'object') ? r.data.data : r.data;
      var list = Array.isArray(inner) ? inner : (inner && Array.isArray(inner.items)) ? inner.items : [];
      if (list.length) console.debug('[admin] user roles, raw item:', list[0]);
      return list.map(function (x) { return urNormalize(x, catalog); }).filter(function (x) { return x !== null; });
    });
  }

  function urBody(userId) {
    return document.querySelector('[data-user-roles-row="' + userId + '"] [data-user-roles-body]');
  }

  function urRender(userId) {
    var body = urBody(userId);
    if (!body) return;
    var roles = userRoles.cache[userId] || [];
    var mainBtn = document.querySelector('[data-user-expand][data-user-id="' + userId + '"]');
    var name = mainBtn ? (mainBtn.getAttribute('data-user-name') || '') : '';

    var removeLabel = tx('admin.user_role_remove', 'Remove role');
    var list = roles.length
      ? '<ul class="admin-user-roles__list">' + roles.map(function (r) {
          return '<li class="admin-user-roles__item">' +
            (r.color ? '<span class="admin-role__dot" style="background:' + esc(r.color) + '"></span>' : '') +
            '<span class="admin-user-roles__title">' + esc(r.title) + '</span>' +
            '<button type="button" class="admin-user-roles__remove" data-user-role-remove' +
              ' data-user-id="' + esc(userId) + '" data-role-id="' + r.id + '"' +
              ' title="' + esc(removeLabel) + '" aria-label="' + esc(removeLabel + ': ' + r.title) + '">' +
              '<svg viewBox="0 0 16 16" width="12" height="12" aria-hidden="true" focusable="false">' +
                '<path d="M3 3l10 10M13 3L3 13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>' +
            '</button></li>';
        }).join('') + '</ul>'
      : '<p class="admin-user-roles__note">' + esc(tx('admin.user_roles_empty', 'No roles')) + '</p>';

    var addLabel = tx('admin.user_role_add_title', 'Add role to user');
    body.innerHTML = list +
      '<div class="admin-user-roles__foot">' +
        '<button type="button" class="btn btn--primary admin-user-roles__add" data-user-role-add' +
          ' data-user-id="' + esc(userId) + '" data-user-name="' + esc(name) + '"' +
          ' title="' + esc(addLabel) + '" aria-label="' + esc(addLabel) + '">' +
          '<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false">' +
            '<path d="M8 3v10M3 8h10" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>' +
        '</button>' +
      '</div>';
  }

  function urToggle(btn) {
    var id = btn.getAttribute('data-user-id');
    var row = document.querySelector('[data-user-roles-row="' + id + '"]');
    if (!row) return;
    var open = row.hidden;
    row.hidden = !open;
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    btn.classList.toggle('is-open', open);
    if (!open || userRoles.cache[id]) return;

    var body = urBody(id);
    if (body) body.innerHTML = '<p class="admin-user-roles__note">' + esc(tx('common.common.loading', 'Loading...')) + '</p>';
    urLoad(id).then(function (roles) {
      if (roles === null) {
        if (body) body.innerHTML = '<p class="admin-user-roles__note">' + esc(tx('admin.roles_load_error', 'Failed to load roles')) + '</p>';
        return;
      }
      userRoles.cache[id] = roles;
      urRender(id);
    });
  }

  /* ---------- дропдаун выбора роли ---------- */
  function urDdClose() {
    var wrap = um('[data-urole-dd]');
    if (!wrap) return;
    wrap.classList.remove('is-open');
    var menu = um('[data-urole-dd-menu]');
    if (menu) menu.hidden = true;
    var toggle = um('[data-urole-dd-toggle]');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
  }

  function urDdIsOpen() {
    var wrap = um('[data-urole-dd]');
    return !!(wrap && wrap.classList.contains('is-open'));
  }

  function urDdToggle() {
    var wrap = um('[data-urole-dd]');
    var toggle = um('[data-urole-dd-toggle]');
    var menu = um('[data-urole-dd-menu]');
    if (!wrap || !toggle || !menu || toggle.disabled) return;
    var open = !wrap.classList.contains('is-open');
    wrap.classList.toggle('is-open', open);
    menu.hidden = !open;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) { var s = um('[data-urole-dd-search]'); if (s) s.focus(); }
  }

  function urDdRenderValue() {
    var el = um('[data-urole-dd-value]');
    if (!el) return;
    var sel = userRoles.selected;
    if (sel) {
      el.classList.remove('is-placeholder');
      el.innerHTML = (sel.color ? '<span class="admin-role__dot" style="background:' + esc(sel.color) + '"></span>' : '') +
        '<span class="admin-role__dd-text">' + esc(sel.title) + '</span>';
    } else {
      el.classList.add('is-placeholder');
      el.textContent = tx('admin.role_select_placeholder', 'Choose a role');
    }
  }

  function urDdRenderMenu() {
    var list = um('[data-urole-dd-list]');
    if (!list) return;
    var shown = userRoles.options;
    var curId = userRoles.selected ? userRoles.selected.id : null;
    if (!shown.length) {
      var msg = userRoles.searching ? tx('common.common.loading', 'Loading...')
        : userRoles.query ? tx('admin.role_dd_nothing', 'Nothing found')
        : tx('admin.role_none_available', 'No roles available');
      list.innerHTML = '<li class="admin-role__dd-note">' + esc(msg) + '</li>';
      return;
    }
    list.innerHTML = shown.map(function (r) {
      var on = r.id === curId;
      return '<li role="presentation"><button type="button" class="admin-role__dd-item' + (on ? ' is-selected' : '') + '"' +
        ' role="option" aria-selected="' + (on ? 'true' : 'false') + '" data-urole-dd-pick="' + r.id + '">' +
        (r.color ? '<span class="admin-role__dot" style="background:' + esc(r.color) + '"></span>' : '') +
        '<span class="admin-role__dd-text">' + esc(r.title) + '</span></button></li>';
    }).join('');
  }

  /** Роли, которые ещё можно выдать: из системных и которых у пользователя нет. */
  function urAvailable(names) {
    var cur = userRoles.current;
    var owned = {};
    ((cur && userRoles.cache[cur.id]) || []).forEach(function (r) { owned[r.id] = true; });
    return names.filter(function (r) {
      if (owned[Number(r.id)]) return false;
      return userRoles.systemIds ? userRoles.systemIds[Number(r.id)] === true : true;
    });
  }

  /** Поиск ролей через API (GET /api/additional/roles?q=…) — только по Enter. */
  function urSearch(query) {
    var q = String(query || '').trim();
    if (q === userRoles.query && !userRoles.searching) return;
    userRoles.query = q;
    userRoles.searching = true;
    urDdRenderMenu();
    var seq = ++userRoles.seq;
    allRolesApi.list(q, 'all').then(function (names) {
      if (seq !== userRoles.seq) return;
      userRoles.searching = false;
      if (names === null) { notify(tx('admin.roles_load_error', 'Failed to load roles'), 'error'); return; }
      userRoles.options = urAvailable(names).map(function (r) { return { id: Number(r.id), title: r.title, color: r.color || '' }; });
      urDdRenderMenu();
    });
  }

  function urPick(id) {
    var picked = userRoles.options.filter(function (r) { return r.id === Number(id); })[0];
    if (!picked) return;
    userRoles.selected = picked;
    urDdRenderValue();
    urDdRenderMenu();
    urDdClose();
    var save = um('[data-urole-save]');
    if (save) save.disabled = false;
  }

  /* ---------- модалка ---------- */
  function urOpenAdd(btn) {
    if (!userRoles.modal) return;
    var id = btn.getAttribute('data-user-id');
    userRoles.current = { id: id, name: btn.getAttribute('data-user-name') || ('#' + id) };
    userRoles.selected = null;
    userRoles.options = [];
    userRoles.query = '';
    userRoles.searching = true;
    userRoles.submitting = false;
    var seq = ++userRoles.seq;

    var nameEl = um('[data-urole-user]');
    if (nameEl) nameEl.textContent = userRoles.current.name;
    var search = um('[data-urole-dd-search]');
    if (search) search.value = '';
    var save = um('[data-urole-save]');
    if (save) save.disabled = true;
    var toggle = um('[data-urole-dd-toggle]');
    if (toggle) toggle.disabled = true;
    urDdClose();
    urDdRenderValue();
    urDdRenderMenu();

    userRoles.lastFocus = document.activeElement;
    userRoles.modal.hidden = false;
    document.body.classList.add('is-modal-open');

    Promise.all([allRolesApi.list('', 'all'), urCatalog()]).then(function (res) {
      if (seq !== userRoles.seq) return;
      var names = res[0];
      userRoles.searching = false;
      if (toggle) toggle.disabled = false;
      if (names === null) {
        notify(tx('admin.roles_load_error', 'Failed to load roles'), 'error');
        userRoles.options = [];
      } else {
        userRoles.options = urAvailable(names).map(function (r) { return { id: Number(r.id), title: r.title, color: r.color || '' }; });
      }
      urDdRenderMenu();
      if (toggle) toggle.focus();
    });
  }

  function urClose() {
    if (!isUserRoleOpen()) return;
    urDdClose();
    userRoles.modal.hidden = true;
    userRoles.seq++;
    document.body.classList.remove('is-modal-open');
    var target = userRoles.lastFocus;
    userRoles.lastFocus = null;
    if (target && target.isConnected && target.offsetParent !== null) {
      try { target.focus(); } catch (_) {}
    }
  }

  function urSave() {
    var cur = userRoles.current;
    var sel = userRoles.selected;
    if (!cur || !sel || userRoles.submitting) return;
    userRoles.submitting = true;
    var save = um('[data-urole-save]');
    if (save) save.disabled = true;

    urFetch('POST', '/api/system/users/' + encodeURIComponent(cur.id) + '/roles', { roleId: sel.id }).then(function (r) {
      if (!r.ok) { notify(urError(r), 'error'); return; }
      var list = userRoles.cache[cur.id] || [];
      if (!list.some(function (x) { return x.id === sel.id; })) list.push({ id: sel.id, title: sel.title, color: sel.color });
      userRoles.cache[cur.id] = list;
      urRender(cur.id);
      notify(tx('admin.role_saved', 'Roles updated'), 'success');
      urClose();
    }).then(function () {
      userRoles.submitting = false;
      if (save) save.disabled = !userRoles.selected;
    });
  }

  /** Снять роль с пользователя: DELETE /api/system/users/{id}/roles/{roleId} */
  function urRemove(btn) {
    var userId = btn.getAttribute('data-user-id');
    var roleId = Number(btn.getAttribute('data-role-id'));
    var list = userRoles.cache[userId] || [];
    var role = list.filter(function (r) { return r.id === roleId; })[0];
    if (!role) return;
    var mainBtn = document.querySelector('[data-user-expand][data-user-id="' + userId + '"]');
    var userName = mainBtn ? (mainBtn.getAttribute('data-user-name') || '') : '';

    confirmModal({
      title:       tx('admin.user_role_remove_title', 'Remove role?'),
      text:        tx('admin.user_role_remove_text',
                      'The role "' + role.title + '" will be removed from ' + userName + '.',
                      { role: role.title, user: userName }),
      confirmText: tx('admin.user_role_remove', 'Remove role'),
      danger:      true
    }).then(function (confirmed) {
      if (!confirmed) return;
      btn.disabled = true;
      return urFetch('DELETE', '/api/system/users/' + encodeURIComponent(userId) + '/roles/' + roleId).then(function (r) {
        if (!r.ok) { notify(urError(r), 'error'); btn.disabled = false; return; }
        userRoles.cache[userId] = (userRoles.cache[userId] || []).filter(function (x) { return x.id !== roleId; });
        urRender(userId);
        notify(tx('admin.user_role_removed', 'Role removed'), 'success');
      });
    }).catch(function () {
      notify(tx('errors.common.network_error', 'Network error. Try again.'), 'error');
      btn.disabled = false;
    });
  }

  function initUserRoles() {
    var table = document.querySelector('[data-admin-table="users"]');
    if (!table) return;
    userRoles.table = table;
    userRoles.modal = document.querySelector('[data-urole-modal]');

    document.addEventListener('click', function (e) {
      if (!e.target || !e.target.closest) return;

      var expand = e.target.closest('[data-user-expand]');
      if (expand) { urToggle(expand); return; }

      var add = e.target.closest('[data-user-role-add]');
      if (add) { urOpenAdd(add); return; }

      var remove = e.target.closest('[data-user-role-remove]');
      if (remove && !remove.disabled) { urRemove(remove); return; }

      if (!isUserRoleOpen()) return;
      if (e.target.closest('[data-urole-close]')) { urClose(); return; }
      if (e.target.closest('[data-urole-dd-toggle]')) { urDdToggle(); return; }
      var pick = e.target.closest('[data-urole-dd-pick]');
      if (pick) { urPick(pick.getAttribute('data-urole-dd-pick')); return; }
      var save = e.target.closest('[data-urole-save]');
      if (save && !save.disabled) { urSave(); return; }
      if (urDdIsOpen() && !e.target.closest('[data-urole-dd]')) urDdClose();
    });

    document.addEventListener('search', function (e) {
      var s = e.target && e.target.closest && e.target.closest('[data-urole-dd-search]');
      if (s && (s.value || '').trim() === '' && userRoles.query) urSearch('');
    });

    document.addEventListener('keydown', function (e) {
      if (!isUserRoleOpen()) return;
      var s = e.target && e.target.closest && e.target.closest('[data-urole-dd-search]');
      if (s && e.key === 'Enter') { e.preventDefault(); urSearch(s.value); return; }
      if (s && e.key === 'ArrowDown') {
        e.preventDefault();
        var first = um('[data-urole-dd-list] [data-urole-dd-pick]');
        if (first) first.focus();
        return;
      }
      if (e.key === 'Escape') {
        e.preventDefault();
        if (urDdIsOpen()) urDdClose(); else urClose();
        return;
      }
      if (e.key !== 'Tab') return;
      var dialog = userRoles.modal.querySelector('.modal__dialog');
      if (!dialog) return;
      var items = Array.prototype.filter.call(
        dialog.querySelectorAll('button, [href], input, select, textarea, [tabindex]'),
        function (el) { return !el.disabled && el.offsetParent !== null; }
      );
      if (!items.length) return;
      var firstEl = items[0], lastEl = items[items.length - 1];
      if (e.shiftKey && document.activeElement === firstEl) { e.preventDefault(); lastEl.focus(); }
      else if (!e.shiftKey && document.activeElement === lastEl) { e.preventDefault(); firstEl.focus(); }
    });
  }

  /* ============================================================
     USERS LIST — таблица пользователей из API (admin/users)
     GET /api/users?q=…&page=N&ps=20  (поиск — только по Enter)
     ============================================================ */
  var usersList = {
    tbody: null, status: null, count: null, search: null, more: null,
    query: '', page: 1, ps: 20, total: null, hasMore: false,
    items: [], seq: 0, loading: false
  };

  function usersParse(payload) {
    if (payload === null || payload === undefined) return null;
    var inner = payload;
    if (!Array.isArray(payload) && payload && typeof payload === 'object' &&
        payload.data && typeof payload.data === 'object') {
      inner = payload.data;
    }
    var list = Array.isArray(inner) ? inner
             : (inner && Array.isArray(inner.items)) ? inner.items
             : (inner && Array.isArray(inner.users)) ? inner.users
             : (inner && Array.isArray(inner.data)) ? inner.data
             : null;
    if (!list) return null;

    var total = null;
    [inner, payload].forEach(function (src) {
      if (total !== null || !src || typeof src !== 'object' || Array.isArray(src)) return;
      ['total', 'totalCount', 'count'].forEach(function (k) {
        if (total === null && src[k] != null && isFinite(Number(src[k]))) total = Number(src[k]);
      });
    });

    return {
      items: list.map(usersNormalize).filter(function (x) { return x !== null; }),
      total: total
    };
  }

  function usersNormalize(u) {
    if (!u || typeof u !== 'object') return null;
    var id = Number(u.id !== undefined ? u.id : u.userId);
    if (!id && id !== 0) return null;
    var statusText = String(u.status || '').toLowerCase();
    var roles = Array.isArray(u.roles) ? u.roles.map(function (r) {
      if (typeof r === 'string') return r;
      var node = (r && r.role && typeof r.role === 'object') ? r.role : r;
      return node ? String(node.title || node.name || '') : '';
    }).filter(function (x) { return x !== ''; }) : [];
    return {
      id: id,
      username: String(u.username || u.name || u.login || u.nickname || ''),
      email: String(u.email || ''),
      blocked: u.blocked === true || u.isBlocked === true || /block|ban/.test(statusText),
      roles: roles
    };
  }

  function usersRowHtml(u) {
    var id = u.id;
    var roleCell = u.roles.length
      ? u.roles.slice(0, 2).map(function (r) { return '<span class="admin-badge">' + esc(r) + '</span>'; }).join(' ') +
        (u.roles.length > 2 ? ' <span class="admin-badge admin-badge--muted">+' + (u.roles.length - 2) + '</span>' : '')
      : '<span class="admin-badge admin-badge--muted">—</span>';
    var statusCell = u.blocked
      ? '<span class="admin-badge admin-badge--danger">' + esc(t('admin.status_blocked')) + '</span>'
      : '<span class="admin-badge admin-badge--success">' + esc(t('admin.status_active')) + '</span>';
    var rolesLabel = tx('admin.user_roles_toggle', 'Roles');

    return '<tr data-user-id="' + id + '">' +
        '<td class="admin-table__id" data-col="id">' + id + '</td>' +
        '<td data-col="name">' + esc(u.username) + '</td>' +
        '<td data-col="email"><span class="admin-table__clip" title="' + esc(u.email) + '">' + esc(u.email) + '</span></td>' +
        '<td data-col="role">' + roleCell + '</td>' +
        '<td data-col="status" data-status-cell>' + statusCell + '</td>' +
        '<td data-col="actions"><div class="admin-actions">' +
          '<a class="btn btn--secondary" href="/users/' + id + '">' + esc(t('admin.action_view')) + '</a>' +
          '<button type="button" class="btn btn--secondary" data-admin-block' +
            ' data-user-id="' + id + '" data-user-name="' + esc(u.username) + '"' + (u.blocked ? ' disabled' : '') + '>' +
            esc(t('admin.action_block')) +
          '</button>' +
        '</div></td>' +
        '<td class="admin-table__expand" data-col="expand">' +
          '<button type="button" class="admin-expand" data-user-expand' +
            ' data-user-id="' + id + '" data-user-name="' + esc(u.username) + '"' +
            ' aria-expanded="false" title="' + esc(rolesLabel) + '" aria-label="' + esc(rolesLabel) + '">' +
            '<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false">' +
              '<path d="M3.5 6l4.5 4.5L12.5 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>' +
            '</svg>' +
          '</button>' +
        '</td>' +
      '</tr>' +
      '<tr class="admin-user-roles" data-user-roles-row="' + id + '" hidden>' +
        '<td colspan="7"><div class="admin-user-roles__body" data-user-roles-body></div></td>' +
      '</tr>';
  }

  function setUsersStatus(text) {
    if (!usersList.status) return;
    usersList.status.textContent = text || '';
    usersList.status.hidden = !text;
  }

  function usersRender() {
    if (!usersList.tbody) return;
    if (!usersList.items.length) {
      usersList.tbody.innerHTML = '<tr class="admin-table__empty"><td colspan="7">' +
        esc(usersList.query ? t('admin.no_results', { query: usersList.query }) : t('admin.empty')) + '</td></tr>';
    } else {
      usersList.tbody.innerHTML = usersList.items.map(usersRowHtml).join('');
    }
    if (usersList.count) {
      usersList.count.textContent = String(usersList.total !== null ? usersList.total : usersList.items.length);
    }
    if (usersList.more) usersList.more.hidden = !usersList.hasMore;
  }

  /** reset=true — новый запрос (поиск), иначе подгрузка следующей страницы. */
  function usersLoad(reset) {
    var st = usersList;
    if (!st.tbody) return Promise.resolve();
    if (reset) { st.page = 1; }
    var seq = ++st.seq;
    st.loading = true;
    if (st.more) st.more.disabled = true;
    setUsersStatus(tx('admin.users_loading', 'Loading users…'));

    var qs = 'page=' + st.page + '&ps=' + st.ps + (st.query ? '&q=' + encodeURIComponent(st.query) : '');
    return urFetch('GET', '/api/users?' + qs).then(function (r) {
      if (seq !== st.seq) return;
      st.loading = false;
      if (st.more) st.more.disabled = false;

      var parsed = r.ok ? usersParse(r.data) : null;
      if (!parsed) {
        setUsersStatus(tx('admin.users_load_error', 'Failed to load users') + ': ' + (r.ok ? tx('errors.common.server_error', 'Server error. Please try again later.') : urError(r)));
        if (reset) { st.items = []; st.total = null; st.hasMore = false; usersRender(); }
        return;
      }

      var known = {};
      var base = reset ? [] : st.items;
      base.forEach(function (u) { known[u.id] = true; });
      var fresh = parsed.items.filter(function (u) { return !known[u.id]; });

      st.items = base.concat(fresh);
      st.total = parsed.total;
      // Страниц больше нет, если новых строк не пришло (бэк мог проигнорировать page)
      st.hasMore = fresh.length > 0 && (parsed.total !== null ? st.items.length < parsed.total : parsed.items.length >= st.ps);
      if (reset) userRoles.cache = {};
      setUsersStatus('');
      usersRender();
    });
  }

  function usersSearch(query) {
    var q = String(query || '').trim();
    if (q === usersList.query && usersList.items.length) return;
    usersList.query = q;
    usersLoad(true);
  }

  function initUsersList() {
    var tbody = document.querySelector('[data-users-rows]');
    if (!tbody) return;
    usersList.tbody = tbody;
    usersList.status = document.querySelector('[data-users-status]');
    usersList.count = document.querySelector('[data-users-count]');
    usersList.search = document.querySelector('[data-users-search]');
    usersList.more = document.querySelector('[data-users-more]');

    if (usersList.search) {
      // Запрос в API — только по Enter, не на каждый символ
      usersList.search.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        usersSearch(usersList.search.value);
      });
      usersList.search.addEventListener('search', function () {
        if ((usersList.search.value || '').trim() === '' && usersList.query !== '') usersSearch('');
      });
    }

    if (usersList.more) {
      usersList.more.addEventListener('click', function () {
        if (usersList.loading) return;
        usersList.page += 1;
        usersLoad(false);
      });
    }

    usersLoad(true);
  }

  /* ============================================================
     GLOBAL CLICKS
     ============================================================ */
  document.addEventListener('click', function (e) {
    if (!e.target || !e.target.closest) return;

    // --- «+» между строками списка ролей (ROLES DND) ---
    if (e.target.closest('[data-role-insert]')) {
      if (insertDisabled()) { hideInsert(); return; }
      var at = dnd.insertAt;
      hideInsert();
      openRoleNew(at);
      return;
    }

    // --- Новая роль (ROLE CREATE) ---
    if (handleRoleNewClick(e.target)) return;

    // --- Справочники (Genres / Categories / Types) ---
    var dictAdd = e.target.closest('[data-admin-dict-add]');
    if (dictAdd) { openDictForm(null); return; }

    var dictEdit = e.target.closest('[data-admin-dict-edit]');
    if (dictEdit) { openDictForm(dictEdit.getAttribute('data-dict-row-id')); return; }

    var dictDelete = e.target.closest('[data-admin-dict-delete]');
    if (dictDelete) { deleteDictItem(dictDelete); return; }

    var langPick = e.target.closest('[data-dict-lang-pick]');
    if (langPick) {
      addLanguage(langPick.getAttribute('data-dict-lang-pick'));
      var dd = langPick.closest('[data-dropdown]');
      if (dd) dd.classList.remove('is-open');
      return;
    }

    var langRemove = e.target.closest('[data-dict-lang-remove]');
    if (langRemove) { removeLanguage(langRemove); return; }

    if (e.target.closest('[data-dict-dismiss], [data-dict-cancel]')) { closeDictForm(); return; }

    // --- Жалобы (REPORTS) ---
    var reportReview = e.target.closest('[data-admin-report-review]');
    if (reportReview) { openReportModal(reportReview); return; }

    if (e.target.closest('[data-admin-report-dismiss], [data-admin-report-cancel]')) { closeReportModal(); return; }

    if (e.target.closest('[data-admin-report-reject]')) { decideReport('reject'); return; }
    if (e.target.closest('[data-admin-report-delete]')) { decideReport('delete'); return; }

    // --- Системные роли (ROLES) ---
    // Кнопка «+» на странице roles: добавить роль в проект с правами
    if (e.target.closest('[data-role-add]')) {
      openRoleAttach();
      return;
    }

    // Дропдаун выбора роли в модалке «+»
    if (e.target.closest('[data-role-dd-toggle]')) { toggleRoleDd(); return; }
    var ddPick = e.target.closest('[data-role-dd-pick]');
    if (ddPick) { pickRoleDd(ddPick.getAttribute('data-role-dd-pick')); return; }
    if (isRoleOpen() && isRoleDdOpen() && !e.target.closest('[data-role-dd]')) closeRoleDd();

    // Назначение роли пользователю (отдельная модалка)
    if (e.target.closest('[data-role-user-open]')) {
      openRoleUserModal();
      return;
    }

    // Кнопка Permissions в строке системной роли
    var roleEdit = e.target.closest('[data-role-edit], [data-role-user-assign-role]');
    if (roleEdit) {
      if (moveQueue.isPending(roleEdit.getAttribute('data-role-id'))) { 
        notify(tx('admin.role_pending', 'The role is being moved. Try again in a moment'), 'error'); 
        return; 
      }
      if (roleEdit.hasAttribute('data-role-user-assign-role')) {
        openRoleUserModal(roleEdit.getAttribute('data-role-id'));
      } else {
        openRoleModal(roleEdit.getAttribute('data-role-id'));
      }
      return;
    }

    if (e.target.closest('[data-role-user-close]')) {
      closeRoleUserModal();
      return;
    }

    var roleDelete = e.target.closest('[data-role-delete]');
    if (roleDelete && !roleDelete.disabled) {
      if (moveQueue.isPending(roleDelete.getAttribute('data-role-id'))) { notify(tx('admin.role_pending', 'The role is being moved. Try again in a moment'), 'error'); return; }
      deleteRole(roleDelete);
      return;
    }

    if (e.target.closest('[data-role-close]')) { closeRoleModal(); return; }

    var roleSave = e.target.closest('[data-role-save]');
    if (roleSave && !roleSave.disabled) { saveRole(); return; }

    // --- Все роли (ALL ROLES) ---
    var allRolesDelete = e.target.closest('[data-allroles-delete]');
    if (allRolesDelete && !allRolesDelete.disabled) { 
      e.preventDefault();
      deleteAllRole(allRolesDelete); 
      return; 
    }

    // --- Пользователи / комментарии ---
    var blockBtn = e.target.closest('[data-admin-block]');
    if (blockBtn && !blockBtn.disabled) { blockUser(blockBtn); return; }

    var deleteBtn = e.target.closest('[data-admin-delete]');
    if (deleteBtn) deleteComment(deleteBtn);
  });

  // Поиск в дропдауне выбора роли: запрос к API только по Enter (и при очистке поля крестиком)
  document.addEventListener('search', function (e) {
    var search = e.target && e.target.closest && e.target.closest('[data-role-dd-search]');
    if (search && (search.value || '').trim() === '' && roleState.ddQuery) searchRoleDd('');
  });

  document.addEventListener('keydown', function (e) {
    var search = e.target && e.target.closest && e.target.closest('[data-role-dd-search]');
    if (!search) return;
    if (e.key === 'Enter') {
      e.preventDefault();
      searchRoleDd(search.value);
    } else if (e.key === 'ArrowDown') {
      e.preventDefault();
      var item = ddEl('[data-role-dd-list] [data-role-dd-pick]');
      if (item) item.focus();
    }
  });

  // Чекбоксы прав роли: обновляем черновик (источник правды — roleState.draft)
  document.addEventListener('change', function (e) {
    if (!e.target || !e.target.closest) return;

    var select = e.target.closest('[data-role-select]');
    if (select && roleState.draft && roleState.current && roleState.current.isNew) {
      var picked = select.value ? findRole(select.value) : null;
      if (picked) {
        roleState.draft.system = Object.assign({}, picked.systemPermissions);
        roleState.draft.posts  = Object.assign({}, picked.postPermissions);
        roleState.draft.pubs   = Object.assign({}, picked.pubPermissions);
        renderRoleLists();
      }
      return;
    }

    var cb = e.target.closest('[data-role-perm]');
    if (!cb || !roleState.draft) return;
    var group = cb.getAttribute('data-role-group');
    var key = cb.getAttribute('data-role-key');
    if (roleState.draft[group]) roleState.draft[group][key] = cb.checked;
  });

  document.addEventListener('keydown', function (e) {
    var dictOpen = isDictOpen();
    var reportOpen = isReportOpen();
    var roleOpen = isRoleOpen();
    var userRoleOpen = !!(roleUserAssign.modal && !roleUserAssign.modal.hidden);
    if (!dictOpen && !reportOpen && !roleOpen && !userRoleOpen) return;

    if (e.key === 'Escape') {
      e.preventDefault();
      if (roleOpen && isRoleDdOpen()) { closeRoleDd(); return; }
      if (dictOpen) closeDictForm();
      else if (reportOpen) closeReportModal();
      else if (userRoleOpen) closeRoleUserModal();
      else closeRoleModal();
      return;
    }
    if (e.key !== 'Tab') return;

    var dialog;
    if (dictOpen) dialog = dict.modal.querySelector('.modal__dialog');
    else if (reportOpen) dialog = reportState.modal.querySelector('.modal__dialog');
    else if (userRoleOpen) dialog = roleUserAssign.modal.querySelector('.modal__dialog');
    else dialog = roleState.modal.querySelector('.modal__dialog');
    if (!dialog) return;
    var items = Array.prototype.filter.call(
      dialog.querySelectorAll('button, [href], input, select, textarea, [tabindex]'),
      function (el) { return !el.disabled && el.offsetParent !== null; }
    );
    if (!items.length) return;

    var first = items[0];
    var last = items[items.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });

  function initAdmin() {
    initSearch();
    initDictionary();
    initRoles();
    initRoleNew();
    initRoleUserModal();
    initAllRoles();
    initUserRoles();
    initUsersList();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAdmin);
  } else {
    initAdmin();
  }
})();