// Each tab slot has its own stack; manifest, native, web and menu routes are shared by all slots.
// The slot number comes from this layout's tab route ("(t3)"): useSegments() is global and would
// make every slot render the focused tab's target.
// iOS: the top bar is translucent and floats over the screen, and the platform tab bar floats at the
// bottom; screens scroll under both and read their insets from useChrome() (shell/chrome.tsx).
import { Platform } from 'react-native';
import { Stack, useRoute } from 'expo-router';
import { useUnistyles } from 'react-native-unistyles';
import { ChromeProvider } from '@/shell/chrome';
import { SlotContext } from '@/shell/slot';
import { TopBar } from '@/shell/TopBar';

export default function SlotStack() {
  const { theme } = useUnistyles();
  const slot = Number(/^\(t(\d)\)$/.exec(useRoute().name)?.[1] ?? 0);
  return (
    <SlotContext.Provider value={slot}>
      <ChromeProvider>
        <Stack
          screenOptions={{
            header: (p) => <TopBar {...(p as unknown as Parameters<typeof TopBar>[0])} />,
            headerTransparent: Platform.OS === 'ios',
            contentStyle: { backgroundColor: theme.colors.bg },
          }}
        />
      </ChromeProvider>
    </SlotContext.Provider>
  );
}
