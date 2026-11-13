// Home (`native:dashboard`): Flux App /dashboard cards + lists, greeting, pull-to-refresh.
import { RefreshControl, ScrollView, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { useDashboard, useMe, useNavigate } from '@fluxwp/runtime';
import { Badge, Card, EmptyState, Skeleton, Text } from '@/ui/primitives';
import { Icon } from '@/ui/Icon';
import { useScrollChrome } from '@/shell/chrome';

const toneColor = (t: string | undefined, c: ReturnType<typeof useUnistyles>['theme']['colors']) =>
  t === 'ok' ? c.success : t === 'warn' ? c.warning : t === 'danger' ? c.danger : t === 'accent' ? c.accent : c.primary;

export default function Dashboard() {
  const scrollChrome = useScrollChrome();
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const me = useMe().data;
  const q = useDashboard();
  const { go } = useNavigate();
  const h = new Date().getHours();
  const hello = t(h < 12 ? 'dashboard.morning' : h < 18 ? 'dashboard.afternoon' : 'dashboard.evening', { name: me?.user.name?.split(' ')[0] ?? '' });

  if (q.isPending) return <Skeleton />;
  if (!q.data) return <EmptyState icon="alert" title={t('states.errorTitle')} body={t('states.errorBody')} action={{ label: t('mobile.states.retry'), onPress: () => void q.refetch() }} />;

  const cards = [...q.data.cards].sort((a, b) => (a.order ?? 50) - (b.order ?? 50));
  const lists = [...q.data.lists].sort((a, b) => (a.order ?? 50) - (b.order ?? 50));
  return (
    <ScrollView {...scrollChrome} contentContainerStyle={styles.wrap} refreshControl={<RefreshControl refreshing={q.isRefetching} onRefresh={() => void q.refetch()} tintColor={theme.colors.primary} />}>
      <Text variant="title">{hello}</Text>
      {me ? <Text tone="muted">{me.site.name}</Text> : null}
      <View style={styles.grid}>
        {cards.map((c) => (
          <Card key={c.id} style={styles.card} onPress={c.open ? () => go({ target: c.open! }, {}) : undefined}>
            <View style={styles.cardHead}>
              <Icon name={c.icon} size={18} color={toneColor(c.tone, theme.colors)} />
              <Text variant="small" tone="muted" numberOfLines={1} style={styles.flex}>{c.title}</Text>
            </View>
            <Text variant="title" style={styles.value}>{c.value}</Text>
            {c.sub ? <Text variant="caption">{c.sub}</Text> : null}
          </Card>
        ))}
      </View>
      {lists.map((l) => (
        <Card key={l.id} style={styles.list}>
          <View style={styles.cardHead}>
            <Icon name={l.icon} size={18} color={theme.colors.primary} />
            <Text variant="heading">{l.title}</Text>
          </View>
          {l.items.map((it, i) => (
            <View key={i} style={[styles.row, i > 0 && styles.rowLine]}>
              <View style={styles.flex}>
                <Text numberOfLines={1}>{it.title}</Text>
                {it.sub ? <Text variant="caption" numberOfLines={1}>{it.sub}</Text> : null}
              </View>
              {it.meta ? <Badge text={it.meta} tone="muted" /> : null}
            </View>
          ))}
        </Card>
      ))}
    </ScrollView>
  );
}

const styles = StyleSheet.create((theme, rt) => ({
  wrap: { padding: theme.space[4], gap: theme.space[3], paddingBottom: theme.space[12] },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space[3], marginTop: theme.space[2] },
  card: { width: { xs: (rt.screen.width - theme.space[4] * 2 - theme.space[3]) / 2 - 0.5, md: 220 }, gap: 4, padding: theme.space[3] },
  cardHead: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  value: { fontSize: 24 },
  list: { gap: theme.space[2] },
  row: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingVertical: 8 },
  rowLine: { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: theme.colors.border },
  flex: { flex: 1 },
}));
