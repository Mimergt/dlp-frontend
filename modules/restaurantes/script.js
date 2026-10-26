/* Restaurantes: filtros, búsqueda, «Cerca de mí» y mapa. Mapa 3D con MapLibre GL + PMTiles; si el dispositivo no
   tiene WebGL (o la conexión es lenta) se usa el mapa plano de Leaflet. Las librerías se cargan solo al ver el mapa. */
(function () {
  var root = document.getElementById('dlprs');
  if (!root) return;
  var C = window.dlpRs || {};
  var cards = [].slice.call(root.querySelectorAll('.dlprs-card'));
  var list = root.querySelector('.dlprs-items');
  var byId = {}, data = {};
  (C.tiendas || []).forEach(function (t) { data[t.id] = t; });
  cards.forEach(function (c, i) { c.setAttribute('data-i', i); byId[c.getAttribute('data-id')] = c; });

  var st = { srv: '', open: false, region: '', q: '', pt: null, label: '', sel: 0 };
  var M = null; // adaptador de mapa activo (GL o Leaflet)
  var HINT = 'Toca el mapa para marcar dónde estás, o usa «Cerca de mí».';
  var bar = root.querySelector('.dlprs-bar-t'), barX = root.querySelector('.dlprs-bar-x');
  var empty = root.querySelector('.dlprs-empty'), msg = root.querySelector('.dlprs-msg');
  var nearBtn = root.querySelector('.dlprs-near');

  function norm(s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
  function km(a, b) {
    var R = 6371, r = Math.PI / 180, dLat = (b[0] - a[0]) * r, dLng = (b[1] - a[1]) * r;
    var x = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(a[0] * r) * Math.cos(b[0] * r) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
    return 2 * R * Math.asin(Math.sqrt(x));
  }
  function fmt(d) { return d < 10 ? d.toFixed(1) + ' km' : Math.round(d) + ' km'; }
  function say(t) { msg.textContent = t || HINT; }

  /* ---------- Filtros, orden y lista ---------- */
  function ok(c) {
    if (st.srv === 'delivery' && c.getAttribute('data-d') !== '1') return false;
    if (st.srv === 'pickup' && c.getAttribute('data-p') !== '1') return false;
    if (st.open && c.getAttribute('data-o') !== '1') return false;
    if (st.region && c.getAttribute('data-r') !== st.region) return false;
    if (st.q && c.getAttribute('data-txt').indexOf(st.q) === -1) return false;
    return true;
  }
  function dist(c) {
    var t = data[c.getAttribute('data-id')];
    return st.pt && t && t.lat !== null ? km(st.pt, [t.lat, t.lng]) : null;
  }
  function apply() {
    var vis = [];
    cards.forEach(function (c) {
      var show = ok(c); c.hidden = !show; c.classList.remove('top');
      var d = dist(c), k = c.querySelector('.dlprs-km');
      k.textContent = d === null ? '' : fmt(d); k.title = d === null ? '' : 'Distancia en línea recta';
      c._d = d;
      if (show) vis.push(c);
    });
    cards.slice().sort(function (a, b) {
      if (st.pt) {
        var da = a._d === null ? 1e9 : a._d, db = b._d === null ? 1e9 : b._d;
        if (da !== db) return da - db;
      }
      return a.getAttribute('data-i') - b.getAttribute('data-i');
    }).forEach(function (c) { list.appendChild(c); });
    var firstVisible = [].slice.call(list.children).filter(function (c) { return !c.hidden; })[0];
    var selOk = st.sel && byId[st.sel] && !byId[st.sel].hidden;
    if (firstVisible && !selOk) firstVisible.classList.add('top');
    empty.hidden = vis.length > 0;
    if (st.pt) { bar.innerHTML = '<b>Ordenado por cercanía</b> · ' + st.label; barX.hidden = false; }
    else { bar.innerHTML = '<b>' + vis.length + (vis.length === 1 ? ' restaurante' : ' restaurantes') + '</b>'; barX.hidden = true; }
    if (M) M.draw();
  }

  /* ---------- Acciones compartidas por los dos mapas ---------- */
  function visibleStores() {
    return cards.filter(function (c) { return !c.hidden; }).map(function (c) { return data[c.getAttribute('data-id')]; }).filter(function (t) { return t && t.lat !== null; });
  }
  function popupBox(t, c) {
    var box = document.createElement('div'); box.className = 'dlprs-pop';
    var b = document.createElement('b'); b.textContent = t.n; box.appendChild(b);
    var ico = c && c.querySelector('.dlprs-ico'); if (ico) box.appendChild(ico.cloneNode(true));
    return box;
  }
  function select(id, fromMap) {
    st.sel = id;
    cards.forEach(function (c) { c.classList.toggle('act', +c.getAttribute('data-id') === id); });
    var c = byId[id], t = data[id];
    if (c && fromMap) {
      list.scrollTop = c.offsetTop - list.offsetTop - 8;
      if (window.innerWidth <= 900 && !mapEl.classList.contains('full')) c.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
    if (M && t && t.lat !== null) M.focus(t, popupBox(t, c));
    if (M) M.draw();
  }
  function setPoint(lat, lng, label) {
    st.pt = [lat, lng]; st.label = label;
    if (M) M.setMe(lat, lng);
    say('');
    apply();
    var near = [].slice.call(list.children).filter(function (c) { return !c.hidden && c._d !== null; }).slice(0, 3);
    if (M && near.length) {
      if (near[0]._d > 300) { say('Estás lejos de nuestros restaurantes. Te mostramos el más cercano.'); }
      M.fitNear(lat, lng, near.map(function (c) { return data[c.getAttribute('data-id')]; }));
    }
    list.scrollTop = 0;
  }
  function clearPoint() {
    st.pt = null; if (M) M.clearMe();
    apply(); if (M) M.fitAll();
  }
  function fitAll() { if (M) M.fitAll(); }
  function onMapTap(lat, lng) {
    if (M.closePopup()) return; // el primer toque solo cierra la burbuja
    setPoint(lat, lng, 'Punto elegido');
  }

  /* ---------- Eventos ---------- */
  root.querySelector('.dlprs-chips').addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b) return;
    var k = b.getAttribute('data-k'), r = b.getAttribute('data-r');
    if (k === 'todos') { st.srv = ''; st.open = false; st.region = ''; }
    else if (k === 'delivery' || k === 'pickup') st.srv = st.srv === k ? '' : k;
    else if (k === 'abierto') st.open = !st.open;
    else if (r) st.region = st.region === r ? '' : r;
    [].forEach.call(root.querySelectorAll('.dlprs-chips button'), function (x) {
      var xk = x.getAttribute('data-k'), xr = x.getAttribute('data-r');
      x.classList.toggle('on', xk === 'todos' ? (!st.srv && !st.open && !st.region) : xk === 'abierto' ? st.open : xk ? st.srv === xk : st.region === xr);
    });
    apply(); if (!st.pt) fitAll();
  });
  root.querySelector('.dlprs-q input').addEventListener('input', function () {
    st.q = norm(this.value.trim()); apply(); if (!st.pt) fitAll();
  });
  list.addEventListener('click', function (e) {
    if (e.target.closest('a')) return;
    var c = e.target.closest('.dlprs-card'); if (c) select(+c.getAttribute('data-id'), false);
  });
  barX.addEventListener('click', clearPoint);
  nearBtn.addEventListener('click', function () {
    if (!navigator.geolocation) { say('Tu navegador no permite obtener la ubicación. Toca el mapa para elegir un punto.'); return; }
    nearBtn.classList.add('busy'); say('Buscando tu ubicación…');
    navigator.geolocation.getCurrentPosition(function (p) {
      nearBtn.classList.remove('busy'); setPoint(p.coords.latitude, p.coords.longitude, 'Tu ubicación');
    }, function () {
      nearBtn.classList.remove('busy'); say('No pudimos obtener tu ubicación. Toca el mapa para elegir un punto.');
    }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
  });

  /* ---------- Mapa 3D: MapLibre GL ([lng, lat]) ---------- */
  function adapterGL() {
    var map, mks = [], me = null, pop = null, SEL_ZOOM = 15.5, TILT = 55;
    var a = {
      init: function (style, done) {
        var b = C.bounds; // [[lat, lng], [lat, lng]]
        map = new maplibregl.Map({
          container: 'dlprs-map', style: style, center: [C.center[1], C.center[0]], zoom: 7.5,
          minZoom: Math.max(5, C.minZoom - 1.5), maxZoom: 18, maxPitch: 70, maxBounds: [[b[0][1], b[0][0]], [b[1][1], b[1][0]]],
          attributionControl: false, cooperativeGestures: true, pitchWithRotate: true, dragRotate: true,
          locale: DLP.LOCALE_GL
        });
        map.addControl(new maplibregl.NavigationControl({ visualizePitch: true }), 'top-right');
        map.addControl(new maplibregl.AttributionControl({ customAttribution: DLP.ATTRIBUTION_CLARO }), 'bottom-right');
        map.on('load', function () { done(); });
        map.on('zoomend', function () {
          if (map.getZoom() < 12 && (map.getPitch() > 1 || Math.abs(map.getBearing()) > 1)) map.easeTo({ pitch: 0, bearing: 0, duration: 500 });
          a.draw();
        });
        map.on('pitchend', a.draw);
        map.on('click', function (e) {
          if (e.originalEvent && e.originalEvent.target.closest && e.originalEvent.target.closest('.maplibregl-marker, .maplibregl-popup')) return;
          onMapTap(e.lngLat.lat, e.lngLat.lng);
        });
      },
      draw: function () {
        if (!map) return;
        mks.forEach(function (m) { m.remove(); }); mks = [];
        var pts = visibleStores().map(function (t) { return { t: t, p: map.project([t.lng, t.lat]), used: false }; });
        pts.forEach(function (p) {
          if (p.used) return;
          var mem = [p]; p.used = true;
          pts.forEach(function (q) { if (!q.used && Math.hypot(p.p.x - q.p.x, p.p.y - q.p.y) < 36) { q.used = true; mem.push(q); } });
          if (mem.length === 1) {
            var t = p.t, el = document.createElement('div');
            el.className = 'dlprs-pinbox'; el.title = t.n; el.setAttribute('role', 'button'); el.setAttribute('aria-label', t.n); el.tabIndex = 0;
            el.innerHTML = DLP.pinHtml({ tipo: 'tienda', color: t.id === st.sel ? (C.color || '#ED1C24') : '#111111' });
            var go = function (e) { e.stopPropagation(); select(t.id, true); };
            el.addEventListener('click', go);
            el.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(e); } });
            mks.push(new maplibregl.Marker({ element: el, anchor: 'bottom' }).setLngLat([t.lng, t.lat]).addTo(map));
          } else {
            var lat = 0, lng = 0; mem.forEach(function (x) { lat += x.t.lat; lng += x.t.lng; });
            var cl = document.createElement('div');
            cl.className = 'dlprs-cl'; cl.textContent = mem.length; cl.title = mem.length + ' restaurantes'; cl.setAttribute('role', 'button');
            cl.addEventListener('click', function (e) {
              e.stopPropagation();
              var b = new maplibregl.LngLatBounds(); mem.forEach(function (x) { b.extend([x.t.lng, x.t.lat]); });
              map.fitBounds(b, { padding: 70, maxZoom: 15 });
            });
            mks.push(new maplibregl.Marker({ element: cl, anchor: 'center' }).setLngLat([lng / mem.length, lat / mem.length]).addTo(map));
          }
        });
      },
      fitAll: function () {
        var v = visibleStores(); if (!v.length) return;
        var b = new maplibregl.LngLatBounds(); v.forEach(function (t) { b.extend([t.lng, t.lat]); });
        map.fitBounds(b, { padding: 50, maxZoom: 13, pitch: 0, bearing: 0 });
      },
      focus: function (t, box) {
        map.easeTo({ center: [t.lng, t.lat], zoom: Math.max(map.getZoom(), SEL_ZOOM), pitch: TILT, duration: 900 });
        if (pop) pop.remove();
        pop = new maplibregl.Popup({ offset: [0, -50], closeButton: false, closeOnClick: false, maxWidth: 'none', className: 'dlprs-popup' }).setLngLat([t.lng, t.lat]).setDOMContent(box).addTo(map);
      },
      fitNear: function (lat, lng, near) {
        var b = new maplibregl.LngLatBounds([lng, lat], [lng, lat]);
        near.forEach(function (t) { b.extend([t.lng, t.lat]); });
        map.fitBounds(b, { padding: 70, maxZoom: 14.5, pitch: 0, bearing: 0 });
        map.once('moveend', function () { if (map.getZoom() >= 13.5) map.easeTo({ pitch: 45, duration: 700 }); });
      },
      setMe: function (lat, lng) {
        if (!me) {
          var el = document.createElement('div'); el.className = 'dlprs-me'; el.title = 'Tu punto';
          me = new maplibregl.Marker({ element: el, draggable: true, anchor: 'center' }).setLngLat([lng, lat]).addTo(map);
          me.on('dragend', function () { var p = me.getLngLat(); setPoint(p.lat, p.lng, 'Punto elegido'); });
        } else me.setLngLat([lng, lat]);
      },
      clearMe: function () { if (me) { me.remove(); me = null; } },
      closePopup: function () { if (pop) { pop.remove(); pop = null; return true; } return false; },
      resize: function () { if (map) map.resize(); },
      full: function (on) { if (on) map.cooperativeGestures.disable(); else map.cooperativeGestures.enable(); }
    };
    return a;
  }

  /* ---------- Mapa plano de respaldo: Leaflet ([lat, lng]) ---------- */
  function adapterLeaflet() {
    var map, group, me = null, closedAt = 0;
    var a = {
      init: function (_, done) {
        map = L.map('dlprs-map', { center: C.center, zoom: 8, minZoom: C.minZoom, maxZoom: C.maxZoom, maxBounds: C.bounds, maxBoundsViscosity: 1, zoomSnap: 0.5, scrollWheelZoom: false, zoomControl: false, dragging: !L.Browser.mobile });
        L.control.zoom({ position: 'topright', zoomInTitle: 'Acercar', zoomOutTitle: 'Alejar' }).addTo(map);
        try { DLP.baseLayer(C.tilesUrl).addTo(map); } catch (e) { say('No se pudo cargar el mapa base.'); }
        group = L.layerGroup().addTo(map);
        map.on('zoomend', a.draw);
        map.on('popupclose', function () { closedAt = Date.now(); });
        map.on('click', function (e) { if (Date.now() - closedAt < 150) return; onMapTap(e.latlng.lat, e.latlng.lng); });
        map.on('focus', function () { map.scrollWheelZoom.enable(); });
        map.on('blur', function () { map.scrollWheelZoom.disable(); });
        done();
      },
      draw: function () {
        if (!map) return;
        group.clearLayers();
        var pts = visibleStores().map(function (t) { return { t: t, p: map.latLngToLayerPoint([t.lat, t.lng]), used: false }; });
        pts.forEach(function (p) {
          if (p.used) return;
          var mem = [p]; p.used = true;
          pts.forEach(function (q) { if (!q.used && p.p.distanceTo(q.p) < 36) { q.used = true; mem.push(q); } });
          if (mem.length === 1) {
            var t = p.t, m = L.marker([t.lat, t.lng], { icon: DLP.pinIcon({ tipo: 'tienda', color: t.id === st.sel ? (C.color || '#ED1C24') : '#111111' }), title: t.n, keyboard: true });
            m.on('click', function () { select(t.id, true); });
            m.addTo(group);
          } else {
            var lat = 0, lng = 0; mem.forEach(function (x) { lat += x.t.lat; lng += x.t.lng; });
            var cl = L.marker([lat / mem.length, lng / mem.length], { icon: L.divIcon({ className: '', html: '<div class="dlprs-cl">' + mem.length + '</div>', iconSize: [36, 36], iconAnchor: [18, 18] }), title: mem.length + ' restaurantes' });
            cl.on('click', function () { map.fitBounds(L.latLngBounds(mem.map(function (x) { return [x.t.lat, x.t.lng]; })), { padding: [60, 60], maxZoom: 16 }); });
            cl.addTo(group);
          }
        });
      },
      fitAll: function () {
        var v = visibleStores(); if (!v.length) return;
        map.fitBounds(L.latLngBounds(v.map(function (t) { return [t.lat, t.lng]; })), { padding: [40, 40], maxZoom: 14 });
      },
      focus: function (t, box) {
        map.setView([t.lat, t.lng], Math.max(map.getZoom(), 15), { animate: true });
        L.popup({ offset: [0, -48], closeButton: false, autoPanPadding: [30, 30] }).setLatLng([t.lat, t.lng]).setContent(box).openOn(map);
      },
      fitNear: function (lat, lng, near) {
        var b = L.latLngBounds([[lat, lng]]); near.forEach(function (t) { b.extend([t.lat, t.lng]); });
        map.fitBounds(b, { padding: [60, 60], maxZoom: 15 });
      },
      setMe: function (lat, lng) {
        if (!me) {
          me = L.marker([lat, lng], { draggable: true, icon: L.divIcon({ className: '', html: '<div class="dlprs-me"></div>', iconSize: [18, 18], iconAnchor: [9, 9] }), zIndexOffset: 1000, title: 'Tu punto' }).addTo(map);
          me.on('dragend', function () { var p = me.getLatLng(); setPoint(p.lat, p.lng, 'Punto elegido'); });
        } else me.setLatLng([lat, lng]);
      },
      clearMe: function () { if (me) { map.removeLayer(me); me = null; } },
      closePopup: function () { return false; }, // Leaflet ya cierra la burbuja con el toque; ver closedAt
      resize: function () { if (map) map.invalidateSize(); },
      full: function (on) { if (L.Browser.mobile) { on ? map.dragging.enable() : map.dragging.disable(); } }
    };
    return a;
  }

  /* ---------- Carga perezosa y arranque ---------- */
  var mapEl = root.querySelector('.dlprs-map');
  function ready(adapter, style) {
    M = adapter;
    adapter.init(style, function () { apply(); adapter.fitAll(); });
  }
  // Las librerías, el estilo y la decisión 3D/plano vienen de dlp-tiendas (assets/js/map-3d.js).
  function conLeaflet() {
    return DLP.cargarMapa(C, 'leaflet').then(function () { ready(adapterLeaflet(), null); });
  }
  function conGL() {
    return DLP.cargarMapa(C, 'gl').then(function () { return DLP.estiloGL(C); }).then(function (style) { ready(adapterGL(), style); });
  }
  function arranque() {
    if (!C.tilesUrl || !C.libs || !window.DLP || !DLP.cargarMapa) { say('No se pudo cargar el mapa.'); return; }
    var go = DLP.motorMapa() === 'gl' ? conGL : conLeaflet;
    go().catch(function () {
      // El mapa 3D falló: se deja el mapa plano.
      M = null;
      mapEl.querySelectorAll('.maplibregl-map').forEach(function (n) { n.remove(); });
      if (go === conGL) conLeaflet().catch(function () { say('No se pudo cargar el mapa.'); });
      else say('No se pudo cargar el mapa.');
    });
  }
  function cuandoSeVea() {
    var el = document.getElementById('dlprs-map'), hecho = false;
    function ir() { if (hecho) return; hecho = true; arranque(); }
    if (!('IntersectionObserver' in window)) return ir();
    new IntersectionObserver(function (en, ob) { if (en[0].isIntersecting) { ob.disconnect(); ir(); } }, { rootMargin: '300px' }).observe(el);
  }
  if (document.readyState === 'complete') cuandoSeVea(); else window.addEventListener('load', cuandoSeVea);

  /* ---------- Pantalla completa (celular) ---------- */
  var fs = root.querySelector('.dlprs-fs');
  function full(on) {
    mapEl.classList.toggle('full', on);
    document.documentElement.classList.toggle('dlprs-lock', on);
    fs.setAttribute('aria-pressed', on ? 'true' : 'false');
    fs.setAttribute('aria-label', on ? 'Cerrar pantalla completa' : 'Pantalla completa');
    fs.querySelector('span').textContent = on ? 'Cerrar' : '';
    setTimeout(function () { if (M) { M.resize(); M.full(on); } }, 60);
  }
  fs.addEventListener('click', function () { full(!mapEl.classList.contains('full')); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && mapEl.classList.contains('full')) full(false); });

  apply();
})();
