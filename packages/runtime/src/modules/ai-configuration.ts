import type { AiSession } from './ai-session';
/** In-memory account-scoped notifications; no credentials or settings values are transmitted. */
const listeners=new Map<string,Set<()=>void>>();
export function onAiConfigurationChanged(accountId:string,listener:()=>void){let set=listeners.get(accountId);if(!set){set=new Set();listeners.set(accountId,set);}set.add(listener);return()=>{set.delete(listener);if(!set.size)listeners.delete(accountId);};}
export function notifyAiConfigurationChanged(accountId:string){for(const listener of listeners.get(accountId)??[])listener();}

/** Coalesce refreshes during a turn; do not abort its requests or depend on React batching. */
export function bindAiConfigurationRefresh(accountId:string,engine:AiSession){
 let pending=false;let live=true;
 const off=onAiConfigurationChanged(accountId,()=>{if(engine.store.getState().busy)pending=true;else void engine.initialize();});
 const unwatch=engine.store.subscribe((state,previous)=>{if(!state.busy&&previous.busy&&pending){pending=false;queueMicrotask(()=>{if(live)void engine.initialize();});}});
 return()=>{live=false;off();unwatch();};
}
