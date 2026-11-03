const { chromium } = require('playwright'); const fs=require('fs');
const B='http://127.0.0.1:8080'; const W=process.env.WPENV||'/tmp/wpenv'; const SF=W+'/state.json';
async function login(b,opts={}){
 if(fs.existsSync(SF)){ const c=await b.newContext({viewport:{width:1440,height:900},deviceScaleFactor:2,storageState:SF,...opts}); const p=await c.newPage(); await p.goto(B+'/wp-admin/profile.php'); if(!p.url().includes('wp-login')) return {c,p}; await c.close(); }
 const c=await b.newContext({viewport:{width:1440,height:900},deviceScaleFactor:2,...opts}); const p=await c.newPage();
 await p.goto(B+'/wp-login.php'); await p.fill('#user_login','admin'); await p.fill('#user_pass','admin12345'); await p.click('#wp-submit'); await p.waitForLoadState('networkidle'); await c.storageState({path:SF}); return {c,p}; }
// cfg adreslerinde {post:Başlık} -> o yazının kimliği (seed sonrası kimlikler değişebilir)
function U(u){ return u.replace(/\{post:([^}]+)\}/g,(m,t)=>require('child_process').execSync(`php -r 'require "${W}/site/wp-load.php"; $p=get_page_by_title(base64_decode("${Buffer.from(t).toString('base64')}"),OBJECT,["post","page"]); echo $p?$p->ID:0;'`).toString().trim()); }
module.exports={chromium,B,login,W,U};
