import { beforeEach, describe, expect, it, vi } from 'vitest';
import { memoryStore, setPlatform } from '../platform';
import { discoverSite } from './discovery';

const fetchMock = vi.fn<typeof fetch>();
const index = (namespaces = ['wp/v2', 'flxapp/v1']) => new Response(JSON.stringify({ name: 'Example', url: 'https://example.org/blog', namespaces }), { headers: { 'Content-Type': 'application/json' } });
beforeEach(() => {
  fetchMock.mockReset();
  setPlatform({ kind: 'mobile', fetch: fetchMock, secure: memoryStore(), storage: memoryStore(), openExternal: async () => {}, prepareOAuthRedirect: async () => { throw new Error('No auth session should open during discovery'); } });
});
describe('WordPress discovery', () => {
  it('uses a valid REST index without a second request', async () => {
    fetchMock.mockResolvedValueOnce(index());
    const site = await discoverSite('example.org/blog/wp-admin');
    expect(site.hasFluxApp).toBe(true);
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });
  it('falls back to the WordPress query route when pretty URLs return HTML', async () => {
    fetchMock.mockResolvedValueOnce(new Response('<html>Not found</html>')).mockResolvedValueOnce(index());
    const site = await discoverSite('https://example.org/blog/');
    expect(site).toMatchObject({ hasFluxApp: true, url: 'https://example.org/blog' });
    expect(fetchMock.mock.calls.map(call => call[0])).toEqual(['https://example.org/blog/wp-json/', 'https://example.org/blog/?rest_route=/']);
  });
  it('finds Flux App after a 404 without falsely reporting a missing plugin', async () => {
    fetchMock.mockResolvedValueOnce(new Response('', { status: 404 })).mockResolvedValueOnce(index());
    expect((await discoverSite('example.org/blog')).hasFluxApp).toBe(true);
  });
  it('reports confirmed plugin absence separately from an invalid REST response', async () => {
    fetchMock.mockResolvedValueOnce(index(['wp/v2']));
    expect((await discoverSite('example.org/blog')).hasFluxApp).toBe(false);
  });
  it('keeps blocked and unreachable responses distinct from non-WordPress sites', async () => {
    fetchMock.mockResolvedValueOnce(new Response('', { status: 403 })).mockResolvedValueOnce(new Response('', { status: 404 }));
    await expect(discoverSite('example.org')).rejects.toMatchObject({ reason: 'unreachable' });
    fetchMock.mockResolvedValueOnce(new Response('{}')).mockResolvedValueOnce(new Response('{}'));
    await expect(discoverSite('example.org')).rejects.toMatchObject({ reason: 'not_wordpress' });
  });
  it('does not send a request for empty input', async () => {
    await expect(discoverSite(' ')).rejects.toMatchObject({ reason: 'invalid_url' });
    expect(fetchMock).not.toHaveBeenCalled();
  });
});
