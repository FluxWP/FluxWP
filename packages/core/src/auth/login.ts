import { createClient } from '../api/client';
import { flxapp } from '../api/flxapp';
import type { Me } from '../api/types';
import { accountsStore } from '../accounts/store';
import { fromTokenResponse } from '../accounts/tokens';
import { accountId, type Account, type AuthKind, type StoredTokens } from '../accounts/types';
import { demoFetch, DEMO_SITE } from '../demo/fetch';
import type { SiteInfo } from './discovery';
import { loginWithOAuth } from './oauth';

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

export async function loginOAuth(site: SiteInfo): Promise<Account> {
  const tokens = fromTokenResponse(await loginWithOAuth(site.url));
  return finish(site.url, 'oauth', tokens);
}

/** Kişisel API anahtarı (flxak_…), Kullanıcılar › Uygulama erişimi. */
export async function loginApiKey(site: SiteInfo, key: string): Promise<Account> {
  return finish(site.url, 'apikey', { access: key.trim() });
}

export async function loginDemo(): Promise<Account> {
  return finish(DEMO_SITE, 'demo', null, demoFetch);
}
