// Kodla giriş (bağışçı/müşteri): flxapp/v1/otp/* (docs/flx-app/API.md). Personel OAuth kullanır.
import { createClient } from '../api/client';
import type { TokenResponse } from '../api/types';
import { oauthClientId } from '../brand';

export interface OtpStart {
  sent: boolean;
  via: string;
  via_label: string;
  ttl: number;
}

const anon = (siteUrl: string) => createClient({ siteUrl, getCredentials: async () => null });

/** Always answers `sent: true`; the server does not reveal whether the account exists. */
export const otpStart = (siteUrl: string, login: string) =>
  anon(siteUrl)<OtpStart>('flxapp/v1/otp/start', { method: 'POST', anonymous: true, body: { login: login.trim(), client_id: oauthClientId() } });

export const otpVerify = (siteUrl: string, login: string, code: string) =>
  anon(siteUrl)<TokenResponse>('flxapp/v1/otp/verify', { method: 'POST', anonymous: true, body: { login: login.trim(), code: code.trim(), client_id: oauthClientId() } });
