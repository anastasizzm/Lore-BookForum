import * as pdfjsLib from './pdfjs/pdf.min.js';

/* ============================================
   Читалка PDF: загрузка из API, prev / next / zoom, счётчик страниц.
   Разметка — views/pages/book/book-read.php (data-reader-*).
   ============================================ */

pdfjsLib.GlobalWorkerOptions.workerSrc = '/assets/js/pdfjs/pdf.worker.min.js';

const root = document.querySelector('[data-reader]');
if (root) init(root);

async function init(root) {
  const t = (key, params) =>
    window.LoreI18n ? window.LoreI18n.t(key, params) : key;
  const $ = (sel) => root.querySelector(sel);

  const canvas  = $('[data-reader-canvas]');
  const stage   = $('[data-reader-stage]');
  const ctx     = canvas.getContext('2d');
  const pageIn  = $('[data-reader-page]');
  const totalEl = $('[data-reader-total]');
  const zoomLbl = $('[data-reader-zoom-label]');
  const status  = $('[data-reader-status]');
  const prevBtn = $('[data-reader-prev]');
  const nextBtn = $('[data-reader-next]');

  const bookId   = root.dataset.bookId || '0';
  const storeKey = 'book-' + bookId + '-page';   // ключ прогресса (см. saveProgress)
  const legacyKey = 'reader:page:' + bookId;     // старый ключ — читаем для миграции
  const DESKTOP_FACTOR = 0.5;
  const MOBILE_FACTOR  = 1;
  const MOBILE_MAX     = 767;
  const baseFactor = () => (stage.clientWidth <= MOBILE_MAX ? MOBILE_FACTOR : DESKTOP_FACTOR);
  const Z_MIN = 0.5, Z_MAX = 4, Z_STEP = 0.25;
  const MAX_PIXELS = 12000000;

  canvas.style.maxWidth = 'none';

  const blockBrowserZoom = (e) => { if (e.ctrlKey) e.preventDefault(); };
  root.addEventListener('wheel', blockBrowserZoom, { passive: false });
  root.addEventListener('gesturestart',  (e) => e.preventDefault(), { passive: false });
  root.addEventListener('gesturechange', (e) => e.preventDefault(), { passive: false });
  root.addEventListener('touchmove', (e) => { if (e.touches.length > 1) e.preventDefault(); }, { passive: false });

  const syncDock = () => {
    const r = stage.getBoundingClientRect();
    const rootStyle = root.style;
    rootStyle.setProperty('--reader-dock-x', Math.round(r.left + r.width / 2) + 'px');
    rootStyle.setProperty(
      '--reader-dock-right',
      Math.max(16, window.innerWidth - r.right + 16) + 'px'
    );
  };
  syncDock();
  window.addEventListener('resize', syncDock);
  if (window.ResizeObserver) new ResizeObserver(syncDock).observe(stage);

  /**
   * saveProgress — сохранение прогресса чтения (JS-хук на смену страницы).
   *
   * Пока храним в localStorage (ключ book-{id}-page). Когда бэк даст
   * API прогресса — заменить тело на fetch PATCH /api/books/{id}/progress
   * с body { page: num } и заголовком X-CSRF-Token (LoreCsrf.token()).
   */
  function saveProgress(num) {
    try { localStorage.setItem(storeKey, String(num)); } catch (_) {}
    // TODO(api): fetch('/api/books/' + bookId + '/progress', { method: 'PATCH', ... })
  }

  const toast = (text) => window.Messages?.show(text, { type: 'error' });
  const setStatus = (text) => {
    if (!status) return;
    status.textContent = text || '';
    status.hidden = !text;
  };

  let url = root.dataset.pdfUrl || '';
  const src = new URLSearchParams(location.search).get('src');
  if (src && src.startsWith('/') && !src.startsWith('//')) url = src;

  if (!url) {
    setStatus('');
    toast(t('errors.reader.book_file_missing'));
    return;
  }

  let pdf;
  try {
    const res = await fetch(url, {
      credentials: 'same-origin',
      headers: { Accept: 'application/pdf, application/json' },
    });
    if (!res.ok) {
      setStatus('');
      await window.Messages?.fail(res, t('errors.reader.book_file_load_failed'));
      return;
    }
    const data = await res.arrayBuffer();
    pdf = await pdfjsLib.getDocument({
      data,
      cMapUrl: '/assets/js/pdfjs/cmaps/',
      cMapPacked: true,
      standardFontDataUrl: '/assets/js/pdfjs/standard_fonts/',
    }).promise;
  } catch (e) {
    console.error('[reader] load failed', e);
    setStatus('');
    toast(e && e.name === 'InvalidPDFException'
      ? t('errors.reader.pdf_invalid')
      : t('errors.reader.book_open_failed'));
    return;
  }

  let zoom = 1;
  let pageNum = 1;
  let rendering = false;
  let queued = false;
  let renderTask = null;
  let pendingCenter = true;

  totalEl.textContent = pdf.numPages;
  pageIn.max = pdf.numPages;

  const fromUrl = parseInt(new URLSearchParams(location.search).get('page'), 10);
  let saved = 0;
  try {
    saved = parseInt(localStorage.getItem(storeKey), 10)
         || parseInt(localStorage.getItem(legacyKey), 10)   // старый ключ reader:page:{id}
         || 0;
  } catch (_) {}
  pageNum = clamp(fromUrl || saved || 1, 1, pdf.numPages);

  setStatus('');
  render();

  async function render() {
    if (rendering) {
      queued = true;
      if (renderTask) renderTask.cancel();
      return;
    }
    rendering = true;

    try {
      for (;;) {
        queued = false;
        const z = zoom, n = pageNum;
        const stale = () => queued || z !== zoom || n !== pageNum;

        const page = await pdf.getPage(n);
        if (stale()) continue;

        const cs = getComputedStyle(stage);
        const inner = stage.clientWidth - (parseFloat(cs.paddingLeft) || 0) - (parseFloat(cs.paddingRight) || 0);
        const baseWidth = Math.max(inner - 2, 200);
        const base = baseWidth / page.getViewport({ scale: 1 }).width;
        const viewport = page.getViewport({ scale: base * z * baseFactor() });

        let outScale = window.devicePixelRatio || 1;
        const area = viewport.width * viewport.height;
        if (area * outScale * outScale > MAX_PIXELS) outScale = Math.sqrt(MAX_PIXELS / area);

        const buf = document.createElement('canvas');
        buf.width  = Math.floor(viewport.width * outScale);
        buf.height = Math.floor(viewport.height * outScale);

        renderTask = page.render({
          canvasContext: buf.getContext('2d'),
          viewport,
          transform: outScale !== 1 ? [outScale, 0, 0, outScale, 0, 0] : null,
        });

        let failed = false;
        try {
          await renderTask.promise;
        } catch (e) {
          if (!e || e.name !== 'RenderingCancelledException') {
            failed = true;
            console.error('[reader] render failed', e);
            toast(t('errors.reader.page_render_failed'));
          }
        } finally {
          renderTask = null;
        }

        if (stale()) continue;
        if (failed) break;

        canvas.width  = buf.width;
        canvas.height = buf.height;
        ctx.drawImage(buf, 0, 0);
        canvas.style.width  = Math.floor(viewport.width) + 'px';
        canvas.style.height = Math.floor(viewport.height) + 'px';
        break;
      }
    } catch (e) {
      console.error('[reader] getPage failed', e);
      toast(t('errors.reader.page_render_failed'));
    } finally {
      rendering = false;
    }

    if (pendingCenter) { pendingCenter = false; centerX(); }
    syncUi();
  }

  function syncUi() {
    pageIn.value = pageNum;
    zoomLbl.textContent = Math.round(zoom * 100) + '%';
    prevBtn.disabled = pageNum <= 1;
    nextBtn.disabled = pageNum >= pdf.numPages;

    // Смена страницы -> saveProgress(currentPage): вызывается здесь,
    // потому что render() завершается только после показа новой страницы.
    saveProgress(pageNum);

    const u = new URL(location.href);
    u.searchParams.set('page', String(pageNum));
    history.replaceState(null, '', u.pathname + u.search);
  }

  function goTo(n) {
    n = clamp(Number(n) || 1, 1, pdf.numPages);
    if (n === pageNum) { syncUi(); return; }
    pageNum = n;
    pendingCenter = true;
    if (stage.getBoundingClientRect().top < 0) stage.scrollIntoView({ block: 'start' });
    render();
  }

  let renderTimer = 0;
  const scheduleRender = () => { clearTimeout(renderTimer); renderTimer = setTimeout(render, 140); };

  function zoomAt(newZoom, cx, cy, anchor) {
    newZoom = clamp(newZoom, Z_MIN, Z_MAX);
    if (!anchor && Math.abs(newZoom - zoom) < 0.001) return;

    const r = canvas.getBoundingClientRect();
    if (!r.width || !r.height) return;

    const fx = anchor ? anchor.fx : (cx - r.left) / r.width;
    const fy = anchor ? anchor.fy : (cy - r.top) / r.height;

    const k = newZoom / zoom;
    const w = parseFloat(canvas.style.width)  || r.width;
    const h = parseFloat(canvas.style.height) || r.height;
    canvas.style.width  = (w * k) + 'px';
    canvas.style.height = (h * k) + 'px';
    zoom = newZoom;
    zoomLbl.textContent = Math.round(zoom * 100) + '%';

    const n = canvas.getBoundingClientRect();
    stage.scrollLeft += (n.left + fx * n.width) - cx;
    window.scrollBy(0, (n.top + fy * n.height) - cy);

    scheduleRender();
  }

  function setZoom(z) {
    const r = canvas.getBoundingClientRect();
    const s = stage.getBoundingClientRect();
    const cx = clamp(s.left + s.width / 2, r.left, r.right);
    const cy = clamp(window.innerHeight / 2, r.top, r.bottom);
    zoomAt(z, cx, cy);
  }

  function clamp(n, min, max) { return Math.min(Math.max(n, min), max); }

  const isZoomedWide = () => stage.scrollWidth > stage.clientWidth + 2;

  const centerX = () => { stage.scrollLeft = Math.max(0, (stage.scrollWidth - stage.clientWidth) / 2); };

  root.addEventListener('click', (e) => {
    const btn = e.target.closest('button');
    if (btn && e.detail > 0) btn.blur();

    if (e.target.closest('[data-reader-prev]'))            goTo(pageNum - 1);
    else if (e.target.closest('[data-reader-next]'))       goTo(pageNum + 1);
    else if (e.target.closest('[data-reader-zoom-in]'))    setZoom(zoom + Z_STEP);
    else if (e.target.closest('[data-reader-zoom-out]'))   setZoom(zoom - Z_STEP);
    else if (e.target.closest('[data-reader-zoom-reset]')) setZoom(1);
  });

  pageIn.addEventListener('change', () => goTo(pageIn.value));

  document.addEventListener('keydown', (e) => {
    if (e.ctrlKey || e.metaKey) {
      if (e.altKey || !pointer.inside) return;
      let z = null;
      if (e.key === '+' || e.key === '=') z = zoom + Z_STEP;
      else if (e.key === '-' || e.key === '_') z = zoom - Z_STEP;
      else if (e.key === '0') z = 1;
      if (z === null) return;
      e.preventDefault();
      zoomAt(z, pointer.x, pointer.y);
      return;
    }
    if (e.altKey) return;

    if (e.key === 'Enter' && e.target === pageIn) {
      e.preventDefault();
      goTo(pageIn.value);
      pageIn.blur();
      return;
    }

    if (e.target.closest?.('input, textarea, select')) return;

    if (e.key === 'Enter') {
      if (e.repeat) return;
      if (e.target.closest?.('button, a[href], [role="button"], [contenteditable], dialog, [aria-modal="true"]')) return;
      e.preventDefault();
      goTo(pageNum + (e.shiftKey ? -1 : 1));
      return;
    }

    if (e.key === 'ArrowLeft'  || e.key === 'PageUp')   { if (!(e.key === 'ArrowLeft'  && isZoomedWide())) goTo(pageNum - 1); }
    if (e.key === 'ArrowRight' || e.key === 'PageDown') { if (!(e.key === 'ArrowRight' && isZoomedWide())) goTo(pageNum + 1); }
    if (e.key === '+' || e.key === '=') setZoom(zoom + Z_STEP);
    if (e.key === '-') setZoom(zoom - Z_STEP);
  });

  const WHEEL_SENS = 0.0025;
  const pointer = { x: 0, y: 0, inside: false };
  const trackPointer = (e) => { pointer.x = e.clientX; pointer.y = e.clientY; pointer.inside = true; };
  stage.addEventListener('mouseenter', trackPointer, { passive: true });
  stage.addEventListener('mousemove',  trackPointer, { passive: true });
  stage.addEventListener('mouseleave', () => { pointer.inside = false; }, { passive: true });

  stage.addEventListener('wheel', (e) => {
    if (!e.ctrlKey) return;
    e.preventDefault();
    const delta = clamp(e.deltaMode === 1 ? e.deltaY * 16 : e.deltaY, -120, 120);
    zoomAt(zoom * Math.exp(-delta * WHEEL_SENS), e.clientX, e.clientY);
  }, { passive: false });

  let gestureStartZoom = 1;
  stage.addEventListener('gesturestart', (e) => { e.preventDefault(); gestureStartZoom = zoom; }, { passive: false });
  stage.addEventListener('gesturechange', (e) => {
    e.preventDefault();
    if (pinching) return;
    zoomAt(gestureStartZoom * e.scale, e.clientX ?? pointer.x, e.clientY ?? pointer.y);
  }, { passive: false });
  stage.addEventListener('gestureend', (e) => e.preventDefault(), { passive: false });

  let tx = 0, ty = 0, sx = 0, tracking = false, atLeft = false, atRight = false;
  let pinchStartDist = 0;
  let pinchStartZoom = 1;
  let pinchAnchor = null;
  let pinching = false;

  const edges = () => {
    const max = stage.scrollWidth - stage.clientWidth;
    return { left: stage.scrollLeft <= 1, right: stage.scrollLeft >= max - 1 };
  };

  const distance = (touches) => {
    const dx = touches[0].clientX - touches[1].clientX;
    const dy = touches[0].clientY - touches[1].clientY;
    return Math.hypot(dx, dy);
  };

  const midpoint = (touches) => ({
    x: (touches[0].clientX + touches[1].clientX) / 2,
    y: (touches[0].clientY + touches[1].clientY) / 2,
  });

  stage.addEventListener('touchstart', (e) => {
    if (e.touches.length === 2) {
      pinching = true;
      tracking = false;
      pinchStartDist = distance(e.touches);
      pinchStartZoom = zoom;
      const m = midpoint(e.touches);
      const r = canvas.getBoundingClientRect();
      pinchAnchor = { fx: (m.x - r.left) / r.width, fy: (m.y - r.top) / r.height };
      return;
    }

    if (e.touches.length === 1) {
      pinching = false;
      tracking = true;
      tx = e.touches[0].clientX;
      ty = e.touches[0].clientY;
      sx = stage.scrollLeft;
      ({ left: atLeft, right: atRight } = edges());
    }
  }, { passive: true });

  stage.addEventListener('touchmove', (e) => {
    if (!pinching || e.touches.length !== 2 || !pinchStartDist) return;

    const m = midpoint(e.touches);
    zoomAt(pinchStartZoom * (distance(e.touches) / pinchStartDist), m.x, m.y, pinchAnchor);

    e.preventDefault();
  }, { passive: false });

  stage.addEventListener('touchend', (e) => {
    if (pinching && e.touches.length < 2) {
      pinching = false;
      pinchStartDist = 0;
      pinchAnchor = null;
      scheduleRender();
      syncUi();
      return;
    }

    if (!tracking) return;
    tracking = false;

    const dx = e.changedTouches[0].clientX - tx;
    const dy = e.changedTouches[0].clientY - ty;
    if (!(Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.5)) return;
    if (isZoomedWide()) {
      if (Math.abs(stage.scrollLeft - sx) > 1) return;
      if (dx < 0 && !atRight) return;
      if (dx > 0 && !atLeft)  return;
    }
    goTo(pageNum + (dx < 0 ? 1 : -1));
  }, { passive: true });

  stage.addEventListener('touchcancel', () => {
    pinching = false;
    tracking = false;
    pinchStartDist = 0;
    pinchAnchor = null;
  }, { passive: true });

  let lastWidth = stage.clientWidth;
  let resizeTimer;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
      if (stage.clientWidth === lastWidth) return;
      lastWidth = stage.clientWidth;
      render();
    }, 150);
  });
}