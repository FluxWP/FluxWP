// Flux Chat inbox (`flxc-inbox`): every channel in one list (WhatsApp, site chat…). The engine is shared with the desktop panel.
import { memo, useEffect, useMemo, useRef, useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, TextInput, View } from 'react-native';
import { FlashList, type FlashListRef } from '@shopify/flash-list';
import { Image } from 'expo-image';
import * as ImagePicker from 'expo-image-picker';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { useStore } from 'zustand';
import { canDeleteForEveryone, isDeletedMessage, type FlxcChannel, type FlxcChat, type FlxcFilter, type FlxcMessage } from '@fluxwp/core';
import { notifyIncoming, useAccount, useApi, useRenderer, WaEngine } from '@fluxwp/runtime';
import { Avatar, EmptyState, IconButton, Spinner, Text } from '@/ui/primitives';
import { Icon } from '@/ui/Icon';
import { overlays, type MenuItem } from '@/ui/overlays';
import { Chip, Chips, SearchField, useScreenTitle } from '../kit';
import { mobilePorts } from '../ports';

const FILTERS: FlxcFilter[] = ['all', 'unread', 'mine', 'unassigned', 'closed'];

const preview = (s: string) => s.replace(/[*_~]/g, '').replace(/\s+/g, ' ').trim();
const clock = (ts: number, locale: string) => {
  const d = new Date(ts);
  const same = d.toDateString() === new Date().toDateString();
  return same ? d.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' }) : d.toLocaleDateString(locale, { day: 'numeric', month: 'short' });
};

const channelIcon = (id?: string) => (id === 'web' ? 'globe' : id === 'email' ? 'email' : 'whatsapp');

const ChatRow = memo(function ChatRow({ chat, fresh, locale, channel, onPress }: { chat: FlxcChat; fresh: boolean; locale: string; channel?: FlxcChannel; onPress: (id: number) => void }) {
  const { theme } = useUnistyles();
  return (
    <Pressable testID={`chat-${chat.id}`} accessibilityRole="button" onPress={() => onPress(chat.id)} style={[styles.row, fresh && { backgroundColor: theme.colors.primarySoft }]}>
      <View>
        <Avatar text={chat.title || chat.phoneFmt} size={46} />
        {channel ? (
          <View accessibilityLabel={channel.label} style={[styles.channelDot, { backgroundColor: channel.color, borderColor: theme.colors.surface }]}>
            <Icon name={channelIcon(channel.id)} size={11} color="#fff" />
          </View>
        ) : null}
      </View>
      <View style={styles.flex}>
        <View style={styles.line}>
          <Text variant="label" numberOfLines={1} style={styles.flex}>{chat.title || chat.phoneFmt}</Text>
          <Text variant="caption">{clock(chat.lastTs, locale)}</Text>
        </View>
        <View style={styles.line}>
          <Text variant="small" tone="muted" numberOfLines={1} style={styles.flex}>{preview(chat.last?.body || chat.phoneFmt)}</Text>
          {chat.unread > 0 ? <View style={styles.badge}><Text variant="caption" style={styles.badgeText}>{chat.unread}</Text></View> : null}
        </View>
      </View>
    </Pressable>
  );
});

function deletedLine(m: FlxcMessage, t: (k: string, o?: Record<string, string>) => string) {
  const d = m.del;
  if (!d || !d.ago) return '';
  if (d.scope === 'contact') return t('mobile.screens.inbox.deletedContact', { ago: d.ago });
  if (d.scope === 'phone') return t('mobile.screens.inbox.deletedPhone', { ago: d.ago });
  return d.by ? t('mobile.screens.inbox.deletedBy', { who: d.by, ago: d.ago }) : t('mobile.screens.inbox.deletedPhone', { ago: d.ago });
}

/** 1.38.0: silinen mesaj tek satıra katlanır; "Göster" ile asıl içerik ve kimin ne zaman sildiği açılır. */
function DeletedBubble({ m, locale }: { m: FlxcMessage; locale: string }) {
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);
  const has = !m.del?.empty && m.type !== 'deleted' && (!!m.body || !!m.media);
  return (
    <View style={[styles.deleted, m.dir === 'in' ? styles.bubbleIn : m.dir === 'note' ? styles.note : styles.bubbleMine]}>
      <Pressable accessibilityRole="button" disabled={!has} onPress={() => setOpen((v) => !v)} style={styles.line}>
        <Icon name="trash" size={14} color="#8696a0" />
        <Text variant="caption" tone="muted">{t('mobile.screens.inbox.deleted')}</Text>
        {has ? <Text variant="caption" tone="primary">· {open ? t('mobile.screens.inbox.hide') : t('mobile.screens.inbox.show')}</Text> : null}
      </Pressable>
      {open ? (
        <View style={styles.deletedBody}>
          <Bubble m={{ ...m, deleted: false }} locale={locale} />
          {deletedLine(m, t) ? <Text variant="caption" tone="muted">{deletedLine(m, t)}</Text> : null}
        </View>
      ) : null}
    </View>
  );
}

function Bubble({ m, locale, onLong }: { m: FlxcMessage; locale: string; onLong?: (m: FlxcMessage) => void }) {
  const { theme } = useUnistyles();
  const { t } = useTranslation();
  if (m.dir === 'system') return <Text variant="caption" style={styles.system}>{m.body || '·'}</Text>;
  if (isDeletedMessage(m)) return <DeletedBubble m={m} locale={locale} />;
  const mine = m.dir === 'out';
  const note = m.dir === 'note';
  const bg = note ? theme.colors.warning : mine ? theme.colors.primary : theme.colors.surface;
  const fg = mine && !note ? theme.colors.onPrimary : theme.colors.text;
  return (
    <Pressable
      accessibilityHint={onLong ? t('mobile.screens.inbox.actions') : undefined}
      disabled={!onLong || !!m.local}
      delayLongPress={350}
      onLongPress={() => onLong?.(m)}
      style={[styles.bubble, mine ? styles.bubbleMine : styles.bubbleIn, { backgroundColor: bg }, note && styles.note]}
    >
      {m.media?.url && !m.media.url.includes('demo.fluxapp.local') ? (
        <Image source={{ uri: m.media.url }} style={styles.media} contentFit="cover" />
      ) : m.media ? (
        <View style={[styles.mediaCard, { backgroundColor: theme.colors.primarySoft }]}>
          <Icon name={m.type === 'audio' ? 'media-audio' : 'image'} size={22} color={theme.colors.primary} />
          <Text variant="small">{m.media.name || t('mobile.screens.inbox.attach')}</Text>
        </View>
      ) : null}
      {m.body ? <Text style={{ color: fg }}>{m.body}</Text> : null}
      <Text variant="caption" style={{ color: mine && !note ? theme.colors.onPrimary : theme.colors.textMuted, alignSelf: 'flex-end' }}>{clock(m.ts, locale)}{m.status === 'failed' ? ' · !' : ''}</Text>
    </Pressable>
  );
}

export default function InboxScreen({ params }: { params: Record<string, string> }) {
  const { t, i18n } = useTranslation();
  const { theme } = useUnistyles();
  useScreenTitle(t('mobile.screens.inbox.title'));
  const api = useApi();
  const account = useAccount();
  const renderer = useRenderer();
  const ports = useMemo(() => mobilePorts(account.id), [account.id]);
  const engine = useMemo(() => {
    const e = new WaEngine({
      client: api,
      ports,
      poll: 10,
      onIncoming: (items) => notifyIncoming(items, e.s.active, undefined, (id) => e.open(id), ports),
    });
    return e;
  }, [api, ports]);
  const s = useStore(engine.store);
  const [text, setText] = useState('');
  const [note, setNote] = useState(false);
  const listRef = useRef<FlashListRef<FlxcMessage>>(null);
  const phoneOnce = useRef(false);

  useEffect(() => {
    void engine.start();
    return () => engine.stop();
  }, [engine]);

  useEffect(() => {
    if (phoneOnce.current || !params.phone || !s.boot) return;
    phoneOnce.current = true;
    engine.setView({ q: params.phone });
  }, [engine, params.phone, s.boot]);

  const chats = s.order.map((id) => s.chats[id]).filter((c): c is FlxcChat => !!c);
  const active = s.active ? s.chats[s.active] : undefined;
  const msgs = s.active ? (s.msgs[s.active]?.list ?? []) : [];

  const channels = (s.boot?.channels ?? []).filter((c) => c.active);
  const multi = channels.length > 1;
  const channelOf = (c: FlxcChat) => (multi ? channels.find((x) => x.id === (c.channel || 'whatsapp')) : undefined);

  /** Uzun basınca: kopyala, herkesten sil (süre içinde), yalnız panelde gizle / notu sil. */
  const messageMenu = async (m: FlxcMessage) => {
    const me = s.boot?.me ?? { id: 0, manage: false };
    const items: MenuItem[] = [];
    if (m.body) items.push({ id: 'copy', label: t('mobile.screens.inbox.copy'), icon: 'copy' });
    if (m.dir === 'note') {
      if (m.userId === me.id || me.manage) items.push({ id: 'note', label: t('mobile.screens.inbox.deleteNote'), icon: 'trash', danger: true });
    } else {
      if (canDeleteForEveryone(m, me)) items.push({ id: 'everyone', label: t('mobile.screens.inbox.deleteEveryone'), icon: 'trash', danger: true });
      items.push({ id: 'panel', label: t('mobile.screens.inbox.deletePanel'), icon: 'trash', danger: true });
    }
    const id = await overlays.menu(t('mobile.screens.inbox.actions'), items);
    if (!id) return;
    if (id === 'copy') {
      await renderer.ui.copy(m.body);
      renderer.ui.toast(t('mobile.screens.inbox.copied'));
      return;
    }
    const ask = id === 'everyone'
      ? { title: t('mobile.screens.inbox.deleteEveryoneAsk'), body: t('mobile.screens.inbox.deleteEveryoneBody') }
      : id === 'note'
        ? { title: t('mobile.screens.inbox.deleteNoteAsk') }
        : { title: t('mobile.screens.inbox.deletePanelAsk'), body: t('mobile.screens.inbox.deletePanelBody') };
    if (!(await renderer.ui.confirm({ ...ask, ok: t('mobile.screens.inbox.delete'), tone: 'danger' }))) return;
    try {
      await engine.deleteMessage(m, id === 'everyone' ? 'everyone' : 'panel');
      renderer.ui.haptic?.('success');
      renderer.ui.toast(t('mobile.screens.inbox.deletedOk'));
    } catch (e) {
      renderer.ui.toast(e instanceof Error ? e.message : String(e), 'error');
    }
  };

  const send = () => {
    const body = text.trim();
    const id = s.active;
    if (!body || !id) return;
    setText('');
    void engine.send(id, { type: note ? 'note' : 'text', body }).catch(() => undefined);
  };

  const attach = async () => {
    const id = s.active;
    if (!id) return;
    const r = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], quality: 0.8 });
    if (r.canceled || !r.assets[0]) return;
    const a = r.assets[0];
    const blob = await (await fetch(a.uri)).blob();
    void engine.send(id, { type: 'image', file: blob, name: a.fileName ?? 'photo.jpg' }).catch(() => undefined);
  };

  if (!s.boot && !s.bootError) return <View testID="screen-inbox" style={styles.fill}><Spinner label={t('mobile.states.loading')} /></View>;
  if (s.bootError) return <View testID="screen-inbox" style={styles.fill}><EmptyState icon="alert" title={s.bootError} action={{ label: t('mobile.states.retry'), onPress: () => void engine.start() }} /></View>;

  return (
    <View testID="screen-inbox" style={styles.fill}>
      {active ? (
        <KeyboardAvoidingView style={styles.fill} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
          <View style={styles.threadHead}>
            <IconButton icon="back" label={t('mobile.screens.inbox.back')} onPress={() => engine.open(null)} />
            <View style={styles.flex}>
              <Text variant="label" numberOfLines={1}>{active.title || active.phoneFmt}</Text>
              <Text variant="caption" numberOfLines={1}>{[channelOf(active)?.label, active.phoneFmt].filter((x, i, a) => x && a.indexOf(x) === i).join(' · ')}</Text>
            </View>
            <IconButton icon="bot" label={active.bot === 'on' ? t('mobile.screens.inbox.botOn') : t('mobile.screens.inbox.botOff')} color={active.bot === 'on' ? theme.colors.primary : theme.colors.textMuted} onPress={() => void engine.setBot(active.id, active.bot !== 'on')} />
          </View>
          <FlashList
            ref={listRef}
            data={msgs}
            keyExtractor={(m) => String(m.id)}
            renderItem={({ item }) => <Bubble m={item} locale={i18n.language} onLong={(m) => void messageMenu(m)} />}
            contentContainerStyle={styles.thread}
            onContentSizeChange={() => listRef.current?.scrollToEnd({ animated: false })}
            ListEmptyComponent={<Spinner />}
          />
          <View style={styles.composer}>
            <IconButton icon="image" label={t('mobile.screens.inbox.attach')} onPress={() => void attach()} />
            <IconButton icon="note" label={t('mobile.screens.inbox.note')} color={note ? theme.colors.warning : theme.colors.textMuted} onPress={() => setNote((v) => !v)} />
            <TextInput value={text} onChangeText={(v) => (setText(v), engine.typing(active.id))} placeholder={t('mobile.screens.inbox.placeholder')} placeholderTextColor={theme.colors.textMuted} style={styles.input} accessibilityLabel={t('mobile.screens.inbox.placeholder')} />
            <IconButton icon="airplane" label={t('mobile.screens.inbox.send')} color={theme.colors.primary} onPress={send} />
          </View>
        </KeyboardAvoidingView>
      ) : (
        <>
          <SearchField testID="inbox-search" value={s.q} onChange={(q) => engine.setView({ q })} placeholder={t('mobile.screens.inbox.search')} />
          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.hscroll} contentContainerStyle={styles.filters}>
            <Chips>
              {FILTERS.map((f) => (
                <Chip key={f} label={t(`mobile.screens.inbox.filters.${f}`)} active={s.filter === f} onPress={() => engine.setView({ filter: f })} />
              ))}
            </Chips>
          </ScrollView>
          {multi ? (
            <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.hscroll} contentContainerStyle={styles.filters}>
              <Chips>
                <Chip label={t('mobile.screens.inbox.allChannels')} active={!s.channel} onPress={() => engine.setView({ channel: '' })} />
                {channels.map((c) => (
                  <Chip key={c.id} label={c.label} color={c.color} active={s.channel === c.id} onPress={() => engine.setView({ channel: c.id })} />
                ))}
              </Chips>
            </ScrollView>
          ) : null}
          {s.offline ? <Text variant="caption" tone="danger" style={styles.banner}>{t(s.offline === 'auth' ? 'mobile.screens.inbox.auth' : 'mobile.screens.inbox.offline')}</Text> : null}
          <FlashList
            style={styles.flex}
            data={chats}
            keyExtractor={(c) => String(c.id)}
            renderItem={({ item }) => <ChatRow chat={item} fresh={!!s.fresh[item.id]} locale={i18n.language} channel={channelOf(item)} onPress={(id) => engine.open(id)} />}
            ListEmptyComponent={s.listLoading ? <Spinner /> : <EmptyState icon="chat" title={t('mobile.screens.inbox.empty')} body={t('mobile.screens.inbox.emptyBody')} />}
            onEndReached={() => { if (s.listMore && !s.listLoading) void engine.loadChats(true).catch(() => undefined); }}
          />
        </>
      )}
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  fill: { flex: 1, backgroundColor: theme.colors.bg },
  flex: { flex: 1 },
  hscroll: { flexGrow: 0, maxHeight: 52 },
  filters: { paddingRight: theme.space[4], alignItems: 'center' },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, marginHorizontal: 12, marginBottom: 8, paddingHorizontal: theme.space[4], minHeight: 76, backgroundColor: theme.colors.surface, borderWidth: 1, borderColor: theme.colors.border, borderRadius: theme.radius.lg },
  line: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  badge: { minWidth: 22, height: 22, borderRadius: 11, backgroundColor: theme.colors.primary, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 6 },
  badgeText: { color: theme.colors.onPrimary, fontSize: 12 },
  banner: { paddingHorizontal: theme.space[4], paddingBottom: 4 },
  threadHead: { flexDirection: 'row', alignItems: 'center', gap: 4, paddingRight: 8, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: theme.colors.border, backgroundColor: theme.colors.surface },
  thread: { padding: theme.space[3], gap: 8 },
  bubble: { maxWidth: '82%', borderRadius: theme.radius.lg, paddingHorizontal: 12, paddingVertical: 8, gap: 4, marginBottom: 8 },
  bubbleIn: { alignSelf: 'flex-start', borderWidth: StyleSheet.hairlineWidth, borderColor: theme.colors.border },
  bubbleMine: { alignSelf: 'flex-end' },
  note: { alignSelf: 'center', maxWidth: '92%' },
  system: { textAlign: 'center', marginVertical: 6 },
  deleted: { maxWidth: '86%', marginBottom: 8, paddingHorizontal: 10, paddingVertical: 6, gap: 6, borderRadius: theme.radius.lg, borderWidth: StyleSheet.hairlineWidth, borderStyle: 'dashed', borderColor: theme.colors.border },
  deletedBody: { gap: 4, opacity: 0.8 },
  channelDot: { position: 'absolute', right: -2, bottom: -2, width: 18, height: 18, borderRadius: 9, borderWidth: 2, alignItems: 'center', justifyContent: 'center' },
  media: { width: 220, height: 160, borderRadius: theme.radius.md },
  mediaCard: { width: 200, height: 92, borderRadius: theme.radius.md, alignItems: 'center', justifyContent: 'center', gap: 6 },
  composer: { flexDirection: 'row', alignItems: 'flex-end', gap: 2, padding: 6, paddingBottom: 10, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: theme.colors.border, backgroundColor: theme.colors.surface },
  input: { flex: 1, minHeight: 44, maxHeight: 120, color: theme.colors.text, fontSize: 16, fontFamily: theme.font.family, paddingHorizontal: 12, borderWidth: 1, borderColor: theme.colors.border, borderRadius: theme.radius.md, backgroundColor: theme.colors.bg },
}));
