// Arnés de regresión visual y de uniformidad. Recorre las plantillas con datos ficticios, saca capturas a 375 y 1280 px,
// mide estilos calculados en el navegador y compara píxel a píxel contra una línea base.
//
//   node regresion.mjs --update                   guarda la línea base en capturas/linea-base
//   node regresion.mjs                            compara contra la línea base (sale con 1 si hay diferencias)
//   node regresion.mjs --solo ficha-oferta,carta  limita las rutas
//   node regresion.mjs --inyectar "h1{color:red}" autoprueba: introduce una diferencia a propósito
//
// Variables: BASE (por defecto http://localhost:8888), UMBRAL (% de píxeles distintos tolerado, por defecto 0.05).
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { PNG } from 'pngjs';
import pixelmatch from 'pixelmatch';

const args = process.argv.slice(2);
const flag = (n) => args.includes(`--${n}`);
const val = (n, d = '') => {
  const i = args.indexOf(`--${n}`);
  return i >= 0 && args[i + 1] ? args[i + 1] : d;
};
const BASE = process.env.BASE || 'http://localhost:8888';
const UMBRAL = Number(process.env.UMBRAL || 0.05);
const ANCHOS = [375, 1280];
const raiz = val('salida', 'capturas');
const dirBase = path.join(raiz, 'linea-base');
const dirCorrida = path.join(raiz, 'corrida');
const actualizar = flag('update');
const destino = actualizar ? dirBase : dirCorrida;
fs.rmSync(destino, { recursive: true, force: true });
fs.mkdirSync(destino, { recursive: true });

const respuesta = await fetch(`${BASE}/wp-content/uploads/flacso-rutas.json`);
if (!respuesta.ok) {
  console.error('No se pudo leer flacso-rutas.json: ¿corriste seed.php?');
  process.exit(2);
}
let rutas = await respuesta.json();
const solo = val('solo');
if (solo) {
  const permitidas = new Set(solo.split(','));
  rutas = Object.fromEntries(Object.entries(rutas).filter(([k]) => permitidas.has(k)));
}
const inyectar = val('inyectar');

const browser = await chromium.launch({ channel: 'chrome' });
const informe = { base: BASE, fecha: new Date().toISOString(), umbralPct: UMBRAL, paginas: [] };

// Se ejecuta dentro de la página: mide estilos calculados, no el CSS escrito.
const medir = () => {
  const px = (s) => parseFloat(s) || 0;
  const visible = (el) => {
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    return r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && cs.display !== 'none';
  };
  const main = document.querySelector('main') || document.querySelector('#inner-wrap') || document.body;
  const h1 = [...document.querySelectorAll('h1')].find(visible);
  const h1cs = h1 ? getComputedStyle(h1) : null;
  const cuerpo = getComputedStyle(document.body);
  const boton = [...document.querySelectorAll('main a, main button')].find((el) => visible(el) && /button|btn|cta/i.test(el.className));
  const bcs = boton ? getComputedStyle(boton) : null;
  const enlace = [...main.querySelectorAll('p a, li a')].find(visible);
  const maxAnchos = [...main.children].map((c) => getComputedStyle(c).maxWidth).filter((v) => v !== 'none');
  const hijos = [...main.children].filter(visible).slice(0, 12);
  const ancho = Math.round(Math.max(0, ...hijos.map((c) => {
    const cs = getComputedStyle(c);
    return c.getBoundingClientRect().width - px(cs.paddingLeft) - px(cs.paddingRight);
  })));
  return {
    mains: document.querySelectorAll('main, [role=main]').length,
    innerWrap: document.querySelectorAll('#inner-wrap').length,
    overflowX: document.documentElement.scrollWidth > document.documentElement.clientWidth,
    h1: h1cs ? {
      texto: h1.textContent.trim().slice(0, 50),
      familia: h1cs.fontFamily.split(',')[0].replace(/["']/g, ''),
      tamanio: h1cs.fontSize,
      peso: h1cs.fontWeight,
      interlineado: h1cs.lineHeight,
    } : null,
    cuerpo: {
      familia: cuerpo.fontFamily.split(',')[0].replace(/["']/g, ''),
      tamanio: cuerpo.fontSize,
      interlineado: cuerpo.lineHeight,
      color: cuerpo.color,
    },
    contenedor: { maxWidthsHijos: [...new Set(maxAnchos)].slice(0, 5), anchoContenidoMax: ancho },
    boton: bcs ? {
      clase: boton.className.toString().slice(0, 60),
      fondo: bcs.backgroundColor,
      color: bcs.color,
      radio: bcs.borderTopLeftRadius,
      alto: Math.round(boton.getBoundingClientRect().height),
      peso: bcs.fontWeight,
    } : null,
    enlace: enlace ? { color: getComputedStyle(enlace).color, subrayado: getComputedStyle(enlace).textDecorationLine } : null,
  };
};

for (const [nombre, url] of Object.entries(rutas)) {
  for (const ancho of ANCHOS) {
    const ctx = await browser.newContext({ viewport: { width: ancho, height: 900 }, reducedMotion: 'reduce' });
    const page = await ctx.newPage();
    const fila = { nombre, ancho, url };
    try {
      const resp = await page.goto(url, { waitUntil: 'load', timeout: 240000 });
      fila.estado = resp ? resp.status() : 0;
      if (inyectar) await page.addStyleTag({ content: inyectar });
      await page.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important;caret-color:transparent!important}' });
      await page.waitForTimeout(700);
      fila.metricas = await page.evaluate(medir);
      const archivo = `${nombre}-${ancho}.png`;
      await page.screenshot({ path: path.join(destino, archivo), fullPage: true, animations: 'disabled' });
      fila.captura = archivo;
    } catch (e) {
      fila.error = String(e.message || e).split('\n')[0];
    }
    informe.paginas.push(fila);
    await ctx.close();
  }
}
await browser.close();

// Comparación contra la línea base.
let distintas = 0;
if (!actualizar) {
  for (const f of informe.paginas) {
    if (!f.captura) { f.diff = { estado: 'sin captura' }; distintas++; continue; }
    const ref = path.join(dirBase, f.captura);
    if (!fs.existsSync(ref)) { f.diff = { estado: 'sin línea base' }; continue; }
    const a = PNG.sync.read(fs.readFileSync(ref));
    const b = PNG.sync.read(fs.readFileSync(path.join(destino, f.captura)));
    if (a.width !== b.width || a.height !== b.height) {
      f.diff = { estado: 'tamaño distinto', antes: `${a.width}x${a.height}`, ahora: `${b.width}x${b.height}` };
      distintas++;
      continue;
    }
    const dif = new PNG({ width: a.width, height: a.height });
    const n = pixelmatch(a.data, b.data, dif.data, a.width, a.height, { threshold: 0.1 });
    const pct = (n / (a.width * a.height)) * 100;
    f.diff = { pixeles: n, porcentaje: Number(pct.toFixed(3)), estado: pct > UMBRAL ? 'DIFERENTE' : 'igual' };
    if (pct > UMBRAL) {
      distintas++;
      fs.writeFileSync(path.join(destino, f.captura.replace('.png', '-diff.png')), PNG.sync.write(dif));
    }
  }
}

fs.writeFileSync(path.join(destino, 'informe.json'), JSON.stringify(informe, null, 1));
const errores = informe.paginas.filter((f) => f.error || (f.estado && f.estado >= 500));
console.log(`${actualizar ? 'Línea base guardada en' : 'Corrida en'} ${destino}: ${informe.paginas.length} capturas, ${errores.length} con error.`);
for (const f of errores) console.log(`  ERROR ${f.nombre}@${f.ancho}: ${f.error || 'HTTP ' + f.estado}`);
if (!actualizar) {
  for (const f of informe.paginas.filter((x) => x.diff && x.diff.estado !== 'igual')) {
    console.log(`  ${f.diff.estado.padEnd(14)} ${f.nombre}@${f.ancho}${f.diff.porcentaje !== undefined ? ' ' + f.diff.porcentaje + '%' : ''}`);
  }
  console.log(distintas ? `${distintas} diferencia(s) sobre el umbral de ${UMBRAL}%.` : 'Sin diferencias sobre el umbral.');
}
process.exit(errores.length || distintas ? 1 : 0);
