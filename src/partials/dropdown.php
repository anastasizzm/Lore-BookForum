<?php
/**
 * dropdown - dropdown component.
 *
 * @param string      $label   - trigger text
 * @param array       $options - [['label' => '...', 'href' => '...', 'value' => '...'], ...]
 * @param string|null $dynamic - optional JS marker: 'genres' etc.
 * @param string|null $key     - filter parameter name (for fetch filtering)
 * @param string|null $param   - имя query-параметра для ссылок динамического
 *                               списка (по умолчанию берётся из SOURCES в
 *                               filters.js: genres → ?genre=, types → ?kind=);
 *                               нужен страницам с серверным рендером, где бэк
 *                               читает другое имя (черновики статей: ?type=)
 */
$label   = $label   ?? 'Sort';
$options = $options ?? [];
$dynamic = $dynamic ?? null;
$key     = $key     ?? null;
$param   = $param   ?? null;
?>
<div class="dropdown"
     data-dropdown
     data-label-base="<?= $view->e($label) ?>"
     <?= $key ? 'data-filter-key="' . $view->e($key) . '"' : '' ?>
     <?= $dynamic ? 'data-dynamic="' . $view->e($dynamic) . '"' : '' ?>
     <?= $param ? 'data-param="' . $view->e($param) . '"' : '' ?>>
  <button type="button" class="dropdown__trigger" data-dropdown-trigger>
    <span data-dropdown-label><?= $view->e($label) ?></span>
    <span class="dropdown__arrow">&#9662;</span>
  </button>

  <ul class="dropdown__menu">
    <?php foreach ($options as $opt): ?>
      <li>
        <a href="<?= $view->e($opt['href'] ?? '#') ?>"
           class="dropdown__item<?= !empty($opt['active']) ? ' is-active' : '' ?>"
           <?= isset($opt['value']) ? 'data-filter-value="' . $view->e($opt['value']) . '"' : '' ?>>
          <?= $view->e($opt['label'] ?? '') ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</div>