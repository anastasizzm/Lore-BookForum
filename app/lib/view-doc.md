# View Engine Documentation

The `View` engine is a lightweight, Django-inspired template system designed for modern PHP. It supports template inheritance, blocks, and shared data.

---

## 1. Setup & Basics

### Configuration
Before using the view engine, you must configure the paths using your `Settings` object in your `index.php`:

```php
\App\Lib\View::configure($settings);
```

### Rendering a Page
To render a page from `src/views/pages/`, call the `render` method:

```php
// In a Controller
return Response::html(View::render('home', ['name' => 'John']));
```

---

## 2. Layouts and Blocks (Inheritance)

The system uses **Template Inheritance**. You define a structure in a `layout` and fill it in your `page`.

### Layout (`src/layouts/main.php`)
Use `$view->block('name')` to define where content should be injected.

```html
<html>
<body>
    <header>My Website</header>
    <main>
        <?= $view->block('content') ?>
    </main>
</body>
</html>
```

### Page (`src/views/pages/home.php`)
Use `$view->extends('layout_name')` and the `startBlock` / `endBlock` methods.

```php
<?php $view->extends('main'); ?>

<?php $view->startBlock('content'); ?>
    <h1>Welcome, <?= $view->e($name) ?></h1>
<?php $view->endBlock('content'); ?>
```

---

## 3. Partials (Components)

Partials are reusable UI chunks (like footers or navbars).

### Rendering a Partial
Inside any template (Page or Layout), use the `include` method:

```php
<!-- Includes src/partials/footer.php -->
<?php $view->include('footer', ['year' => 2024]); ?>
```

---

## 4. Variables & Security

### Passing Data
Data passed as the second argument in `render()` is extracted into the template scope.

```php
// Controller
View::render('profile', ['user' => $user]);

// Template (profile.php)
echo $user->name; 
```

### Escaping Data (Security)
**Always** use `$view->e()` to escape variables to prevent XSS attacks:

```php
<h1>Hello, <?= $view->e($username) ?></h1>
```

---

## 5. CSRF Protection

The engine includes built-in support for CSRF tokens.

### Injecting the Token
In any form, use the `csrfField()` method:

```html
<form method="POST" action="/submit">
    <?= $view->csrfField() ?>
    <input type="text" name="data">
    <button type="submit">Submit</button>
</form>
```

---

## 6. Global Data

If you need a variable available in **every** template (like the current user or app name), use `share()` in your `index.php`:

```php
View::share('app_name', 'Lore Framework');
```

Then, access it in any template:
```php
<title><?= $view->e($app_name) ?></title>
```

---

## Summary of API

| Method | Description |
| :--- | :--- |
| `$view->extends('name')` | Set the layout for the current page. |
| `$view->include('name', [...])` | Render and echo a partial file. |
| `$view->startBlock('name')` | Start capturing content for a block. |
| `$view->endBlock('name')` | Stop capturing content and save it to the block registry. |
| `$view->block('name')` | Output the content of a defined block. |
| `$view->e($value)` | HTML-escape a string (Always use for output). |
| `$view->csrfField()` | Return hidden input field for CSRF. |

### Best Practices
1. **Never** use `require` or `include` directly for templates. Always use the `$view` object to ensure correct path resolution.
2. **Always** use `$view->e()` for any user-provided data.
3. **Keep paths clean:** Do not use `../` in file names; the engine blocks directory traversal for security.