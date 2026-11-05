// TV remote. Commands go out only while state.same_wifi is true (the bridge's network).
import { useState } from 'react';
import { Pressable, ScrollView, TextInput, View } from 'react-native';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { TV_KEYS, tvCommandAllowed, useAccount, useApi, useRenderer, type TvCommand, type TvState } from '@fluxwp/runtime';
import { Text } from '@/ui/primitives';
import { Icon } from '@/ui/Icon';
import { useScreenTitle } from '../kit';

function Key({ label, icon, onPress, disabled }: { label: string; icon?: string; onPress: () => void; disabled?: boolean }) {
  const { theme } = useUnistyles();
  return (
    <Pressable accessibilityRole="button" accessibilityLabel={label} accessibilityState={{ disabled: !!disabled }} disabled={disabled} onPress={onPress} style={({ pressed }) => [styles.key, pressed && styles.pressed, disabled && styles.disabled]}>
      {icon ? <Icon name={icon} size={22} color={theme.colors.text} /> : <Text variant="label">{label}</Text>}
    </Pressable>
  );
}

export default function TvScreen(_props: { params: Record<string, string> }) {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  useScreenTitle(t('mobile.screens.tv.title'));
  const api = useApi();
  const account = useAccount();
  const renderer = useRenderer();
  const qc = useQueryClient();
  const key = ['tv', account.id] as const;
  const q = useQuery({ queryKey: key, queryFn: () => api<TvState>('flxtv/v1/state'), refetchInterval: 4000 });
  const [typed, setTyped] = useState('');
  const state = q.data ?? null;
  const allowed = tvCommandAllowed(state);

  const send = async (cmd: TvCommand) => {
    if (!tvCommandAllowed(state)) {
      renderer.ui.toast(t('mobile.screens.tv.wifiOff'), 'error');
      return;
    }
    const r = await api<{ ok: boolean; message?: string; state: TvState }>('flxtv/v1/cmd', { method: 'POST', body: cmd });
    if (r.state) qc.setQueryData(key, r.state);
    if (!r.ok && r.message) renderer.ui.toast(r.message, 'error');
  };

  return (
    <ScrollView testID="screen-tv" contentContainerStyle={styles.wrap}>
      <View style={[styles.banner, { backgroundColor: allowed ? theme.colors.primarySoft : theme.colors.warning }]}>
        <Text variant="label">{state?.name || t('mobile.screens.tv.title')}</Text>
        <Text variant="small">{!state ? '…' : !state.same_wifi ? t('mobile.screens.tv.wifiOff') : !state.online ? t('mobile.screens.tv.offline') : `${t('mobile.screens.tv.wifi')} · ${state.app} · ${state.muted ? t('mobile.screens.tv.mute') : state.volume}`}</Text>
      </View>

      <View style={styles.pad}>
        <Key label="↑" onPress={() => void send({ cmd: 'key', key: TV_KEYS.up })} disabled={!allowed} />
        <View style={styles.row}>
          <Key label="←" onPress={() => void send({ cmd: 'key', key: TV_KEYS.left })} disabled={!allowed} />
          <Key label={t('mobile.screens.tv.ok')} onPress={() => void send({ cmd: 'key', key: TV_KEYS.ok })} disabled={!allowed} />
          <Key label="→" onPress={() => void send({ cmd: 'key', key: TV_KEYS.right })} disabled={!allowed} />
        </View>
        <Key label="↓" onPress={() => void send({ cmd: 'key', key: TV_KEYS.down })} disabled={!allowed} />
      </View>

      <View style={styles.row}>
        <Key label={t('mobile.screens.tv.back')} icon="back" onPress={() => void send({ cmd: 'key', key: TV_KEYS.back })} disabled={!allowed} />
        <Key label={t('mobile.screens.tv.home')} icon="home" onPress={() => void send({ cmd: 'key', key: TV_KEYS.home })} disabled={!allowed} />
        <Key label={t('mobile.screens.tv.power')} icon="offline" onPress={() => void send({ cmd: 'power' })} disabled={!state?.same_wifi} />
      </View>
      <View style={styles.row}>
        <Key label={t('mobile.screens.tv.volDown')} onPress={() => void send({ cmd: 'volume', delta: -1 })} disabled={!allowed} />
        <Key label={t('mobile.screens.tv.mute')} onPress={() => void send({ cmd: 'mute' })} disabled={!allowed} />
        <Key label={t('mobile.screens.tv.volUp')} onPress={() => void send({ cmd: 'volume', delta: 1 })} disabled={!allowed} />
      </View>

      <Text variant="label">{t('mobile.screens.tv.apps')}</Text>
      <View style={styles.apps}>
        {(state?.apps ?? []).map((a) => (
          <Pressable key={a.id} accessibilityRole="button" accessibilityLabel={a.name} disabled={!allowed} onPress={() => void send({ cmd: 'app', app: a.id })} style={[styles.app, !allowed && styles.disabled]}>
            <Text numberOfLines={1}>{a.name}</Text>
          </Pressable>
        ))}
      </View>

      <Text variant="label">{t('mobile.screens.tv.text')}</Text>
      <View style={styles.row}>
        <TextInput value={typed} onChangeText={setTyped} placeholder={t('mobile.screens.tv.textPh')} placeholderTextColor={theme.colors.textMuted} style={styles.input} accessibilityLabel={t('mobile.screens.tv.text')} />
        <Pressable accessibilityRole="button" accessibilityLabel={t('mobile.screens.tv.type')} disabled={!allowed || !typed.trim()} onPress={() => void send({ cmd: 'text', text: typed }).then(() => setTyped(''))} style={[styles.key, styles.type, (!allowed || !typed.trim()) && styles.disabled]}>
          <Text variant="label" style={{ color: theme.colors.onPrimary }}>{t('mobile.screens.tv.type')}</Text>
        </Pressable>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create((theme) => ({
  wrap: { padding: theme.space[4], gap: theme.space[3], paddingBottom: theme.space[12], alignItems: 'center' },
  banner: { alignSelf: 'stretch', borderRadius: theme.radius.lg, padding: theme.space[3], gap: 4 },
  pad: { alignItems: 'center', gap: 8 },
  row: { flexDirection: 'row', gap: 8, alignItems: 'center' },
  key: { width: 72, height: 56, borderRadius: theme.radius.lg, backgroundColor: theme.colors.surface, borderWidth: StyleSheet.hairlineWidth, borderColor: theme.colors.border, alignItems: 'center', justifyContent: 'center' },
  pressed: { opacity: 0.7 },
  disabled: { opacity: 0.4 },
  apps: { alignSelf: 'stretch', flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  app: { minHeight: 44, paddingHorizontal: 14, borderRadius: theme.radius.full, backgroundColor: theme.colors.surface, borderWidth: StyleSheet.hairlineWidth, borderColor: theme.colors.border, alignItems: 'center', justifyContent: 'center' },
  input: { flex: 1, minHeight: 48, borderRadius: theme.radius.md, borderWidth: StyleSheet.hairlineWidth, borderColor: theme.colors.border, backgroundColor: theme.colors.surface, paddingHorizontal: 12, color: theme.colors.text, fontSize: 16 },
  type: { width: 88, backgroundColor: theme.colors.primary, borderColor: theme.colors.primary },
}));
