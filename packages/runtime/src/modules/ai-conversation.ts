import { useEffect, useRef, useState } from 'react';
import { useStore } from 'zustand';
import { useQuery } from '@tanstack/react-query';
import { useScope } from '../scope';
import type { AiMessage } from './ai';
import type { AiSession, AiDecision, AiSummary } from './ai-session';
/** Shared user actions. Destructive history edits never run just by opening a chat. */
export function useAiConversation(engine:AiSession){
 const {account,api,renderer}=useScope();const s=useStore(engine.store);const [search,setSearch]=useState(s.historyQuery);const [decisions,setDecisions]=useState<Record<string,AiDecision>>({});const [editing,setEditing]=useState<AiMessage|null>(null);const [editText,setEditText]=useState('');const [modelsOpen,setModelsOpen]=useState(false);const [modelText,setModelText]=useState(s.chat?s.chat.model??'':s.selectedModel);const dialog=useRef(false);const mounted=useRef(true);
 useEffect(()=>{mounted.current=true;return()=>{mounted.current=false;};},[]);
 useEffect(()=>{const timer=setTimeout(()=>{if(search!==engine.store.getState().historyQuery)void engine.loadHistory(search,s.archived);},250);return()=>clearTimeout(timer);},[engine,search,s.archived]);
 useEffect(()=>{setDecisions({});setEditing(null);},[s.chat?.id,JSON.stringify(s.chat?.pending)]);
 useEffect(()=>{setModelText(s.chat?s.chat.model??'':s.selectedModel);},[s.chat?.id,s.chat?.model,s.selectedModel]);
 const ask=async(action:()=>Promise<void>)=>{if(dialog.current||engine.store.getState().busy)return;dialog.current=true;try{await action();}finally{dialog.current=false;}};
 const send=async()=>{const text=engine.store.getState().composer;if(s.busy||s.loading||!text.trim())return;engine.store.setState({composer:''});if(!await engine.send(text)&&mounted.current&&!engine.store.getState().composer)engine.store.setState({composer:text});};
 const rename=(row:AiSummary)=>ask(async()=>{const values=await renderer.ui.prompt({title:renderer.t('aiChat.rename'),fields:[{name:'title',type:'text',label:renderer.t('aiChat.chatTitle'),required:true,maxLength:190,default:row.title}]});if(values&&mounted.current)await engine.rename(row.id,String(values.title??''));});
 const archive=(row:AiSummary)=>ask(async()=>{if(await renderer.ui.confirm({title:renderer.t(row.archived?'aiChat.restore':'aiChat.archive'),body:renderer.t('aiChat.archiveConfirm',{title:row.title||renderer.t('ai.new')})})&&mounted.current)await engine.archive(row.id,!row.archived);});
 const remove=(row:AiSummary)=>ask(async()=>{if(await renderer.ui.confirm({title:renderer.t('aiChat.delete'),body:renderer.t('aiChat.deleteConfirm',{title:row.title||renderer.t('ai.new')}),tone:'danger'})&&mounted.current)await engine.remove(row.id);});
 const rewind=(message:AiMessage)=>ask(async()=>{if(message._id&&await renderer.ui.confirm({title:renderer.t('aiChat.rewind'),body:renderer.t('aiChat.rewindConfirm'),tone:'danger'})&&mounted.current)await engine.rewind(message._id,'keep');});
 const edit=async()=>{if(!editing?._id||!editText.trim())return;await ask(async()=>{if(!await renderer.ui.confirm({title:renderer.t('aiChat.edit'),body:renderer.t('aiChat.editConfirm'),tone:'danger'})||!mounted.current)return;if(await engine.rewind(editing._id!,'edit')){engine.store.setState({composer:editText});setEditing(null);}});};
 const recover=()=>ask(async()=>{if(await renderer.ui.confirm({title:renderer.t('aiChat.recover'),body:renderer.t('aiChat.recoverConfirm')})&&mounted.current){engine.store.setState({composer:engine.store.getState().recoveryText});engine.clearRecovery();}});
 const models=useQuery<{models:Array<{id:string;name:string;tools?:boolean;provider?:string}>}>({queryKey:[account.id,'ai-model-catalogue'],enabled:modelsOpen&&!!s.boot?.canManage,staleTime:300_000,queryFn:({signal})=>api('flx-ai/v1/models',{signal}),retry:false});
 return{...s,engine,account,search,setSearch,decisions,setDecisions,editing,editText,setEditText,startEdit:(m:AiMessage)=>{setEditing(m);setEditText(m.content??'');},cancelEdit:()=>setEditing(null),send,rename,archive,remove,rewind,edit,recover,models,modelsOpen,setModelsOpen,modelText,setModelText,applyModel:()=>engine.model(modelText.trim()),setText:(composer:string)=>engine.store.setState({composer}),setArchived:(on:boolean)=>{void engine.loadHistory(search,on);},model:s.chat?s.chat.model??'':s.selectedModel,errorText:s.errorCode==='ai_send_uncertain'?renderer.t('aiChat.sendUncertain'):s.errorCode==='ai_incomplete_stream'?renderer.t('aiChat.incompleteStream'):s.errorCode==='ai_step_limit'?renderer.t('aiChat.stepLimit'):s.error};
}
