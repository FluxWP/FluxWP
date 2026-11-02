// node gif.js <config.json> <lang>  -> $WPENV/vid/<slug>-<lang>/*.webm
const {chromium,B}=require(__dirname+'/lib.js'); const fs=require('fs'); const {execSync}=require('child_process');
const cfg=JSON.parse(fs.readFileSync(process.argv[2])); const lang=process.argv[3]; const L=(v)=>typeof v==='object'&&v&&!Array.isArray(v)?v[lang]:v;
execSync(`php ${__dirname}/env/setloc.php ${lang==='en'?'en_US':'tr_TR'}`);
const CURSOR=fs.readFileSync(__dirname+'/cursor.js','utf8');
(async()=>{ const b=await chromium.launch(); const W=cfg.gifW||1280,H=cfg.gifH||760;
 const {login}=require(__dirname+'/lib.js'); const {c:lc}=await login(b,{viewport:{width:W,height:H}}); const st=await lc.storageState(); await lc.close();
 const dir=`${require(__dirname+'/lib.js').W}/vid/${cfg.slug}-${lang}`; fs.rmSync(dir,{recursive:true,force:true});
 const c=await b.newContext({viewport:{width:W,height:H},storageState:st,recordVideo:{dir,size:{width:W,height:H}}}); await c.addInitScript(CURSOR); await c.addInitScript(css=>{ const add=()=>{ const st=document.createElement('style'); st.textContent=css; document.head.appendChild(st); }; if(document.head) add(); else document.addEventListener('DOMContentLoaded',add); },'#wpfooter{display:none}'+(cfg.css||''));
 const p=await c.newPage(); p.setDefaultTimeout(8000);
 const css=async()=>{ try{ await p.addStyleTag({content:'#wpfooter{display:none}'+(cfg.css||'')}); }catch(e){} };
 const move=async(x,y,steps=26)=>p.mouse.move(x,y,{steps});
 const clickLoc=async(loc,pause)=>{ await loc.scrollIntoViewIfNeeded(); let bb=await loc.boundingBox(); if(bb.y<(cfg.stickyTop||120)||bb.y>H-60){ await p.evaluate(d=>window.scrollBy({top:d,behavior:'smooth'}),bb.y-H/2.5); await p.waitForTimeout(700); bb=await loc.boundingBox(); }
   await move(bb.x+Math.min(bb.width/2,70),bb.y+bb.height/2); await p.waitForTimeout(250); await p.mouse.down(); await p.mouse.up(); await p.waitForTimeout(pause??900); };
 for(const s of cfg.gif){
  try{
  if(s.goto){ await p.goto(B+(s.goto.startsWith('/')?s.goto:'/wp-admin/'+s.goto)); await p.waitForTimeout(s.wait||2000); await css(); }
  if(s.move) { await move(s.move[0],s.move[1]); await p.waitForTimeout(s.wait||500); }
  if(s.click){ const scope=s.in?p.locator(s.in):p; const loc=s.css?scope.locator(L(s.click)):scope.getByText(L(s.click),{exact:!!s.exact}); await clickLoc(loc.locator('visible=true').first(),s.wait); if(s.nav){ await p.waitForLoadState('networkidle'); await p.waitForTimeout(1500); await css(); } }
  if(s.type){ await p.keyboard.type(L(s.type),{delay:60}); await p.waitForTimeout(s.wait||600); }
  if(s.key){ await p.keyboard.press(s.key); await p.waitForTimeout(s.wait||600); }
  if(s.scroll!==undefined){ const y=typeof s.scroll==='string'? await p.evaluate(q=>{const e=document.querySelector(q); return e? e.getBoundingClientRect().top+scrollY-140:0;},s.scroll): s.scroll; await p.evaluate(y=>window.scrollTo({top:y,behavior:'smooth'}),y); await p.waitForTimeout(s.wait||1300); }
  if(s.pause) await p.waitForTimeout(s.pause);
  }catch(e){ console.log('STEP FAIL',JSON.stringify(s),String(e).slice(0,150)); }
 }
 await p.waitForTimeout(800); const vp=await p.video().path(); await c.close(); await b.close(); console.log(vp); })();
