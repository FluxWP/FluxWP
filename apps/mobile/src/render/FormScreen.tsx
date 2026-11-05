// Manifest `form` screen: react-hook-form fields from useFormController. Field types without a native
// control yet (image, file, user, post, repeater…) are shown read-only; their full controls come in a5/c1.
import { useLayoutEffect } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, Switch, View } from 'react-native';
import { Controller } from 'react-hook-form';
import { useNavigation } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { useFormController } from '@fluxwp/runtime';
import type { FormScreen as FormDef, Manifest } from '@fluxwp/manifest';
import { Button, Field, Segmented, Skeleton, Text } from '@/ui/primitives';

const KEYBOARD: Record<string, 'default' | 'email-address' | 'phone-pad' | 'url' | 'decimal-pad'> = { email: 'email-address', phone: 'phone-pad', url: 'url', number: 'decimal-pad', money: 'decimal-pad' };

export function FormScreen({ manifest, screen, params }: { manifest: Manifest; screen: FormDef; params: Record<string, string> }) {
  const c = useFormController(manifest, screen, params);
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const nav = useNavigation();
  useLayoutEffect(() => nav.setOptions({ title: c.title }), [nav, c.title]);
  if (c.loading) return <Skeleton rows={4} />;
  const msg = (m?: string) => (m ? (m.startsWith('form.') ? t(m) : m) : undefined);

  return (
    <KeyboardAvoidingView style={styles.screen} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={styles.wrap} keyboardShouldPersistTaps="handled">
        {c.fields.map((f) => (
          <Controller
            key={f.name}
            control={c.form.control}
            name={f.name}
            rules={c.rules(f)}
            render={({ field, fieldState }) => {
              const err = msg(fieldState.error?.message);
              if (f.type === 'switch')
                return (
                  <View style={styles.switchRow}>
                    <Text style={styles.flex}>{f.label}</Text>
                    <Switch value={!!field.value} onValueChange={field.onChange} disabled={f.readonly} trackColor={{ true: theme.colors.primary }} accessibilityLabel={f.label} />
                  </View>
                );
              if ((f.type === 'segmented' || f.type === 'radio' || f.type === 'select') && Array.isArray(f.options))
                return (
                  <View style={styles.gap}>
                    <Text variant="label">{f.label}</Text>
                    <Segmented options={f.options} value={String(field.value ?? '')} onChange={field.onChange} />
                    {err ? <Text variant="small" tone="danger">{err}</Text> : null}
                  </View>
                );
              const editable = !f.readonly && ['text', 'textarea', 'email', 'phone', 'url', 'number', 'money', 'markdown', 'secret', 'color', 'date', 'datetime', 'time'].includes(f.type);
              return (
                <Field
                  label={f.label + (f.required ? ' *' : '')}
                  value={field.value === undefined || field.value === null ? '' : String(field.value)}
                  onChangeText={field.onChange}
                  onBlur={field.onBlur}
                  editable={editable}
                  placeholder={f.placeholder}
                  help={f.help}
                  error={err}
                  multiline={f.type === 'textarea' || f.type === 'markdown'}
                  secureTextEntry={f.type === 'secret'}
                  keyboardType={KEYBOARD[f.type] ?? 'default'}
                  autoCapitalize={f.type === 'email' || f.type === 'url' ? 'none' : 'sentences'}
                  maxLength={f.maxLength}
                />
              );
            }}
          />
        ))}
        {c.form.formState.errors.root?.message ? <Text tone="danger">{msg(c.form.formState.errors.root.message)}</Text> : null}
        <Button label={c.submitting ? t('form.saving') : t('form.submit')} loading={c.submitting} onPress={() => void c.submit()} full />
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create((theme) => ({
  screen: { flex: 1, backgroundColor: theme.colors.bg },
  wrap: { padding: theme.space[4], gap: theme.space[4], paddingBottom: 80 },
  gap: { gap: 6 },
  flex: { flex: 1 },
  switchRow: { flexDirection: 'row', alignItems: 'center', minHeight: 48 },
}));
