<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in to Lore</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&display=swap">
  <link rel="stylesheet" href="/assets/css/variable.css">
  <link rel="stylesheet" href="/assets/css/base.css">
  <link rel="stylesheet" href="/assets/css/component.css">
  <link rel="stylesheet" href="/assets/css/login.css">
</head>
<body class="page page--login">

  <main class="login-card">
    <header class="login-card__header">
      <span class="login-card__logo">
        <img src="/assets/img/logo.svg" alt="Lore logo" width="56" height="56">
      </span>
      <h1 class="login-card__title">Sign in to Lore</h1>
    </header>

    <form class="login-form" id="loginForm" action="/login" method="POST" novalidate>
      <div class="form-field">
        <label class="visually-hidden" for="loginEmail">Email</label>
        <input class="form-field__input" type="email" id="loginEmail" name="email"
               placeholder="Enter email" autocomplete="username" maxlength="254"
               aria-describedby="loginEmailError">
        <p class="form-field__error" id="loginEmailError" aria-live="polite"></p>
      </div>

      <div class="form-field">
        <label class="visually-hidden" for="loginPassword">Password</label>
        <input class="form-field__input" type="password" id="loginPassword" name="password"
               placeholder="Enter password" autocomplete="current-password" maxlength="64"
               aria-describedby="loginPasswordError">
        <p class="form-field__error" id="loginPasswordError" aria-live="polite"></p>
      </div>

      <button class="button button--primary" type="submit">Sign in</button>

      <div class="login-form__links">
        <a class="link" href="/forgot-password">Forgot password?</a>
        <a class="link" href="/register">Don't have account?</a>
      </div>
    </form>
  </main>

  <script src="/assets/js/validators.js"></script>
  <script src="/assets/js/login.js"></script>
</body>
</html>
