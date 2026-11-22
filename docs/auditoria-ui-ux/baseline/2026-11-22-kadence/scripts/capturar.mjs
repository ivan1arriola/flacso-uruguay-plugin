import { chromium } from 'playwright';
import fs from 'node:fs';
import paths from 'node:path';
const outputParent = process.argv[2];
if (!outputParent) throw new Error('Indic? el directorio donde guardar una corrida nueva.');
fs.mkdirSync(outputParent, { recursive: true });
const output = fs.mkdtempSync(paths.join(paths.resolve(outputParent), 'kadence-'));
console.log('Evidencias:', output);

const browser = await chromium.launch({channel:'chrome',headless:true});
const origin=new URL(process.env.FLACSO_AUDIT_BASE_URL ?? 'https://flacso.edu.uy').origin;
const visits=[
 ['01-portada','/'],
 ['02-formacion','/formacion/'],
 ['03-oferta','/formacion/maestrias/maestria-en-genero/'],
 ['04-seminarios','/formacion/seminarios/'],
 ['05-seminario','/formacion/seminarios/politicas-e-identidades-docentes/'],
 ['06-faq','/preguntas-frecuentes/'],
 ['07-contacto','/contactos/'],
];
const records=[];
for (const width of [375,1280]) {
 const context=await browser.newContext({userAgent:'FLACSO-UX-audit-readonly',viewport:{width,height:width===375?870:940},reducedMotion:'reduce',serviceWorkers:'block'});
 try {
 await context.route('**/*', route=>{
   const request=route.request();
   const url=new URL(request.url());
   if (url.protocol==='data:' || url.protocol==='blob:') return route.continue();
   if (url.origin!==origin || !['GET','HEAD'].includes(request.method()) || /admin-post|admin-ajax|wp-login|analytics|recaptcha/i.test(url.pathname)) return route.abort();
   return route.continue();
 });
 const page=await context.newPage();
 for (const [slug, path] of visits) {
  const record={slug,width};
  try {
    const response=await page.goto(origin+path,{waitUntil:'domcontentloaded',timeout:22000});
    record.status=response?.status();
    record.url=page.url();
    if (record.status!==200) throw new Error('HTTP '+record.status);
    await page.locator('h1').first().waitFor({state:'visible',timeout:8500});
    await page.waitForLoadState('load',{timeout:14000}).catch(()=>{});
    await page.waitForTimeout(850);
    record.data=await page.evaluate(()=>{
      const root=getComputedStyle(document.documentElement);
      const pick=(selector)=>[...document.querySelectorAll(selector)].filter(el=>el.getClientRects().length).slice(0,12).map(el=>{
       const s=getComputedStyle(el);
       return {text:el.innerText.trim().slice(0,115),cls:el.className,fg:s.color,bg:s.backgroundColor,sz:s.fontSize,family:s.fontFamily,radius:s.borderRadius,href:el.tagName==='A'?el.getAttribute('href'):undefined};
      });
      return {title:document.title,body:document.body.className,palette:Array.from({length:9},(_,i)=>root.getPropertyValue('--global-palette'+(i+1)).trim()),contentWidth:root.getPropertyValue('--global-content-width').trim(),overflow:document.documentElement.scrollWidth>innerWidth,headings:pick('main h1,main h2,#primary h1,#primary h2'),cta:pick('.flacso-navbar__cta,.hero-cta,main [data-flacso-preinscription-cta],main .btn,main [class*=cta],main summary'),assets:[...document.querySelectorAll('link[rel=stylesheet]')].map(x=>x.href).filter(h=>h.includes('kadence-child-flacso')).map(h=>h.split('kadence-child-flacso/')[1])};
    });
    record.shot=slug+'-'+width+'.png';
    await page.screenshot({path:paths.join(output, record.shot),animations:'disabled',timeout:14000});
  } catch(error) { record.error=String(error); delete record.shot; }
  records.push(record);
  fs.writeFileSync(paths.join(output, 'captura.json'),JSON.stringify(records,null,2));
  console.log(slug,width,record.status,record.data?.title,record.error||'');
  await page.waitForTimeout(700);
 }
 } finally { await context.close(); }
}
await browser.close();
