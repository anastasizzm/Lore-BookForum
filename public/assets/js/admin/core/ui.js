/**
 * Обёртки над глобальными ConfirmModal и Messages (notify).
 */

export var confirmModal = function (options) {
  return window.ConfirmModal ? ConfirmModal.confirm(options) : Promise.resolve(false);
};

export var notify = function (text, type) {
  if (window.Messages) window.Messages.show(text, { type: type || 'success' });
  else console.warn(text);
};
