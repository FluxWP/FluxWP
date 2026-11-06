import { useTranslation } from 'react-i18next';
import { brand, platform } from '@fluxwp/core';

/** Publisher policies belong to the generic FluxWP app, not a user's connected website. */
export function LegalLinks() {
  const { t, i18n } = useTranslation();
  if (brand.slug !== 'flux') return null;
  const base = i18n.language.startsWith('tr') ? 'https://fluxwp.com/tr/' : 'https://fluxwp.com/';
  return (
    <nav className="legal-links small" aria-label={t('legal.title')}>
      <button type="button" className="btn-link" onClick={() => void platform().openExternal(base + 'privacy/')}>{t('legal.privacy')}</button>
      <button type="button" className="btn-link" onClick={() => void platform().openExternal(base + 'terms/')}>{t('legal.terms')}</button>
    </nav>
  );
}
