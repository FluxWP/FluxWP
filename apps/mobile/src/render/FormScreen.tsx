// Manifest `form` screen: react-hook-form fields from useFormController. Field types without a native
// control yet (image, file, user, post, repeater…) are shown read-only; their full controls come in a5/c1.
import { useLayoutEffect } from 'react';
import { ScrollView, Switch, View } from 'react-native';
import { Controller } from 'react-hook-form';
import { useNavigation } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { useFormController } from '@fluxwp/runtime';
import type { FormScreen as FormDef, Manifest } from '@fluxwp/manifest';
import { KeyboardView } from '@/ui/KeyboardView';
import { RichEditor } from '@/ui/RichEditor';
import { Button, Field, Skeleton, Text } from '@/ui/primitives';
import { Select } from '@/ui/Select';
import { EntityField, MediaField } from './fields';
import type { RelationValue } from '@fluxwp/runtime';
import { useScrollChrome } from '@/shell/chrome';

const KEYBOARD: Record<string, 'default' | 'email-address' | 'phone-pad' | 'url' | 'decimal-pad'> = { email: 'email-address', phone: 'phone-pad', url: 'url', number: 'decimal-pad', money: 'decimal-pad' };

export function FormScreen({ manifest, screen, params }: { manifest: Manifest; screen: FormDef; params: Record<string, string> }) {
  const scrollChrome = useScrollChrome();
  const c = useFormController(manifest, screen, params);
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const nav = useNavigation();
  useLayoutEffect(() => nav.setOptions({ title: c.title }), [nav, c.title]);
  if (c.loading) return <Skeleton rows={4} />;
  const msg = (m?: string) => (m ? (m.startsWith('form.') ? t(m) : m) : undefined);

  return (
    <KeyboardView style={styles.screen}>
      <ScrollView {...scrollChrome} contentContainerStyle={styles.wrap} keyboardShouldPersistTaps="handled">
        {c.fields.map((f) => (
          <Controller
            key={f.name}
            control={c.form.control}
            name={f.name}
            rules={c.rules(f)}
            render={({ field, fieldState }) => {
              const err = msg(fieldState.error?.message);
              const relation = field.value && typeof field.value === 'object' && 'id' in (field.value as object) ? (field.value as RelationValue) : null;
              if (f.type === 'markdown') return <RichEditor value={field.value == null ? '' : String(field.value)} onChange={field.onChange} />;
              if (f.type === 'image' || f.type === 'file') return <MediaField field={f} value={relation} onChange={field.onChange} />;
              if (f.type === 'user' || f.type === 'term' || f.type === 'post') return <EntityField field={f} value={relation} onChange={field.onChange} />;
              if (f.type === 'switch')
                return (
                  <View style={styles.switchRow}>
                    <Text style={styles.flex}>{f.label}</Text>
                    <Switch value={!!field.value} onValueChange={field.onChange} disabled={f.readonly} trackColor={{ true: theme.colors.primary }} accessibilityLabel={f.label} />
                  </View>
                );
              if ((f.type === 'segmented' || f.type === 'radio' || f.type === 'select') && Array.isArray(f.options))
                return <Select label={f.label + (f.required ? ' *' : '')} options={f.options} value={String(field.value ?? '')} onChange={field.onChange} error={err} help={f.help} disabled={f.readonly} />;
              const editable = !f.readonly && ['text', 'textarea', 'email', 'phone', 'url', 'number', 'money', 'secret', 'color', 'date', 'datetime', 'time', 'tags'].includes(f.type);
              // Tags are edited as one comma-separated line; the runtime splits them on submit.
              const shown = Array.isArray(field.value) ? field.value.join(', ') : field.value === undefined || field.value === null ? '' : String(field.value);
              return (
                <Field
                  label={f.label + (f.required ? ' *' : '')}
                  value={shown}
                  onChangeText={field.onChange}
                  onBlur={field.onBlur}
                  editable={editable}
                  placeholder={f.placeholder}
                  help={f.help}
                  error={err}
                  multiline={f.type === 'textarea'}
                  secureTextEntry={f.type === 'secret'}
                  keyboardType={KEYBOARD[f.type] ?? 'default'}
                  autoCapitalize={f.type === 'email' || f.type === 'url' || f.type === 'tags' ? 'none' : 'sentences'}
                  maxLength={f.maxLength}
                />
              );
            }}
          />
        ))}
        {c.form.formState.errors.root?.message ? <Text tone="danger">{msg(c.form.formState.errors.root.message)}</Text> : null}
        <Button label={c.submitting ? t('form.saving') : t('form.submit')} loading={c.submitting} onPress={() => void c.submit()} full />
      </ScrollView>
    </KeyboardView>
  );
}

const styles = StyleSheet.create((theme) => ({
  screen: { flex: 1, backgroundColor: theme.colors.bg },
  wrap: { padding: theme.space[4], gap: theme.space[4], paddingBottom: 80 },
  gap: { gap: 6 },
  flex: { flex: 1 },
  switchRow: { flexDirection: 'row', alignItems: 'center', minHeight: 48 },
}));
