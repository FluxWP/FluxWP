// Mobile ports for the shared engines: AppState visibility, per-account MMKV, haptic beep.
import { platform } from '@fluxwp/core';
import { browserPorts, taskText, type ModulePorts } from '@fluxwp/runtime';
import * as Haptics from 'expo-haptics';
import { device } from '@/platform/storage';
import { i18n } from '@/i18n';

export function mobilePorts(accountId: string): ModulePorts {
  const base = browserPorts();
  let vis: ReturnType<typeof platform>['visibility'];
  try {
    vis = platform().visibility;
  } catch {
    vis = undefined;
  }
  const prefix = `mod.${accountId}.`;
  return {
    ...base,
    visible: () => vis?.visible() ?? true,
    onVisibility: (fn) => vis?.subscribe(fn) ?? (() => undefined),
    storage: {
      get: (k) => device.getString(prefix + k) ?? null,
      set: (k, v) => {
        if (v === null) device.remove(prefix + k);
        else device.set(prefix + k, v);
      },
    },
    beep: () => {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => undefined);
    },
    showNotification: (n) => {
      try {
        void platform().notify?.({ title: n.title, body: n.body });
      } catch {
        /* platform not ready */
      }
    },
    t: (text, vars) => taskText(i18n.language, text, vars),
  };
}
