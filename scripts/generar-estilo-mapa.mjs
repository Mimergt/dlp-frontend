// Genera modules/restaurantes/mapa/style.json (estilo MapLibre con edificios en 3D) a partir de la paleta de
// dlp-tiendas (assets/js/map-style.js → DLP.PALETA) y de @protomaps/basemaps.
// Uso (desde una carpeta temporal con `npm i @protomaps/basemaps`):
//   node generar-estilo-mapa.mjs <ruta/map-style.js> <ruta/salida/style.json>
import { layers } from '@protomaps/basemaps';
import { readFileSync, writeFileSync } from 'node:fs';
import vm from 'node:vm';

const [, , palFile, outFile] = process.argv;
const ctx = { window: {} };
vm.createContext(ctx);
vm.runInContext(readFileSync(palFile, 'utf8'), ctx);
const flavor = ctx.window.DLP.PALETA;

let ls = layers('protomaps', flavor, { lang: 'es' })
  .filter((l) => !(l.layout && l.layout['icon-image']) && l.id !== 'address_label'); // sin íconos: no hay sprites

ls = ls.map((l) => {
  if (l.id !== 'buildings') return l;
  return {
    id: 'buildings',
    type: 'fill-extrusion',
    source: 'protomaps',
    'source-layer': 'buildings',
    minzoom: 14,
    filter: ['in', 'kind', 'building', 'building_part'],
    paint: {
      'fill-extrusion-color': '#ece9e4',
      'fill-extrusion-height': ['to-number', ['get', 'height'], 9],
      'fill-extrusion-base': ['to-number', ['get', 'min_height'], 0],
      'fill-extrusion-opacity': 0.95,
      'fill-extrusion-vertical-gradient': true,
    },
  };
});

const style = {
  version: 8,
  name: 'Del Puente 3D',
  glyphs: '__GLYPHS__',
  light: { anchor: 'viewport', color: '#ffffff', intensity: 0.4, position: [1.4, 215, 40] },
  sources: { protomaps: { type: 'vector', url: '__TILES__' } },
  layers: ls,
};
writeFileSync(outFile, JSON.stringify(style));
console.log(`${ls.length} capas → ${outFile}`);
