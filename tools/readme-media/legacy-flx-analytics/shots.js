const { chromium } = require('playwright');
const { execSync } = require('child_process');
const lang=process.argv[2]; const out=process.argv[3];
execSync(`php ${__dirname}/../env/setloc.php ${lang==='en'?'en_US':'tr_TR'}`);
const B='http://127.0.0.1:8080/wp-admin/';
async function login(c){const p=await c.newPage(); await p.goto('http://127.0.0.1:8080/wp-login.php'); await p.fill('#user_login','admin'); await p.fill('#user_pass','admin12345'); await p.click('#wp-submit'); await p.waitForLoadState('networkidle'); return p;}
async function union(p,sels,pad=12){ const bs=[]; for(const s of sels){ for(const l of await p.locator(s).all()){ if(!(await l.isVisible())) continue; const b=await l.boundingBox(); if(b&&b.height>10) bs.push(b);} } const x=Math.min(...bs.map(b=>b.x))-pad,y=Math.min(...bs.map(b=>b.y))-pad; const x2=Math.max(...bs.map(b=>b.x+b.width))+pad,y2=Math.max(...bs.map(b=>b.y+b.height))+pad; return {x,y,width:x2-x,height:y2-y}; }
async function go(p,u){ await p.goto(B+u); await p.waitForTimeout(4000); await p.addStyleTag({content:'#wpfooter{display:none}'}); }
const jpg=async(p,name,opt)=>{ await p.screenshot({path:`${out}/${name}-${lang}.jpg`,type:'jpeg',quality:85,...opt}); };
(async()=>{
 const b=await chromium.launch();
 const c=await b.newContext({viewport:{width:1440,height:900},deviceScaleFactor:2});
 const p=await login(c); const errs=[]; p.on('pageerror',e=>errs.push(String(e)));
 await go(p,'admin.php?page=flx-analytics');
 await jpg(p,'overview',{});
 await jpg(p,'map',{fullPage:true,clip:await union(p,['#flxan-p-map'])});
 await jpg(p,'funnel',{fullPage:true,clip:await union(p,['#flxan-p-funnel'])});
 await jpg(p,'referrals',{fullPage:true,clip:await union(p,['#flxan-p-codes','#flxan-p-channel','#flxan-p-roles'])});
 await jpg(p,'pages',{fullPage:true,clip:await union(p,['#flxan-p-pages','#flxan-p-refsites'])});
 await jpg(p,'events',{fullPage:true,clip:await union(p,['#flxan-p-events'])});
 await go(p,'admin.php?page=flx-analytics-journeys');
 await p.setViewportSize({width:1440,height:1040}); await p.waitForTimeout(800);
 await jpg(p,'journeys',{});
 await p.setViewportSize({width:1440,height:900});
 await go(p,'admin.php?page=flx-analytics-settings');
 const fsel=await p.evaluate(()=>{ const hs=[...document.querySelectorAll('h2,h3')].find(h=>/Funnels|Huniler/.test(h.textContent)); let e=hs; while(e && e.getBoundingClientRect().height<400) e=e.parentElement; e.id=e.id||'zz-fun'; return '#'+e.id; });
 await jpg(p,'funnel-editor',{fullPage:true,clip:await union(p,[fsel],0)});
 await go(p,'index.php');
 await jpg(p,'dashboard-widgets',{fullPage:true,clip:await union(p,['#dashboard-widgets .postbox'],8)});
 const m=await b.newContext({viewport:{width:390,height:844},deviceScaleFactor:2,isMobile:true,hasTouch:true});
 const mp=await login(m); await go(mp,'admin.php?page=flx-analytics'); await jpg(mp,'mobile',{clip:{x:0,y:0,width:390,height:625}});
 await go(mp,'admin.php?page=flx-analytics-journeys'); await jpg(mp,'mobile-journeys',{});
 console.log('errors:',errs.join('\n')); await b.close();
})();
