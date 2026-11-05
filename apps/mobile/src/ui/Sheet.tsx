// Native sheet (SwiftUI on iOS, Material bottom sheet on Android, dialog on web).
// The scrim is part of the system presentation, so it appears with the sheet.
// The grabber drags between half and full height.
import type { ReactNode } from 'react';
import { View } from 'react-native';
import { BottomSheet } from '@expo/ui';
import { useUnistyles } from 'react-native-unistyles';

export function Sheet({ visible, onDismiss, children }: { visible: boolean; onDismiss: () => void; children: ReactNode }) {
  const { theme } = useUnistyles();
  return (
    <BottomSheet
      isPresented={visible}
      onDismiss={onDismiss}
      showDragIndicator
      snapPoints={['half', 'full']}
      containerColor={theme.colors.surface}
      scrimColor={theme.colors.overlay}
      contentPadding={{ top: 4, left: 8, right: 8, bottom: 12 }}
    >
      <View style={{ gap: 12 }}>{children}</View>
    </BottomSheet>
  );
}
