// node hero.js <lang> <out.jpg> : kök README hero görseli (paket ekranlarından kolaj)
const {chromium}=require('playwright'); const fs=require('fs'); const path=require('path');
const [lang,out]=process.argv.slice(2); const R=path.join(__dirname,'../../../');
const img=(p)=>'data:image/jpeg;base64,'+fs.readFileSync(R+p.replace('{l}',lang)).toString('base64');
const tags={en:['🌱 Donations','💬 WhatsApp & e-mail','🤖 AI & MCP','📊 Analytics'],tr:['🌱 Bağış','💬 WhatsApp ve e-posta','🤖 Yapay zekâ ve MCP','📊 Analitik']}[lang];
let h=fs.readFileSync(__dirname+'/hero.html','utf8')
 .replace('src="A"',`src="${img('plugins/flx-chat/docs/images/chats-{l}.jpg')}"`)
 .replace('src="B"',`src="${img('plugins/flx-donate/docs/images/dashboard-{l}.jpg')}"`)
 .replace('src="C"',`src="${img('plugins/flx-analytics/docs/images/overview-{l}.jpg')}"`)
 .replace('src="D"',`src="${img('plugins/flx-ai/docs/images/mobile-{l}.jpg')}"`)
 .replace('src="E"',`src="${img('themes/fluxwp/docs/images/mobile-{l}.jpg')}"`)
 .replace('🌱 Donations',tags[0]).replace('💬 WhatsApp & e-mail',tags[1]).replace('🤖 AI & MCP',tags[2]).replace('📊 Analytics',tags[3]);
(async()=>{const b=await chromium.launch(); const p=await b.newPage({viewport:{width:1600,height:760},deviceScaleFactor:1.5}); await p.setContent(h); await p.waitForTimeout(800);
 // etiketleri yan yana ortala
 await p.evaluate(()=>{const t=[...document.querySelectorAll('.tag')]; const w=t.reduce((a,e)=>a+e.offsetWidth,0)+14*(t.length-1); let x=(1600-w)/2; for(const e of t){e.style.left=x+'px'; x+=e.offsetWidth+14;}});
 await p.screenshot({path:out,type:'jpeg',quality:85}); await b.close(); console.log(out);})();
