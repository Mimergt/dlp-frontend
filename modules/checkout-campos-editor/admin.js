/* Editor de campos del checkout (panel). Sin dependencias: lista con arrastrar y soltar + panel de edición. */
(function () {
  'use strict';
  var dataEl = document.getElementById('dlpfe-data');
  if (!dataEl) return;
  var data = JSON.parse(dataEl.textContent);
  var items = data.items;
  var rowsEl = document.getElementById('dlpfe-rows');
  var editEl = document.getElementById('dlpfe-edit');
  var jsonEl = document.getElementById('dlpfe-json');
  var current = null, dragIdx = null;
  var SECTIONS = { entrega: 'Entrega', datos: 'Tus datos', factura: 'Factura y notas' };
  var TYPES = { text: 'Texto', textarea: 'Área de texto', checkbox: 'Casilla', select: 'Lista', tel: 'Teléfono', email: 'Correo' };

  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
  function sw(on, attrs) { return '<button type="button" class="dlpfe-sw' + (on ? ' on' : '') + '" role="switch" aria-checked="' + (on ? 'true' : 'false') + '" ' + (attrs || '') + '><i></i></button>'; }

  function renderRows() {
    var html = items.map(function (it, i) {
      return '<tr draggable="true" data-i="' + i + '" class="' + (current === i ? 'sel' : '') + '">' +
        '<td class="h">⋮⋮</td>' +
        '<td><div class="n">' + esc(it.label || '(sin etiqueta)') + '</div><div class="k">' + esc(it.key) + (it.custom ? ' · personalizado' : '') + '</div></td>' +
        '<td><span class="dlpfe-badge">' + esc(SECTIONS[it.section] || it.section) + '</span></td>' +
        '<td>' + sw(it.enabled, 'data-t="enabled" data-i="' + i + '"') + '</td>' +
        '<td>' + sw(it.required, 'data-t="required" data-i="' + i + '"') + '</td>' +
        '<td class="r"><button type="button" class="dlpfe-btn" data-edit="' + i + '">Editar</button></td></tr>';
    }).join('');
    html += data.locked.map(function (l) {
      return '<tr class="locked"><td class="h">🔒</td><td><div class="n">' + esc(l.label) + '</div><div class="k">' + esc(l.key) + '</div></td><td><span class="dlpfe-badge">Sistema</span></td><td colspan="3" class="dlpfe-hint">Lo maneja dlp-tiendas</td></tr>';
    }).join('');
    rowsEl.innerHTML = html;
  }

  function field(label, inner, hint) { return '<div class="dlpfe-f"><label>' + label + '</label>' + inner + (hint ? '<p>' + hint + '</p>' : '') + '</div>'; }
  function seg(name, opts, val) {
    return '<div class="dlpfe-seg" data-seg="' + name + '">' + Object.keys(opts).map(function (k) { return '<button type="button" data-v="' + k + '" class="' + (k === val ? 'on' : '') + '">' + opts[k] + '</button>'; }).join('') + '</div>';
  }

  function renderEdit() {
    if (current == null) { editEl.innerHTML = '<div class="dlpfe-empty">Elige un campo con «Editar» para cambiar sus datos.</div>'; return; }
    var it = items[current];
    var h = '<h3>Editar campo</h3>';
    h += field('Etiqueta', '<input type="text" data-p="label" value="' + esc(it.label) + '">');
    if (it.type !== 'checkbox') h += field('Texto de ayuda', '<input type="text" data-p="placeholder" value="' + esc(it.placeholder) + '">');
    h += field('Ancho', seg('width', { full: 'Completo', first: 'Mitad izq.', last: 'Mitad der.' }, it.width));
    h += field('Sección', seg('section', SECTIONS, it.section));
    if (it.custom) {
      h += field('Tipo', '<select data-p="type">' + Object.keys(TYPES).map(function (k) { return '<option value="' + k + '"' + (k === it.type ? ' selected' : '') + '>' + TYPES[k] + '</option>'; }).join('') + '</select>');
      if (it.type === 'select') h += field('Opciones', '<textarea rows="4" data-p="options">' + esc(it.options) + '</textarea>', 'Una opción por línea.');
      h += '<div class="dlpfe-line"><span>Mostrar en el pedido (admin)</span>' + sw(it.show_order, 'data-e="show_order"') + '</div>';
      h += '<div class="dlpfe-line"><span>Mostrar en el correo</span>' + sw(it.show_email, 'data-e="show_email"') + '</div>';
    }
    h += '<div class="dlpfe-code">Clave: <code>' + esc(it.key) + '</code></div>';
    if (it.custom && it.isNew) h += '<div class="dlpfe-actions"><button type="button" class="dlpfe-btn dlpfe-danger" id="dlpfe-del">Eliminar</button></div>';
    editEl.innerHTML = h;
  }

  rowsEl.addEventListener('click', function (e) {
    var t = e.target.closest('[data-t]');
    if (t) { var i = +t.dataset.i; items[i][t.dataset.t] = items[i][t.dataset.t] ? 0 : 1; renderRows(); return; }
    var ed = e.target.closest('[data-edit]');
    if (ed) { current = +ed.dataset.edit; renderRows(); renderEdit(); }
  });

  editEl.addEventListener('input', function (e) {
    var p = e.target.dataset.p; if (p == null || current == null) return;
    items[current][p] = e.target.value;
    if (p === 'label') { var n = rowsEl.querySelector('tr.sel .n'); if (n) n.textContent = e.target.value || '(sin etiqueta)'; }
    if (p === 'type') renderEdit();
  });
  editEl.addEventListener('change', function (e) { if (e.target.dataset.p === 'type') { items[current].type = e.target.value; renderEdit(); } });
  editEl.addEventListener('click', function (e) {
    var b = e.target.closest('[data-seg] button');
    if (b) { items[current][b.parentNode.dataset.seg] = b.dataset.v; renderEdit(); renderRows(); return; }
    var s = e.target.closest('[data-e]');
    if (s) { var k = s.dataset.e; items[current][k] = items[current][k] ? 0 : 1; renderEdit(); return; }
    if (e.target.id === 'dlpfe-del' && confirm('¿Eliminar este campo?')) { items.splice(current, 1); current = null; renderRows(); renderEdit(); }
  });

  // Arrastrar para ordenar
  rowsEl.addEventListener('dragstart', function (e) { var tr = e.target.closest('tr[data-i]'); if (!tr) return; dragIdx = +tr.dataset.i; e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', ''); } catch (x) {} });
  rowsEl.addEventListener('dragover', function (e) { if (dragIdx != null) e.preventDefault(); });
  rowsEl.addEventListener('drop', function (e) {
    var tr = e.target.closest('tr[data-i]'); if (!tr || dragIdx == null) return;
    e.preventDefault();
    var to = +tr.dataset.i, it = items.splice(dragIdx, 1)[0];
    items.splice(to, 0, it);
    if (current != null) current = items.indexOf(items[current === dragIdx ? to : current]);
    dragIdx = null; current = null; renderRows(); renderEdit();
  });

  document.getElementById('dlpfe-add').addEventListener('click', function () {
    var n = 1; while (items.some(function (x) { return x.key === 'billing_campo_' + n; })) n++;
    items.push({ key: 'billing_campo_' + n, group: 'billing', label: 'Nuevo campo', placeholder: '', type: 'text', required: 0, enabled: 1, width: 'full', section: 'datos', custom: 1, options: '', show_order: 1, show_email: 0, locked: 0, isNew: 1 });
    current = items.length - 1; renderRows(); renderEdit();
    var inp = editEl.querySelector('[data-p=label]'); if (inp) { inp.focus(); inp.select(); }
  });

  document.getElementById('dlpfe-form').addEventListener('submit', function () {
    jsonEl.value = JSON.stringify(items.map(function (x) { var c = {}; for (var k in x) if (k !== 'isNew') c[k] = x[k]; return c; }));
  });

  renderRows(); renderEdit();
})();
