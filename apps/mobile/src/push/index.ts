// Expo push (plan a6). The token is registered per account by runtime useDeviceRegistration.
// A tap is {v, site, user, target, params}; routePush picks the account. No Firebase config:
// without an EAS project id the token stays empty and the device is registered as notifications-off.
import { useEffect, useState } from 'react';
import { Platform } from 'react-native';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import Constants from 'expo-constants';
import { router } from 'expo-router';
import { accountsStore } from '@fluxwp/core';
import { pushDataOf, routePush, type DeviceInfo } from '@fluxwp/runtime';
import { brand } from '@/brand';
import { deviceLocale } from '@/i18n';
import { APP_VERSION } from '@/platform';
import { hrefFor } from '@/shell/routes';
import { installId } from './device';

Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowBanner: true,
    shouldShowList: true,
    shouldPlaySound: false,
    shouldSetBadge: false,
  }),
});

async function ensurePushToken(): Promise<string | undefined> {
  if (Platform.OS === 'web' || !Device.isDevice) return undefined;
  const projectId = Constants.expoConfig?.extra?.eas?.projectId ?? Constants.easConfig?.projectId;
  // Do not request notification permission when there is no configured delivery backend.
  if (!projectId) return undefined;
  const current = await Notifications.getPermissionsAsync();
  const status = current.granted || current.status === 'granted' ? current.status : (await Notifications.requestPermissionsAsync()).status;
  if (status !== 'granted') return undefined;
  try {
    return (await Notifications.getExpoPushTokenAsync({ projectId })).data;
  } catch {
    return undefined;
  }
}

function openPush(raw: unknown) {
  const data = pushDataOf(raw);
  if (!data) return;
  const hit = routePush(data, accountsStore.getState().accounts, () => ({ appVersion: APP_VERSION, native: new Set(['dashboard']) }));
  if (!hit) return;
  const href = hrefFor(hit.account.id, hit.route);
  if (href) router.push(href);
}

/** Foreground banner + tap routing. Mount once at the root. */
export function PushRouter() {
  useEffect(() => {
    const tap = Notifications.addNotificationResponseReceivedListener((response) => openPush(response.notification.request.content.data));
    void Notifications.getLastNotificationResponseAsync().then((response) => {
      if (response) openPush(response.notification.request.content.data);
    });
    return () => tap.remove();
  }, []);
  return null;
}

/** Device description for POST /devices. Null until the install id (and token, if any) is ready. */
export function usePushDevice(): DeviceInfo | null {
  const [info, setInfo] = useState<DeviceInfo | null>(null);
  useEffect(() => {
    let live = true;
    void (async () => {
      const deviceId = await installId();
      const token = await ensurePushToken();
      if (!live) return;
      setInfo({
        token,
        platform: Platform.OS === 'ios' ? 'ios' : 'android',
        brand: brand.slug,
        appVersion: APP_VERSION,
        locale: deviceLocale(),
        deviceId,
        name: Device.deviceName ?? undefined,
        model: Device.modelName ?? undefined,
      });
    })();
    return () => {
      live = false;
    };
  }, []);
  return info;
}
