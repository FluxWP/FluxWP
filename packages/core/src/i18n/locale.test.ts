import { describe, expect, it } from 'vitest';
import { createI18n, normalizeLocale, locales, resources } from './index';
describe('WordPress locale normalization', () => {
 it('supports all ten locales and regional WordPress codes', () => {
  expect(locales).toHaveLength(10);
  for (const [input,expected] of [['de_DE','de'],['fr_CA','fr'],['pt_BR','pt-br'],['pt-PT','pt-br'],['zh_CN','zh'],['ar','ar'],['ja','ja'],['ru_RU','ru']]) expect(normalizeLocale(input)).toBe(expected);
 });
 it('retains the display-name interpolation in every locale', () => {
  for (const locale of locales) expect(resources[locale].translation.auth.welcome).toBe('{{name}}');
 });
 it('loads localized interface messages immediately', () => {
  expect(createI18n('de_DE').t('common.cancel')).not.toBe(createI18n('tr_TR').t('common.cancel'));
  expect(createI18n('ar').language).toBe('ar');
 });
});
