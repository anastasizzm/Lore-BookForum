# Frontend — что сделать

- [ ] **Вернуть кнопку Unblock.**
  - После появления `DELETE /api/users/{id}/block` или `POST /unblock`.
  - Файлы: `public/assets/js/admin/features/users/list.js`, `block.js`, `public/assets/js/admin.js`.
  - Для `blocked` показывать Unblock и обновлять строку по ответу API.

- [ ] **Вернуть пункт «Пожаловаться».**
  - После появления `POST /api/reports`.
  - Файлы: `src/partials/details-menu.php`, `src/partials/comment-menu.php`, `public/assets/js/profile.js`.
  - В `public/assets/js/report-modal.js` заменить mock на реальный POST с обработкой ошибок.

- [ ] **Вывести счётчики на дашборде.**
  - После появления счётчиков и `$allowed` в `AdminPanelController`.
  - Файл: `src/views/pages/admin/dashboard.php`.
  - Убрать клиентскую фильтрацию, если сервер начнёт передавать `$allowed`.

- [ ] **Показать текст статьи в оверлее.**
  - После исправления B-04 и B-21.
  - Если бэк начнёт отдавать контент только через API — добавить lazy-fetch в `public/assets/js/article-reader.js`.
  - Файлы: `src/partials/article-reader.php`, `src/views/pages/article/article-details.php`.

- [ ] **Подключить страницу ошибки отправки письма при регистрации.**
  - После исправления B-20.
  - Бэк должен рендерить `auth/verify-result` с verify-token.
  - Файл уже готов: `src/views/pages/auth/verify-result.php`; отправка идёт на `POST /api/mail/resend`.

- [ ] **Подключить полную смену пароля.**
  - После появления роутов B-11.
  - Добавить ссылку/переход на `/auth/change-password`.
  - Файл уже готов: `src/views/pages/auth/change-password.php`.
