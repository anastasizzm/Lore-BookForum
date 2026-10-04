<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $view->block('title') ?></title>
  <link rel="stylesheet" href="<?= $view->asset('css/variable.css') ?>">
  <link rel="stylesheet" href="<?= $view->asset('css/base.css') ?>">
  <link rel="stylesheet" href="<?= $view->asset('css/layout.css') ?>">
  <link rel="stylesheet" href="<?= $view->asset('css/component.css') ?>">
  <link rel="stylesheet" href="<?= $view->asset('css/modal.css') ?>">
  <?= $view->block('head_extra') ?>
</head>