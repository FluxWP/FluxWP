// Expo config from the build brand: FLUX_BRAND=<slug> → brands/<slug>/brand.json (default "flux" = FluxWP,
// com.fluxwp.app, scheme fluxwp). White-label builds change only the brand folder (plan §11).
import type { ConfigContext, ExpoConfig } from 'expo/config';
import pkg from './package.json';

// eslint-disable-next-line @typescript-eslint/no-require-imports
const { applyBrand } = require('./scripts/brand.cjs') as { applyBrand: (slug?: string) => MobileBrand };

interface MobileBrand {
  slug: string;
  name: string;
  bundleId: string;
  androidPackage: string;
  scheme: string;
  colors: { primary: string };
  splash: { background?: string; backgroundDark?: string };
  eas: { projectId?: string | null; owner?: string; updatesChannel?: string } | null;
  oauthClientId: string;
  allowedSites: string[];
  lockSite: boolean;
}

export default ({ config }: ConfigContext): ExpoConfig => {
  const brand = applyBrand(process.env.FLUX_BRAND);
  const asset = (f: string) => `./assets/brand/${f}`;
  const projectId = process.env.EXPO_PUBLIC_EAS_PROJECT_ID || brand.eas?.projectId || undefined;
  const version = process.env.FLUX_APP_VERSION || pkg.version;
  const buildNumber = process.env.FLUX_BUILD_NUMBER;
  if (!/^\d+\.\d+\.\d+$/.test(version)) throw new Error('FLUX_APP_VERSION must be major.minor.patch');
  if (buildNumber && (!/^[1-9]\d*$/.test(buildNumber) || Number(buildNumber) > 2_100_000_000)) throw new Error('Invalid FLUX_BUILD_NUMBER');
  if (projectId && !/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i.test(projectId)) throw new Error('Invalid EAS project ID');
  return {
    ...config,
    name: brand.name,
    slug: brand.slug === 'flux' ? 'fluxwp' : `fluxwp-${brand.slug}`,
    owner: brand.eas?.owner || 'fluxwp',
    version,
    orientation: 'portrait',
    icon: asset('icon.png'),
    scheme: brand.scheme,
    userInterfaceStyle: 'automatic',
    runtimeVersion: { policy: 'fingerprint' },
    ...(projectId ? { updates: { url: `https://u.expo.dev/${projectId}` } } : {}),
    ios: {
      bundleIdentifier: brand.bundleId,
      supportsTablet: true,
      ...(buildNumber ? { buildNumber } : {}),
      ...(process.env.APPLE_TEAM_ID ? { appleTeamId: process.env.APPLE_TEAM_ID } : {}),
      config: { usesNonExemptEncryption: false },
    },
    android: {
      package: brand.androidPackage,
      ...(buildNumber ? { versionCode: Number(buildNumber) } : {}),
      blockedPermissions: ['android.permission.RECORD_AUDIO'],
      adaptiveIcon: {
        backgroundColor: brand.colors.primary,
        foregroundImage: asset('adaptive-icon.png'),
        monochromeImage: asset('adaptive-monochrome.png'),
      },
      predictiveBackGestureEnabled: false,
    },
    web: { output: 'single', favicon: asset('favicon.png'), name: brand.name, themeColor: brand.colors.primary },
    plugins: [
      'expo-router',
      [
        'expo-splash-screen',
        {
          image: asset('splash.png'),
          imageWidth: 120,
          backgroundColor: brand.splash.background ?? '#F3F2EF',
          dark: { image: asset('splash.png'), backgroundColor: brand.splash.backgroundDark ?? '#101114' },
        },
      ],
      'expo-secure-store',
      'expo-localization',
      'expo-web-browser',
      ['expo-notifications', { icon: asset('notification.png'), color: brand.colors.primary }],
      [
        'expo-image-picker',
        {
          photosPermission: 'Fotoğraf seçmek için galeriye erişilir. / The gallery is used to choose photos.',
          cameraPermission: 'Dekont ve görsel çekmek için kamera kullanılır. / The camera is used to take photos.',
          microphonePermission: false,
        },
      ],
    ],
    experiments: { typedRoutes: true, reactCompiler: true },
    extra: {
      brand: brand.slug,
      oauthClientId: brand.oauthClientId,
      allowedSites: brand.allowedSites,
      lockSite: brand.lockSite,
      ...(projectId ? { eas: { projectId } } : {}),
    },
  };
};
