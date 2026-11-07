// Deep link landing (fluxwp://oauth/callback): resolves a pending sign-in when the system delivered the
// redirect to the app instead of the auth session (e.g. Android task restarts).
import { useEffect } from 'react';
import { Redirect, useGlobalSearchParams } from 'expo-router';
import * as Linking from 'expo-linking';
import { completeAuthRedirect } from '@/platform';
import { Spinner } from '@/ui/primitives';

export default function OAuthCallback() {
  const url = Linking.useLinkingURL();
  const params = useGlobalSearchParams();
  useEffect(() => {
    if (url) completeAuthRedirect(url);
  }, [url, params]);
  return url ? <Redirect href="/" /> : <Spinner />;
}
