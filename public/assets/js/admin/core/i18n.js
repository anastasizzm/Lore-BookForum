/**
 * Переводы: t() и tx() (с запасным текстом, если ключа нет в словаре).
 */

export var t = function (key, params) {
  return window.LoreI18n ? LoreI18n.t(key, params) : key;
};

/** Перевод с запасным текстом: если ключа нет в словаре, показываем fallback, а не сам ключ. */
export var tx = function (key, fallback, params) {
  var value = t(key, params);
  return (value && value !== key) ? value : fallback;
};
