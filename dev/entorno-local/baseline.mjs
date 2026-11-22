import { chromium } from 'playwright';
import fs from 'node:fs';
const base = process.env.BASE || 'http://localhost:8888';
const ficha = '/formacion/maestrias/maestria-en-genero-y-politicas-publicas-ficticia/';
const rutas = { home: '/', formacion: '/formacion/', ficha, carta: ficha + 'carta/', contacto: '/contactos/' };
const anchos = [320, 375, 768, 1024, 1280];
const out = process.argv[2] || 'corrida';
fs.mkdirSync(out, { recursive: true });
const browser = await chromium.launch({ channel: 'chrome' });
const informe = { base, fecha: new Date().toISOString(), paginas: [] };
for (const [nombre, ruta] of Object.entries(rutas)) {
  for (const w of anchos) {
    const ctx = await browser.newContext({ viewport: { width: w, height: 900 } });
    const page = await ctx.newPage();
    const resp = await page.goto(base + ruta, { waitUntil: 'networkidle' });
    const m = await page.evaluate(() => ({
      overflowX: document.documentElement.scrollWidth > document.documentElement.clientWidth,
      innerWrap: document.querySelectorAll('#inner-wrap').length,
      robots: [...document.querySelectorAll('meta[name=robots]')].map(e => e.content),
    }));
    await page.screenshot({ path: `${out}/${nombre}-${w}.png`, fullPage: false });
    informe.paginas.push({ nombre, ruta, ancho: w, estado: resp.status(), ...m });
    await ctx.close();
  }
}
// Foco del buscador con teclado a 375 px
const ctx = await browser.newContext({ viewport: { width: 375, height: 800 } });
const page = await ctx.newPage();
await page.goto(base + '/', { waitUntil: 'networkidle' });
const toggle = page.locator('[data-flacso-search-toggle]').first();
let foco = { toggleVisible: await toggle.isVisible().catch(() => false) };
if (foco.toggleVisible) {
  await toggle.focus(); await page.keyboard.press('Enter');
  await page.locator('#flacso-header-search-field').focus();
  foco.outline = await page.evaluate(() => { const s = getComputedStyle(document.querySelector('#flacso-header-search-field')); return { style: s.outlineStyle, width: s.outlineWidth, color: s.outlineColor }; });
  await page.screenshot({ path: `${out}/buscador-foco-375.png` });
}
informe.focoBuscador = foco;
await browser.close();
fs.writeFileSync(`${out}/informe.json`, JSON.stringify(informe, null, 1));
console.log(JSON.stringify(informe.focoBuscador));
console.log(informe.paginas.filter(p => p.estado !== 200 || p.overflowX || p.innerWrap > 1).map(p => `${p.nombre}@${p.ancho} estado=${p.estado} overflowX=${p.overflowX} innerWrap=${p.innerWrap}`).join('\n') || 'sin incidencias de estado, desborde ni inner-wrap');
