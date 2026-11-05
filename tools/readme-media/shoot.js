// node shoot.js <config.json> <lang> <outdir>
const {chromium,B,login,U}=require(__dirname+'/lib.js'); const fs=require('fs'); const {execFileSync}=require('child_process');
const cfg=JSON.parse(fs.readFileSync(process.argv[2])); const lang=process.argv[3]; const out=process.argv[4]; fs.mkdirSync(out,{recursive:true});
execFileSync('php',[__dirname+'/env/setloc.php',lang==='en'?'en_US':'tr_TR']);
async function union(p,sels,pad){ const bs=[]; for(const s of sels){ for(const l of await p.locator(s).all()){ if(!(await l.isVisible())) continue; const b=await l.boundingBox(); if(b&&b.height>10) bs.push(b);} } if(!bs.length) throw new Error('no el '+sels); const x=Math.max(0,Math.min(...bs.map(b=>b.x))-pad),y=Math.max(0,Math.min(...bs.map(b=>b.y))-pad); const x2=Math.max(...bs.map(b=>b.x+b.width))+pad,y2=Math.max(...bs.map(b=>b.y+b.height))+pad; return {x,y,width:x2-x,height:y2-y}; }
async function run(p,s,suffix){ 
  await p.goto(B+U(s.url.startsWith('/')?s.url:'/wp-admin/'+s.url)); await p.waitForTimeout(s.wait||2500);
  await p.addStyleTag({content:'#wpfooter{display:none}'+(cfg.css||'')+(s.css||'')});
  if(s.eval) await p.evaluate(s.eval); if(s.click){ for(const c of [].concat(s.click)){ await p.locator(c).locator('visible=true').first().click(); await p.waitForTimeout(1200);} if(s.after) await p.waitForTimeout(s.after); await p.addStyleTag({content:'#wpfooter{display:none}'+(cfg.css||'')+(s.css||'')}).catch(()=>{}); }
  if(s.keys){ for(const k of [].concat(s.keys)){ await p.keyboard.press(k); await p.waitForTimeout(700);} } if(s.type){ await p.keyboard.type(typeof s.type==='object'?s.type[lang]:s.type,{delay:30}); await p.waitForTimeout(900);} if(s.hover){ const bb=await p.locator(s.hover).locator('visible=true').first().boundingBox(); await p.mouse.move(bb.x+bb.width/2,bb.y+bb.height/2); await p.waitForTimeout(700);} 
  if(s.vh) { await p.setViewportSize({width:p.viewportSize().width,height:s.vh}); await p.waitForTimeout(500); }
  const opt={path:`${out}/${s.name}-${lang}.jpg`,type:'jpeg',quality:85};
  if(s.sel) Object.assign(opt,{fullPage:true,clip:await union(p,[].concat(s.sel),s.pad??12)});
  else if(s.clip) Object.assign(opt,{clip:s.clip});
  else if(s.full) opt.fullPage=true;
  await p.screenshot(opt); console.log('ok',s.name);
}
(async()=>{ const b=await chromium.launch(); const errs=[];
 const {p}=await login(b); p.on('pageerror',e=>errs.push(String(e)));
 for(const s of cfg.shots||[]){ try{ await p.setViewportSize({width:1440,height:900}); await run(p,s);}catch(e){console.log('FAIL',s.name,String(e).slice(0,200));} }
 if(cfg.mobile){ const {p:m}=await login(b,{viewport:{width:390,height:844},isMobile:true,hasTouch:true}); for(const s of cfg.mobile){ try{ await m.setViewportSize({width:390,height:844}); await run(m,s);}catch(e){console.log('FAIL',s.name,String(e).slice(0,200));} } }
 if(cfg.dark){ const {p:d}=await login(b,{colorScheme:'dark'}); for(const s of cfg.dark){ try{ await d.setViewportSize({width:1440,height:900}); await run(d,s);}catch(e){console.log('FAIL',s.name,String(e).slice(0,200));} } }
 if(errs.length) console.log('pageerrors:',errs.join('\n')); await b.close(); })();
