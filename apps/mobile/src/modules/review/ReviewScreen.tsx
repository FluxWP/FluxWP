// Donation approval queue (`flxb-review`).
import { memo, useState } from 'react';
import { Pressable, ScrollView, View } from 'react-native';
import { FlashList } from '@shopify/flash-list';
import { Image } from 'expo-image';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { bagisReview, type PendingDonation } from '@fluxwp/core';
import { useAccount, useApi, useRenderer } from '@fluxwp/runtime';
import { Avatar, Button, EmptyState, IconButton, Spinner, Text } from '@/ui/primitives';
import { useScreenTitle } from '../kit';

const Row = memo(function Row({ item, onPress }: { item: PendingDonation; onPress: () => void }) {
  return (
    <Pressable accessibilityRole="button" onPress={onPress} style={styles.row}>
      <Avatar text={item.donor.name || '?'} size={44} />
      <View style={styles.flex}>
        <View style={styles.line}>
          <Text variant="label" numberOfLines={1} style={styles.flex}>{item.donor.name}</Text>
          <Text variant="label">{item.amount_label}</Text>
        </View>
        <View style={styles.line}>
          <Text variant="small" tone="muted" numberOfLines={1} style={styles.flex}>{item.title}</Text>
          <Text variant="caption" tone={item.receipt ? 'muted' : 'danger'}>{item.receipt ? item.waiting_label : '·'}</Text>
        </View>
      </View>
    </Pressable>
  );
});

export default function ReviewScreen(_props: { params: Record<string, string> }) {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  useScreenTitle(t('mobile.screens.review.title'));
  const api = useApi();
  const account = useAccount();
  const renderer = useRenderer();
  const qc = useQueryClient();
  const review = bagisReview(api);
  const key = ['flxb', account.id, 'pending'] as const;
  const q = useQuery({ queryKey: key, queryFn: () => review.pending(), refetchInterval: 15_000 });
  const [id, setId] = useState<number | null>(null);
  const items = q.data?.items ?? [];
  const sel = items.find((i) => i.id === id) ?? null;

  const drop = (gone: number, message: string) => {
    qc.setQueryData(key, (old: typeof q.data) => old && { ...old, items: old.items.filter((i) => i.id !== gone), total: Math.max(0, old.total - 1) });
    setId(null);
    renderer.ui.toast(message, 'ok');
  };
  const approve = useMutation({
    mutationFn: (d: PendingDonation) => review.approve(d.id),
    onSuccess: (r, d) => drop(d.id, r.certificates ? t('mobile.screens.review.approvedCert', { count: r.certificates }) : t('mobile.screens.review.approved')),
  });
  const reject = useMutation({
    mutationFn: (v: { d: PendingDonation; reason: string; notify: boolean }) => review.reject(v.d.id, v.reason, v.notify),
    onSuccess: (_r, v) => drop(v.d.id, t('mobile.screens.review.rejected')),
  });

  const onApprove = async (d: PendingDonation) => {
    const ok = await renderer.ui.confirm({ title: t('mobile.screens.review.approveAsk', { name: d.donor.name, amount: d.amount_label }), tone: 'default' });
    if (ok) approve.mutate(d);
  };
  const onReject = async (d: PendingDonation) => {
    const v = await renderer.ui.prompt({
      title: t('mobile.screens.review.reject'),
      fields: [
        { name: 'reason', type: 'textarea', label: t('mobile.screens.review.reason'), placeholder: t('mobile.screens.review.reasonPh') },
        { name: 'notify', type: 'switch', label: t('mobile.screens.review.notify'), default: true },
      ],
    });
    if (!v) return;
    reject.mutate({ d, reason: String(v.reason ?? ''), notify: v.notify !== false });
  };

  if (q.isPending) return <View testID="screen-review" style={styles.fill}><Spinner /></View>;
  if (q.isError) return <View testID="screen-review" style={styles.fill}><EmptyState icon="alert" title={t('states.errorTitle')} action={{ label: t('mobile.states.retry'), onPress: () => void q.refetch() }} /></View>;

  if (sel) {
    return (
      <View testID="screen-review" style={styles.fill}>
        <View style={styles.head}>
          <IconButton icon="back" label={t('mobile.screens.review.back')} onPress={() => setId(null)} />
          <Text variant="heading">{sel.amount_label}</Text>
        </View>
        <ScrollView contentContainerStyle={styles.detail}>
          <Text tone="muted">{sel.title} · #{sel.order_id} · {sel.date_label}</Text>
          <Text variant="label">{sel.donor.name}</Text>
          {sel.donor.phone ? <Text>{sel.donor.phone}</Text> : null}
          {sel.donor.email ? <Text>{sel.donor.email}</Text> : null}
          {sel.note ? <Text tone="muted">{sel.note}</Text> : null}
          {sel.lines.map((l, i) => (
            <View key={i} style={styles.line}>
              <Text style={styles.flex}>{l.title}</Text>
              <Text tone="muted">× {l.quantity}</Text>
              <Text>{l.amount_label}</Text>
            </View>
          ))}
          <Text variant="label">{t('mobile.screens.review.receipt')}</Text>
          {sel.receipt?.type === 'image' ? (
            <Image source={{ uri: sel.receipt.url }} style={styles.receipt} contentFit="contain" accessibilityLabel={t('mobile.screens.review.receipt')} />
          ) : sel.receipt ? (
            <Button kind="secondary" label={sel.receipt.name || 'PDF'} onPress={() => renderer.nav.openUrl(sel.receipt!.url)} />
          ) : (
            <Text tone="muted">{t('mobile.screens.review.noReceipt')}</Text>
          )}
          {sel.receipt ? <Text variant="caption">{sel.receipt.at_label}</Text> : null}
          <View style={styles.actions}>
            <Button kind="danger" label={t('mobile.screens.review.reject')} onPress={() => void onReject(sel)} loading={reject.isPending} />
            <Button label={t('mobile.screens.review.approve')} onPress={() => void onApprove(sel)} loading={approve.isPending} />
          </View>
        </ScrollView>
      </View>
    );
  }

  return (
    <View testID="screen-review" style={styles.fill}>
      <Text tone="muted" style={styles.count}>{t('mobile.screens.review.count', { count: q.data?.total ?? items.length })}</Text>
      <FlashList
        data={items}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => <Row item={item} onPress={() => setId(item.id)} />}
        ListEmptyComponent={<EmptyState icon="heart" title={t('mobile.screens.review.empty')} body={t('mobile.screens.review.emptyBody')} />}
        refreshing={q.isRefetching}
        onRefresh={() => void q.refetch()}
      />
      <View style={{ height: theme.space[2] }} />
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  fill: { flex: 1, backgroundColor: theme.colors.bg },
  flex: { flex: 1 },
  count: { paddingHorizontal: theme.space[4], paddingTop: theme.space[3] },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, minHeight: 76, paddingHorizontal: theme.space[4], borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: theme.colors.border },
  line: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  head: { flexDirection: 'row', alignItems: 'center', gap: 4, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: theme.colors.border },
  detail: { padding: theme.space[4], gap: theme.space[2], paddingBottom: 32 },
  receipt: { width: '100%', height: 280, borderRadius: theme.radius.md, backgroundColor: theme.colors.surface },
  actions: { flexDirection: 'row', gap: 8, marginTop: theme.space[3] },
}));
