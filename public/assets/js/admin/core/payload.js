/**
 * Достаёт массив-список из ответа API, не завися от точной формы конверта.
 * Понимает: [..], {items|data|results|result|list|rows|records|content: [..]},
 * вложенность {data:{items:[..]}} и именованные ключи (roles, users — через keys).
 * Если ничего не нашлось — берёт первое поле-массив верхнего уровня и пишет
 * в консоль, какие ключи пришли (чтобы не гадать, почему страница пустая).
 *
 * @param {any} payload
 * @param {string[]} [keys] приоритетные имена ключей для этого ресурса
 * @returns {Array|null}
 */
var DEFAULT_KEYS = ['items', 'data', 'results', 'result', 'list', 'rows', 'records', 'content'];

export function listFrom(payload, keys) {
  var names = (keys || []).concat(DEFAULT_KEYS);

  function walk(node, depth) {
    if (Array.isArray(node)) return node;
    if (!node || typeof node !== 'object' || depth > 3) return null;
    for (var i = 0; i < names.length; i++) {
      var v = node[names[i]];
      if (v === undefined || v === null) continue;
      var found = walk(v, depth + 1);
      if (found) return found;
    }
    return null;
  }

  var list = walk(payload, 0);
  if (list) return list;

  if (payload && typeof payload === 'object') {
    var all = Object.keys(payload);
    for (var j = 0; j < all.length; j++) {
      if (Array.isArray(payload[all[j]])) return payload[all[j]];
    }
    console.warn('[admin] unknown list payload shape, top-level keys:', all, payload);
  } else {
    console.warn('[admin] list payload is not an object:', typeof payload);
  }
  return null;
}

/**
 * Элемент в обёртке «значение + контекст» ({ item: {...}, context: { canManage } })
 * превращает в плоский объект: поля context подмешиваются, поля item главнее.
 * Не обёрнутый элемент возвращает как есть.
 *
 * @param {any} raw
 * @returns {any}
 */
export function unwrapItem(raw) {
  if (raw && typeof raw === 'object' && !Array.isArray(raw) && raw.item && typeof raw.item === 'object') {
    return Object.assign({}, raw.context && typeof raw.context === 'object' ? raw.context : {}, raw.item);
  }
  return raw;
}
