/* Disponibilidad (DLP): filtros, atajos y contadores. Sin dependencias. */
(function () {
  function $all(s, r) { return [].slice.call((r || document).querySelectorAll(s)); }

  // ---- Caja del producto ----
  var box = document.querySelector('.dlpd-prod');
  if (box) {
    var days = $all('.dlpd-days input', box), stores = $all('.dlpd-stores input', box), sum = box.querySelector('.dlpd-sum');
    var names = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    function refresh() {
      var on = days.filter(function (i) { return i.checked; }).map(function (i) { return +i.value; });
      var out = stores.filter(function (i) { return i.checked; }).map(function (i) { return i.getAttribute('data-n'); });
      var t = on.length === 0 || on.length === 7 ? 'Se vende <b>todos los días</b>' : 'Se vende solo: <b>' + on.map(function (d) { return names[d]; }).join(', ') + '</b>';
      t += out.length ? '<br>No se vende en: <b>' + out.join(', ') + '</b>' : '<br>Se vende en <b>todas las tiendas</b>';
      sum.innerHTML = t;
    }
    box.addEventListener('change', refresh);
    box.addEventListener('click', function (e) {
      var q = e.target.closest('[data-set]'); if (!q) return; e.preventDefault();
      var set = q.getAttribute('data-set');
      if (set === 'stores-none') { stores.forEach(function (i) { i.checked = false; }); }
      else {
        var map = { all: [0, 1, 2, 3, 4, 5, 6], week: [1, 2, 3, 4, 5], weekend: [0, 6, 5] };
        var m = map[set] || []; days.forEach(function (i) { i.checked = m.indexOf(+i.value) > -1; });
      }
      refresh();
    });
    var f = box.querySelector('.dlpd-find');
    if (f) f.addEventListener('input', function () {
      var v = f.value.toLowerCase();
      $all('.dlpd-stores .dlpd-pill', box).forEach(function (l) { l.style.display = l.textContent.toLowerCase().indexOf(v) > -1 ? '' : 'none'; });
    });
    refresh();
  }

  // ---- Página por tienda ----
  var page = document.querySelector('.dlpd-page');
  if (page) {
    var items = $all('.dlpd-item', page), cnt = page.querySelector('.dlpd-count'), dirty = page.querySelector('.dlpd-dirty');
    items.forEach(function (it) { var c = it.querySelector('input[type=checkbox]'); c.setAttribute('data-orig', c.checked ? '1' : '0'); });
    function recount() {
      var inn = 0, out = 0, chg = 0;
      items.forEach(function (it) {
        var c = it.querySelector('input[type=checkbox]');
        c.checked ? inn++ : out++;
        var ch = (c.checked ? '1' : '0') !== c.getAttribute('data-orig');
        it.classList.toggle('chg', ch); if (ch) chg++;
      });
      cnt.innerHTML = '<b>' + inn + '</b> se venden · <b class="out">' + out + '</b> quitados';
      dirty.textContent = chg ? chg + (chg === 1 ? ' cambio sin guardar' : ' cambios sin guardar') : '';
      $all('.dlpd-cat', page).forEach(function (d) {
        var cs = $all('.dlpd-item input', d), q = cs.filter(function (i) { return !i.checked; }).length;
        d.querySelector('summary em').textContent = '(' + cs.length + (q ? ' · ' + q + ' quitados' : '') + ')';
      });
    }
    page.addEventListener('change', recount);
    page.addEventListener('click', function (e) {
      var b = e.target.closest('[data-cat]'); if (!b) return; e.preventDefault();
      var on = b.getAttribute('data-cat') === 'on';
      $all('.dlpd-item', b.closest('.dlpd-cat')).forEach(function (it) { if (it.style.display !== 'none') it.querySelector('input').checked = on; });
      recount();
    });
    var q = page.querySelector('.dlpd-q');
    q.addEventListener('input', function () {
      var v = q.value.toLowerCase();
      items.forEach(function (it) { it.style.display = it.textContent.toLowerCase().indexOf(v) > -1 ? '' : 'none'; });
      $all('.dlpd-cat', page).forEach(function (d) {
        var vis = $all('.dlpd-item', d).some(function (it) { return it.style.display !== 'none'; });
        d.style.display = vis ? '' : 'none'; if (v && vis) d.open = true;
      });
    });
    window.addEventListener('beforeunload', function (e) { if (dirty.textContent) { e.preventDefault(); e.returnValue = ''; } });
    page.querySelector('form.dlpd-form').addEventListener('submit', function () { dirty.textContent = ''; });
    recount();
  }
})();
