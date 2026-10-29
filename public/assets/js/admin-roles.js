(function () {
  'use strict';

  const container = document.querySelector('[data-roles-container]');
  if (!container) return;

  const API = container.dataset.api;
  const listEl = container.querySelector('[data-roles-list]');
  const spinner = container.querySelector('[data-roles-spinner]');

  const modal = document.querySelector('[data-role-modal]');
  const form = modal.querySelector('[data-role-form]');
  const modalTitle = modal.querySelector('[data-role-modal-title]');
  const submitBtn = form.querySelector('[data-role-submit]');

  const POLL_INTERVAL_MS = 15000;

  let state = {
    roles: [],        
    editingId: null,  
    busy: false,      
  };

  let pollTimer = null;
  let isDragging = false; 

  // --- CSRF ---
  function csrfHeaders(extra = {}) {
    const token = window.LoreCsrf ? window.LoreCsrf.token() : '';
    const headers = new Headers({
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    });
    if (token) headers.set('X-CSRF-Token', token);

    Object.keys(extra).forEach(key => {
      if (extra[key] != null) headers.set(key, extra[key]);
    });

    return headers;
  }

  function notify(msg, type = 'error') {
    if (window.Messages && typeof window.Messages.show === 'function') {
      window.Messages.show(msg, { type });
      return;
    }
    console.warn('[admin-roles]', msg);
    if (type === 'error') {
      try { window.alert(msg); } catch (_) {}
    }
  }

  function setLoading(loading) {
    if (spinner) spinner.hidden = !loading;
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  // ==========================================
  // 1. ЗАГРУЗКА + MERGE
  // ==========================================
  async function loadRoles({ silent = false } = {}) {
    if (!silent) setLoading(true);
    try {
      const res = await fetch(API, { headers: csrfHeaders() });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = await res.json();

      const incoming = json.items || (Array.isArray(json) ? json : []);
      state.roles = incoming;

      mergeList(incoming);
    } catch (e) {
      console.error('[admin-roles] Load failed', e);
      if (!silent) {
        listEl.innerHTML = '<li class="admin-roles__loading" style="color:red">Ошибка загрузки ролей.</li>';
      }
    } finally {
      if (!silent) setLoading(false);
    }
  }

  function mergeList(incoming) {
    const desiredIds = new Set(incoming.map(r => String(r.id)));
    const existing = new Map();
    [...listEl.children].forEach(li => {
      if (li.dataset.roleId) existing.set(li.dataset.roleId, li);
    });

    existing.forEach((li, id) => {
      if (!desiredIds.has(id)) li.remove();
    });

    let prev = null;
    incoming.forEach(role => {
      const id = String(role.id);
      let li = existing.get(id);

      if (!li) {
        li = createRoleItem(role);
      } else {
        updateRoleItem(li, role);
      }

      const expectedNext = prev ? prev.nextSibling : listEl.firstChild;
      if (li !== expectedNext) {
        listEl.insertBefore(li, expectedNext || null);
      }
      prev = li;
    });

    if (!listEl.children.length) {
      listEl.innerHTML = '<li class="admin-roles__loading">Ролей пока нет. Создайте первую!</li>';
    }
  }

  function createRoleItem(role) {
    const li = document.createElement('li');
    li.className = 'admin-role-item';
    li.dataset.roleId = role.id;
    li.innerHTML = `
      <div class="admin-role-drag-handle" title="Перетащить">⋮⋮</div>
      <div class="admin-role-info">
        <span class="admin-role-name"></span>
        <span class="admin-role-flags"></span>
      </div>
      <div class="admin-role-actions">
        <button type="button" class="btn btn--sm btn--secondary" data-action="edit">✎</button>
        <button type="button" class="btn btn--sm btn--danger" data-action="delete">✕</button>
      </div>
    `;

    // Берем самую актуальную версию из state для клика (защита от рассинхрона)
    const currentRole = () => state.roles.find(r => String(r.id) === String(role.id)) || role;

    const editBtn = li.querySelector('[data-action="edit"]');
    const delBtn = li.querySelector('[data-action="delete"]');
    
    editBtn.onclick = () => openModal(currentRole());
    delBtn.onclick = () => deleteRole(currentRole(), li);

    const handle = li.querySelector('.admin-role-drag-handle');
    handle.setAttribute('draggable', 'true');
    attachDragEvents(handle, li);

    updateRoleItem(li, role);
    return li;
  }

  function updateRoleItem(li, role) {
    li.dataset.roleId = role.id;
    // Учитываем, что version может быть числом
    li.dataset.version = role.version ?? role.etag ?? '';

    const canManage = role.canManage !== false;
    li.classList.toggle('is-locked', !canManage);

    const nameEl = li.querySelector('.admin-role-name');
    if (nameEl) nameEl.textContent = role.name || 'Без названия';

    const flagsEl = li.querySelector('.admin-role-flags');
    if (flagsEl) flagsEl.textContent = getFlagsSummary(role);

    const handle = li.querySelector('.admin-role-drag-handle');
    if (handle) {
      handle.title = canManage ? 'Перетащить' : 'Нет прав';
      handle.setAttribute('draggable', canManage ? 'true' : 'false');
    }

    const editBtn = li.querySelector('[data-action="edit"]');
    const delBtn = li.querySelector('[data-action="delete"]');
    if (editBtn) editBtn.disabled = !canManage;
    if (delBtn) delBtn.disabled = !canManage;

    // FIX: если роль открыта в модалке — обновляем скрытую версию
    if (state.editingId && String(role.id) === String(state.editingId)) {
      const versionInput = form.elements['version'];
      if (versionInput) versionInput.value = role.version ?? role.etag ?? '';
    }
  }

  function getFlagsSummary(role) {
    const active = [];
    const sys = role.systemPermissions || {};
    const post = role.postPermissions || {};
    const pub = role.pubPermissions || {};

    if (sys.manageAccounts) active.push('Аккаунты');
    if (sys.manageAdditionals) active.push('Справочники');
    if (sys.managePeople) active.push('Люди');
    if (sys.manageRoles) active.push('Системные роли');

    if (post.create || post.update || post.delete) active.push('Посты');
    if (pub.manageRoles) active.push('Роли публикаций');

    return active.length ? active.join(', ') : 'Нет прав';
  }

  // ==========================================
  // 2. DRAG AND DROP
  // ==========================================
  let draggedEl = null;

  function attachDragEvents(handle, li) {
    handle.addEventListener('dragstart', (e) => {
      if (li.classList.contains('is-locked')) {
        e.preventDefault();
        return;
      }
      [...listEl.children].forEach(el => el.classList.remove('is-dragging', 'drag-over-top', 'drag-over-bottom'));
      draggedEl = li;
      isDragging = true;
      li.classList.add('is-dragging');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', li.dataset.roleId);
    });

    handle.addEventListener('dragend', () => {
      [...listEl.children].forEach(el => el.classList.remove('is-dragging'));
      li.classList.remove('is-dragging');
      clearDragOver();
      draggedEl = null;
      isDragging = false;
      schedulePoll();
    });

    li.addEventListener('dragover', (e) => {
      e.preventDefault();
      if (!draggedEl || li === draggedEl) return;

      const rect = li.getBoundingClientRect();
      const midpoint = rect.top + rect.height / 2;
      const isTop = e.clientY < midpoint;

      // FIX: Проверяем canDropAbove только для верхней половины
      if (isTop && !canDropAbove(li)) {
        clearDragOver();
        return;
      }

      clearDragOver();
      if (isTop) {
        li.classList.add('drag-over-top');
      } else {
        li.classList.add('drag-over-bottom');
      }
    });

    li.addEventListener('dragleave', () => {
      li.classList.remove('drag-over-top', 'drag-over-bottom');
    });

    li.addEventListener('drop', async (e) => {
      e.preventDefault();
      if (!draggedEl || li === draggedEl) return;

      const isTop = li.classList.contains('drag-over-top');
      clearDragOver();

      if (isTop && !canDropAbove(li)) {
        notify('Нельзя переместить выше заблокированной роли', 'error');
        return;
      }

      const orderSnapshot = [...listEl.children].map(el => el.dataset.roleId);

      if (isTop) {
        listEl.insertBefore(draggedEl, li);
      } else {
        listEl.insertBefore(draggedEl, li.nextSibling);
      }

      const newOrder = [...listEl.children];
      const currentIndex = newOrder.indexOf(draggedEl);
      const aboveId = currentIndex > 0 ? newOrder[currentIndex - 1].dataset.roleId : null;
      const belowId = currentIndex < newOrder.length - 1 ? newOrder[currentIndex + 1].dataset.roleId : null;

      const roleId = draggedEl.dataset.roleId;
      const version = draggedEl.dataset.version;

      if (!version) {
        notify('Нет версии роли. Обновите список.', 'error');
        rollbackOrder(orderSnapshot);
        return;
      }

      state.busy = true;
      try {
        const res = await fetch(`${API}/${roleId}/position`, {
          method: 'PATCH',
          headers: csrfHeaders({ 'If-Match': `"${version}"` }),
          body: JSON.stringify({ aboveId, belowId })
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const newEtag = res.headers.get('ETag');
        if (newEtag && draggedEl) {
          draggedEl.dataset.version = newEtag.replace(/"/g, '');
        }
        await loadRoles({ silent: true });
      } catch (err) {
        console.error('[admin-roles] Move failed', err);
        notify('Не удалось переместить. Возможно, роли были изменены. Обновление...', 'error');
        rollbackOrder(orderSnapshot);
        await loadRoles({ silent: true });
      } finally {
        state.busy = false;
        draggedEl = null;
        schedulePoll();
      }
    });
  }

  // FIX: запрещаем бросать выше locked-роли и НАД самой locked-ролью
  function canDropAbove(li) {
    if (!li || li.classList.contains('is-locked')) return false;
    let prev = li.previousElementSibling;
    while (prev) {
      if (prev.classList.contains('is-locked')) return false;
      prev = prev.previousElementSibling;
    }
    return true;
  }

  function clearDragOver() {
    [...listEl.children].forEach(el => el.classList.remove('drag-over-top', 'drag-over-bottom'));
  }

  function rollbackOrder(orderIds) {
    if (!Array.isArray(orderIds)) return;
    const map = new Map([...listEl.children].map(el => [el.dataset.roleId, el]));
    orderIds.forEach(id => {
      const el = map.get(id);
      if (el) listEl.appendChild(el);
    });
  }

  // ==========================================
  // 3. МОДАЛКА
  // ==========================================
  function openModal(roleOrNull = null) {
    if (roleOrNull && roleOrNull.canManage === false) {
      notify('Недостаточно прав для изменения этой роли', 'error');
      return;
    }

    // FIX: берем актуальные данные из state.roles
    const role = roleOrNull
      ? (state.roles.find(r => String(r.id) === String(roleOrNull.id)) || roleOrNull)
      : null;

    state.editingId = role ? role.id : null;
    modalTitle.textContent = role ? 'Редактирование роли' : 'Создание роли';

    form.reset();
    form.elements['id'].value = role ? role.id : '';
    form.elements['version'].value = role ? (role.version ?? role.etag ?? '') : '';
    form.elements['name'].value = role ? (role.name || '') : '';

    if (role) {
      fillCheckboxes('systemPermissions', role.systemPermissions);
      fillCheckboxes('postPermissions', role.postPermissions);
      fillCheckboxes('pubPermissions', role.pubPermissions);
    }

    modal.hidden = false;
    const first = form.querySelector('input[name="name"]');
    if (first) first.focus();
  }

  function fillCheckboxes(prefix, rules) {
    if (!rules) return;
    Object.keys(rules).forEach(key => {
      const input = form.elements[`${prefix}[${key}]`];
      if (input) input.checked = !!rules[key];
    });
  }

  function closeModal() {
    modal.hidden = true;
    state.editingId = null;
  }

  // ==========================================
  // 4. СОХРАНЕНИЕ (POST / PUT)
  // ==========================================
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (state.busy) return;

    const formData = new FormData(form);
    const data = {
      name: formData.get('name'),
      systemPermissions: {},
      postPermissions: {},
      pubPermissions: {}
    };

    ['manageAccounts', 'manageAdditionals', 'managePeople', 'manageRoles'].forEach(k => {
      const el = form.elements[`systemPermissions[${k}]`];
      if (el && el.checked) data.systemPermissions[k] = true;
    });
    ['create', 'update', 'delete'].forEach(k => {
      const el = form.elements[`postPermissions[${k}]`];
      if (el && el.checked) data.postPermissions[k] = true;
    });
    ['create', 'read', 'updateMeta', 'updateContent', 'delete', 'managePeople', 'manageRoles'].forEach(k => {
      const el = form.elements[`pubPermissions[${k}]`];
      if (el && el.checked) data.pubPermissions[k] = true;
    });

    const isEdit = !!state.editingId;
    const url = isEdit ? `${API}/${state.editingId}` : API;
    const method = isEdit ? 'PUT' : 'POST';
    const version = form.elements['version'].value;

    if (isEdit && !version) {
      notify('Нет версии роли. Обновите список и повторите.', 'error');
      loadRoles({ silent: true });
      return;
    }

    const headers = csrfHeaders(isEdit ? { 'If-Match': `"${version}"` } : {});

    state.busy = true;
    if (submitBtn) submitBtn.disabled = true;
    try {
      const res = await fetch(url, {
        method,
        headers,
        body: JSON.stringify(data)
      });

      if (!res.ok) throw new Error(`HTTP ${res.status}`);

      notify('Сохранено', 'success');
      closeModal();
      await loadRoles({ silent: true });
    } catch (err) {
      console.error('[admin-roles] Save failed', err);
      notify('Ошибка сохранения. Проверьте данные или обновите страницу.', 'error');
      loadRoles({ silent: true });
    } finally {
      state.busy = false;
      if (submitBtn) submitBtn.disabled = false;
      schedulePoll();
    }
  });

  // ==========================================
  // 5. УДАЛЕНИЕ
  // ==========================================
  async function deleteRole(role, li) {
    if (role.canManage === false) {
      notify('Недостаточно прав для удаления этой роли', 'error');
      return;
    }
    if (!confirm(`Удалить роль "${role.name}"?`)) return;
    if (state.busy) return;

    const version = li.dataset.version;
    if (!version) {
      notify('Нет версии роли. Обновите список.', 'error');
      loadRoles({ silent: true });
      return;
    }

    state.busy = true;
    try {
      const res = await fetch(`${API}/${role.id}`, {
        method: 'DELETE',
        headers: csrfHeaders({ 'If-Match': `"${version}"` })
      });

      if (!res.ok) throw new Error(`HTTP ${res.status}`);

      li.remove();
      notify('Роль удалена', 'success');
      await loadRoles({ silent: true });
    } catch (err) {
      console.error('[admin-roles] Delete failed', err);
      notify('Ошибка удаления.', 'error');
      loadRoles({ silent: true });
    } finally {
      state.busy = false;
      schedulePoll();
    }
  }

  // ==========================================
  // 6. POLLING
  // ==========================================
  function schedulePoll() {
    if (pollTimer) clearTimeout(pollTimer);
    pollTimer = setTimeout(async () => {
      if (isDragging || state.busy || !modal.hidden) {
        schedulePoll();
        return;
      }
      await loadRoles({ silent: true });
      schedulePoll();
    }, POLL_INTERVAL_MS);
  }

  // ==========================================
  // 7. ИНИЦИАЛИЗАЦИЯ
  // ==========================================
  const createBtn = document.querySelector('[data-role-create]');
  if (createBtn) createBtn.onclick = () => openModal(null);

  modal.querySelectorAll('[data-role-modal-close]').forEach(el => {
    el.onclick = closeModal;
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.hidden) closeModal();
  });

  loadRoles();
  schedulePoll();

})();