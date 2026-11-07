# Frontend — что нужно изменить сейчас

**Проверено:** ветка `fix/frontend-audit` после мержа `fix/backend-fixes-7`, 07.11.2026 (код + браузер).
**Статус:** чистых фронтовых багов из прошлого аудита не осталось. Всё ниже либо ждёт бэкенд, либо включается после его роутов.

---

## Актуальные задачи

| Что | Когда делать | Где |
|---|---|---|
| Кнопка **Unblock** | после `DELETE /api/users/{id}/block` или `POST /unblock` | `public/assets/js/admin/features/users/list.js`, `block.js`, `public/assets/js/admin.js` |
| Вернуть **«Пожаловаться»** | после `POST /api/reports` | `src/partials/details-menu.php`, `src/partials/comment-menu.php`, `public/assets/js/profile.js`, `report-modal.js` |
| Счётчики на дашборде | после `$allowed` / счётчиков из `AdminPanelController` | `src/views/pages/admin/dashboard.php` |
| Текст в оверлее статьи | после B-04; если бэк отдаст только API — добавить lazy-fetch | `src/partials/article-reader.php`, `public/assets/js/article-reader.js` |
| Страница ошибки письма при регистрации | после B-20: бэк должен рендерить `auth/verify-result` с токеном | `src/views/pages/auth/verify-result.php` уже готов |
| Полная смена пароля | после B-11 | `src/views/pages/auth/change-password.php` уже готов |

Подробности по backend-блокерам — в `BACKEND-FIXES.md`.

---

## Что уже работает (не переделывать)

### F-10. Блокировка

- `POST /api/users/{userId}/block` вызывается из UI.
- При ошибке показывается сообщение, строка не меняется.
- При успехе бейдж становится `Blocked`, кнопка Block убирается.
- После F5 статус сохраняется (админ-список отдаёт состояние).
- Unblock скрыт, пока роута нет.

Файлы: `public/assets/js/admin/features/users/block.js`, `list.js`, `public/assets/js/admin.js`.

### F-11. Жалобы

- Пункт «Пожаловаться» убран из меню публикаций, комментариев, ответов и постов в профиле.
- Пустое меню у не-авторов не открывается.
- Mock-отправка недоступна из UI; плашка и JS оставлены для возврата после B-10.

Файлы: `src/partials/details-menu.php`, `src/partials/comment-menu.php`, `public/assets/js/profile.js`, `public/assets/js/comments.js`.

### F-12. Дашборд

- Заглушка «Stub data» и выдуманные счётчики удалены.
- Карточки фильтруются по `GET /api/system/rules` через `permissions.js`:
  - Users → `system.managePeople`
  - Books / Articles → `publication.control`
  - Comments → `post.control`
  - Genres / Categories / Types → `system.manageAdditionals`
  - Roles / All roles → `system.manageRoles`
- Если доступных разделов нет, показывается пустое состояние.

Файлы: `src/views/pages/admin/dashboard.php`, `public/assets/js/admin/dashboard.js`, `public/assets/js/permissions.js`.

### Чтение статьи

- Для standalone-статьи кнопка «Читать статью» открывает оверлей.
- Для отрывка из книги кнопка ведёт на `/books/{id}`.
- Крестик, Close, Esc и клик по фону закрывают; фокус переходит в диалог.
- Текст пока пуст из-за B-04/B-21 — фронт к этому готов.

Файлы: `src/views/pages/article/article-details.php`, `src/partials/article-reader.php`, `public/assets/js/article-reader.js`, `public/assets/css/modules/article-reader.css`.

### Resend письма

- `auth/verify-result` отправляет повторное письмо на публичный `POST /api/mail/resend` (`api.resendmail`).
- При ошибке показывает текст ошибки; ложного успеха нет.

Файл: `src/views/pages/auth/verify-result.php`.

---

## Проверка в браузере 07.11.2026

- Block → `POST /api/users/2/block` 200, строка `Blocked`, после F5 статус сохраняется.
- Reports: `[data-report-open]` = 0 на статье и в ленте.
- Dashboard: `/api/system/rules` 200; при полных правах все карточки, при одном `manageAdditionals` — только справочники.
- Article: оверлей открывается/закрывается, фокус корректен; текст пуст (B-04/B-21).
- Resend: `/auth/verify/invalidtoken` → `action="/api/mail/resend"`, ошибка 403 без ложного успеха.
- Регистрация: падает с `Undefined variable $userId/$form` (B-20).

Тестовые данные в БД (пароль admin, временная роль, пользователь, блокировка) откачены.
