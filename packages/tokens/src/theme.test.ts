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
});
