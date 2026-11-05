// Web preview has no Expo push token. The install id is still registered (notifications off).
import { useEffect, useState } from 'react';
import { type DeviceInfo } from '@fluxwp/runtime';
import { brand } from '@/brand';
import { deviceLocale } from '@/i18n';
import { APP_VERSION } from '@/platform';
import { installId } from './device';

export function PushRouter() {
  return null;
}

export function usePushDevice(): DeviceInfo | null {
  const [info, setInfo] = useState<DeviceInfo | null>(null);
  useEffect(() => {
    let live = true;
    void installId().then((deviceId) => {
      if (live) setInfo({ platform: 'web', brand: brand.slug, appVersion: APP_VERSION, deviceId, locale: deviceLocale() });
    });
    return () => {
      live = false;
    };
  }, []);
  return info;
}
