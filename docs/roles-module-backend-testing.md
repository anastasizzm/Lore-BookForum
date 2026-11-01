# Как проверить, что модуль ролей подключён к бэкенду

Документ для ветки `feature/pub-roles-module`: модуль «назначение ролей»
(`src/partials/roles-module.php` + `public/assets/js/roles-module.js`)
встроен в publish-диалоги книги и статьи и работает поверх реальных
API-роутов. Ниже — как убедиться, что фронт ходит именно в бэк, а не
рисует демо-данные.

---

## 1. Карта «действие → запрос»

Все пути — из `config/endpoints.php` (`PublicationPermissionsController`).

| Действие в UI | Запрос |
|---|---|
| Открыть диалог (экран 1, роли) | `GET /api/publications/{id}/roles` |
| Открыть диалог (экран 2, люди) | `GET /api/publications/{id}/users?include=roles&ps=50` |
| «+» на экране 1 → модалка создания роли | `GET /api/additional/roles?ps=50` (каталог) |
| «+» на экране 2 → модалка «роль + пользователь» | `GET /api/users?ps=50&page=1` + `GET /api/additional/roles?ps=50` |
| Add в модалке создания роли | `POST /api/publications/{id}/roles` `{ roleId, pubPermissions }` |
| Add в модалке «роль + пользователь» | `POST /api/publications/{id}/users` `{ userId, roleId }` (если человек ещё не в публикации), иначе `POST .../users/{userId}/roles` `{ roleId }` |
| «×» у роли в строке пользователя | `DELETE /api/publications/{id}/users/{userId}/roles/{roleId}` |
| Permissions → Save | `PUT /api/publications/{id}/roles/{roleId}` `{ pubPermissions }` + `If-Match: "<version>"` |
| Drag & drop / вставка «+» между ролями | `POST /api/publications/{id}/roles` с `aboveId`/`belowId` (вставка) или `PATCH /api/publications/{id}/roles/{roleId}/position` `{ aboveId, belowId }` + `If-Match` |
| «×» у роли (экран 1) | `DELETE /api/publications/{id}/roles/{roleId}` |
| Publish (книга, черновик) | `POST /api/books/{id}/publish` |

Ответы ролей: `{ items: [ { role: { id, title, color }, pubPermissions: {...}, canManage, version } ], meta }`.
Ответы людей: `{ items: [ { user: {...}, roles: [...], canManage } ], meta }`.

---

## 2. Подготовка

```bash
docker compose up -d
docker compose run --rm php composer install   # если vendor/ ещё нет
```

1. Открыть `http://localhost:8080`, зарегистрироваться / войти.
2. Создать **черновик книги**: «Создать книгу» → заполнить поля → сохранить
   (не публиковать). Либо взять существующую статью-черновик.
3. Открыть страницу книги и нажать «Опубликовать книгу» (кнопка
   `data-rights-open`) — откроется плашка с модулем ролей.
   Для статьи — «Опубликовать статью» на странице черновика.

> Проверять нужно **владельцем публикации** (или ролью с правами
> `manageRoles`/`managePeople`): иначе бэк честно ответит `403`, и это не
> баг фронта.

---

## 3. Быстрая проверка в браузере (DevTools → Network)

Держите панель Network открытой и фильтруйте по `api/`.

1. **Открытие диалога.**
   Ожидаемо: `GET /api/publications/{id}/roles` → `200` и
   `GET /api/publications/{id}/users?include=roles&ps=50` → `200`.
   Список ролей заполнен, у корня модуля `data-api="live"`.
2. **«+» на экране ролей.**
   Ожидаемо: `GET /api/additional/roles?ps=50` → `200`, в выпадающем списке
   есть роли каталога. Если список пуст («Nothing found»), а запроса нет —
   значит каталог не загрузился (см. п. 5).
3. **«+» на экране людей.**
   Ожидаемо: `GET /api/users?ps=50&page=1` → `200`, список людей непустой.
4. **Выбрать роль + человека → Add.**
   Ожидаемо: `POST /api/publications/{id}/users` (или `.../users/{id}/roles`)
   → `200/201`; человек появляется в списке без перезагрузки.
5. **Permissions → отметить чекбоксы → Save → подтвердить.**
   Ожидаемо: `PUT /api/publications/{id}/roles/{roleId}` → `200`,
   в **Request Headers** есть `If-Match: "N"`, в **Response Headers** —
   новый `ETag: "N+1"`. Повторное открытие Permissions показывает
   сохранённые галочки.
6. **Перетащить роль за ручку.**
   Ожидаемо: `PATCH /api/publications/{id}/roles/{roleId}/position` → `200`
   с `If-Match`; при ошибке роль возвращается на место.
7. **Publish (экран 2).**
   Ожидаемо: `POST /api/books/{id}/publish` → `200` (для статьи —
   `POST /api/articles/{id}/publish`), затем переход на страницу книги.

Если все семь пунктов дают запросы к `/api/...` со статусами `2xx` —
модуль подключён к бэкенду.

---

## 4. Проверка через curl

Нужны cookie сессии и CSRF-токен: бэк принимает его заголовком
`X-CSRF-Token` (значение cookie `csrf_token`).

```bash
BASE=http://localhost:8080
JAR=/tmp/lore-cookies.txt

# 1. Страница логина — ставит cookie csrf_token
curl -s -c "$JAR" "$BASE/auth/login" > /dev/null
TOKEN=$(awk '$6 == "csrf_token" { print $7 }' "$JAR")

# 2. Логин (поля формы: login, password)
curl -s -b "$JAR" -c "$JAR" -X POST "$BASE/auth/login" \
  -H "X-CSRF-Token: $TOKEN" \
  --data-urlencode "login=ВАШ_ЛОГИН" \
  --data-urlencode "password=ВАШ_ПАРОЛЬ" -o /dev/null -w "login: %{http_code}\n"

# Токен мог обновиться после логина — читаем заново
TOKEN=$(awk '$6 == "csrf_token" { print $7 }' "$JAR")
API=(-b "$JAR" -H "X-CSRF-Token: $TOKEN" -H "Accept: application/json")
```

```bash
PUB=1   # id своей публикации

# Роли публикации (200 + items с version/canManage)
curl -s "${API[@]}" "$BASE/api/publications/$PUB/roles" | head -c 600; echo

# Люди публикации
curl -s "${API[@]}" "$BASE/api/publications/$PUB/users?include=roles&ps=50" | head -c 600; echo

# Каталоги, которые грузят модалки
curl -s "${API[@]}" "$BASE/api/users?ps=50&page=1" | head -c 300; echo
curl -s "${API[@]}" "$BASE/api/additional/roles?ps=50" | head -c 300; echo
```

Создание роли (в ответе `ETag` — версия новой записи):

```bash
ROLE=2   # id роли из /api/additional/roles
curl -s -D /tmp/h.txt "${API[@]}" -X POST "$BASE/api/publications/$PUB/roles" \
  -H "Content-Type: application/json" \
  -d "{\"roleId\":$ROLE,\"pubPermissions\":{\"read\":true,\"updateMeta\":false,\"updateContent\":false,\"create\":false,\"managePeople\":false,\"manageRoles\":false}}"
grep -i etag /tmp/h.txt
```

Сохранение прав (обязателен `If-Match`, иначе `428`):

```bash
VER=1   # version из GET /roles
curl -s -D /tmp/h.txt "${API[@]}" -X PUT "$BASE/api/publications/$PUB/roles/$ROLE" \
  -H "Content-Type: application/json" -H "If-Match: \"$VER\"" \
  -d '{"pubPermissions":{"read":true,"updateMeta":true,"updateContent":true,"create":false,"managePeople":false,"manageRoles":false}}'
grep -i etag /tmp/h.txt
```

Смена позиции (соседи вместо ранга):

```bash
VER2=2   # актуальная version роли из GET /roles
curl -s -D /tmp/h.txt "${API[@]}" -X PATCH "$BASE/api/publications/$PUB/roles/$ROLE/position" \
  -H "Content-Type: application/json" -H "If-Match: \"$VER2\"" \
  -d '{"aboveId":1,"belowId":3}'
```

Удаление:

```bash
curl -s -o /dev/null -w "role delete: %{http_code}\n" "${API[@]}" \
  -X DELETE "$BASE/api/publications/$PUB/roles/$ROLE"
```

---

## 5. Как читать ошибки

| Симптом | Причина | Что делать |
|---|---|---|
| Все `POST/PUT/PATCH/DELETE` → `419` | CSRF: не загрузился `csrf.js` или нет cookie `csrf_token` | Проверить Network: `/assets/js/csrf.js` должен быть `200`. В `window.LoreCsrf` должен быть объект с `token`/`fetch` |
| `403` на `GET /roles` или `/users` | У пользователя нет `ControlRPPublicationOperation` (не владелец и без прав) | Тестировать владельцем публикации |
| `403` на изменение | Нет `ManageRoles`/`ManagePeople` либо роль выше по рангу | Взять роль с нужными правами |
| `428` | Не передан `If-Match` | Фронт шлёт его сам; при curl — добавить `If-Match: "N"` |
| `409` | Конфликт версий (`version_invalid`), попытка удалить последнюю роль, человек уже в публикации | Перечитать список (`GET /roles`) и повторить |
| `404` + HTML в ответе | Роут не развёрнут (старый бэк) | Модуль уходит в демо: `data-api="mock"`, плашка «The roles API is not available yet» |
| Запросов к `/api/...` вообще нет | Открыт `public/roles-demo.php` (демо-стенд) или модуль в `mock` | Открывать реальную страницу книги/статьи |

Диагностика в консоли браузера:

```js
const root = document.querySelector('[data-roles-module]');
root.getAttribute('data-api');            // live | mock | denied | error
window.LoreRoles._state.api;              // то же из JS
window.LoreRoles._state.add.catalogUsers; // каталог людей
window.LoreRoles._state.add.catalogRoles; // каталог ролей
```

Состояния интерфейса:

- `data-api="live"` — данные с бэка;
- `data-api="mock"` — оба роута публикации ответили `404`/HTML, показаны
  встроенные демо-данные и плашка-предупреждение;
- `data-api="denied"` — `401/403`, показан текст «нет прав» и «Повторить»;
- `data-api="error"` — сеть/5xx, показан текст ошибки и «Повторить».

---

## 6. Чек-лист

- [ ] `/assets/js/csrf.js` отдаётся с `200` (не `404`), `window.LoreCsrf` определён
- [ ] Открытие диалога: `GET /roles` и `GET /users?include=roles` → `200`
- [ ] `data-api="live"`, демо-плашка не показана
- [ ] «+» (роли): `GET /api/additional/roles` → `200`, список ролей непустой
- [ ] «+» (люди): `GET /api/users` → `200`, список людей непустой
- [ ] Add (роль/человек): `POST .../roles` или `POST .../users` → `2xx`
- [ ] Permissions → Save: `PUT .../roles/{id}` c `If-Match`, ответ `ETag`
- [ ] Drag & drop: `PATCH .../position` c `If-Match`
- [ ] Удаление роли/человека: `DELETE ...` → `2xx`
- [ ] Publish: `POST /api/books/{id}/publish` (или `/api/articles/{id}/publish`) → `2xx`
- [ ] В консоли нет красных ошибок

---

## 7. Известные ограничения бэка (фронт их не обходит)

1. **`pubPermissions.delete` теряется при сохранении.**
   `GET /roles` возвращает `delete` (`PublicationAccessRules::toArray`),
   но `PublicationPermissionForm` парсит только шесть ключей
   (`ShortPublicationAccessRules`), поэтому `PUT` сбрасывает `delete`
   в `false`. Фронт отправляет ключ обратно, но бэк его игнорирует.
   Чинится на бэке: добавить `delete` в `ShortPublicationAccessRules`.
2. **Демо-стенд `public/roles-demo.php` публично доступен** (лежит в
   docroot и исполняется напрямую). Перед продом его нужно убрать или
   закрыть авторизацией — он не ходит в API и не трогает данные, но
   раскрывает внутреннюю разметку.
3. **Мок-режим включается сам** при `404`/HTML на обоих роутах
   публикации — это осознанный fallback, а не ошибка подключения.
   Если видите демо-данные на реальной странице, смотрите Network:
   скорее всего, роуты не развёрнуты на этом стенде.

---

## 8. Автономная проверка UI (без бэкенда)

```bash
php -S localhost:8000 -t public
# открыть http://localhost:8000/roles-demo.php
```

Стенд поднимает реальный partial, CSS и JS в `mock`-режиме: удобно
проверить экраны, drag & drop и вставку «+» без БД и сессии. Сетевых
запросов стенд не делает — для проверки связи с бэком используйте п. 3–4.
