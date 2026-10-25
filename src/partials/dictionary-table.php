<?php
/**
 * dictionary-table — toolbar + таблица справочника (Genres / Categories / Types).
 *
 * Общий для трёх страниц src/views/pages/admin/{genres,categories,types}.php:
 * там остаются только шапка, мок-данные и подключение admin.js.
 *
 * @param string $resource — ключ ресурса ('genres' | 'categories' | 'types').
 *                           Из него JS собирает контрактный путь
 *                           /api/admin/{resource} (dictApi в admin.js).
 * @param array  $items     — строки: [['id' => int,
 *                            'translations' => [['langCode' => 'en', 'title' => '...'], ...]], ...]
 * @param string $searchPh  — плейсхолдер поиска (локализован на странице)
 * @param string $addLabel  — подпись кнопки добавления (локализована на странице)
 *
 * Данные отдаются JS'у как JSON (script[type="application/json"]), чтобы
 * add/edit/delete работали с тем же мок-массивом, что и серверная отрисовка.
 * Поиск — клиентский (admin.js): по всем переводам через data-search.
 *
 * Стили — public/assets/css/admin.css (.admin-toolbar, .admin-table).
 */

$resource = $resource ?? 'genres';
$items    = is_array($items ?? null) ? $items : [];
$searchPh = $searchPh ?? '';
$addLabel = $addLabel ?? '';
?>

<div class="admin-toolbar">
  <!-- Открытие модалки «Добавить» — admin.js (ADM-UI-3); сам fetch
       (POST /api/admin/{resource}, при недоступности — мок) — dictApi там же -->
  <button type="button" class="btn btn--primary admin-toolbar__add" data-admin-dict-add>
    <?= $view->e($addLabel) ?>
  </button>

  <input class="admin-toolbar__search"
         type="search"
         name="q"
         data-admin-search
         placeholder="<?= $view->e($searchPh) ?>"
         aria-label="<?= $view->e($searchPh) ?>"
         autocomplete="off">

  <span class="admin-toolbar__count" data-admin-count><?= count($items) ?></span>
</div>

<div class="admin-table-wrap">
  <table class="admin-table"
         data-admin-table
         data-dict-table
         data-dict-resource="<?= $view->e($resource) ?>">
    <thead>
      <tr>
        <th scope="col"><?= $view->e($view->t('common.admin.col_id')) ?></th>
        <th scope="col"><?= $view->e($view->t('common.admin.col_translations')) ?></th>
        <th scope="col"><?= $view->e($view->t('common.admin.col_actions')) ?></th>
      </tr>
    </thead>
    <tbody data-dict-rows>
      <?php foreach ($items as $item):
          $id = (int) ($item['id'] ?? 0);
          $translations = is_array($item['translations'] ?? null) ? $item['translations'] : [];
          $titles = array_values(array_filter(array_map(
              static fn($t): string => trim((string) ($t['title'] ?? '')),
              $translations
          )));
          $search = mb_strtolower(implode(' ', $titles));
      ?>
        <tr data-dict-row-id="<?= $id ?>" data-search="<?= $view->e($search) ?>">
          <td class="admin-table__id"><?= $id ?></td>
          <td>
            <!-- Все переводы через « / »: «Fantasy / Фэнтези / Fantasía» -->
            <span class="admin-dict__translations"><?= $view->e(implode(' / ', $titles)) ?></span>
          </td>
          <td>
            <div class="admin-actions">
              <!-- Модалка «Редактировать» / удаление через ConfirmModal — admin.js.
                   Запросы — dictApi: PATCH/DELETE /api/admin/{resource}/{id}. -->
              <button type="button"
                      class="btn btn--secondary"
                      data-admin-dict-edit
                      data-dict-row-id="<?= $id ?>">
                <?= $view->e($view->t('common.admin.action_edit')) ?>
              </button>
              <button type="button"
                      class="btn btn--danger"
                      data-admin-dict-delete
                      data-dict-row-id="<?= $id ?>">
                <?= $view->e($view->t('common.admin.action_delete')) ?>
              </button>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>

      <!-- Заглушка пустого состояния. Текст переключает admin.js:
           данных нет — common.admin.empty, поиск ничего не нашёл — admin.no_results -->
      <tr class="admin-table__empty" data-admin-empty<?= $items ? ' hidden' : '' ?>>
        <td colspan="3"><?= $view->e($view->t('common.admin.empty')) ?></td>
      </tr>
    </tbody>
  </table>
</div>

<!-- Мок-массив страницы: JS читает его при первом рендере строк (единый
     источник правды, пока GET /api/admin/{resource} не отвечает — dictApi
     в admin.js). Без htmlspecialchars: <script> — raw-text-элемент, сущности
     в нём НЕ декодируются. JSON_HEX_* вырезает < > & " ', поэтому </script>
     и разрыв разметки внутри данных невозможны. -->
<script type="application/json" data-dict-data><?= json_encode(
    ['resource' => $resource, 'items' => $items],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?></script>
