// Root: platform + brand + i18n, account list, navigation theme from the active Unistyles theme,
// global overlays (toast/confirm/prompt/menu) and the account switcher.
import '@/platform';
import '@/i18n';
import { useEffect } from 'react';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { DarkTheme, DefaultTheme, Stack, ThemeProvider } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useStore } from 'zustand';
import { useUnistyles } from 'react-native-unistyles';
import { accountsStore } from '@fluxwp/core';
import { AccountSwitcherHost } from '@/shell/AccountSwitcher';
import { useAppFonts } from '@/theme/fonts';
import { OverlayHost } from '@/ui/overlays';
import { PushRouter } from '@/push';

void SplashScreen.preventAutoHideAsync();

export default function RootLayout() {
  useAppFonts();
  const ready = useStore(accountsStore, (s) => s.ready);
  const { theme } = useUnistyles();
  useEffect(() => void accountsStore.getState().load(), []);
  useEffect(() => {
    if (ready) void SplashScreen.hideAsync();
  }, [ready]);
  if (!ready) return null;

  const base = theme.mode === 'dark' ? DarkTheme : DefaultTheme;
  const navTheme = { ...base, colors: { ...base.colors, primary: theme.colors.primary, background: theme.colors.bg, card: theme.colors.surface, text: theme.colors.text, border: theme.colors.border, notification: theme.colors.danger } };
  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <SafeAreaProvider>
        <ThemeProvider value={navTheme}>
          <StatusBar style={theme.mode === 'dark' ? 'light' : 'dark'} />
          <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: theme.colors.bg } }}>
            <Stack.Screen name="(auth)/add-site" options={{ presentation: 'modal' }} />
          </Stack>
          <OverlayHost />
          <AccountSwitcherHost />
          <PushRouter />
        </ThemeProvider>
      </SafeAreaProvider>
    </GestureHandlerRootView>
  );
}
