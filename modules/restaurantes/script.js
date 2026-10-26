/* Restaurantes: filtros, búsqueda, «Cerca de mí» y mapa 3D (MapLibre GL + PMTiles, mismos tiles de dlp-tiendas). */
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
  var map = null, mks = [], me = null, pop = null;
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
    drawMarkers();
  }

  /* ---------- Mapa (MapLibre: [lng, lat]) ---------- */
  var SEL_ZOOM = 15.5, TILT = 55;
  function visibleStores() {
    return cards.filter(function (c) { return !c.hidden; }).map(function (c) { return data[c.getAttribute('data-id')]; }).filter(function (t) { return t && t.lat !== null; });
  }
  function pinEl(t, act) {
    var el = document.createElement('div');
    el.className = 'dlprs-pinbox'; el.title = t.n; el.setAttribute('role', 'button'); el.setAttribute('aria-label', t.n); el.tabIndex = 0;
    el.innerHTML = DLP.pinHtml({ tipo: 'tienda', color: act ? (C.color || '#ED1C24') : '#111111' });
    function go(e) { e.stopPropagation(); select(t.id, true); }
    el.addEventListener('click', go);
    el.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(e); } });
    return el;
  }
  function drawMarkers() {
    if (!map) return;
    mks.forEach(function (m) { m.remove(); }); mks = [];
    var pts = visibleStores().map(function (t) { return { t: t, p: map.project([t.lng, t.lat]), used: false }; });
    pts.forEach(function (a) {
      if (a.used) return;
      var mem = [a]; a.used = true;
      pts.forEach(function (b) {
        if (!b.used && Math.hypot(a.p.x - b.p.x, a.p.y - b.p.y) < 36) { b.used = true; mem.push(b); }
      });
      if (mem.length === 1) {
        mks.push(new maplibregl.Marker({ element: pinEl(a.t, a.t.id === st.sel), anchor: 'bottom' }).setLngLat([a.t.lng, a.t.lat]).addTo(map));
      } else {
        var lat = 0, lng = 0; mem.forEach(function (x) { lat += x.t.lat; lng += x.t.lng; });
        var el = document.createElement('div');
        el.className = 'dlprs-cl'; el.textContent = mem.length; el.title = mem.length + ' restaurantes'; el.setAttribute('role', 'button');
        el.addEventListener('click', function (e) {
          e.stopPropagation();
          var b = new maplibregl.LngLatBounds(); mem.forEach(function (x) { b.extend([x.t.lng, x.t.lat]); });
          map.fitBounds(b, { padding: 70, maxZoom: 15 });
        });
        mks.push(new maplibregl.Marker({ element: el, anchor: 'center' }).setLngLat([lng / mem.length, lat / mem.length]).addTo(map));
      }
    });
  }
  function fitAll() {
    var v = visibleStores();
    if (!map || !v.length) return;
    var b = new maplibregl.LngLatBounds(); v.forEach(function (t) { b.extend([t.lng, t.lat]); });
    map.fitBounds(b, { padding: 50, maxZoom: 13, pitch: 0, bearing: 0 });
  }
  function select(id, fromMap) {
    st.sel = id;
    cards.forEach(function (c) { c.classList.toggle('act', +c.getAttribute('data-id') === id); });
    var c = byId[id], t = data[id];
    if (c && fromMap) {
      list.scrollTop = c.offsetTop - list.offsetTop - 8;
      if (window.innerWidth <= 900) c.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
    if (map && t && t.lat !== null) {
      map.easeTo({ center: [t.lng, t.lat], zoom: Math.max(map.getZoom(), SEL_ZOOM), pitch: TILT, duration: 900 });
      var box = document.createElement('div'); box.className = 'dlprs-pop';
      var b = document.createElement('b'); b.textContent = t.n; box.appendChild(b);
      var ico = c && c.querySelector('.dlprs-ico'); if (ico) box.appendChild(ico.cloneNode(true));
      if (pop) pop.remove();
      pop = new maplibregl.Popup({ offset: [0, -50], closeButton: false, closeOnClick: false, maxWidth: 'none', className: 'dlprs-popup' }).setLngLat([t.lng, t.lat]).setDOMContent(box).addTo(map);
    }
    drawMarkers();
  }

  /* ---------- Punto del cliente ---------- */
  function setPoint(lat, lng, label) {
    st.pt = [lat, lng]; st.label = label;
    if (map) {
      if (!me) {
        var el = document.createElement('div'); el.className = 'dlprs-me'; el.title = 'Tu punto';
        me = new maplibregl.Marker({ element: el, draggable: true, anchor: 'center' }).setLngLat([lng, lat]).addTo(map);
        me.on('dragend', function () { var p = me.getLngLat(); setPoint(p.lat, p.lng, 'Punto elegido'); });
      } else me.setLngLat([lng, lat]);
    }
    say('');
    apply();
    var near = [].slice.call(list.children).filter(function (c) { return !c.hidden && c._d !== null; }).slice(0, 3);
    if (map && near.length) {
      if (near[0]._d > 300) { say('Estás lejos de nuestros restaurantes. Te mostramos el más cercano.'); }
      var b = new maplibregl.LngLatBounds([lng, lat], [lng, lat]);
      near.forEach(function (c) { var t = data[c.getAttribute('data-id')]; b.extend([t.lng, t.lat]); });
      map.fitBounds(b, { padding: 70, maxZoom: 14.5, pitch: 0, bearing: 0 });
      map.once('moveend', function () { if (map.getZoom() >= 13.5) map.easeTo({ pitch: 45, duration: 700 }); });
    }
    list.scrollTop = 0;
  }
  function clearPoint() {
    st.pt = null; if (me) { me.remove(); me = null; }
    apply(); fitAll();
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

  /* ---------- Arranque del mapa ---------- */
  function initMap(style) {
    style.glyphs = C.mapaUrl + 'fonts/{fontstack}/{range}.pbf';
    style.sources.protomaps.url = 'pmtiles://' + C.tilesUrl;
    var b = C.bounds; // [[lat, lng], [lat, lng]]
    try {
      maplibregl.addProtocol('pmtiles', new pmtiles.Protocol().tile);
      map = new maplibregl.Map({
        container: 'dlprs-map', style: style, center: [C.center[1], C.center[0]], zoom: 7.5,
        minZoom: Math.max(5, C.minZoom - 1.5), maxZoom: 18, maxPitch: 70, maxBounds: [[b[0][1], b[0][0]], [b[1][1], b[1][0]]],
        attributionControl: false, cooperativeGestures: true, pitchWithRotate: true, dragRotate: true,
        locale: {
          'CooperativeGesturesHandler.WindowsHelpText': 'Usa Ctrl + scroll para acercar el mapa',
          'CooperativeGesturesHandler.MacHelpText': 'Usa ⌘ + scroll para acercar el mapa',
          'CooperativeGesturesHandler.MobileHelpText': 'Usa dos dedos para mover el mapa',
          'NavigationControl.ZoomIn': 'Acercar', 'NavigationControl.ZoomOut': 'Alejar', 'NavigationControl.ResetBearing': 'Orientar al norte'
        }
      });
    } catch (e) { root.querySelector('.dlprs-map').innerHTML = '<p class="dlprs-empty">Tu dispositivo no pudo mostrar el mapa. La lista de restaurantes sigue disponible.</p>'; return; }
    map.addControl(new maplibregl.NavigationControl({ visualizePitch: true }), 'top-right');
    map.addControl(new maplibregl.AttributionControl({ customAttribution: DLP.ATTRIBUTION_CLARO }), 'bottom-right');
    map.on('load', function () { apply(); fitAll(); });
    map.on('zoomend', function () {
      if (map.getZoom() < 12 && (map.getPitch() > 1 || Math.abs(map.getBearing()) > 1)) map.easeTo({ pitch: 0, bearing: 0, duration: 500 });
      drawMarkers();
    });
    map.on('pitchend', drawMarkers);
    map.on('click', function (e) {
      if (e.originalEvent && e.originalEvent.target.closest && e.originalEvent.target.closest('.maplibregl-marker, .maplibregl-popup')) return;
      if (pop) { pop.remove(); pop = null; return; } // el primer toque solo cierra la burbuja
      setPoint(e.lngLat.lat, e.lngLat.lng, 'Punto elegido');
    });
  }
  (function wait(n) {
    if (window.maplibregl && window.pmtiles && window.DLP && DLP.pinHtml && C.tilesUrl) {
      fetch(C.mapaUrl + 'style.json?v=' + (C.ver || '1')).then(function (r) { return r.json(); }).then(initMap).catch(function () { say('No se pudo cargar el mapa.'); });
      return;
    }
    if (n > 160) { say('No se pudo cargar el mapa.'); return; }
    setTimeout(function () { wait(n + 1); }, 50);
  })(0);
  apply();
})();
