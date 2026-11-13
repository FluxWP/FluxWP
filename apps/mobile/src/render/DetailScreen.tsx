// Manifest `detail` screen: header, sections (fields, stats, chips, timeline, list, content, notice,
// actions) and footer actions, from useDetailController.
import { useLayoutEffect } from 'react';
import { Pressable, RefreshControl, ScrollView, View } from 'react-native';
import { useNavigation } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { stripTags } from '@fluxwp/core';
import { useDetailController, useRenderer, type SectionView } from '@fluxwp/runtime';
import type { DetailScreen as DetailDef, Manifest } from '@fluxwp/manifest';
import { GlassButton } from '@/ui/Glass';
import { Avatar, Badge, Button, Card, EmptyState, Skeleton, Text } from '@/ui/primitives';
import { useChrome, useScrollChrome } from '@/shell/chrome';

export function DetailScreen({ manifest, screen, params }: { manifest: Manifest; screen: DetailDef; params: Record<string, string> }) {
  const chrome = useChrome();
  const scrollChrome = useScrollChrome();
  const scrollTop = chrome.top ? { ...scrollChrome, contentInset: { top: chrome.top, bottom: 0 }, scrollIndicatorInsets: { top: chrome.top, bottom: 0 } } : scrollChrome;
  const c = useDetailController(manifest, screen, params);
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const nav = useNavigation();
  useLayoutEffect(() => {
    nav.setOptions({
      title: c.header?.title || c.title,
      headerRight: c.headerActions.length ? () => <View style={styles.headerActions}>{c.headerActions.slice(0, 2).map((a) => <GlassButton key={a.id} icon={a.icon ?? 'more'} label={a.label} onPress={() => void c.runAction(a.id)} color={a.tone === 'danger' ? theme.colors.danger : undefined} />)}</View> : undefined,
    });
  }, [nav, c, theme.colors.danger]);

  if (c.loading) return <Skeleton rows={4} />;
  if (c.error && !c.sections.length) return <EmptyState icon="alert" title={t('states.errorTitle')} body={t('states.errorBody')} action={{ label: t('mobile.states.retry'), onPress: () => void c.refresh() }} />;
  return (
    <View style={styles.screen}>
      <ScrollView {...(c.footerActions.length ? scrollTop : scrollChrome)} contentContainerStyle={styles.wrap} refreshControl={<RefreshControl refreshing={c.refreshing} onRefresh={() => void c.refresh()} tintColor={theme.colors.primary} />}>
        {c.header ? (
          <View style={styles.header}>
            {c.header.avatar ? <Avatar image={c.header.avatar.image} text={c.header.avatar.text} size={56} /> : null}
            <View style={styles.flex}>
              <Text variant="title" style={styles.headerTitle}>{stripTags(c.header.title)}</Text>
              {c.header.subtitle ? <Text tone="muted">{c.header.subtitle}</Text> : null}
            </View>
          </View>
        ) : null}
        {c.sections.map((s, i) => <Section key={i} s={s} run={c.runAction} />)}
        {c.headerActions.length > 2 ? c.headerActions.slice(2).map((a) => <Button key={a.id} kind={a.tone === 'danger' ? 'danger' : 'secondary'} label={a.label} icon={a.icon} onPress={() => void c.runAction(a.id)} />) : null}
      </ScrollView>
      {c.footerActions.length ? (
        <View style={[styles.footer, chrome.bottom ? { paddingBottom: 16 + chrome.bottom } : null]}>
          {c.footerActions.map((a) => <View key={a.id} style={styles.flex}><Button kind="secondary" label={a.label} icon={a.icon} onPress={() => void c.runAction(a.id)} full /></View>)}
        </View>
      ) : null}
    </View>
  );
}

export function Section({ s, run }: { s: SectionView; run: (id: string) => Promise<boolean> }) {
  const r = useRenderer();
  const body = (() => {
    switch (s.type) {
      case 'fields':
        return s.fields.map((f, i) => (
          <Pressable key={i} disabled={!f.link} onPress={() => r.nav.openUrl(f.link)} onLongPress={f.copy ? () => void r.ui.copy(f.value).then(() => r.ui.toast(f.value)) : undefined} style={[styles.field, i > 0 && styles.line]}>
            <Text variant="small" tone="muted" style={styles.fieldLabel}>{f.label}</Text>
            <Text style={styles.flex} tone={f.link ? 'primary' : undefined} selectable>{f.value}</Text>
          </Pressable>
        ));
      case 'stats':
        return <View style={styles.stats}>{s.items.map((x, i) => <Pressable key={i} disabled={!x.press} onPress={x.press ?? undefined} style={styles.stat}><Text variant="caption">{x.label}</Text><Text variant="heading">{x.value}</Text>{x.sub ? <Text variant="caption">{x.sub}</Text> : null}</Pressable>)}</View>;
      case 'chips':
        return <View style={styles.chips}>{s.chips.map((ch, i) => <Badge key={i} text={ch.text} tone="accent" />)}</View>;
      case 'timeline':
        return s.items.map((x, i) => <View key={i} style={[styles.timeline, i > 0 && styles.line]}><Text style={styles.bold}>{x.title}</Text>{x.body ? <Text variant="small" tone="muted">{x.body}</Text> : null}<Text variant="caption">{[x.label, x.at].filter(Boolean).join(' · ')}</Text></View>);
      case 'list':
        return s.rows.map((x, i) => <View key={x.key} style={[styles.timeline, i > 0 && styles.line]}><Text>{x.title}</Text>{x.subtitle ? <Text variant="small" tone="muted">{x.subtitle}</Text> : null}</View>);
      case 'content':
        return <Text selectable>{s.format === 'html' ? stripTags(s.value).trim() : s.value}</Text>;
      case 'actions':
        return <View style={styles.chips}>{s.actions.map((a) => <Button key={a.id} kind="secondary" label={a.label} icon={a.icon} onPress={() => void run(a.id)} />)}</View>;
      case 'notice':
        return <Text>{s.text}</Text>;
    }
  })();
  return (
    <Card style={styles.card}>
      {s.title ? <Text variant="label">{s.title}</Text> : null}
      {body}
    </Card>
  );
}

const styles = StyleSheet.create((theme) => ({
  screen: { flex: 1, backgroundColor: theme.colors.bg },
  wrap: { padding: theme.space[4], gap: theme.space[3], paddingBottom: 120 },
  header: { flexDirection: 'row', gap: theme.space[3], alignItems: 'center', marginBottom: theme.space[2] },
  headerTitle: { fontSize: 22 },
  headerActions: { flexDirection: 'row', gap: 8 },
  flex: { flex: 1 },
  card: { gap: theme.space[2] },
  field: { flexDirection: 'row', gap: theme.space[3], paddingVertical: 8 },
  fieldLabel: { width: 110 },
  line: { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: theme.colors.border },
  stats: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space[3] },
  stat: { minWidth: 96, gap: 2 },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  timeline: { paddingVertical: 8, gap: 2 },
  bold: { fontWeight: '600' },
  footer: { flexDirection: 'row', gap: theme.space[3], padding: theme.space[4], borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: theme.colors.border, backgroundColor: theme.colors.surface },
}));
