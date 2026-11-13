// Native sheet (SwiftUI on iOS, Material bottom sheet on Android, dialog on web).
// The scrim is part of the system presentation, so it appears with the sheet.
// By default the sheet fits its content (no empty band under a short menu); pass `snapPoints`
// for tall content that should drag between half and full height.
// The title and the close button share one header row, so short sheets have no empty top band.
// The system sheet already keeps clear of the home indicator / navigation bar: content adds no bottom inset.
// Content is React Native: it sits in RNHostView so the native sheet sizes it and passes touches
// to it. Without the host, iOS gave the views no width (text cut short) and swallowed taps.
import type { ReactNode } from 'react';
import { Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { BottomSheet, RNHostView, type SnapPoint } from '@expo/ui';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { Icon } from './Icon';
import { Text } from './primitives';

export function Sheet({ visible, onDismiss, title, snapPoints, children }: { visible: boolean; onDismiss: () => void; title?: string; snapPoints?: SnapPoint[]; children: ReactNode }) {
  const { theme } = useUnistyles();
  const { t } = useTranslation();
  return (
    <BottomSheet
      isPresented={visible}
      onDismiss={onDismiss}
      showDragIndicator
      snapPoints={snapPoints}
      containerColor={theme.colors.surface}
      scrimColor={theme.colors.overlay}
      // Top clears the drag indicator (iOS draws it over the content).
      contentPadding={{ top: 20, left: 16, right: 16, bottom: 8 }}
    >
      <RNHostView matchContents={{ vertical: true }}>
        <View collapsable={false} style={styles.body}>
          <View style={styles.head}>
            {title ? <Text variant="heading" numberOfLines={2} style={styles.title}>{title}</Text> : <View style={styles.title} />}
            <Pressable onPress={onDismiss} accessibilityRole="button" accessibilityLabel={t('common.close')} testID="sheet-close" hitSlop={8} style={[styles.close, { backgroundColor: theme.colors.surface2 }]}>
              <Icon name="x" size={18} color={theme.colors.text} />
            </Pressable>
          </View>
          {children}
        </View>
      </RNHostView>
    </BottomSheet>
  );
}

const styles = StyleSheet.create({
  body: { width: '100%', gap: 8 },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, minHeight: 36 },
  title: { flex: 1, minWidth: 0 },
  close: { width: 32, height: 32, borderRadius: 16, alignItems: 'center', justifyContent: 'center' },
});
