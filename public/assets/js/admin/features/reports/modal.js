/**
 * Модалка решения по жалобе «Отклонить / Удалить».
 */
import { t } from '../../core/i18n.js';
import { notify } from '../../core/ui.js';
import { reportsApi } from './api.js';

export var reportState = {
  modal: null,
  row: null,
  id: '',
  submitting: false,
  lastFocus: null
};

function reportRoot() {
  if (!reportState.modal) reportState.modal = document.querySelector('[data-admin-report-modal]');
  return reportState.modal;
}

export function isReportOpen() {
  var m = reportRoot();
  return !!(m && !m.hidden);
}

function rq(sel) {
  var m = reportRoot();
  return m ? m.querySelector(sel) : null;
}

function setReportsStatus(text) {
  var el = document.querySelector('[data-admin-reports-status]');
  if (!el) return;
  el.textContent = text || '';
  el.hidden = !text;
}

function setReportButtonsDisabled(disabled) {
  ['[data-admin-report-reject]', '[data-admin-report-delete]'].forEach(function (sel) {
    var btn = rq(sel);
    if (btn) btn.disabled = disabled;
  });
}

export function closeReportModal() {
  var m = reportRoot();
  if (m) m.hidden = true;
  document.body.classList.remove('is-modal-open');
  reportState.row = null;
  reportState.id = '';
  reportState.submitting = false;

  ['[data-admin-report-reject]', '[data-admin-report-delete]', '[data-admin-report-cancel]'].forEach(function (sel) {
    var btn = rq(sel);
    if (btn) btn.disabled = false;
  });

  if (reportState.lastFocus && reportState.lastFocus.isConnected) {
    try { reportState.lastFocus.focus(); } catch (_) {}
  }
  reportState.lastFocus = null;
}

export function openReportModal(btn) {
  var m = reportRoot();
  var row = btn.closest('tr');
  if (!m || !row) return;

  var text = function (sel, value) {
    var el = rq(sel);
    if (el) el.textContent = value || '';
  };

  reportState.row = row;
  reportState.id = row.getAttribute('data-report-id') || '';

  var title = rq('[data-admin-report-title]');
  if (title) title.textContent = t('admin.decision_title', { id: reportState.id });

  text('[data-admin-report-kind]', t('report.kind_' + (row.getAttribute('data-report-target-type') || 'comment')));
  text('[data-admin-report-target]', row.getAttribute('data-report-target-label'));
  text('[data-admin-report-reason]', t('report.reason_' + (row.getAttribute('data-report-reason') || 'other')));
  text('[data-admin-report-reporter]', row.getAttribute('data-report-reporter'));
  text('[data-admin-report-created]', row.getAttribute('data-report-created'));

  var note = rq('[data-admin-report-note]');
  if (note) note.value = '';

  setReportsStatus('');
  reportState.lastFocus = document.activeElement;
  m.hidden = false;
  document.body.classList.add('is-modal-open');

  var cancel = rq('[data-admin-report-cancel]');
  if (cancel) cancel.focus();
}

function applyReportDecision(row, action) {
  var status = action === 'delete' ? 'deleted' : 'rejected';
  row.setAttribute('data-status', status);

  var statusCell = row.querySelector('[data-status-cell]');
  if (statusCell) {
    statusCell.innerHTML =
      '<span class="admin-badge ' + (action === 'delete' ? 'admin-badge--danger' : 'admin-badge--muted') + '">' +
      t('admin.status_' + status) +
      '</span>';
  }

  var reviewBtn = row.querySelector('[data-admin-report-review]');
  if (reviewBtn) reviewBtn.disabled = true;
}

export function decideReport(action) {
  var row = reportState.row;
  if (!row || reportState.submitting) return;
  reportState.submitting = true;
  setReportButtonsDisabled(true);

  var note = rq('[data-admin-report-note]');
  var noteText = note ? note.value.trim() : '';

  reportsApi.decide(reportState.id, action, noteText).then(function (result) {
    if (result.mode === 'forbidden') {
      notify(t('js.no_rights'), 'error');
      setReportsStatus(t('js.no_rights'));
      setReportButtonsDisabled(false);
      return;
    }
    if (result.mode === 'error') {
      notify(result.message, 'error');
      setReportsStatus(result.message);
      setReportButtonsDisabled(false);
      return;
    }

    applyReportDecision(row, action);
    var toast = action === 'delete' ? 'admin.delete_report_success' : 'admin.reject_success';
    closeReportModal();
    notify(toast, 'success');
  }).catch(function (err) {
    console.warn('[admin] report decision failed', err);
    notify(t('errors.common.network_error'), 'error');
    setReportsStatus(t('errors.common.network_error'));
    setReportButtonsDisabled(false);
  }).then(function () {
    reportState.submitting = false;
  });
}
