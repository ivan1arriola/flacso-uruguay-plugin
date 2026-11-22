import {chromium} from 'playwright';
import fs from 'node:fs';
import paths from 'node:path';
const root=new URL(process.env.FLACSO_AUDIT_BASE_URL ?? 'https://flacso.edu.uy').origin;
const outputParent = process.argv[2];
if (!outputParent) throw new Error('Indic? el directorio donde guardar una corrida nueva.');
fs.mkdirSync(outputParent, { recursive: true });
const output = fs.mkdtempSync(paths.join(paths.resolve(outputParent), 'kadence-'));
console.log('Evidencias:', output);

const browser=await chromium.launch({channel:'chrome',headless:true});
const context=await browser.newContext({userAgent:'FLACSO-UX-audit-readonly',viewport:{width:1280,height:940},serviceWorkers:'block',reducedMotion:'reduce'});
const page=await context.newPage();
await context.route('**/*',route=>{
 const request=route.request(), url=new URL(request.url());
 if (['data:','blob:'].includes(url.protocol)) return route.continue();
 if(url.origin!==root || !['GET','HEAD'].includes(request.method()) || /admin-post|admin-ajax|wp-login|analytics|recaptcha/i.test(url.pathname)) return route.abort();
 return route.continue();
});
const data=[];
async function open(path) {
 const res=await page.goto(root+path,{waitUntil:'domcontentloaded',timeout:24000});
 await page.waitForLoadState('load',{timeout:12000}).catch(()=>{});
 await page.locator('h1').first().waitFor({state:'visible',timeout:5000});
 return {status:res?.status(),url:page.url(),robots:res?.headers()['x-robots-tag']};
}
async function shot(name) { await page.screenshot({path:paths.join(output, name + '.png'),animations:'disabled'}); }
const intro=[
 ['portada','/',['.flacso-home h1','.hero-cta','.flacso-chrome-container','.flacso-home .container']],
 ['formacion','/formacion/',['main h1','main h2','main']],
 ['oferta','/formacion/maestrias/maestria-en-genero/',['main h1','.flacso-academic-hero','[data-flacso-preinscription-cta]']],
 ['seminario','/formacion/seminarios/politicas-e-identidades-docentes/',['main h1','[data-flacso-preinscription-cta]']],
 ['carta','/formacion/maestrias/maestria-en-genero/carta/',['main h1','article h1']],
];
for(const [name,path,selectors] of intro) {
 const rec={name};
 try {
  Object.assign(rec,await open(path));
  if (name==='carta' && rec.status===200) { await shot('08-carta-1280'); rec.shot='08-carta-1280.png'; }
  rec.measure=await page.evaluate(selectors=>{
   const root=document.documentElement;
   const measure=()=>selectors.map(selector=>{
    const el=document.querySelector(selector);
    if(!el) return {selector,missing:true};
    const cs=getComputedStyle(el);
    return {selector,family:cs.fontFamily,text:cs.color,bg:cs.backgroundColor,width:Math.round(el.getBoundingClientRect().width),padding:cs.padding,radius:cs.borderRadius};
   });
   const rootVars=Object.fromEntries(['--global-palette9rgb','--global-palette12','--global-palette14','--global-palette2','--global-content-width'].map(name=>[name,getComputedStyle(root).getPropertyValue(name).trim()]));
   const before=measure();
   const changes={'--global-palette1':'#641e4d','--global-palette2':'#ffa5ac','--global-heading-font-family':'Georgia, serif','--global-body-font-family':'Georgia, serif','--global-content-width':'920px','--global-palette-btn-bg':'#005f51','--global-palette-btn':'#ffffff'};
   if(document.body) for(const [key,value] of Object.entries(changes)) root.style.setProperty(key,value,'important');
   const after=measure();
   for(const key of Object.keys(changes)) root.style.removeProperty(key);
   return {rootVars,before,after,innerWrap:document.querySelectorAll('#inner-wrap').length,robots:[...document.querySelectorAll('meta[name=robots]')].map(x=>x.content)};
  },selectors);
 } catch(error) {rec.error=String(error);}
 data.push(rec); console.log(name,rec.status,rec.error||'');
 await page.waitForTimeout(700);
}
for(const [name, path, width] of [['carta-movil','/formacion/maestrias/maestria-en-genero/carta/',375],['buscador-movil','/',375]]) {
 const rec={name};
 try {
  await page.setViewportSize({width,height:870});
  Object.assign(rec,await open(path));
  if(name==='buscador-movil') {
   const button=page.locator('[data-flacso-search-toggle]');
   await button.focus();
   await button.press('Enter');
   rec.focus=await page.evaluate(()=>{
    const a=document.activeElement,s=getComputedStyle(a);
    return {id:a.id,outline:s.outline,border:s.borderColor,shadow:s.boxShadow};
   });
   const input=page.locator('#flacso-header-search-field');
   if(await input.isVisible()) { await shot('09-busqueda-teclado-375'); rec.shot='09-busqueda-teclado-375.png'; }
   await page.keyboard.press('Escape');
   rec.focusAfterEscape=await page.evaluate(()=>document.activeElement.matches('[data-flacso-search-toggle]'));
  } else if(rec.status===200) { await shot('08-carta-375'); rec.shot='08-carta-375.png'; }
 } catch(error) {rec.error=String(error);}
 data.push(rec); console.log(name,rec.status,rec.error||'');
 await page.waitForTimeout(700);
}
fs.writeFileSync(paths.join(output, 'sondeo.json'),JSON.stringify(data,null,2));
await browser.close();
