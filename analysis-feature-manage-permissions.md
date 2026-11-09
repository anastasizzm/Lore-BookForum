# Анализ бэкенда управления правами (`feature/manage-permissions`)

> **Актуальность:** 09.11.2026. Проверено по текущей ветке `fix/frontend-audit` = `e1f538e`.
> В неё уже влит `origin/develop` = `d09d1c1`: `git merge develop` → `Already up to date`, позади develop — 0 коммитов.
> Ветка `feature/manage-permissions` влита в develop (merge-коммит `4ff8d15`, PR #83). Её текущая голова — **`6c6bf18`**.
> После прошлой версии анализа (`ae6d4b7`, 27.10.2026) в ветке появились 2 коммита: `c5fa472` «fix problems found by AI» и `6c6bf18` «remove DELETE remove user endpoints from the system». Дальнейшие правки прав пришли уже в develop (`32856fe`, `0179fa5`, `7dfb17f`, `1c27399` и др.).
> Метод проверки: чтение кода текущего HEAD; `php -l` по контроллерам, сервисам, репозиториям, `App\Lib\LexoRank`, `config/policies.php`, `config/endpoints.php` проходит без ошибок.

---

## 1. Общая картина

Стек: `config/endpoints.php` → `SystemPermissionsController` / `PublicationPermissionsController` → `SystemPermissionsService` / `PublicationPermissionsService` → `Ranks\*RoleRankService` → `PermissionsRepository` (+ наследники) → `roles_permissions` / `roles_users` / `user_roles`.

Действующие группы роутов:

| Группа | Роуты | Политика |
|---|---|---|
| `/api/system/roles` | `GET`, `POST`, `PUT /{roleId}`, `PATCH /{roleId}/position`, `DELETE /{roleId}` | `AnySystemOperation` (GET), `ManageRolesSystemOperation` (остальные) |
| `/api/system/users` | `GET`, `POST`, `GET /{userId}/roles`, `POST /{userId}/roles`, `DELETE /{userId}/roles/{roleId}` | `AnySystemOperation` (GET), `ManagePeopleSystemOperation` (остальные) |
| `/api/publications/{id}/roles` | `GET`, `POST`, `PUT /{roleId}`, `PATCH /{roleId}/position`, `DELETE /{roleId}` | `ControlRPPublicationOperation` (GET), `ManageRolesPublicationOperation` (остальные) |
| `/api/publications/{id}/users` | `GET`, `POST`, `DELETE /{userId}`, `GET/POST/DELETE /{userId}/roles...` | `ControlRPPublicationOperation` (GET), `ManagePeoplePublicationOperation` (остальные) |
| `/api/additional/roles` | `GET`, `GET /{roleId}`, `POST`, `PUT /{roleId}`, `DELETE /{roleId}` | `AuthPolicy::Auth` (GET), `ManageRolesSystemOperation` (CRUD) |

Каталог ролей (`user_roles` + переводы) теперь доступен через `/api/additional/roles` — это закрывает прежний пробел «роль можно создать только для заранее известного id».

---

## 2. Что исправлено после прошлой версии анализа (`ae6d4b7` → `6c6bf18` / develop) ✅

| # | Было на `ae6d4b7` | Статус сейчас |
|---|---|---|
| 1 | `init.sql` вставлял удалённую `authority_rank` | ✅ колонка заменена на `lexorank`, значения `'2'`/`'y'` — в одинарных кавычках |
| 2 | `new HttpException(428, '...')` в публикационном `requireVersion()` | ✅ `new HttpException('If-Match header is required', 428, ErrorCodes::INVALID_HEADER)` |
| 3 | `t("errors.roles.insufficient_role", ErrorCodes::FORBIDDEN)` | ✅ второй аргумент убран |
| 4 | `use App\Exceptions\EnumExtensions` | ✅ `App\Extensions\EnumExtensions` в обоих сервисах |
| 5 | `use App\Services\LexoRank\LexoRank` | ✅ `App\Lib\LexoRank` в обоих сервисах |
| 6 | `PermissionsRepository::rebalanceRanks()` отсутствовал | ✅ реализован (bulk `UPDATE ... FROM VALUES`); см. оговорку в 3.1.5 |
| 7 | Паттерны политик не матчили `/api/publications/{id}/...` | ✅ `PUB_PATTERN_CONCRETE = #(?:books|articles|publications)\/(\d+)#`; операции `ManageRoles`/`ManagePeople`/`AnyPublication`/`Control*` берут правила конкретной публикации |
| 8 | Неопределённая `$roleId` в системном `removeUser` | ✅ метод удалён вместе с роутом `DELETE /api/system/users/{userId}` |
| 9 | Частичный `editRole` обнулял непереданные группы | ✅ системный `editRole` требует все три группы (`systemPermissions`, `postPermissions`, `pubPermissions`), публикационный — `pubPermissions`; см. латентную оговорку в 3.2.3 |
| 10 | Публикационный `moveRole` ловил `\LogicException` | ✅ ловит `\RuntimeException` (в обеих версиях) |
| 11 | `role->toArray()` падал при `null` | ✅ `role?->toArray()` в `SystemPermissions` и `PublicationPermissions` |
| 12 | Не было API каталога ролей | ✅ добавлен `/api/additional/roles` (создание/чтение/обновление/soft-delete, защита `is_system`) |
| 13 | Мёртвый импорт `RoleToRoleUserAssignmentForm`, `inT`, `RolesScriptDirector` | ✅ импорт заменён на `RoleToUserAssignmentForm`, `inT` → `int`, `RolesScriptDirector` убран из репозиториев |
| 14 | `countUserRoles` возвращал недостижимый `NULL` | ✅ сигнатура `: int`, ветка `NULL` убрана |
| 15 | SQL-ошибки в `getUserRoles`/select/моделях | ✅ исправлены ранее и не регрессировали |

`granted_by_id` в актуальном дереве создаётся базовой миграцией `20261014185748_add_permissions.sql`, `init.sql` пишет в неё же — для **свежей** установки рассинхрона нет. Оговорка про окружения, где успела отработать удалённая `rm_granter`, — в 3.1.6.

---

## 3. Оставшиеся проблемы (проверено на `e1f538e`)

### 3.1. Функциональные и инфраструктурные

1. **`addRole` без `aboveId`/`belowId` всегда 409 при непустой области.** `RoleRankService::computeInsertRank()` вызывает `areAdjacent(null, null, ...)`, а она возвращает `false`, если в области есть хотя бы одна роль. После seed (роли 0 и 1) `POST /api/system/roles` и `POST /api/publications/{id}/roles` без позиции падают с `errors.roles.rank_far`. Нужно явное поведение по умолчанию — вставка в начало/конец.

2. **`addRole` проверяет право только по `belowId`** (оба контроллера):
   `if ($form->belowId !== NULL && !canManageRole(...))` — при заданном только `aboveId` или без позиции проверка пропускается. Пользователь с `manageRoles`, но рангом ниже, может вставить роль выше себя. Проверять нужно обе границы (или позицию вставки), а не только `belowId`.

3. **`canManageRole` считает управляемой роль с равным рангом** (`strcmp($localRank, $targetRank) >= 0`). Так как ранг собственной роли пользователя равен `localRank`, он может редактировать **свою** роль. В сочетании с `PUT /api/system/roles/{roleId}` это даёт самоповышение: обладатель `sys_manage_roles` может выставить своей роли любые права (`sys_manage_accounts`, `sys_manage_additionals` и т.д.). Для редактирования/удаления нужно строгое `>` либо явное исключение собственной роли. Для выдачи ролей равенство рангов тоже спорно, но там ограничение мягче.

4. **Системный `DELETE /api/system/roles/{roleId}` не проверяет `is_system`.** Флаг защищается только в `/api/additional/roles` (soft-delete каталога). Через `/api/system/roles` можно удалить строку `roles_permissions` системной роли: если валидных назначений нет — удаление пройдёт с `204` (в отличие от каталога), если есть — FK `roles_users → roles_permissions` даст 409 `in_use`. То есть защита сейчас держится на побочном FK, а не на явном инварианте. Плюс удаление несуществующей роли тоже возвращает `204`, а не `404`.

5. **`rebalanceRanks()` обновляет все ранги одним `UPDATE` при живом уникальном индексе** `roles_permissions_pub_lexorank_uq (publication_id, lexorank)`. Если новый ранг совпадёт со старым рангом ещё не обновлённой строки, PostgreSQL может выдать `23505`. Срабатывает редко (когда длина нового ранга превышает `MAX_RANK_LENGTH = 100`), но надёжнее обновлять в два шага (временный префикс/`DEFERRABLE`-подход) или пересоздавать индекс внутри транзакции.

6. **Откат миграций сломан:**
   - `20261024201556_add_unique_valid_roles_users.sql`: `up` создаёт `CREATE UNIQUE INDEX`, `down` делает `ALTER TABLE ... DROP CONSTRAINT` → откат всегда падает (нужен `DROP INDEX`);
   - `20261024070541_add_lexorank.sql`: блок `-- migrate:down` пуст → откат оставляет `lexorank` и не возвращает `authority_rank`;
   - исторически файл `20261022101736_rm_granter.sql` добавлялся и удалялся; на окружениях, где он успел примениться, колонки `granted_by_id` может не быть, хотя текущие `init.sql` и `addUser()` в неё пишут. Для новых БД проблемы нет, для старых нужна сверка схемы.

### 3.2. Авторизация, контракты и логика

7. **Чтение системных списков доступно любому системному праву.** `GET /api/system/roles` и `GET /api/system/users` закрыты `AnySystemOperation`, то есть `hasAny()` — достаточно любого из `manageAccounts` / `manageAdditionals` / `managePeople` / `manageRoles`. Это шире, чем «просмотр ролей/пользователей»; если задумано — стоит зафиксировать, иначе нужна отдельная read-операция.

8. **`getUserRoles` в обоих контроллерах не делает явных проверок** — только политика роута (`AnySystemOperation` / `ControlRPPublicationOperation`). После исправления паттернов это корректно, но в системном методе даже не читается `USER_ID_ATTR`; при изменении политики защита исчезнет молча. Желательно добавить явную auth-проверку, как в соседних методах.

9. **Публикационный `editRole` обнуляет `post_*`/`sys_*` колонки строки.** `PublicationPermissionsService::editRole()` передаёт в `PermissionsRepository::editRole()` только `pubPermissions`, а репозиторий пишет все 14 колонок через `?? false`. Сейчас безвредно (у публикационных ролей эти поля и так не заполняются), но при появлении постановки post/system-прав на уровне публикации частичное редактирование будет их стирать.

10. **Создание роли — двухшаговое.** Сначала каталог (`POST /api/additional/roles`), затем права (`POST /api/system/roles` с уже существующим `roleId`). Это не баг, но контракт неочевиден: без предварительного создания каталога `POST /api/system/roles` упадёт на FK. Стоит либо задокументировать, либо сделать единый эндпоинт.

11. **Мёртвый код после удаления роутов:** `SystemPermissionsService::removeUser()` больше не вызывается из контроллера (репозиторный `removeUser()` при этом используется публикационной частью). Мелочь, но лучше убрать.

12. **Устаревший ключ переводчика:** в `PermissionExceptionTranslator` остался `roles_users_user_pub_role_valid_uq`, такого constraint в актуальной схеме нет (есть `roles_users_valid_uq`). Безвредно, но вводит в заблуждение.

13. **Баг в каталоге ролей (смежный, но актуальный):** `RolesService::getList()` для фильтра статуса берёт `$query->sort->sortString()` вместо `$query->status->sortString()`. Поэтому `GET /api/additional/roles?status=...` фильтрует по значению из `sort`, а не `status`. Каталог используется при создании ролей для прав — баг стоит поправить вместе с этим модулем.

---

## 4. Что сделано хорошо

- **LexoRank + уникальный индекс** `(publication_id, lexorank COLLATE "C") NULLS NOT DISTINCT` — правильная основа порядка ролей.
- **Оптимистическая блокировка** `If-Match`/`ETag` + `version` с 428/400 — единый контракт для системных и публикационных роутов.
- **Soft-delete назначений** (`valid_to` + generated `is_valid`) и частичный уникальный индекс `WHERE is_valid` — повторная выдача роли возможна.
- **Batch-проверки прав** (`canManageRoleMany`, `canManageUserMany`, `getRanksBatch`, `getUsersHighestRanksBatch`, `DISTINCT ON` с `COLLATE "C"`) — N+1 в enrichers убран.
- **Контекст в списках** `WithContext{item, context{canManage[, version]}}` — фронт сразу видит доступность строки и версию для `If-Match`.
- **Бизнес-защиты**: нельзя выдать роль пользователю, у которого уже есть роли (`addUser` → 409), нельзя снять последнюю роль (`last_role` → 409), нельзя удалить системную роль из каталога (`is_system`), удаление роли с валидными назначениями блокируется FK и переводится в 409.
- **Каталог ролей** (`/api/additional/roles`) с переводами, цветом, `is_public` и soft-delete — закрывает прежний пробел.
- **Политики** приведены в порядок: `AnySystemOperation` зарегистрирован, `ManageRolesSystemOperation` требует `sys_manage_roles`, публикационные операции матчат `/api/publications/{id}/...`.
- **Переводчики ошибок** наследуются корректно (protected `raiseException`, общий конструктор) — починены и старые Book/Post/User/Article-переводчики.
- Локализация `ru`/`en` и API Test-запросы (`.resources/*.yaml`) для ролей и пользователей.

---

## 5. Итог

После `c5fa472` и слияния в develop ветка **перестала быть «падающей»**: исправлены `init.sql`, публикационный `requireVersion()`, импорты `EnumExtensions`/`LexoRank`, паттерны политик, частичный `editRole`, откат `moveRole`, `rebalanceRanks()` и мелочи вроде `$roleId`, `inT`, `role?->toArray()`. Основные сценарии `/api/system/...` и `/api/publications/{id}/...` работоспособны, свежая установка не ломается.

Тем не менее перед активной эксплуатацией стоит закрыть:

1. **самоповышение через редактирование собственной роли** (`canManageRole`: `>=` → `>` / исключить свою роль) — п.3.1.3;
2. **`addRole` без позиции (409) и проверку прав только по `belowId`** — п.3.1.1–3.1.2;
3. **откат миграций** (`DROP INDEX` вместо `DROP CONSTRAINT`, непустой `down` для lexorank, сверка `rm_granter` на старых БД) — п.3.1.6;
4. **защиту системных ролей от удаления правил** через `/api/system/roles` — п.3.1.4;
5. **устойчивость `rebalanceRanks()` к уникальному индексу** — п.3.1.5;
6. **латентное обнуление `post_*`/`sys_*`** при публикационном `editRole`, баг фильтра `status` в каталоге и мелкую чистку (`removeUser`, устаревший ключ переводчика) — п.3.2.9, 3.2.11–3.2.13.
