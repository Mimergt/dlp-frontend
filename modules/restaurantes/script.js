/* Restaurantes: filtros, búsqueda, «Cerca de mí» y mapa (Leaflet + Protomaps, los de dlp-tiendas). */
(function () {
  var root = document.getElementById('dlprs');
  if (!root) return;
  var C = window.dlpRs || {};
  var cards = [].slice.call(root.querySelectorAll('.dlprs-card'));
  var list = root.querySelector('.dlprs-items');
  var byId = {}, data = {};
  (C.tiendas || []).forEach(function (t) { data[t.id] = t; });
  cards.forEach(function (c, i) { c.setAttribute('data-i', i); byId[c.getAttribute('data-id')] = c; });

  var st = { srv: '', open: false, region: '', q: '', pt: null, label: '', picking: false, sel: 0 };
  var map = null, group = null, me = null;
  var bar = root.querySelector('.dlprs-bar-t'), barX = root.querySelector('.dlprs-bar-x');
  var empty = root.querySelector('.dlprs-empty'), msg = root.querySelector('.dlprs-msg');
  var pickBtn = root.querySelector('.dlprs-pickbtn'), nearBtn = root.querySelector('.dlprs-near');

  function norm(s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
  function km(a, b) {
    var R = 6371, r = Math.PI / 180, dLat = (b[0] - a[0]) * r, dLng = (b[1] - a[1]) * r;
    var x = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(a[0] * r) * Math.cos(b[0] * r) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
    return 2 * R * Math.asin(Math.sqrt(x));
  }
  function fmt(d) { return d < 10 ? d.toFixed(1) + ' km' : Math.round(d) + ' km'; }
  function say(t) { msg.textContent = t || ''; }

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
    if (firstVisible) firstVisible.classList.add('top');
    empty.hidden = vis.length > 0;
    if (st.pt) { bar.innerHTML = '<b>Ordenado por cercanía</b> · ' + st.label; barX.hidden = false; }
    else { bar.innerHTML = '<b>' + vis.length + (vis.length === 1 ? ' restaurante' : ' restaurantes') + '</b>'; barX.hidden = true; }
    drawMarkers();
  }

  /* ---------- Mapa ---------- */
  function visibleStores() {
    return cards.filter(function (c) { return !c.hidden; }).map(function (c) { return data[c.getAttribute('data-id')]; }).filter(function (t) { return t && t.lat !== null; });
  }
  function pinIcon(act) { return L.divIcon({ className: '', html: '<div class="dlprs-pin' + (act ? ' act' : '') + '"></div>', iconSize: [0, 0] }); }
  function drawMarkers() {
    if (!map) return;
    group.clearLayers();
    var pts = visibleStores().map(function (t) { return { t: t, p: map.latLngToLayerPoint([t.lat, t.lng]), used: false }; });
    pts.forEach(function (a) {
      if (a.used) return;
      var mem = [a]; a.used = true;
      pts.forEach(function (b) {
        if (!b.used && a.p.distanceTo(b.p) < 36) { b.used = true; mem.push(b); }
      });
      if (mem.length === 1) {
        var t = a.t, m = L.marker([t.lat, t.lng], { icon: pinIcon(t.id === st.sel), title: t.n, keyboard: true });
        m.on('click', function () { select(t.id, true); });
        m.addTo(group);
      } else {
        var lat = 0, lng = 0; mem.forEach(function (x) { lat += x.t.lat; lng += x.t.lng; });
        var cl = L.marker([lat / mem.length, lng / mem.length], { icon: L.divIcon({ className: '', html: '<div class="dlprs-cl">' + mem.length + '</div>', iconSize: [0, 0] }), title: mem.length + ' restaurantes' });
        cl.on('click', function () {
          map.fitBounds(L.latLngBounds(mem.map(function (x) { return [x.t.lat, x.t.lng]; })), { padding: [60, 60], maxZoom: 16 });
        });
        cl.addTo(group);
      }
    });
  }
  function fitAll() {
    var v = visibleStores();
    if (!map || !v.length) return;
    map.fitBounds(L.latLngBounds(v.map(function (t) { return [t.lat, t.lng]; })), { padding: [40, 40], maxZoom: 14 });
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
      if (!fromMap) map.setView([t.lat, t.lng], Math.max(map.getZoom(), 15), { animate: true });
      L.popup({ offset: [0, -8], closeButton: false }).setLatLng([t.lat, t.lng]).setContent('<b></b><span></span>').openOn(map);
      var el = map.getContainer().querySelector('.leaflet-popup-content');
      el.querySelector('b').textContent = t.n; el.querySelector('span').textContent = t.a;
    }
    drawMarkers();
  }

  /* ---------- Punto del cliente ---------- */
  function setPoint(lat, lng, label) {
    st.pt = [lat, lng]; st.label = label; st.picking = false;
    pickBtn.classList.remove('on'); pickBtn.textContent = 'Elegir un punto en el mapa'; root.querySelector('.dlprs-map').classList.remove('picking');
    if (map) {
      if (!me) {
        me = L.marker([lat, lng], { draggable: true, icon: L.divIcon({ className: '', html: '<div class="dlprs-me"></div>', iconSize: [0, 0] }), zIndexOffset: 1000, title: 'Tu punto' }).addTo(map);
        me.on('dragend', function () { var p = me.getLatLng(); setPoint(p.lat, p.lng, 'Punto elegido'); });
      } else me.setLatLng([lat, lng]);
    }
    say('');
    apply();
    var near = [].slice.call(list.children).filter(function (c) { return !c.hidden && c._d !== null; }).slice(0, 3);
    if (map && near.length) {
      if (near[0]._d > 300) { say('Estás lejos de nuestros restaurantes. Te mostramos el más cercano.'); }
      var b = L.latLngBounds([[lat, lng]]);
      near.forEach(function (c) { var t = data[c.getAttribute('data-id')]; b.extend([t.lat, t.lng]); });
      map.fitBounds(b, { padding: [60, 60], maxZoom: 15 });
    }
    list.scrollTop = 0;
  }
  function clearPoint() {
    st.pt = null; if (me && map) { map.removeLayer(me); me = null; }
    apply(); fitAll();
  }
  function startPick(on) {
    st.picking = on; pickBtn.classList.toggle('on', on);
    pickBtn.textContent = on ? 'Toca el mapa…' : 'Elegir un punto en el mapa';
    root.querySelector('.dlprs-map').classList.toggle('picking', on);
    if (on) say('Toca el lugar donde estás o a donde quieres pedir.');
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
  pickBtn.addEventListener('click', function () { startPick(!st.picking); });
  nearBtn.addEventListener('click', function () {
    if (!navigator.geolocation) { say('Tu navegador no permite obtener la ubicación. Elige un punto en el mapa.'); startPick(true); return; }
    nearBtn.classList.add('busy'); say('Buscando tu ubicación…');
    navigator.geolocation.getCurrentPosition(function (p) {
      nearBtn.classList.remove('busy'); setPoint(p.coords.latitude, p.coords.longitude, 'Tu ubicación');
    }, function () {
      nearBtn.classList.remove('busy'); say('No pudimos obtener tu ubicación. Elige un punto en el mapa.'); startPick(true);
    }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
  });

  /* ---------- Arranque del mapa ---------- */
  function initMap() {
    if (!C.tilesUrl) { root.querySelector('.dlprs-map').innerHTML = '<p class="dlprs-empty">No se pudo cargar el mapa.</p>'; return; }
    map = L.map('dlprs-map', { center: C.center, zoom: 8, minZoom: C.minZoom, maxZoom: C.maxZoom, maxBounds: C.bounds, maxBoundsViscosity: 1, zoomSnap: 0.5, scrollWheelZoom: false });
    try {
      protomapsL.leafletLayer({ url: C.tilesUrl, flavor: 'light', lang: 'es', attribution: '<a href="https://epic.gt" target="_blank" rel="noopener">EPIC.GT</a> · <a href="https://protomaps.com" target="_blank" rel="noopener">Protomaps</a> © <a href="https://openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>' }).addTo(map);
    } catch (e) { say('No se pudo cargar el mapa base.'); }
    group = L.layerGroup().addTo(map);
    map.on('zoomend', drawMarkers);
    map.on('click', function (e) { if (st.picking) setPoint(e.latlng.lat, e.latlng.lng, 'Punto elegido'); });
    map.on('focus', function () { map.scrollWheelZoom.enable(); });
    map.on('blur', function () { map.scrollWheelZoom.disable(); });
    apply(); fitAll();
  }
  (function wait(n) {
    if (window.L && window.protomapsL) return initMap();
    if (n > 160) { say('No se pudo cargar el mapa.'); return; }
    setTimeout(function () { wait(n + 1); }, 50);
  })(0);
  apply();
})();
