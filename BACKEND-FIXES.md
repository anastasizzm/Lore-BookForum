# Backend — актуальные проблемы

**Аудит:** `origin/develop` (`d09d1c1`), повторная проверка 05.11.2026.
**Фронтенд:** ветка `fix/frontend-audit` (бэкенд в ней не менялся).
**Статус:** оставлены только проблемы, которые воспроизводятся в текущем коде. `B-13` и `B-18` закрыты на фронтенде и в списке не требуются.

---

## Сводка

| ID | Приоритет | Проблема |
|---|---|---|
| B-01 | P0 | Чистая установка не поднимается: `init.sql` пишет в generated-колонку `languages.is_active` |
| B-02 | P0 | Сохранение книги/статьи («Save book») падает с SQL-опечаткой `publicaiton_id` |
| B-03 | P0 | Восстановление пароля: `GET /auth/password-reset` и оба POST падают с `Undefined variable` |
| B-04 | P1 | Текст статьи не показывается на странице статьи (модель/контроллер не отдают `contentData`) |
| B-05 | P1 | `AuthPolicy::Admin` пускает любого верифицированного пользователя с правом `pub_create`/`post_create` в `/admin` |
| B-06 | P1 | `PATCH /api/books/{id}` с полем `pages` всегда возвращает 404 (`pagesCount` вместо `pages`) |
| B-07 | P1 | Дубликат email/username при регистрации → 500 `RuntimeException` вместо ошибки поля |
| B-08 | P1 | `PUT /api/articles/{id}/content` без `If-Match` → fatal `Class HttpException not found`; GET не отдаёт версию |
| B-09 | P1 | Блокировка есть (`POST …/block`), но нет разблокировки; список пользователей не отдаёт `is_blocked` |
| B-10 | P1 | Нет API жалоб (`POST /api/reports`) и раздела `/admin/reports`; кнопка жалобы временно скрыта на фронте |
| B-11 | P1 | Нет роутов смены пароля (`password.change` / `password.change.submit`), хотя view уже есть |
| B-12 | P1 | Ошибка SMTP при регистрации не обрабатывается: пользователь создан, ответ — fatal/500 |
| B-14 | P2 | Несуществующая публикация (`/books/999`) отдаёт 403 вместо 404 |
| B-15 | P2 | Нет автотестов: каталога `tests/` и `phpunit.xml` нет, хотя `phpunit` в `require-dev` |
| B-16 | P2 | `SEED_TEST_DATA` не используется, `test_data.sql` отсутствует, README обещает seed |
| B-17 | P2 | Дашборд админки не получает карту прав `$allowed`, поэтому показывает все разделы |
| B-19 | P1 | Статус черновика не попадает на страницу книги/статьи: нет бейджа и publish-bar |
| B-20 | P0 | Регистрация падает: `AuthController::register()` использует неопределённые `$userId`/`$form`; нет страницы повторной отправки письма |

---

## P0 — блокеры

### B-01. Чистая установка падает на `init.sql`

**Где:** `storage/db/init.sql:13-15`, миграция `storage/db/migrations/20261027200548_add_times_languages.sql`.

**Воспроизведение:**

```bash
docker compose -p <new-project> down -v
docker compose -p <new-project> up -d
```

**Факт:**

```text
[migrate] First start - seeding data and creating the application role
psql:/storage/db/init.sql:15: ERROR: cannot insert a non-DEFAULT value into column "is_active"
DETAIL: Column "is_active" is a generated column.
```

**Причина:** миграция заменяет `languages.is_active` на `GENERATED ALWAYS AS (deleted_at IS NULL) STORED`, а `init.sql` продолжает делать `INSERT INTO languages (code, is_active) ...`.

**Что сделать:** убрать `is_active` из `INSERT` и синхронизировать `init.sql` с миграциями. Добавить CI smoke-проверку «поднять стек на пустом volume».

---

### B-02. Сохранение публикации падает с SQL-опечаткой

**Где:** `app/Repositories/Publications/PublicationsRepository.php:108`.

**Воспроизведение:** открыть книгу/статью → нажать иконку закладки «Save book» / «Save article».

**Факт:** `POST /api/books/1/save` возвращает HTTP **200** и HTML с fatal-ошибкой:

```text
SQLSTATE[42703]: Undefined column: 7
ERROR: column checker.publicaiton_id does not exist
HINT: Perhaps you meant to reference the column "checker.publication_id".
```

Запись в `saved_publications` не появляется, при этом UI успевает переключить кнопку в «Remove from saved». `DELETE /api/books/{id}/save` работает (204).

**Причина:** опечатка `checker.publicaiton_id` вместо `checker.publication_id`.

**Что сделать:** исправить колонку, добавить интеграционный тест save/unsave для книги и статьи. Проверить, что API-ошибки не отдаются как `200 text/html`.

---

### B-03. Восстановление пароля полностью сломано

**Где:** `app/Controllers/Auth/AccountController.php:62-98`.

**Воспроизведение:** `GET /auth/password-reset`.

**Факт:**

```text
Warning: Undefined variable $context in .../AccountController.php on line 64
Fatal error: Uncaught Error: Call to a member function query() on null
```

Проблемы в трёх местах:

- `getPasswordMail(HttpContext $ctx)` использует `$context`;
- `passwordMail(HttpContext $context)` использует `$ctx`;
- `passwordReset(HttpContext $context)` использует `$ctx`.

Дополнительно в строке 102 массив собран как `['message' => ..., 'actionUrl' => ..., 'Login']`; похоже, вместо `'actionTitle' => 'Login'`.

**Что сделать:** привести имена параметров к одному виду, добавить `actionTitle`, покрыть тестами GET/POST reset и POST submit.

---

## P1 — важное

### B-04. Текст статьи не попадает на страницу статьи

**Где:** `app/Controllers/Publications/ArticlesController.php:199-218`, `src/views/pages/article/article-details.php:151`, модель `app/Models/Publications/Article.php`.

**Симптом:** `GET /api/articles/2/content` возвращает текст:

```json
{"data":{"bookData":null,"content":"<p>Updated audit content.</p>"}}
```

а на странице `/articles/2` вкладка **Content** пустая/пишет «The article text is not available yet».

**Причина:** контроллер запрашивает `PropertiesQuery::fromRaw("creator+genre+type")`, а view читает `$article->contentData`; в модели `Article` поля `contentData` нет — текст в SSR не загружается.

**Что сделать:** включить контент в read-модель (или отдельно запрашивать `ArticlesService::getContent()` в `retrieve` и передавать во view), добавить SSR-тест «опубликованная статья показывает текст».

---

### B-05. `/admin` доступен обычному пользователю

**Где:** `app/Policies/Access/AdminHandler.php:48-57`.

**Симптом:** пользователь без системных прав открывает `/admin` и видит дашборд со всеми карточками. Подстраницы (`/admin/users` и т.д.) при этом корректно отдают 403.

**Причина:** политика `Admin` проверяет не системные права, а «есть ли хоть одно право» в публикациях/постах/системе. У базовой роли `User` (id 0) есть `pub_create` и `post_create`, поэтому `hasAny()` истинно у всех.

**Что сделать:** для `AdminRequirement` проверять только `sysProvider` (и, желательно, явный флаг администратора). Добавить тест «роль User → `/admin` = 403».

---

### B-06. Редактирование книги с `pages` всегда 404

**Где:** `app/Repositories/Publications/BooksRepository.php:259`.

**Воспроизведение (подтверждено в браузере 05.11.2026):**

```http
PATCH /api/books/1
{"iconId":"..."}                                   → 200 {}
{"title":"Test Book Alpha"}                        → 200 {}
{"title":"Test Book Alpha","pages":120}            → 404 {"error":{"code":"not_found"}}
```

**Причина:** в `updateBook()` SQL собирается с колонкой, которой нет: `$sets[] = 'pagesCount = :pagesCount';`. В таблице `books` колонка называется `pages`.

**Что сделать:** заменить на `pages = :pages`. Это блокирует сохранение формы редактирования книги, потому что форма отправляет `pages`. Добавить тест на PATCH с `pages`.

---

### B-07. Дубликат email/username при регистрации даёт fatal

**Где:** `app/Exceptions/Translators/UserExceptionTranslator.php:21-28`, `app/Lib/I18n/Translator.php:86-94`.

**Воспроизведение:** POST `/auth/register` с уже существующим email и валидным CSRF.

**Факт:** HTTP 200 + fatal:

```text
Uncaught RuntimeException: Translation key must be 'file.key',
got: 'This email is already registered' in .../Translator.php:91
```

**Причина:** в `UNIQUE` вместо i18n-ключей лежат готовые английские фразы:

```php
'uq_users_email_lower'    => ['email',    'This email is already registered'],
'uq_users_username_lower' => ['username', 'This username is already taken'],
```

`Translator::t()` ожидает ключ вида `file.key` и падает на строке с пробелами.

**Что сделать:** завести ключи (`errors.users.email_taken`, `errors.users.username_taken` и т.п.) и использовать их. Проверить остальные ветки `UNIQUE` (`users_pkey`, `profiles_*`). Добавить тесты на дубликаты email и username.

---

### B-08. Обновление контента статьи: отсутствующий импорт и версии

**Где:** `app/Controllers/Api/Publications/ArticlesController.php:96-117`, `getContent()`.

**Симптом 1:** `PUT /api/articles/2/content` без `If-Match` → fatal:

```text
Uncaught Error: Class "App\Controllers\Api\Publications\HttpException" not found
```

**Причина:** не импортирован `App\Http\HttpException`; вместо 428/400 приходит 500/fatal.

**Симптом 2:** `GET /api/articles/2/content` не отдаёт ни `ETag`, ни `version`, хотя `article-form.js` рассчитывает синхронизировать версию из GET.

**Симптом 3:** повторный `PUT` с устаревшим `If-Match` возвращает 404, а не 409/412.

**Что сделать:** добавить импорт `HttpException`; возвращать `ETag`/`version` из `GET content`; на конфликт версий отдавать 409/412. Покрыть тестами «без If-Match», «устаревшая версия», «успешное обновление».

---

### B-09. Блокировка есть, разблокировки и статуса в списке нет

**Где:** `config/endpoints.php` (только `POST /api/users/{userId}/block`), `app/Repositories/Users/UsersRepository.php:129-135`, `app/Extensions/ScriptBuilders/Users/UsersScriptDirector.php` (`addUserShortSelectTemp`).

**Симптом:** в `/admin/users` кнопка **Block** теперь вызывает `POST /api/users/{userId}/block` и реально ставит `users.is_blocked = true`. Но:

- роута разблокировки (`/unblock` или toggle) нет — снять блокировку из UI невозможно;
- `GET /api/users` не выбирает `users.is_blocked`, поэтому после перезагрузки страницы бейдж `Blocked` не отображается и кнопка Block снова активна;
- блокировка не отзывает активные сессии пользователя.

**Что сделать:** добавить `DELETE /api/users/{userId}/block` (или `POST /unblock`) с проверкой `ManagePeopleSystemOperation`; включить `is_blocked` в read/list-модель пользователя; инвалидировать сессии/кеш контекста при блокировке.

---

### B-10. Жалобы не сохраняются

**Где:** `public/assets/js/report-modal.js:201-210`, `config/endpoints.php`, `config/routes.php`.

**Симптом:** `POST /api/reports` → 404; `/admin/reports` → 404, хотя view существует; карточка Reports скрыта с TODO.

**Текущее состояние фронта:** кнопки «Пожаловаться» временно убраны из меню публикаций и комментариев (`src/partials/details-menu.php`, `src/partials/comment-menu.php`), mock-отправка недоступна. Плашка и JS остались в проекте.

**Что сделать:** добавить таблицу/репозиторий жалоб, `POST /api/reports` (валидация kind/id/reason/comment), админский список и действия `reject`/`delete`, роут `admin.reports`. После этого вернуть пункт в меню и переключить `report-modal.js` с mock на fetch.

---

### B-11. Смена пароля: роутов нет

**Где:** `config/routes.php`, `src/views/pages/auth/change-password.php:6-7`.

**Симптом:** `/auth/change-password` и `/auth/change-password/submit` → 404. В sidebar пункт уже переименован в «Reset password» и ведёт на существующий сброс по почте, но полноценной смены пароля в профиле нет.

**Что сделать:** зарегистрировать `GET/POST /auth/change-password` (`AuthPolicy::Auth`), проверять текущий пароль, обновлять хеш, инвалидировать другие сессии.

---

### B-12. Ошибка SMTP при регистрации обрабатывается не до конца

**Где:** `app/Services/Auth/EmailVerificationService.php:65`, `app/Controllers/Auth/AuthController.php:50-75`.

**Симптом (SMTP недоступен):** POST `/auth/register` создаёт пользователя, затем `startVerification()` бросает `MailException`:

```text
Uncaught App\Exceptions\MailException: Failed to send email:
SMTP Error: Could not connect to SMTP host...
```

После ветки `fix/backend-fixes-7` исключение ловится, но пользователь направляется на общий `message` с текстом «Can't send the mail», без ссылки на повторную отправку и без token. Плюс сам `register()` сейчас не работает — см. B-20.

**Что сделать:** ловить `MailException` и рендерить отдельную страницу с кнопкой повторной отправки (`auth/verify-result`), передавая в неё verify-token; либо транзакционно откатывать создание пользователя. Добавить тест с недоступным SMTP.

---

### B-20. Регистрация падает на неопределённых переменных

**Где:** `app/Controllers/Auth/AuthController.php::register()` (ветка `fix/backend-fixes-7`).

**Симптом:** после `AuthService::register()` (теперь возвращает `[token, userId]`) контроллер делает:

```php
$token = $this->service->register(...);
$this->mailVerify->startVerification($userId, $form->email);
```

`$userId` и `$form` не определены → `Undefined variable`, письмо не отправляется, регистрация завершается fatal-ошибкой (либо письмо не уходит даже при рабочем SMTP).

**Что сделать:**

```php
$form = RegisterForm::fromInput($formData);
[$token, $userId] = $this->service->register($form);
$this->mailVerify->startVerification($userId, $form->email);
```

При `MailException` рендерить `auth/verify-result` (success=false, token для resend) вместо общего `message`, чтобы работал `POST /api/mail/resend`.

---

## P2/P3 — по мере возможности

### B-14. 403 вместо 404 для несуществующей публикации

`GET /books/999` и `/articles/999` возвращают `403 Forbidden. The user should have permissions to the publication`, а `/users/999` — корректный 404.

**Что сделать:** сначала проверять существование публикации, затем права; для отсутствующей отдавать 404.

### B-15. Нет автотестов

В репозитории нет каталога `tests/`, нет `phpunit.xml`; `phpunit/phpunit` есть только в `require-dev`. Ошибки B-01…B-08 ловятся одним-двумя интеграционными тестами.

**Что сделать:** добавить минимальный набор:

- boot на чистой БД;
- регистрация (успех/дубликат/ошибка SMTP);
- login/logout;
- save/unsave, rate/unrate;
- PATCH книги с `pages`;
- статьи: read content, update content с версиями;
- права: роль User → `/admin` 403, `/api/system/*` 403.

### B-16. Seed-данные для QA не воспроизводятся

`README.md` обещает `SEED_TEST_DATA` и `storage/db/test_data.sql`, но в `origin/develop` файла нет, а `migrate.sh` переменную не читает. На чистой БД есть только admin и справочники.

**Что сделать:** вернуть `test_data.sql` (или скрипт) и подключить его по `SEED_TEST_DATA`, либо убрать упоминания из README.

### B-17. Дашборд админки не получает карту прав и счётчики

`AdminPanelController::dashboard()` (`app/Controllers/Additional/AdminPanelController.php:49-57`) не передаёт `$allowed` и не отдаёт счётчики разделов. Роут `/admin` теперь закрыт `AnySystemOperation`, но карточки внутри страницы бэкенд не фильтрует.

**Текущее состояние фронта:** счётчики убраны (вместо выдуманных «—»), заглушка «Stub data» удалена, карточки скрываются на клиенте через `GET /api/system/rules` и `permissions.js` (`system.managePeople`, `system.manageAdditionals`, `system.manageRoles`, `publication.control`, `post.control`).

**Что сделать:** передавать с картой прав `$allowed` и реальные счётчики (`COUNT(*)` по разделам) в SSR, чтобы дашборд не зависел от клиентского запроса прав.

### B-19. Статус черновика не попадает на страницу публикации

**Где:** `app/Controllers/Publications/BooksController.php::retrieve`, `ArticlesController.php::retrieve`, модели `PublicationShort` / `Article`.

**Симптом (подтверждено в браузере 05.11.2026):** созданная через форму книга/статья имеет `status = 'draft'` в БД, но на `/books/{id}` и `/articles/{id}` нет бейджа «Draft» и publish-bar. Страница выглядит как опубликованная, опубликовать из UI нельзя.

**Причина:** read-модель/контроллер не передают `status`, а view вынужден считать `$isDraft = false` (в комментариях прямо указано, что бэкенд статус не отдаёт).

**Что сделать:** включить `status` в read-модель или передавать его из контроллера; добавить SSR-тест «черновик показывает бейдж и кнопку публикации».

---

## Что проверено и работает

- login/logout, неверный пароль;
- регистрация создаёт пользователя (но падает на SMTP — B-12);
- просмотр и сохранение профиля;
- комментарии к книге/статье, ответы, лайки, удаление комментариев через админку;
- `POST /api/books/{id}/rate` и `DELETE`, пересчёт `rating_avg`;
- `POST /api/books/{id}/publish` и `/api/articles/{id}/publish`;
- удаление из сохранённого (`DELETE .../save`) — 204;
- `PATCH /api/articles/{id}` — 200;
- `PUT /api/articles/{id}/content` с корректным `If-Match` — 200 + ETag;
- CRUD жанров, справочники `genres/categories/types/languages/roles`;
- скачивание и preview файлов через `/api/files/{id}/...`;
- создание роли публикации.

## Ограничения аудита

- SMTP в тестовом окружении недоступен, письма (verify/reset) проверены по коду и реакции приложения.
- Мобильная вёрстка проверялась точечно (viewport ~762 px), не на всех разрешениях.
