<?php
/**
 * admin/reports — каркас страницы жалоб админки (таблица + модалка
 * «Отклонить/Удалить»).
 *
 * Статус: ЗАГЛОШКА на моках — проверок прав нет (имена разрешений ждём
 * от бэка). Строки рендерит сервер из мок-массива ниже, но решение по
 * жалобе уходит в fetch-слой reportsApi (public/assets/js/admin.js,
 * раздел REPORTS API) c graceful-фолбэком на мок. КОНТРАКТ:
 *   GET    /api/admin/reports?page=&ps=  -> { items: [...] }
 *   PATCH  /api/admin/reports/{id}       -> 204
 *          body: { decision: 'reject' | 'delete', note: string }
 *
 * Плюс клиентский фильтр по статусу (select [data-admin-status])
 * поверх поиска [data-admin-search]. Строки несут data-status.
 *
 * Стили — public/assets/css/admin.css (.admin-table, .admin-badge,
 * .admin-report__*), каркас .modal — modal.css.
 * Логика — public/assets/js/admin.js (раздел REPORTS).
 */

$view->extends('main');
$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));

// ---- ЗАГЛУШКА: строки таблицы. Придут из GET /api/admin/reports ----
$reports = [
    ['id' => 1, 'reporter' => 'reader_one',  'targetType' => 'book',    'targetId' => 12,  'targetLabel' => 'The Silent Library',                 'reason' => 'spam',       'status' => 'new',      'created' => '2026-10-21'],
    ['id' => 2, 'reporter' => 'spam_bot',    'targetType' => 'comment', 'targetId' => 88,  'targetLabel' => 'Check out my channel!!!',             'reason' => 'abuse',     'status' => 'new',      'created' => '2026-10-22'],
    ['id' => 3, 'reporter' => 'maria_reads', 'targetType' => 'article', 'targetId' => 31,  'targetLabel' => 'Notes on Reading',                    'reason' => 'plagiarism', 'status' => 'rejected', 'created' => '2026-10-19'],
    ['id' => 4, 'reporter' => 'denis_k',     'targetType' => 'post',    'targetId' => 5,   'targetLabel' => 'Chapter 3 was great, but the ending…', 'reason' => 'spoiler',   'status' => 'new',      'created' => '2026-10-24'],
];

$statusBadge = [
    'new'      => 'admin-badge--warning',
    'rejected' => 'admin-badge--muted',
    'deleted'  => 'admin-badge--danger',
];
?>

<?php $view->setBlock('selectedTab', 'admin'); ?>

<?php $view->startBlock('title'); ?><?= $tr('common.admin.reports_title') ?> - <?= $tr('common.common.app_name') ?><?php $view->endBlock('title'); ?>

<?php $view->startBlock('head_extra'); ?>
  <link rel="stylesheet" href="<?= $view->asset('css/admin.css') ?>">
<?php $view->endBlock('head_extra'); ?>

<?php $view->startBlock('content'); ?>

<?php $view->include('page-header', [
    'title'    => $view->t('common.admin.reports_title'),
    'subtitle' => $view->t('common.admin.reports_subtitle'),
]); ?>

<p class="admin-note"><?= $tr('common.admin.stub_note') ?></p>

<div class="admin-toolbar">
  <input class="admin-toolbar__search"
         type="search"
         name="q"
         data-admin-search
         placeholder="<?= $tr('common.admin.search_reports_ph') ?>"
         aria-label="<?= $tr('common.admin.search_reports_ph') ?>"
         autocomplete="off">
  <label class="admin-toolbar__filter">
    <span class="admin-toolbar__filter-label"><?= $tr('common.admin.filter_status') ?></span>
    <select class="form-field__input admin-toolbar__select"
            data-admin-status
            aria-label="<?= $tr('common.admin.filter_status') ?>">
      <option value=""><?= $tr('common.admin.status_all') ?></option>
      <option value="new"><?= $tr('common.admin.status_new') ?></option>
      <option value="rejected"><?= $tr('common.admin.status_rejected') ?></option>
      <option value="deleted"><?= $tr('common.admin.status_deleted') ?></option>
    </select>
  </label>
  <span class="admin-toolbar__count" data-admin-count><?= count($reports) ?></span>
</div>

<!-- Статус загрузки/ошибки (живой регион): наполняет admin.js (раздел REPORTS) -->
<p class="admin-toolbar__status" role="status" aria-live="polite" data-admin-reports-status hidden></p>

<div class="admin-table-wrap">
  <table class="admin-table" data-admin-table="reports">
    <thead>
      <tr>
        <th scope="col"><?= $tr('common.admin.col_id') ?></th>
        <th scope="col"><?= $tr('common.admin.col_reporter') ?></th>
        <th scope="col"><?= $tr('common.admin.col_target') ?></th>
        <th scope="col"><?= $tr('common.admin.col_reason') ?></th>
        <th scope="col"><?= $tr('common.admin.col_status') ?></th>
        <th scope="col"><?= $tr('common.admin.col_created') ?></th>
        <th scope="col"><?= $tr('common.admin.col_actions') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($reports as $row): $row['id'] = (int) $row['id']; ?>
        <tr data-report-id="<?= $row['id'] ?>"
            data-status="<?= $view->e($row['status']) ?>"
            data-search="<?= $view->e(mb_strtolower(implode(' ', [
                $row['reporter'],
                $row['targetLabel'],
                $view->t('common.report.reason_' . $row['reason']),
                $view->t('common.admin.status_' . $row['status']),
            ]))) ?>"
            data-report-reporter="<?= $view->e($row['reporter']) ?>"
            data-report-target-type="<?= $view->e($row['targetType']) ?>"
            data-report-target-label="<?= $view->e($row['targetLabel']) ?>"
            data-report-reason="<?= $view->e($row['reason']) ?>"
            data-report-created="<?= $view->e($row['created']) ?>">
          <td class="admin-table__id"><?= $row['id'] ?></td>
          <td><?= $view->e($row['reporter']) ?></td>
          <td>
            <span class="admin-badge admin-badge--accent">
              <?= $view->e($view->t('common.report.kind_' . $row['targetType'])) ?>
            </span>
            <span class="admin-table__clip admin-report__target"
                  title="<?= $view->e($row['targetLabel']) ?>"><?= $view->e($row['targetLabel']) ?></span>
          </td>
          <td><?= $view->e($view->t('common.report.reason_' . $row['reason'])) ?></td>
          <td data-status-cell>
            <span class="admin-badge <?= $view->e($statusBadge[$row['status']] ?? 'admin-badge--muted') ?>">
              <?= $view->e($view->t('common.admin.status_' . $row['status'])) ?>
            </span>
          </td>
          <td><?= $view->e($row['created']) ?></td>
          <td>
            <div class="admin-actions">
              <!-- Модалка «Отклонить/Удалить» — admin.js (раздел REPORTS):
                   решение уходит в reportsApi (PATCH /api/admin/reports/{id}) -->
              <button type="button"
                      class="btn btn--secondary"
                      data-admin-report-review
                      data-report-id="<?= $row['id'] ?>">
                <?= $tr('common.admin.action_decide') ?>
              </button>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>

      <!-- Пустое состояние (переключает admin.js при пустом поиске) -->
      <tr class="admin-table__empty" data-admin-empty<?= $reports ? ' hidden' : '' ?>>
        <td colspan="7"><?= $tr('common.admin.empty') ?></td>
      </tr>
    </tbody>
  </table>
</div>

<!-- Модалка решения по жалобе: сводка + «Отклонить» / «Удалить» -->
<?php $view->include('admin-report-modal'); ?>

<?php $view->endBlock('content'); ?>

<?php $view->startBlock('scripts'); ?>
<script src="<?= $view->asset('js/admin.js') ?>" defer></script>
<?php $view->endBlock('scripts'); ?>
