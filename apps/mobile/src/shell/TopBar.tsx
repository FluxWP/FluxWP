// Top bar (config.shell.topbar): drawer button on tab roots, back elsewhere, title, config actions and
// the site avatar that opens the quick account switcher.
import type { ReactNode } from 'react';
import { View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { useAccount, useRenderer, useShellModel } from '@fluxwp/runtime';
import { GlassButton, GlassPill } from '@/ui/Glass';
import { Avatar, Text } from '@/ui/primitives';
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
  const switcher = root && shell.topbar.site_switcher;
  const title = options.title ?? '';
  // Root without a screen title: the site chip carries the name. With a title, the chip shrinks to the icon.
  const chipName = switcher && !title;

  return (
    <View style={[styles.bar, { paddingTop: insets.top + 4 }]}>
      <View style={styles.row}>
        {back ? (
          <GlassButton icon="back" label={t('mobile.shell.back')} onPress={() => navigation.goBack()} />
        ) : shell.drawer.enabled ? (
          <GlassButton testID="open-drawer" icon="menu" label={t('mobile.shell.openDrawer')} onPress={() => navigation.dispatch({ type: 'OPEN_DRAWER' })} />
        ) : null}
        <Text variant="heading" numberOfLines={1} style={styles.title} accessibilityRole="header">{title}</Text>
        {options.headerRight ? <View style={styles.custom}>{options.headerRight({ tintColor: theme.colors.text, canGoBack: !!back })}</View> : null}
        {root ? shell.topbar.actionEntries.map((a) => <GlassButton key={a.id} icon={a.icon} label={label(a.label)} badge={a.badge} onPress={() => renderer.nav.push(a.route)} />) : null}
        {switcher ? (
          chipName ? (
            <GlassPill testID="site-switcher" accessibilityLabel={t('switcher.title')} onPress={openSwitcher} maxWidth={200}
              leading={<Avatar image={account.siteIcon || undefined} text={account.siteName} size={28} square />} label={account.siteName} />
          ) : (
            <GlassButton testID="site-switcher" label={t('switcher.title')} onPress={openSwitcher}>
              <Avatar image={account.siteIcon || undefined} text={account.siteName} size={28} square />
            </GlassButton>
          )
        ) : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  bar: { backgroundColor: theme.colors.bg, paddingBottom: 6 },
  row: { minHeight: 52, flexDirection: 'row', alignItems: 'center', paddingHorizontal: 14, gap: 8 },
  title: { flex: 1, marginLeft: 4, fontFamily: theme.font.heading, fontWeight: '800', letterSpacing: -0.3 },
  custom: { flexDirection: 'row', alignItems: 'center' },
}));
