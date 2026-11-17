// Choice field: a segmented control for a few short options, a sheet list for more (site languages,
// countries…). Used by manifest forms and action prompts.
import { useState } from 'react';
import { Pressable, ScrollView, View } from 'react-native';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { Icon } from './Icon';
import { Segmented, Text } from './primitives';
import { Sheet } from './Sheet';

export interface SelectOption {
  value: string;
  label: string;
}

/** Segmented fits up to 4 options whose labels are short; anything else opens a list. */
const fitsSegmented = (options: SelectOption[]) => options.length <= 4 && options.reduce((n, o) => n + o.label.length, 0) <= 36;

export function Select({ label, options, value, onChange, error, help, disabled, testID }: { label: string; options: SelectOption[]; value: string; onChange: (v: string) => void; error?: string; help?: string; disabled?: boolean; testID?: string }) {
  const { theme } = useUnistyles();
  const [open, setOpen] = useState(false);
  const current = options.find((o) => o.value === value);
  return (
    <View style={styles.gap}>
      <Text variant="label">{label}</Text>
      {fitsSegmented(options) ? (
        <Segmented options={options} value={value} onChange={(v) => !disabled && onChange(v)} />
      ) : (
        <Pressable testID={testID} accessibilityRole="button" accessibilityLabel={label} accessibilityValue={{ text: current?.label ?? '' }} disabled={disabled} onPress={() => setOpen(true)} style={[styles.box, !!error && styles.boxError]}>
          <Text style={styles.flex} numberOfLines={1} tone={current ? undefined : 'muted'}>{current?.label ?? '—'}</Text>
          <Icon name="chevron-down" size={18} color={theme.colors.textMuted} />
        </Pressable>
      )}
      {error ? <Text variant="small" tone="danger">{error}</Text> : help ? <Text variant="small" tone="muted">{help}</Text> : null}
      <Sheet visible={open} onDismiss={() => setOpen(false)} title={label} snapPoints={[{ fraction: 0.7 }]}>
        <ScrollView keyboardShouldPersistTaps="handled">
          {options.map((o) => {
            const active = o.value === value;
            return (
              <Pressable key={o.value} accessibilityRole="button" accessibilityState={{ selected: active }} onPress={() => { onChange(o.value); setOpen(false); }} style={({ pressed }) => [styles.row, pressed && styles.pressed]}>
                <Text style={styles.flex} numberOfLines={2}>{o.label}</Text>
                {active ? <Icon name="check" size={18} color={theme.colors.primary} /> : null}
              </Pressable>
            );
          })}
        </ScrollView>
      </Sheet>
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  gap: { gap: 6 },
  flex: { flex: 1 },
  box: { minHeight: 44, borderRadius: theme.radius.md, borderWidth: 1, borderColor: theme.colors.border, backgroundColor: theme.colors.surface, paddingHorizontal: theme.space[4], flexDirection: 'row', alignItems: 'center', gap: 8 },
  boxError: { borderColor: theme.colors.danger },
  row: { minHeight: 48, flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 4, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: theme.colors.border },
  pressed: { opacity: 0.6 },
}));
