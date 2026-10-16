/**
 * article-text.js — чистые (без DOM) функции для формы статьи:
 *   • текст страницы PDF и позиция «страница / строка / символ»;
 *   • якоря начала и конца отрывка;
 *   • вырезка текста между якорями и разбивка на абзацы.
 *
 * Подключается ДО article-form.js (глобал LoreArticleText), а в Node — через require().
 *
 * ── Как считается позиция (версия якорей 1) ──────────────────────────────
 * Текст страницы строится из элементов, которые отдаёт PDF.js (getTextContent):
 * строки элементов подряд, а где у элемента hasEOL — добавляется «\\n».
 *   offset — индекс символа в этом тексте (абсолютная позиция на странице);
 *   line   — номер строки (1 + число «\\n» до offset);
 *   char   — номер символа в строке (с 1);
 *   quote  — до 40 символов рядом с точкой (для начала — после неё, для конца —
 *            до неё): по цитате место можно найти, если файл книги заменят.
 * Если алгоритм когда-либо поменяется — увеличить ANCHOR_VERSION.
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) module.exports = factory();
  else root.LoreArticleText = factory();
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  var ANCHOR_VERSION = 1;
  var QUOTE_LEN = 40;

  /** Только текстовые элементы: служебные (marked content) в тексте и в слое не участвуют. */
  function textItems(content) {
    var items = (content && content.items) || [];
    var out = [];
    for (var i = 0; i < items.length; i++) {
      if (items[i] && typeof items[i].str === 'string') out.push(items[i]);
    }
    return out;
  }

  /** { text, starts }: starts[i] — смещение начала i-го текстового элемента. */
  function buildPageText(items) {
    var text = '';
    var starts = [];
    for (var i = 0; i < items.length; i++) {
      starts.push(text.length);
      text += items[i].str;
      if (items[i].hasEOL) text += '\n';
    }
    return { text: text, starts: starts };
  }

  /** Номер строки и символа (оба с 1) для смещения. */
  function positionAt(text, offset) {
    offset = Math.max(0, Math.min(offset, text.length));
    var before = text.slice(0, offset);
    var line = 1;
    for (var i = 0; i < before.length; i++) if (before.charCodeAt(i) === 10) line++;
    var lastNl = before.lastIndexOf('\n');
    return { line: line, char: offset - lastNl };
  }

  function collapse(s) {
    return String(s).replace(/\s+/g, ' ').trim();
  }

  /** side: 'start' | 'end'. */
  function makeAnchor(text, offset, page, side) {
    offset = Math.max(0, Math.min(offset, text.length));
    var pos = positionAt(text, offset);
    var quote = side === 'end'
      ? text.slice(Math.max(0, offset - QUOTE_LEN), offset)
      : text.slice(offset, offset + QUOTE_LEN);
    return { page: page, offset: offset, line: pos.line, char: pos.char, quote: collapse(quote) };
  }

  /** <0, 0, >0 — как у обычного сравнения. */
  function compareAnchors(a, b) {
    if (a.page !== b.page) return a.page - b.page;
    return a.offset - b.offset;
  }

  /** Куски текста по страницам между якорями. pageTexts: { номерСтраницы: текст }. */
  function sliceFragment(pageTexts, start, end) {
    var chunks = [];
    for (var p = start.page; p <= end.page; p++) {
      var t = String(pageTexts[p] || '');
      var from = p === start.page ? start.offset : 0;
      var to = p === end.page ? end.offset : t.length;
      chunks.push(t.slice(from, to));
    }
    return chunks;
  }

  /**
   * Строки PDF -> абзацы. В PDF нет абзацев, только строки, поэтому:
   *   • абзац заканчивается там, где строка короче ~70% типичной длины и оканчивается
   *     знаком конца предложения; новая строка с тире (диалог) тоже начинает абзац;
   *   • перенос слова («пере-\\nнос») склеивается без дефиса и пробела;
   *   • остальные переводы строк превращаются в пробел.
   * Результат всё равно правится автором в редакторе.
   */
  function toParagraphs(chunks) {
    var lines = [];
    for (var c = 0; c < chunks.length; c++) {
      var parts = String(chunks[c]).split('\n');
      for (var i = 0; i < parts.length; i++) {
        var ln = parts[i].replace(/\s+/g, ' ').trim();
        if (ln) lines.push(ln);
      }
    }
    if (!lines.length) return [];

    var lens = lines.map(function (l) { return l.length; }).sort(function (a, b) { return a - b; });
    var ref = lens[Math.floor(lens.length * 0.9)] || lens[lens.length - 1];
    var SENTENCE_END = /[.!?…»”"')\]]$/;
    var DIALOG_START = /^[—–-]\s/;
    var HYPHEN_END = /[A-Za-zА-Яа-яЁё]-$/;
    var LOWER_START = /^[a-zа-яё]/;

    var paras = [];
    var cur = lines[0];
    for (var k = 1; k < lines.length; k++) {
      var prev = lines[k - 1];
      var next = lines[k];
      var newPara = SENTENCE_END.test(prev) && (prev.length < ref * 0.7 || DIALOG_START.test(next));
      if (newPara) { paras.push(cur); cur = next; continue; }
      if (HYPHEN_END.test(cur) && LOWER_START.test(next)) cur = cur.slice(0, -1) + next;
      else cur += ' ' + next;
    }
    paras.push(cur);
    return paras;
  }

  function escapeHtml(s) {
    return String(s === null || s === undefined ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function paragraphsToHtml(paras) {
    var out = '';
    for (var i = 0; i < paras.length; i++) {
      if (paras[i]) out += '<p>' + escapeHtml(paras[i]) + '</p>';
    }
    return out;
  }

  return {
    ANCHOR_VERSION: ANCHOR_VERSION,
    textItems: textItems,
    buildPageText: buildPageText,
    positionAt: positionAt,
    makeAnchor: makeAnchor,
    compareAnchors: compareAnchors,
    sliceFragment: sliceFragment,
    toParagraphs: toParagraphs,
    paragraphsToHtml: paragraphsToHtml,
    escapeHtml: escapeHtml
  };
});