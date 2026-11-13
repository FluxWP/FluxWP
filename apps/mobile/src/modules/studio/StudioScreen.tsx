// Flux Studio: prompt, generate, gallery. Polls while something is still running.
import { useEffect, useMemo, useState } from 'react';
import { Image as RNImage, TextInput, View, useWindowDimensions } from 'react-native';
import { FlashList } from '@shopify/flash-list';
import { Image } from 'expo-image';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { studioBusy, studioGenerateBody, useAccount, useApi, useRenderer, type StudioItem, type StudioKind } from '@fluxwp/runtime';
import { Button, EmptyState, Skeleton, Spinner, Text } from '@/ui/primitives';
import { useScreenTitle } from '../kit';
import { ChromeFrame } from '@/shell/chrome';

const swatch = (id: number) => ['#1D7932', '#0f766e', '#7c3aed', '#2563eb'][Math.abs(id) % 4]!;

interface Items { items: StudioItem[] }

export default function StudioScreen(_props: { params: Record<string, string> }) {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const { width } = useWindowDimensions();
  useScreenTitle(t('mobile.screens.studio.title'));
  const api = useApi();
  const account = useAccount();
  const renderer = useRenderer();
  const qc = useQueryClient();
  const [prompt, setPrompt] = useState('');
  const [kind, setKind] = useState<StudioKind>('image');
  const key = useMemo(() => ['studio', account.id] as const, [account.id]);
  const q = useQuery({ queryKey: key, queryFn: () => api<Items>('flx-studio/v1/items') });
  const items = q.data?.items ?? [];
  const jobs = studioBusy(items).join(',');
  const cell = Math.floor((width - 16) / 2);

  useEffect(() => {
    if (!jobs) return;
    const timer = setInterval(() => {
      void api<Items>('flx-studio/v1/poll', { query: { jobs } }).then((r) => {
        qc.setQueryData<Items>(key, (old) => {
          if (!old) return { items: r.items };
          const map = new Map(r.items.map((i) => [i.id, i]));
          return { items: old.items.map((i) => map.get(i.id) ?? i) };
        });
      }).catch(() => undefined);
    }, 2500);
    return () => clearInterval(timer);
  }, [api, jobs, qc, key]);

  const gen = useMutation({
    mutationFn: () => api<Items>('flx-studio/v1/generate', { method: 'POST', body: studioGenerateBody({ prompt, kind }) }),
    onSuccess: (r) => {
      setPrompt('');
      qc.setQueryData<Items>(key, (old) => ({ items: [...r.items, ...(old?.items ?? [])] }));
    },
    onError: (e) => renderer.ui.toast(e instanceof Error ? e.message : String(e), 'error'),
  });

  return (
    <ChromeFrame testID="screen-studio" style={styles.fill}>
      <View style={styles.form}>
        <View style={styles.kinds}>
          <Button kind={kind === 'image' ? 'primary' : 'secondary'} label={t('mobile.screens.studio.image')} onPress={() => setKind('image')} />
          <Button kind={kind === 'video' ? 'primary' : 'secondary'} label={t('mobile.screens.studio.video')} onPress={() => setKind('video')} />
        </View>
        <TextInput
          testID="studio-prompt"
          value={prompt}
          onChangeText={setPrompt}
          placeholder={t('mobile.screens.studio.prompt')}
          placeholderTextColor={theme.colors.textMuted}
          multiline
          style={styles.input}
          accessibilityLabel={t('mobile.screens.studio.prompt')}
        />
        <Button label={t('mobile.screens.studio.generate')} loading={gen.isPending} disabled={!prompt.trim()} onPress={() => (prompt.trim() ? gen.mutate() : renderer.ui.toast(t('mobile.screens.studio.need'), 'error'))} full />
      </View>
      <Text variant="label" style={styles.gallery}>{t('mobile.screens.studio.gallery')}</Text>
      {q.isPending ? <Skeleton variant="grid" rows={6} /> : (
        <FlashList
          style={styles.flex}
          data={items}
          numColumns={2}
          keyExtractor={(item) => String(item.id)}
          renderItem={({ item }) => {
            const uri = item.thumb || item.url || '';
            return (
              <View style={{ padding: 6 }}>
                <View style={[styles.tile, { height: cell - 12, backgroundColor: uri.includes('svg') ? swatch(item.id) : theme.colors.surface2 }]}>
                  {uri.includes('svg') ? null : uri.startsWith('data:') ? <RNImage source={{ uri }} style={styles.img} /> : uri ? <Image source={{ uri }} style={styles.img} contentFit="cover" /> : <View style={styles.img}><Spinner label={t('mobile.screens.studio.running')} /></View>}
                  {item.state !== 'done' ? <Text variant="caption" style={styles.badge}>{item.state === 'failed' ? t('mobile.screens.studio.failed') : t('mobile.screens.studio.running')}</Text> : null}
                </View>
                <Text variant="caption" numberOfLines={2}>{item.prompt}</Text>
              </View>
            );
          }}
          ListEmptyComponent={<EmptyState icon="studio" title={t('mobile.screens.studio.empty')} />}
        />
      )}
    </ChromeFrame>
  );
}

const styles = StyleSheet.create((theme) => ({
  fill: { flex: 1, backgroundColor: theme.colors.bg },
  flex: { flex: 1 },
  form: { padding: theme.space[4], gap: theme.space[3] },
  kinds: { flexDirection: 'row', gap: 8 },
  input: { minHeight: 72, borderWidth: 1, borderColor: theme.colors.border, borderRadius: theme.radius.md, padding: theme.space[3], color: theme.colors.text, fontSize: 16, fontFamily: theme.font.family, backgroundColor: theme.colors.surface, textAlignVertical: 'top' },
  gallery: { paddingHorizontal: theme.space[4] },
  tile: { borderRadius: theme.radius.md, overflow: 'hidden', backgroundColor: theme.colors.surface2, marginBottom: 4 },
  img: { width: '100%', height: '100%', alignItems: 'center', justifyContent: 'center' },
  badge: { position: 'absolute', left: 8, bottom: 8, backgroundColor: theme.colors.overlay, color: '#fff', paddingHorizontal: 8, paddingVertical: 2, borderRadius: theme.radius.full, overflow: 'hidden' },
}));
