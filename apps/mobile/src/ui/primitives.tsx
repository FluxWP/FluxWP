// UI primitives on Unistyles. Every color comes from the active theme (site theme of the account).
import { type ReactNode } from 'react';
import { ActivityIndicator, Pressable, Text as RNText, TextInput, View, type TextInputProps, type TextProps, type ViewStyle } from 'react-native';
import { Image } from 'expo-image';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import type { Tone } from '@fluxwp/manifest';
import { Icon } from './Icon';

export function Text({ variant = 'body', tone, style, ...rest }: TextProps & { variant?: 'title' | 'heading' | 'body' | 'label' | 'small' | 'caption'; tone?: 'muted' | 'primary' | 'danger' | 'onPrimary' }) {
  styles.useVariants({ variant, tone });
  return <RNText {...rest} style={[styles.text, style]} />;
}

export function Button(props: { label: string; onPress?: () => void; kind?: 'primary' | 'secondary' | 'ghost' | 'danger'; icon?: string; loading?: boolean; disabled?: boolean; full?: boolean; testID?: string }) {
  const { kind = 'primary', loading, disabled } = props;
  const { theme } = useUnistyles();
  styles.useVariants({ kind });
  const fg = kind === 'primary' ? theme.colors.onPrimary : kind === 'danger' ? '#FFFFFF' : kind === 'secondary' ? theme.colors.text : theme.colors.primary;
  return (
    <Pressable
      testID={props.testID}
      accessibilityRole="button"
      accessibilityState={{ disabled: !!disabled || !!loading, busy: !!loading }}
      disabled={disabled || loading}
      onPress={props.onPress}
      style={({ pressed }) => [styles.button, props.full && styles.full, (pressed || disabled) && styles.pressed]}
    >
      {loading ? <ActivityIndicator size="small" color={fg} /> : props.icon ? <Icon name={props.icon} size={18} color={fg} /> : null}
      <RNText style={[styles.buttonText, { color: fg }]}>{props.label}</RNText>
    </Pressable>
  );
}

export function IconButton({ icon, label, onPress, color, badge, testID }: { icon: string; label: string; onPress?: () => void; color?: string; badge?: number | string; testID?: string }) {
  const { theme } = useUnistyles();
  return (
    <Pressable testID={testID} accessibilityRole="button" accessibilityLabel={label} hitSlop={8} onPress={onPress} style={({ pressed }) => [styles.iconButton, pressed && styles.pressed]}>
      <Icon name={icon} size={22} color={color ?? theme.colors.text} />
      {badge ? <Badge text={String(badge)} tone="danger" style={styles.iconBadge} /> : null}
    </Pressable>
  );
}

export function Avatar({ image, text, size = 40, square }: { image?: string; text?: string; size?: number; square?: boolean }) {
  const { theme } = useUnistyles();
  const initials = (text ?? '').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]!.toLocaleUpperCase()).join('');
  const radius = square ? theme.radius.md : size / 2;
  if (image) return <Image source={{ uri: image }} style={{ width: size, height: size, borderRadius: radius }} contentFit="cover" transition={120} />;
  return (
    <View style={[styles.avatar, { width: size, height: size, borderRadius: radius }]}>
      <RNText style={[styles.avatarText, { fontSize: size * 0.38 }]}>{initials || '·'}</RNText>
    </View>
  );
}

export function Badge({ text, tone = 'default', style }: { text: string; tone?: Tone; style?: ViewStyle }) {
  // Unistyles reserves the variant name "default".
  styles.useVariants({ badgeTone: tone === 'default' ? 'neutral' : tone });
  return (
    <View style={[styles.badge, style]}>
      <RNText style={styles.badgeText} numberOfLines={1}>{text}</RNText>
    </View>
  );
}

export function Card({ children, style, onPress }: { children: ReactNode; style?: ViewStyle; onPress?: () => void }) {
  if (!onPress) return <View style={[styles.card, style]}>{children}</View>;
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.card, style, pressed && styles.pressed]}>
      {children}
    </Pressable>
  );
}

export function Field({ label, error, help, ...input }: TextInputProps & { label: string; error?: string; help?: string }) {
  const { theme } = useUnistyles();
  return (
    <View style={styles.field}>
      <Text variant="label">{label}</Text>
      <TextInput placeholderTextColor={theme.colors.textMuted} {...input} style={[styles.input, !!error && styles.inputError, input.multiline && styles.textarea]} accessibilityLabel={label} />
      {error ? <Text variant="small" tone="danger">{error}</Text> : help ? <Text variant="small" tone="muted">{help}</Text> : null}
    </View>
  );
}

export function Segmented<T extends string | number | boolean | null>({ options, value, onChange }: { options: { value: T; label: string }[]; value: T; onChange: (v: T) => void }) {
  return (
    <View style={styles.segmented} accessibilityRole="tablist">
      {options.map((o) => {
        const active = o.value === value;
        return (
          <Pressable key={String(o.value)} accessibilityRole="tab" accessibilityState={{ selected: active }} onPress={() => onChange(o.value)} style={[styles.segment, active && styles.segmentActive]}>
            <RNText style={[styles.segmentText, active && styles.segmentTextActive]} numberOfLines={1}>{o.label}</RNText>
          </Pressable>
        );
      })}
    </View>
  );
}

export function Spinner({ label }: { label?: string }) {
  const { theme } = useUnistyles();
  return (
    <View style={styles.center} accessibilityLabel={label} accessibilityRole="progressbar">
      <ActivityIndicator color={theme.colors.primary} />
    </View>
  );
}

export function EmptyState({ icon = 'inbox', title, body, action }: { icon?: string; title: string; body?: string; action?: { label: string; onPress: () => void } }) {
  const { theme } = useUnistyles();
  return (
    <View style={styles.empty}>
      <View style={styles.emptyCard}>
        <View style={styles.emptyIcon}>
          <Icon name={icon} size={26} color={theme.colors.accent} />
        </View>
        <Text variant="heading" style={styles.centerText}>{title}</Text>
        {body ? <Text tone="muted" style={styles.centerText}>{body}</Text> : null}
        {action ? <Button kind="secondary" label={action.label} onPress={action.onPress} /> : null}
      </View>
    </View>
  );
}

export function Skeleton({ rows = 6 }: { rows?: number }) {
  return (
    <View style={styles.skeletonWrap} accessibilityLabel="loading">
      {Array.from({ length: rows }, (_, i) => (
        <View key={i} style={styles.skeletonRow}>
          <View style={styles.skeletonAvatar} />
          <View style={styles.skeletonLines}>
            <View style={[styles.skeletonLine, { width: `${70 - (i % 3) * 12}%` }]} />
            <View style={[styles.skeletonLine, styles.skeletonLineSmall]} />
          </View>
        </View>
      ))}
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  text: {
    color: theme.colors.text,
    fontFamily: theme.font.family,
    fontSize: theme.font.size.md,
    variants: {
      variant: {
        title: { fontFamily: theme.font.heading, fontSize: 26, fontWeight: '800', letterSpacing: -0.4 },
        heading: { fontFamily: theme.font.heading, fontSize: theme.font.size.lg, fontWeight: '700' },
        body: { fontSize: 15, lineHeight: 21 },
        label: { fontSize: theme.font.size.sm, fontWeight: '700', color: theme.colors.textMuted },
        small: { fontSize: theme.font.size.sm, lineHeight: 18 },
        caption: { fontSize: theme.font.size.xs, color: theme.colors.textMuted },
      },
      tone: {
        muted: { color: theme.colors.textMuted },
        primary: { color: theme.colors.primary },
        danger: { color: theme.colors.danger },
        onPrimary: { color: theme.colors.onPrimary },
      },
    },
  },
  button: {
    minHeight: 44,
    paddingHorizontal: theme.space[4],
    borderRadius: theme.radius.md,
    borderWidth: 1,
    borderColor: 'transparent',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: theme.space[2],
    variants: {
      kind: {
        primary: { backgroundColor: theme.colors.primary, borderColor: theme.colors.primary },
        secondary: { backgroundColor: theme.colors.surface, borderColor: theme.colors.border },
        ghost: { backgroundColor: 'transparent' },
        danger: { backgroundColor: theme.colors.danger, borderColor: theme.colors.danger },
      },
    },
  },
  buttonText: { fontFamily: theme.font.family, fontSize: 15, fontWeight: '700' },
  full: { alignSelf: 'stretch' },
  pressed: { opacity: 0.6 },
  iconButton: { width: 44, height: 44, alignItems: 'center', justifyContent: 'center' },
  iconBadge: { position: 'absolute', top: 4, right: 2 },
  avatar: { backgroundColor: theme.colors.primarySoft, alignItems: 'center', justifyContent: 'center' },
  avatarText: { color: theme.colors.primary, fontWeight: '700' },
  badge: {
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: theme.radius.full,
    alignSelf: 'flex-start',
    minWidth: 20,
    alignItems: 'center',
    variants: {
      badgeTone: {
        neutral: { backgroundColor: theme.colors.surface2 },
        accent: { backgroundColor: theme.mode === 'dark' ? 'rgba(245,158,11,.22)' : '#FBEADB' },
        ok: { backgroundColor: theme.mode === 'dark' ? 'rgba(60,194,122,.2)' : 'rgba(31,157,85,.12)' },
        warn: { backgroundColor: theme.mode === 'dark' ? 'rgba(232,176,58,.2)' : 'rgba(201,138,0,.14)' },
        danger: { backgroundColor: theme.colors.danger },
        muted: { backgroundColor: theme.colors.surface2 },
        info: { backgroundColor: theme.mode === 'dark' ? 'rgba(96,165,250,.2)' : 'rgba(37,99,235,.12)' },
      },
    },
  },
  badgeText: {
    fontSize: 12,
    fontWeight: '600',
    color: theme.colors.text,
    variants: {
      badgeTone: {
        neutral: { color: theme.colors.text },
        accent: { color: theme.colors.accent },
        ok: { color: theme.colors.success },
        warn: { color: theme.colors.warning },
        danger: { color: '#FFFFFF' },
        muted: { color: theme.colors.textMuted },
        info: { color: theme.colors.info },
      },
    },
  },
  card: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: theme.space[4],
    shadowColor: '#111318',
    shadowOpacity: 0.05,
    shadowRadius: 8,
    shadowOffset: { width: 0, height: 2 },
    elevation: 1,
  },
  field: { gap: 6 },
  input: {
    minHeight: 44,
    borderRadius: theme.radius.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface,
    color: theme.colors.text,
    paddingHorizontal: theme.space[4],
    fontSize: 16,
    fontFamily: theme.font.family,
  },
  inputError: { borderColor: theme.colors.danger },
  textarea: { minHeight: 110, paddingTop: 12, textAlignVertical: 'top' },
  segmented: { flexDirection: 'row', backgroundColor: theme.colors.surface2, borderRadius: theme.radius.md, padding: 3, gap: 3 },
  segment: { flex: 1, minHeight: 34, borderRadius: theme.radius.sm, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 8 },
  segmentActive: { backgroundColor: theme.colors.surface, shadowColor: '#000', shadowOpacity: 0.08, shadowRadius: 3, shadowOffset: { width: 0, height: 1 }, elevation: 1 },
  segmentText: { fontFamily: theme.font.family, fontSize: 13, fontWeight: '600', color: theme.colors.textMuted },
  segmentTextActive: { color: theme.colors.text, fontWeight: '700' },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: theme.space[8] },
  centerText: { textAlign: 'center' },
  empty: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: theme.space[6] },
  emptyCard: { alignItems: 'center', gap: theme.space[3], width: '100%', maxWidth: 360, padding: theme.space[6], backgroundColor: theme.colors.surface, borderWidth: 1, borderStyle: 'dashed', borderColor: theme.colors.border, borderRadius: theme.radius.xl },
  emptyIcon: { width: 56, height: 56, borderRadius: theme.radius.lg, backgroundColor: theme.colors.surface2, alignItems: 'center', justifyContent: 'center' },
  skeletonWrap: { padding: theme.space[4], gap: theme.space[4] },
  skeletonRow: { flexDirection: 'row', gap: theme.space[3], alignItems: 'center' },
  skeletonAvatar: { width: 40, height: 40, borderRadius: 20, backgroundColor: theme.colors.surface2 },
  skeletonLines: { flex: 1, gap: 8 },
  skeletonLine: { height: 12, borderRadius: 6, backgroundColor: theme.colors.surface2 },
  skeletonLineSmall: { width: '40%' },
}));
