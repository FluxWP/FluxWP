// Manifest `dashboard` screen (e.g. CRM overview): stats/actions/list sections over one or more sources.
import { useLayoutEffect } from 'react';
import { RefreshControl, ScrollView } from 'react-native';
import { useNavigation } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { useDashboardController } from '@fluxwp/runtime';
import type { DashboardScreen as DashboardDef, Manifest } from '@fluxwp/manifest';
import { EmptyState, Skeleton } from '@/ui/primitives';
import { Section } from './DetailScreen';
import { useScrollChrome } from '@/shell/chrome';

export function DashboardScreen({ manifest, screen, params }: { manifest: Manifest; screen: DashboardDef; params: Record<string, string> }) {
  const scrollChrome = useScrollChrome();
  const c = useDashboardController(manifest, screen, params);
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const nav = useNavigation();
  useLayoutEffect(() => nav.setOptions({ title: c.title }), [nav, c.title]);
  if (c.loading) return <Skeleton rows={3} />;
  if (c.error && !c.sections.length) return <EmptyState icon="alert" title={t('states.errorTitle')} body={t('states.errorBody')} action={{ label: t('mobile.states.retry'), onPress: () => void c.refresh() }} />;
  return (
    <ScrollView {...scrollChrome} contentContainerStyle={styles.wrap} refreshControl={<RefreshControl refreshing={false} onRefresh={() => void c.refresh()} tintColor={theme.colors.primary} />}>
      {c.sections.map((s, i) => <Section key={i} s={s} run={c.runAction} />)}
    </ScrollView>
  );
}

const styles = StyleSheet.create((theme) => ({
  wrap: { padding: theme.space[4], gap: theme.space[3], paddingBottom: 80 },
}));
