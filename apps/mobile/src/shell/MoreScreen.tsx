// "Menü": everything the user can open on the site (wp-admin menu, role-filtered by flx-app), each item
// resolved through the same targets as the shell (manifest screen → native → webview).
import { useMemo } from 'react';
import { Pressable } from 'react-native';
import { FlashList } from '@shopify/flash-list';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { useMe, useRenderer, useResolveEnv, resolveTarget, type Route } from '@fluxwp/runtime';
import { Icon } from '@/ui/Icon';
import { Badge, EmptyState, Skeleton, Text } from '@/ui/primitives';

interface Row {
  key: string;
  title: string;
  icon: string;
  slug: string;
  iconUrl?: string;
  badge?: number | string;
  route: Route;
  child: boolean;
}

export function MoreScreen() {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const me = useMe();
  const env = useResolveEnv();
  const renderer = useRenderer();
  const rows = useMemo<Row[]>(() => {
    const out: Row[] = [];
    for (const m of me.data?.menu ?? []) {
      out.push({ key: m.id || m.slug, title: m.title, icon: m.icon, slug: m.slug, iconUrl: m.icon_url, badge: m.badge || undefined, route: resolveTarget(`menu:${m.slug}`, env), child: false });
      for (const c of m.items?.length > 1 ? m.items.slice(1) : []) out.push({ key: `${m.slug}/${c.slug}`, title: c.title, icon: '', slug: c.slug, badge: c.badge || undefined, route: resolveTarget(`menu:${c.slug}`, env), child: true });
    }
    return out;
  }, [me.data, env]);

  if (me.isPending) return <Skeleton />;
  if (!rows.length) return <EmptyState title={t('list.empty')} />;
  return (
    <FlashList
      data={rows}
      keyExtractor={(r) => r.key}
      style={styles.list}
      renderItem={({ item }) => (
        <Pressable accessibilityRole="button" onPress={() => renderer.nav.push(item.route)} style={({ pressed }) => [styles.row, item.child && styles.child, pressed && styles.pressed]}>
          {item.child ? null : <Icon name={item.iconUrl || item.icon} slug={item.slug} size={20} color={theme.colors.textMuted} />}
          <Text numberOfLines={1} style={[styles.flex, item.child && styles.childText]}>{item.title}</Text>
          {item.badge ? <Badge text={String(item.badge)} tone="danger" /> : null}
          <Icon name={item.route.kind === 'web' ? 'external' : 'chevron'} size={16} color={theme.colors.textMuted} />
        </Pressable>
      )}
    />
  );
}

const styles = StyleSheet.create((theme) => ({
  list: { backgroundColor: theme.colors.bg },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, minHeight: 48, marginHorizontal: 12, marginBottom: 6, paddingHorizontal: 12, backgroundColor: theme.colors.surface, borderWidth: 1, borderColor: theme.colors.border, borderRadius: theme.radius.md },
  child: { marginLeft: 28, minHeight: 42 },
  childText: { color: theme.colors.textMuted, fontSize: 14 },
  pressed: { backgroundColor: theme.colors.primarySoft },
  flex: { flex: 1 },
}));
