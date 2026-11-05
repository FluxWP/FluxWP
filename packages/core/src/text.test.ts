import { describe, expect, it } from 'vitest';
import { isUnsafeKey, siteBase, stripAngles, stripTags, trimEnd, trimStart } from './text';
import { adminPath } from './layout/merge';

describe('text helpers', () => {
  it('trims trailing slashes in linear time', () => {
    expect(trimEnd('https://a.test///')).toBe('https://a.test');
    expect(trimEnd('/')).toBe('');
    expect(siteBase('https://a.test/wp')).toBe('https://a.test/wp');
    const evil = '/'.repeat(50000) + 'x';
    const t0 = Date.now();
    expect(trimEnd(evil)).toBe(evil);
    expect(Date.now() - t0).toBeLessThan(100);
    expect(trimStart('///wp-admin/')).toBe('wp-admin/');
    expect(trimStart('/')).toBe('');
    expect(trimStart('index.php')).toBe('index.php');
  });
  it('strips angle brackets without leaving a tag fragment', () => {
    expect(stripAngles('<p>Hi</p>')).toBe('pHi/p');
    expect(stripAngles('<script')).toBe('script');
    expect(stripAngles('<scr<script>ipt>')).toBe('scrscriptipt');
    expect(stripAngles('Fish & chips')).toBe('Fish & chips');
    expect(isUnsafeKey('__proto__')).toBe(true);
    expect(isUnsafeKey('constructor')).toBe(true);
    expect(isUnsafeKey('prototype')).toBe(true);
    expect(isUnsafeKey('name')).toBe(false);
  });
  it('strips tags', () => {
    expect(stripTags('<p>Hi <b>there</b></p>')).toBe('Hi there');
    expect(stripTags('<img src=x onerror=alert(1)>')).toBe('');
    expect(stripTags('a < b')).toBe('a ');
  });
  it('adminPath', () => {
    expect(adminPath('https://a.test/wp-admin/edit.php?x=1')).toBe('edit.php?x=1');
    expect(adminPath('https://a.test/wp-admin/')).toBe('index.php');
    expect(adminPath('https://a.test/other')).toBe('https://a.test/other');
  });
});
