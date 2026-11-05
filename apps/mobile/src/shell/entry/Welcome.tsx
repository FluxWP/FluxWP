// First-run frames for the site form. `onboarding` is three pages then the form.
// `card` and `band` are the other two entry designs (open with /add-site?giris=card|band).
import { useState, type ReactNode } from 'react';
import { Pressable, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { Icon } from '@/ui/Icon';
import { Button, Text } from '@/ui/primitives';

export type EntryVariant = 'onboarding' | 'card' | 'band';

export function entryVariant(raw: string | string[] | undefined): EntryVariant {
  const v = Array.isArray(raw) ? raw[0] : raw;
  return v === 'card' || v === 'band' ? v : 'onboarding';
}

const PAGES = ['sites', 'look', 'safe'] as const;

export function Welcome({ variant, children, footer }: { variant: EntryVariant; children: ReactNode; footer?: ReactNode }) {
  const { t } = useTranslation();
  const insets = useSafeAreaInsets();
  const { theme } = useUnistyles();
  const [page, setPage] = useState(0);
  const [ready, setReady] = useState(variant !== 'onboarding');
  const id = PAGES[page] ?? 'sites';
  const bottom = Math.max(insets.bottom, 16);

  if (variant === 'onboarding' && !ready) {
    return (
      <View style={[styles.screen, { paddingTop: insets.top + 12, paddingBottom: bottom }]}>
        <Pressable accessibilityRole="button" onPress={() => setReady(true)} hitSlop={8} style={styles.skip} testID="welcome-skip">
          <Text tone="muted">{t('mobile.welcome.skip')}</Text>
        </Pressable>
        <View style={styles.page}>
          <View style={styles.art}>{id === 'sites' ? <Mark size={32} /> : id === 'look' ? <ListArt /> : <SafeArt />}</View>
          <Text variant="title" style={styles.center}>{t(`mobile.welcome.${id}.title`)}</Text>
          <Text tone="muted" style={styles.center}>{t(`mobile.welcome.${id}.body`)}</Text>
        </View>
        <View style={styles.dots}>
          {PAGES.map((pid, i) => <View key={pid} style={[styles.dot, i === page && { backgroundColor: theme.colors.primary, width: 18 }]} />)}
        </View>
        {/* Primary action, then the demo link. A column with gap so they cannot stack on top of each other. */}
        <View style={styles.dock}>
          <Button
            full
            testID="welcome-next"
            label={page === PAGES.length - 1 ? t('mobile.welcome.enter') : t('mobile.welcome.next')}
            onPress={() => (page === PAGES.length - 1 ? setReady(true) : setPage(page + 1))}
          />
          {footer ? <View style={styles.footer}>{footer}</View> : null}
        </View>
      </View>
    );
  }

  if (variant === 'band') {
    return (
      <View style={[styles.screen, { paddingTop: insets.top }]}>
        <View style={[styles.band, { backgroundColor: theme.colors.primary }]}>
          <Mark on={theme.colors.onPrimary} accent={theme.colors.accent} />
          <Text variant="title" style={{ color: theme.colors.onPrimary }}>{t('mobile.welcome.bandTitle')}</Text>
          <Text style={{ color: theme.colors.onPrimary, opacity: 0.88 }}>{t('mobile.welcome.bandBody')}</Text>
        </View>
        <View style={[styles.sheet, { paddingBottom: bottom }]}>
          <Text variant="heading">{t('mobile.addSite.title')}</Text>
          <View style={styles.formGap}>{children}</View>
          <View style={styles.flex} />
          {footer ? <View style={styles.footer}>{footer}</View> : null}
        </View>
      </View>
    );
  }

  if (variant === 'card') {
    return (
      <View style={[styles.screen, styles.cardScreen, { paddingTop: insets.top + 28, paddingBottom: bottom }]}>
        <Mark size={26} />
        <Text variant="title" style={styles.center}>{t('mobile.welcome.cardTitle')}</Text>
        <Text tone="muted" style={styles.center}>{t('mobile.welcome.cardBody')}</Text>
        <View style={styles.card}>
          <View style={[styles.amber, { backgroundColor: theme.colors.accent }]} />
          <Text variant="heading">{t('mobile.addSite.title')}</Text>
          {children}
        </View>
        <View style={styles.flex} />
        {footer ? <View style={styles.footer}>{footer}</View> : null}
      </View>
    );
  }

  return (
    <View style={[styles.screen, { paddingTop: insets.top + 8, paddingBottom: bottom, paddingHorizontal: 24 }]}>
      <Mark size={18} />
      <Text variant="title">{t('mobile.addSite.title')}</Text>
      <View style={styles.formGap}>{children}</View>
      <View style={styles.flex} />
      {footer ? <View style={styles.footer}>{footer}</View> : null}
    </View>
  );
}

function Mark({ size = 22, on, accent }: { size?: number; on?: string; accent?: string }) {
  const { theme } = useUnistyles();
  const ink = on ?? theme.colors.primary;
  const leaf = accent ?? theme.colors.accent;
  const gap = 5;
  const cell = (color: string, lift = 0) => (
    <View style={{ width: size, height: size, borderRadius: 6, backgroundColor: color, marginTop: lift }} />
  );
  return (
    <View style={{ flexDirection: 'row', gap, alignItems: 'flex-start' }}>
      <View style={{ gap }}>{cell(ink)}{cell(ink)}{cell(ink)}</View>
      <View style={{ gap }}>{cell(ink)}{cell(ink)}{cell(leaf, -4)}</View>
    </View>
  );
}

function ListArt() {
  const { theme } = useUnistyles();
  return (
    <View style={[styles.listArt, { backgroundColor: theme.colors.surface, borderColor: theme.colors.border }]}>
      {['posts', 'tasks', 'donate'].map((row, i) => (
        <View key={row} style={[styles.listRow, i > 0 && { borderTopWidth: 1, borderTopColor: theme.colors.border }]}>
          <View style={[styles.mini, { backgroundColor: i === 1 ? theme.colors.accent : theme.colors.primarySoft }]} />
          <View style={styles.lines}>
            <View style={[styles.line, { backgroundColor: theme.colors.text, width: 92 - i * 12 }]} />
            <View style={[styles.line, { backgroundColor: theme.colors.border, width: 64 }]} />
          </View>
        </View>
      ))}
    </View>
  );
}

function SafeArt() {
  const { theme } = useUnistyles();
  return (
    <View style={[styles.safe, { backgroundColor: theme.colors.primarySoft }]}>
      <Icon name="lock" size={36} color={theme.colors.primary} />
      <View style={[styles.safeDot, { backgroundColor: theme.colors.accent }]} />
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  screen: { flex: 1, backgroundColor: theme.colors.bg },
  flex: { flex: 1, minHeight: 0 },
  skip: { alignSelf: 'flex-end', paddingHorizontal: 24, paddingVertical: 8, flexShrink: 0 },
  page: { flex: 1, minHeight: 0, paddingHorizontal: 32, alignItems: 'center', justifyContent: 'center', gap: 14 },
  art: { height: 180, alignItems: 'center', justifyContent: 'center', marginBottom: 8 },
  center: { textAlign: 'center' },
  dots: { flexDirection: 'row', justifyContent: 'center', gap: 6, paddingVertical: 12, flexShrink: 0 },
  dot: { width: 7, height: 7, borderRadius: 4, backgroundColor: theme.colors.border },
  dock: { paddingHorizontal: 24, paddingTop: 4, gap: 8, flexShrink: 0, alignSelf: 'stretch' },
  footer: { alignSelf: 'stretch' },
  formGap: { gap: 12, marginTop: 8, alignSelf: 'stretch' },
  band: { paddingHorizontal: 28, paddingTop: 28, paddingBottom: 36, gap: 12, alignItems: 'flex-start' },
  sheet: { flex: 1, minHeight: 0, marginTop: -20, backgroundColor: theme.colors.bg, borderTopLeftRadius: 28, borderTopRightRadius: 28, paddingHorizontal: 24, paddingTop: 24, gap: 12 },
  cardScreen: { paddingHorizontal: 24, gap: 12, alignItems: 'center' },
  card: { alignSelf: 'stretch', width: '100%', backgroundColor: theme.colors.surface, borderRadius: theme.radius.xl, borderWidth: 1, borderColor: theme.colors.border, padding: 18, gap: 12, overflow: 'hidden' },
  amber: { height: 4, borderRadius: 2, alignSelf: 'stretch', marginHorizontal: -18, marginTop: -18, marginBottom: 4 },
  listArt: { width: 220, borderRadius: 18, borderWidth: 1, paddingVertical: 6 },
  listRow: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 14, paddingVertical: 12 },
  mini: { width: 28, height: 28, borderRadius: 8 },
  lines: { gap: 6 },
  line: { height: 8, borderRadius: 4 },
  safe: { width: 120, height: 120, borderRadius: 36, alignItems: 'center', justifyContent: 'center' },
  safeDot: { position: 'absolute', right: 22, top: 26, width: 14, height: 14, borderRadius: 7 },
}));
