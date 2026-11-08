import { useEffect, useRef, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useScope } from '../scope';
import { notifyAiConfigurationChanged } from './ai-configuration';

export const AI_SETTINGS_SECTIONS=['providers','models','reasoning','limits','chat','media','render','logs'] as const;
export type AiSettingValue=string|number|boolean|string[];
export interface AiSettingField { section:string;type:'secret'|'text'|'url'|'textarea'|'model'|'select'|'toggle'|'roles'|'number'|'image-model'|'video-model';min?:number;max?:number;step?:number;options?:string[] }
export interface AiSettingsState { revision:string;fields:Record<string,AiSettingField>;values:Record<string,AiSettingValue>;secrets:Record<string,{configured:boolean;locked:boolean}>;roles:Array<{value:string;label:string}>;media:{models:Array<{id:string;label:string;kind:string;routes:string[];auto:string}>;providers:Record<string,{label:string;ready:boolean}>};sources:Array<{source:string;label:string;model:string}>;renderer:string;default_model:string }
type SecretEdit={action:'replace';value:string}|{action:'clear'};
interface Draft { revision:string;original:Record<string,AiSettingValue>;values:Record<string,AiSettingValue>;secrets:Record<string,SecretEdit> }
/** New provider keys live in component state only; never in persisted query/mutation caches. */
export function useAiSettings(){
 const {account,api,renderer}=useScope();const qc=useQueryClient();const mounted=useRef(true);const running=useRef(false);const queryKey=[account.id,'ai-settings'];
 const query=useQuery<AiSettingsState>({queryKey,queryFn:({signal})=>api('flx-ai/v1/admin/settings',{signal})});
 const [section,setSection]=useState<string>('providers');const [draft,setDraft]=useState<Draft|null>(null);const [busy,setBusy]=useState(false);const [error,setError]=useState('');const [testResult,setTestResult]=useState<{text?:string;ms:number;img?:string}|null>(null);
 useEffect(()=>{mounted.current=true;return()=>{mounted.current=false;};},[]);
 const state=query.data;const form=draft??{revision:state?.revision??'',original:state?.values??{},values:state?.values??{},secrets:{}};
 const changes=Object.fromEntries(Object.entries(form.values).filter(([key,value])=>JSON.stringify(value)!==JSON.stringify(form.original[key])));
 const dirty=Object.keys(changes).length>0||Object.keys(form.secrets).length>0;const stale=!!draft&&!!state&&draft.revision!==state.revision;
 const field=(key:string,value:AiSettingValue)=>{if(!running.current){setDraft({...form,values:{...form.values,[key]:value}});setError('');setTestResult(null);}};
 const secret=(key:string,action:'replace'|'clear'|'keep',value='')=>{if(running.current||state?.secrets[key]?.locked)return;const secrets={...form.secrets};if(action==='keep'||action==='replace'&&!value)delete secrets[key];else secrets[key]=action==='clear'?{action}:{action,value};setDraft({...form,secrets});setError('');setTestResult(null);};
 const reset=async()=>{if(running.current)return;running.current=true;setBusy(true);try{if(dirty&&!await renderer.ui.confirm({title:renderer.t('aiSettings.title'),body:renderer.t('aiSettings.discard')}))return;if(mounted.current){setDraft(null);setError('');setTestResult(null);await query.refetch();}}finally{running.current=false;if(mounted.current)setBusy(false);}};
 const save=async()=>{
  if(running.current||!state||!dirty||stale)return;const values={...changes};
  for(const [key,value] of Object.entries(values)){const f=state.fields[key];if(f?.type!=='number')continue;let raw=String(value).trim().replace(',','.');if(!raw&&['limit_day','limit_week','limit_month','log_days','log_body_days'].includes(key))raw='0';const n=Number(raw);if(!raw||!Number.isFinite(n)||f.min!==undefined&&n<f.min||f.max!==undefined&&n>f.max||f.step===1&&!Number.isInteger(n)){setError(renderer.t('aiSettings.invalidNumber',{name:renderer.t(`aiSettings.fields.${key}`)}));return;}values[key]=n;}
  running.current=true;setBusy(true);setError('');
  try{if(!await renderer.ui.confirm({title:renderer.t('aiSettings.title'),body:renderer.t('aiSettings.confirm',{site:`${account.siteName} (${account.siteUrl})`})})||!mounted.current)return;
   const saved=await api<AiSettingsState>('flx-ai/v1/admin/settings',{method:'POST',body:{revision:form.revision,values,secrets:form.secrets}});
   qc.setQueryData(queryKey,saved);await Promise.all(['ai-model-catalogue','ai-systems','ai-system'].map(key=>qc.invalidateQueries({queryKey:[account.id,key]})));
   notifyAiConfigurationChanged(account.id);
   if(mounted.current){setDraft(null);setTestResult(null);renderer.ui.toast(renderer.t('form.saved'),'ok');}
  }catch(e){if(mounted.current)setError(e instanceof Error?e.message:String(e));}finally{running.current=false;if(mounted.current)setBusy(false);}
 };
 const models=useQuery<{models:Array<{id:string;name:string;provider:string}>}>({queryKey:[account.id,'ai-model-catalogue'],enabled:section==='models',staleTime:300_000,queryFn:({signal})=>api('flx-ai/v1/models',{signal}),retry:false});
 const options=(key:string)=>{const f=state?.fields[key];const value=String(form.values[key]??'');if(f?.type==='model')return[{value:'',label:renderer.t('aiSettings.inherit')},...(value&&!models.data?.models.some(m=>m.id===value)?[{value,label:value}]:[]),...(models.data?.models.map(m=>({value:m.id,label:`${m.name} · ${m.provider}`}))??[])];if(f?.type==='image-model'||f?.type==='video-model'){const list=state?.media.models.filter(m=>m.kind===f.type.replace('-model','')).map(m=>({value:m.id,label:m.label}))??[];return value&&!list.some(m=>m.value===value)?[{value,label:value},...list]:list;}return f?.options?.map(value=>({value,label:renderer.t(`aiSettings.options.${value}`)}))??[];};
 const runTest=async(kind:'model'|'render')=>{
  if(running.current||!state||kind==='model'&&Object.keys(form.secrets).length||kind==='render'&&form.secrets.key_render?.action==='clear')return;
  running.current=true;setBusy(true);setError('');setTestResult(null);
  try{if(!await renderer.ui.confirm({title:renderer.t('aiSettings.test'),body:renderer.t(kind==='model'?'aiSettings.testCost':'aiSettings.testRenderConfirm',{site:account.siteName,url:String(form.values.render_url||'Imagick')})})||!mounted.current)return;
   const body=kind==='model'?{model:form.values.model??''}:{url:form.values.render_url??'',user:form.values.render_user??'',pass:form.secrets.key_render?.action==='replace'?form.secrets.key_render.value:''};
   const result=await api<{text?:string;ms:number;img?:string}>(kind==='model'?'flx-ai/v1/test':'flx-ai/v1/render-test',{method:'POST',body});if(mounted.current)setTestResult(result);
  }catch(e){if(mounted.current)setError(e instanceof Error?e.message:String(e));}finally{running.current=false;if(mounted.current)setBusy(false);}
 };
 const systems=async()=>{if(running.current)return;running.current=true;setBusy(true);try{if(dirty&&!await renderer.ui.confirm({title:renderer.t('aiSettings.title'),body:renderer.t('aiSettings.discard')}))return;if(mounted.current)renderer.nav.push({kind:'native',component:'flxai-systems',params:{}});}finally{running.current=false;if(mounted.current)setBusy(false);}};
 return{account,query,state,form,section,setSection,fields:Object.entries(state?.fields??{}).filter(([,f])=>f.section===section),field,secret,dirty,stale,busy,error,save,reset,models,options,runTest,testResult,systems};
}
