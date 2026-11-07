// Native sheet (SwiftUI on iOS, Material bottom sheet on Android, dialog on web).
// The scrim is part of the system presentation, so it appears with the sheet.
// The grabber drags between half and full height.
// Content is React Native: it sits in RNHostView so the native sheet sizes it and passes touches
// to it. Without the host, iOS gave the views no width (text cut short) and swallowed taps.
import type { ReactNode } from 'react';
import { Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { Icon } from './Icon';
import { BottomSheet, RNHostView } from '@expo/ui';
import { useUnistyles } from 'react-native-unistyles';

export function Sheet({ visible, onDismiss, children }: { visible: boolean; onDismiss: () => void; children: ReactNode }) {
  const { theme } = useUnistyles();
  const { t } = useTranslation();
  return (
    <BottomSheet
      isPresented={visible}
      onDismiss={onDismiss}
      showDragIndicator
      snapPoints={['half', 'full']}
      containerColor={theme.colors.surface}
      scrimColor={theme.colors.overlay}
      // Top clears the drag indicator (iOS draws it over the content).
      contentPadding={{ top: 28, left: 12, right: 12, bottom: 12 }}
    >
      <RNHostView matchContents={{ vertical: true }}>
        <View collapsable={false} style={{ width: '100%', gap: 12 }}>
          <Pressable onPress={onDismiss} accessibilityRole="button" accessibilityLabel={t('common.close')} testID="sheet-close" style={{ alignSelf: 'flex-end', width: 44, height: 44, alignItems: 'center', justifyContent: 'center', borderRadius: 22, backgroundColor: theme.colors.surface2 }}>
            <Icon name="x" size={20} color={theme.colors.text}/>
          </Pressable>
          {children}
        </View>
      </RNHostView>
    </BottomSheet>
  );
}
