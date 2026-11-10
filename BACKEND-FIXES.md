# Backend — что сделать

> Актуально на 10.11.2026. Сверено с `origin/develop` (`de31a36`): `app/` и
> `config/` в текущей ветке совпадают с develop. Ниже — только то, что ещё
> реально требует правок на бэке.

## P0

- [ ] **B-20. Исправить регистрацию.**
  - `app/Controllers/Auth/AuthController.php::register()`
  - Сейчас метод вызывается с ошибкой: `$token = $this->service->register(...)`,
    а ниже используются неопределённые `$userId` и `$form`.
  - `AuthService::register()` уже возвращает `[$token, $userId]` — разобрать пару.
  - Передавать в `startVerification($userId, $email)` e-mail из `RegisterForm`
    (`$form = RegisterForm::fromInput($formData)` или `$formData['email']`).
  - При `MailException` рендерить `auth/verify-result` для повторной отправки,
    но учесть: `startVerification()` сейчас не возвращает verify-token, а view
    `auth/verify-result.php` берёт token из пути `/auth/verify/{token}`.
    Нужно либо вернуть/передать token и научить view принимать `$verifyToken`,
    либо использовать другой экран с рабочей повторной отправкой.

- [ ] **B-21. Убрать fatal в API контента статьи.**
  - `app/Models/Publications/VersionedContentData.php`
  - `VersionedContentData` не должен наследовать `final ContentData`.
  - Использовать композицию (`ContentData` + `version`) или снять `final` и вызывать `parent::__construct()`.
  - Проверить `GET /api/articles/{id}/content` и `PUT` с `If-Match`.
  - Важно: сейчас этот fatal блокирует не только редактирование статьи, но и
    показ текста standalone-статьи на detail view (фронт подгружает его через
    `GET /api/articles/{id}/content`).

## P1

- [ ] **B-09. Добавить разблокировку и статус блокировки.**
  - `config/endpoints.php`, `app/Controllers/Api/Users/UsersController.php`,
    `app/Services/Users/UsersService.php`, `app/Repositories/Users/UsersRepository.php`
  - Сейчас есть только `POST /api/users/{userId}/block`; добавить
    `DELETE /api/users/{userId}/block` или `POST /unblock`.
  - Добавить `isBlocked` в DTO `GET /api/users`
    (`app/Models/Users/UserShortData.php`, `UsersScriptDirector`).
    Важно: `UserShortData` используется и как creator в книгах/статьях/
    постах, где `is_blocked` не выбирается. Делать поле nullable
    (`?bool` + `boolN`) или использовать отдельный DTO для админ-списка,
    иначе сломается сериализация creator.
  - Инвалидировать сессии и кеш контекста при блокировке/разблокировке.
  - Заодно в `UsersController::block()` не импортированы `Error` и
    `ErrorCodes` — исправить латентный fatal.

- [ ] **B-10. Добавить API и раздел жалоб.**
  - `config/endpoints.php`, `config/routes.php`
  - Сейчас `src/views/pages/admin/reports.php` и `report-modal.js` работают на
    моках; API/таблицы жалоб и роута `admin.reports` нет.
  - Таблица/репозиторий жалоб + миграция.
  - `POST /api/reports` с валидацией.
  - `GET /api/admin/reports` и `PATCH /api/admin/reports/{id}`.
  - Роут `admin.reports` и policy/право на управление жалобами.
  - После появления API вернуть на фронте: карточку жалоб в
    `src/views/pages/admin/dashboard.php`, пункт «Пожаловаться» в
    `src/partials/details-menu.php`, `src/partials/comment-menu.php`,
    `public/assets/js/profile.js`.

- [ ] **B-04. Отдавать `contentData` статьи в read-модели.**
  - `app/Models/Publications/Article.php`, `ArticlesRepository.php`,
    `ArticlesScriptDirector` / `ArticlesController::retrieve()`
  - Сейчас `Article` не содержит `contentData`, поэтому на detail view:
    - текст standalone-статьи приходится тянуть отдельно через
      `GET /api/articles/{id}/content` (после B-21);
    - для отрывка `$bookData?->pageStart` всегда пустой, и ссылка
      «Открыть книгу» ведёт в читалку без `?page=`.
  - Нужно отдавать `contentData` (или хотя бы `bookData.pageStart/pageEnd`),
    чтобы ссылка отрывка открывала нужную страницу.
  - SSR-рендер текста статьи после этого станет опциональным улучшением
    (SEO / работа без JS), а не обязательным.

## P2

- [ ] **B-11. Добавить смену пароля.**
  - `config/routes.php`, `app/Controllers/Auth/AccountController.php`
  - `GET/POST /auth/change-password` (роуты `password.change` /
    `password.change.submit`); view `src/views/pages/auth/change-password.php`
    уже готова.
  - View сейчас присылает только `password` и `password_confirm` — поля
    текущего пароля нет. Либо добавить его и проверять, либо не требовать
    текущий пароль (решить на бэке/фронте явно).
  - Добавить серверную проверку `password_confirm`, обновление хеша и
    инвалидацию сессий через `TokenResetService::resetFromUser()`.
  - В `UsersRepository` уже есть `changePassword()`.
  - Ссылка в `src/partials/sidebar.php` сейчас ведёт на `password.email`;
    после появления роутов переключить на `password.change`.

- [ ] **B-14. Отдавать 404 для несуществующей публикации.**
  - `app/Policies/Access/AccessHandler.php`,
    `app/Repositories/RuleProviders/Concrete/PublicationRuleProvider.php`
  - Сейчас проверка прав идёт до загрузки публикации: для несуществующего
    id провайдер возвращает пустые правила → 403, а не 404.
  - Проверять существование публикации до проверки прав.

- [ ] **B-15. Добавить автотесты.**
  - `tests/`, `phpunit.xml`
  - PHPUnit уже в `require-dev`, но каталога `tests/` и конфига нет.
  - Покрыть регистрацию, вход, контент статьи, блокировку, жалобы, права.

- [ ] **B-16. Вернуть seed-данные.**
  - `storage/db/test_data.sql`
  - README ссылается на `test_data.sql` и `SEED_TEST_DATA`, но файла в
    `storage/db/` нет, а в `.env.example` переменной тоже нет.
  - В локальном `.env` `SEED_TEST_DATA="true"` уже стоит, но файла seed
    всё равно нет.
  - Подключить seed по `SEED_TEST_DATA` или убрать переменную/описание из README.

- [ ] **B-17. Передавать в дашборд права и счётчики.**
  - `app/Controllers/Additional/AdminPanelController.php::dashboard()`
  - View `src/views/pages/admin/dashboard.php` уже умеет принимать
    `$allowed` (`route => bool`), но контроллер его не передаёт.
  - Передавать `$allowed` по правилам доступа к разделам.
  - Счётчики сейчас не рисуются вообще: в view нет разметки под них.
    Сначала нужно согласовать контракт (`$counts` или data-атрибуты),
    затем отдавать `COUNT(*)` из контроллера/сервиса.

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
  - После этого работает создание книги/статьи без заметок и очистка заметок
    через `PATCH /api/books/{id}` и `PATCH /api/articles/{id}`. На фронте поле
    уже необязательное.
  - Пока бэк не исправлен, фронт отправляет вместо пустой заметки невидимый
    NBSP. После фикса этот обход можно убрать из `book-form.js` и
    `article-form.js`.

- [ ] **B-A2-02. Передавать текущего пользователя в читалку.**
  - `app/Controllers/Publications/BooksController.php::read()`
    - Загрузить `$userContext = $this->usersService->loadContext($userId)`
      и передать его во view `book/book-read` как `user`.
  - Причина: `src/layouts/main.php` передаёт `$user` в sidebar, а читалка
    сейчас его не получает — вместо выбранного аватара показывается
    заглушка. Активная вкладка сайдбара уже определяется на фронте.

- [ ] **B-A2-03. (Рекомендуется) Отдавать роли в `GET /api/users`.**
  - `app/Controllers/Api/Users/UsersController.php`
  - Но одного контроллера мало: список собирается из `UserShortData`
    (`UsersService::getList()`), а его select — в
    `UsersScriptDirector::addUserShortSelectTemp()`.
  - Нужно добавить `roles` (`id`, `title`, `color`) в DTO и агрегировать роли
    в репозитории/селекте, либо обогащать элементы отдельным DTO после выборки.
  - Сейчас фронт админки подгружает роли отдельными запросами
    `GET /api/system/users/{userId}/roles` (работает уже сейчас), поэтому
    пункт не блокирующий. Бэкенд-агрегация уберёт N+1 запросов.

- [ ] **B-A2-04. Согласовать право Publish с операцией API.**
  - Кнопки Publish в формах книги/статьи проверяются через
    `publication.publish`, который в `permissions.js` означает
    `updateMeta || updateContent`.
  - Эндпоинты `POST /api/books|articles/{id}/publish` требуют
    `UpdateMetaPublicationOperation` (только `updateMeta`).
  - Пользователь с одним `updateContent` увидит Publish и получит 403.
    Нужно либо сузить ключ до `updateMeta`, либо расширить policy публикации.
  - Для владельца публикации проблемы нет — owner-override даёт оба права.

### Что из текущей ветки уже не требует правок бэка

- Черновики: карточки ведут на существующие `/books/create?id=` и
  `/articles/create?id=`; publish/delete используют уже существующие API.
- Library: авторы после AJAX-фильтров возвращаются благодаря существующему
  `include=creator` в `GET /api/books|articles`.
- Локаль: фронт сохраняет выбранный язык в cookie `preferred_language`,
  которую `LocaleMiddleware` уже читает.
- Удаление и публикация из форм книги/статьи: используются существующие
  `DELETE /api/books|articles/{id}` и `POST /api/books|articles/{id}/publish`
  (но см. B-A2-04 про несовпадение права Publish).

### Проверено, правок не требует

- `GET /api/books/{id}/contents` уже возвращает `items[].id`, `format` и
  `mimeType` (`app/Models/Files/File.php`, `BooksRepository::getContents()`),
  поэтому выбор фрагмента статьи на фронте менять не нужно.
