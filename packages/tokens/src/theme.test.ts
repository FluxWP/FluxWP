import { describe, expect, it } from 'vitest';
import { contrast, themeFromConfig } from './theme';

describe('theme', () => {
  const brand = { primary: '#4338CA', primaryContrast: '#FFFFFF', accent: '#F59E0B' };
  it('applies site colors and computes a readable onPrimary', () => {
    const t = themeFromConfig({ mode: 'light', brand, config: { light: { primary: '#FDE047' } } });
    expect(t.colors.primary).toBe('#FDE047');
    expect(contrast(t.colors.primary, t.colors.onPrimary)).toBeGreaterThan(4.5);
  });
  it('ignores non-color values from the server', () => {
    const t = themeFromConfig({ mode: 'light', brand, config: { light: { primary: 'url(javascript:x)' } } });
    expect(t.colors.primary).toBe('#4338CA');
  });
  it('lifts a too-dark primary in dark mode', () => {
    const t = themeFromConfig({ mode: 'dark', brand: { ...brand, primary: '#1F2937' } });
    expect(contrast(t.colors.primary, t.colors.bg)).toBeGreaterThan(3);
  });
  it('applies an allowlisted site font and keeps Figtree otherwise', () => {
    const t = themeFromConfig({ mode: 'light', brand, config: { font: { body: 'Nunito Sans', heading: 'Nunito' } } });
    expect(t.font.family.startsWith('"Nunito Sans"')).toBe(true);
    expect(t.font.heading.startsWith('Nunito')).toBe(true);
    const blocked = themeFromConfig({ mode: 'light', brand, config: { font: { body: 'url(javascript:x)' } } });
    expect(blocked.font.family.startsWith('Figtree')).toBe(true);
  });
  it('keeps the beige canvas when the site only overrides the action color', () => {
    const t = themeFromConfig({ mode: 'light', brand, config: { light: { primary: '#4338CA' } } });
    expect(t.colors.bg).toBe('#F3F2EF');
    expect(t.colors.accent).toBe('#F59E0B');
    expect(t.colors.border).toBe('#E6E0D6');
  });
});
