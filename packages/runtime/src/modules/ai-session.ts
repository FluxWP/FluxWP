import { createStore } from 'zustand/vanilla';
import { ApiError, type ApiClient } from '@fluxwp/core';
import { streamAiStep, type AiChat, type AiEvent } from './ai';
export interface AiBoot { ready:boolean;model:string;modelLabel:string;canManage:boolean;mode?:'bubble'|'dock';showAdmin?:boolean;tools?:Record<string,{title:string;write:boolean}>;widgets?:boolean }
export interface AiSummary { id:number;title:string;status:string;archived?:number;updated?:number;snippet?:string }
export interface AiSessionState {
 boot:AiBoot|null;chats:AiSummary[];chat:AiChat|null;loading:boolean;busy:boolean;error:string;errorCode:string;denied:boolean;
 liveText:string;liveThinking:string;liveTools:Array<{id:string;name:string}>;historyQuery:string;archived:boolean;historyLoading:boolean;historyMore:boolean;selectedModel:string;recoveryText:string;composer:string;
}
export interface AiResult {state:string;chat:AiChat}
export type AiDecision='approve'|'reject';
const emptyLive={liveText:'',liveThinking:'',liveTools:[]};
const interrupted=(e:unknown)=>!(e instanceof ApiError)||e.status===0||e.code==='ai_incomplete_stream';
const wait=(ms:number,signal:AbortSignal)=>new Promise<void>((resolve,reject)=>{const abort=()=>{clearTimeout(timer);reject(new ApiError(0,'aborted','aborted'));};const timer=setTimeout(()=>{signal.removeEventListener('abort',abort);resolve();},ms);if(signal.aborted)abort();else signal.addEventListener('abort',abort,{once:true});});
/** One account-owned session: credentials, refresh and streaming all use its authenticated client. */
export class AiSession {
 readonly store=createStore<AiSessionState>(()=>({boot:null,chats:[],chat:null,loading:false,busy:false,error:'',errorCode:'',denied:false,...emptyLive,historyQuery:'',archived:false,historyLoading:false,historyMore:false,selectedModel:'',recoveryText:'',composer:''}));
 private controller:AbortController|null=null;private revision=0;private historyRevision=0;private historyController:AbortController|null=null;
 constructor(private api:ApiClient,private page:()=>Record<string,unknown>=()=>({})){}
 private error(e:unknown){this.store.setState({error:e instanceof Error?e.message:String(e),errorCode:e instanceof ApiError?e.code:''});}
 async initialize(){
  if(this.store.getState().busy)return;const rev=++this.revision;this.controller?.abort();const c=this.controller=new AbortController();this.store.setState({loading:true,error:'',errorCode:'',denied:false});
  try{const boot=await this.api<AiBoot>('flx-ai/v1/boot',{signal:c.signal});if(rev!==this.revision||c.signal.aborted)return;this.store.setState({boot});await this.loadHistory();}
  catch(e){if(rev===this.revision&&!c.signal.aborted){this.store.setState({denied:e instanceof ApiError&&[401,403,404].includes(e.status),boot:null});this.error(e);}}
  finally{if(rev===this.revision)this.store.setState({loading:false});}
 }
 async search(q:string,archived=0,offset=0){return(await this.api<{chats:AiSummary[]}>('flx-ai/v1/chats',{query:{q,archived,offset,limit:50}})).chats;}
 async loadHistory(q=this.store.getState().historyQuery,archived=this.store.getState().archived,more=false){
  const rev=++this.historyRevision;this.historyController?.abort();const c=this.historyController=new AbortController();const offset=more?this.store.getState().chats.length:0;
  this.store.setState({historyQuery:q,archived,historyLoading:true});
  try{const result=await this.api<{chats:AiSummary[]}>('flx-ai/v1/chats',{query:{q,archived:archived?1:0,offset,limit:50},signal:c.signal});if(rev===this.historyRevision&&!c.signal.aborted)this.store.setState({chats:more?[...new Map([...this.store.getState().chats,...result.chats].map(row=>[row.id,row])).values()]:result.chats,historyMore:result.chats.length===50});}
  catch(e){if(rev===this.historyRevision&&!c.signal.aborted){this.error(e);if(e instanceof ApiError&&[401,403].includes(e.status))this.store.setState({chats:[],chat:null,denied:true,...emptyLive});}}
  finally{if(rev===this.historyRevision)this.store.setState({historyLoading:false});}
 }
 private accept(chat:AiChat){const previous=this.store.getState().chat;const since=chat.since??0;if(since&&(previous?.id!==chat.id||since>previous.messages.length))throw new ApiError(502,'ai_incomplete_stream','Conversation history is incomplete. Refresh before continuing.');this.store.setState({chat:{...chat,messages:since?previous!.messages.slice(0,since).concat(chat.messages):chat.messages},...emptyLive});}
 async open(id:number){
  if(this.store.getState().busy)return;const rev=++this.revision;this.controller?.abort();const c=this.controller=new AbortController();this.store.setState({loading:true,chat:null,error:'',errorCode:'',...emptyLive});
  try{const chat=await this.api<AiChat>(`flx-ai/v1/chats/${id}`,{signal:c.signal});if(rev===this.revision&&!c.signal.aborted)this.accept(chat);}
  catch(e){if(rev===this.revision&&!c.signal.aborted)this.error(e);}finally{if(rev===this.revision)this.store.setState({loading:false});}
 }
 newChat(){if(this.store.getState().busy)return;++this.revision;this.controller?.abort();this.store.setState({chat:null,error:'',errorCode:'',loading:false,...emptyLive});}
 async send(text:string){
  const body=text.trim();const s=this.store.getState();if(!body||!s.boot?.ready||s.loading||s.chat?.pending.length)return false;
  return this.run(signal=>this.api<AiResult>(`flx-ai/v1/chats/${s.chat?.id??0}/send`,{method:'POST',body:{text:body,page:this.page(),...(!s.chat&&s.selectedModel?{model:s.selectedModel}:{})},signal}),body);
 }
 async decide(decisions:Record<string,AiDecision>){const chat=this.store.getState().chat;if(!chat?.pending.length||chat.pending.some(p=>!['approve','reject'].includes(decisions[p.id]??'')))return false;const exact=Object.fromEntries(chat.pending.map(p=>[p.id,decisions[p.id]]));return this.run(signal=>this.api<AiResult>(`flx-ai/v1/chats/${chat.id}/decide`,{method:'POST',body:{decisions:exact},signal}));}
 async resume(){const chat=this.store.getState().chat;if(!chat)return false;return this.run(async signal=>{const fresh=await this.api<AiChat>(`flx-ai/v1/chats/${chat.id}`,{signal});return{state:fresh.status==='running'?'continue':fresh.status==='approval'?'approval':'done',chat:fresh};});}
 private live(event:AiEvent){
  if(event.t==='chat'&&event.chat){this.accept(event.chat);return;}
  if(event.t==='model'){this.store.setState({...emptyLive});return;}
  if(event.t==='text')this.store.setState(s=>({liveText:s.liveText+(event.d??'')}));
  if(event.t==='think')this.store.setState(s=>({liveThinking:s.liveThinking+(event.d??'')}));
  if(event.t==='tool')this.store.setState(s=>({liveTools:[...s.liveTools.filter(tool=>tool.id!==event.id),{id:event.id??`live-${s.liveTools.length}`,name:event.name??''}]}));
 }
 private async run(start:(signal:AbortSignal)=>Promise<AiResult>,sentText=''){
  if(this.store.getState().busy||this.store.getState().loading)return false;const rev=++this.revision;this.controller?.abort();const c=this.controller=new AbortController();this.store.setState({busy:true,error:'',errorCode:'',...emptyLive});let accepted=false;
  try{let result=await start(c.signal);accepted=true;
   for(let step=0;;step++){if(rev!==this.revision||c.signal.aborted)return accepted;this.accept(result.chat);if(result.state==='approval'||result.chat.status==='approval'||result.chat.pending.length)break;if(result.state!=='continue'&&result.state!=='busy'&&result.chat.status!=='running')break;if(step>=100)throw new ApiError(502,'ai_step_limit','The conversation needs to be refreshed before continuing.');
    if(result.state==='busy')await wait(1500,c.signal);
    result=await streamAiStep({api:this.api,chatId:result.chat.id,signal:c.signal},event=>{if(rev===this.revision&&!c.signal.aborted)this.live(event);});
   }
   await this.loadHistory();return true;
  }catch(e){if(rev===this.revision&&!c.signal.aborted){this.error(e);if(!accepted&&sentText&&interrupted(e)){this.store.setState({recoveryText:sentText,errorCode:'ai_send_uncertain'});accepted=true;}
    // Recover committed state after a broken stream. Never repeat a write/model step automatically.
    const id=this.store.getState().chat?.id;if(accepted&&id&&interrupted(e)){try{const fresh=await this.api<AiChat>(`flx-ai/v1/chats/${id}`,{signal:c.signal});if(rev===this.revision&&!c.signal.aborted)this.accept(fresh);}catch{/* Keep the last confirmed conversation and the original transport error. */}}
   }return accepted;
  }finally{if(rev===this.revision)this.store.setState({busy:false,...emptyLive});}
 }
 async stop(){const id=this.store.getState().chat?.id;if(!id)return;const rev=++this.revision;this.controller?.abort();const c=this.controller=new AbortController();this.store.setState({busy:true,...emptyLive});try{const r=await this.api<AiResult>(`flx-ai/v1/chats/${id}/stop`,{method:'POST',body:{},signal:c.signal});if(rev===this.revision&&!c.signal.aborted)this.accept(r.chat);}catch(e){if(rev===this.revision&&!c.signal.aborted)this.error(e);}finally{if(rev===this.revision)this.store.setState({busy:false});}}
 private async update(id:number,body:Record<string,unknown>){if(this.store.getState().busy||this.store.getState().loading)return false;const rev=++this.revision;this.controller?.abort();const c=this.controller=new AbortController();this.store.setState({busy:true,error:'',errorCode:''});try{const chat=await this.api<AiChat>(`flx-ai/v1/chats/${id}`,{method:'POST',body,signal:c.signal});if(rev!==this.revision||c.signal.aborted)return false;if(this.store.getState().chat?.id===id)this.accept(chat);await this.loadHistory();return true;}catch(e){if(rev===this.revision&&!c.signal.aborted)this.error(e);return false;}finally{if(rev===this.revision)this.store.setState({busy:false});}}
 rename(id:number,title:string){return title.trim()?this.update(id,{title:title.trim()}):Promise.resolve(false);}
 archive(id:number,on:boolean){return this.update(id,{archived:on?1:0});}
 async model(model:string){if(!this.store.getState().boot?.canManage||this.store.getState().busy)return false;const id=this.store.getState().chat?.id;if(id)return this.update(id,{model});this.store.setState({selectedModel:model});return true;}
 async remove(id:number){if(this.store.getState().busy||this.store.getState().loading)return false;const rev=++this.revision;this.controller?.abort();const c=this.controller=new AbortController();this.store.setState({busy:true,error:'',errorCode:''});try{await this.api(`flx-ai/v1/chats/${id}/delete`,{method:'POST',body:{},signal:c.signal});if(rev!==this.revision||c.signal.aborted)return false;if(this.store.getState().chat?.id===id)this.store.setState({chat:null,...emptyLive});await this.loadHistory();return true;}catch(e){if(rev===this.revision&&!c.signal.aborted)this.error(e);return false;}finally{if(rev===this.revision)this.store.setState({busy:false});}}
 async rewind(mid:string,mode:'edit'|'keep'){const chat=this.store.getState().chat;if(!chat||this.store.getState().busy||this.store.getState().loading)return false;const rev=++this.revision;this.controller?.abort();const c=this.controller=new AbortController();this.store.setState({busy:true,error:'',errorCode:''});try{const result=await this.api<AiChat>(`flx-ai/v1/chats/${chat.id}/rewind`,{method:'POST',body:{msg:mid,mode},signal:c.signal});if(rev!==this.revision||c.signal.aborted)return false;this.accept(result);return true;}catch(e){if(rev===this.revision&&!c.signal.aborted)this.error(e);return false;}finally{if(rev===this.revision)this.store.setState({busy:false});}}
 clearRecovery(){this.store.setState({recoveryText:''});}
 dispose(){++this.revision;++this.historyRevision;this.controller?.abort();this.historyController?.abort();this.store.setState({busy:false,loading:false,historyLoading:false,...emptyLive});}
}
