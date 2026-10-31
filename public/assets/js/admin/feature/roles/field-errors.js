/**
 * Разбор ошибок валидации бэка по полям формы.
 */
import { t } from '../../core/i18n.js';

export function parseFieldErrors(data) {
  var out = [];
  var codeOf = function (c) {
    if (typeof c === 'string') return c;
    if (c && typeof c === 'object') return String(c.message || c.code || c.error || '');
    return '';
  };
  var listOf = function (node) { return node.errors || node.messages || node.codes || node.error || node.message; };
  var add = function (field, value) {
    var list = Array.isArray(value) ? value : (value == null ? [] : [value]);
    var codes = list.map(codeOf).filter(function (c) { return c !== ''; });
    if (codes.length) out.push({ field: String(field || ''), codes: codes });
  };
  var walk = function (prefix, node) {
    if (Array.isArray(node)) {
      if (node.every(function (x) { return typeof x === 'string'; })) { add(prefix, node); return; }
      node.forEach(function (item, i) {
        if (item && typeof item === 'object') walk(prefix ? prefix + '.' + i : String(i), item);
        else add(prefix, item);
      });
      return;
    }
    if (node && typeof node === 'object') {
      if (node.field !== undefined) { add(node.field, listOf(node)); return; }
      Object.keys(node).forEach(function (k) { walk(prefix ? prefix + '.' + k : k, node[k]); });
      return;
    }
    add(prefix, node);
  };

  var root = data;
  if (root && typeof root === 'object' && !Array.isArray(root) &&
      root.field === undefined && root.errors === undefined &&
      root.data && typeof root.data === 'object') {
    root = root.data;
  }
  if (!root || typeof root !== 'object') return out;
  if (!Array.isArray(root) && root.field === undefined) {
    root = root.errors !== undefined ? root.errors
         : (root.error && typeof root.error === 'object' && root.error.details !== undefined) ? root.error.details
         : root.details !== undefined ? root.details
         : null;
  }
  if (root) walk('', root);

  if (!out.length) {
    var scan = function (node) {
      if (Array.isArray(node)) { node.forEach(scan); return; }
      if (!node || typeof node !== 'object') return;
      var field = node.field !== undefined ? node.field
                : node.name !== undefined ? node.name
                : node.path !== undefined ? node.path
                : node.param;
      if (typeof field === 'string') {
        var arr = Object.keys(node).map(function (k) { return node[k]; })
          .filter(function (v) { return Array.isArray(v); })[0];
        add(field, arr !== undefined ? arr : node.message);
        return;
      }
      Object.keys(node).forEach(function (k) { scan(node[k]); });
    };
    scan(data);
  }
  return out;
}

export function fieldErrorText(code) {
  var key = String(code);
  if (/^errors\.[\w.]+$/.test(key)) {
    var value = t(key);
    if (value && value !== key) return value;
    var last = key.split('.').pop().replace(/_/g, ' ');
    return last.charAt(0).toUpperCase() + last.slice(1);
  }
  return key;
}
