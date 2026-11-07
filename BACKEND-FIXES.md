# Backend — что нужно исправить сейчас

**Проверено:** ветка `fix/frontend-audit` после мержа `fix/backend-fixes-7`, 07.11.2026 (код + браузер).
**Ниже только актуальные проблемы.** Уже исправленные B-01, B-02, B-03, B-05, B-06, B-07, B-08, B-12 и B-19 в список не входят.

---

## Сводка

| ID | Приоритет | Что исправить |
|---|---|---|
| B-20 | P0 | Регистрация падает на `$userId` / `$form`, письмо не отправляется |
| B-21 | P0 | `VersionedContentData extends final ContentData` → fatal в API контента статьи |
| B-04 | P1 | Текст статьи не попадает в SSR (`contentData` не запрашивается) |
| B-09 | P1 | Нет разблокировки, блокировка не отзывает сессии, `/api/users` не отдаёт `is_blocked` |
| B-10 | P1 | Нет API жалоб и раздела `/admin/reports` |
| B-11 | P2 | Нет роутов смены пароля |
| B-14 | P2 | Несуществующая публикация отдаёт 403 вместо 404 |
| B-15 | P2 | Нет автотестов |
| B-16 | P2 | Нет seed-данных, обещанных README |
| B-17 | P2 | Дашборд не получает `$allowed` и счётчики разделов |

---

## P0 — блокеры

### B-20. Регистрация падает на неопределённых переменных

**Где:** `app/Controllers/Auth/AuthController.php::register()`, строки ~59–62.

**Факт (воспроизведено в браузере 07.11.2026):** пользователь создаётся, затем POST `/auth/register` отдаёт `200 text/html`:

```text
Warning: Undefined variable $userId in .../AuthController.php on line 60
Warning: Undefined variable $form in .../AuthController.php on line 60
Fatal error: Uncaught TypeError:
EmailVerificationService::startVerification(): Argument #1 ($userId)
must be of type int, null given
```

**Причина:** `AuthService::register()` теперь возвращает `[token, userId]`, но контроллер по-прежнему делает `$token = ...` и обращается к `$userId` / `$form`, которых нет. `MailException` уже ловится, но до отправки письма выполнение не доходит.

**Что изменить:**

```php
$form = RegisterForm::fromInput($formData);
[$token, $userId] = $this->service->register($form);
$this->mailVerify->startVerification($userId, $form->email);
```

При `MailException` рендерить `auth/verify-result` (`success => false`, verify-token), а не общий `message`, чтобы работала кнопка resend через `POST /api/mail/resend`.

---

### B-21. Контент статьи падает на наследовании от final-класса

**Где:** `app/Models/Publications/VersionedContentData.php:7`, `app/Models/Publications/ContentData.php:9`.

**Факт:** `GET /api/articles/38/content` → HTTP 200 с HTML:

```text
Class App\Models\Publications\VersionedContentData cannot extend final class
App\Models\Publications\ContentData
```

**Причина:** `ContentData` объявлен `final readonly`. Кроме того, дочерний конструктор не вызывает `parent::__construct()`, поэтому просто снять `final` недостаточно — `parent::toArray()` обратится к неинициализированным private-свойствам.

**Что изменить:** убрать наследование (`VersionedContentData` хранит `ContentData` + `version`) либо сделать `ContentData` не-final и вызывать родительский конструктор. Добавить тесты `GET /api/articles/{id}/content` и `PUT` с `If-Match`.

---

## P1 — важное

### B-04. Текст статьи не попадает в SSR

**Где:** `app/Controllers/Publications/ArticlesController.php::retrieve()` (~207), `src/views/pages/article/article-details.php`.

**Факт:** контроллер запрашивает `PropertiesQuery::fromRaw("creator+genre+type")`. В модели `Article` нет `contentData`, поэтому `$article->contentData` на странице всегда `null`; вкладка Content и оверлей чтения показывают «The article text is not available yet».

**Что изменить:** запрашивать контент в `retrieve` (через `ArticlesService::getContent()`) и передавать его во view, либо добавить `contentData` в read-модель. После исправления B-21 проверить, что текст виден и на странице, и в оверлее.

---

### B-09. Блокировка: нет разблокировки и статуса в общем API

**Где:** `config/endpoints.php` (только `POST /api/users/{userId}/block`), `app/Repositories/Users/UsersRepository.php:129`, `app/Extensions/ScriptBuilders/Users/UsersScriptDirector.php`.

**Факт:** кнопка Block работает: `POST /api/users/2/block` → 200, `users.is_blocked = true`; админ-список после F5 показывает `Blocked` (проверено в браузере). Осталось:

- нет `DELETE /api/users/{userId}/block` или `POST /unblock` — снять блокировку из UI нельзя;
- блокировка не отзывает активные сессии пользователя;
- `GET /api/users` не отдаёт `is_blocked` (проверено: в JSON только `id`, `username`, `name`, `surname`, `avatar`).

**Что изменить:** добавить unblock-роут с проверкой прав; включить `is_blocked` в общий list-DTO; инвалидировать сессии/кеш контекста при блокировке.

---

### B-10. Нет API жалоб и админского раздела

**Где:** `config/endpoints.php`, `config/routes.php`.

**Факт:** `POST /api/reports` отсутствует; `/admin/reports` не зарегистрирован, хотя view `src/views/pages/admin/reports.php` и JS-каркас есть. На фронте пункт «Пожаловаться» временно скрыт.

**Что изменить:** таблица/репозиторий жалоб, `POST /api/reports` с валидацией, админский список, решения `reject` / `delete`, роут `admin.reports`. После этого фронт вернёт пункт меню и переключит `report-modal.js` с mock на реальный запрос.

---

## P2 — по мере возможности

### B-11. Нет роутов смены пароля

**Где:** `config/routes.php`.

**Факт:** `/auth/change-password` и `/auth/change-password/submit` отсутствуют, хотя view `src/views/pages/auth/change-password.php` уже есть. В сайдбаре сейчас «Reset password» ведёт на сброс по почте.

**Что изменить:** добавить `GET/POST /auth/change-password` (`AuthPolicy::Auth`), проверять текущий пароль, обновлять хеш, инвалидировать другие сессии.

---

### B-14. Несуществующая публикация отдаёт 403 вместо 404

**Где:** политика чтения публикации.

**Факт (проверено в браузере 07.11.2026):** `GET /books/999` и `GET /articles/999` → `403 Forbidden`, а не `404 Not Found`.

**Что изменить:** сначала проверять существование публикации, затем права; для отсутствующей отдавать 404.

---

### B-15. Нет автотестов

**Факт:** в репозитории нет каталога `tests/` и `phpunit.xml`, хотя `phpunit/phpunit` есть в `require-dev`.

**Что изменить:** минимальный набор интеграционных тестов на регистрацию, вход, контент статьи, блокировку, жалобы и права. Хотя бы smoke-тест, который ловит B-20/B-21.

---

### B-16. Seed-данные не воспроизводятся

**Факт:** README обещает `SEED_TEST_DATA` и `storage/db/test_data.sql`, но файла нет, а `migrate.sh` переменную не читает.

**Что изменить:** вернуть `test_data.sql` (или скрипт) и подключить его по `SEED_TEST_DATA`, либо убрать упоминания из README.

---

### B-17. Дашборд не получает карту прав и счётчики

**Где:** `app/Controllers/Additional/AdminPanelController.php::dashboard()` (~49–57), `src/views/pages/admin/dashboard.php`.

**Факт:** контроллер передаёт только `user`; `$allowed` и счётчики разделов отсутствуют. Сейчас фронт скрывает карточки на клиенте по `GET /api/system/rules` (проверено: при одном `manageAdditionals` видны только справочники), а цифры не показывает.

**Что изменить:** передавать `$allowed` (`route => bool`) и реальные `COUNT(*)` по разделам в SSR. После этого клиентскую фильтрацию можно оставить как fallback, а счётчики вывести из контроллера.

---

## Быстрая проверка после исправлений

1. `POST /auth/register` с новым email → письмо/страница resend, без warning/fatal.
2. `GET /api/articles/{id}/content` → JSON с `content` и `version`, без fatal.
3. `/articles/{id}` → текст виден на странице и в оверлее.
4. Block → Unblock → в списке снова `Active`, `/api/users` содержит `is_blocked`.
5. `POST /api/reports` → 201, жалоба видна в `/admin/reports`.
6. `/books/999` → 404.
