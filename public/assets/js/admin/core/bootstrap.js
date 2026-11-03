/**
 * Данные, которые сервер уже положил в страницу:
 *   <script type="application/json" data-admin-data="users">…</script>
 * Первая загрузка таблицы берёт их оттуда и не ходит в API; поиск, фильтры
 * и «Показать ещё» дальше работают через API как раньше.
 * Блок одноразовый: после чтения он удаляется, повторный вызов вернёт null.
 *
 * @param {string} name значение data-admin-data
 * @returns {any} разобранный JSON или null (нет блока / битый JSON)
 */
export function takeBootstrap(name) {
  var el = document.querySelector('script[type="application/json"][data-admin-data="' + name + '"]');
  if (!el) return null;
  var text = el.textContent;
  el.parentNode.removeChild(el);
  try {
    var data = JSON.parse(text);
    return data === undefined ? null : data;
  } catch (err) {
    console.warn('[admin] bootstrap "' + name + '" is not valid JSON:', err);
    return null;
  }
}
