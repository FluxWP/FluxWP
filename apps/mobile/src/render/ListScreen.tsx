// Manifest `list` screen: draws useListController (search, filters, pull-to-refresh, pagination, rows,
// row actions on long press, FAB). No data logic here.
import { useLayoutEffect } from 'react';
import { Pressable, RefreshControl, TextInput, View } from 'react-native';
import { FlashList } from '@shopify/flash-list';
import { useNavigation } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { stripAngles } from '@fluxwp/core';
import { useListController, type ListController, type RowView } from '@fluxwp/runtime';
import type { ListScreen as ListDef, Manifest } from '@fluxwp/manifest';
import { Icon } from '@/ui/Icon';
import { overlays } from '@/ui/overlays';
import { Avatar, Badge, EmptyState, IconButton, Segmented, Skeleton, Spinner, Text } from '@/ui/primitives';

export function ListScreen({ manifest, screen, params }: { manifest: Manifest; screen: ListDef; params: Record<string, string> }) {
  const c = useListController(manifest, screen, params);
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const nav = useNavigation();

  useLayoutEffect(() => {
    nav.setOptions({
      title: c.title,
      headerRight: c.headerActions.length ? () => <View style={styles.headerActions}>{c.headerActions.slice(0, 2).map((a) => <IconButton key={a.id} icon={a.icon ?? 'plus'} label={a.label} onPress={() => void c.runAction(a.id)} />)}</View> : undefined,
    });
  }, [nav, c.title, c.headerActions, c]);

  const header = (
    <View style={styles.top}>
      {c.search ? (
        <View style={styles.search}>
          <Icon name="search" size={18} color={theme.colors.textMuted} />
          <TextInput value={c.search.value} onChangeText={c.search.set} placeholder={c.search.placeholder || t('list.search')} placeholderTextColor={theme.colors.textMuted} style={styles.searchInput} returnKeyType="search" autoCapitalize="none" accessibilityLabel={t('list.search')} clearButtonMode="while-editing" />
        </View>
      ) : null}
      {c.filters.filter((f) => f.type === 'segmented' && f.options.length).map((f) => (
        <Segmented key={f.param} options={f.options} value={String(f.value ?? '')} onChange={(v) => c.setFilter(f.param, v)} />
      ))}
      {c.stale ? <Text variant="caption" style={styles.offline}>{t('mobile.shell.offline')}</Text> : null}
    </View>
  );

  return (
    <View style={styles.screen}>
      {header}
      {c.loading ? (
        <Skeleton />
      ) : c.error && !c.rows.length ? (
        <EmptyState icon="alert" title={t('states.errorTitle')} body={t('states.errorBody')} action={{ label: t('mobile.states.retry'), onPress: () => void c.refresh() }} />
      ) : (
        <FlashList
          data={c.rows}
          keyExtractor={(r) => r.key}
          renderItem={({ item }) => <Row row={item} c={c} />}
          onEndReached={() => void c.loadMore()}
          onEndReachedThreshold={0.5}
          refreshControl={<RefreshControl refreshing={c.refreshing} onRefresh={() => void c.refresh()} tintColor={theme.colors.primary} />}
          ListEmptyComponent={<EmptyState icon={c.empty.icon ?? (c.empty.searching ? 'search' : 'inbox')} title={c.empty.searching ? t('list.noResults') : c.empty.title || t('list.empty')} body={c.empty.searching ? undefined : c.empty.body} />}
          ListFooterComponent={c.loadingMore ? <Spinner /> : <View style={styles.footer} />}
          contentContainerStyle={styles.content}
        />
      )}
      {c.fab ? (
        <Pressable accessibilityRole="button" accessibilityLabel={c.fab.label} onPress={() => void c.runAction(c.fab!.id)} style={({ pressed }) => [styles.fab, pressed && styles.pressed]}>
          <Icon name={c.fab.icon ?? 'plus'} size={24} color={theme.colors.onPrimary} />
        </Pressable>
      ) : null}
    </View>
  );
}

function Row({ row, c }: { row: RowView; c: ListController }) {
  const { theme } = useUnistyles();
  const actions = c.rowActions(row);
  const longPress = actions.length
    ? async () => {
        const id = await overlays.menu(row.title, actions.map((a) => ({ id: a.id, label: a.label, icon: a.icon, danger: a.tone === 'danger' })));
        if (id) await c.runAction(id, row);
      }
    : undefined;
  return (
    <Pressable onPress={c.onPress ? () => c.onPress!(row) : undefined} onLongPress={longPress} accessibilityRole={c.onPress ? 'button' : undefined} style={({ pressed }) => [styles.row, pressed && styles.rowPressed]}>
      {row.avatar ? <Avatar image={row.avatar.image} text={row.avatar.text} size={42} /> : null}
      <View style={styles.rowBody}>
        <View style={styles.rowLine}>
          <Text numberOfLines={1} style={[styles.rowTitle, row.unread && styles.unread]}>{row.title}</Text>
          {row.meta ? <Text variant="small" tone="muted" numberOfLines={1}>{row.meta}</Text> : null}
        </View>
        {row.subtitle ? <Text variant="small" tone="muted" numberOfLines={2}>{stripAngles(row.subtitle)}</Text> : null}
        {row.badges.length || row.chips.length ? (
          <View style={styles.badges}>
            {row.badges.map((b, i) => <Badge key={'b' + i} text={b.text} tone={b.tone} />)}
            {row.chips.map((ch, i) => <Badge key={'c' + i} text={ch.text} tone="muted" />)}
          </View>
        ) : null}
        {row.caption ? <Text variant="caption">{row.caption}</Text> : null}
      </View>
      {c.onPress ? <Icon name="chevron" size={18} color={theme.colors.textMuted} /> : null}
    </Pressable>
  );
}

const styles = StyleSheet.create((theme) => ({
  screen: { flex: 1, backgroundColor: theme.colors.bg },
  top: { paddingHorizontal: theme.space[4], paddingTop: theme.space[3], paddingBottom: theme.space[2], gap: theme.space[3] },
  search: { flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: theme.colors.surface, borderWidth: 1, borderColor: theme.colors.border, borderRadius: theme.radius.md, paddingHorizontal: 12, minHeight: 44 },
  searchInput: { flex: 1, color: theme.colors.text, fontSize: 16, paddingVertical: 8, fontFamily: theme.font.family },
  offline: { color: theme.colors.warning },
  content: { paddingTop: 8, paddingBottom: 96 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, marginHorizontal: 12, marginBottom: 8, paddingHorizontal: theme.space[4], paddingVertical: 12, backgroundColor: theme.colors.surface, borderWidth: 1, borderColor: theme.colors.border, borderRadius: theme.radius.lg },
  rowPressed: { backgroundColor: theme.colors.surface2 },
  rowBody: { flex: 1, gap: 4 },
  rowLine: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  rowTitle: { flex: 1, fontSize: 16, fontWeight: '500' },
  unread: { fontWeight: '700' },
  badges: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  footer: { height: 24 },
  fab: { position: 'absolute', right: 20, bottom: 24, width: 56, height: 56, borderRadius: 28, backgroundColor: theme.colors.primary, alignItems: 'center', justifyContent: 'center', shadowColor: '#000', shadowOpacity: 0.2, shadowRadius: 8, shadowOffset: { width: 0, height: 4 }, elevation: 4 },
  pressed: { opacity: 0.8 },
  headerActions: { flexDirection: 'row' },
}));
