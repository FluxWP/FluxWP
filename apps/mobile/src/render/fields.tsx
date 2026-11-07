// Image/file fields (upload with progress) and user/term/post pickers. The form value is a
// `{ id, url?, label? }` while editing; submit sends the id (runtime submitValues).
import { useEffect, useState } from 'react';
import { Platform, Pressable, View } from 'react-native';
import { Image } from 'expo-image';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { uploadMedia, useApi, type RelationValue } from '@fluxwp/runtime';
import type { FormField } from '@fluxwp/manifest';
import { overlays } from '@/ui/overlays';
import { Button, Field, Text } from '@/ui/primitives';

const labelOf = (row: Record<string, unknown>): string => {
  if (typeof row.name === 'string' && row.name) return row.name;
  const title = row.title;
  if (typeof title === 'string') return title;
  if (title && typeof title === 'object' && typeof (title as { rendered?: unknown }).rendered === 'string') return (title as { rendered: string }).rendered;
  return String(row.id ?? '');
};

async function asFormData(uri: string, name: string, type: string): Promise<FormData> {
  const body = new FormData();
  if (Platform.OS === 'web') {
    const blob = await (await fetch(uri)).blob();
    body.append('file', blob, name);
  } else {
    body.append('file', { uri, name, type } as unknown as Blob);
  }
  return body;
}

export function MediaField({ field, value, onChange }: { field: FormField & { readonly?: boolean }; value: RelationValue | null; onChange: (v: RelationValue | null) => void }) {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const api = useApi();
  const [ratio, setRatio] = useState<number | null>(null);

  const send = async (uri: string, name: string, type: string) => {
    setRatio(0);
    try {
      const file = await asFormData(uri, name, type);
      const up = await uploadMedia(api, file, { path: field.upload, onProgress: setRatio });
      onChange(up.id ? { id: up.id, url: up.url || uri, label: name } : null);
    } catch {
      overlays.toast(t('action.failed'), 'error');
    } finally {
      setRatio(null);
    }
  };

  const pick = async () => {
    if (field.type === 'file') {
      const DocumentPicker = await import('expo-document-picker');
      const res = await DocumentPicker.getDocumentAsync({ copyToCacheDirectory: true, type: '*/*' });
      if (res.canceled || !res.assets?.[0]) return;
      const a = res.assets[0];
      await send(a.uri, a.name, a.mimeType ?? 'application/octet-stream');
      return;
    }
    const ImagePicker = await import('expo-image-picker');
    const res = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], quality: 0.85 });
    if (res.canceled || !res.assets[0]) return;
    const a = res.assets[0];
    await send(a.uri, a.fileName ?? 'image.jpg', a.mimeType ?? 'image/jpeg');
  };

  return (
    <View style={styles.gap}>
      <Text variant="label">{field.label}{field.required ? ' *' : ''}</Text>
      {value?.url && field.type === 'image' ? <Image source={{ uri: value.url }} style={styles.preview} contentFit="cover" accessibilityLabel={field.label} /> : null}
      {value && field.type === 'file' ? <Text>{value.label || `#${value.id}`}</Text> : null}
      {ratio !== null ? (
        <View style={styles.track} accessibilityRole="progressbar">
          <View style={[styles.bar, { width: `${Math.round(ratio * 100)}%`, backgroundColor: theme.colors.primary }]} />
        </View>
      ) : null}
      <View style={styles.row}>
        <Button kind="secondary" icon={field.type === 'file' ? 'media' : 'image'} label={ratio !== null ? t('form.uploading') : value ? t('form.change') : t('form.upload')} loading={ratio !== null} onPress={() => void pick()} disabled={field.readonly} />
        {value ? <Button kind="ghost" label={t('form.remove')} onPress={() => onChange(null)} disabled={field.readonly} /> : null}
      </View>
      {field.help ? <Text variant="small" tone="muted">{field.help}</Text> : null}
    </View>
  );
}

function useDebounced(value: string): string {
  const [v, setV] = useState(value);
  useEffect(() => {
    const id = setTimeout(() => setV(value), 300);
    return () => clearTimeout(id);
  }, [value]);
  return v;
}

export function EntityField({ field, value, onChange }: { field: FormField & { readonly?: boolean }; value: RelationValue | null; onChange: (v: RelationValue | null) => void }) {
  const { t } = useTranslation();
  const api = useApi();
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState('');
  const query = useDebounced(q);
  const path = field.type === 'user' ? 'wp/v2/users' : field.type === 'post' ? 'wp/v2/posts' : `wp/v2/${field.taxonomy || 'categories'}`;

  const named = useQuery({
    queryKey: ['entity', path, value?.id],
    enabled: !!value?.id && !value.label,
    queryFn: async () => labelOf(await api<Record<string, unknown>>(`${path}/${value!.id}`, { query: { context: 'edit', _fields: 'id,name,title' } })),
  });
  const shown = value ? value.label || named.data || `#${value.id}` : t('form.choose');

  const list = useQuery({
    queryKey: ['pick', path, query],
    enabled: open,
    queryFn: () => api<Record<string, unknown>[]>(path, { query: { search: query, per_page: 20, context: 'edit', _fields: 'id,name,title' } }),
  });

  return (
    <View style={styles.gap}>
      <Text variant="label">{field.label}{field.required ? ' *' : ''}</Text>
      <View style={styles.row}>
        <Text style={styles.flex}>{shown}</Text>
        <Button kind="secondary" label={value ? t('form.change') : t('form.choose')} onPress={() => setOpen((o) => !o)} disabled={field.readonly} />
        {value ? <Button kind="ghost" label={t('form.remove')} onPress={() => onChange(null)} disabled={field.readonly} /> : null}
      </View>
      {open ? (
        <View style={styles.gap}>
          <Field label={t('form.search')} value={q} onChangeText={setQ} autoCapitalize="none" autoCorrect={false} />
          {(Array.isArray(list.data) ? list.data : []).map((row) => {
            const id = Number(row.id);
            const label = labelOf(row);
            return (
              <Pressable key={id} accessibilityRole="button" onPress={() => (onChange({ id, label }), setOpen(false))} style={styles.hit}>
                <Text>{label}</Text>
              </Pressable>
            );
          })}
        </View>
      ) : null}
      {field.help ? <Text variant="small" tone="muted">{field.help}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  gap: { gap: 8 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 8, flexWrap: 'wrap' },
  flex: { flex: 1 },
  preview: { width: 96, height: 96, borderRadius: theme.radius.md, backgroundColor: theme.colors.surface2 },
  track: { height: 4, borderRadius: 2, backgroundColor: theme.colors.surface3, overflow: 'hidden' },
  bar: { height: 4 },
  hit: { minHeight: 44, justifyContent: 'center' },
}));
