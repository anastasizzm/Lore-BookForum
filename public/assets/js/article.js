"use strict";

/* ============================================
   Article details page
   Save article: POST/DELETE /api/articles/{id}/save
   (книжный аналог лежит в app.js — там /api/books/{id}/save)
   ============================================ */
(function () {
  var busy = new WeakSet();

  function csrfInput() {
    return document.querySelector(
      '[data-csrf] input[type="hidden"], ' +
      'input[type="hidden"][name*="csrf" i], ' +
      'input[type="hidden"][name*="token" i]'
    );
  }

  function toast(text) {
    var el = document.createElement('div');
    el.setAttribute('role', 'alert');
    el.textContent = text;
    el.style.cssText =
      'position:fixed;left:50%;bottom:24px;transform:translateX(-50%);' +
      'background:#222;color:#fff;padding:10px 16px;border-radius:8px;' +
      'font-size:14px;z-index:1000;max-width:90vw;';
    document.body.appendChild(el);
    setTimeout(function () { el.remove(); }, 3500);
  }

  function setState(btn, saved) {
    btn.classList.toggle('is-active', saved);
    btn.setAttribute('aria-pressed', String(saved));
    btn.setAttribute('aria-label', saved ? 'Remove from saved' : 'Save article');
  }

  function bumpSavesCount(delta) {
    var el = document.querySelector('[data-saves-count]');
    if (el) el.textContent = Math.max(0, (parseInt(el.textContent, 10) || 0) + delta);
  }

  document.addEventListener('click', async function (e) {
    var btn = e.target.closest('[data-save-article]');
    if (!btn) return;
    e.preventDefault();
    if (busy.has(btn)) return;

    var id = Number(btn.dataset.articleId);
    if (!id) {
      console.error('Save article: data-article-id is missing');
      toast('Could not save the article. Please reload the page.');
      return;
    }

    var wasSaved = btn.classList.contains('is-active');
    var willSave = !wasSaved;

    setState(btn, willSave);
    busy.add(btn);
    btn.disabled = true;

    var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    var body = new URLSearchParams();
    var token = csrfInput();
    if (token) {
      headers['X-CSRF-Token'] = token.value;
      body.set(token.name, token.value);
    }

    var url = '/api/articles/' + id + '/save';
    var method = willSave ? 'POST' : 'DELETE';

    try {
      var res = await fetch(url, {
        method: method,
        credentials: 'same-origin',
        headers: headers,
        body: body
      });

      var data = null;
      try { data = await res.json(); } catch (_) {}

      if (res.ok) {
        bumpSavesCount(willSave ? 1 : -1);
        btn.dispatchEvent(new CustomEvent('article:save-changed', {
          bubbles: true,
          detail: { id: id, saved: willSave }
        }));
      } else {
        setState(btn, wasSaved);
        var msg = '';
        if (data && data.errors) msg = Object.values(data.errors).flat().join('\n');
        if (!msg && data && data.message) msg = data.message;
        if (!msg) {
          msg = res.status === 403 ? 'Forbidden (verify email / CSRF?)'
              : res.status === 401 ? 'Please sign in again'
              : 'Failed to update saved articles (HTTP ' + res.status + ')';
        }
        toast(msg);
      }
    } catch (err) {
      setState(btn, wasSaved);
      toast('Network error. Try again.');
    } finally {
      busy.delete(btn);
      btn.disabled = false;
    }
  });
})();
