// Native sheet (SwiftUI on iOS, Material bottom sheet on Android, dialog on web).
// The scrim is part of the system presentation, so it appears with the sheet.
// By default the sheet fits its content (no empty band under a short menu); pass `snapPoints`
// for tall content that should drag between half and full height.
// The title and the close button share one header row, so short sheets have no empty top band.
// The system sheet already keeps clear of the home indicator / navigation bar: content adds no bottom inset.
// Content is React Native: it sits in RNHostView so the native sheet sizes it and passes touches
// to it. Without the host, iOS gave the views no width (text cut short) and swallowed taps.
// iPad: a fitted SwiftUI sheet floats mid-screen with a drag handle, half bottom sheet, half dialog.
// There the same content opens as a centred dialog card instead.
import type { ReactNode } from 'react';
import { KeyboardAvoidingView, Modal, Platform, Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { BottomSheet, RNHostView, type SnapPoint } from '@expo/ui';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { Icon } from './Icon';
import { Text } from './primitives';

export function Sheet({ visible, onDismiss, title, snapPoints, children }: { visible: boolean; onDismiss: () => void; title?: string; snapPoints?: SnapPoint[]; children: ReactNode }) {
  const { theme } = useUnistyles();
  const { t } = useTranslation();
  const head = (
    <View style={styles.head}>
      {title ? <Text variant="heading" numberOfLines={2} style={styles.title}>{title}</Text> : <View style={styles.title} />}
      <Pressable onPress={onDismiss} accessibilityRole="button" accessibilityLabel={t('common.close')} testID="sheet-close" hitSlop={8} style={[styles.close, { backgroundColor: theme.colors.surface2 }]}>
        <Icon name="x" size={18} color={theme.colors.text} />
      </Pressable>
    </View>
  );
  if (IS_PAD) {
    return (
      <Modal visible={visible} transparent animationType="fade" onRequestClose={onDismiss} supportedOrientations={['portrait', 'landscape']}>
        <KeyboardAvoidingView behavior="padding" style={[styles.backdrop, { backgroundColor: theme.colors.overlay }]}>
          <Pressable style={styles.scrim} onPress={onDismiss} accessibilityRole="button" accessibilityLabel={t('common.close')} />
          <View style={[styles.card, { backgroundColor: theme.colors.surface }]}>
            <View style={[styles.body, styles.padBody]}>
              {head}
              {children}
            </View>
          </View>
        </KeyboardAvoidingView>
      </Modal>
    );
  }
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
          {head}
          {children}
        </View>
      </RNHostView>
    </BottomSheet>
  );
}

const IS_PAD = Platform.OS === 'ios' && Platform.isPad;

const styles = StyleSheet.create({
  backdrop: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24 },
  scrim: { position: 'absolute', top: 0, right: 0, bottom: 0, left: 0 },
  card: { width: '100%', maxWidth: 520, maxHeight: '85%', borderRadius: 24, paddingTop: 16, paddingHorizontal: 16, paddingBottom: 16, shadowColor: '#000', shadowOpacity: 0.18, shadowRadius: 24, shadowOffset: { width: 0, height: 8 } },
  body: { width: '100%', gap: 8 },
  // Long lists scroll inside the card instead of running past its max height.
  padBody: { flexShrink: 1 },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, minHeight: 36 },
  title: { flex: 1, minWidth: 0 },
  close: { width: 32, height: 32, borderRadius: 16, alignItems: 'center', justifyContent: 'center' },
});
