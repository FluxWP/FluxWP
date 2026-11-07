import { expect, it } from 'vitest';
import { createClient } from '@fluxwp/core';
import { readAiStream, streamAiStep, type AiEvent } from './ai';
const chat={id:7,title:'Test',status:'idle',messages:[],pending:[]};
it('decodes split Unicode and a terminal event without a final newline',async()=>{
 const bytes=new TextEncoder().encode('{"t":"text","d":"🌱 Türkçe"}\n{"t":"result","state":"done","chat":'+JSON.stringify(chat)+'}');
 const body=new ReadableStream({start(c){for(let i=0;i<bytes.length;i+=3)c.enqueue(bytes.slice(i,i+3));c.close();}});const events:AiEvent[]=[];
 await readAiStream(new Response(body,{headers:{'Content-Type':'application/x-ndjson'}}),ev=>events.push(ev));expect(events[0]?.d).toBe('🌱 Türkçe');expect(events[1]?.chat?.id).toBe(7);expect(body.locked).toBe(false);
});
it('supports buffered native NDJSON responses with no readable body',async()=>{
 const events:AiEvent[]=[];await readAiStream({headers:new Headers({'Content-Type':'application/x-ndjson'}),body:null,text:async()=>'{"t":"text","d":"buffered"}\n'} as Response,e=>events.push(e));expect(events[0]?.d).toBe('buffered');
});
it('cancels the reader and refuses late events after stop',async()=>{
 const c=new AbortController();const events:AiEvent[]=[];let canceled=false;const body=new ReadableStream({start(controller){controller.enqueue(new TextEncoder().encode('{"t":"text","d":"first"}\n{"t":"text","d":"late"}\n'));},cancel(){canceled=true;}});
 await expect(readAiStream(new Response(body,{headers:{'Content-Type':'application/x-ndjson'}}),ev=>{events.push(ev);c.abort();},c.signal)).rejects.toMatchObject({code:'aborted'});expect(events.map(e=>e.d)).toEqual(['first']);expect(canceled).toBe(true);expect(body.locked).toBe(false);
});
it('uses the account client and requires a committed terminal result',async()=>{
 let calls=0;const api=createClient({siteUrl:'https://example.test',getCredentials:async()=>({token:'fixture'}),fetchImpl:async(_url,options)=>{calls++;expect(options?.headers).toMatchObject({Authorization:'Bearer fixture',Accept:'application/x-ndjson, application/json'});return new Response('{"t":"text","d":"partial"}\n',{headers:{'Content-Type':'application/x-ndjson'}});}});
 await expect(streamAiStep({api,chatId:7},()=>{})).rejects.toMatchObject({code:'ai_incomplete_stream'});expect(calls).toBe(1);
});
it('accepts a JSON fallback when a proxy or idle endpoint returns a completed step',async()=>{
 const api=createClient({siteUrl:'https://example.test',getCredentials:async()=>null,fetchImpl:async()=>new Response(JSON.stringify({state:'done',chat}),{headers:{'Content-Type':'application/json'}})});
 expect(await streamAiStep({api,chatId:7},()=>{})).toEqual({state:'done',chat});
});
