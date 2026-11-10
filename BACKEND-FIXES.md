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

## Frontend audit 2 (`fix/frontend-audit-2`)

Ниже — правки, которые нужны на бэке. Фронт уже подготовлен: после
выполнения этих пунктов соответствующие сценарии заработают без
дополнительных изменений в `app/` и `config/`.

- [ ] **B-A2-01. Разрешить пустые заметки автора (book/article).**
  - `app/Forms/Publications/PublicationForm.php`
    - `authorNotes` разбирать как необязательное:
      `QueryParser::optionalString($input, 'authorNotes') ?? ''`.
  - `app/Validators/Publications/PublicationFormValidator.php`
    - Для `authorNotes` проверять длину `0..NOTES_MAX_LEN` вместо `1..`.
  - `app/Validators/Publications/PublicationPatchFormValidator.php`
    - Разрешить пустую строку/`null` при `hasAuthorNotes` (проверка только
      верхнего лимита `2000`).
  - После этого работает создание книги без заметок и очистка заметок
    через `PATCH /api/books/{id}`. На фронте поле уже необязательное.

- [ ] **B-A2-02. Передавать текущего пользователя в читалку.**
  - `app/Controllers/Publications/BooksController.php::read()`
    - Загрузить `$userContext = $this->usersService->loadContext($userId)`
      и передать его во view `book/book-read` как `user`.
  - Причина: `src/layouts/main.php` передаёт `$user` в sidebar, а читалка
    сейчас его не получает — вместо выбранного аватара показывается
    заглушка. Активная вкладка сайдбара уже определяется на фронте.

- [ ] **B-A2-03. (Рекомендуется) Отдавать роли в `GET /api/users`.**
  - `app/Controllers/Api/Users/UsersController.php`
    - Добавлять `roles` (`id`, `title`, `color`) в каждый элемент списка.
  - Сейчас фронт админки подгружает роли отдельными запросами
    `GET /api/system/users/{userId}/roles` (работает уже сейчас), поэтому
    пункт не блокирующий. Бэкенд-агрегация уберёт N+1 запросов.

- [ ] **B-A2-04. Проверить контракт выбора фрагмента статьи.**
  - Новый роут не нужен: фронт использует существующие
    `GET /api/books/{id}/contents` и `GET /api/files/{fileId}/download`.
  - Убедиться, что `GET /api/books/{id}/contents` для книги-источника
    возвращает `items[].id`, `format` и `mimeType` (сейчас контракт
    соответствует; проверка — после изменений в правах/фильтрах).
