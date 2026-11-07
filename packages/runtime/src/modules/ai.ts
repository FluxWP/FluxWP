// Flux AI chat (flx-ai/v1). Streaming is NDJSON from POST /chats/<id>/step?stream=1
// (`t`: text | think | chat | result). A JSON body is accepted too (proxies that buffer the stream).
import { ApiError, type ApiClient } from '@fluxwp/core';

export interface AiMessage {
  role: string;
  content?: string;
  _thinking?: string;
  _name?: string;
  _id?: string; _ts?:number; _ms?:number; _ok?:boolean; _error?:boolean|string; _rejected?:boolean; _args?:Record<string,unknown>; _usage?:Record<string,number>;
  tool_call_id?:string; tool_calls?:Array<{id:string;type?:string;function:{name:string;arguments:string}}>;
}

export interface AiPending {
  id: string;
  name: string;
  title: string;
  source?: string;
  args?: Record<string, unknown>;
}

export interface AiChat {
  id: number;
  title: string;
  status: string;
  messages: AiMessage[];
  pending: AiPending[];
  total?: number; since?:number; archived?:number; updated?:number; model?:string; turn?:Record<string,number>;
}

export interface AiEvent {
  t: string;
  d?: string;
  state?: string;
  chat?: AiChat; id?:string; name?:string; code?:string;message?:string;status?:number;
}

export function parseNdjson(chunk: string, carry = ''): { events: AiEvent[]; rest: string } {
  const lines = (carry + chunk).split('\n');
  const rest = lines.pop() ?? '';
  const events: AiEvent[] = [];
  for (const line of lines) {
    const s = line.trim();
    if (!s) continue;
    try {
      const ev = JSON.parse(s) as AiEvent;
      if (ev && typeof ev.t === 'string') events.push(ev);
    } catch {
      /* padding or a partial line already kept in rest */
    }
  }
  return { events, rest };
}

export function messageText(m: AiMessage): string {
  return (m.content ?? '').trim();
}

/** Reads an NDJSON body, or one JSON object, and reports each event. */
export async function readAiStream(res: Response, onEvent: (ev: AiEvent) => void, signal?:AbortSignal): Promise<void> {
  const check=()=>{if(signal?.aborted)throw new ApiError(0,'aborted','aborted');};check();
  const type=res.headers.get('content-type')??'';
  if(!type.includes('ndjson')){const json=await res.json() as {state?:string;chat?:AiChat};check();onEvent({t:'result',...json});return;}
  // Older React Native transports buffer the response and expose no ReadableStream.
  if(!res.body){const text=await res.text();check();parseNdjson(text+'\n').events.forEach(onEvent);return;}
  const reader=res.body.getReader();const decoder=new TextDecoder();let carry='';
  const cancel=()=>{void reader.cancel().catch(()=>undefined);};signal?.addEventListener('abort',cancel,{once:true});
  try{for(;;){check();const {done,value}=await reader.read();check();if(done)break;const parsed=parseNdjson(decoder.decode(value,{stream:true}),carry);carry=parsed.rest;for(const event of parsed.events){check();onEvent(event);}}
    carry+=decoder.decode();if(carry.trim())for(const event of parseNdjson(carry+'\n').events){check();onEvent(event);}
  }finally{signal?.removeEventListener('abort',cancel);reader.releaseLock();}
}

export interface AiStepRequest { api:ApiClient;chatId:number;since?:number;signal?:AbortSignal }
/** Uses the account client, including refreshed credentials, platform headers and configured header fallback. */
export async function streamAiStep(req:AiStepRequest,onEvent:(ev:AiEvent)=>void):Promise<{state:string;chat:AiChat}>{
 const response=await req.api<Response>(`flx-ai/v1/chats/${req.chatId}/step`,{method:'POST',as:'response',query:{stream:1,since:req.since??0},body:{},headers:{Accept:'application/x-ndjson, application/json'},signal:req.signal});
 let result:{state:string;chat:AiChat}|null=null;
 await readAiStream(response,event=>{if(event.t==='error')throw new ApiError(event.status??500,event.code??'ai_stream_error',event.message??'The AI response failed.');if(event.t==='result'&&typeof event.state==='string'&&event.chat&&Number.isInteger(event.chat.id)&&Array.isArray(event.chat.messages)&&Array.isArray(event.chat.pending))result={state:event.state,chat:event.chat};onEvent(event);},req.signal);
 if(!result)throw new ApiError(502,'ai_incomplete_stream','The response was interrupted. Refresh the conversation before continuing.');
 return result;
}
