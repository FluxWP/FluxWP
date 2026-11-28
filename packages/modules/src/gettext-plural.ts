/** Match the plural order of the WordPress catalogs used to build the screen dictionaries. */
export function gettextPlural(forms: string[], locale: string, count: number): string | undefined {
  if (forms.length < 2) return forms[0];
  const n = Math.abs(count);
  if (locale === 'ar' && forms.length === 6) {
    const remainder = n % 100;
    const index = n === 0 ? 0 : n === 1 ? 1 : n === 2 ? 2 : remainder >= 3 && remainder <= 10 ? 3 : remainder >= 11 && remainder <= 99 ? 4 : 5;
    return forms[index];
  }
  if (locale === 'ru' && forms.length === 3) {
    const last = n % 10;
    const remainder = n % 100;
    if (last === 1 && remainder !== 11) return forms[0];
    if (last >= 2 && last <= 4 && (remainder < 12 || remainder > 14)) return forms[1];
    return forms[2];
  }
  const singular = locale === 'fr' || locale === 'pt-br' ? n <= 1 : n === 1;
  return forms[singular ? 0 : 1];
}
