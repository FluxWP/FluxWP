import { createClient } from '../api/client';
import { flxapp } from '../api/flxapp';
import type { Me } from '../api/types';
import { accountsStore } from '../accounts/store';
import { fromTokenResponse } from '../accounts/tokens';
import { accountId, type Account, type AuthKind, type StoredTokens } from '../accounts/types';
import { demoFetch, DEMO_SITE } from '../demo/fetch';
import { basicToken, requestAppPassword } from './apppass';
import type { SiteInfo } from './discovery';
import { loginWithOAuth, type AuthorizeOptions } from './oauth';
import { otpVerify } from './otp';

function toAccount(siteUrl: string, me: Me, kind: AuthKind): Account {
  return {
    id: accountId(siteUrl, me.user.id),
    siteUrl,
    siteName: me.site.name,
    siteIcon: me.site.icon,
    userId: me.user.id,
    userName: me.user.name,
    userAvatar: me.user.avatar,
    authKind: kind,
    addedAt: Date.now(),
  };
}

async function finish(siteUrl: string, kind: AuthKind, tokens: StoredTokens | null, fetchImpl?: typeof fetch) {
  const api = createClient({ siteUrl, fetchImpl, getCredentials: async () => (tokens ? { token: tokens.access } : null) });
  const me = await flxapp(api).me();
  const account = toAccount(siteUrl, me, kind);
  await accountsStore.getState().add(account, tokens);
  return account;
}

export async function loginOAuth(site: SiteInfo, opts?: AuthorizeOptions): Promise<Account> {
  const tokens = fromTokenResponse(await loginWithOAuth(site.url, opts));
  return finish(site.url, 'oauth', tokens);
}

/** Kodla giriş: otpStart() sonrası gelen kod. */
export async function loginOtp(site: SiteInfo, login: string, code: string): Promise<Account> {
  return finish(site.url, 'otp', fromTokenResponse(await otpVerify(site.url, login, code)));
}

/** Flux App olmayan site (sınırlı mod): WordPress Application Passwords + wp/v2/users/me. */
export async function loginAppPassword(site: SiteInfo, appName: string): Promise<Account> {
  const pass = await requestAppPassword(site.appPasswordsUrl, appName);
  const tokens: StoredTokens = { access: basicToken(pass.user, pass.password) };
  const api = createClient({ siteUrl: site.url, getCredentials: async () => ({ token: tokens.access, scheme: 'basic' }) });
  const u = await api<{ id: number; name: string; avatar_urls?: Record<string, string> }>('wp/v2/users/me', { query: { context: 'edit' } });
  const account: Account = {
    id: accountId(site.url, u.id),
    siteUrl: site.url,
    siteName: site.name,
    siteIcon: site.icon,
    userId: u.id,
    userName: u.name,
    userAvatar: u.avatar_urls?.['96'] ?? '',
    authKind: 'apppass',
    addedAt: Date.now(),
  };
  await accountsStore.getState().add(account, tokens);
  return account;
}

/** Kişisel API anahtarı (flxak_…), Kullanıcılar › Uygulama erişimi. */
export async function loginApiKey(site: SiteInfo, key: string): Promise<Account> {
  return finish(site.url, 'apikey', { access: key.trim() });
}

export async function loginDemo(): Promise<Account> {
  return finish(DEMO_SITE, 'demo', null, demoFetch);
}
