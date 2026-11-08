import { useEffect, useRef, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useScope } from '../scope';
export interface AiLogRow {
 id:number;ts:number;kind:string;source:string;source_label:string;purpose:string;purpose_label:string;user_id:number;user_label:string;chat_id:number;provider:string;model:string;model_label:string;tool:string;title:string;in_tok:number;out_tok:number;cache_read:number;cache_write:number;cost:number;est:boolean;ms:number;ok:number;error:string;has_body:boolean;
}
export interface AiLogResult {items:AiLogRow[];total:number;page:number;pages:number;sources:Array<{id:string;label:string}>;details_available?:boolean}
export interface AiLogFilter {q:string;days:number;kind:''|'model'|'tool';source:string;errors:boolean}
export interface AiLogBody {req:Array<[string,string]>;res:{text?:string;error?:string;tools?:string[]}}
export const AI_LOG_DAYS=[1,7,30,90,365,0] as const;
/** Body content intentionally stays outside persisted query caches and is discarded on close/account change. */
export function useAiLog(){
 const {account,api,renderer}=useScope();const [filter,changeFilter]=useState<AiLogFilter>({q:'',days:7,kind:'',source:'',errors:false});const [search,setSearch]=useState('');const [page,setPage]=useState(1);
 const [detail,setDetail]=useState<AiLogRow|null>(null);const [body,setBody]=useState<AiLogBody|null>(null);const [bodyError,setBodyError]=useState('');const [bodyMissing,setBodyMissing]=useState(false);const [loading,setLoading]=useState(false);const [attempt,setAttempt]=useState(0);
 const selected=useRef(0);const query=useQuery<AiLogResult>({queryKey:[account.id,'ai-log',filter,page],queryFn:({signal})=>api('flx-ai/v1/log',{query:{...filter,page,per_page:25},signal}),staleTime:15_000});
 const pages=query.data?.pages;useEffect(()=>{if(pages!==undefined&&page>Math.max(1,pages))setPage(Math.max(1,pages));},[page,pages]);
 const close=()=>{selected.current=0;setDetail(null);setBody(null);setBodyError('');setBodyMissing(false);setLoading(false);};
 const setFilter=(patch:Partial<AiLogFilter>)=>{close();setPage(1);changeFilter(current=>({...current,...patch}));};
 useEffect(()=>{const timer=setTimeout(()=>{changeFilter(current=>current.q===search?current:{...current,q:search});setPage(1);},300);return()=>clearTimeout(timer);},[search]);
 // Query effects abort old account/selection requests even when the transport resolves after abort.
 useEffect(()=>{setBody(null);setBodyError('');setBodyMissing(false);if(!detail?.has_body){setLoading(false);return;}
  const abort=new AbortController();const id=detail.id;selected.current=id;setLoading(true);
  void api<AiLogBody>(`flx-ai/v1/log/${id}`,{signal:abort.signal}).then(result=>{if(!abort.signal.aborted&&selected.current===id)setBody(result);}).catch(error=>{if(!abort.signal.aborted&&selected.current===id){if(error&&typeof error==='object'&&'status' in error&&error.status===404)setBodyMissing(true);else setBodyError(error instanceof Error?error.message:String(error));}}).finally(()=>{if(!abort.signal.aborted&&selected.current===id)setLoading(false);});
  return()=>{abort.abort();selected.current=0;};
 },[account.id,api,detail,attempt]);
 useEffect(()=>{close();},[filter.q]);
 // An authorization error must not leave an already-open content pane visible.
 useEffect(()=>{if(query.isError)close();},[query.isError]);
 return{account,query,data:query.isError?undefined:query.data,filter,setFilter,search,setSearch:(value:string)=>{close();setSearch(value);},page,setPage:(value:number)=>{close();setPage(value);},detail,open:(row:AiLogRow)=>{if(detail?.id===row.id)return;close();selected.current=row.id;setDetail(row);},close,body,bodyError,bodyMissing,loading,retry:()=>setAttempt(n=>n+1),openChat:()=>{close();renderer.nav.push({kind:'native',component:'flxai-chat',params:{}});},reload:()=>{close();void query.refetch();}};
}
export function aiLogFacts(row:AiLogRow,t:(key:string)=>string,locale:string){
 const n=(value:number)=>new Intl.NumberFormat(locale).format(value);
 const cost=new Intl.NumberFormat(locale,{style:'currency',currency:'USD',minimumFractionDigits:5,maximumFractionDigits:5}).format(row.cost);
 return [
  ['time',new Date(row.ts*1000).toLocaleString(locale)],['kind',t(row.kind==='tool'?'aiLog.tool':'aiLog.model')],['source',row.source_label||row.source||'—'],['purpose',row.purpose_label||row.purpose||'—'],['user',row.user_label||t('aiLog.system')],['chat',row.chat_id?`#${row.chat_id}`:'—'],['provider',row.provider||'—'],['modelTool',row.kind==='tool'?row.tool:row.model_label||row.model],['input',n(row.in_tok)],['output',n(row.out_tok)],['cacheRead',n(row.cache_read)],['cacheWrite',n(row.cache_write)],['duration',`${n(row.ms)} ms`],['cost',row.kind==='tool'?'—':`${cost}${row.est?` · ${t('aiLog.estimated')}`:''}`],['status',t(row.ok?'aiLog.success':'aiLog.failed')],...(!row.ok?[['error',row.error==='limit'?t('aiLog.limitError'):row.error==='off'?t('aiLog.offError'):row.error||'—']]:[]),
 ];
}
