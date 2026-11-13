// The account screen as a card: while the side panel opens (drawerType "back"), the screen slides right,
// its corners round and it lifts with a shadow, so the panel reads as sitting behind it.
import type { ReactNode } from 'react';
import { Platform } from 'react-native';
import Animated, { Extrapolation, interpolate, useAnimatedStyle } from 'react-native-reanimated';
import { useDrawerProgress } from 'expo-router/drawer';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';

// iPhone display corners are ~47-55 pt; Android devices vary, a Material "extra large" shape fits.
const RADIUS = Platform.OS === 'ios' ? 44 : 28;
const HAIRLINE = StyleSheet.hairlineWidth;

export function DrawerCard({ children }: { children: ReactNode }) {
  const { theme } = useUnistyles();
  const progress = useDrawerProgress();
  const shadow = theme.mode === 'dark' ? 0.5 : 0.18;
  // A light shade on the part of the screen still visible beside the open panel.
  const dim = theme.mode === 'dark' ? 0.35 : 0.12;
  const outer = useAnimatedStyle(() => ({
    shadowOpacity: interpolate(progress.value, [0, 0.2], [0, shadow], Extrapolation.CLAMP),
  }));
  const inner = useAnimatedStyle(() => {
    const r = interpolate(progress.value, [0, 0.12], [0, RADIUS], Extrapolation.CLAMP);
    return {
      borderRadius: r,
      borderWidth: progress.value > 0.01 ? HAIRLINE : 0,
    };
  });
  const shade = useAnimatedStyle(() => ({
    opacity: interpolate(progress.value, [0, 1], [0, dim], Extrapolation.CLAMP),
  }));
  return (
    <Animated.View style={[styles.outer, outer]}>
      <Animated.View style={[styles.inner, inner]}>
        {children}
        <Animated.View pointerEvents="none" style={[styles.shade, shade]} />
      </Animated.View>
    </Animated.View>
  );
}

const styles = StyleSheet.create((theme) => ({
  outer: { flex: 1, shadowColor: '#000', shadowRadius: 24, shadowOffset: { width: -6, height: 0 } },
  inner: { flex: 1, overflow: 'hidden', backgroundColor: theme.colors.bg, borderColor: theme.mode === 'dark' ? 'rgba(255,255,255,0.10)' : 'rgba(0,0,0,0.08)' },
  shade: { position: 'absolute', top: 0, right: 0, bottom: 0, left: 0, backgroundColor: '#000' },
}));
