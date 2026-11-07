import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { RefreshCw, X } from 'lucide-react';
import { useAccount } from '@fluxwp/runtime';
import { AI_LOG_DAYS, aiLogFacts, useAiLog } from '@fluxwp/runtime/src/modules/ai-log';
import { ResizablePane, Select } from '@fluxwp/ui';
import './native-admin.css';
import './ai-log.css';
export function AiLogScreen(){const account=useAccount();return <AiLogView key={account.id}/>;}
function AiLogView(){
 const {t,i18n}=useTranslation();const c=useAiLog();const data=c.data;const [tab,setTab]=useState('metadata');const open=(row:NonNullable<typeof c.detail>)=>{setTab('metadata');c.open(row);};
 return <div className="native-admin ai-log" data-testid="screen-ai-log" onKeyDown={e=>{if(e.key==='Escape'&&c.detail){c.close();e.stopPropagation();}}}>
  <main className="native-admin-body"><header className="page-head"><div><h1>{t('aiLog.title')}</h1><p className="muted">{t('aiLog.intro')}</p></div><div className="ai-log-head-actions"><button className="btn-ghost" onClick={c.openChat}>{t('aiLog.openChat')}</button><button className="btn-ghost" onClick={c.reload} disabled={c.query.isFetching}><RefreshCw size={16}/>{t('aiLog.reload')}</button></div></header>
   <div className="ai-log-filters">
    <label className="field">{t('aiLog.search')}<input type="search" value={c.search} onChange={e=>c.setSearch(e.target.value)} placeholder={t('aiLog.searchPlaceholder')}/></label>
    <label className="field">{t('aiLog.period')}<Select value={String(c.filter.days)} onChange={e=>c.setFilter({days:Number(e.target.value)})}>{AI_LOG_DAYS.map(d=><option key={d} value={d}>{t(d===0?'aiLog.allTime':d===1?'aiLog.lastDay':'aiLog.days',{count:d})}</option>)}</Select></label>
    <label className="field">{t('aiLog.kind')}<Select value={c.filter.kind} onChange={e=>c.setFilter({kind:e.target.value as ''|'model'|'tool'})}>{['','model','tool'].map(k=><option key={k} value={k}>{t(k?`aiLog.${k}`:'aiLog.all')}</option>)}</Select></label>
    <label className="field">{t('aiLog.source')}<Select value={c.filter.source} onChange={e=>c.setFilter({source:e.target.value})}><option value="">{t('aiLog.allSources')}</option>{data?.sources.map(s=><option key={s.id} value={s.id}>{s.label}</option>)}</Select></label>
    <label className="ai-log-check"><input type="checkbox" checked={c.filter.errors} onChange={e=>c.setFilter({errors:e.target.checked})}/>{t('aiLog.errorsOnly')}</label>
   </div>
   {c.query.isError&&<p className="error" role="alert">{c.query.error instanceof Error?c.query.error.message:t('states.errorTitle')}</p>}
   {data&&data.details_available!==true&&<p className="notice warn">{t('aiLog.updateRequired')}</p>}
   <p className="muted" aria-live="polite">{c.query.isFetching?t('states.loading'):t('aiLog.results',{count:data?.total??0})}</p>
   <div className="native-table-scroll"><table className="native-matrix ai-log-table"><thead><tr>{['time','source','user','modelTool','input','output','cache','duration','cost'].map(k=><th key={k}>{t(`aiLog.${k}`)}</th>)}</tr></thead><tbody>
    {data?.items.map(row=>{const facts=Object.fromEntries(aiLogFacts(row,t,i18n.language));return <tr key={row.id} className={!row.ok?'ai-log-error':undefined}><td><button className="ai-log-title" onClick={()=>open(row)} aria-label={t('aiLog.open',{id:row.id})}>{facts.time}</button><small>#{row.id} · {t(row.kind==='tool'?'aiLog.tool':'aiLog.model')}</small></td><td>{row.source_label||row.source}<small>{row.purpose_label||row.purpose}{row.chat_id?` #${row.chat_id}`:''}</small></td><td>{row.user_label||t('aiLog.system')}</td><td><button className="ai-log-title" onClick={()=>open(row)}>{row.tool||row.model_label||row.model||row.title}</button>{!row.ok&&<small className="error">{facts.error}</small>}{row.has_body&&<small>{t('aiLog.savedContent')}</small>}</td><td>{facts.input}</td><td>{facts.output}</td><td>{facts.cacheRead} / {facts.cacheWrite}</td><td>{facts.duration}</td><td>{facts.cost}</td></tr>;})}
    {!data?.items.length&&<tr><td colSpan={9}>{c.query.isPending?t('states.loading'):t('aiLog.empty')}</td></tr>}
   </tbody></table></div>
   <div className="ai-log-pager"><button className="btn-ghost" disabled={c.query.isFetching||c.page<=1} onClick={()=>c.setPage(c.page-1)}>{t('aiLog.previous')}</button><span>{t('aiLog.page',{page:c.page,pages:Math.max(1,data?.pages??0)})}</span><button className="btn-ghost" disabled={c.query.isFetching||!data||c.page>=data.pages} onClick={()=>c.setPage(c.page+1)}>{t('aiLog.next')}</button></div><p className="muted small">{t('aiLog.note')}</p>
  </main>
  {c.detail&&<ResizablePane storageKey={`flux.pane.${c.account.id}.ai-log-detail`} initialWidth={420} minWidth={300} maxWidth={800} minContentWidth={340} edge="left" label={t('aiLog.resize')} className="ai-log-detail">
   <header className="page-head"><h2>{t('aiLog.record',{id:c.detail.id})}</h2><button className="icon-btn" onClick={c.close} aria-label={t('common.close')}><X size={18}/></button></header>
   <div className="ai-log-tabs" role="tablist">{['metadata','request','response'].map(k=><button role="tab" aria-selected={tab===k} key={k} className={tab===k?'on':''} onClick={()=>setTab(k)}>{t(`aiLog.${k}`)}</button>)}</div>
   <div className="ai-log-content" role="tabpanel">{tab==='metadata'?<dl>{aiLogFacts(c.detail,t,i18n.language).map(([key,value])=><div key={key}><dt>{t(`aiLog.${key}`)}</dt><dd>{value}</dd></div>)}</dl>:<>
    <p className="muted small">{t('aiLog.contentNote')}</p>{c.loading?<p role="status">{t('states.loading')}</p>:c.bodyError?<><p className="error" role="alert">{c.bodyError}</p><button className="btn-ghost" onClick={c.retry}>{t('aiLog.retry')}</button></>:!c.detail.has_body||c.bodyMissing?<p>{t('aiLog.noBody')}</p>:c.body&&<>{tab==='request'?c.body.req.map(([role,text],i)=><section key={i}><h3>{t(`aiLog.roles.${role}`,{defaultValue:role})}</h3><pre>{text}</pre></section>):<><pre>{c.body.res.error||c.body.res.text||'—'}</pre>{c.body.res.tools?.map((tool,i)=><pre key={i}>{tool}</pre>)}</>}</>}
   </>}</div><button className="btn-ghost" onClick={c.close}>{t('common.close')}</button>
  </ResizablePane>}
 </div>;
}
