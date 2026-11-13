// Tasks (`flxg-tasks`): list, board, detail, comments, checklist. Engine shared with the desktop.
import { memo, useEffect, useMemo, useState } from 'react';
import { Pressable, ScrollView, TextInput, View } from 'react-native';
import { FlashList } from '@shopify/flash-list';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { flxg, type FlxgComment, type FlxgTask, type FlxgTaskDetail } from '@fluxwp/core';
import { FlxgEngine, parseChecklist, toggleChecklistItem, useAccount, useApi, useEngineVersion, useRenderer } from '@fluxwp/runtime';
import { Avatar, Button, EmptyState, IconButton, Skeleton, Text } from '@/ui/primitives';
import { Chip, Chips, SearchField, useScreenTitle } from '../kit';
import { mobilePorts } from '../ports';

const TaskRow = memo(function TaskRow({ task, color, names, overdue, onPress }: { task: FlxgTask; color: string; names: string; overdue: boolean; onPress: () => void }) {
  return (
    <Pressable accessibilityRole="button" onPress={onPress} style={styles.row}>
      <View style={[styles.dot, { backgroundColor: color }]} />
      <View style={styles.flex}>
        <Text numberOfLines={2} style={task.completed ? styles.done : undefined}>{task.title}</Text>
        <Text variant="caption" tone={overdue ? 'danger' : 'muted'} numberOfLines={1}>{[task.due?.slice(0, 10), names].filter(Boolean).join(' · ')}</Text>
      </View>
    </Pressable>
  );
});

export default function TasksScreen(_props: { params: Record<string, string> }) {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  useScreenTitle(t('mobile.screens.tasks.title'));
  const api = useApi();
  const account = useAccount();
  const renderer = useRenderer();
  const ports = useMemo(() => mobilePorts(account.id), [account.id]);
  const engine = useMemo(() => new FlxgEngine(flxg(api), undefined, ports), [api, ports]);
  const ver = useEngineVersion(engine);
  const [mode, setMode] = useState<'list' | 'board'>('list');
  const [q, setQ] = useState('');
  const [detail, setDetail] = useState<FlxgTaskDetail | null>(null);
  const [comment, setComment] = useState('');

  useEffect(() => {
    void engine.boot();
    return () => engine.dispose();
  }, [engine]);

  const tasks = ver >= 0 && engine.S.ready ? engine.filtered() : [];
  const groups = engine.S.ready ? engine.groups(tasks, 'status') : [];
  const namesOf = (task: FlxgTask) => task.assignees.map((id) => engine.S.users[id]?.name).filter(Boolean).join(', ');

  const open = async (id: number) => {
    engine.openTask(id);
    const r = await engine.api.task(id);
    setDetail(r.task);
  };

  const add = async () => {
    const v = await renderer.ui.prompt({ title: t('mobile.screens.tasks.add'), fields: [{ name: 'title', type: 'text', label: t('mobile.screens.tasks.addTitle'), required: true }] });
    const title = String(v?.title ?? '').trim();
    const list = engine.S.route.page === 'list' ? engine.S.route.id : engine.S.lists[0]?.id;
    if (!title || !list) return;
    const task = await engine.createTask({ title, list });
    if (task) void open(task.id);
  };

  const toggle = async (index: number) => {
    if (!detail) return;
    const description = toggleChecklistItem(detail.description, index);
    setDetail({ ...detail, description });
    await engine.patchTask(detail.id, { description });
  };

  const sendComment = async () => {
    if (!detail || !comment.trim()) return;
    const text = comment.trim();
    setComment('');
    const c = await engine.api.comment(detail.id, text, []);
    setDetail({ ...detail, comments_list: [...detail.comments_list, c] });
  };

  if (!engine.S.ready && !engine.S.error) return <View testID="screen-tasks" style={styles.fill}><Skeleton variant="cards" /></View>;
  if (engine.S.error) return <View testID="screen-tasks" style={styles.fill}><EmptyState icon="alert" title={engine.S.error} action={{ label: t('mobile.states.retry'), onPress: () => void engine.boot() }} /></View>;

  if (detail) {
    const checks = parseChecklist(detail.description);
    return (
      <View testID="screen-tasks" style={styles.fill}>
        <View style={styles.head}>
          <IconButton icon="back" label={t('mobile.screens.tasks.close')} onPress={() => (engine.openTask(0), setDetail(null))} />
          <Text variant="heading" style={styles.flex} numberOfLines={2}>{detail.title}</Text>
        </View>
        <ScrollView contentContainerStyle={styles.detail}>
          <View style={styles.meta}>
            {engine.list(detail.list)?.statuses.map((st) => (
              <Chip key={st.key} label={st.label} color={st.color} active={detail.status === st.key} onPress={() => void engine.patchTask(detail.id, { status: st.key }).then(() => setDetail({ ...detail, status: st.key }))} />
            ))}
          </View>
          <Text variant="label">{t('mobile.screens.tasks.checklist')}</Text>
          {checks.length === 0 ? <Text tone="muted">{t('mobile.screens.tasks.noChecklist')}</Text> : checks.map((item, i) => (
            <Pressable key={i} accessibilityRole="checkbox" accessibilityState={{ checked: item.done }} onPress={() => void toggle(i)} style={styles.check}>
              <View style={[styles.box, item.done && { backgroundColor: theme.colors.primary, borderColor: theme.colors.primary }]} />
              <Text style={item.done ? styles.done : undefined}>{item.text}</Text>
            </Pressable>
          ))}
          <Text variant="label">{t('mobile.screens.tasks.comments')}</Text>
          {detail.comments_list.map((c: FlxgComment) => (
            <View key={String(c.id)} style={styles.comment}>
              <Avatar text={engine.S.users[c.user]?.name || '?'} size={28} />
              <View style={styles.flex}>
                <Text variant="label">{engine.S.users[c.user]?.name}</Text>
                <Text>{c.body}</Text>
              </View>
            </View>
          ))}
        </ScrollView>
        <View style={styles.composer}>
          <TextInput value={comment} onChangeText={setComment} placeholder={t('mobile.screens.tasks.comment')} placeholderTextColor={theme.colors.textMuted} style={styles.input} accessibilityLabel={t('mobile.screens.tasks.comment')} />
          <Button label={t('mobile.screens.tasks.send')} onPress={() => void sendComment()} />
        </View>
      </View>
    );
  }

  return (
    <View testID="screen-tasks" style={styles.fill}>
      <SearchField testID="tasks-search" value={q} onChange={(v) => (setQ(v), engine.setQuery(v))} placeholder={t('mobile.screens.tasks.search')} />
      <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.hscroll} contentContainerStyle={styles.filters}>
        <Chips>
          <Chip label={t('mobile.screens.tasks.home')} active={engine.S.route.page === 'home'} onPress={() => void engine.go({ page: 'home', id: 0, view: 0, task: 0 })} />
          {engine.S.lists.map((l) => (
            <Chip key={l.id} label={l.name} color={l.color} active={engine.S.route.page === 'list' && engine.S.route.id === l.id} onPress={() => void engine.go({ page: 'list', id: l.id, view: 0, task: 0 })} />
          ))}
        </Chips>
      </ScrollView>
      <View style={styles.tools}>
        <Chip label={t('mobile.screens.tasks.list')} active={mode === 'list'} onPress={() => setMode('list')} />
        <Chip label={t('mobile.screens.tasks.board')} active={mode === 'board'} onPress={() => setMode('board')} />
        <View style={styles.flex} />
        <IconButton icon="plus" label={t('mobile.screens.tasks.add')} onPress={() => void add()} />
      </View>
      {mode === 'list' ? (
        <FlashList
          style={styles.flex}
          data={tasks}
          keyExtractor={(task) => String(task.id)}
          renderItem={({ item }) => {
            const st = engine.status(item);
            const overdue = !!item.due && item.due.slice(0, 10) < engine.S.config.today && st.type !== 'done';
            return <TaskRow task={item} color={st.color} names={namesOf(item)} overdue={overdue} onPress={() => void open(item.id)} />;
          }}
          ListEmptyComponent={engine.S.loading ? <Skeleton variant="cards" /> : <EmptyState icon="task" title={t('mobile.screens.tasks.empty')} body={t('mobile.screens.tasks.emptyBody')} />}
        />
      ) : (
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.board}>
          {groups.map((g) => (
            <View key={g.key} style={styles.column}>
              <View style={styles.colHead}>
                <View style={[styles.dot, { backgroundColor: g.color }]} />
                <Text variant="label" style={styles.flex} numberOfLines={1}>{g.label}</Text>
                <Text variant="caption">{g.tasks.length}</Text>
              </View>
              <FlashList
                data={g.tasks}
                keyExtractor={(task) => String(task.id)}
                renderItem={({ item }) => (
                  <Pressable onPress={() => void open(item.id)} style={styles.card}>
                    <Text numberOfLines={3}>{item.title}</Text>
                    {item.due ? <Text variant="caption" tone="muted">{item.due.slice(0, 10)}</Text> : null}
                  </Pressable>
                )}
              />
            </View>
          ))}
        </ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  fill: { flex: 1, backgroundColor: theme.colors.bg },
  flex: { flex: 1 },
  hscroll: { flexGrow: 0, maxHeight: 52 },
  filters: { paddingRight: theme.space[4], alignItems: 'center' },
  tools: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingHorizontal: theme.space[4], paddingBottom: 4 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 10, minHeight: 68, marginHorizontal: 12, marginBottom: 8, paddingHorizontal: theme.space[4], backgroundColor: theme.colors.surface, borderWidth: 1, borderColor: theme.colors.border, borderRadius: theme.radius.lg },
  dot: { width: 10, height: 10, borderRadius: 5 },
  done: { textDecorationLine: 'line-through', color: theme.colors.textMuted },
  board: { padding: theme.space[3], gap: theme.space[3], alignItems: 'stretch' },
  column: { width: 260, backgroundColor: theme.colors.surface2, borderRadius: theme.radius.lg, padding: 8 },
  colHead: { flexDirection: 'row', alignItems: 'center', gap: 8, minHeight: 36, paddingHorizontal: 4 },
  card: { backgroundColor: theme.colors.surface, borderRadius: theme.radius.md, padding: 12, marginBottom: 8, gap: 6, borderWidth: StyleSheet.hairlineWidth, borderColor: theme.colors.border },
  head: { flexDirection: 'row', alignItems: 'center', gap: 4, paddingRight: 12, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: theme.colors.border },
  detail: { padding: theme.space[4], gap: theme.space[3], paddingBottom: 24 },
  meta: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  check: { flexDirection: 'row', alignItems: 'center', gap: 10, minHeight: 44 },
  box: { width: 22, height: 22, borderRadius: 6, borderWidth: 1.5, borderColor: theme.colors.border },
  comment: { flexDirection: 'row', gap: 8, paddingVertical: 6 },
  composer: { flexDirection: 'row', alignItems: 'center', gap: 8, padding: 8, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: theme.colors.border, backgroundColor: theme.colors.surface },
  input: { flex: 1, minHeight: 44, color: theme.colors.text, fontSize: 16, fontFamily: theme.font.family, paddingHorizontal: 12, borderWidth: 1, borderColor: theme.colors.border, borderRadius: theme.radius.md, backgroundColor: theme.colors.bg },
}));
