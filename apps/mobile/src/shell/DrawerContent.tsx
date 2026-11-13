// Side panel from config.shell.drawer. It sits behind the screen card: a large title with a floating
// search button on top, pins and collapsible groups in the middle, floating site and footer buttons at
// the bottom. List rows scroll under both floating rows and fade out there.
import { useMemo, useState } from 'react';
import { Platform, Pressable, ScrollView, TextInput, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import Svg, { Defs, LinearGradient, Rect, Stop } from 'react-native-svg';
import { useAccount, useRenderer, useShellModel, type NavEntry } from '@fluxwp/runtime';
import { brand } from '@/brand';
import { Glass, GlassButton, GlassPill } from '@/ui/Glass';
import { Icon } from '@/ui/Icon';
import { Avatar, Badge, Text } from '@/ui/primitives';
import { openSwitcher } from './AccountSwitcher';
import { useLabel } from './label';

const HEAD = 64;
const FOOT = 76;

export function DrawerContent({ close }: { close: () => void }) {
  const shell = useShellModel();
  const account = useAccount();
  const renderer = useRenderer();
  const insets = useSafeAreaInsets();
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const label = useLabel();
  const [open, setOpen] = useState<Record<string, boolean>>({});
  const [query, setQuery] = useState<string | null>(null);
  const go = (e: NavEntry) => {
    close();
    setQuery(null);
    renderer.nav.push(e.route);
  };
  const groupTitle = (s: string) => (s.startsWith('$t:') ? t(s.slice(3)) : s);

  const matches = useMemo(() => {
    const q = (query ?? '').trim().toLocaleLowerCase();
    if (!q) return null;
    const all = [...shell.drawer.pins, ...shell.drawer.groups.flatMap((g) => g.items), ...shell.drawer.footer];
    const seen = new Set<string>();
    return all.filter((e) => {
      const name = label(e.label);
      if (seen.has(name) || !name.toLocaleLowerCase().includes(q)) return false;
      seen.add(name);
      return true;
    });
  }, [query, shell.drawer, label]);

  const title = shell.drawer.header === 'brand' ? brand.name : account.siteName;

  return (
    <View style={styles.drawer}>
      <ScrollView
        keyboardShouldPersistTaps="handled"
        showsVerticalScrollIndicator={false}
        contentContainerStyle={{ paddingTop: insets.top + HEAD + 8, paddingBottom: insets.bottom + FOOT + 16 }}
      >
        {matches ? (
          matches.length ? matches.map((e) => <Item key={e.id} e={e} onPress={go} />) : <Text tone="muted" style={styles.empty}>{t('mobile.drawer.noResults')}</Text>
        ) : (
          <>
            {shell.drawer.pins.map((e) => <Item key={e.id} e={e} onPress={go} />)}
            {shell.drawer.groups.map((g) => {
              const collapsed = open[g.id] === undefined ? g.collapsed : !open[g.id];
              return (
                <View key={g.id} style={styles.group}>
                  <Pressable accessibilityRole="button" accessibilityState={{ expanded: !collapsed }} onPress={() => setOpen((o) => ({ ...o, [g.id]: !!collapsed }))} style={styles.groupHead}>
                    <Text style={styles.groupTitle}>{groupTitle(g.label)}</Text>
                    <Icon name={collapsed ? 'chevron' : 'chevron-down'} size={14} color={theme.colors.textMuted} />
                  </Pressable>
                  {!collapsed && g.items.map((e) => <Item key={e.id} e={e} onPress={go} />)}
                </View>
              );
            })}
          </>
        )}
      </ScrollView>

      <Fade color={theme.colors.sidebar} edge="top" height={insets.top + HEAD + 16} />
      <View style={[styles.head, { top: insets.top + 6 }]}>
        {query === null ? (
          <>
            <Pressable style={styles.titleWrap} accessibilityRole="button" accessibilityLabel={t('switcher.title')} disabled={shell.drawer.header === 'brand'} onPress={() => (close(), openSwitcher())}>
              <Text numberOfLines={1} style={styles.title}>{title}</Text>
              {shell.drawer.header === 'account' ? <Text variant="small" tone="muted" numberOfLines={1}>{account.siteUrl.replace(/^https?:\/\//, '')}</Text> : null}
            </Pressable>
            <GlassButton testID="drawer-search" icon="search" label={t('mobile.drawer.search')} size={48} onPress={() => setQuery('')} />
          </>
        ) : (
          <>
            <Glass style={styles.searchField}>
              <View style={styles.searchIcon}><Icon name="search" size={18} color={theme.colors.textMuted} /></View>
              <TextInput
                testID="drawer-search-input"
                autoFocus
                value={query}
                onChangeText={setQuery}
                placeholder={t('mobile.drawer.searchPlaceholder')}
                placeholderTextColor={theme.colors.textMuted}
                style={styles.searchInput}
                returnKeyType="search"
                autoCorrect={false}
              />
            </Glass>
            <GlassButton icon="x" label={t('mobile.drawer.closeSearch')} size={48} onPress={() => setQuery(null)} />
          </>
        )}
      </View>

      <Fade color={theme.colors.sidebar} edge="bottom" height={insets.bottom + FOOT + 12} />
      <View style={[styles.foot, { bottom: insets.bottom + 12 }]}>
        <GlassButton testID="drawer-switcher" label={t('switcher.title')} size={52} onPress={() => (close(), openSwitcher())}>
          <Avatar image={account.siteIcon || undefined} text={account.siteName} size={30} square />
        </GlassButton>
        <View style={styles.footActions}>
          {shell.drawer.footer.map((e, i, all) => i < all.length - 1 ? (
            <GlassButton key={e.id} testID={`nav-${e.id}`} icon={e.iconUrl || e.icon} label={label(e.label)} size={52} onPress={() => go(e)} />
          ) : (
            <GlassPill key={e.id} testID={`nav-${e.id}`} icon={e.iconUrl || e.icon} label={label(e.label)} onPress={() => go(e)} />
          ))}
        </View>
      </View>
    </View>
  );
}

/** Background → transparent fade so rows dissolve under the floating header and footer. */
function Fade({ color, edge, height }: { color: string; edge: 'top' | 'bottom'; height: number }) {
  const id = `fade-${edge}`;
  return (
    <View pointerEvents="none" style={[styles.fade, edge === 'top' ? { top: 0 } : { bottom: 0 }, { height }]}>
      <Svg width="100%" height="100%">
        <Defs>
          <LinearGradient id={id} x1="0" y1={edge === 'top' ? '0' : '1'} x2="0" y2={edge === 'top' ? '1' : '0'}>
            <Stop offset="0" stopColor={color} stopOpacity={1} />
            <Stop offset="0.6" stopColor={color} stopOpacity={0.85} />
            <Stop offset="1" stopColor={color} stopOpacity={0} />
          </LinearGradient>
        </Defs>
        <Rect x="0" y="0" width="100%" height="100%" fill={`url(#${id})`} />
      </Svg>
    </View>
  );
}

function Item({ e, onPress }: { e: NavEntry; onPress: (e: NavEntry) => void }) {
  const { theme } = useUnistyles();
  const label = useLabel();
  return (
    <Pressable testID={`nav-${e.id}`} accessibilityRole="button" onPress={() => onPress(e)} style={({ pressed }) => [styles.item, pressed && styles.pressed]}>
      <Icon name={e.iconUrl || e.icon} slug={e.slug} size={22} color={theme.colors.primary} strokeWidth={1.7} />
      <Text numberOfLines={1} style={styles.itemLabel}>{label(e.label)}</Text>
      {e.badge ? <Badge text={String(e.badge)} tone="danger" style={styles.itemBadge} /> : null}
      {e.route.kind === 'web' ? <Icon name="external" size={14} color={theme.colors.textMuted} /> : null}
    </Pressable>
  );
}

const styles = StyleSheet.create((theme) => ({
  drawer: { flex: 1, backgroundColor: theme.colors.sidebar },
  head: { position: 'absolute', left: 16, right: 14, height: HEAD, flexDirection: 'row', alignItems: 'center', gap: 10 },
  titleWrap: { flex: 1, minWidth: 0 },
  title: { fontFamily: theme.font.heading, fontSize: 30, lineHeight: 36, fontWeight: '800', letterSpacing: -0.6, color: theme.colors.text },
  searchField: { flex: 1, height: 48, borderRadius: 24, flexDirection: 'row', alignItems: 'center', gap: 8, paddingHorizontal: 14, overflow: 'hidden' },
  searchIcon: { flexShrink: 0 },
  searchInput: { flex: 1, minWidth: 0, fontSize: 16, color: theme.colors.text, fontFamily: theme.font.family, paddingVertical: 8, ...(Platform.OS === 'web' ? ({ outlineStyle: 'none' } as object) : null) },
  empty: { paddingHorizontal: 24, paddingVertical: 16 },
  group: { marginTop: 18 },
  groupHead: { flexDirection: 'row', alignItems: 'center', gap: 6, paddingHorizontal: 22, paddingVertical: 8 },
  groupTitle: { fontSize: 15, color: theme.colors.textMuted, fontWeight: '500' },
  item: { flexDirection: 'row', alignItems: 'center', gap: 14, minHeight: 50, paddingHorizontal: 14, marginHorizontal: 8, borderRadius: 16 },
  itemLabel: { flex: 1, fontSize: 17, fontWeight: '500', color: theme.colors.sidebarText },
  itemBadge: { alignSelf: 'center' },
  pressed: { backgroundColor: theme.colors.sidebarActive },
  fade: { position: 'absolute', left: 0, right: 0 },
  foot: { position: 'absolute', left: 14, right: 14, height: 56, flexDirection: 'row', alignItems: 'center', gap: 10 },
  footActions: { flex: 1, flexDirection: 'row', justifyContent: 'flex-end', gap: 8, flexWrap: 'nowrap' },
}));
