(() => {
  const root = document.querySelector('[data-library]');
  if (!root) return;

  // ============ CONFIG ============
  const PARAM = {
    page:   'page',
    q:      'q',
    genre:  'genre',
    isbn:   'isbn',
    doi:    'doi',
    sort:   'sort',
    status: 'status',
    type:   'type',
    props:  'include',
  };

  // Значения совпадают с App\Models\Enums\*
  // (если бэк починит опечатки в enum — синхронизируй здесь)
  const SORT_VALUES   = { newest: 'newest', popularity: 'popularity', alpha: 'alpha' };
  // ReadingStatus: none|reading|ended. 'finished' оставлен для старых ссылок.
  const STATUS_VALUES = { reading: 'reading', finished: 'ended', ended: 'ended' };

  // Типы статей больше не маппятся — приходят из /api/additional/article-types
  // и уходят в ?type= как есть.
  const PROPS = 'creator';
  // ================================================

  const API         = root.dataset.api;
  const IS_ARTICLES = API.endsWith('/articles');
  // P1-6: страница публикации, куда ведёт клик по карточке
  const DETAIL_BASE = IS_ARTICLES ? '/articles' : '/books';
  const grid   = root.querySelector('[data-library-grid]');
  const empty  = root.querySelector('[data-library-empty]');
  const more   = root.querySelector('[data-library-more]');
  const count  = root.querySelector('[data-library-count]');
  const search = document.querySelector('input[name="q"]');

  // 'f' намеренно отсутствует: им управляет app.js
  // 'doi' обязателен: без него значение из ?doi= не восстанавливается
  // после перезагрузки, а поле стирается в syncUi()
  const KEYS = ['genre', 'status', 'isbn', 'doi', 'sort', 'kind', 'q'];
  const DEFAULTS = { sort: 'newest' }; // как на сервере по умолчанию
  const state = {};
  let page = Number(new URLSearchParams(location.search).get('page') || 1);
  let controller = null;
  let shown = grid ? grid.children.length : 0;

  // ---------- state <-> URL ----------
  const initial = new URLSearchParams(location.search);
  KEYS.forEach(k => { if (initial.get(k)) state[k] = initial.get(k); });
  // Старые/человеческие ссылки ?status=finished -> каноничное 'ended' (enum),
  // иначе tab[data-filter-value="ended"] не подсветится.
  if (state.status === 'finished') state.status = 'ended';

  function pushUrl() {
    const p = new URLSearchParams();
    for (const [k, v] of Object.entries(state)) {
      if (v && v !== 'all' && v !== DEFAULTS[k]) p.set(k, v);
    }
    const f = new URLSearchParams(location.search).get('f');
    if (f) p.set('f', f);
    const qs = p.toString();
    history.replaceState(null, '', qs ? `?${qs}` : location.pathname);
  }

  function apiQuery() {
    const p = new URLSearchParams();
    const sortKey = state.sort ?? DEFAULTS.sort;
    p.set(PARAM.props, PROPS);
    p.set(PARAM.sort, sortKey);
    p.set(PARAM.page, String(page));
    if (state.q)      p.set(PARAM.q, state.q);
    if (state.genre)  p.set(PARAM.genre, state.genre);
    if (state.status) p.set(PARAM.status, STATUS_VALUES[state.status] ?? state.status);
    if (IS_ARTICLES) {
      // Вариант B: без маппинга, значение из state.kind уходит как есть
      if (state.kind) p.set(PARAM.type, state.kind);
      // Фильтр по DOI — бэкенд читает ?doi= (ArticlesFilters::fromInput)
      if (state.doi) p.set(PARAM.doi, state.doi);
    } else if (state.isbn) {
      p.set(PARAM.isbn, state.isbn);
    }
    return p;
  }

  // ---------- UI sync ----------
  function refreshSegments() {
    if (typeof updateSegmentIndicator !== 'function') return;
    document.querySelectorAll('.tabs--segmented').forEach(t => updateSegmentIndicator(t, true));
  }

  function syncUi() {
    document.querySelectorAll('.dropdown[data-filter-key]').forEach(dd => {
      const key   = dd.dataset.filterKey;
      const cur   = state[key] ?? DEFAULTS[key] ?? 'all';
      const base  = dd.dataset.labelBase;
      const label = dd.querySelector('[data-dropdown-label]');
      let active = null;

      dd.querySelectorAll('[data-filter-value]').forEach(a => {
        const isActive = a.dataset.filterValue === String(cur);
        a.classList.toggle('is-active', isActive);
        if (isActive) active = a;
      });

      if (key === 'sort') {
        label.textContent = active ? `Sort: ${active.textContent.trim()}` : base;
      } else {
        label.textContent = active && cur !== 'all'
          ? `${base}: ${active.textContent.trim()}`
          : base;
      }
    });

    document.querySelectorAll('.tab[data-filter-key]').forEach(tab => {
      const cur = state[tab.dataset.filterKey] ?? 'all';
      tab.classList.toggle('is-active', tab.dataset.filterValue === cur);
    });

    document.querySelectorAll('input[data-filter-key]').forEach(inp => {
      if (document.activeElement !== inp) inp.value = state[inp.dataset.filterKey] ?? '';
    });
    if (search && document.activeElement !== search) search.value = state.q ?? '';

    refreshSegments();
  }

  // ---------- rendering ----------
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  function resolveCover(b) {
    if (b.cover) return b.cover;
    // Publication отдаёт iconId как объект Uuid или как строку
    const uuid = b.iconId && typeof b.iconId === 'object'
      ? (b.iconId.uuid ?? b.iconId.value ?? '')
      : (b.iconId || '');
    // Нет обложки -> '' (cardHtml нарисует CSS-заглушку .cover--empty)
    return uuid ? '/uploads/' + uuid : '';
  }

  function cardHtml(b) {
    const cover = resolveCover(b);
    const c = b.creator ?? b.author ?? null;
    const authorName = c
      ? ([c.name, c.surname].filter(Boolean).join(' ').trim() || c.username || '')
      : '';
    const authorId = Number(c?.id ?? 0);
    // «логин -> id» для @упоминаний в комментариях (users.js): карточки
    // библиотеки/подборок — один из немногих мест, где пара уже есть.
    if (authorId > 0 && c && c.username && window.LoreUsers) {
      window.LoreUsers.remember(authorId, c.username);
    }
    const author = authorName
      ? `<p class="card-book__author"><a class="card-book__author-link"
           href="${authorId > 0 ? '/users/' + authorId : '#'}">${esc(authorName)}</a></p>`
      : '';
    const id = Number(b.id);
    // P1-6: карточка ведёт на страницу книги/статьи (раньше href="#" — клик молчал)
    const href = id > 0 ? `${DETAIL_BASE}/${id}` : '#';

    // На странице статей кнопка помечается как article — app.js по этому
    // атрибуту выбирает тип и событие (data-save-url дублирует эндпоинт).
    const saveAttr = IS_ARTICLES ? 'data-save-article data-article-id' : 'data-save-book data-book-id';
    const saveKind = IS_ARTICLES ? 'article' : 'book';

    // API отдаёт состояние закладки в контексте юзера как isSaved
    // (WithContext<Publication, PublicationContext>), поле saved оставлено
    // как запасной вариант для старых ответов — иначе в библиотеке
    // сохранённая книга выглядела несохранённой.
    const isSaved = !!(b.isSaved ?? b.saved);

    return `
<article class="card-base card-book">
  <div class="card-book__cover${cover ? '' : ' cover--empty'}">${cover
    ? `<img src="${esc(cover)}" alt="${esc(b.title)}" loading="lazy">`
    : ''}</div>
  <button type="button" class="btn-icon btn-icon--circle card-book__save${isSaved ? ' is-active' : ''}"
          ${saveAttr}="${id}"
          data-save-url="${esc(API + '/' + id + '/save')}"
          aria-pressed="${isSaved ? 'true' : 'false'}"
          aria-label="${isSaved ? 'Remove from saved' : 'Save ' + saveKind}">
    <svg width="14" height="18" viewBox="0 0 14 18" fill="none" aria-hidden="true">
      <path d="M1 2C1 1.44772 1.44772 1 2 1H12C12.5523 1 13 1.44772 13 2V16.5273C13 16.928 12.5574 17.1704 12.2039 16.9631L7 13.9114L1.79612 16.9631C1.44265 17.1704 1 16.928 1 16.5273V2Z" stroke="currentColor" stroke-width="1.5"/>
    </svg>
  </button>
  <h3 class="card-book__title"><a class="card-book__link" href="${esc(href)}">${esc(b.title)}</a></h3>
  ${author}
</article>`;
  }

  // ---------- loading ----------
  // Ошибки — общими плашками (messages.js)
  const notify = (text) => {
    if (window.Messages) window.Messages.show(text, { type: 'error' });
    else console.warn(text);
  };

  async function load({ append = false } = {}) {
    console.log('load called, sort =', state.sort);
    controller?.abort();
    controller = new AbortController();
    if (!append) page = 1;

    root.classList.add('is-loading');
    let received = false; // true, когда ответ получен: дальше ошибка уже не сетевая
    try {
      const res = await fetch(`${API}?${apiQuery()}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        signal: controller.signal,
      });
      received = true;

      const raw = await res.text();
      let json = null;
      try { json = raw ? JSON.parse(raw) : null; } catch (_) { /* не JSON */ }
      if (!res.ok || !json) {
        notify(window.Messages
          ? window.Messages.describe(res.status, json, raw, 'Could not load the list.')
          : 'Could not load the list (HTTP ' + res.status + ').');
        return;
      }

      const items = json.items ?? json.data ?? [];
      const meta  = json.meta ?? {};

      const html = items.map(cardHtml).join('');
      if (append) grid.insertAdjacentHTML('beforeend', html);
      else grid.innerHTML = html;

            shown = append ? shown + items.length : items.length;
      if (count) count.textContent = shown;

      if (empty) {
        empty.hidden = shown > 0;
        empty.style.display = shown > 0 ? 'none' : '';
      }
      grid.hidden = shown === 0;
      grid.style.display = shown === 0 ? 'none' : '';

      if (more) more.hidden = !meta.hasNext;
    } catch (e) {
      if (e.name === 'AbortError') return;
      console.error('Library load failed', e);
      notify(received ? 'Could not display the list.' : 'Network error. Try again.');
    } finally {
      root.classList.remove('is-loading');
    }
  }

  function setFilter(key, value) {
    if (!value || value === 'all') delete state[key];
    else state[key] = value;

    // Закрываем открытые dropdown (в т.ч. динамические — на них нет обработчиков из app.js)
    document.querySelectorAll('[data-dropdown].is-open')
      .forEach(dd => dd.classList.remove('is-open'));

    syncUi(); pushUrl(); load();
  }

  // ---------- events ----------
  document.addEventListener('click', e => {
    const el = e.target.closest('a[data-filter-value]');
    if (el) {
      const key = el.dataset.filterKey || el.closest('[data-filter-key]')?.dataset.filterKey;
      if (!key) return;
      e.preventDefault();
      setFilter(key, el.dataset.filterValue);
      return;
    }

    if (e.target.closest('[data-filter-reset]')) {
      ['genre', 'status', 'isbn', 'doi', 'kind'].forEach(k => delete state[k]);
      syncUi(); pushUrl(); load();
      return;
    }

    if (e.target.closest('[data-load-more]')) {
      page += 1;
      load({ append: true });
    }
  });

  const debounce = (fn, ms) => {
    let t;
    return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
  };

  // filters.js шлёт cancelable-событие filter:input, чтобы без обработчика
  // применить фильтр переходом по URL (Saved, публикации профиля). Здесь
  // фильтр идёт через API (см. ввод ниже) — страницу не перезагружаем.
  document.addEventListener('filter:input', e => e.preventDefault());

  document.querySelectorAll('input[data-filter-key]').forEach(inp =>
    inp.addEventListener('input', debounce(() => {
      // filters.js помечает неверный ISBN/DOI классом is-invalid — бэкенд
      // всё равно ничего не найдёт, поэтому не пишем его в URL и не дёргаем API
      if (inp.classList.contains('is-invalid')) return;
      setFilter(inp.dataset.filterKey, inp.value.trim());
    }, 350)));

  search?.addEventListener('input', debounce(() => setFilter('q', search.value.trim()), 350));

  document.addEventListener('dropdown:populated', syncUi);

  // ---------- init ----------
  syncUi();
  load();
})();