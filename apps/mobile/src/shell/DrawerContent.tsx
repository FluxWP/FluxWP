// Side drawer from config.shell.drawer: account header (tap = switcher), pins, groups (collapsible), footer.
import { useState } from 'react';
import { Pressable, ScrollView, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { useAccount, useRenderer, useShellModel, type NavEntry } from '@fluxwp/runtime';
import { brand } from '@/brand';
import { Icon } from '@/ui/Icon';
import { Avatar, Badge, Text } from '@/ui/primitives';
import { openSwitcher } from './AccountSwitcher';
import { useLabel } from './label';

export function DrawerContent({ close }: { close: () => void }) {
  const shell = useShellModel();
  const account = useAccount();
  const renderer = useRenderer();
  const insets = useSafeAreaInsets();
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const [open, setOpen] = useState<Record<string, boolean>>({});
  const go = (e: NavEntry) => {
    close();
    renderer.nav.push(e.route);
  };
  return (
    <ScrollView style={styles.drawer} contentContainerStyle={{ paddingTop: insets.top + 8, paddingBottom: insets.bottom + 16 }}>
      {shell.drawer.header === 'account' ? (
        <Pressable accessibilityRole="button" accessibilityLabel={t('switcher.title')} onPress={() => (close(), openSwitcher())} style={styles.account}>
          <Avatar image={account.siteIcon || undefined} text={account.siteName} size={44} square />
          <View style={styles.flex}>
            <Text variant="heading" numberOfLines={1}>{account.siteName}</Text>
            <Text variant="small" tone="muted" numberOfLines={1}>{account.userName} · {account.siteUrl.replace(/^https?:\/\//, '')}</Text>
          </View>
          <Icon name="chevron-down" size={18} color={theme.colors.textMuted} />
        </Pressable>
      ) : shell.drawer.header === 'brand' ? (
        <Text variant="title" style={styles.brand}>{brand.name}</Text>
      ) : null}
      {shell.drawer.pins.map((e) => <Item key={e.id} e={e} onPress={go} />)}
      {shell.drawer.groups.map((g) => {
        const collapsed = open[g.id] === undefined ? g.collapsed : !open[g.id];
        return (
          <View key={g.id} style={styles.group}>
            <Pressable accessibilityRole="button" accessibilityState={{ expanded: !collapsed }} onPress={() => setOpen((o) => ({ ...o, [g.id]: !!collapsed }))} style={styles.groupHead}>
              <Text variant="caption" style={styles.groupTitle}>{g.label.startsWith('$t:') ? t(g.label.slice(3)) : g.label}</Text>
              <Icon name={collapsed ? 'chevron' : 'chevron-down'} size={14} color={theme.colors.textMuted} />
            </Pressable>
            {!collapsed && g.items.map((e) => <Item key={e.id} e={e} onPress={go} />)}
          </View>
        );
      })}
      <View style={styles.sep} />
      {shell.drawer.footer.map((e) => <Item key={e.id} e={e} onPress={go} />)}
    </ScrollView>
  );
}

function Item({ e, onPress }: { e: NavEntry; onPress: (e: NavEntry) => void }) {
  const { theme } = useUnistyles();
  const label = useLabel();
  return (
    <Pressable testID={`nav-${e.id}`} accessibilityRole="button" onPress={() => onPress(e)} style={({ pressed }) => [styles.item, pressed && styles.pressed]}>
      <Icon name={e.iconUrl || e.icon} slug={e.slug} size={20} color={theme.colors.textMuted} />
      <Text numberOfLines={1} style={styles.itemLabel}>{label(e.label)}</Text>
      {e.badge ? <Badge text={String(e.badge)} tone="danger" /> : null}
      {e.route.kind === 'web' ? <Icon name="external" size={14} color={theme.colors.textMuted} /> : null}
    </Pressable>
  );
}

const styles = StyleSheet.create((theme) => ({
  drawer: { flex: 1, backgroundColor: theme.colors.sidebar, borderTopRightRadius: 22, borderBottomRightRadius: 22 },
  account: { flexDirection: 'row', alignItems: 'center', gap: 12, marginHorizontal: 8, marginBottom: 8, padding: 8, borderRadius: theme.radius.md },
  brand: { paddingHorizontal: 20, marginBottom: 12 },
  flex: { flex: 1 },
  group: { marginTop: 14 },
  groupHead: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 18, paddingVertical: 6 },
  groupTitle: { textTransform: 'uppercase', letterSpacing: 0.6, fontWeight: '700' },
  item: { flexDirection: 'row', alignItems: 'center', gap: 10, minHeight: 46, paddingHorizontal: 12, marginHorizontal: 8, borderRadius: theme.radius.md },
  itemLabel: { flex: 1, fontSize: 15, fontWeight: '500' },
  pressed: { backgroundColor: theme.colors.primarySoft },
  sep: { height: StyleSheet.hairlineWidth, backgroundColor: theme.colors.border, marginVertical: 12, marginHorizontal: 16 },
}));
