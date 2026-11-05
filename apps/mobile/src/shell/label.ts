import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';

/** Shell labels: "$t:<key>" (built-in config) → app string; server labels as they are. */
export function useLabel() {
  const { t } = useTranslation();
  return useCallback((s: string | undefined) => (s?.startsWith('$t:') ? t(s.slice(3)) : (s ?? '')), [t]);
}
