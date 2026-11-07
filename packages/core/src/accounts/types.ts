export type AuthKind = 'oauth' | 'otp' | 'apikey' | 'apppass' | 'demo';

export interface Account {
  /** `<host>#<userId>`: aynı siteye iki farklı kullanıcıyla bağlanılabilir. */
  id: string;
  siteUrl: string;
  siteName: string;
  siteIcon: string;
  userId: number;
  userName: string;
  userAvatar: string;
  authKind: AuthKind;
  addedAt: number;
  /** Yenileme başarısız: kullanıcı yeniden giriş yapmalı. */
  needsLogin?: boolean;
}

export interface StoredTokens {
  access: string;
  refresh?: string;
  /** ms epoch; API anahtarında yok. */
  expiresAt?: number;
}

export const accountId = (siteUrl: string, userId: number): string => `${new URL(siteUrl).host}#${userId}`;
