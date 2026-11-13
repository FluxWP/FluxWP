// Flux AI chat. Tokens arrive as NDJSON (`stream=1` on /step) and paint as they land.
// The screen opens straight into a chat, like Flux AI on the web. The chat list lives in a separate
// panel (header button): running/approval first, recent, archived on demand, search, scheduled-task
// chats marked with a clock (Flux AI 0.14 chat sidebar).
import { useEffect, useState } from 'react';
import { Modal, Platform, Pressable, TextInput, View } from 'react-native';
import { FlashList } from '@shopify/flash-list';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { loadTokens } from '@fluxwp/core';
import { messageText, streamAiStep, useAccount, useApi, useRenderer, type AiChat, type AiEvent, type AiMessage } from '@fluxwp/runtime';
import { KeyboardView } from '@/ui/KeyboardView';
import { Button, EmptyState, IconButton, Skeleton, Spinner, Text } from '@/ui/primitives';
import { Icon } from '@/ui/Icon';
import { overlays, type MenuItem } from '@/ui/overlays';
import { SearchField, useScreenTitle } from '../kit';

interface ChatRow { id: number; title: string; status: string; archived?: number; task?: string; snippet?: string }
interface ChatList { chats: ChatRow[] }
type Item = { kind: 'head'; key: string; label: string; toggle?: boolean } | { kind: 'chat'; key: string; chat: ChatRow };

const busy = (s: string) => s === 'running' || s === 'approval';
interface SendResult { state: string; chat: AiChat }

export default function AiScreen(_props: { params: Record<string, string> }) {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  useScreenTitle(t('mobile.screens.ai.title'));
  const api = useApi();
  const account = useAccount();
  const renderer = useRenderer();
  const qc = useQueryClient();
  const [q, setQ] = useState('');
  const [query, setQuery] = useState('');
  const [showArchived, setShowArchived] = useState(false);
  useEffect(() => {
    const h = setTimeout(() => setQuery(q.trim()), 300);
    return () => clearTimeout(h);
  }, [q]);
  const list = useQuery({
    queryKey: ['flxai', account.id, 'chats', query],
    queryFn: () => api<ChatList>('flx-ai/v1/chats', { query: { q: query || undefined, limit: 50 } }),
    // A running chat (also a scheduled task started on the server) refreshes the list until it settles.
    refetchInterval: (qr) => (qr.state.data?.chats.some((c) => c.status === 'running') ? 4000 : false),
  });
  const archived = useQuery({
    queryKey: ['flxai', account.id, 'chats', 'archived', query],
    queryFn: () => api<ChatList>('flx-ai/v1/chats', { query: { archived: 1, q: query || undefined, limit: 50 } }),
    enabled: showArchived,
  });
  const refreshLists = () => void qc.invalidateQueries({ queryKey: ['flxai', account.id, 'chats'] });
  // 0 = a new chat that is not saved yet.
  const [openId, setOpenId] = useState<number>(0);
  const [listOpen, setListOpen] = useState(false);
  const [chat, setChat] = useState<AiChat | null>(null);
  const [draft, setDraft] = useState('');
  const [text, setText] = useState('');
  const [streaming, setStreaming] = useState(false);

  const load = async (id: number) => {
    setListOpen(false);
    setOpenId(id);
    setChat(await api<AiChat>(`flx-ai/v1/chats/${id}`));
    setDraft('');
  };

  const consume = (ev: AiEvent) => {
    if (ev.t === 'text') setDraft((d) => d + (ev.d ?? ''));
    if ((ev.t === 'result' || ev.t === 'chat') && ev.chat) {
      setChat(ev.chat);
      setDraft('');
      setOpenId(ev.chat.id);
    }
  };

  const step = async (id: number) => {
    setStreaming(true);
    try {
      const tokens = account.authKind === 'demo' ? null : await loadTokens(account.id);
      await streamAiStep({
        siteUrl: account.siteUrl,
        demo: account.authKind === 'demo',
        token: tokens?.access,
        scheme: account.authKind === 'apppass' ? 'basic' : 'bearer',
        chatId: id,
      }, consume);
    } finally {
      setStreaming(false);
    }
  };

  const send = useMutation({
    mutationFn: async (body: string) => {
      const id = openId;
      return api<SendResult>(`flx-ai/v1/chats/${id}/send`, { method: 'POST', body: { text: body } });
    },
    onSuccess: async (r) => {
      setChat(r.chat);
      setOpenId(r.chat.id);
      setText('');
      void qc.invalidateQueries({ queryKey: ['flxai', account.id, 'chats'] });
      if (r.state === 'continue' || r.chat.status === 'running') await step(r.chat.id);
    },
    onError: (e) => renderer.ui.toast(e instanceof Error ? e.message : String(e), 'error'),
  });

  const decide = async (ok: boolean) => {
    if (!chat) return;
    const decisions = Object.fromEntries(chat.pending.map((p) => [p.id, ok ? 'approve' : 'deny']));
    const r = await api<SendResult>(`flx-ai/v1/chats/${chat.id}/decide`, { method: 'POST', body: { decisions } });
    setChat(r.chat);
    if (r.state === 'continue' || r.chat.status === 'running') await step(chat.id);
  };

  const stop = async () => {
    if (!chat) return;
    try {
      const r = await api<SendResult>(`flx-ai/v1/chats/${chat.id}/stop`, { method: 'POST' });
      if (r?.chat) setChat(r.chat);
    } catch (e) {
      renderer.ui.toast(e instanceof Error ? e.message : String(e), 'error');
    }
  };

  /** Uzun basınca: arşivle / arşivden çıkar / sil. */
  const rowMenu = async (c: ChatRow) => {
    const items: MenuItem[] = [
      c.archived ? { id: 'unarchive', label: t('mobile.screens.ai.unarchive'), icon: 'archive' } : { id: 'archive', label: t('mobile.screens.ai.archive'), icon: 'archive' },
      { id: 'delete', label: t('mobile.screens.ai.delete'), icon: 'trash', danger: true },
    ];
    const id = await overlays.menu(c.title || t('mobile.screens.ai.actions'), items);
    if (!id) return;
    try {
      if (id === 'delete') {
        if (!(await renderer.ui.confirm({ title: t('mobile.screens.ai.deleteAsk'), body: t('mobile.screens.ai.deleteBody'), ok: t('mobile.screens.ai.delete'), tone: 'danger' }))) return;
        await api(`flx-ai/v1/chats/${c.id}/delete`, { method: 'POST' });
      } else {
        await api(`flx-ai/v1/chats/${c.id}`, { method: 'POST', body: { archived: id === 'archive' ? 1 : 0 } });
      }
      refreshLists();
    } catch (e) {
      renderer.ui.toast(e instanceof Error ? e.message : String(e), 'error');
    }
  };

  const messages: (AiMessage & { id: string })[] = [
    ...(chat?.messages ?? []).filter((m) => messageText(m)).map((m, i) => ({ ...m, id: 'm' + i })),
    ...(draft ? [{ id: 'draft', role: 'assistant', content: draft }] : []),
  ];

  const newChat = () => (setListOpen(false), setOpenId(0), setChat(null), setDraft(''));
  const chatList = (() => {
    const all = list.data?.chats ?? [];
    const active = all.filter((c) => busy(c.status));
    const recent = all.filter((c) => !busy(c.status));
    const items: Item[] = [
      ...(active.length ? [{ kind: 'head' as const, key: 'h-active', label: t('mobile.screens.ai.active') }, ...active.map((c) => ({ kind: 'chat' as const, key: 'c' + c.id, chat: c }))] : []),
      ...(recent.length ? [{ kind: 'head' as const, key: 'h-recent', label: t('mobile.screens.ai.recent') }, ...recent.map((c) => ({ kind: 'chat' as const, key: 'c' + c.id, chat: c }))] : []),
      ...(all.length || showArchived ? [{ kind: 'head' as const, key: 'h-arch', label: t('mobile.screens.ai.archived'), toggle: true }] : []),
      ...(showArchived ? (archived.data?.chats ?? []).map((c) => ({ kind: 'chat' as const, key: 'a' + c.id, chat: { ...c, archived: 1 } })) : []),
    ];
    return (
      <View testID="ai-chat-list" style={styles.fill}>
        <View style={styles.listHead}>
          <Text variant="heading" style={styles.flex}>{t('mobile.screens.ai.chats')}</Text>
          <IconButton icon="x" label={t('common.close')} onPress={() => setListOpen(false)} testID="ai-chats-close" />
        </View>
        <View style={styles.tools}>
          <Button icon="plus" label={t('mobile.screens.ai.new')} onPress={newChat} />
        </View>
        <SearchField testID="ai-search" value={q} onChange={setQ} placeholder={t('mobile.screens.ai.search')} />
        {list.isPending ? <Skeleton rows={5} /> : (
          <FlashList
            data={items}
            keyExtractor={(i) => i.key}
            getItemType={(i) => i.kind}
            onRefresh={refreshLists}
            refreshing={list.isRefetching && !list.isPending}
            renderItem={({ item }) => {
              if (item.kind === 'head') {
                return item.toggle ? (
                  <Pressable accessibilityRole="button" accessibilityState={{ expanded: showArchived }} onPress={() => setShowArchived((v) => !v)} style={styles.headRow}>
                    <Text variant="caption" tone="muted" style={styles.flex}>{item.label}</Text>
                    <Icon name={showArchived ? 'chevron-down' : 'chevron'} size={16} color={theme.colors.textMuted} />
                  </Pressable>
                ) : <Text variant="caption" tone="muted" style={styles.headRow}>{item.label}</Text>;
              }
              const c = item.chat;
              const status = c.status && c.status !== 'idle' ? t(`mobile.screens.ai.status.${c.status}`, { defaultValue: c.status }) : '';
              return (
                <Pressable testID={`ai-chat-${c.id}`} accessibilityRole="button" onPress={() => void load(c.id)} onLongPress={() => void rowMenu(c)} delayLongPress={350} style={[styles.row, c.archived ? styles.rowArchived : null]}>
                  <View style={styles.rowLine}>
                    {c.task ? <Icon name="clock" size={15} color={theme.colors.textMuted} /> : null}
                    <Text numberOfLines={1} style={styles.flex}>{c.title || t('mobile.screens.ai.new')}</Text>
                    {status ? <Text variant="caption" tone={c.status === 'approval' ? 'danger' : 'primary'}>{status}</Text> : null}
                  </View>
                  {c.snippet ? <Text variant="caption" tone="muted" numberOfLines={1}>{c.snippet}</Text> : c.task ? <Text variant="caption" tone="muted" numberOfLines={1}>{t('mobile.screens.ai.task')}</Text> : null}
                </Pressable>
              );
            }}
            ListEmptyComponent={<EmptyState icon="ai" title={t('mobile.screens.ai.empty')} body={t('mobile.screens.ai.emptyBody')} action={{ label: t('mobile.screens.ai.new'), onPress: newChat }} />}
          />
        )}
      </View>
    );
  })();

  return (
    <KeyboardView testID="screen-ai" style={styles.fill} chrome>
      <View style={styles.head}>
        <IconButton testID="ai-chats" icon="list-view" label={t('mobile.screens.ai.chats')} onPress={() => (refreshLists(), setListOpen(true))} />
        <Text variant="label" numberOfLines={1} style={styles.flex}>{chat?.title || t('mobile.screens.ai.new')}</Text>
        {streaming || chat?.status === 'running' ? <IconButton icon="x" label={t('mobile.screens.ai.stop')} onPress={() => void stop()} /> : null}
        {openId ? <IconButton icon="plus" label={t('mobile.screens.ai.new')} onPress={newChat} /> : null}
      </View>
      <Modal visible={listOpen} animationType="slide" presentationStyle={Platform.OS === 'ios' ? 'pageSheet' : undefined} onRequestClose={() => setListOpen(false)}>
        {chatList}
      </Modal>
      <FlashList
        data={messages}
        keyExtractor={(m) => m.id}
        renderItem={({ item }) => {
          const mine = item.role === 'user';
          return (
            <View style={[styles.bubble, mine ? styles.mine : styles.theirs]}>
              <Text variant="caption" tone="muted">{mine ? t('mobile.screens.ai.you') : t('mobile.screens.ai.assistant')}</Text>
              <Text>{messageText(item)}</Text>
            </View>
          );
        }}
        ListEmptyComponent={streaming ? <Spinner label={t('mobile.screens.ai.thinking')} /> : <EmptyState icon="ai" title={t('mobile.screens.ai.welcome')} body={t('mobile.screens.ai.welcomeBody')} />}
        contentContainerStyle={styles.thread}
      />
      {chat?.pending?.length ? (
        <View style={styles.pending}>
          <Text variant="label">{t('mobile.screens.ai.tool')}</Text>
          {chat.pending.map((p) => <Text key={p.id} variant="small">{p.title || p.name}</Text>)}
          <View style={styles.actions}>
            <Button kind="secondary" label={t('mobile.screens.ai.deny')} onPress={() => void decide(false)} />
            <Button label={t('mobile.screens.ai.approve')} onPress={() => void decide(true)} />
          </View>
        </View>
      ) : null}
      <View style={styles.composer}>
        <TextInput value={text} onChangeText={setText} placeholder={t('mobile.screens.ai.placeholder')} placeholderTextColor={theme.colors.textMuted} style={styles.input} accessibilityLabel={t('mobile.screens.ai.placeholder')} />
        <IconButton icon="airplane" label={t('mobile.screens.ai.send')} color={theme.colors.primary} onPress={() => text.trim() && send.mutate(text.trim())} />
      </View>
    </KeyboardView>
  );
}

const styles = StyleSheet.create((theme) => ({
  fill: { flex: 1, backgroundColor: theme.colors.bg },
  flex: { flex: 1 },
  tools: { padding: theme.space[3] },
  listHead: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingLeft: theme.space[4], paddingRight: theme.space[2], paddingTop: theme.space[3] },
  headRow: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: theme.space[4] + 4, paddingTop: theme.space[3], paddingBottom: 6 },
  rowLine: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  rowArchived: { opacity: 0.75 },
  row: { minHeight: 64, gap: 2, marginHorizontal: 12, marginBottom: 8, paddingHorizontal: theme.space[4], justifyContent: 'center', backgroundColor: theme.colors.surface, borderWidth: 1, borderColor: theme.colors.border, borderRadius: theme.radius.lg },
  head: { flexDirection: 'row', alignItems: 'center', borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: theme.colors.border },
  thread: { padding: theme.space[3] },
  bubble: { maxWidth: '86%', marginHorizontal: theme.space[3], borderRadius: theme.radius.lg, padding: 12, marginBottom: 8, gap: 4 },
  mine: { alignSelf: 'flex-end', backgroundColor: theme.colors.primarySoft },
  theirs: { alignSelf: 'flex-start', backgroundColor: theme.colors.surface, borderWidth: StyleSheet.hairlineWidth, borderColor: theme.colors.border },
  pending: { margin: theme.space[3], padding: theme.space[3], gap: 6, borderRadius: theme.radius.md, backgroundColor: theme.colors.surface, borderWidth: StyleSheet.hairlineWidth, borderColor: theme.colors.border },
  actions: { flexDirection: 'row', gap: 8 },
  composer: { flexDirection: 'row', alignItems: 'flex-end', gap: 4, padding: 8, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: theme.colors.border, backgroundColor: theme.colors.surface },
  input: { flex: 1, minHeight: 44, maxHeight: 120, color: theme.colors.text, fontSize: 16, fontFamily: theme.font.family, paddingHorizontal: 12, borderWidth: 1, borderColor: theme.colors.border, borderRadius: theme.radius.md, backgroundColor: theme.colors.bg },
}));
