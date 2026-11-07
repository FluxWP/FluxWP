import { expect, it } from 'vitest';
import type { ApiClient } from '@fluxwp/core';
import { AiSession } from './ai-session';
import { bindAiConfigurationRefresh,notifyAiConfigurationChanged,onAiConfigurationChanged } from './ai-configuration';
it('refreshes only the changed account and removes unmounted listeners',()=>{let first=0,second=0;const off=onAiConfigurationChanged('site-a',()=>first++);const offOther=onAiConfigurationChanged('site-b',()=>second++);notifyAiConfigurationChanged('site-a');expect([first,second]).toEqual([1,0]);off();notifyAiConfigurationChanged('site-a');notifyAiConfigurationChanged('site-b');expect([first,second]).toEqual([1,1]);offOther();});
it('queues one configuration refresh until a busy turn ends and cancels queued work on unmount',async()=>{
 let reads=0;const api=(async(path:string)=>{if(path.endsWith('/boot')){reads++;return{ready:true,model:'demo',modelLabel:'Demo',canManage:true};}return{chats:[]};}) as ApiClient;
 const engine=new AiSession(api);const off=bindAiConfigurationRefresh('busy-site',engine);engine.store.setState({busy:true});
 notifyAiConfigurationChanged('another-site');notifyAiConfigurationChanged('busy-site');notifyAiConfigurationChanged('busy-site');expect(reads).toBe(0);expect(engine.store.getState().busy).toBe(true);
 engine.store.setState({busy:false});await Promise.resolve();expect(reads).toBe(1);
 engine.store.setState({busy:true});notifyAiConfigurationChanged('busy-site');engine.store.setState({busy:false});off();await Promise.resolve();expect(reads).toBe(1);engine.dispose();
});
