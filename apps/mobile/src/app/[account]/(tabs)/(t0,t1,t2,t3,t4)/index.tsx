// Tab slot root: draws the target of config.shell.tabs[n] (home when the bar is empty).
import { use, useLayoutEffect } from 'react';
import { useNavigation } from 'expo-router';
import { useShellModel } from '@fluxwp/runtime';
import { RouteView } from '@/shell/RouteView';
import { useLabel } from '@/shell/label';
import { SlotContext } from '@/shell/slot';

export default function SlotIndex() {
  const n = use(SlotContext);
  const shell = useShellModel();
  const entry = shell.tabs[n] ?? shell.home;
  const label = useLabel();
  const nav = useNavigation();
  useLayoutEffect(() => nav.setOptions({ title: label(entry.label) }), [nav, entry.label, label]);
  return <RouteView route={entry.route} />;
}
