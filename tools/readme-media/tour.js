// Fresh, non-destructive browser walkthrough from the current screenshot recipe.
// Usage: bun tools/readme-media/tour.js cfg.json en|tr
const {chromium,B,login,U,W,php,context:makeContext}=require('./lib.js');
const fs=require('fs');const path=require('path');
const cfg=JSON.parse(fs.readFileSync(process.argv[2]));const lang=process.argv[3];
process.env.MEDIA_CAPTURE_LOCALE=lang==='en'?'en_US':'tr_TR';
(async()=>{const browser=await chromium.launch();try{
 const {c:auth}=await login(browser);const storageState=await auth.storageState();await auth.close();
 const dir=path.join(W,'vid',cfg.slug+'-'+lang);fs.mkdirSync(dir,{recursive:true});
 const context=await makeContext(browser,{locale:process.env.MEDIA_CAPTURE_LOCALE.replace("_","-"),viewport:{width:1280,height:800},storageState,recordVideo:{dir,size:{width:1280,height:800}}});
 await context.addCookies([{name:'capture_locale',value:process.env.MEDIA_CAPTURE_LOCALE,url:B}]);const page=await context.newPage();
 const seen=new Set();const scenes=(cfg.shots||[]).filter(s=>{const url=typeof s.url==='object'?s.url[lang]:s.url;if(seen.has(url)||url.includes('customize.php'))return false;seen.add(url);return true;}).slice(0,3);
 if(!scenes.length)throw Error('No current scenes configured');
 for(const scene of scenes){const target=typeof scene.url==='object'?scene.url[lang]:scene.url;const response=await page.goto(B+U(target.startsWith('/')?target:'/wp-admin/'+target),{waitUntil:'domcontentloaded'});if(response&&response.status()>=400&&!scene.allowError)throw Error('Walkthrough rejected: HTTP '+response.status());await page.waitForTimeout(1200);
  const text=await page.locator('body').innerText();if(/There has been a critical error|Fatal error:|You are not allowed to access this page|Sorry, you are not allowed|kritik bir hata|ciddi bir hata|Bu sayfaya erişmenize izin verilmiyor|Bu sayfaya erişim izniniz yok/i.test(text))throw Error('Walkthrough rejected: application error');
  await page.mouse.move(1020,250,{steps:12});await page.waitForTimeout(900);await page.evaluate(()=>window.scrollBy({top:280,behavior:'smooth'}));await page.waitForTimeout(1400);
 }
 const video=await page.video().path();await context.close();console.log(video);
 }finally{await browser.close();}})().catch(error=>{console.error(error);process.exitCode=1;});
