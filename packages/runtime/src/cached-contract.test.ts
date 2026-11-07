import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createClient, memoryStore, setPlatform } from '@fluxwp/core';
import { DEFAULT_CONFIG } from '@fluxwp/manifest';
import config from '@fluxwp/manifest/fixtures/config.json';
import manifest from '@fluxwp/manifest/fixtures/manifest.json';
import { loadConfig, loadContractValue, loadManifests, rememberedEtag, rememberEtag } from './api';

const fetchMock = vi.fn<typeof fetch>();
const api = () => createClient({ siteUrl: 'https://example.invalid', fetchImpl: fetchMock, getCredentials: async () => ({ token: 'fake-qa-token' }) });
const notModified = () => new Response(null, { status: 304, headers: { ETag: '"same"' } });
const json = (value: unknown) => new Response(JSON.stringify(value), { headers: { 'Content-Type': 'application/json', ETag: '"same"' } });
beforeEach(() => {
  fetchMock.mockReset();
  setPlatform({ kind: 'web', fetch: fetchMock, storage: memoryStore(), secure: memoryStore(), openExternal: async () => {}, prepareOAuthRedirect: async () => { throw new Error('Unexpected authentication flow'); } });
});
describe('persisted contract validators after cache reset', () => {
  it('loads the full config when a remembered validator has no corresponding body', async () => {
    fetchMock.mockResolvedValueOnce(notModified()).mockResolvedValueOnce(json(config));
    const client = api();
    const got = await loadContractValue(etag => loadConfig(client, undefined, etag), '"same"', () => undefined);
    expect(got.value?.shell?.tabs?.find(tab => tab.id === 'crm')?.label).toBe('Kişiler');
    expect(fetchMock).toHaveBeenCalledTimes(2);
    expect(new Headers(fetchMock.mock.calls[0]?.[1]?.headers).get('If-None-Match')).toBe('"same"');
    expect(new Headers(fetchMock.mock.calls[1]?.[1]?.headers).has('If-None-Match')).toBe(false);
  });
  it('keeps a valid cached config on 304 without another request', async () => {
    fetchMock.mockResolvedValueOnce(notModified());
    const client = api();
    const got = await loadContractValue(etag => loadConfig(client, undefined, etag), '"same"', () => DEFAULT_CONFIG);
    expect(got.value).toBe(DEFAULT_CONFIG);
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });
  it('recovers plugin manifests instead of silently substituting the core-only placeholder', async () => {
    fetchMock.mockResolvedValueOnce(notModified()).mockResolvedValueOnce(json(manifest));
    const client = api();
    const got = await loadContractValue(etag => loadManifests(client, undefined, etag), '"same"', () => undefined);
    expect(got.value.modules.ai?.screens.chat?.type).toBe('native');
    expect(got.value.modules.core).toBeDefined();
    expect(fetchMock).toHaveBeenCalledTimes(2);
  });
  it('forgets a stale validator when the endpoint no longer provides one', async () => {
    await rememberEtag('demo', 'config', '"old"');
    expect(await rememberedEtag('demo', 'config')).toBe('"old"');
    await rememberEtag('demo', 'config', null);
    expect(await rememberedEtag('demo', 'config')).toBeNull();
  });
  it('reports an invalid unconditional 304 instead of using an empty body or retrying forever', async () => {
    fetchMock.mockResolvedValue(notModified());
    const client = api();
    await expect(loadContractValue(etag => loadConfig(client, undefined, etag), '"same"', () => undefined)).rejects.toThrow('without a cached response');
    expect(fetchMock).toHaveBeenCalledTimes(2);
  });
});
