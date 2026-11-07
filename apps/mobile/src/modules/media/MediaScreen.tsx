// Media library: grid, camera and gallery upload with progress, alt text.
import { useState } from 'react';
import { Image as RNImage, Pressable, View, useWindowDimensions } from 'react-native';
import { FlashList } from '@shopify/flash-list';
import { Image } from 'expo-image';
import * as ImagePicker from 'expo-image-picker';
import { useInfiniteQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { mediaThumb, uploadPickedMedia, useAccount, useApi, useRenderer, type MediaFile, type WpMedia } from '@fluxwp/runtime';
import { Button, EmptyState, Spinner, Text } from '@/ui/primitives';
import { useScreenTitle } from '../kit';

const swatch = (id: number) => ['#1D7932', '#2563eb', '#db2777', '#d97706', '#0f766e', '#7c3aed'][Math.abs(id) % 6]!;

export default function MediaScreen(_props: { params: Record<string, string> }) {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const { width } = useWindowDimensions();
  useScreenTitle(t('mobile.screens.media.title'));
  const api = useApi();
  const account = useAccount();
  const renderer = useRenderer();
  const qc = useQueryClient();
  const [progress, setProgress] = useState<number | null>(null);
  const [error, setError] = useState('');
  const cell = Math.floor((width - theme.space[2] * 2) / 3);

  const q = useInfiniteQuery({
    queryKey: ['media', account.id],
    queryFn: ({ pageParam }) => api<WpMedia[]>('wp/v2/media', { query: { page: pageParam, per_page: 30, media_type: 'image' } }),
    initialPageParam: 1,
    getNextPageParam: (last, _all, page) => (last.length < 30 ? undefined : page + 1),
  });
  const items = q.data?.pages.flat() ?? [];

  const pick = async (camera: boolean) => {
    setError('');
    const perm = camera ? await ImagePicker.requestCameraPermissionsAsync() : await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) {
      renderer.ui.toast(t('mobile.screens.media.denied'), 'error');
      return;
    }
    const r = camera
      ? await ImagePicker.launchCameraAsync({ mediaTypes: ['images'], quality: 0.85 })
      : await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], quality: 0.85 });
    if (r.canceled || !r.assets[0]) return;
    const a = r.assets[0];
    const alt = await renderer.ui.prompt({ title: t('mobile.screens.media.alt'), fields: [{ name: 'alt', type: 'text', label: t('mobile.screens.media.alt'), placeholder: t('mobile.screens.media.altPh') }] });
    let blob: Blob | undefined;
    try {
      blob = await (await fetch(a.uri)).blob();
    } catch {
      blob = undefined;
    }
    const file: MediaFile = { uri: a.uri, name: a.fileName ?? `photo-${Date.now()}.jpg`, type: a.mimeType ?? 'image/jpeg', blob };
    setProgress(0.05);
    try {
      await uploadPickedMedia({ api, file, alt: alt ? String(alt.alt ?? '') : undefined, onProgress: setProgress });
      renderer.ui.toast(t('mobile.screens.media.done'), 'ok');
      void qc.invalidateQueries({ queryKey: ['media', account.id] });
    } catch (e) {
      setError(e instanceof Error ? e.message : String(e));
    } finally {
      setProgress(null);
    }
  };

  return (
    <View testID="screen-media" style={styles.fill}>
      <View style={styles.tools}>
        <Button kind="secondary" icon="camera" label={t('mobile.screens.media.camera')} onPress={() => void pick(true)} />
        <Button kind="secondary" icon="image" label={t('mobile.screens.media.gallery')} onPress={() => void pick(false)} />
      </View>
      {progress !== null ? (
        <View style={styles.track}>
          <View style={[styles.bar, { width: `${Math.round(progress * 100)}%` }]} />
          <Text variant="caption">{t('mobile.screens.media.uploading')}</Text>
        </View>
      ) : null}
      {error ? <Text tone="danger" style={styles.err}>{error}</Text> : null}
      {q.isPending ? <Spinner /> : (
        <FlashList
          style={styles.flex}
          data={items}
          numColumns={3}
          keyExtractor={(m) => String(m.id)}
          renderItem={({ item }) => {
            const uri = mediaThumb(item);
            const label = item.title?.rendered || item.alt_text || '';
            const svg = uri.includes('svg');
            return (
              <Pressable accessibilityRole="image" accessibilityLabel={item.alt_text || label} style={{ height: cell, padding: 2 }}>
                {svg ? (
                  <View style={[styles.thumb, styles.swatch, { backgroundColor: swatch(item.id) }]}><Text style={styles.swatchText}>{label}</Text></View>
                ) : uri.startsWith('data:') ? (
                  <RNImage source={{ uri }} style={styles.thumb} />
                ) : (
                  <Image source={{ uri }} style={styles.thumb} contentFit="cover" recyclingKey={String(item.id)} />
                )}
              </Pressable>
            );
          }}
          onEndReached={() => { if (q.hasNextPage && !q.isFetchingNextPage) void q.fetchNextPage(); }}
          ListEmptyComponent={<EmptyState icon="image" title={t('mobile.screens.media.empty')} body={t('mobile.screens.media.emptyBody')} />}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  fill: { flex: 1, backgroundColor: theme.colors.bg },
  flex: { flex: 1 },
  tools: { flexDirection: 'row', gap: 8, padding: theme.space[3] },
  track: { marginHorizontal: theme.space[3], marginBottom: 8, gap: 4 },
  bar: { height: 4, backgroundColor: theme.colors.primary, borderRadius: 2 },
  err: { paddingHorizontal: theme.space[3] },
  thumb: { width: '100%', height: '100%', borderRadius: theme.radius.sm, backgroundColor: theme.colors.surface2 },
  swatch: { alignItems: 'center', justifyContent: 'center' },
  swatchText: { color: '#fff', fontWeight: '600' },
}));
