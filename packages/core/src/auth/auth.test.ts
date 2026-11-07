import { describe, expect, it, vi } from 'vitest';
import { createClient } from '../api/client';
import { brand, setBrand } from '../brand';
import { appPasswordUrl, basicToken } from './apppass';
import { authorizeUrl, issuerMatches } from './oauth';

describe('oauth', () => {
  it('adds prompt, login_hint and scope only when asked', () => {
    const base = { clientId: 'flx-mobil', redirectUri: 'fluxwp://oauth/callback', state: 's', challenge: 'c' };
    const plain = new URL(authorizeUrl('https://x.org', base));
    expect(plain.searchParams.get('scope')).toBe('flx');
    expect(plain.searchParams.has('prompt')).toBe(false);
    const second = new URL(authorizeUrl('https://x.org', { ...base, prompt: 'login', loginHint: 'ayse', scope: 'flx offline_access' }));
    expect(second.searchParams.get('prompt')).toBe('login');
    expect(second.searchParams.get('login_hint')).toBe('ayse');
    expect(second.searchParams.get('scope')).toBe('flx offline_access');
  });

  it('checks the issuer against the site (mix-up guard)', () => {
    expect(issuerMatches(null, 'https://x.org')).toBe(true);
    expect(issuerMatches('https://x.org/', 'https://x.org')).toBe(true);
    expect(issuerMatches('https://x.org/blog', 'https://x.org/blog/')).toBe(true);
    expect(issuerMatches('https://evil.org', 'https://x.org')).toBe(false);
    expect(issuerMatches('https://x.org/other', 'https://x.org/blog')).toBe(false);
  });
});

describe('application passwords', () => {
  it('builds the authorize URL and a UTF-8 Basic token', () => {
    const u = new URL(appPasswordUrl('https://x.org/wp-admin/authorize-application.php', { appName: 'FluxWP', appId: 'id', successUrl: 'fluxwp://appauth/callback' }));
    expect(u.searchParams.get('success_url')).toBe('fluxwp://appauth/callback');
    expect(u.searchParams.get('reject_url')).toBe('fluxwp://appauth/callback');
    expect(basicToken('ayşe', 'abcd 1234')).toBe(Buffer.from('ayşe:abcd 1234').toString('base64'));
  });

  it('sends Basic credentials', async () => {
    const f = vi.fn<typeof fetch>().mockResolvedValue(new Response('{}', { status: 200 }));
    const api = createClient({ siteUrl: 'https://x.org', fetchImpl: f, getCredentials: async () => ({ token: 'dTpw', scheme: 'basic' }) });
    await api('wp/v2/users/me');
    expect((f.mock.calls[0]![1]!.headers as Record<string, string>).Authorization).toBe('Basic dTpw');
  });
});

describe('brand', () => {
  it('setBrand replaces the shared object in place', () => {
    const before = { ...brand };
    setBrand({ ...before, name: 'Örnek', scheme: 'ornek' });
    expect(brand.name).toBe('Örnek');
    setBrand(before);
    expect(brand.name).toBe(before.name);
  });
});
