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
  const projectId = brand.eas?.projectId || undefined;
  return {
    ...config,
    name: brand.name,
    slug: brand.slug === 'flux' ? 'fluxwp' : `fluxwp-${brand.slug}`,
    owner: brand.eas?.owner || 'fluxwp',
    version: pkg.version,
    orientation: 'portrait',
    icon: asset('icon.png'),
    scheme: brand.scheme,
    userInterfaceStyle: 'automatic',
    runtimeVersion: { policy: 'fingerprint' },
    ...(projectId ? { updates: { url: `https://u.expo.dev/${projectId}` } } : {}),
    ios: {
      bundleIdentifier: brand.bundleId,
      supportsTablet: true,
      config: { usesNonExemptEncryption: false },
    },
    android: {
      package: brand.androidPackage,
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
          backgroundColor: brand.splash.background ?? '#F6F7F9',
          dark: { image: asset('splash.png'), backgroundColor: brand.splash.backgroundDark ?? '#0E1014' },
        },
      ],
      'expo-secure-store',
      'expo-localization',
      'expo-web-browser',
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
