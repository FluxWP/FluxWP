// Flux Social media composer: text, accounts, an image, publish or schedule.
import { useState } from 'react';
import { Pressable, ScrollView, TextInput, View } from 'react-native';
import { Image } from 'expo-image';
import * as ImagePicker from 'expo-image-picker';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { socialPostBody, uploadPickedMedia, useAccount, useApi, useRenderer, type SocialAccount } from '@fluxwp/runtime';
import { KeyboardView } from '@/ui/KeyboardView';
import { Button, Text } from '@/ui/primitives';
import { Chip, Chips, useScreenTitle } from '../kit';
import { useScrollChrome } from '@/shell/chrome';

interface Boot {
  accounts: SocialAccount[];
  user: { publish: boolean };
}

export default function SocialScreen(_props: { params: Record<string, string> }) {
  const scrollChrome = useScrollChrome();
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  useScreenTitle(t('mobile.screens.social.title'));
  const api = useApi();
  const account = useAccount();
  const renderer = useRenderer();
  const boot = useQuery({ queryKey: ['flxsm', account.id, 'boot'], queryFn: () => api<Boot>('flx-social/v1/boot') });
  const [content, setContent] = useState('');
  const [picked, setPicked] = useState<number[]>([]);
  const [mediaId, setMediaId] = useState<number | null>(null);
  const [preview, setPreview] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const accounts = boot.data?.accounts ?? [];

  const toggle = (id: number) => setPicked((cur) => (cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]));

  const attach = async () => {
    const r = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], quality: 0.85 });
    if (r.canceled || !r.assets[0]) return;
    const a = r.assets[0];
    setPreview(a.uri);
    const blob = await (await fetch(a.uri)).blob().catch(() => undefined);
    const media = await uploadPickedMedia({ api, file: { uri: a.uri, name: a.fileName ?? 'social.jpg', type: a.mimeType ?? 'image/jpeg', blob } });
    setMediaId(media.id);
  };

  const publish = async (schedule: boolean) => {
    if (!content.trim() || picked.length === 0) {
      setError(t('mobile.screens.social.need'));
      return;
    }
    setBusy(true);
    setError('');
    try {
      let scheduledAt = 0;
      if (schedule) {
        const v = await renderer.ui.prompt({ title: t('mobile.screens.social.schedule'), fields: [{ name: 'when', type: 'datetime', label: t('mobile.screens.social.schedule'), required: true }] });
        if (!v?.when) return;
        scheduledAt = Math.floor(new Date(String(v.when)).getTime() / 1000);
        if (!scheduledAt) return;
      }
      await api('flx-social/v1/posts', { method: 'POST', body: socialPostBody({ content, accountIds: picked, mediaIds: mediaId ? [mediaId] : [], scheduledAt }) });
      renderer.ui.toast(t('mobile.screens.social.sent'), 'ok');
      setContent('');
      setMediaId(null);
      setPreview(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };

  return (
    <KeyboardView style={styles.kb}>
      <ScrollView {...scrollChrome} testID="screen-social" contentContainerStyle={styles.wrap} keyboardShouldPersistTaps="handled">
      <Text variant="label">{t('mobile.screens.social.accounts')}</Text>
      {accounts.length === 0 && !boot.isPending ? <Text tone="muted">{t('mobile.screens.social.noAccounts')}</Text> : null}
      <Chips>
        {accounts.map((a) => (
          <Chip key={a.id} label={`${a.name} · ${a.provider}`} color={a.color} active={picked.includes(a.id)} onPress={() => toggle(a.id)} />
        ))}
      </Chips>
      <TextInput
        testID="social-body"
        value={content}
        onChangeText={setContent}
        placeholder={t('mobile.screens.social.placeholder')}
        placeholderTextColor={theme.colors.textMuted}
        multiline
        style={styles.input}
        accessibilityLabel={t('mobile.screens.social.placeholder')}
      />
      {preview ? (
        <Pressable onPress={() => (setPreview(null), setMediaId(null))} accessibilityRole="image" accessibilityLabel={t('mobile.screens.social.attach')}>
          <Image source={{ uri: preview }} style={styles.preview} contentFit="cover" />
        </Pressable>
      ) : (
        <Button kind="secondary" icon="image" label={t('mobile.screens.social.attach')} onPress={() => void attach()} />
      )}
      {error ? <Text tone="danger">{error}</Text> : null}
      <View style={styles.actions}>
        <Button kind="secondary" label={t('mobile.screens.social.schedule')} onPress={() => void publish(true)} disabled={busy} />
        <Button label={t('mobile.screens.social.publish')} onPress={() => void publish(false)} loading={busy} />
      </View>
      </ScrollView>
    </KeyboardView>
  );
}

const styles = StyleSheet.create((theme) => ({
  kb: { flex: 1 },
  wrap: { padding: theme.space[4], gap: theme.space[3], paddingBottom: theme.space[12] },
  input: { minHeight: 140, borderWidth: 1, borderColor: theme.colors.border, borderRadius: theme.radius.md, padding: theme.space[3], color: theme.colors.text, fontSize: 16, fontFamily: theme.font.family, backgroundColor: theme.colors.surface, textAlignVertical: 'top' },
  preview: { width: '100%', height: 220, borderRadius: theme.radius.md, backgroundColor: theme.colors.surface2 },
  actions: { flexDirection: 'row', gap: 8 },
}));
