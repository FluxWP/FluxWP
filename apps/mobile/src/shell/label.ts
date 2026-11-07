import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';

/** Shell labels: "$t:<key>" (built-in config) → app string; server labels as they are. */
export function useLabel() {
  const { i18n } = useTranslation();
  const language = i18n.resolvedLanguage || i18n.language;
  return useCallback((s: string | undefined) => (s?.startsWith('$t:') ? i18n.t(s.slice(3), { lng: language }) : (s ?? '')), [i18n, language]);
}
