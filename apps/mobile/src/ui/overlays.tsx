// Toast, confirm dialog and prompt sheet: the RendererUI ports. One store, one host at the root.
// (RN's Alert has no buttons on web, so the confirm is our own modal on every platform.)
import { useState } from 'react';
import { Modal, Pressable, View } from 'react-native';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { create } from 'zustand';
import type { FormField } from '@fluxwp/manifest';
import type { ConfirmRequest } from '@fluxwp/runtime';
import { i18n } from '@/i18n';
import { Icon } from './Icon';
import { Button, Field, Segmented, Text } from './primitives';

interface Overlay {
  toast: { id: number; text: string; tone: 'ok' | 'error' } | null;
  confirm: (ConfirmRequest & { resolve: (ok: boolean) => void }) | null;
  prompt: { title: string; fields: FormField[]; resolve: (v: Record<string, unknown> | null) => void } | null;
  menu: { title: string; items: MenuItem[]; resolve: (id: string | null) => void } | null;
}

export interface MenuItem {
  id: string;
  label: string;
  icon?: string;
  danger?: boolean;
}

const useOverlay = create<Overlay>(() => ({ toast: null, confirm: null, prompt: null, menu: null }));
let seq = 0;

export const overlays = {
  toast(text: string, tone: 'ok' | 'error' = 'ok') {
    const id = ++seq;
    useOverlay.setState({ toast: { id, text, tone } });
    setTimeout(() => useOverlay.getState().toast?.id === id && useOverlay.setState({ toast: null }), 2800);
  },
  confirm: (req: ConfirmRequest) => new Promise<boolean>((resolve) => useOverlay.setState({ confirm: { ...req, resolve } })),
  prompt: (req: { title: string; fields: FormField[] }) => new Promise<Record<string, unknown> | null>((resolve) => useOverlay.setState({ prompt: { ...req, resolve } })),
  /** Action sheet; resolves with the chosen item id or null. */
  menu: (title: string, items: MenuItem[]) => new Promise<string | null>((resolve) => useOverlay.setState({ menu: { title, items, resolve } })),
};

export function OverlayHost() {
  const { toast, confirm, prompt, menu } = useOverlay();
  const insets = useSafeAreaInsets();
  const close = (ok: boolean) => {
    confirm?.resolve(ok);
    useOverlay.setState({ confirm: null });
  };
  return (
    <>
      {toast ? (
        <View pointerEvents="none" style={[styles.toastWrap, { bottom: insets.bottom + 88 }]} accessibilityLiveRegion="polite">
          <View style={[styles.toast, toast.tone === 'error' && styles.toastError]}>
            <Text style={styles.toastText}>{toast.text}</Text>
          </View>
        </View>
      ) : null}
      <Modal transparent visible={!!confirm} animationType="fade" onRequestClose={() => close(false)}>
        <Pressable style={styles.backdrop} onPress={() => close(false)}>
          <Pressable style={styles.dialog} onPress={() => undefined}>
            <Text variant="heading">{confirm?.title}</Text>
            {confirm?.body ? <Text tone="muted">{confirm.body}</Text> : null}
            <View style={styles.row}>
              <Button kind="ghost" label={i18n.t('common.cancel')} onPress={() => close(false)} />
              <Button kind={confirm?.tone === 'danger' ? 'danger' : 'primary'} label={confirm?.ok ?? i18n.t('action.confirm')} onPress={() => close(true)} />
            </View>
          </Pressable>
        </Pressable>
      </Modal>
      {prompt ? <PromptSheet key={prompt.title} {...prompt} /> : null}
      {menu ? <MenuSheet {...menu} /> : null}
    </>
  );
}

function MenuSheet({ title, items, resolve }: NonNullable<Overlay['menu']>) {
  const insets = useSafeAreaInsets();
  const { theme } = useUnistyles();
  const done = (id: string | null) => {
    resolve(id);
    useOverlay.setState({ menu: null });
  };
  return (
    <Modal transparent visible animationType="slide" onRequestClose={() => done(null)}>
      <Pressable style={styles.sheetBackdrop} onPress={() => done(null)}>
        <View style={[styles.sheet, { paddingBottom: insets.bottom + 8 }]}>
          <Text variant="label" numberOfLines={1}>{title}</Text>
          {items.map((it) => (
            <Pressable key={it.id} accessibilityRole="button" onPress={() => done(it.id)} style={({ pressed }) => [styles.menuItem, pressed && styles.menuPressed]}>
              <Icon name={it.icon} size={20} color={it.danger ? theme.colors.danger : theme.colors.text} />
              <Text tone={it.danger ? 'danger' : undefined}>{it.label}</Text>
            </Pressable>
          ))}
        </View>
      </Pressable>
    </Modal>
  );
}

function PromptSheet({ title, fields, resolve }: NonNullable<Overlay['prompt']>) {
  const [values, setValues] = useState<Record<string, unknown>>(() => Object.fromEntries(fields.map((f) => [f.name, f.default ?? ''])));
  const insets = useSafeAreaInsets();
  const done = (v: Record<string, unknown> | null) => {
    resolve(v);
    useOverlay.setState({ prompt: null });
  };
  const missing = fields.some((f) => f.required && !values[f.name]);
  return (
    <Modal transparent visible animationType="slide" onRequestClose={() => done(null)}>
      <Pressable style={styles.sheetBackdrop} onPress={() => done(null)}>
        <Pressable style={[styles.sheet, { paddingBottom: insets.bottom + 16 }]} onPress={() => undefined}>
          <Text variant="heading">{title}</Text>
          {fields.map((f) =>
            (f.type === 'segmented' || f.type === 'radio') && Array.isArray(f.options) ? (
              <View key={f.name} style={styles.gap}>
                <Text variant="label">{f.label}</Text>
                <Segmented options={f.options} value={String(values[f.name] ?? '')} onChange={(v) => setValues((s) => ({ ...s, [f.name]: v }))} />
              </View>
            ) : (
              <Field key={f.name} label={f.label} value={String(values[f.name] ?? '')} multiline={f.type === 'textarea'} onChangeText={(v) => setValues((s) => ({ ...s, [f.name]: v }))} />
            ),
          )}
          <Button label={i18n.t('action.confirm')} disabled={missing} onPress={() => done(values)} full />
        </Pressable>
      </Pressable>
    </Modal>
  );
}

const styles = StyleSheet.create((theme) => ({
  toastWrap: { position: 'absolute', left: 16, right: 16, alignItems: 'center' },
  toast: { backgroundColor: theme.mode === 'dark' ? '#E8EBF0' : '#14171C', borderRadius: theme.radius.lg, paddingHorizontal: 16, paddingVertical: 12, maxWidth: 480 },
  toastError: { backgroundColor: theme.colors.danger },
  toastText: { color: theme.mode === 'dark' ? '#14171C' : '#FFFFFF', fontWeight: '600' },
  backdrop: { flex: 1, backgroundColor: theme.colors.overlay, alignItems: 'center', justifyContent: 'center', padding: 24 },
  dialog: { backgroundColor: theme.colors.surface, borderRadius: theme.radius.xl, padding: 20, gap: 12, width: '100%', maxWidth: 420 },
  row: { flexDirection: 'row', justifyContent: 'flex-end', gap: 8 },
  sheetBackdrop: { flex: 1, backgroundColor: theme.colors.overlay, justifyContent: 'flex-end' },
  sheet: { backgroundColor: theme.colors.surface, borderTopLeftRadius: theme.radius.xl, borderTopRightRadius: theme.radius.xl, padding: 20, gap: 14 },
  gap: { gap: 6 },
  menuItem: { flexDirection: 'row', alignItems: 'center', gap: 14, minHeight: 48, paddingHorizontal: 4 },
  menuPressed: { opacity: 0.6 },
}));
