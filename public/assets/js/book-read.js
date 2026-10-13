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
  // 100% на компьютере = страница в половину ширины контейнера (раньше так выглядели 50%).
  // На телефоне так нельзя: текст становится нечитаемым. Там 100% = страница ровно на
  // всю ширину экрана (как в системных просмотрщиках PDF); дальше можно увеличивать
  // до 400% — страница скроллится вбок.
  const DESKTOP_FACTOR = 0.5;
  const MOBILE_FACTOR  = 1;
  const MOBILE_MAX     = 767;   // как @media (max-width: 767px) в book.css
  const baseFactor = () => (stage.clientWidth <= MOBILE_MAX ? MOBILE_FACTOR : DESKTOP_FACTOR);
  const Z_MIN = 0.5, Z_MAX = 4, Z_STEP = 0.25;   // подпись на кнопке: 50% … 400%
  const MAX_PIXELS = 12000000;   // потолок размера canvas (иначе мобильные браузеры его не рисуют; рисуем в два canvas, поэтому с запасом)

  // страница шире контейнера при zoom > 100% — CSS не должен её ужимать
  canvas.style.maxWidth = 'none';

  // Браузерный зум (Ctrl + колесо, щипок на тачпаде и пальцами) над читалкой отключаем
  // ВСЕГДА — ещё до загрузки PDF. Иначе, если файла нет или он не загрузился, скрипт
  // завершается раньше, чем вешаются обработчики зума страницы, и Ctrl + колесо
  // увеличивает весь сайт. Сам зум страницы подключается ниже, после загрузки PDF.
  const blockBrowserZoom = (e) => { if (e.ctrlKey) e.preventDefault(); };
  root.addEventListener('wheel', blockBrowserZoom, { passive: false });
  root.addEventListener('gesturestart',  (e) => e.preventDefault(), { passive: false });
  root.addEventListener('gesturechange', (e) => e.preventDefault(), { passive: false });
  root.addEventListener('touchmove', (e) => { if (e.touches.length > 1) e.preventDefault(); }, { passive: false });

  // Панель страниц закреплена к низу окна (position: fixed), а центрировать её
  // нужно по области контента, а не по окну: слева стоит сайдбар. Середину
  // области читалки кладём в CSS-переменную --reader-dock-x.
  // Дополнительно: правый край читалки → --reader-dock-right (для зума).
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
  let pendingCenter = true;          // после следующей отрисовки поставить страницу по центру

  totalEl.textContent = pdf.numPages;
  pageIn.max = pdf.numPages;

  const fromUrl = parseInt(new URLSearchParams(location.search).get('page'), 10);
  let saved = 0;
  try { saved = parseInt(localStorage.getItem(storeKey), 10) || 0; } catch (_) {}
  pageNum = clamp(fromUrl || saved || 1, 1, pdf.numPages);

  setStatus('');
  render();

  // ---------- рендер ----------
  // Страница рисуется во внеэкранный canvas и копируется на экран одним действием,
  // поэтому при перерисовке нет «белой вспышки». Если пока шла отрисовка масштаб или
  // страница поменялись (идёт жест зума), готовый результат выбрасывается и рисуется
  // актуальный. Параллельно рисовать нельзя: лишние вызовы только отменяют текущую
  // отрисовку (queued), цикл сам нарисует последнюю нужную страницу.
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
        if (stale()) continue;              // пока грузилась страница, запросили другую

        // clientWidth включает padding и рамку области — вычитаем их, иначе на телефоне
        // страница на 100% не помещалась бы по ширине и появлялась бы горизонтальная прокрутка
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
            toast(t('js.page_render_failed'));
          }
        } finally {
          renderTask = null;
        }

        if (stale()) continue;
        if (failed) break;

        // показываем готовую страницу
        canvas.width  = buf.width;
        canvas.height = buf.height;
        ctx.drawImage(buf, 0, 0);
        canvas.style.width  = Math.floor(viewport.width) + 'px';
        canvas.style.height = Math.floor(viewport.height) + 'px';
        break;
      }
    } catch (e) {
      console.error('[reader] getPage failed', e);
      toast(t('js.page_render_failed'));
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

    try { localStorage.setItem(storeKey, String(pageNum)); } catch (_) {}

    const u = new URL(location.href);
    u.searchParams.set('page', String(pageNum));
    history.replaceState(null, '', u.pathname + u.search);
  }

  function goTo(n) {
    n = clamp(Number(n) || 1, 1, pdf.numPages);
    if (n === pageNum) { syncUi(); return; }
    pageNum = n;
    pendingCenter = true;
    // на телефоне новая страница должна начинаться сверху
    if (stage.getBoundingClientRect().top < 0) stage.scrollIntoView({ block: 'start' });
    render();
  }

  // ---------- зум ----------
  // Масштаб меняется мгновенно: страница растягивается через CSS (без перерисовки),
  // а через ~140 мс после последнего изменения рисуется заново в чёткости нового
  // масштаба. Точка (cx, cy) в координатах окна остаётся на месте — страница
  // увеличивается вокруг курсора / пальцев, а не вокруг левого верхнего угла.
  // anchor — фиксированная точка на странице (для щипка: она «едет» за пальцами).
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

    // вернуть точку под курсор: по горизонтали прокручивается сама область страницы,
    // по вертикали — окно
    const n = canvas.getBoundingClientRect();
    stage.scrollLeft += (n.left + fx * n.width) - cx;
    window.scrollBy(0, (n.top + fy * n.height) - cy);

    scheduleRender();
  }

  // Кнопки +/−/100% и клавиши: масштабируем вокруг центра видимой части страницы
  function setZoom(z) {
    const r = canvas.getBoundingClientRect();
    const s = stage.getBoundingClientRect();
    const cx = clamp(s.left + s.width / 2, r.left, r.right);
    const cy = clamp(window.innerHeight / 2, r.top, r.bottom);
    zoomAt(z, cx, cy);
  }

  function clamp(n, min, max) { return Math.min(Math.max(n, min), max); }

  const isZoomedWide = () => stage.scrollWidth > stage.clientWidth + 2;

  // Страница шире экрана: показываем её середину (текст обычно по центру, поля — по краям)
  const centerX = () => { stage.scrollLeft = Math.max(0, (stage.scrollWidth - stage.clientWidth) / 2); };

  // ---------- управление ----------
  root.addEventListener('click', (e) => {
    // После клика мышью кнопка остаётся в фокусе, и Enter нажал бы её ещё раз
    // вместо листания. Снимаем фокус (e.detail === 0 — это клавиатура, её не трогаем).
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
    // Системный зум с клавиатуры (Ctrl/Cmd + «+», «−», «0»): пока курсор над страницей
    // книги, масштабируется она сама вокруг курсора, а не весь сайт. Курсор вне страницы —
    // обычный зум браузера.
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

    // В поле номера страницы Enter = «перейти на введённую страницу»
    if (e.key === 'Enter' && e.target === pageIn) {
      e.preventDefault();
      goTo(pageIn.value);
      pageIn.blur();                                           // дальше Enter листает вперёд
      return;
    }

    if (e.target.closest?.('input, textarea, select')) return;

    // Enter — следующая страница, Shift+Enter — предыдущая.
    // Не перехватываем Enter на кнопках, ссылках и в диалогах: там он нажимает сам элемент.
    if (e.key === 'Enter') {
      if (e.repeat) return;                                    // зажатый Enter не должен пролистывать книгу
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

  // ---------- Жесты ----------
  // Системный зум браузера (Ctrl + колесо мыши, щипок на тачпаде, щипок двумя пальцами)
  // перехвачен ТОЛЬКО над страницей книги: масштабируется сама страница вокруг курсора /
  // пальцев, остальной сайт остаётся прежним. Над другими элементами зум браузера
  // работает как обычно.
  const WHEEL_SENS = 0.0025;
  const pointer = { x: 0, y: 0, inside: false };
  const trackPointer = (e) => { pointer.x = e.clientX; pointer.y = e.clientY; pointer.inside = true; };
  stage.addEventListener('mouseenter', trackPointer, { passive: true });
  stage.addEventListener('mousemove',  trackPointer, { passive: true });
  stage.addEventListener('mouseleave', () => { pointer.inside = false; }, { passive: true });

  // Ctrl + колесо мыши и щипок на тачпаде (браузеры присылают его как wheel с ctrlKey).
  // Обычная прокрутка колесом не затрагивается.
  stage.addEventListener('wheel', (e) => {
    if (!e.ctrlKey) return;
    e.preventDefault();                                       // не даём увеличиться всему сайту
    const delta = clamp(e.deltaMode === 1 ? e.deltaY * 16 : e.deltaY, -120, 120);
    zoomAt(zoom * Math.exp(-delta * WHEEL_SENS), e.clientX, e.clientY);
  }, { passive: false });

  // Safari на Mac: щипок на тачпаде приходит как gesture-события
  let gestureStartZoom = 1;
  stage.addEventListener('gesturestart', (e) => { e.preventDefault(); gestureStartZoom = zoom; }, { passive: false });
  stage.addEventListener('gesturechange', (e) => {
    e.preventDefault();
    if (pinching) return;                                     // на iOS то же самое уже делают touch-события
    zoomAt(gestureStartZoom * e.scale, e.clientX ?? pointer.x, e.clientY ?? pointer.y);
  }, { passive: false });
  stage.addEventListener('gestureend', (e) => e.preventDefault(), { passive: false });

  // Телефон: свайп для листания + щипок двумя пальцами для зума
  let tx = 0, ty = 0, sx = 0, tracking = false, atLeft = false, atRight = false;
  let pinchStartDist = 0;
  let pinchStartZoom = 1;
  let pinchAnchor = null;           // точка страницы, которая была между пальцами в начале щипка
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
    // Два пальца — начинаем щипок, свайп отменяем
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

    // Один палец — свайп
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

    // Масштаб = стартовый × (расстояние между пальцами / стартовое расстояние).
    // Точка страницы, которая была между пальцами, остаётся между ними.
    const m = midpoint(e.touches);
    zoomAt(pinchStartZoom * (distance(e.touches) / pinchStartDist), m.x, m.y, pinchAnchor);

    // preventDefault, чтобы браузер не увеличил весь сайт
    e.preventDefault();
  }, { passive: false });

  stage.addEventListener('touchend', (e) => {
    // Щипок закончился, когда осталось меньше двух пальцев
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

  // Отмена touch (например, системный жест) — сбрасываем щипок
  stage.addEventListener('touchcancel', () => {
    pinching = false;
    tracking = false;
    pinchStartDist = 0;
    pinchAnchor = null;
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