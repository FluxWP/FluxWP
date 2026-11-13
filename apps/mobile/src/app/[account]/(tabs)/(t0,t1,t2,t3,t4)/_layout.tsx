// Each tab slot has its own stack; manifest, native, web and menu routes are shared by all slots.
// The slot number comes from this layout's tab route ("(t3)"): useSegments() is global and would
// make every slot render the focused tab's target.
import { useEffect, useState } from 'react';
import { Keyboard, Platform } from 'react-native';
import { Stack, useRoute } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useUnistyles } from 'react-native-unistyles';
import { SlotContext } from '@/shell/slot';
import { TopBar } from '@/shell/TopBar';

export default function SlotStack() {
  const { theme } = useUnistyles();
  const slot = Number(/^\(t(\d)\)$/.exec(useRoute().name)?.[1] ?? 0);
  const bottom = useTabBarClearance();
  return (
    <SlotContext.Provider value={slot}>
      <Stack screenOptions={{ header: (p) => <TopBar {...(p as unknown as Parameters<typeof TopBar>[0])} />, contentStyle: { backgroundColor: theme.colors.bg, paddingBottom: bottom } }} />
    </SlotContext.Provider>
  );
}

/**
 * iOS draws the tab bar over the tab's view; its height is part of this tab's bottom safe area. Screens
 * keep their own bottom bars (composer, form buttons) above it, except while the keyboard is up, when
 * KeyboardAvoidingView takes over. Android and web lay the tab bar out beside the content already.
 */
function useTabBarClearance(): number {
  const insets = useSafeAreaInsets();
  const [keyboard, setKeyboard] = useState(false);
  useEffect(() => {
    if (Platform.OS !== 'ios') return;
    const show = Keyboard.addListener('keyboardWillShow', () => setKeyboard(true));
    const hide = Keyboard.addListener('keyboardWillHide', () => setKeyboard(false));
    return () => (show.remove(), hide.remove());
  }, []);
  return Platform.OS === 'ios' && !keyboard ? insets.bottom : 0;
}
