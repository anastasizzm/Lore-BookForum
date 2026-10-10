import * as pdfjsLib from './pdfjs/pdf.min.mjs';

/* ============================================
   Читалка PDF: загрузка из API, prev / next / zoom.
   Разметка — src/views/book/book-read.php (data-reader-*).
   ============================================ */

pdfjsLib.GlobalWorkerOptions.workerSrc = '/assets/js/pdfjs/pdf.worker.min.mjs';

const root = document.querySelector('[data-reader]');
if (root) init(root);

async function init(root) {
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
  const storeKey = 'reader:page:' + bookId;
  const Z_MIN = 0.5, Z_MAX = 3, Z_STEP = 0.25;

  const toast = (text) => window.Messages?.show(text, { type: 'error' });
  const setStatus = (text) => {
    if (!status) return;
    status.textContent = text || '';
    status.hidden = !text;
  };

  // ---------- адрес файла ----------
  // Основной: data-pdf-url. Для проверки без бэка: ?src=/test.pdf
  // (только путь на этом же сайте; на проде эту ветку можно удалить).
  let url = root.dataset.pdfUrl || '';
  const src = new URLSearchParams(location.search).get('src');
  if (src && src.startsWith('/') && !src.startsWith('//')) url = src;

  if (!url) {
    setStatus('');
    toast('The book file is not available yet.');
    return;
  }

  // ---------- загрузка ----------
  let pdf;
  try {
    const res = await fetch(url, {
      credentials: 'same-origin',
      headers: { Accept: 'application/pdf' },
    });
    if (!res.ok) {
      setStatus('');
      await window.Messages?.fail(res, 'Could not load the book file.');
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
      ? 'The file is not a valid PDF.'
      : 'Could not open the book. Try again.');
    return;
  }

  // ---------- состояние ----------
  let zoom = 1;
  let pageNum = 1;
  let renderTask = null;
  let queued = false;

  totalEl.textContent = pdf.numPages;
  pageIn.max = pdf.numPages;

  const fromUrl = parseInt(new URLSearchParams(location.search).get('page'), 10);
  let saved = 0;
  try { saved = parseInt(localStorage.getItem(storeKey), 10) || 0; } catch (_) {}
  pageNum = clamp(fromUrl || saved || 1, 1, pdf.numPages);

  setStatus('');
  render();

  // ---------- рендер ----------
  async function render() {
    // уже рисуем: отменяем текущую отрисовку, после неё нарисуем актуальную страницу
    if (renderTask) {
      queued = true;
      renderTask.cancel();
      return;
    }

    let page;
    try {
      page = await pdf.getPage(pageNum);
    } catch (e) {
      console.error('[reader] getPage failed', e);
      toast('Could not display the page.');
      return;
    }

    const baseWidth = Math.max(stage.clientWidth - 16, 200);
    const base = baseWidth / page.getViewport({ scale: 1 }).width;
    const viewport = page.getViewport({ scale: base * zoom });
    const dpr = window.devicePixelRatio || 1;

    canvas.width  = Math.floor(viewport.width * dpr);
    canvas.height = Math.floor(viewport.height * dpr);
    canvas.style.width  = Math.floor(viewport.width) + 'px';
    canvas.style.height = Math.floor(viewport.height) + 'px';

    renderTask = page.render({
      canvasContext: ctx,
      viewport,
      transform: dpr !== 1 ? [dpr, 0, 0, dpr, 0, 0] : null,
    });

    try {
      await renderTask.promise;
    } catch (e) {
      if (!e || e.name !== 'RenderingCancelledException') {
        console.error('[reader] render failed', e);
        toast('Could not display the page.');
      }
    } finally {
      renderTask = null;
    }

    if (queued) {
      queued = false;
      return render();
    }
    syncUi();
  }

  function syncUi() {
    pageIn.value = pageNum;
    zoomLbl.textContent = Math.round(zoom * 100) + '%';
    prevBtn.disabled = pageNum <= 1;
    nextBtn.disabled = pageNum >= pdf.numPages;

    try { localStorage.setItem(storeKey, String(pageNum)); } catch (_) {}

    const u = new URL(location.href);
    u.searchParams.set('page', String(pageNum));
    history.replaceState(null, '', u.pathname + u.search);
  }

  function goTo(n) {
    n = clamp(Number(n) || 1, 1, pdf.numPages);
    if (n === pageNum) { syncUi(); return; }
    pageNum = n;
    stage.scrollTop = 0;
    render();
  }

  function setZoom(z) {
    zoom = clamp(z, Z_MIN, Z_MAX);
    render();
  }

  function clamp(n, min, max) { return Math.min(Math.max(n, min), max); }

  // ---------- управление ----------
  root.addEventListener('click', (e) => {
    if (e.target.closest('[data-reader-prev]'))            goTo(pageNum - 1);
    else if (e.target.closest('[data-reader-next]'))       goTo(pageNum + 1);
    else if (e.target.closest('[data-reader-zoom-in]'))    setZoom(zoom + Z_STEP);
    else if (e.target.closest('[data-reader-zoom-out]'))   setZoom(zoom - Z_STEP);
    else if (e.target.closest('[data-reader-zoom-reset]')) setZoom(1);
  });

  pageIn.addEventListener('change', () => goTo(pageIn.value));

  document.addEventListener('keydown', (e) => {
    if (e.target.closest('input, textarea')) return;
    if (e.key === 'ArrowLeft'  || e.key === 'PageUp')   goTo(pageNum - 1);
    if (e.key === 'ArrowRight' || e.key === 'PageDown') goTo(pageNum + 1);
    if (e.key === '+' || e.key === '=') setZoom(zoom + Z_STEP);
    if (e.key === '-') setZoom(zoom - Z_STEP);
  });

  let resizeTimer;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(render, 150);
  });
}