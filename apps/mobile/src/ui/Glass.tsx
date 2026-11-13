// Floating "glass" controls. iOS 26+: real Liquid Glass (expo-glass-effect). Older iOS and web: a
// translucent surface with a hairline edge. Android: a Material tonal surface with elevation.
import type { ReactNode } from 'react';
import { Platform, Pressable, View, type StyleProp, type ViewStyle } from 'react-native';
import { GlassView, isLiquidGlassAvailable } from 'expo-glass-effect';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { alpha } from '@fluxwp/tokens';
import { Icon } from './Icon';
import { Badge, Text } from './primitives';

const liquid = Platform.OS === 'ios' && isLiquidGlassAvailable();
// Web preview: frosted look where the browser supports it.
const frosted = { backdropFilter: 'blur(18px) saturate(160%)' };

export function Glass({ children, style, interactive }: { children?: ReactNode; style?: StyleProp<ViewStyle>; interactive?: boolean }) {
  const { theme } = useUnistyles();
  if (liquid) {
    return <GlassView glassEffectStyle="regular" isInteractive={interactive} colorScheme={theme.mode} style={style}>{children}</GlassView>;
  }
  return <View style={[styles.fallback, Platform.OS === 'web' ? (frosted as ViewStyle) : null, style]}>{children}</View>;
}

/** Round floating button (menu, back, search, top bar actions). */
export function GlassButton({ icon, label, onPress, badge, testID, size = 44, color, children }: { icon?: string; label: string; onPress?: () => void; badge?: number | string; testID?: string; size?: number; color?: string; children?: ReactNode }) {
  const { theme } = useUnistyles();
  return (
    <Pressable testID={testID} accessibilityRole="button" accessibilityLabel={label} hitSlop={6} onPress={onPress} style={({ pressed }) => [pressed && !liquid && styles.pressed]}>
      <Glass interactive style={[styles.round, { width: size, height: size, borderRadius: size / 2 }]}>
        {children ?? <Icon name={icon} size={Math.round(size * 0.48)} color={color ?? theme.colors.text} strokeWidth={1.9} />}
      </Glass>
      {badge ? <Badge text={String(badge)} tone="danger" style={styles.badge} /> : null}
    </Pressable>
  );
}

/** Pill with an optional leading element and a label (site chip, drawer footer actions). */
export function GlassPill({ label, icon, leading, onPress, testID, accessibilityLabel, solid, maxWidth }: { label?: string; icon?: string; leading?: ReactNode; onPress?: () => void; testID?: string; accessibilityLabel?: string; solid?: boolean; maxWidth?: number }) {
  const { theme } = useUnistyles();
  const color = solid ? theme.colors.onPrimary : theme.colors.text;
  const body = (
    <>
      {leading ?? (icon ? <Icon name={icon} size={20} color={color} strokeWidth={2} /> : null)}
      {label ? <Text numberOfLines={1} style={[styles.pillLabel, { color }]}>{label}</Text> : null}
    </>
  );
  return (
    <Pressable testID={testID} accessibilityRole="button" accessibilityLabel={accessibilityLabel ?? label} hitSlop={6} onPress={onPress} style={({ pressed }) => [pressed && (solid || !liquid) && styles.pressed, maxWidth ? { maxWidth } : null]}>
      {solid ? <View style={[styles.pill, styles.solid]}>{body}</View> : <Glass interactive style={styles.pill}>{body}</Glass>}
    </Pressable>
  );
}

const styles = StyleSheet.create((theme) => ({
  fallback: Platform.OS === 'android'
    ? { backgroundColor: theme.colors.surface3, elevation: 3 }
    : {
        backgroundColor: alpha(theme.colors.surface, theme.mode === 'dark' ? 0.78 : 0.86),
        borderWidth: StyleSheet.hairlineWidth,
        borderColor: alpha(theme.mode === 'dark' ? '#FFFFFF' : '#000000', theme.mode === 'dark' ? 0.14 : 0.08),
        shadowColor: '#000',
        shadowOpacity: theme.mode === 'dark' ? 0.35 : 0.1,
        shadowRadius: 14,
        shadowOffset: { width: 0, height: 4 },
      },
  round: { alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
  pill: { height: 44, borderRadius: 22, paddingHorizontal: 14, flexDirection: 'row', alignItems: 'center', gap: 8, overflow: 'hidden' },
  solid: { backgroundColor: theme.colors.primary, shadowColor: '#000', shadowOpacity: 0.18, shadowRadius: 12, shadowOffset: { width: 0, height: 4 }, elevation: 4, paddingHorizontal: 18 },
  pillLabel: { fontSize: 16, fontWeight: '600', flexShrink: 1 },
  pressed: { opacity: 0.7, transform: [{ scale: 0.97 }] },
  badge: { position: 'absolute', top: -2, right: -2 },
}));
