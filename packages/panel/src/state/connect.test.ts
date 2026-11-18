import { describe, expect, it } from 'bun:test';
import { parseConnectLink } from './connect';

describe('parseConnectLink', () => {
  it('reads the site address', () => {
    expect(parseConnectLink('fluxwp://connect?site=https%3A%2F%2Fexample.com%2F')).toBe('https://example.com');
    expect(parseConnectLink('fluxwp://connect?site=https%3A%2F%2Fexample.com%2Fblog')).toBe('https://example.com/blog');
  });
  it('rejects other links, plain http, credentials and junk', () => {
    expect(parseConnectLink('fluxwp://oauth/callback?code=1')).toBeNull();
    expect(parseConnectLink('fluxwp://connect')).toBeNull();
    expect(parseConnectLink('fluxwp://connect?site=http%3A%2F%2Fexample.com')).toBeNull();
    expect(parseConnectLink('fluxwp://connect?site=https%3A%2F%2Fa%3Ab%40example.com')).toBeNull();
    expect(parseConnectLink('fluxwp://connect?site=javascript%3Aalert(1)')).toBeNull();
    expect(parseConnectLink('nope')).toBeNull();
  });
  it('allows http on localhost for development', () => {
    expect(parseConnectLink('fluxwp://connect?site=http%3A%2F%2Flocalhost%3A8080')).toBe('http://localhost:8080');
  });
});
