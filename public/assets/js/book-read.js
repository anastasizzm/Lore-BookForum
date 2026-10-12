import * as pdfjsLib from './pdfjs/pdf.min.js';

/* ============================================
   Читалка PDF: загрузка из API, prev / next / zoom, счётчик страниц.
   Разметка — views/pages/book/book-read.php (data-reader-*).
   ============================================ */

// файлы лежат с расширением .js: сервер отдаёт .mjs как application/octet-stream,
// а браузер такие модули не выполняет
pdfjsLib.GlobalWorkerOptions.workerSrc = '/assets/js/pdfjs/pdf.worker.min.js';

const root = document.querySelector('[data-reader]');
if (root) init(root);

// Запасные тексты: если словарь js.* ещё не подключён, пользователь увидит
// текст, а не ключ вида «js.pdf_invalid»
const FALLBACK = {
  'js.book_file_missing':     'The book file is not available yet.',
  'js.book_file_load_failed': 'Could not load the book file.',
  'js.pdf_invalid':           'The file is not a valid PDF.',
  'js.book_open_failed':      'Could not open the book. Try again.',
  'js.page_render_failed':    'Could not display the page.',
};

async function init(root) {
  const t = (key, params) => {
    const v = window.LoreI18n ? window.LoreI18n.t(key, params) : key;
    return (!v || v === key) ? (FALLBACK[key] || key) : v;
  };
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
  // 100% на экране = страница в половину ширины контейнера (раньше так выглядели 50%).
  // BASE_FACTOR — во сколько раз сжат «полный» размер (по ширине контейнера) при 100%.
  const BASE_FACTOR = 0.5;
  const Z_MIN = 0.5, Z_MAX = 4, Z_STEP = 0.25;   // подпись на кнопке: 50% … 400%
  const MAX_PIXELS = 16000000;   // потолок размера canvas (иначе мобильные браузеры его не рисуют)

  // страница шире контейнера при zoom > 100% — CSS не должен её ужимать
  canvas.style.maxWidth = 'none';

  const toast = (text) => window.Messages?.show(text, { type: 'error' });
  const setStatus = (text) => {
    if (!status) return;
    status.textContent = text || '';
    status.hidden = !text;
  };

  // ---------- адрес файла ----------
  // Основной: data-pdf-url. Для проверки без бэка: ?src=/test.pdf
  // (только путь на этом же сайте; когда бэк отдаст файл — блок можно удалить).
  let url = root.dataset.pdfUrl || '';
  const src = new URLSearchParams(location.search).get('src');
  if (src && src.startsWith('/') && !src.startsWith('//')) url = src;

  if (!url) {
    setStatus('');
    toast(t('js.book_file_missing'));
    return;
  }

  // ---------- загрузка ----------
  let pdf;
  try {
    const res = await fetch(url, {
      credentials: 'same-origin',
      // json — чтобы бэк мог вернуть ошибку в своём формате { error: {...} }
      headers: { Accept: 'application/pdf, application/json' },
    });
    if (!res.ok) {
      setStatus('');
      await window.Messages?.fail(res, t('js.book_file_load_failed'));
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
      ? t('js.pdf_invalid')
      : t('js.book_open_failed'));
    return;
  }

  // ---------- состояние ----------
  let zoom = 1;
  let pageNum = 1;
  let rendering = false;
  let queued = false;
  let renderTask = null;

  totalEl.textContent = pdf.numPages;
  pageIn.max = pdf.numPages;

  const fromUrl = parseInt(new URLSearchParams(location.search).get('page'), 10);
  let saved = 0;
  try { saved = parseInt(localStorage.getItem(storeKey), 10) || 0; } catch (_) {}
  pageNum = clamp(fromUrl || saved || 1, 1, pdf.numPages);

  setStatus('');
  render();

  // ---------- рендер ----------
  // rendering блокирует параллельные вызовы: два render() на одном canvas
  // PDF.js не допускает. Лишние вызовы только помечают queued и отменяют
  // текущую отрисовку — цикл сам нарисует актуальную страницу.
  async function render() {
    if (rendering) {
      queued = true;
      if (renderTask) renderTask.cancel();
      return;
    }
    rendering = true;

    try {
      do {
        queued = false;

        const page = await pdf.getPage(pageNum);
        if (queued) continue;               // пока грузилась страница, запросили другую

        const baseWidth = Math.max(stage.clientWidth - 16, 200);
        const base = baseWidth / page.getViewport({ scale: 1 }).width;
        const viewport = page.getViewport({ scale: base * zoom * BASE_FACTOR });

        let outScale = window.devicePixelRatio || 1;
        const area = viewport.width * viewport.height;
        if (area * outScale * outScale > MAX_PIXELS) outScale = Math.sqrt(MAX_PIXELS / area);

        canvas.width  = Math.floor(viewport.width * outScale);
        canvas.height = Math.floor(viewport.height * outScale);
        canvas.style.width  = Math.floor(viewport.width) + 'px';
        canvas.style.height = Math.floor(viewport.height) + 'px';

        renderTask = page.render({
          canvasContext: ctx,
          viewport,
          transform: outScale !== 1 ? [outScale, 0, 0, outScale, 0, 0] : null,
        });

        try {
          await renderTask.promise;
        } catch (e) {
          if (!e || e.name !== 'RenderingCancelledException') {
            console.error('[reader] render failed', e);
            toast(t('js.page_render_failed'));
          }
        } finally {
          renderTask = null;
        }
      } while (queued);
    } catch (e) {
      console.error('[reader] getPage failed', e);
      toast(t('js.page_render_failed'));
    } finally {
      rendering = false;
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
    stage.scrollLeft = 0;
    // на телефоне новая страница должна начинаться сверху
    if (stage.getBoundingClientRect().top < 0) stage.scrollIntoView({ block: 'start' });
    render();
  }

  function setZoom(z) {
    zoom = clamp(z, Z_MIN, Z_MAX);
    render();
  }

  function clamp(n, min, max) { return Math.min(Math.max(n, min), max); }

  const isZoomedWide = () => stage.scrollWidth > stage.clientWidth + 2;

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
    if (e.ctrlKey || e.metaKey || e.altKey) return;            // не мешаем браузерному зуму и т.п.
    if (e.target.closest?.('input, textarea')) return;
    if (e.key === 'ArrowLeft'  || e.key === 'PageUp')   { if (!(e.key === 'ArrowLeft'  && isZoomedWide())) goTo(pageNum - 1); }
    if (e.key === 'ArrowRight' || e.key === 'PageDown') { if (!(e.key === 'ArrowRight' && isZoomedWide())) goTo(pageNum + 1); }
    if (e.key === '+' || e.key === '=') setZoom(zoom + Z_STEP);
    if (e.key === '-') setZoom(zoom - Z_STEP);
  });

  // Свайп влево/вправо листает страницы (только когда страница не увеличена)
  let tx = 0, ty = 0, tracking = false;
  stage.addEventListener('touchstart', (e) => {
    tracking = e.touches.length === 1 && !isZoomedWide();
    if (tracking) { tx = e.touches[0].clientX; ty = e.touches[0].clientY; }
  }, { passive: true });
  stage.addEventListener('touchend', (e) => {
    if (!tracking) return;
    tracking = false;
    const dx = e.changedTouches[0].clientX - tx;
    const dy = e.changedTouches[0].clientY - ty;
    if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.5) goTo(pageNum + (dx < 0 ? 1 : -1));
  }, { passive: true });

  // Перерисовка при смене ширины. Высота на телефонах меняется при прокрутке
  // (адресная строка) — на это перерисовывать не нужно.
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