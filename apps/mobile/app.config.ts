// Expo config from the build brand: FLUX_BRAND=<slug> → brands/<slug>/brand.json (default "flux" = FluxWP,
// com.fluxwp.app, scheme fluxwp). White-label builds change only the brand folder (plan §11).
import type { ConfigContext, ExpoConfig } from 'expo/config';
import pkg from './package.json';

// eslint-disable-next-line @typescript-eslint/no-require-imports
const { applyBrand } = require('./scripts/brand.cjs') as { applyBrand: (slug?: string) => MobileBrand };
// eslint-disable-next-line @typescript-eslint/no-require-imports
const { existsSync } = require('node:fs') as typeof import('node:fs');

interface MobileBrand {
  slug: string;
  name: string;
  bundleId: string;
  androidPackage: string;
  scheme: string;
  colors: { primary: string };
  splash: { background?: string; backgroundDark?: string };
  eas: { projectId?: string | null; owner?: string; updatesChannel?: string } | null;
  ota: { url: string; channel: string };
  oauthClientId: string;
  allowedSites: string[];
  lockSite: boolean;
  locales: string[];
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
  const otaChannel = process.env.FLUX_OTA_CHANNEL || brand.ota.channel;
  if (!/^[a-z0-9][a-z0-9_-]{0,39}$/.test(otaChannel)) throw new Error('Invalid FLUX_OTA_CHANNEL');
  const otaCert = asset('ota-certificate.pem');
  const otaOn = existsSync(`${__dirname}/assets/brand/ota-certificate.pem`) && process.env.FLUX_OTA !== 'off';
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
    updates: {
      enabled: otaOn,
      url: `${brand.ota.url}/v1/expo/${brand.slug}/manifest`,
      // flux-* are filled at runtime (src/platform/ota.ts) for the update server's beta list; expo-updates
      // only lets the app override header keys that exist here, so they ship empty in the build.
      requestHeaders: { 'expo-channel-name': otaChannel, 'flux-device': '', 'flux-device-name': '', 'flux-accounts': '' },
      // Silent (Ahmet 2026-11-18): download in the background on launch, apply on the next cold start.
      checkAutomatically: 'ON_LOAD',
      fallbackToCacheTimeout: 0,
      ...(otaOn ? { codeSigningCertificate: otaCert, codeSigningMetadata: { keyid: 'main', alg: 'rsa-v1_5-sha256' as const } } : {}),
    },
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
      ['expo-localization', { supportedLocales: brand.locales }],
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
      otaChannel,
      ...(projectId ? { eas: { projectId } } : {}),
    },
  };
};
