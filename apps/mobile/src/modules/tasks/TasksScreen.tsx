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
import { overlays } from '@/ui/overlays';
import { Avatar, Button, EmptyState, IconButton, Skeleton, Text } from '@/ui/primitives';
import { Chip, SearchField, useScreenTitle } from '../kit';
import { mobilePorts } from '../ports';

type RowProps = { task: FlxgTask; depth: number; color: string; names: string[]; listName: string; overdue: boolean; done: boolean; kids: 'none' | 'open' | 'closed'; onKids: () => void; onPress: () => void; onToggle: () => void };

// Same row as the site's mobile list: [subtask caret] status circle, title (+ list, due), counts on the right.
const TaskRow = memo(function TaskRow({ task, depth, color, listName, overdue, done, kids, onKids, onPress, onToggle }: RowProps) {
  const { theme } = useUnistyles();
  const muted = theme.colors.textMuted;
  const meta = [listName, task.due ? task.due.slice(5, 10).split('-').reverse().join('.') : ''].filter(Boolean).join(' · ');
  return (
    <Pressable accessibilityRole="button" onPress={onPress} style={[styles.row, { paddingLeft: theme.space[4] + depth * 18 }]}>
      <Pressable accessibilityRole="button" hitSlop={8} disabled={kids === 'none'} onPress={onKids} style={styles.caret}>
        {kids === 'none' ? null : <Icon name={kids === 'open' ? 'chevron-down' : 'chevron'} size={14} color={muted} />}
      </Pressable>
      <Pressable accessibilityRole="checkbox" accessibilityState={{ checked: done }} hitSlop={10} onPress={onToggle} style={[styles.circle, { borderColor: color }, done && { backgroundColor: color }]}>
        {done ? <Icon name="check" size={13} color={theme.colors.onPrimary} strokeWidth={3} /> : null}
      </Pressable>
      <View style={styles.flex}>
        <Text numberOfLines={2} style={done ? styles.done : undefined}>{task.title}</Text>
        {meta ? <Text variant="caption" tone={overdue ? 'danger' : 'muted'} numberOfLines={1}>{meta}</Text> : null}
      </View>
      <View style={styles.counts}>
        {task.subtasks ? <View style={styles.count}><Icon name="subtask" size={14} color={muted} /><Text variant="caption" tone="muted">{task.subtasks}</Text></View> : null}
        {task.comments ? <View style={styles.count}><Icon name="comment" size={14} color={muted} /><Text variant="caption" tone="muted">{task.comments}</Text></View> : null}
        {task.files ? <View style={styles.count}><Icon name="paperclip" size={14} color={muted} /><Text variant="caption" tone="muted">{task.files}</Text></View> : null}
      </View>
    </Pressable>
  );
});

type Group = ReturnType<FlxgEngine['groups']>[number];
type Item = { head: Group } | { add: Group } | { task: FlxgTask; depth: number };

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
  const [openSub, setOpenSub] = useState<Record<number, boolean>>({});
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

  const S = engine.S;
  const cfg = engine.viewCfg();
  const board = S.view?.type === 'board';
  const views = S.views.filter((v) => v.type === 'list' || v.type === 'board');
  const canAdd = S.lists.some((l) => l.role === 'edit' || l.role === 'admin');
  const kids = engine.childIndex();

  const navigate = async () => {
    const items = [{ id: 'home', label: t('mobile.screens.tasks.mine'), icon: 'home' }];
    for (const sp of S.spaces) {
      for (const l of S.lists.filter((x) => x.space_id === sp.id)) items.push({ id: 'l' + l.id, label: sp.name + ' › ' + l.name, icon: 'tasks' });
    }
    for (const l of S.lists.filter((x) => !S.spaces.some((sp) => sp.id === x.space_id))) items.push({ id: 'l' + l.id, label: l.name, icon: 'tasks' });
    const pick = await overlays.menu(t('mobile.screens.tasks.title'), items);
    if (!pick) return;
    void engine.go(pick === 'home' ? { page: 'home', id: 0, view: 0, task: 0 } : { page: 'list', id: +pick.slice(1), view: 0, task: 0 });
  };
  const pickGroup = async () => {
    const opts = engine.groupOptions();
    const pick = await overlays.menu(t('mobile.screens.tasks.group'), opts.map((o) => ({ id: o.id, label: o.label })));
    if (!pick) return;
    engine.viewCfg().group = pick;
    engine.touchDraft();
  };
  const pickSort = async () => {
    const opts = [['position', t('mobile.screens.tasks.sortManual')], ['due', t('mobile.screens.tasks.sortDue')], ['priority', t('mobile.screens.tasks.sortPriority')], ['created', t('mobile.screens.tasks.sortCreated')], ['title', t('mobile.screens.tasks.sortTitle')]] as const;
    const pick = await overlays.menu(t('mobile.screens.tasks.sort'), opts.map(([id, label]) => ({ id, label })));
    if (!pick) return;
    engine.viewCfg().sort = { by: pick, dir: 'asc' };
    engine.touchDraft();
  };
  const addIn = async (g?: Group) => {
    const v = await renderer.ui.prompt({ title: t('mobile.screens.tasks.add'), fields: [{ name: 'title', type: 'text', label: t('mobile.screens.tasks.addTitle'), required: true }] });
    const title = String(v?.title ?? '').trim();
    const list = S.route.page === 'list' ? S.route.id : S.lists.find((l) => l.role === 'edit' || l.role === 'admin')?.id ?? S.lists[0]?.id;
    if (!title || !list) return;
    const extra = g?.patch ? g.patch({ list, status: '', assignees: [] } as unknown as FlxgTask, null) : null;
    const task = await engine.createTask({ title, list, ...(extra ?? {}) });
    if (task) void open(task.id);
  };

  const groups = engine.groups(tasks, cfg.group || 'status');
  const rows: Item[] = [];
  const pushTask = (task: FlxgTask, depth: number, guard: number) => {
    rows.push({ task, depth });
    if (guard < 6 && openSub[task.id]) for (const k of kids[task.id] ?? []) pushTask(k, depth + 1, guard + 1);
  };
  for (const g of groups) {
    rows.push({ head: g });
    if (shut[g.key]) continue;
    for (const task of g.tasks) pushTask(task, 0, 0);
    if (canAdd && g.patch) rows.push({ add: g });
  }

  return (
    <KeyboardView testID="screen-tasks" style={styles.fill} chrome>
      <View style={styles.top}>
        <IconButton icon="menu" label={t('mobile.screens.tasks.projects')} onPress={() => void navigate()} />
        <Text variant="heading" numberOfLines={1} style={styles.flex}>{scopeTitle}</Text>
      </View>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.tabsScroll} contentContainerStyle={styles.tabs}>
        {(views.length ? views : [null]).map((v) => {
          const on = v ? S.view?.id === v.id : true;
          return (
            <Pressable key={v?.id ?? 'list'} accessibilityRole="tab" accessibilityState={{ selected: on }} onPress={() => v && (engine.setView(v), engine.bump())} style={[styles.tab, on && styles.tabOn]}>
              <Icon name={v?.type === 'board' ? 'board' : 'list'} size={16} color={on ? theme.colors.text : theme.colors.textMuted} />
              <Text style={on ? undefined : styles.muted}>{v?.name || t('mobile.screens.tasks.list')}</Text>
            </Pressable>
          );
        })}
      </ScrollView>
      <View style={styles.tools}>
        <View style={styles.flex}><SearchField testID="tasks-search" value={q} onChange={(v) => (setQ(v), engine.setQuery(v))} placeholder={t('mobile.screens.tasks.search')} /></View>
        <Pressable accessibilityRole="button" accessibilityLabel={t('mobile.screens.tasks.group')} onPress={() => void pickGroup()} style={styles.round}><Icon name="group" size={18} color={theme.colors.text} /></Pressable>
        <Pressable accessibilityRole="button" accessibilityLabel={t('mobile.screens.tasks.sort')} onPress={() => void pickSort()} style={styles.round}><Icon name="sort" size={18} color={theme.colors.text} /></Pressable>
      </View>
      {!board ? (
        <FlashList
          style={styles.flex}
          contentContainerStyle={styles.listPad}
          data={rows}
          keyExtractor={(it) => ('head' in it ? 'g' + it.head.key : 'add' in it ? 'a' + it.add.key : String(it.task.id))}
          getItemType={(it) => ('head' in it ? 'head' : 'add' in it ? 'add' : 'row')}
          renderItem={({ item }) => {
            if ('head' in item) {
              const g = item.head;
              return (
                <Pressable accessibilityRole="button" onPress={() => setShut((m) => ({ ...m, [g.key]: !m[g.key] }))} style={styles.group}>
                  <Icon name={shut[g.key] ? 'chevron' : 'chevron-down'} size={16} color={theme.colors.textMuted} />
                  <View style={[styles.pill, { backgroundColor: g.color }]}><Text variant="caption" style={styles.pillText}>{g.label.toUpperCase()}</Text></View>
                  <Text variant="caption" tone="muted">{g.tasks.length}</Text>
                </Pressable>
              );
            }
            if ('add' in item) {
              return (
                <Pressable accessibilityRole="button" onPress={() => void addIn(item.add)} style={styles.addRow}>
                  <Icon name="plus" size={16} color={theme.colors.textMuted} />
                  <Text tone="muted">{t('mobile.screens.tasks.add')}</Text>
                </Pressable>
              );
            }
            const task = item.task;
            const st = engine.status(task);
            const overdue = !!task.due && task.due.slice(0, 10) < S.config.today && st.type !== 'done';
            const hasKids = (kids[task.id]?.length ?? 0) > 0;
            return (
              <TaskRow
                task={task}
                depth={item.depth}
                color={st.color}
                names={namesOf(task)}
                listName={sc?.type === 'list' ? '' : engine.list(task.list)?.name ?? ''}
                overdue={overdue}
                done={st.type === 'done'}
                kids={hasKids ? (openSub[task.id] ? 'open' : 'closed') : 'none'}
                onKids={() => setOpenSub((m) => ({ ...m, [task.id]: !m[task.id] }))}
                onPress={() => void open(task.id)}
                onToggle={() => toggleDone(task)}
              />
            );
          }}
          ListEmptyComponent={S.loading ? <Skeleton variant="cards" /> : <EmptyState icon="task" title={t('mobile.screens.tasks.empty')} body={t('mobile.screens.tasks.emptyBody')} />}
        />
      ) : (
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.board}>
          {groups.map((g) => (
            <View key={g.key} style={styles.column}>
              <View style={styles.colHead}>
                <View style={[styles.pill, { backgroundColor: g.color }]}><Text variant="caption" style={styles.pillText} numberOfLines={1}>{g.label.toUpperCase()}</Text></View>
                <Text variant="caption" tone="muted">{g.tasks.length}</Text>
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
      {canAdd ? (
        <Pressable accessibilityRole="button" accessibilityLabel={t('mobile.screens.tasks.add')} onPress={() => void addIn()} style={styles.fab}>
          <Icon name="plus" size={26} color={theme.colors.onPrimary} />
        </Pressable>
      ) : null}
    </KeyboardView>
  );
}

const styles = StyleSheet.create((theme) => ({
  fill: { flex: 1, backgroundColor: theme.colors.bg },
  flex: { flex: 1 },
  hscroll: { flexGrow: 0, maxHeight: 52 },
  filters: { paddingRight: theme.space[4], alignItems: 'center' },
  tools: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingRight: theme.space[4] },
  top: { flexDirection: 'row', alignItems: 'center', gap: 4, paddingHorizontal: theme.space[2] },
  tabsScroll: { flexGrow: 0, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: theme.colors.border },
  tabs: { paddingHorizontal: theme.space[3] },
  tab: { flexDirection: 'row', alignItems: 'center', gap: 6, minHeight: 44, paddingHorizontal: 10, borderBottomWidth: 2, borderBottomColor: 'transparent' },
  tabOn: { borderBottomColor: theme.colors.text },
  muted: { color: theme.colors.textMuted },
  round: { width: 44, height: 44, borderRadius: 22, borderWidth: 1, borderColor: theme.colors.border, backgroundColor: theme.colors.surface, alignItems: 'center', justifyContent: 'center' },
  listPad: { paddingBottom: 96 },
  caret: { width: 18, alignItems: 'center', marginRight: -4 },
  counts: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  count: { flexDirection: 'row', alignItems: 'center', gap: 2 },
  pillText: { color: '#fff', fontWeight: '700' },
  addRow: { flexDirection: 'row', alignItems: 'center', gap: 8, minHeight: 44, paddingHorizontal: theme.space[4] + 36, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: theme.colors.border },
  fab: { position: 'absolute', right: 20, bottom: 24, width: 56, height: 56, borderRadius: 28, backgroundColor: theme.colors.primary, alignItems: 'center', justifyContent: 'center', shadowColor: '#000', shadowOpacity: 0.25, shadowRadius: 8, shadowOffset: { width: 0, height: 3 }, elevation: 6 },
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
