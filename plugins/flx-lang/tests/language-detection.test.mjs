import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import fs from 'node:fs';
const source=fs.readFileSync(new URL('../assets/js/oneri.js',import.meta.url),'utf8');
async function visit(options={}) {
 const redirects=[];let calls=0,cookie=options.cookie||'';
 const codes=options.active||['en','tr','de','es','fr','pt-br','ja','zh','ar','ru'];
 const data={mode:'redirect',cur:options.current||'en',def:'en',to:'en',home:options.home||[],alts:Object.fromEntries(codes.map(code=>[code,'https://example.test/'+code+'/'])),geo:'https://example.test/geo',path:'/',countries:{TR:'tr',FR:'fr',JP:'ja',CN:'zh',BR:'pt-br',SA:'ar',DE:'de'},explicit:!!options.explicit};
 const document={addEventListener(){},get cookie(){return cookie;},set cookie(value){cookie+='; '+value.split(';')[0];}};
 const context={window:{flxlData:data},document,navigator:{languages:options.languages||['en-US'],userAgent:options.bot?'Googlebot':'Mozilla/5.0',webdriver:false},location:{search:'',replace(url){redirects.push(url);}},Intl:{DateTimeFormat(){return{resolvedOptions(){return{timeZone:options.timeZone||'Europe/London'};}};}},setTimeout(){return 1;},clearTimeout(){},fetch(){calls++;return Promise.resolve({ok:true,json(){return Promise.resolve({country:options.country||''});}});}};
 vm.runInNewContext(source,context);for(let i=0;i<8;i++)await Promise.resolve();
 return {language:redirects.length?redirects.at(-1).split('/').at(-2):data.cur,calls,cookie,redirects};
}
test('supported regional browser locales use their active language',async()=>{
 for(const [browser,expected] of [['tr-TR','tr'],['de-AT','de'],['fr-CA','fr'],['pt-BR','pt-br'],['pt-PT','pt-br'],['ja-JP','ja'],['zh-Hans-CN','zh'],['ar-SA','ar'],['ru-RU','ru']])assert.equal((await visit({languages:[browser]})).language,expected,browser);
});
test('country hint is used only when the browser language has no supported match',async()=>{
 assert.equal((await visit({languages:['ko-KR'],country:'JP'})).language,'ja');
 assert.equal((await visit({languages:['xx'],country:'SA'})).language,'ar');
 assert.equal((await visit({languages:['fr-FR'],country:'DE'})).language,'fr');
});
test('unsupported languages and countries retain English',async()=>assert.equal((await visit({languages:['ne-NP'],country:'NP'})).language,'en'));
test('unsupported target is never selected from country hints',async()=>assert.equal((await visit({active:['en','tr'],languages:['ko-KR'],country:'JP'})).language,'en'));
test('saved preference wins without a country request',async()=>{const result=await visit({cookie:'flxl_pref=ar',languages:['de-DE'],country:'DE'});assert.equal(result.language,'ar');assert.equal(result.calls,0);});
test('explicit language URLs win over both saved and inferred language',async()=>{const result=await visit({current:'ar',explicit:true,cookie:'flxl_pref=de',languages:['en-US']});assert.equal(result.language,'ar');assert.equal(result.calls,0);});
test('bots are never redirected or geolocated',async()=>{const result=await visit({bot:true,languages:['fr-FR']});assert.equal(result.redirects.length,0);assert.equal(result.calls,0);});
test('legacy home-country behavior is preserved and an empty home list follows browser language',async()=>{assert.equal((await visit({home:['TR'],timeZone:'Europe/Istanbul',languages:['de-DE']})).language,'en');assert.equal((await visit({home:[],timeZone:'Europe/Istanbul',languages:['tr-TR']})).language,'tr');});
