// Top of the sidebar: the selected site's own logo. A wide logo when the site has one for the current
// theme, otherwise its square icon with the site name. The FluxWP mark lives in Settings › About.
import { useState } from 'react';
import { Avatar } from '@fluxwp/ui';

export interface SiteBrand {
  name: string;
  /** Wide logo for light backgrounds. */
  logo: string;
  /** Wide logo for dark backgrounds. */
  logoDark: string;
  /** Square icon. */
  icon: string;
  url: string;
}

/** A "logo" that is only the square icon again is not a wide logo. */
const wide = (u: string, icon: string) => !!u && u !== icon;

export function SiteLogo({ site }: { site: SiteBrand }) {
  const [broken, setBroken] = useState<Record<string, boolean>>({});
  const light = wide(site.logo, site.icon) && !broken[site.logo] ? site.logo : '';
  const dark = wide(site.logoDark, site.icon) && !broken[site.logoDark] ? site.logoDark : '';
  const fail = (u: string) => () => setBroken((b) => ({ ...b, [u]: true }));
  const mark = (
    <span className="site-mark">
      <Avatar src={site.icon} name={site.name} size={36} square />
      <span className="site-name ellipsis-1">{site.name}</span>
    </span>
  );
  return (
    <span className="site-logo" title={site.name}>
      {/* Light theme: the light logo, else the icon + name. Dark theme: the dark logo, else the icon + name. */}
      <span className="brand-img-light">{light ? <img src={light} alt={site.name} draggable={false} onError={fail(light)} /> : mark}</span>
      <span className="brand-img-dark">{dark ? <img src={dark} alt={site.name} draggable={false} onError={fail(dark)} /> : mark}</span>
    </span>
  );
}
