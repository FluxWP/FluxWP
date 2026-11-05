import { useLocalSearchParams } from 'expo-router';
import { ManifestScreen } from '@/render/ManifestScreen';
import { paramsOf } from '@/shell/routes';

export default function ManifestRoute() {
  const all = useLocalSearchParams();
  return <ManifestScreen module={String(all.module)} screen={String(all.screen)} params={paramsOf(all, ['account', 'module', 'screen'])} />;
}
