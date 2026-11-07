import { createHash } from 'node:crypto';
import { describe, expect, it, vi } from 'vitest';
import { buildUrl, createClient, ApiError } from './client';
import { normalizeSiteUrl } from '../auth/discovery';
import { pkceChallenge } from '../auth/pkce';

const json = (status: number, body: unknown) => new Response(JSON.stringify(body), { status });

describe('client', () => {
  it('url kurar, _locale=user ekler', () => {
    expect(buildUrl('https://x.org/', 'flxapp/v1/me', { a: 1, b: undefined })).toBe('https://x.org/wp-json/flxapp/v1/me?_locale=user&a=1');
  });

  it('401de bir kez yenileyip tekrar dener', async () => {
    const f = vi.fn<typeof fetch>()
      .mockResolvedValueOnce(json(401, { code: 'flxapp_invalid_token', message: 'x' }))
      .mockResolvedValueOnce(json(200, { ok: true }));
    const api = createClient({ siteUrl: 'https://x.org', fetchImpl: f, getCredentials: async () => ({ token: 'old' }), onUnauthorized: async () => ({ token: 'new' }) });
    await expect(api('flxapp/v1/me')).resolves.toEqual({ ok: true });
    const h = f.mock.calls[1]![1]!.headers as Record<string, string>;
    expect(h.Authorization).toBe('Bearer new');
  });

  it('WP_Error → ApiError', async () => {
    const f = vi.fn<typeof fetch>().mockImplementation(async () => json(403, { code: 'rest_forbidden', message: 'Yasak' }));
    const api = createClient({ siteUrl: 'https://x.org', fetchImpl: f, getCredentials: async () => null });
    await expect(api('x')).rejects.toMatchObject({ status: 403, code: 'rest_forbidden' });
    await expect(api('x')).rejects.toBeInstanceOf(ApiError);
  });

  it('X-FluxWP-Token yedeği', async () => {
    const f = vi.fn<typeof fetch>().mockResolvedValue(json(200, {}));
    const api = createClient({ siteUrl: 'https://x.org', fetchImpl: f, useHeaderFallback: true, getCredentials: async () => ({ token: 't' }) });
    await api('x');
    expect((f.mock.calls[0]![1]!.headers as Record<string, string>)['X-FluxWP-Token']).toBe('t');
  });

  it('raw: ETag, 304 ve X-WP-TotalPages', async () => {
    const f = vi.fn<typeof fetch>()
      .mockResolvedValueOnce(new Response(JSON.stringify([{ id: 1 }]), { status: 200, headers: { ETag: '"abc"', 'X-WP-Total': '40', 'X-WP-TotalPages': '2' } }))
      .mockResolvedValueOnce(new Response(null, { status: 304, headers: { ETag: '"abc"' } }))
      .mockResolvedValueOnce(new Response(null, { status: 304, headers: { ETag: '"abc"' } }));
    const api = createClient({ siteUrl: 'https://x.org', fetchImpl: f, getCredentials: async () => ({ token: 't' }) });
    const page = await api<unknown[]>('wp/v2/posts', { raw: true });
    expect(page.status).toBe(200);
    expect(page.etag).toBe('"abc"');
    expect(page.headers.get('X-WP-TotalPages')).toBe('2');
    expect(page.data).toEqual([{ id: 1 }]);
    const again = await api('flxapp/v1/config', { raw: true, headers: { 'If-None-Match': page.etag! } });
    expect(again).toMatchObject({ status: 304, data: null, etag: '"abc"' });
    expect((f.mock.calls[1]![1]!.headers as Record<string, string>)['If-None-Match']).toBe('"abc"');
    await expect(api('flxapp/v1/config', { headers: { 'If-None-Match': '"abc"' } })).resolves.toBeNull();
  });
});

describe('auth yardımcıları', () => {
  it('site adresini normalleştirir', () => {
    expect(normalizeSiteUrl('ornek.org/')).toBe('https://ornek.org');
    expect(normalizeSiteUrl('http://ornek.org/alt/wp-admin/edit.php')).toBe('http://ornek.org/alt');
  });
  it('PKCE S256', async () => {
    const v = 'verifier-ornek-123';
    const expected = createHash('sha256').update(v).digest('base64url');
    expect(await pkceChallenge(v)).toBe(expected);
  });
});

describe('authenticated response bodies',()=>{
 it('leaves a successful streaming body unread and keeps account/platform authentication',async()=>{
  const response=new Response('{"t":"text","d":"hello"}\n',{headers:{'Content-Type':'application/x-ndjson'}});
  const fetchImpl=vi.fn<typeof fetch>().mockResolvedValueOnce(json(401,{message:'expired'})).mockResolvedValueOnce(response);
  const refresh=vi.fn(async()=>({token:'fresh'}));
  const api=createClient({siteUrl:'https://x.org',fetchImpl,getCredentials:async()=>({token:'old'}),onUnauthorized:refresh,useHeaderFallback:true,extraHeaders:()=>({'X-WP-Nonce':'fixture-nonce'})});
  const result=await api<Response>('flx-ai/v1/chats/7/step',{method:'POST',body:{},as:'response',headers:{Accept:'application/x-ndjson'}});
  expect(result).toBe(response);expect(response.bodyUsed).toBe(false);expect(refresh).toHaveBeenCalledTimes(1);
  expect(fetchImpl.mock.calls[1]![1]!.headers).toMatchObject({'X-FluxWP-Token':'fresh','X-WP-Nonce':'fixture-nonce',Accept:'application/x-ndjson'});
  expect(await result.text()).toContain('hello');
 });
 it('keeps WP errors structured and does not repeat an ambiguous POST',async()=>{
  const fetchImpl=vi.fn<typeof fetch>().mockResolvedValue(json(503,{code:'busy',message:'try later'}));
  const api=createClient({siteUrl:'https://x.org',fetchImpl,getCredentials:async()=>({token:'t',scheme:'basic'})});
  await expect(api<Response>('step',{method:'POST',body:{},as:'response'})).rejects.toMatchObject({status:503,code:'busy'});expect(fetchImpl).toHaveBeenCalledTimes(1);expect(fetchImpl.mock.calls[0]![1]!.headers).toMatchObject({Authorization:'Basic t'});
 });
 it('does not refresh or send again after an aborted response',async()=>{
  const c=new AbortController();const refresh=vi.fn(async()=>({token:'fresh'}));const fetchImpl=vi.fn<typeof fetch>().mockImplementation(async()=>{c.abort();return json(401,{message:'expired'});});
  const api=createClient({siteUrl:'https://x.org',fetchImpl,getCredentials:async()=>({token:'t'}),onUnauthorized:refresh});
  await expect(api<Response>('step',{as:'response',signal:c.signal})).rejects.toMatchObject({code:'aborted'});expect(refresh).not.toHaveBeenCalled();expect(fetchImpl).toHaveBeenCalledTimes(1);
 });
 it('rejects conflicting response modes before sending credentials',async()=>{
  const credentials=vi.fn(async()=>({token:'t'}));const fetchImpl=vi.fn<typeof fetch>();const api=createClient({siteUrl:'https://x.org',fetchImpl,getCredentials:credentials});
  await expect(api('step',{as:'response',raw:true})).rejects.toMatchObject({code:'invalid_options'});expect(credentials).not.toHaveBeenCalled();expect(fetchImpl).not.toHaveBeenCalled();
 });
});
