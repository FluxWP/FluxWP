// Bottom tab bar from config.shell.tabs (≤5 slots t0..t4). Badges come from /sync counts.
import { Pressable, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import type { NavEntry } from '@fluxwp/runtime';
import { Icon } from '@/ui/Icon';
import { Badge, Text } from '@/ui/primitives';
import { useLabel } from './label';

export function TabBar({ tabs, active, onPress }: { tabs: NavEntry[]; active: number; onPress: (i: number) => void }) {
  const { theme } = useUnistyles();
  const insets = useSafeAreaInsets();
  const label = useLabel();
  if (!tabs.length) return null;
  return (
    <View style={[styles.bar, { paddingBottom: Math.max(insets.bottom, 6) }]} accessibilityRole="tablist">
      {tabs.map((tab, i) => {
        const on = i === active;
        const color = on ? theme.colors.primary : theme.colors.textMuted;
        return (
          <Pressable key={tab.id} accessibilityRole="tab" accessibilityState={{ selected: on }} accessibilityLabel={label(tab.label)} onPress={() => onPress(i)} style={styles.tab}>
            <View style={[styles.ico, on && styles.icoOn]}>
              <Icon name={tab.icon} slug={tab.slug} size={22} color={color} strokeWidth={on ? 2.2 : 1.8} />
              {tab.badge ? <Badge text={String(tab.badge)} tone="danger" style={styles.badge} /> : null}
            </View>
            <Text numberOfLines={1} style={[styles.label, { color }, on && styles.labelOn]}>{label(tab.label)}</Text>
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  bar: { flexDirection: 'row', backgroundColor: theme.colors.sidebar, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: theme.colors.border, paddingTop: 6, shadowColor: '#000', shadowOpacity: 0.04, shadowRadius: 12, shadowOffset: { width: 0, height: -4 } },
  tab: { flex: 1, alignItems: 'center', gap: 2, minHeight: 52, justifyContent: 'center' },
  ico: { width: 56, height: 30, borderRadius: 15, alignItems: 'center', justifyContent: 'center' },
  icoOn: { backgroundColor: theme.colors.primarySoft },
  label: { fontFamily: theme.font.family, fontSize: 11, fontWeight: '600' },
  labelOn: { fontWeight: '800' },
  badge: { position: 'absolute', top: -4, right: 6 },
}));
