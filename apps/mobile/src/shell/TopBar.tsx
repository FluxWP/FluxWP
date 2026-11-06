// Top bar (config.shell.topbar): drawer button on tab roots, back elsewhere, title, config actions and
// the site avatar that opens the quick account switcher.
import type { ReactNode } from 'react';
import { Pressable, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { useAccount, useRenderer, useShellModel } from '@fluxwp/runtime';
import { Avatar, IconButton, Text } from '@/ui/primitives';
import { openSwitcher } from './AccountSwitcher';
import { useLabel } from './label';

export interface TopBarProps {
  options: { title?: string; headerRight?: (p: { tintColor?: string; canGoBack: boolean }) => ReactNode };
  back?: { title?: string } | undefined;
  navigation: { goBack(): void; dispatch(a: { type: string }): void };
}

export function TopBar({ options, back, navigation }: TopBarProps) {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const insets = useSafeAreaInsets();
  const shell = useShellModel();
  const account = useAccount();
  const renderer = useRenderer();
  const label = useLabel();
  const root = !back;
  const title = options.title || (root && shell.topbar.site_switcher ? account.siteName : '');

  return (
    <View style={[styles.bar, { paddingTop: insets.top }]}>
      <View style={styles.row}>
        {back ? (
          <IconButton icon="back" label={t('mobile.shell.back')} onPress={() => navigation.goBack()} />
        ) : shell.drawer.enabled ? (
          <IconButton testID="open-drawer" icon="menu" label={t('mobile.shell.openDrawer')} onPress={() => navigation.dispatch({ type: 'OPEN_DRAWER' })} />
        ) : (
          <View style={styles.spacer} />
        )}
        <Text variant="heading" numberOfLines={1} style={styles.title} accessibilityRole="header">{title}</Text>
        {options.headerRight?.({ tintColor: theme.colors.text, canGoBack: !!back })}
        {root ? shell.topbar.actionEntries.map((a) => <IconButton key={a.id} icon={a.icon} label={label(a.label)} badge={a.badge} onPress={() => renderer.nav.push(a.route)} />) : null}
        {root && shell.topbar.site_switcher ? (
          <Pressable testID="site-switcher" accessibilityRole="button" accessibilityLabel={t('switcher.title')} onPress={openSwitcher} hitSlop={8} style={styles.avatar}>
            <Avatar image={account.siteIcon || undefined} text={account.siteName} size={32} square />
          </Pressable>
        ) : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  bar: { backgroundColor: theme.colors.sidebar, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: theme.colors.border },
  row: { height: 48, flexDirection: 'row', alignItems: 'center', paddingHorizontal: 4, gap: 4 },
  title: { flex: 1, marginLeft: 4, fontFamily: theme.font.heading, fontWeight: '800', letterSpacing: -0.3 },
  spacer: { width: 12 },
  avatar: { paddingHorizontal: 8 },
}));
