import { createHash } from 'node:crypto';
import { describe, expect, it, vi } from 'vitest';
import { buildUrl, createClient, ApiError } from './client';
import { normalizeSiteUrl } from '../auth/discovery';
import { pkceChallenge } from '../auth/pkce';

const json = (status: number, body: unknown) => new Response(JSON.stringify(body), { status });

describe('client', () => {
  it('url kurar, _locale=user ekler', () => {
    expect(buildUrl('https://x.org/', 'flxapp/v1/me', { a: 1, b: undefined })).toBe('https://x.org/wp-json/flxapp/v1/me?_locale=user&a=1');
    expect(buildUrl('https://x.org/blog/', '/flxapp/v1/me')).toBe('https://x.org/blog/wp-json/flxapp/v1/me?_locale=user');
    expect(buildUrl('https://x.org/blog', 'https://x.org/blog/wp-content/uploads/a.png')).toBe('https://x.org/blog/wp-content/uploads/a.png');
  });

  it('rejects paths that leave the site before any request', async () => {
    const f = vi.fn<typeof fetch>();
    const creds = vi.fn(async () => ({ token: 'secret' }));
    const api = createClient({ siteUrl: 'https://x.org/blog', fetchImpl: f, getCredentials: creds });
    await expect(api('\\\\evil.test/x')).rejects.toMatchObject({ name: 'ApiError', code: 'foreign_url' });
    await expect(api('//evil.test/x')).rejects.toMatchObject({ code: 'foreign_url' });
    await expect(api('https://evil.test/a')).rejects.toMatchObject({ code: 'foreign_url' });
    await expect(api('https://x.org/other')).rejects.toMatchObject({ code: 'foreign_url' });
    await expect(api('https://x.org/blog/../other')).rejects.toMatchObject({ code: 'foreign_url' });
    await expect(api('https://x.org/blog-evil/a')).rejects.toMatchObject({ code: 'foreign_url' });
    await expect(api('https://user:pass@x.org/blog/wp-json/a')).rejects.toMatchObject({ code: 'bad_url' });
    await expect(api('javascript:alert(1)')).rejects.toMatchObject({ code: 'foreign_url' });
    await expect(api('flx\r\nX: 1')).rejects.toMatchObject({ code: 'bad_url' });
    await expect(api('http://x.org/blog/a')).rejects.toMatchObject({ code: 'foreign_url' });
    expect(f).not.toHaveBeenCalled();
    expect(creds).not.toHaveBeenCalled();
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

  it('sends no cookies with a token (a shared wp-admin cookie would make the token be ignored)', async () => {
    const f = vi.fn<typeof fetch>().mockImplementation(async () => json(200, {}));
    await createClient({ siteUrl: 'https://x.org', fetchImpl: f, getCredentials: async () => ({ token: 't' }) })('x');
    expect(f.mock.calls[0]![1]!.credentials).toBe('omit');
    await createClient({ siteUrl: 'https://x.org', fetchImpl: f, credentials: 'same-origin', getCredentials: async () => ({ token: 't' }) })('x');
    expect(f.mock.calls[1]![1]!.credentials).toBe('same-origin');
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
