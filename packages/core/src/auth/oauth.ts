// Flux App OAuth 2.0 + PKCE. Masaüstü: 127.0.0.1 loopback; mobil: <scheme>://oauth/callback.
import { platform } from '../platform';
import { trimEnd } from '../text';
import { createClient, ApiError } from '../api/client';
import type { TokenResponse } from '../api/types';
import { oauthClientId } from '../brand';
import { createPkce, randomString } from './pkce';

export class OAuthError extends Error {
  constructor(public code: 'state_mismatch' | 'iss_mismatch' | 'access_denied' | 'no_code' | 'cancelled' | string, message = code) {
    super(message);
  }
}

export interface AuthorizeOptions {
  /** "login": sign out first (second account on the same site). */
  prompt?: 'login';
  loginHint?: string;
  /** Default "flx"; the mobile app asks for "flx offline_access". */
  scope?: string;
}

export function authorizeUrl(siteUrl: string, p: { clientId: string; redirectUri: string; state: string; challenge: string } & AuthorizeOptions): string {
  const u = new URL(trimEnd(siteUrl) + '/wp-login.php');
  const q = new URLSearchParams({
    action: 'flxapp_authorize',
    response_type: 'code',
    client_id: p.clientId,
    redirect_uri: p.redirectUri,
    state: p.state,
    code_challenge: p.challenge,
    code_challenge_method: 'S256',
    scope: p.scope ?? 'flx',
  });
  if (p.prompt) q.set('prompt', p.prompt);
  if (p.loginHint) q.set('login_hint', p.loginHint);
  u.search = q.toString();
  return u.toString();
}

/** RFC 9207: the `iss` the server put on the redirect must be the site we started with (mix-up guard). */
export function issuerMatches(iss: string | null, siteUrl: string): boolean {
  if (!iss) return true;
  try {
    const a = new URL(iss);
    const b = new URL(siteUrl);
    return a.origin === b.origin && trimEnd(a.pathname) === trimEnd(b.pathname);
  } catch {
    return false;
  }
}

const anon = (siteUrl: string) => createClient({ siteUrl, getCredentials: async () => null });

export async function loginWithOAuth(siteUrl: string, opts: AuthorizeOptions = {}): Promise<TokenResponse> {
  const clientId = oauthClientId();
  const pkce = await createPkce();
  const state = randomString(16);
  const redirect = await platform().prepareOAuthRedirect('oauth/callback');
  await platform().openExternal(authorizeUrl(siteUrl, { clientId, redirectUri: redirect.redirectUri, state, challenge: pkce.challenge, ...opts }));
  const back = await redirect.wait();
  const q = back.searchParams;
  if (q.get('error')) throw new OAuthError(q.get('error') ?? 'error', q.get('error_description') ?? undefined);
  if (q.get('state') !== state) throw new OAuthError('state_mismatch');
  if (!issuerMatches(q.get('iss'), siteUrl)) throw new OAuthError('iss_mismatch');
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
