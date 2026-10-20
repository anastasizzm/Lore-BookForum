<?php
/**
 * card-draft — карточка черновика (книга или статья в статусе draft).
 *
 * Страница — src/views/pages/drafts/drafts.php (DR-02/DR-03/DR-08).
 *
 * @param int    $id      — id публикации
 * @param string $type    — 'book' | 'article' (определяет ссылки ниже)
 * @param string $title   — название
 * @param string $cover   — адрес обложки или '' (тогда CSS-заглушка .cover--empty)
 * @param string $date    — уже отформатированная дата изменения (д.m.Y)
 *
 * ССЫЛКИ (роуты books.edit / articles.edit добавит бэк — config/ и app/
 * заморожены; роуты books.retrieve / articles.retrieve УЖЕ ЕСТЬ):
 *   Продолжить → /books/{id}/edit или /articles/{id}/edit
 *   Открыть    → /books/{id}      или /articles/{id}
 *
 * Кнопки вместо ссылки-обёртки всего карточки (в отличие от card-book):
 * у черновика два взаимоисключающих действия, поэтому клик по обложке
 * или заголовку ведёт на «Открыть», а не на редактирование.
 */
$id    = (int) ($id ?? 0);
$type  = ($type ?? 'book') === 'article' ? 'article' : 'book';
$title = (string) ($title ?? '');
$cover = (string) ($cover ?? '');
$date  = (string) ($date ?? '');

$isArticle = $type === 'article';
$basePath  = $isArticle ? '/articles/' : '/books/';

$openUrl = $id > 0 ? $basePath . $id : '#';
// TODO(backend): роутов books.edit / articles.edit в config/routes.php нет —
// «Продолжить» пока даёт 404. Когда появятся — заменить на $view->url(...).
// Роутов edit пока нет (см. шапку) — ссылка всё равно строится по шаблону,
// чтобы вёрстка и локализация были готовы к моменту, когда бэк их добавит.
$editUrl = $id > 0 ? $basePath . $id . '/edit' : '#';

$hasCover = $cover !== '';

$typeLabel = $view->t($isArticle ? 'common.drafts.type_article' : 'common.drafts.type_book');
?>
<article class="card-base card-draft<?= $hasCover ? '' : ' card-draft--no-cover' ?>"
         data-draft-type="<?= $view->e($type) ?>"
         data-draft-id="<?= $id ?>">

  <div class="card-draft__cover<?= $hasCover ? '' : ' cover--empty' ?>">
    <?php if ($hasCover): ?>
      <img src="<?= $view->e($cover) ?>"
           alt="<?= $view->e($title) ?>"
           loading="lazy">
    <?php endif; ?>
    <!-- Бейдж статуса поверх обложки: черновик виден сразу -->
    <span class="card-draft__badge"><?= $view->e($view->t('common.drafts.badge')) ?></span>
  </div>

  <h3 class="card-draft__title">
    <a class="card-draft__link" href="<?= $view->e($openUrl) ?>">
      <?= $view->e($title) ?>
    </a>
  </h3>

  <p class="card-draft__meta">
    <span class="card-draft__type"><?= $view->e($typeLabel) ?></span>
    <?php if ($date !== ''): ?>
      <span class="card-draft__sep" aria-hidden="true">·</span>
      <time class="card-draft__date"><?= $view->e($view->t('common.drafts.updated', ['value' => $date])) ?></time>
    <?php endif; ?>
  </p>

  <div class="card-draft__actions">
    <a class="btn btn--secondary card-draft__edit"
       href="<?= $view->e($editUrl) ?>"
       aria-label="<?= $view->e($view->t('common.drafts.continue') . ': ' . $title) ?>">
      <?= $view->e($view->t('common.drafts.continue')) ?>
    </a>
    <a class="card-draft__open"
       href="<?= $view->e($openUrl) ?>"
       aria-label="<?= $view->e($view->t('common.drafts.open') . ': ' . $title) ?>">
      <?= $view->e($view->t('common.drafts.open')) ?>
    </a>
  </div>

</article>