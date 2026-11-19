// The RN Renderer: navigation and UI ports the runtime calls (plan §2.2).
import { useMemo } from 'react';
import { Share } from 'react-native';
import * as Clipboard from 'expo-clipboard';
import * as Haptics from 'expo-haptics';
import { router } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import * as Linking from 'expo-linking';
import type { Manifest, ScreenType } from '@fluxwp/manifest';
import type { Renderer } from '@fluxwp/runtime';
import { i18n } from '@/i18n';
import { NATIVE } from '@/modules/registry';
import { hrefFor } from '@/shell/routes';
import { overlays } from '@/ui/overlays';

/**
 * FluxWP plugin screens open the site's own mobile screen in the webview: the same React screen, tabs and
 * data as the site at phone width. Core screens (posts, pages, media, orders) and native components stay
 * native. Packages owned elsewhere keep their manifest screens until their owners move them.
 */
const KEEP_NATIVE = new Set(['flx-crm', 'flx-donate', 'flx-commerce', 'flx-mail', 'flx-forms', 'flx-certificates', 'flx-chat']);
export const preferWeb = (m: Manifest): boolean => !!m.plugin?.startsWith('flx-') && !KEEP_NATIVE.has(m.plugin);

/** Screen types this renderer draws; others open their web fallback. */
export const SCREENS: ReadonlySet<ScreenType> = new Set(['list', 'detail', 'form', 'dashboard', 'native', 'web']);

export function useRenderer(accountId: string): Renderer {
  return useMemo<Renderer>(
    () => ({
      screens: SCREENS,
      native: new Set(Object.keys(NATIVE)),
      preferWeb,
      t: (key, vars) => i18n.t(key, vars),
      nav: {
        push: (route) => {
          if (route.kind === 'url') return void openUrl(route.url);
          const href = hrefFor(accountId, route);
          if (href) router.push(href);
        },
        replace: (route) => {
          const href = hrefFor(accountId, route);
          if (href) router.replace(href);
        },
        back: () => (router.canGoBack() ? router.back() : undefined),
        openUrl: (url) => void openUrl(url),
      },
      ui: {
        confirm: overlays.confirm,
        prompt: overlays.prompt,
        toast: overlays.toast,
        copy: async (text) => void (await Clipboard.setStringAsync(text)),
        share: async ({ text, url }) => void (await Share.share({ message: [text, url].filter(Boolean).join('\n'), url })),
        haptic: (k) => {
          if (k === 'light') void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
          else void Haptics.notificationAsync(k === 'success' ? Haptics.NotificationFeedbackType.Success : k === 'warning' ? Haptics.NotificationFeedbackType.Warning : Haptics.NotificationFeedbackType.Error);
        },
      },
    }),
    [accountId],
  );
}

async function openUrl(url: string): Promise<void> {
  if (/^https:\/\//i.test(url)) await WebBrowser.openBrowserAsync(url);
  else if (/^(tel|mailto|sms|whatsapp):/i.test(url)) await Linking.openURL(url);
}
