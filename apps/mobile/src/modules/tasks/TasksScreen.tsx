// Tasks (`flxg-tasks`): list, board, detail, comments, checklist. Engine shared with the desktop.
import { memo, useEffect, useMemo, useRef, useState } from 'react';
import { Pressable, ScrollView, TextInput, View } from 'react-native';
import { FlashList } from '@shopify/flash-list';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import type { FlxgComment, FlxgTask, FlxgTaskDetail } from '@fluxwp/core';
import { Icon } from '@/ui/Icon';
import { flxgGraphQL } from '@fluxwp/graphql/flx-tasks';
import { FlxgEngine, parseChecklist, toggleChecklistItem, useAccount, useApi, useEngineVersion, useRenderer } from '@fluxwp/runtime';
import { KeyboardView } from '@/ui/KeyboardView';
import { Avatar, Button, EmptyState, IconButton, Skeleton, Text } from '@/ui/primitives';
import { Chip, Chips, SearchField, useScreenTitle } from '../kit';
import { mobilePorts } from '../ports';

type RowProps = { task: FlxgTask; color: string; names: string[]; listName: string; overdue: boolean; done: boolean; onPress: () => void; onToggle: () => void };

// Same row as the site's mobile list: status circle, title, then a meta line (list, due, people, counts).
const TaskRow = memo(function TaskRow({ task, color, names, listName, overdue, done, onPress, onToggle }: RowProps) {
  const { theme } = useUnistyles();
  const meta = [listName, task.due ? task.due.slice(5, 10).split('-').reverse().join('.') : ''].filter(Boolean).join(' · ');
  return (
    <Pressable accessibilityRole="button" onPress={onPress} style={styles.row}>
      <Pressable accessibilityRole="checkbox" accessibilityState={{ checked: done }} hitSlop={10} onPress={onToggle} style={[styles.circle, { borderColor: color }, done && { backgroundColor: color }]}>
        {done ? <Icon name="check" size={13} color={theme.colors.onPrimary} strokeWidth={3} /> : null}
      </Pressable>
      <View style={styles.flex}>
        <Text numberOfLines={2} style={done ? styles.done : undefined}>{task.title}</Text>
        <View style={styles.metaRow}>
          {meta ? <Text variant="caption" tone={overdue ? 'danger' : 'muted'} numberOfLines={1}>{meta}</Text> : null}
          {task.subtasks ? <Text variant="caption" tone="muted">↳ {task.subtasks}</Text> : null}
          {task.comments ? <Text variant="caption" tone="muted">💬 {task.comments}</Text> : null}
        </View>
      </View>
      {names.length ? (
        <View style={styles.people}>
          {names.slice(0, 3).map((n, i) => (
            <View key={i} style={i ? styles.overlap : undefined}><Avatar text={n} size={24} /></View>
          ))}
        </View>
      ) : null}
    </Pressable>
  );
});

type Item = { head: ReturnType<FlxgEngine['groups']>[number] } | { task: FlxgTask };

export default function TasksScreen(_props: { params: Record<string, string> }) {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  useScreenTitle(t('mobile.screens.tasks.title'));
  const api = useApi();
  const account = useAccount();
  const renderer = useRenderer();
  const ports = useMemo(() => mobilePorts(account.id), [account.id]);
  const engine = useMemo(() => {
    // Reopen the list or project the user had open last (same as the site); first time: their own tasks.
    const m = /^(list|space):(\d+)$/.exec(ports.storage.get('last') ?? '');
    const e = new FlxgEngine(flxgGraphQL(api), m ? { page: m[1] as 'list' | 'space', id: +m[2]! } : undefined, ports);
    e.onRoute = (r) => {
      if (r.page === 'list' || r.page === 'space') ports.storage.set('last', r.page + ':' + r.id);
      else if (r.page === 'home') ports.storage.set('last', null);
    };
    return e;
  }, [api, ports]);
  const ver = useEngineVersion(engine);
  const [mode, setMode] = useState<'list' | 'board'>('list');
  const [q, setQ] = useState('');
  const [detail, setDetail] = useState<FlxgTaskDetail | null>(null);
  const [comment, setComment] = useState('');
  const [shut, setShut] = useState<Record<string, boolean>>({});
  const fell = useRef(false);

  useEffect(() => {
    void engine.boot();
    return () => engine.dispose();
  }, [engine]);

  // Nothing assigned to the user yet: open the first list instead of an empty "my tasks" page.
  useEffect(() => {
    const S = engine.S;
    if (fell.current || !S.ready || S.loading || S.route.page !== 'home' || !S.scope) return;
    fell.current = true;
    if (!Object.keys(S.tasks).length && S.lists.length) void engine.go({ page: 'list', id: S.lists[0]!.id, view: 0, task: 0 });
  }, [engine, ver]);

  const tasks = ver >= 0 && engine.S.ready ? engine.filtered() : [];
  const groups = engine.S.ready ? engine.groups(tasks, 'status') : [];
  const namesOf = (task: FlxgTask) => task.assignees.map((id) => engine.S.users[id]?.name ?? '').filter(Boolean);
  const toggleDone = (task: FlxgTask) => {
    const sts = engine.list(task.list)?.statuses ?? [];
    const next = engine.isDone(task) ? sts.find((x) => x.type !== 'done') : sts.find((x) => x.type === 'done');
    if (next) void engine.patchTask(task.id, { status: next.key });
  };
  const sc = engine.S.scope;
  const scopeTitle = !sc || sc.type === 'home' ? t('mobile.screens.tasks.mine') : sc.type === 'list' ? engine.list(sc.id)?.name ?? '' : engine.space(sc.id)?.name ?? '';

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

  if (!engine.S.ready && !engine.S.error) return <KeyboardView testID="screen-tasks" style={styles.fill} chrome><Skeleton variant="cards" /></KeyboardView>;
  if (engine.S.error) return <KeyboardView testID="screen-tasks" style={styles.fill} chrome><EmptyState icon="alert" title={engine.S.error} action={{ label: t('mobile.states.retry'), onPress: () => void engine.boot() }} /></KeyboardView>;

  if (detail) {
    const checks = parseChecklist(detail.description);
    return (
      <KeyboardView testID="screen-tasks" style={styles.fill} chrome>
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
      </KeyboardView>
    );
  }

  return (
    <KeyboardView testID="screen-tasks" style={styles.fill} chrome>
      <Text variant="title" numberOfLines={1} style={styles.title}>{scopeTitle}</Text>
      <SearchField testID="tasks-search" value={q} onChange={(v) => (setQ(v), engine.setQuery(v))} placeholder={t('mobile.screens.tasks.search')} />
      <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.hscroll} contentContainerStyle={styles.filters}>
        <Chips>
          <Chip label={t('mobile.screens.tasks.mine')} active={engine.S.route.page === 'home'} onPress={() => void engine.go({ page: 'home', id: 0, view: 0, task: 0 })} />
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
          data={groups.flatMap((g) => [{ head: g } as Item, ...(shut[g.key] ? [] : g.tasks.map((task) => ({ task })))])}
          keyExtractor={(it) => ('head' in it ? 'g' + it.head.key : String(it.task.id))}
          getItemType={(it) => ('head' in it ? 'head' : 'row')}
          renderItem={({ item }) => {
            if ('head' in item) {
              const g = item.head;
              return (
                <Pressable accessibilityRole="button" onPress={() => setShut((m) => ({ ...m, [g.key]: !m[g.key] }))} style={styles.group}>
                  <Icon name={shut[g.key] ? 'chevron' : 'chevron-down'} size={16} color={theme.colors.textMuted} />
                  <View style={[styles.pill, { backgroundColor: g.color + '33' }]}><Text variant="caption" style={{ color: g.color, fontWeight: '700' }}>{g.label.toUpperCase()}</Text></View>
                  <Text variant="caption" tone="muted">{g.tasks.length}</Text>
                </Pressable>
              );
            }
            const task = item.task;
            const st = engine.status(task);
            const overdue = !!task.due && task.due.slice(0, 10) < engine.S.config.today && st.type !== 'done';
            return <TaskRow task={task} color={st.color} names={namesOf(task)} listName={sc?.type === 'list' ? '' : engine.list(task.list)?.name ?? ''} overdue={overdue} done={st.type === 'done'} onPress={() => void open(task.id)} onToggle={() => toggleDone(task)} />;
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
    </KeyboardView>
  );
}

const styles = StyleSheet.create((theme) => ({
  fill: { flex: 1, backgroundColor: theme.colors.bg },
  flex: { flex: 1 },
  hscroll: { flexGrow: 0, maxHeight: 52 },
  filters: { paddingRight: theme.space[4], alignItems: 'center' },
  tools: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingHorizontal: theme.space[4], paddingBottom: 4 },
  title: { paddingHorizontal: theme.space[4], paddingTop: theme.space[3] },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, minHeight: 60, paddingVertical: 10, paddingHorizontal: theme.space[4], borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: theme.colors.border },
  circle: { width: 22, height: 22, borderRadius: 11, borderWidth: 2, alignItems: 'center', justifyContent: 'center' },
  metaRow: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 2 },
  people: { flexDirection: 'row' },
  overlap: { marginLeft: -8 },
  group: { flexDirection: 'row', alignItems: 'center', gap: 8, minHeight: 44, paddingHorizontal: theme.space[4], marginTop: 6 },
  pill: { paddingHorizontal: 10, paddingVertical: 3, borderRadius: theme.radius.full },
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
