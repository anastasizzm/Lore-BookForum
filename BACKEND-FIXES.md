# Backend — что сделать

## P0

- [ ] **B-20. Исправить регистрацию.**
  - `app/Controllers/Auth/AuthController.php::register()`
  - Заменить `$token = $this->service->register(...)` на `[$token, $userId] = ...`.
  - Передавать в `startVerification($userId, $form->email)` форму `RegisterForm`.
  - При `MailException` рендерить `auth/verify-result` с verify-token для повторной отправки.

- [ ] **B-21. Убрать fatal в API контента статьи.**
  - `app/Models/Publications/VersionedContentData.php`
  - `VersionedContentData` не должен наследовать `final ContentData`.
  - Использовать композицию (`ContentData` + `version`) или снять `final` и вызывать `parent::__construct()`.
  - Проверить `GET /api/articles/{id}/content` и `PUT` с `If-Match`.

## P1

- [ ] **B-04. Отдавать текст статьи в SSR.**
  - `app/Controllers/Publications/ArticlesController.php::retrieve()`
  - Запрашивать контент через `ArticlesService::getContent()` и передавать во view.
  - Либо добавить `contentData` в read-модель `Article`.

- [ ] **B-09. Добавить разблокировку и статус блокировки.**
  - `config/endpoints.php`, `app/Repositories/Users/UsersRepository.php`
  - Добавить `DELETE /api/users/{userId}/block` или `POST /unblock`.
  - Добавить `is_blocked` в DTO `GET /api/users`.
  - Инвалидировать сессии и кеш контекста при блокировке.

- [ ] **B-10. Добавить API и раздел жалоб.**
  - `config/endpoints.php`, `config/routes.php`
  - Таблица/репозиторий жалоб.
  - `POST /api/reports` с валидацией.
  - `GET`/`PATCH /api/admin/reports` и роут `admin.reports`.

## P2

- [ ] **B-11. Добавить смену пароля.**
  - `config/routes.php`
  - `GET/POST /auth/change-password`.
  - Проверка текущего пароля, обновление хеша, инвалидация сессий.

- [ ] **B-14. Отдавать 404 для несуществующей публикации.**
  - Проверять существование публикации до проверки прав.

- [ ] **B-15. Добавить автотесты.**
  - `tests/`, `phpunit.xml`
  - Покрыть регистрацию, вход, контент статьи, блокировку, жалобы, права.

- [ ] **B-16. Вернуть seed-данные.**
  - `storage/db/test_data.sql`
  - Подключить по `SEED_TEST_DATA` или убрать переменную из README.

- [ ] **B-17. Передавать в дашборд права и счётчики.**
  - `app/Controllers/Additional/AdminPanelController.php::dashboard()`
  - Передавать `$allowed` (`route => bool`).
  - Передавать `COUNT(*)` по разделам.
