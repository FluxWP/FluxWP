// OTA identity for the update server's beta list: a random device code (MMKV, new on reinstall), the device
// model and the signed-in accounts as `site#userId` (no names or e-mails). Sent as request headers on the next
// expo-updates check; the keys must exist in app.config.ts updates.requestHeaders. docs/mobile/OTA.md
import * as Device from 'expo-device';
import * as ExpoCrypto from 'expo-crypto';
import * as Updates from 'expo-updates';
import Constants from 'expo-constants';
import { accountsStore } from '@fluxwp/core';
import { device } from './storage';

const KEY = 'ota.device';
const SENT = 'ota.headers';

/** This install's code, shown in Settings so it can be added to the beta list. */
export function otaDeviceCode(): string {
  let id = device.getString(KEY);
  if (!id) device.set(KEY, (id = ExpoCrypto.randomUUID().toLowerCase()));
  return id;
}

export function otaEnabled(): boolean {
  return Updates.isEnabled;
}

function sync(): void {
  if (!Updates.isEnabled) return;
  const accounts = accountsStore
    .getState()
    .accounts.filter((a) => a.authKind !== 'demo')
    .map((a) => a.id.toLowerCase())
    .filter((id) => /^[a-z0-9.:/_~-]+#[0-9]+$/.test(id))
    .slice(0, 10);
  const channel = (Constants.expoConfig?.extra as { otaChannel?: string } | undefined)?.otaChannel || 'production';
  const headers = {
    'expo-channel-name': channel,
    'flux-device': otaDeviceCode(),
    'flux-device-name': [Device.modelName, Device.osVersion].filter(Boolean).join(' ').replace(/[^\w .,()+-]/g, '').slice(0, 60),
    'flux-accounts': accounts.join(','),
  };
  const json = JSON.stringify(headers);
  if (device.getString(SENT) === json) return;
  try {
    Updates.setUpdateRequestHeadersOverride(headers);
    device.set(SENT, json);
  } catch {
    // An older native build without these header keys: keep the embedded headers, OTA still works.
  }
}

/** Keeps the update request headers in step with the account list. */
export function startOtaIdentity(): () => void {
  sync();
  return accountsStore.subscribe((s, prev) => {
    if (s.accounts !== prev.accounts) sync();
  });
}
