// Five fixed tab slots t0..t4 (plan §3.2); config.shell.tabs decides which are used and what they open.
// iOS and Android use the platform tab bar (UITabBarController / Material bottom navigation) through
// expo-router NativeTabs; web keeps the drawn TabBar.
import { Platform } from 'react-native';
import { Tabs } from 'expo-router';
import { NativeTabs } from 'expo-router/native-tabs';
import { useUnistyles } from 'react-native-unistyles';
import { useShellModel } from '@fluxwp/runtime';
import { DrawerCard } from '@/shell/DrawerCard';
import { useLabel } from '@/shell/label';
import { nativeGlyph } from '@/shell/nativeIcon';
import { TabBar } from '@/shell/TabBar';

const SLOTS = ['(t0)', '(t1)', '(t2)', '(t3)', '(t4)'] as const;

export default function TabsLayout() {
  return <DrawerCard>{Platform.OS === 'web' ? <DrawnTabs /> : <PlatformTabs />}</DrawerCard>;
}

function PlatformTabs() {
  const shell = useShellModel();
  const label = useLabel();
  const { theme } = useUnistyles();
  const android = Platform.OS === 'android';
  return (
    <NativeTabs
      hidden={!shell.tabs.length}
      tintColor={theme.colors.primary}
      iconColor={android ? { default: theme.colors.textMuted, selected: theme.colors.primary } : undefined}
      labelStyle={android ? { default: { color: theme.colors.textMuted }, selected: { color: theme.colors.text } } : undefined}
      backgroundColor={android ? theme.colors.sidebar : undefined}
      indicatorColor={android ? theme.colors.primarySoft : undefined}
      rippleColor={android ? theme.colors.primarySoft : undefined}
      badgeBackgroundColor={theme.colors.danger}
      labelVisibilityMode={android ? 'labeled' : undefined}
    >
      {SLOTS.map((name, i) => {
        const tab = shell.tabs[i];
        const glyph = nativeGlyph(tab?.icon, tab?.slug);
        return (
          <NativeTabs.Trigger key={name} name={name} hidden={!tab && i > 0} disableAutomaticContentInsets={!android} testID={tab ? `tab-${tab.id}` : undefined}>
            <NativeTabs.Trigger.Label>{tab ? label(tab.label) : ''}</NativeTabs.Trigger.Label>
            <NativeTabs.Trigger.Icon sf={{ default: glyph.sf, selected: glyph.sfOn }} md={glyph.md} />
            {tab?.badge ? <NativeTabs.Trigger.Badge>{String(tab.badge)}</NativeTabs.Trigger.Badge> : null}
          </NativeTabs.Trigger>
        );
      })}
    </NativeTabs>
  );
}

function DrawnTabs() {
  const shell = useShellModel();
  return (
    <Tabs
      screenOptions={{ headerShown: false }}
      tabBar={({ state, navigateToTab }) => {
        const active = SLOTS.indexOf(state.routes[state.index]?.name as (typeof SLOTS)[number]);
        return (
          <TabBar
            tabs={shell.tabs}
            active={active}
            onPress={(i) => {
              const r = state.routes.find((x) => x.name === SLOTS[i]);
              if (r) navigateToTab(r.key);
            }}
          />
        );
      }}
    >
      {SLOTS.map((name, i) => <Tabs.Screen key={name} name={name} options={{ href: i < Math.max(1, shell.tabs.length) ? undefined : null }} />)}
    </Tabs>
  );
}
