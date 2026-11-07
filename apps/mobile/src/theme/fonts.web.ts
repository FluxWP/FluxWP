// Figtree (Flux Admin) plus Nunito / Nunito Sans (themes/fluxwp theme.json) for the web preview.
import { useEffect } from 'react';
import { Asset } from 'expo-asset';

const files = {
  figtreeExt: require('../../../../plugins/flx-admin/assets/fonts/figtree-latin-ext-wght-normal.woff2'),
  figtree: require('../../../../plugins/flx-admin/assets/fonts/figtree-latin-wght-normal.woff2'),
  nunitoExt: require('../../../../themes/fluxwp/assets/fonts/nunito-latin-ext.woff2'),
  nunito: require('../../../../themes/fluxwp/assets/fonts/nunito-latin.woff2'),
  sansExt: require('../../../../themes/fluxwp/assets/fonts/nunitosans-latin-ext.woff2'),
  sans: require('../../../../themes/fluxwp/assets/fonts/nunitosans-latin.woff2'),
} as const;

const EXT = 'U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF';
const LAT = 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD';

function face(family: string, weight: string, uri: string, range: string): string {
  return `@font-face{font-family:${family};font-style:normal;font-weight:${weight};font-display:swap;src:url("${uri}") format("woff2");unicode-range:${range}}`;
}

export function useAppFonts(): void {
  useEffect(() => {
    let cancel = false;
    void (async () => {
      const loaded = await Promise.all(Object.values(files).map(async (mod) => (await Asset.fromModule(mod).downloadAsync()).uri));
      if (cancel || document.getElementById('flux-fonts')) return;
      const [figtreeExt, figtree, nunitoExt, nunito, sansExt, sans] = loaded;
      const style = document.createElement('style');
      style.id = 'flux-fonts';
      style.textContent = [
        face('Figtree', '300 900', figtreeExt!, EXT),
        face('Figtree', '300 900', figtree!, LAT),
        face('Nunito', '600 900', nunitoExt!, EXT),
        face('Nunito', '600 900', nunito!, LAT),
        face('"Nunito Sans"', '400 700', sansExt!, EXT),
        face('"Nunito Sans"', '400 700', sans!, LAT),
      ].join('');
      document.head.appendChild(style);
    })();
    return () => {
      cancel = true;
    };
  }, []);
}
