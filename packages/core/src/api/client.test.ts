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
