<head>
  <?php /* Словарь JS-локализации + локаль: window.LORE_I18N / window.LORE_LOCALE.
           Должен идти ПЕРВЫМ — до i18n.js и до всех скриптов, которые зовут LoreI18n.t() */ ?>
  <?php $view->include('i18n'); ?>

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $view->block('title') ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= $view->asset('img/logo.svg') ?>">

  <?php /* JS-хелперы, которые нужны везде и всегда:
           - i18n.js      -> window.LoreI18n  (читает LORE_I18N выше)
           - messages.js  -> window.Messages  (плашки + describe/readError/fromPayload)
           - csrf.js      -> window.LoreCsrf  (токен из cookie, источник правды)
         Ставим до CSS-ов и до head_extra: остальные скрипты (app.js, comments.js,
         card-feed.js, login.js и т.д.) подключаются позже и уже видят их. */ ?>
  <script src="<?= $view->asset('js/i18n.js') ?>"></script>
  <script src="<?= $view->asset('js/messages.js') ?>"></script>
  <script src="<?= $view->asset('js/csrf.js') ?>"></script>

  <link rel="stylesheet" href="<?= $view->asset('css/variable.css') ?>">
  <link rel="stylesheet" href="<?= $view->asset('css/base.css') ?>">
  <link rel="stylesheet" href="<?= $view->asset('css/layout.css') ?>">
  <link rel="stylesheet" href="<?= $view->asset('css/component.css') ?>">
  <link rel="stylesheet" href="<?= $view->asset('css/modal.css') ?>">

  <?= $view->block('head_extra') ?>
</head>