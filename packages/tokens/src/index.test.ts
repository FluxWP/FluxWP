import { describe, expect, it } from 'vitest';
import { toCssVars, nativeTheme } from './index';

const brand = { primary: '#111111', primaryContrast: '#ffffff', accent: '#222222' };

describe('tokens', () => {
  it('marka rengi paleti ezer', () => {
    const v = toCssVars('light', brand);
    expect(v['--flux-primary']).toBe('#111111');
    expect(v['--flux-text-muted']).toBeDefined();
    expect(v['--flux-space-4']).toBe('16px');
  });
  it('native tema mod taşır', () => {
    expect(nativeTheme('dark', brand).colors.bg).toBe('#101114');
    expect(nativeTheme('light', brand).colors.bg).toBe('#F3F2EF');
    expect(nativeTheme('light', brand).colors.text).toBe('#111827');
  });
});
