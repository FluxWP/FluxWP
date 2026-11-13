// Keeps a screen's bottom (composer, form buttons) above the software keyboard on iOS and Android.
// The view measures where its own bottom edge sits on screen and pads by exactly the part the keyboard
// covers, so it works under the platform tab bar, inside nested headers and with Android edge-to-edge,
// where KeyboardAvoidingView (frame relative to the parent) and adjustResize both miss.
import { useCallback, useEffect, useRef, useState, type ComponentRef, type ReactNode } from 'react';
import { useChrome } from '@/shell/chrome';
import { Keyboard, LayoutAnimation, Platform, View, type KeyboardEvent, type StyleProp, type ViewStyle } from 'react-native';

/**
 * `chrome`: the view is a screen root under the iOS floating top bar and tab bar (shell/chrome.tsx), so it
 * also pads clear of them; the keyboard then replaces the tab bar's share of the bottom.
 */
export function KeyboardView({ children, style, testID, chrome }: { children: ReactNode; style?: StyleProp<ViewStyle>; testID?: string; chrome?: boolean }) {
  const bars = useChrome();
  const top = chrome ? bars.top : 0;
  const bottom = chrome ? bars.bottom : 0;
  const ref = useRef<ComponentRef<typeof View>>(null);
  const keyboardTop = useRef<number | null>(null);
  const [pad, setPad] = useState(0);

  const update = useCallback((e?: KeyboardEvent) => {
    const node = ref.current;
    if (!node) return;
    node.measureInWindow((_x: number, y: number, _w: number, h: number) => {
      const top = keyboardTop.current;
      const next = top === null ? 0 : Math.max(0, Math.round(y + h - top));
      setPad((prev) => {
        if (prev === next) return prev;
        if (Platform.OS === 'ios' && e?.duration) {
          LayoutAnimation.configureNext({ duration: e.duration, update: { type: LayoutAnimation.Types.keyboard } });
        }
        return next;
      });
    });
  }, []);

  useEffect(() => {
    if (Platform.OS === 'web') return;
    const ios = Platform.OS === 'ios';
    const onShow = (e: KeyboardEvent) => {
      keyboardTop.current = e.endCoordinates.screenY;
      update(e);
    };
    const onHide = (e: KeyboardEvent) => {
      keyboardTop.current = null;
      update(e);
    };
    const subs = [
      Keyboard.addListener(ios ? 'keyboardWillShow' : 'keyboardDidShow', onShow),
      Keyboard.addListener(ios ? 'keyboardWillHide' : 'keyboardDidHide', onHide),
      ...(ios ? [Keyboard.addListener('keyboardWillChangeFrame', onShow)] : []),
    ];
    return () => subs.forEach((s) => s.remove());
  }, [update]);

  return (
    <View ref={ref} testID={testID} style={[style, top || pad || bottom ? { paddingTop: top, paddingBottom: Math.max(pad, bottom) } : null]} onLayout={() => update()}>
      {children}
    </View>
  );
}
