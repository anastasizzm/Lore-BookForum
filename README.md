# 📚 Lore — BookForum

> Веб-приложение для обсуждения книг: пользователи создают посты, комментируют, сохраняют книги, ведут профиль и делятся впечатлениями о литературе.

---

## 📖 О проекте

**Lore** — форум для книголюбов. Здесь можно:

- 🔐 Регистрироваться и подтверждать email.
- 📰 Читать ленту постов от других пользователей.
- 📚 Искать книги в каталоге, фильтровать по жанру, автору, серии.
- 📖 Открывать карточку книги: аннотация, оглавление, рейтинг, комментарии.
- 🔖 Сохранять книги в личный список «Saved».
- ✍️ Создавать посты с привязкой к книгам.
- 💬 Комментировать посты и книги.
- 👤 Вести профиль: имя, био, публикации.

**Цель проекта** — учебный MVP за 4 недели: полный цикл от идеи до рабочего приложения с аутентификацией, БД, шаблонизатором и адаптивным интерфейсом.

---

## 🛠 Стек технологий

| Слой | Технология |
|---|---|
| **Backend** | PHP 8.5 |
| **База данных** | PostgreSQL 18 |
| **Веб-сервер** | Nginx 1.27 |
| **Контейнеризация** | Docker + Docker Compose |
| **Пакетный менеджер** | Composer 2.x |
| **Миграции** | dbmate |
| **Frontend** | Vanilla HTML / CSS / JS |
| **Шаблонизатор** | Собственный View Engine (на бэке) |
| **Аутентификация** | JWT в HttpOnly-куке |
| **CSRF-защита** | Middleware + `$view->csrfField()` в шаблонах |
| **Почта** | SMTP (для verify email) |
| **Хранение паролей** | `password_hash()` + bcrypt |

---

## 📁 Структура проекта

```
Lore-BookForum/
│
├── app/                              # Backend (PHP)
│   ├── Controllers/                  # Контроллеры страниц
│   │   └── AuthController.php
│   ├── Middleware/                   # Прослойки
│   │   ├── AuthMiddleware.php        # Проверка JWT
│   │   └── CsrfMiddleware.php        # Проверка CSRF
│   ├── Http/                         # HTTP-слой
│   │   ├── Router.php
│   │   ├── Request.php
│   │   ├── Response.php
│   │   ├── Pipeline.php
│   │   └── Middleware.php
│   ├── Lib/                          # Утилиты
│   │   ├── View.php                  # Шаблонизатор
│   │   ├── Database.php              # PDO-обёртка
│   │   ├── CsrfManager.php           # Генерация/проверка токена
│   │   ├── Cookie.php
│   │   ├── UrlGenerator.php
│   │   ├── UnitOfWork.php            # Транзакции
│   │   └── Settings.php
│   ├── Models/                       # Модели
│   │   ├── User.php
│   │   └── Profile.php
│   ├── Repositories/                 # Работа с БД
│   │   └── UsersRepository.php
│   ├── Services/                     # Бизнес-логика
│   │   ├── AuthService.php
│   │   ├── CookieService.php
│   │   └── MailerService.php
│   ├── Constants.php                 # Константы (имена кук, методы)
│   └── Kernel.php                    # Точка входа приложения
│
├── config/                           # Конфигурация
│   ├── routes.php                    # Роуты
│   └── settings.php                  # Настройки (читает $_ENV)
│
├── public/                           # Веб-корень (доступен извне)
│   ├── index.php                     # Front controller
│   └── assets/                       # Статика
│       ├── css/
│       │   ├── variable.css          # CSS-токены (цвета, шрифты, отступы)
│       │   ├── base.css              # Reset + типографика
│       │   ├── layout.css            # Каркас, sidebar, бургер
│       │   ├── component.css         # Компоненты (кнопки, карточки, табы)
│       │   ├── login.css             # Auth-страницы
│       │   ├── book.css              # Book details
│       │   └── profile.css           # Profile
│       ├── img/
│       │   └── logo.svg
│       └── js/
│           ├── app.js                # Sidebar dropdown, общие
│           ├── filters.js            # Панель фильтров
│           ├── validators.js         # Валидаторы форм
│           ├── login.js              # Login-страница
│           ├── register.js           # Register-страница
│           ├── book.js               # Book details (закладка, вкладки)
│           └── verify-result.js      # Verify-страница
│
├── src/                              # Frontend (шаблоны)
│   ├── layouts/                      # Общие каркасы
│   │   ├── main.php                  # Основной (sidebar + content)
│   │   └── auth.php                  # Для login/register (без sidebar)
│   │
│   ├── partials/                     # Переиспользуемые компоненты
│   │   ├── head.php                  # <head> с CSS
│   │   ├── sidebar.php               # Левое меню + settings dropdown
│   │   ├── page-header.php           # Заголовок страницы + actions
│   │   ├── avatar.php                # Круглый аватар
│   │   ├── button.php                # Кнопка
│   │   ├── card-book.php             # Карточка книги
│   │   ├── card-feed.php             # Карточка поста/комментария
│   │   ├── dropdown.php              # Выпадающий список
│   │   ├── filter-panel.php          # Универсальная панель фильтров
│   │   ├── info-box.php              # Инфо-блок
│   │   ├── input.php                 # Поле ввода
│   │   ├── library-filters.php       # Фильтры для library
│   │   ├── tabs.php                  # Табы/пилюли
│   │   └── tags.php                  # Иконка + число
│   │
│   └── views/pages/                  # Страницы
│       ├── auth/
│       │   ├── login.php             # /login
│       │   └── verify-result.php     # /verify/{token}
│       ├── book/
│       │   └── book-details.php      # /book/{id}
│       ├── feed/
│       │   └── feed-list.php         # /
│       ├── library/
│       │   └── library-list.php      # /library
│       ├── profile/
│       │   └── profile.php           # /profile
│       ├── saved/
│       │   └── saved-list.php        # /saved
│       ├── message.php               # /message
│       └── register.php              # /register
│
├── storage/                          # Данные и миграции
│   └── db/
│       ├── migrations/               # SQL-миграции
│       │   └── 20260926171859_add_mail_verify.sql
│       ├── schema.sql                # Схема БД
│       ├── init.sql                  # Первичные данные (admin)
│       ├── test_data.sql             # Seed (3 книги, 3 статьи)
│       └── migrate.sh                # Скрипт миграций
│
├── docker/                           # Docker-конфиги
│   ├── php/
│   │   └── Dockerfile
│   └── nginx/
│       └── lore-bookforum.conf
│
├── .env.example                      # Шаблон переменных окружения
├── .gitignore
├── composer.json
├── composer.lock
├── docker-compose.yml
├── README.md                         # Этот файл
└── TESTING.md                        # Чек-лист проверки
```

---

## 🚀 Быстрый старт

### Требования

- **Docker Desktop** (или Docker Engine + Compose)
- **Git**
- Composer локально **не нужен** — ставим внутри контейнера.

### Установка — 5 шагов

#### 1. Клонировать проект

```bash
git clone https://github.com/anastasizzm/Lore-BookForum.git
cd Lore-BookForum
```

#### 2. Настроить `.env`

```bash
cp .env.example .env
```

Открыть `.env` и заполнить **все переменные**:

```env
# === Database ===
DBMATE_MIGRATIONS_DIR=./storage/db/migrations
DATABASE_URL="postgres://postgres:postgres@localhost:5433/lore?sslmode=disable"

POSTGRES_USER="postgres"
POSTGRES_PASSWORD="postgres"
POSTGRES_DB="lore"
POSTGRES_PORT=5433

POSTGRES_APP_USER="app"
POSTGRES_APP_PASSWORD="app_secret"

# === App ===
APP_DATABASE_URL="postgres://app:app_secret@db:5433/lore"

# === Dev only ===
SEED_TEST_DATA="true"

# === JWT ===
JWT_SECRET="change-me-in-production-please"

# === First-start admin ===
ADMIN_USERNAME="admin"
ADMIN_EMAIL="admin@example.com"
ADMIN_PASS_HASH='$2y$10$e0NRzQ2zXh4cZm5YqKp9W.6aQ8K1bL0vC7mN9pQ2rS3tU4vW5xY6z'

# === SMTP (для verify email) ===
MAIL_HOST=""
MAIL_PORT=""
MAIL_USERNAME=""
MAIL_PASSWORD=""
MAIL_ENCRYPTION="tls"
MAIL_FROM="noreply@lore.local"
MAIL_FROM_NAME="LoreBook"
```

> ⚠️ **Важно:** значения `POSTGRES_*` и `SEED_TEST_DATA` применяются **только при первом запуске** — на чистой БД. После создания volume менять их бессмысленно, нужно снести volume (`docker compose down -v`).

#### 3. Запустить Docker

```bash
docker compose up -d
```

Подождать 15–30 секунд.

Проверить статус:

```bash
docker compose ps
```

**Ожидаемо:**

| Сервис | Статус |
|---|---|
| `lore-bookforum-db-1` | `Up (healthy)` |
| `lore-bookforum-nginx-1` | `Up` |
| `lore-bookforum-php-1` | `Up` |
| `lore-bookforum-migrate-1` | `Exited (0)` |

#### 4. Установить Composer-зависимости

```bash
docker compose run --rm php composer install
```

В папке проекта появится `vendor/`.

#### 5. Открыть приложение

```
http://localhost:8080
```

Первый вход:

- **Email:** `admin@example.com`
- **Password:** тот, хеш которого задан в `ADMIN_PASS_HASH`
  (по умолчанию в примере — `admin123`, но лучше **сгенерировать свой**):
  ```bash
  php -r "echo password_hash('твой_пароль', PASSWORD_BCRYPT);"
  ```

---

## 🔄 Обновление проекта

Когда бэк запушил новую версию в `develop`:

```bash
git checkout develop
git pull origin develop
```

Если менялся `composer.json`:

```bash
docker compose run --rm php composer install
```

Затем:

```bash
docker compose up -d
```

**Сервер перезапускать не нужно** — изменения в PHP-файлах подхватываются автоматически. Обнови страницу в браузере (F5).

---

## 🗺 Роуты

| Метод | URL | Описание | Auth |
|---|---|---|---|
| `GET` | `/login` | Страница входа | — |
| `POST` | `/login` | Обработка входа | — |
| `GET` | `/register` | Страница регистрации | — |
| `POST` | `/register` | Обработка регистрации | — |
| `POST` | `/logout` | Выход | ✅ |
| `GET` | `/verify/{token}` | Подтверждение email | — |
| `GET` | `/` | Лента постов | ✅ |
| `GET` | `/library` | Каталог книг | ✅ |
| `GET` | `/book/{id}` | Карточка книги | ✅ |
| `GET` | `/saved` | Сохранённые книги | ✅ |
| `GET` | `/profile/{id}` | Профиль пользователя | ✅ |
| `POST` | `/comments` | Добавить комментарий | ✅ |
| `POST` | `/save` | Сохранить книгу | ✅ |
| `DELETE` | `/save` | Убрать из сохранённых | ✅ |

---

## 🎨 Frontend — View Engine

В шаблонах используется объект `$view`. **Не пишем** `include`, `htmlspecialchars`, `$_GET` — только методы `$view`.

### Основные методы

| Метод | Что делает |
|---|---|
| `$view->extends('main')` | Использовать layout `main.php` |
| `$view->startBlock('name')` | Начать блок |
| `$view->endBlock('name')` | Закончить блок |
| `$view->block('name')` | Вывести блок |
| `$view->setBlock('name', 'value')` | Установить значение блока |
| `$view->include('name', [...])` | Подключить partial |
| `$view->e($value)` | Экранировать HTML (защита от XSS) |
| `$view->url('route', [...])` | Сгенерировать URL по имени роута |
| `$view->csrfField()` | Скрытое поле с CSRF-токеном |

### Пример страницы

```php
<?php $view->extends('main'); ?>

<?php $view->setBlock('selectedTab', 'library'); ?>

<?php $view->startBlock('title'); ?>Library — Book App<?php $view->endBlock('title'); ?>

<?php $view->startBlock('content'); ?>

  <?php $view->include('page-header', ['title' => 'Library']); ?>

  <div class="grid-books">
    <?php foreach ($books ?? [] as $book): ?>
      <?php $view->include('card-book', [
          'cover'  => $book['cover']  ?? '',
          'title'  => $book['title']  ?? '',
          'author' => $book['author'] ?? '',
      ]); ?>
    <?php endforeach; ?>
  </div>

<?php $view->endBlock('content'); ?>
```

### Правила проекта

- ✅ **Все страницы** наследуют `main` (или `auth` для login/register).
- ✅ **Все ссылки** — через `$view->url('route.name')`, не хардкод.
- ✅ **Все данные** выводятся через `$view->e()`.
- ✅ **Все POST-формы** имеют `$view->csrfField()`.
- ✅ **Ошибки полей** — `$errors['field']` (массив сообщений).
- ✅ **Общие плашки** — `$innerMessages` (выводятся в layout).
- ✅ **Значения форм** — `$form['field']` (сохраняются при ошибке).
- ❌ **Не пишем** `include __DIR__`, `htmlspecialchars`, `$_GET`, `$_POST`.
- ❌ **Не хардкодим** ссылки (`href="/library"`) — только через `url()`.

---

## 🐳 Docker — полезные команды

### Основные

```bash
# Запустить
docker compose up -d

# Остановить
docker compose stop

# Перезапустить
docker compose restart

# Полностью снести (с данными)
docker compose down -v

# Собрать образы заново
docker compose build --no-cache
```

### Логи и диагностика

```bash
# Логи конкретного сервиса
docker compose logs php --tail 50
docker compose logs nginx --tail 30
docker compose logs db --tail 30
docker compose logs migrate

# Поток логов в реальном времени
docker compose logs -f php
```

### Внутри контейнера

```bash
# Зайти в PHP-контейнер
docker compose exec php bash

# Зайти в БД через psql
docker compose exec db psql -U postgres -d lore

# Список таблиц
docker compose exec db psql -U postgres -d lore -c "\dt"

# Проверить структуру users
docker compose exec db psql -U postgres -d lore -c "\d users"
```

### Composer

```bash
# Установить зависимости
docker compose run --rm php composer install

# Обновить зависимости
docker compose run --rm php composer update
```

---

## 🧪 Тестирование

Полный чек-лист проверки — в файле **`TESTING.md`**.

**Как пользоваться:**

1. Убедиться, что `View::$urlResolver` пофикшен у бэка.
2. Запустить контейнеры.
3. Открыть `TESTING.md` и идти по пунктам.
4. Отмечать `[x]` то, что работает.
5. Сломанное — записывать в блокер-лист.

---

## 🚩 Известные проблемы

### 1. `View::$urlResolver` отсутствует

**Симптом:**

```
Fatal error: Access to undeclared static property App\Lib\View::$urlResolver
in /var/www/html/app/Lib/View.php on line 148
```

**Причина:** в классе `View` не объявлено свойство для генератора URL.

**Fix** (делает бэк):

```php
private static ?UrlGenerator $urlResolver = null;
```

И инициализация в `configure()`.

### 2. CSS/JS не отдаются (404)

**Симптом:** страницы «голые», в Network все `.css`/`.js` = 404.

**Причина:** сервер запущен с `public/index.php` как router — все запросы идут через front controller, включая статику.

**Fix** (в `public/index.php` в самом начале):

```php
if (php_sapi_name() === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $file = __DIR__ . $path;
    if ($path !== '/' && file_exists($file) && !is_dir($file)) {
        return false;
    }
}
```

### 3. CSRF token mismatch

**Симптом:** при отправке формы — «CSRF token mismatch».

**Причина:** кука `csrf_token` не сохраняется в браузере.

**Fix** (в `CookieService` или `CsrfMiddleware`): для локальной разработки `secureByDefault` должен быть `false`, потому что сайт по HTTP.

### 4. Порт 5433 занят

**Симптом:** `Error: bind: address already in use`.

**Fix:** изменить порт в `.env`:

```env
POSTGRES_PORT=5434
```

И в `APP_DATABASE_URL` тоже заменить на `5434`.

---

## 📊 Прогресс проекта

### ✅ Сделано

- Инфраструктура: Docker, PostgreSQL, Nginx, PHP-FPM.
- Миграции + seed.
- Аутентификация: register, login, verify email, logout.
- JWT в HttpOnly-куке.
- CSRF-middleware.
- View Engine с блоками, includes, URL-хелпером.
- UI-kit: 14 partials, CSS-токены.
- 9 страниц MVP:
  - `/login`, `/register`, `/message`, `/verify/{token}`
  - `/` (feed), `/library`, `/book/{id}`
  - `/saved`, `/profile`
- Адаптив 375px / 768px / 1280px / 1920px.
- JS: sidebar dropdown, фильтры, валидация форм, book-страница, verify-страница.

### 🚧 В работе

- Подключение реальных данных в feed, library, book, saved, profile.
- Поиск и фильтры в library.
- Пагинация.
- Комментарии (POST + отображение).
- Save/unsave книги.

### 📅 В планах

- Профиль: редактирование.
- Help / Privacy (страницы).
- Password reset (2 экрана: `password.email`, `password.reset`).
- Деплой на прод.

---

## 👥 Команда

| Роль | Зона ответственности |
|---|---|
| **Тимлид + Frontend 1** | Layouts, `main.php`, `auth.php`, sidebar, страницы (`auth`, `feed`, `library`, `book`, `saved`, `profile`), интеграция с бэком |
| **Frontend 2** | UI-kit (`variable.css`, `component.css`), страницы (`register`, `book-details`), адаптив |
| **Backend** | БД, роуты, контроллеры, View Engine, JWT, CSRF, миграции, email |

---

## 🔗 Полезные ссылки

- **GitHub:** [github.com/anastasizzm/Lore-BookForum](https://github.com/anastasizzm/Lore-BookForum)
- **Чек-лист тестов:** `TESTING.md`
- **Figma-макеты:** __
- **Docker Hub:** __

---

## 📝 Лицензия

Учебный проект. Не предназначен для продакшена.

---