import { useLocalSearchParams } from 'expo-router';
import { RouteView } from '@/shell/RouteView';
import { paramsOf } from '@/shell/routes';

export default function NativeRoute() {
  const all = useLocalSearchParams();
  return <RouteView route={{ kind: 'native', component: String(all.component), params: paramsOf(all, ['account', 'component']) }} />;
}
