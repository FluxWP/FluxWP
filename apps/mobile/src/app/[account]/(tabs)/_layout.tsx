// Five fixed tab slots t0..t4 (plan §3.2); config.shell.tabs decides which are used and what they open.
import { Tabs } from 'expo-router';
import { useShellModel } from '@fluxwp/runtime';
import { TabBar } from '@/shell/TabBar';

const SLOTS = ['(t0)', '(t1)', '(t2)', '(t3)', '(t4)'] as const;

export default function TabsLayout() {
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
