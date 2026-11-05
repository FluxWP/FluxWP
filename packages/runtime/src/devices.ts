// Push device registration (POST/DELETE flxapp/v1/devices). The app supplies the Expo token; this module
// talks to the site and remembers the server id so logout can delete the device (which ends its grant).
import { useEffect } from 'react';
import { ApiError, clientFor, platform, type Account, type ApiClient } from '@fluxwp/core';
import { useScope } from './scope';
import { WebSessionManager } from './web-session';

export interface DeviceInfo {
  /** Empty or omitted: notifications off, the device still shows in the user's list. */
  token?: string;
  provider?: 'expo';
  platform: 'ios' | 'android' | 'web' | 'macos' | 'windows' | 'linux';
  brand: string;
  appVersion: string;
  locale?: string;
  name?: string;
  model?: string;
  /** Stable id of this install. A new login with the same id closes the previous session. */
  deviceId: string;
}

export interface RegisteredDevice {
  id: string;
}

export const deviceStorageKey = (accountId: string) => `device:${accountId}`;

/** OAuth session required. API key / cookie → 403 flxapp_no_grant, returned as null (not an error). */
export async function registerDevice(api: ApiClient, info: DeviceInfo): Promise<RegisteredDevice | null> {
  try {
    const row = await api<{ id?: number | string }>('flxapp/v1/devices', {
      method: 'POST',
      body: {
        token: info.token ?? '',
        provider: info.provider ?? 'expo',
        platform: info.platform,
        brand: info.brand,
        app_version: info.appVersion,
        locale: info.locale,
        name: info.name,
        model: info.model,
        device_id: info.deviceId,
      },
    });
    return row?.id === undefined || row.id === null ? null : { id: String(row.id) };
  } catch (e) {
    if (e instanceof ApiError && (e.status === 403 || e.status === 404)) return null;
    throw e;
  }
}

export async function deleteDevice(api: ApiClient, id: string): Promise<void> {
  await api(`flxapp/v1/devices/${encodeURIComponent(id)}`, { method: 'DELETE' });
}

export async function storedDeviceId(accountId: string): Promise<string | null> {
  try {
    return (await platform().storage.get(deviceStorageKey(accountId))) || null;
  } catch {
    return null;
  }
}

/**
 * Ends the webview cookie session, then deletes the registered device (that closes the grant).
 * Returns whether the device record was deleted. Caller still revokes the refresh token when this
 * returns false (no device, or the site has no devices endpoint).
 */
export async function signOutRemote(account: Account): Promise<boolean> {
  if (account.authKind === 'demo') return false;
  const api = clientFor(account);
  if (account.authKind === 'oauth' || account.authKind === 'otp') {
    try {
      await new WebSessionManager(account, api, true).end();
    } catch {
      /* session/end is best-effort */
    }
  }
  const id = await storedDeviceId(account.id);
  if (!id) return false;
  try {
    await deleteDevice(api, id);
    try {
      await platform().storage.remove(deviceStorageKey(account.id));
    } catch {
      /* local id is dropped with the account store anyway */
    }
    return true;
  } catch {
    return false;
  }
}

/** Registers (or refreshes) this install on the open account. API-key accounts are skipped (403). */
export function useDeviceRegistration(info: DeviceInfo | null) {
  const { api, account } = useScope();
  const stamp = info ? `${info.deviceId}\0${info.token ?? ''}\0${info.platform}` : '';
  useEffect(() => {
    if (!info || account.authKind === 'apikey' || account.authKind === 'apppass') return;
    let cancelled = false;
    void (async () => {
      try {
        const row = await registerDevice(api, info);
        if (!cancelled && row) await platform().storage.set(deviceStorageKey(account.id), row.id);
      } catch {
        /* offline or not signed in yet: the next open retries */
      }
    })();
    return () => {
      cancelled = true;
    };
    // `stamp` is the primitive snapshot of `info`.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [api, account.id, account.authKind, stamp]);
}
