// Flux App OAuth 2.0 + PKCE. Masaüstü: 127.0.0.1 loopback; mobil: <scheme>://oauth/callback.
import { platform } from '../platform';
import { createClient, ApiError } from '../api/client';
import type { TokenResponse } from '../api/types';
import { oauthClientId } from '../brand';
import { createPkce, randomString } from './pkce';

export class OAuthError extends Error {
  constructor(public code: 'state_mismatch' | 'access_denied' | 'no_code' | 'cancelled' | string, message = code) {
    super(message);
  }
}

export function authorizeUrl(siteUrl: string, p: { clientId: string; redirectUri: string; state: string; challenge: string }): string {
  const u = new URL(siteUrl.replace(/\/+$/, '') + '/wp-login.php');
  u.search = new URLSearchParams({
    action: 'flxapp_authorize',
    response_type: 'code',
    client_id: p.clientId,
    redirect_uri: p.redirectUri,
    state: p.state,
    code_challenge: p.challenge,
    code_challenge_method: 'S256',
    scope: 'flx',
  }).toString();
  return u.toString();
}

const anon = (siteUrl: string) => createClient({ siteUrl, getCredentials: async () => null });

export async function loginWithOAuth(siteUrl: string): Promise<TokenResponse> {
  const clientId = oauthClientId();
  const pkce = await createPkce();
  const state = randomString(16);
  const redirect = await platform().prepareOAuthRedirect();
  await platform().openExternal(authorizeUrl(siteUrl, { clientId, redirectUri: redirect.redirectUri, state, challenge: pkce.challenge }));
  const back = await redirect.wait();
  const q = back.searchParams;
  if (q.get('error')) throw new OAuthError(q.get('error') ?? 'error', q.get('error_description') ?? undefined);
  if (q.get('state') !== state) throw new OAuthError('state_mismatch');
  const code = q.get('code');
  if (!code) throw new OAuthError('no_code');
  return anon(siteUrl)<TokenResponse>('flxapp/v1/oauth/token', {
    method: 'POST',
    anonymous: true,
    form: { grant_type: 'authorization_code', code, redirect_uri: redirect.redirectUri, client_id: clientId, code_verifier: pkce.verifier },
  });
}

export async function refreshTokens(siteUrl: string, refreshToken: string): Promise<TokenResponse> {
  return anon(siteUrl)<TokenResponse>('flxapp/v1/oauth/token', {
    method: 'POST',
    anonymous: true,
    form: { grant_type: 'refresh_token', refresh_token: refreshToken, client_id: oauthClientId() },
  });
}

export async function revokeToken(siteUrl: string, token: string): Promise<void> {
  try {
    await anon(siteUrl)('flxapp/v1/oauth/revoke', { method: 'POST', anonymous: true, form: { token, client_id: oauthClientId() } });
  } catch (e) {
    if (!(e instanceof ApiError)) throw e;
  }
}
