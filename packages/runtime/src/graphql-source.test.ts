import { describe, expect, it } from 'vitest';
import { createClient } from '@fluxwp/core';
import type { FormScreen, Source } from '@fluxwp/manifest';
import { runRequest, runRequestRaw } from './api';
import { actionRequest, formRequest, GRAPHQL_PATH, ManifestError, parsePage, sourceRequest } from './source';

const ID = 'a'.repeat(64);
const pages: Source = {
  graphql: { op: 'FlxWikiPages', id: ID, variables: { section: '{{params.section}}', statuses: ['publish'] }, data: 'flxWikiPages' },
  paging: { mode: 'cursor', size: 20, items: 'nodes', next: 'pageInfo.endCursor', has_next: 'pageInfo.hasNextPage', total: 'totalCount' },
};

describe('GraphQL manifest sources', () => {
  it('plans a persisted query: paging, search and filters are variables', () => {
    const r = sourceRequest(pages, { params: { section: '' } }, { cursor: 'c1', search: { param: 'search', value: ' kur ' }, filters: { status: null } });
    expect(r.path).toBe(GRAPHQL_PATH);
    expect(r.method).toBe('GET');
    expect(r.graphql).toEqual({ id: ID, name: 'FlxWikiPages', kind: 'query', data: 'flxWikiPages', variables: { statuses: ['publish'], search: 'kur', after: 'c1', first: 20 } });
    expect(r.query.op).toBe('FlxWikiPages');
  });

  it('needs the queryId the server fills', () => {
    expect(() => sourceRequest({ graphql: { op: 'X' } }, {})).toThrow(ManifestError);
    expect(() => sourceRequest({}, {})).toThrow(ManifestError);
  });

  it('cursor paging stops on hasNextPage false', () => {
    const body = { nodes: [{ id: 1 }], pageInfo: { endCursor: 'c2', hasNextPage: false }, totalCount: 1 };
    expect(parsePage(pages, body, 1)).toEqual({ items: [{ id: 1 }], page: 1, total: 1, next: null });
    expect(parsePage(pages, { ...body, pageInfo: { endCursor: 'c2', hasNextPage: true } }, 1).next).toBe('c2');
  });

  it('actions and forms are mutations; form values land in the input variable', () => {
    const a = actionRequest({ graphql: { op: 'FlxWikiPageTrash', id: ID, variables: { id: '{{id}}' } } }, { scope: { id: 7 } });
    expect(a.method).toBe('POST');
    expect(a.graphql?.kind).toBe('mutation');
    const form = { type: 'form', fields: [], submit: { graphql_create: { op: 'Create', id: ID }, graphql_update: { op: 'Save', id: ID, variables: { id: '{{params.id}}' }, values: 'page' } } } as unknown as FormScreen;
    const upd = formRequest(form, { title: 'A', 'meta.x': 1 }, { params: { id: '5' } });
    expect(upd.graphql?.name).toBe('Save');
    expect(upd.graphql?.variables).toEqual({ id: '5', page: { title: 'A', meta: { x: 1 } } });
    expect(formRequest(form, { title: 'B' }, { params: {} }).graphql?.variables).toEqual({ input: { title: 'B' } });
  });

  it('runs over /graphql with queryId only and unwraps data or the first error', async () => {
    const seen: { url: string; init?: RequestInit }[] = [];
    const reply = (b: unknown, status = 200) => new Response(JSON.stringify(b), { status, headers: { 'content-type': 'application/json' } });
    let next: Response = reply({ data: { flxWikiPages: { nodes: [{ id: 1 }] } } });
    const api = createClient({ siteUrl: 'https://s.test', getCredentials: async () => ({ token: 't' }), fetchImpl: (async (url: string, init?: RequestInit) => (seen.push({ url, init }), next)) as typeof fetch });
    const r = sourceRequest(pages, { params: {} });
    expect(await runRequest(api, r)).toEqual({ nodes: [{ id: 1 }] });
    expect(seen[0]!.url).toContain('https://s.test/graphql?');
    expect(seen[0]!.url).toContain(`queryId=${ID}`);
    expect(seen[0]!.url).not.toContain('query=');

    next = reply({ errors: [{ message: 'No', extensions: { code: 'FORBIDDEN' } }] });
    await expect(runRequestRaw(api, r)).rejects.toMatchObject({ name: 'ApiError', code: 'FORBIDDEN', message: 'No' });

    next = reply({ data: { save: { page: null, errors: [{ code: 'required', message: 'Title?', field: 'title' }] } } });
    await expect(runRequest(api, actionRequest({ graphql: { op: 'S', id: ID, data: 'save' } }, {}))).rejects.toMatchObject({ status: 422, code: 'required', data: { params: { title: 'Title?' } } });

    next = reply({ data: { ok: true } });
    await runRequest(api, actionRequest({ graphql: { op: 'M', id: ID, variables: { id: 3 } } }, {}));
    expect(seen[3]!.init?.method).toBe('POST');
    expect(JSON.parse(String(seen[3]!.init?.body))).toEqual({ queryId: ID, operationName: 'M', variables: { id: 3 } });
  });
});
