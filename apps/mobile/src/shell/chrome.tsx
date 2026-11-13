// iOS draws the top bar (translucent blur) and the platform tab bar over the screen, so content can scroll
// under both. Screens read these insets to keep their first and last rows (and fixed bars) clear of them.
// Android and web lay both bars out beside the content: every inset is 0 there.
import { createContext, useContext, type ReactNode } from 'react';
import { Platform, View, type ScrollViewProps, type StyleProp, type ViewStyle } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

/** Height of TopBar below the status bar: 4 top padding + 52 row + 6 bottom padding. */
export const TOPBAR_HEIGHT = 62;

const ChromeContext = createContext(false);

/** Wraps a tab stack: screens inside it sit under the overlaid top bar and tab bar on iOS. */
export function ChromeProvider({ children }: { children: ReactNode }) {
  return <ChromeContext.Provider value={Platform.OS === 'ios'}>{children}</ChromeContext.Provider>;
}

export function useChrome(): { top: number; bottom: number } {
  const overlaid = useContext(ChromeContext);
  const insets = useSafeAreaInsets();
  return overlaid ? { top: insets.top + TOPBAR_HEIGHT, bottom: insets.bottom } : { top: 0, bottom: 0 };
}

/** Props for a full-screen ScrollView: native content insets, so pull-to-refresh and the scroll bar sit right. */
export function useScrollChrome(): Pick<ScrollViewProps, 'contentInset' | 'contentOffset' | 'scrollIndicatorInsets' | 'automaticallyAdjustContentInsets'> {
  const { top, bottom } = useChrome();
  if (!top && !bottom) return {};
  return { contentInset: { top, bottom }, contentOffset: { x: 0, y: -top }, scrollIndicatorInsets: { top, bottom }, automaticallyAdjustContentInsets: false };
}

/** Screen root for fixed layouts (composer, toolbars, lists below a header): padded clear of both bars. */
export function ChromeFrame({ children, style, testID, top = true, bottom = true }: { children: ReactNode; style?: StyleProp<ViewStyle>; testID?: string; top?: boolean; bottom?: boolean }) {
  const c = useChrome();
  return <View testID={testID} style={[style, { paddingTop: top ? c.top : 0, paddingBottom: bottom ? c.bottom : 0 }]}>{children}</View>;
}
