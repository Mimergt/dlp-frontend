/* Editor visual de categorías (home y menú). Guarda el mismo texto de siempre en un textarea oculto:
   «slug | Título | URL de la foto | cinta», una categoría por línea. */
(function ($) {
  'use strict';

  function limpio(v) { return String(v || '').replace(/[|\r\n]+/g, ' ').trim(); }

  function parsear(texto) {
    return String(texto || '').split(/\r?\n/).map(function (l) {
      var p = l.split('|').map(function (x) { return x.trim(); });
      return p[0] ? { slug: p[0], titulo: p[1] || '', img: p[2] || '', cinta: p[3] || '' } : null;
    }).filter(Boolean);
  }

  function iniciar(box) {
    var area = box.querySelector('textarea');
    var lista = box.querySelector('.dlpfe-cats-list');
    var terms = [];
    try { terms = JSON.parse(box.getAttribute('data-terms')) || []; } catch (e) {}
    var size = box.getAttribute('data-size') || 'large';
    var porSlug = {};
    terms.forEach(function (t) { porSlug[t.slug] = t; });

    function guardar() {
      var out = [];
      [].forEach.call(lista.querySelectorAll('.dlpfe-cat'), function (r) {
        var slug = r.querySelector('select').value;
        if (!slug) return;
        var titulo = limpio(r.querySelector('.t').value), img = r.getAttribute('data-img') || '', cinta = limpio(r.querySelector('.c').value);
        var partes = [slug, titulo, img, cinta];
        while (partes.length > 1 && partes[partes.length - 1] === '') partes.pop();
        out.push(partes.join(' | '));
      });
      area.value = out.join('\n');
    }

    function miniatura(r) {
      var img = r.getAttribute('data-img'), slug = r.querySelector('select').value;
      var th = r.querySelector('.th'), url = img || (porSlug[slug] ? porSlug[slug].thumb : '');
      th.style.backgroundImage = url ? 'url("' + url.replace(/"/g, '%22') + '")' : 'none';
      th.classList.toggle('vacia', !url);
      th.classList.toggle('auto', !img && !!url);
      r.querySelector('.q').hidden = !img;
    }

    function fila(d) {
      var r = document.createElement('div');
      r.className = 'dlpfe-cat';
      r.setAttribute('data-img', d.img || '');
      var opts = '';
      var conocido = !!porSlug[d.slug];
      if (!conocido && d.slug) opts += '<option value="' + d.slug.replace(/"/g, '') + '" selected>' + d.slug.replace(/[<>&]/g, '') + ' (no existe)</option>';
      terms.forEach(function (t) { opts += '<option value="' + t.slug + '"' + (t.slug === d.slug ? ' selected' : '') + '>' + t.name.replace(/[<>&]/g, '') + '</option>'; });
      r.innerHTML =
        '<span class="h" title="Arrastra para ordenar">⠿</span>' +
        '<div class="ph"><button type="button" class="th" title="Elegir foto"><span>Foto</span></button><button type="button" class="q button-link" hidden>Quitar foto</button></div>' +
        '<div class="f">' +
          '<label><span>Categoría</span><select>' + opts + '</select></label>' +
          '<label><span>Título que se muestra</span><input type="text" class="t" placeholder="Si lo dejas vacío, usa el nombre de la categoría"></label>' +
          '<label><span>Cinta en la esquina <em>(opcional)</em></span><input type="text" class="c" placeholder="Ej. ¡Lo nuevo!"></label>' +
        '</div>' +
        '<button type="button" class="x" title="Quitar esta categoría" aria-label="Quitar esta categoría">×</button>';
      r.querySelector('.t').value = d.titulo || '';
      r.querySelector('.c').value = d.cinta || '';
      lista.appendChild(r);
      miniatura(r);
    }

    parsear(area.value).forEach(fila);

    lista.addEventListener('input', guardar);
    lista.addEventListener('change', function (e) {
      if (e.target.tagName === 'SELECT') { miniatura(e.target.closest('.dlpfe-cat')); }
      guardar();
    });
    lista.addEventListener('click', function (e) {
      var r = e.target.closest('.dlpfe-cat');
      if (!r) return;
      if (e.target.closest('.x')) {
        if (confirm('¿Quitar esta categoría de la lista?')) { r.parentNode.removeChild(r); guardar(); }
      } else if (e.target.closest('.q')) {
        r.setAttribute('data-img', ''); miniatura(r); guardar();
      } else if (e.target.closest('.th')) {
        var marco = wp.media({ title: 'Elige la foto', multiple: false, library: { type: 'image' }, button: { text: 'Usar esta foto' } });
        marco.on('select', function () {
          var a = marco.state().get('selection').first().toJSON();
          var url = (a.sizes && a.sizes[size] && a.sizes[size].url) || a.url;
          r.setAttribute('data-img', url); miniatura(r); guardar();
        });
        marco.open();
      }
    });
    box.querySelector('.dlpfe-cats-add').addEventListener('click', function () {
      var usados = [].map.call(lista.querySelectorAll('select'), function (s) { return s.value; });
      var libre = terms.filter(function (t) { return usados.indexOf(t.slug) === -1; })[0] || terms[0];
      fila({ slug: libre ? libre.slug : '', titulo: '', img: '', cinta: '' });
      guardar();
      var u = lista.lastElementChild; if (u) u.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    });
    $(lista).sortable({ handle: '.h', axis: 'y', tolerance: 'pointer', update: guardar });
    guardar();
  }

  $(function () { [].forEach.call(document.querySelectorAll('.dlpfe-cats'), iniciar); });
})(jQuery);
