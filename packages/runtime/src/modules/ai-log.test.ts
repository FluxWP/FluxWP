import { expect, it } from 'vitest';
import { aiLogFacts } from './ai-log';
import { aiLogDemoBody, aiLogDemoRows } from '../demo/ai-log';
import en from '../i18n/en.json';
import tr from '../i18n/tr.json';
it('renders every returned metadata label in TR/EN and keeps fractional cost precision',()=>{
 const row=aiLogDemoRows()[2]!;
 for(const [locale,labels] of [['tr',tr.aiLog],['en',en.aiLog]] as const){
  const facts=aiLogFacts({...row,cost:.01234567,est:true,ok:0,error:'limit'},key=>(labels as Record<string,unknown>)[key.replace('aiLog.','')] as string,locale);
  for(const [key,value] of facts){expect((labels as Record<string,unknown>)[key],key).toBeTruthy();expect(value).not.toContain('undefined');expect(value).not.toContain('NaN');}
  const cost=facts.find(([key])=>key==='cost')![1]!;expect(cost).toContain(locale==='tr'?'0,01235':'0.01235');
 }
});
it('keeps saved content out of log metadata and represents missing content as 404',()=>{
 const rows=aiLogDemoRows();expect(JSON.stringify(rows)).not.toContain('req');expect(JSON.stringify(rows)).not.toContain('Summarize');
 const absent=aiLogDemoBody(rows.find(r=>!r.has_body)!.id);expect(absent).toBeInstanceOf(Response);expect((absent as Response).status).toBe(404);
 const saved=aiLogDemoBody(rows.find(r=>r.has_body)!.id);expect(saved).not.toBeInstanceOf(Response);expect('req' in saved).toBe(true);
});
