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
            <View>
              <Icon name={tab.icon} slug={tab.slug} size={24} color={color} strokeWidth={on ? 2.2 : 1.8} />
              {tab.badge ? <Badge text={String(tab.badge)} tone="danger" style={styles.badge} /> : null}
            </View>
            <Text numberOfLines={1} style={[styles.label, { color }]}>{label(tab.label)}</Text>
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  bar: { flexDirection: 'row', backgroundColor: theme.colors.surface, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: theme.colors.border, paddingTop: 6 },
  tab: { flex: 1, alignItems: 'center', gap: 3, minHeight: 48, justifyContent: 'center' },
  label: { fontSize: 11, fontWeight: '600' },
  badge: { position: 'absolute', top: -6, right: -14 },
}));
