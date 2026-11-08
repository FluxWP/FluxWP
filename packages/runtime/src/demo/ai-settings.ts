import { getDemoLocale } from '@fluxwp/core/src/demo/locale';
import schema from './ai-settings-schema.json';
import { aiChatDemoTools } from './ai-chat';
import type { AiSettingsState } from '../modules/ai-settings';
type Route=[string,RegExp,(match:RegExpMatchArray,query:URLSearchParams,body:Record<string,unknown>)=>unknown];
const stored=structuredClone(schema) as AiSettingsState;let revision=1;
const snapshot=():AiSettingsState=>{const s=structuredClone(stored);s.revision=String(revision);for(const [id,provider] of Object.entries(s.media.providers))provider.ready=!!s.secrets[`key_${id}`]?.configured;for(const model of s.media.models)model.auto=model.routes.find(id=>s.media.providers[id]?.ready)||'';if(getDemoLocale()==='tr')s.roles=s.roles.map(role=>({...role,label:role.value==='administrator'?'Yönetici':'Editör'}));return s;};
const failure=(status:number,message:string)=>new Response(JSON.stringify({code:'demo_ai_settings',message}),{status,headers:{'Content-Type':'application/json'}});
export const aiSettingsDemoRoutes:Route[]=[
 ['GET',/^flx-ai\/v1\/boot$/,()=>({ready:true,model:stored.values.chat_model||stored.values.model||'demo/model-1',modelLabel:'Demo model',tools:aiChatDemoTools(),widgets:!!stored.values.chat_widgets,canManage:true,counts:{chats:1},mode:stored.values.chat_mode,showAdmin:stored.values.chat_admin})],
 ['GET',/^flx-ai\/v1\/admin\/settings$/,()=>snapshot()],
 ['POST',/^flx-ai\/v1\/admin\/settings$/,(_m,_q,body)=>{
  if(body.revision!==String(revision))return failure(409,'Settings changed. Refresh before saving.');
  const values=body.values as AiSettingsState['values']??{};const secrets=body.secrets as Record<string,{action:string;value?:string}>??{};
  for(const [key,value] of Object.entries(values)){const f=stored.fields[key];if(!f||f.type==='secret')return failure(400,'Unknown field.');if(f.type==='number'&&(typeof value!=='number'||!Number.isFinite(value)||f.min!==undefined&&value<f.min||f.max!==undefined&&value>f.max||f.step===1&&!Number.isInteger(value)))return failure(400,'Invalid number.');if(f.type==='toggle'&&typeof value!=='boolean'||f.type==='roles'&&!Array.isArray(value)||f.type==='select'&&!f.options?.includes(String(value)))return failure(400,'Invalid value.');}
  for(const [key,s] of Object.entries(secrets))if(!stored.secrets[key]||!['clear','replace'].includes(s.action)||s.action==='replace'&&!s.value)return failure(400,'Invalid secret change.');
  Object.assign(stored.values,values);for(const [key,s] of Object.entries(secrets))stored.secrets[key]!.configured=s.action==='replace';
  stored.renderer=stored.values.render_url?'gotenberg':'';revision++;return snapshot();
 }],
 ['POST',/^flx-ai\/v1\/test$/,()=>({text:getDemoLocale()==='en'?'Demo model connection succeeded. No external service was called.':'Demo model bağlantısı başarılı. Harici servise istek yapılmadı.',ms:120})],
 ['POST',/^flx-ai\/v1\/render-test$/,()=>({text:getDemoLocale()==='en'?'Demo renderer connection succeeded.':'Demo görsel çevirici bağlantısı başarılı.',ms:80})],
];
