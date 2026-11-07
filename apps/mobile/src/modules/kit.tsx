import { useLayoutEffect, type ReactNode } from 'react';
import { Pressable, TextInput, View } from 'react-native';
import { useNavigation } from 'expo-router';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { Text } from '@/ui/primitives';

export function useScreenTitle(title: string) {
  const navigation = useNavigation();
  useLayoutEffect(() => {
    navigation.setOptions({ title });
  }, [navigation, title]);
}

export function SearchField({ value, onChange, placeholder, testID }: { value: string; onChange: (v: string) => void; placeholder: string; testID?: string }) {
  const { theme } = useUnistyles();
  return (
    <TextInput
      testID={testID}
      value={value}
      onChangeText={onChange}
      placeholder={placeholder}
      placeholderTextColor={theme.colors.textMuted}
      style={styles.search}
      accessibilityLabel={placeholder}
      autoCorrect={false}
      clearButtonMode="while-editing"
    />
  );
}

export function Chips({ children }: { children: ReactNode }) {
  return <View style={styles.chips}>{children}</View>;
}

export function Chip({ label, active, onPress, color }: { label: string; active?: boolean; onPress: () => void; color?: string }) {
  return (
    <Pressable accessibilityRole="button" accessibilityState={{ selected: !!active }} onPress={onPress} style={[styles.chip, active && styles.chipOn, color ? { borderColor: color } : null]}>
      <Text variant="small" numberOfLines={1} style={active ? styles.chipTextOn : undefined}>{label}</Text>
    </Pressable>
  );
}

const styles = StyleSheet.create((theme) => ({
  search: {
    marginHorizontal: theme.space[4],
    marginTop: theme.space[3],
    backgroundColor: theme.colors.surface,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    paddingHorizontal: theme.space[3],
    minHeight: 44,
    color: theme.colors.text,
    fontSize: 16,
    fontFamily: theme.font.family,
  },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, paddingHorizontal: theme.space[4], paddingVertical: theme.space[2] },
  chip: { minHeight: 36, paddingHorizontal: 12, borderRadius: theme.radius.full, borderWidth: StyleSheet.hairlineWidth, borderColor: theme.colors.border, alignItems: 'center', justifyContent: 'center', backgroundColor: theme.colors.surface },
  chipOn: { backgroundColor: theme.colors.primary, borderColor: theme.colors.primary },
  chipTextOn: { color: theme.colors.onPrimary },
}));
