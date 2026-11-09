const { chromium } = require('playwright'); const fs=require('fs');
const B=process.env.MEDIA_BASE_URL||'http://127.0.0.1:8080'; const W=process.env.WPENV||'/tmp/wpenv'; const SF=W+'/state.json';
async function context(b,opts){
 const c=await b.newContext({locale:process.env.MEDIA_CAPTURE_LOCALE?process.env.MEDIA_CAPTURE_LOCALE.replace('_','-'):'en-US',...opts});
 if(process.env.MEDIA_CAPTURE_LOCALE)await c.addCookies([{name:'capture_locale',value:process.env.MEDIA_CAPTURE_LOCALE,url:B}]);
 const proxy=process.env.MEDIA_UPDATER_PROXY?JSON.parse(process.env.MEDIA_UPDATER_PROXY):null;
 await c.route('**/*',async route=>{
  const url=new URL(route.request().url());
  if(proxy&&url.origin===proxy.origin&&url.pathname.startsWith('/v1/media/')){
   const target=new URL(proxy.target);if(!['127.0.0.1','localhost','[::1]'].includes(target.hostname))throw Error('Capture proxy must stay local');
   try { const response=await route.fetch({url:proxy.target+url.pathname+url.search,maxRedirects:0,maxRetries:2});await route.fulfill({response}); } catch(error) { if(!/Request context disposed|Target page, context or browser has been closed/.test(error.message))throw error; } return;
  }
  if(['127.0.0.1','localhost','[::1]'].includes(url.hostname)||['data:','blob:','file:'].includes(url.protocol)){await route.continue();return;}
  await route.abort('blockedbyclient');
 });return c;
}

async function login(b,opts={}){
 if(fs.existsSync(SF)){ const c=await context(b,{viewport:{width:1440,height:900},deviceScaleFactor:2,storageState:SF,...opts}); const p=await c.newPage(); await p.goto(B+'/wp-admin/profile.php'); if(!p.url().includes('wp-login')) return {c,p}; await c.close(); }
 const c=await context(b,{viewport:{width:1440,height:900},deviceScaleFactor:2,...opts}); const p=await c.newPage();
 await p.goto(B+'/wp-login.php'); await p.fill('#user_login','admin'); await p.fill('#user_pass','admin12345'); await p.click('#wp-submit'); await p.waitForLoadState('networkidle'); await c.storageState({path:SF}); return {c,p}; }
// cfg adreslerinde {post:Başlık} -> o yazının kimliği (seed sonrası kimlikler değişebilir)
function php(args){ return require('child_process').execFileSync(process.env.MEDIA_PHP||'php',args,{env:process.env}).toString(); }
function U(u){ return u.replace(/\{post:([^}]+)\}/g,(m,t)=>php(['-r',`require ${JSON.stringify(W+'/site/wp-load.php')}; $posts=get_posts(['post_type'=>array_keys(get_post_types()),'title'=>base64_decode('${Buffer.from(t).toString('base64')}'),'numberposts'=>1]); echo $posts?$posts[0]->ID:0;`]).trim()); }

module.exports={chromium,B,login,W,U,php,context};
