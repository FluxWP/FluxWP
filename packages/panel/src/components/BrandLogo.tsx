import { brand } from '@fluxwp/core';

/** Marka logosu: açık/koyu temaya göre (html[data-theme]) logo.svg ya da logo-dark.svg. */
export function BrandLogo({ className }: { className?: string }) {
  return (
    <>
      <img className={(className ?? '') + ' brand-img brand-img-light'} src="./brand/logo.svg" alt={brand.name} draggable={false} />
      <img className={(className ?? '') + ' brand-img brand-img-dark'} src="./brand/logo-dark.svg" alt="" aria-hidden draggable={false} />
    </>
  );
}
