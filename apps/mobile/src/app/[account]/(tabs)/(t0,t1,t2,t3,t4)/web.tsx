import { useLocalSearchParams } from 'expo-router';
import { safeAdminPath } from '@fluxwp/manifest';
import { WebScreen } from '@/web/WebScreen';

export default function WebRoute() {
  const { path, title } = useLocalSearchParams<{ path?: string; title?: string }>();
  return <WebScreen path={safeAdminPath(String(path ?? '')) ?? 'index.php'} title={title} />;
}
