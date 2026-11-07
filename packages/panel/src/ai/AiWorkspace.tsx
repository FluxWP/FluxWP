import { createContext, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { useStore } from 'zustand';
import { useTranslation } from 'react-i18next';
import { Bot, Maximize2, PanelRight, Plus, Send, Square, X } from 'lucide-react';
import { AiSession, useAccount, useApi, useManifest } from '@fluxwp/runtime';
import { ResizablePane } from '@fluxwp/ui';
import { useRoute } from '../state/route';
import './ai.css';
import { useAiConversation } from '@fluxwp/runtime/src/modules/ai-conversation';
import { AiMessageView, AiRichText } from './AiMessageView';
import { useSidebar } from '../state/sidebar';
import { Select } from '@fluxwp/ui';
import { bindAiConfigurationRefresh } from '@fluxwp/runtime/src/modules/ai-configuration';

type Mode = 'bubble' | 'dock';
interface Workspace { engine: AiSession; open: boolean; mode: Mode; setOpen: (open: boolean) => void; setMode: (mode: Mode) => void }
const Context = createContext<Workspace | null>(null);
const useWorkspace = () => {
  const workspace = useContext(Context);
  if (!workspace) throw new Error('AI workspace missing');
  return workspace;
};

export function AiProvider({ children }: { children: ReactNode }) {
  const account = useAccount();
  const api = useApi();
  const enabled = !!useManifest().data?.modules.ai;
  const engine = useMemo(() => new AiSession(api, () => {
    const route = useRoute.getState().route;
    return { url: account.siteUrl, title: account.siteName, app: 'FluxWP', route };
  }), [api, account.id]);
  const defaultMode = useStore(engine.store, (s) => s.boot?.mode);
  const key = `flux.ai.${account.id}`;
  const readMode = (): Mode => { try { return localStorage.getItem(key) === 'dock' ? 'dock' : 'bubble'; } catch { return 'bubble'; } };
  const [mode, changeMode] = useState<Mode>(readMode);
  const [open, setOpen] = useState(false);
  const setMode = (value: Mode) => { changeMode(value); try { localStorage.setItem(key, value); } catch { /* storage unavailable */ } };
  useEffect(() => {
    if (enabled) void engine.initialize();
    const unsubscribe = enabled ? bindAiConfigurationRefresh(account.id, engine) : () => {};
    return () => { unsubscribe(); engine.dispose(); };
  }, [engine, enabled, account.id]);
  useEffect(() => {
    if (!defaultMode) return;
    try { const saved = localStorage.getItem(key); changeMode(saved === 'bubble' || saved === 'dock' ? saved : defaultMode); }
    catch { changeMode(defaultMode); }
  }, [defaultMode, key]);
  useEffect(() => {
    const keydown = (e: KeyboardEvent) => {
      if (enabled && (e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'j') { e.preventDefault(); setOpen((v) => !v); }
      if (e.key === 'Escape') setOpen(false);
    };
    window.addEventListener('keydown', keydown);
    return () => window.removeEventListener('keydown', keydown);
  }, [enabled]);
  return <Context.Provider value={{ engine, open, mode, setOpen, setMode }}>{children}</Context.Provider>;
}

/** One mounted session survives navigation and switching between the floating and docked views. */
export function AiDock() {
  const { engine, open, mode, setOpen, setMode } = useWorkspace();
  const { t } = useTranslation();
  const denied = useStore(engine.store, (s) => s.denied);
  const showAdmin = useStore(engine.store, (s) => s.boot?.showAdmin);
  const enabled = !!useManifest().data?.modules.ai;
  const account = useAccount();
  const route = useRoute((s) => s.route);
  const sidebarWidth = useSidebar((s) => s.width);
  const sidebarCollapsed = useSidebar((s) => s.collapsed);
  if (!enabled || denied || showAdmin === false || route.kind === 'native' && route.component === 'flxai-chat') return null;
  if (!open) return <button type="button" className="ai-launcher" aria-label={t('ai.title')} title={t('ai.shortcut')} onClick={() => setOpen(true)}><Bot size={24} /></button>;
  return (
    <ResizablePane storageKey={`flux.ai.width.${account.id}`} initialWidth={420} minWidth={320} maxWidth={780} minContentWidth={(sidebarCollapsed ? 0 : sidebarWidth) + 360} edge="left" label={t('ai.resize')} className={`ai-workspace ai-${mode}`}>
      <header className="ai-toolbar"><strong className="grow">{t('ai.title')}</strong>
        <button className="icon-btn" onClick={() => setMode(mode === 'dock' ? 'bubble' : 'dock')} aria-label={t(mode === 'dock' ? 'ai.float' : 'ai.dock')}><PanelRight size={17} /></button>
        <button className="icon-btn" onClick={() => useRoute.getState().go({ kind: 'native', component: 'flxai-chat', params: {} })} aria-label={t('ai.expand')}><Maximize2 size={17} /></button>
        <button className="icon-btn" onClick={() => setOpen(false)} aria-label={t('common.close')}><X size={17} /></button>
      </header>
      <AiConversation compact />
    </ResizablePane>
  );
}

export function AiScreen() {
  const { t } = useTranslation();
  return <section className="ai-screen"><header className="ai-toolbar"><Bot size={22} /><h1>{t('ai.title')}</h1></header><AiConversation /></section>;
}

function AiConversation({compact=false}:{compact?:boolean}){
 const {engine}=useWorkspace();const {t}=useTranslation();const c=useAiConversation(engine);const [historyOpen,setHistoryOpen]=useState(false);const bottom=useRef<HTMLDivElement>(null);
 useEffect(()=>{bottom.current?.scrollIntoView({block:'end'});},[c.chat?.messages.length,c.liveText,c.liveThinking]);
 const rows=c.chat?.messages.filter(m=>m.role!=='system'&&(m.role!=='tool'||!c.chat?.messages.some(a=>a.tool_calls?.some(call=>call.id===m.tool_call_id))))??[];
 const disabled=c.busy||c.loading;
 const history=<><header><h2>{t('ai.history')}</h2>{compact&&<button className="icon-btn" aria-label={t('common.close')} onClick={()=>setHistoryOpen(false)}><X size={18}/></button>}</header><button className="btn-ghost" disabled={disabled} onClick={()=>{engine.newChat();setHistoryOpen(false);}}><Plus size={16}/>{t('ai.new')}</button><input type="search" aria-label={t('ai.search')} placeholder={t('ai.search')} value={c.search} onChange={e=>c.setSearch(e.target.value)}/><label className="ai-archived-toggle"><input type="checkbox" checked={c.archived} onChange={e=>c.setArchived(e.target.checked)}/>{t('aiChat.archived')}</label><div className="ai-chat-list" aria-busy={c.historyLoading}>
  {c.chats.map(row=><div key={row.id} className={row.id===c.chat?.id?'selected':''}><button className="ai-chat-title" disabled={disabled} onClick={()=>{void engine.open(row.id);setHistoryOpen(false);}}><b>{row.title||t('ai.new')}</b>{row.snippet&&<small>{row.snippet}</small>}</button><div className="ai-chat-row-actions"><button className="btn-link" disabled={disabled} onClick={()=>void c.rename(row)}>{t('aiChat.rename')}</button><button className="btn-link" disabled={disabled} onClick={()=>void c.archive(row)}>{t(row.archived?'aiChat.restore':'aiChat.archive')}</button><button className="btn-link" disabled={disabled} onClick={()=>void c.remove(row)}>{t('aiChat.delete')}</button></div></div>)}
  {!c.chats.length&&<p className="muted">{t(c.historyLoading?'states.loading':'aiChat.emptyHistory')}</p>}{c.historyMore&&<button className="btn-ghost" disabled={c.historyLoading} onClick={()=>void engine.loadHistory(c.search,c.archived,true)}>{t('aiChat.more')}</button>}
 </div></>;
 return <div className={`ai-conversation${compact?' compact':''}`} data-testid="ai-conversation">
  {!compact&&<ResizablePane storageKey={`flux.pane.${c.account.id}.ai-history`} initialWidth={240} minWidth={200} maxWidth={420} minContentWidth={360} edge="right" label={t('aiChat.resizeHistory')} className="ai-history-pane">{history}</ResizablePane>}
  <div className="ai-conversation-main">
   <div className="ai-conversation-heading">{compact&&<button className="btn-ghost small" onClick={()=>setHistoryOpen(v=>!v)}>{t('ai.history')}</button>}<strong className="grow">{c.chat?.title||t('ai.new')}</strong><button className="btn-ghost small" disabled={disabled} onClick={()=>engine.newChat()}><Plus size={16}/>{t('ai.new')}</button>{c.boot?.canManage&&<button className="btn-ghost small" disabled={disabled} onClick={()=>c.setModelsOpen(v=>!v)}>{t('aiChat.model')}</button>}</div>
   {compact&&historyOpen&&<section className="ai-history-pop">{history}</section>}
   {c.modelsOpen&&<section className="ai-model-picker"><label>{t('aiChat.model')}<Select aria-label={t('aiChat.model')} value={c.model} disabled={disabled} onChange={e=>void engine.model(e.target.value)}><option value="">{t('aiChat.defaultModel')} · {c.boot?.modelLabel}</option>{c.model&&!c.models.data?.models.some(m=>m.id===c.model)&&<option value={c.model}>{c.model}</option>}{c.models.data?.models.filter(m=>m.tools!==false).map(m=><option key={m.id} value={m.id}>{m.name} · {m.id}</option>)}</Select></label>{c.models.isError&&<p className="error">{t('aiChat.modelsUnavailable')}</p>}<label>{t('aiChat.manualModel')}<input value={c.modelText} disabled={disabled} onChange={e=>c.setModelText(e.target.value)}/><button className="btn-ghost small" disabled={disabled} onClick={()=>void c.applyModel()}>{t('aiChat.applyModel')}</button></label><button className="btn-ghost small" onClick={()=>c.setModelsOpen(false)}>{t('common.close')}</button></section>}
   {c.errorText&&<div className="ai-error" role="alert">{c.errorText}<button className="btn-link" disabled={disabled} onClick={()=>c.chat?void engine.resume():void engine.initialize()}>{t('common.retry')}</button></div>}
   {c.recoveryText&&<button className="btn-ghost" disabled={disabled} onClick={()=>void c.recover()}>{t('aiChat.recover')}</button>}
   {c.loading&&<p role="status">{t('states.loading')}</p>}{c.boot&&!c.boot.ready&&<p className="notice">{t('ai.notReady')}</p>}
   <div className="ai-messages" aria-label={t('ai.messages')} aria-busy={c.busy}>
    {!c.chat?.messages.length&&<div className="ai-welcome"><Bot size={32}/><h2>{t('ai.welcome')}</h2><p>{t('ai.disclosure')}</p></div>}
    {c.chat&&rows.map((m,i)=><AiMessageView key={m._id||`${c.chat!.id}:${i}`} message={m} chat={c.chat!} tools={c.boot?.tools??{}} disabled={disabled} onEdit={()=>c.startEdit(m)} onRewind={()=>void c.rewind(m)}/>)}
    {(c.liveText||c.liveThinking||c.liveTools.length>0)&&<article className="ai-message live">{c.liveThinking&&<details open={!c.liveText}><summary>{t('ai.thinking')}</summary><p>{c.liveThinking}</p></details>}{c.liveText&&<AiRichText text={c.liveText} live/>}{c.liveTools.map(tool=><p key={tool.id}>{c.boot?.tools?.[tool.name]?.title||tool.name} · {t('ai.working')}</p>)}</article>}
    {c.busy&&<p className="muted" role="status">{t('ai.working')}</p>}<div ref={bottom}/>
   </div>
   {c.editing&&<section className="ai-edit"><label>{t('aiChat.edit')}<textarea value={c.editText} onChange={e=>c.setEditText(e.target.value)}/></label><p className="muted small">{t('aiChat.editConfirm')}</p><button className="btn-ghost" disabled={disabled} onClick={c.cancelEdit}>{t('common.cancel')}</button><button className="btn-primary" disabled={disabled||!c.editText.trim()} onClick={()=>void c.edit()}>{t('aiChat.applyEdit')}</button></section>}
   {!!c.chat?.pending.length&&<section className="ai-approval" aria-label={t('ai.approval')}><h2>{t('ai.approval')}</h2><p>{t('ai.disclosure')}</p>{c.chat.pending.map(call=><div className="ai-call" key={call.id}><strong>{call.title||call.name}</strong><small>{call.source} · {call.name}</small><details><summary>{t('ai.arguments')}</summary><pre>{JSON.stringify(call.args??{},null,2)}</pre></details><div className="seg">{(['reject','approve'] as const).map(decision=><button key={decision} disabled={disabled} aria-pressed={c.decisions[call.id]===decision} className={c.decisions[call.id]===decision?'on':''} onClick={()=>c.setDecisions(v=>({...v,[call.id]:decision}))}>{t(`ai.${decision}`)}</button>)}</div></div>)}<button className="btn-primary small" disabled={disabled||c.chat.pending.some(p=>!c.decisions[p.id])} onClick={()=>void engine.decide(c.decisions)}>{t('ai.continue')}</button></section>}
   {c.chat?.archived&&<div className="notice">{t('aiChat.archivedNotice')}<button className="btn-link" disabled={disabled} onClick={()=>void c.archive(c.chat!)}>{t('aiChat.restore')}</button></div>}<>{c.chat?.status==='running'&&!c.busy&&<button className="btn-ghost" onClick={()=>void engine.resume()}>{t('ai.resume')}</button>}<form className="ai-composer" onSubmit={e=>{e.preventDefault();void c.send();}}><textarea aria-label={t('ai.message')} placeholder={t('ai.message')} value={c.composer} disabled={!c.boot?.ready||!!c.editing} onChange={e=>c.setText(e.target.value)} rows={2} onKeyDown={e=>{if(e.key==='Enter'&&!e.shiftKey&&!e.nativeEvent.isComposing){e.preventDefault();void c.send();}}}/>{(c.busy||c.chat?.status==='running')&&c.chat?.id?<button className="icon-btn" type="button" aria-label={t('ai.stop')} onClick={()=>void engine.stop()}><Square size={18}/></button>:<button className="icon-btn" type="submit" aria-label={t('ai.send')} disabled={disabled||!c.boot?.ready||!c.composer.trim()||!!c.chat?.pending.length||!!c.editing}><Send size={18}/></button>}</form></>
   <small className="ai-model">{c.model||c.boot?.modelLabel||c.boot?.model}</small>
  </div>
 </div>;
}
