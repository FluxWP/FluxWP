// Each tab slot has its own stack; manifest, native, web and menu routes are shared by all slots.
// The slot number comes from this layout's tab route ("(t3)"): useSegments() is global and would
// make every slot render the focused tab's target.
import { Stack, useRoute } from 'expo-router';
import { useUnistyles } from 'react-native-unistyles';
import { SlotContext } from '@/shell/slot';
import { TopBar } from '@/shell/TopBar';

export default function SlotStack() {
  const { theme } = useUnistyles();
  const slot = Number(/^\(t(\d)\)$/.exec(useRoute().name)?.[1] ?? 0);
  return (
    <SlotContext.Provider value={slot}>
      <Stack screenOptions={{ header: (p) => <TopBar {...(p as unknown as Parameters<typeof TopBar>[0])} />, contentStyle: { backgroundColor: theme.colors.bg } }} />
    </SlotContext.Provider>
  );
}
