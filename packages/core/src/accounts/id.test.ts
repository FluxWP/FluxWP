import { describe, expect, it } from 'vitest';
import { accountId, accountIdFromRouteKey, accountRouteKey, parseAccountId } from './types';

describe('account id', () => {
  it('keeps root installs as host#userId and adds the base path otherwise', () => {
    expect(accountId('https://x.org/', 3)).toBe('x.org#3');
    expect(accountId('https://x.org/blog/', 3)).toBe('x.org/blog#3');
    expect(accountId('https://x.org/blog/wp-admin/index.php', 3)).toBe('x.org/blog#3');
    expect(accountId('https://x.org:8080/shop', 2)).toBe('x.org:8080/shop#2');
    expect(accountId('https://x.org', 1)).not.toBe(accountId('https://x.org/blog', 1));
  });

  it('parses old and new ids and does not throw on a stored value', () => {
    expect(parseAccountId('x.org#3')).toEqual({ host: 'x.org', basePath: '', userId: 3 });
    expect(parseAccountId('x.org/blog#3')).toEqual({ host: 'x.org', basePath: '/blog', userId: 3 });
    expect(parseAccountId('not an id')).toBeNull();
    expect(parseAccountId('x.org#nope')).toBeNull();
    expect(accountRouteKey('x.org#3')).toBe('x.org@3');
    expect(accountIdFromRouteKey('x.org@3')).toBe('x.org#3');
    expect(accountIdFromRouteKey(accountRouteKey('x.org/blog#3'))).toBe('x.org/blog#3');
    expect(accountIdFromRouteKey('%')).toBe('%');
  });
});
