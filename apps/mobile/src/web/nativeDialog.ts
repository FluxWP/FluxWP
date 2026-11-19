// Site windows inside the webview (flx-app embed-native.js) opened as the phone's own dialogs:
// confirm/alert → system alert, prompt → system prompt (iOS) or the app's sheet (Android), a React
// screen's confirmation window → system alert with its buttons, a React menu → the app's action sheet.
// Resolves with the page's answer.
import { Alert, Platform, type AlertButton } from 'react-native';
import type { BridgeDialog } from '@fluxwp/runtime';
import { i18n } from '@/i18n';
import { overlays } from '@/ui/overlays';

export type DialogAnswer = { ok: boolean; value?: string; index?: number };

export function nativeDialog(d: BridgeDialog): Promise<DialogAnswer> {
  const ok = d.ok || i18n.t('mobile.web.ok');
  const cancel = d.cancel || i18n.t('common.cancel');
  // A title-less window shows its message as the alert title (bold), like iOS does for one-line alerts.
  const head = d.title || d.message;
  const body = d.title ? d.message || undefined : undefined;
  return new Promise<DialogAnswer>((resolve) => {
    let done = false;
    const end = (a: DialogAnswer) => {
      if (done) return;
      done = true;
      resolve(a);
    };
    switch (d.kind) {
      case 'alert':
        Alert.alert(head, body, [{ text: ok, onPress: () => end({ ok: true }) }], { cancelable: true, onDismiss: () => end({ ok: true }) });
        return;
      case 'confirm':
        Alert.alert(
          head,
          body,
          [
            { text: cancel, style: 'cancel', onPress: () => end({ ok: false }) },
            { text: ok, style: d.danger ? 'destructive' : 'default', onPress: () => end({ ok: true }) },
          ],
          { cancelable: true, onDismiss: () => end({ ok: false }) },
        );
        return;
      case 'prompt':
        if (Platform.OS === 'ios') {
          Alert.prompt(
            head,
            body ?? (d.placeholder ? d.placeholder : undefined),
            [
              { text: cancel, style: 'cancel', onPress: () => end({ ok: false }) },
              { text: ok, style: d.danger ? 'destructive' : 'default', onPress: (value?: string) => end({ ok: true, value: value ?? '' }) },
            ],
            'plain-text',
            d.value ?? '',
          );
          return;
        }
        void overlays
          .prompt({ title: head, fields: [{ name: 'value', type: 'text', label: body ?? '', placeholder: d.placeholder, default: d.value ?? '' }] })
          .then((v) => end(v ? { ok: true, value: String(v.value ?? '') } : { ok: false }));
        return;
      case 'menu': {
        const list = d.buttons ?? [];
        void overlays
          .menu(d.title, list.map((b, i) => ({ id: String(i), label: b.label, danger: b.danger })))
          .then((id) => end(id === null ? { ok: false, index: -1 } : { ok: true, index: Number(id) }));
        return;
      }
      case 'choice': {
        const list = d.buttons ?? [];
        const cancelAt = list.findIndex((b) => b.cancel);
        const buttons: AlertButton[] = list.map((b, index) => ({
          text: b.label,
          style: b.cancel ? 'cancel' : b.danger ? 'destructive' : 'default',
          onPress: () => end({ ok: !b.cancel, index }),
        }));
        Alert.alert(head, body, buttons, { cancelable: true, onDismiss: () => end({ ok: false, index: cancelAt }) });
        return;
      }
    }
  });
}
