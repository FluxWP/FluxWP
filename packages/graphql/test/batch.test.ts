import { describe, expect, it, vi } from 'vitest';
import { createClient } from '@fluxwp/core';
import { createGraphQLClient } from '../src/client';
import type { FlxOperation } from '../src/operation';

const op = (name: string, c: string): FlxOperation<Record<string, unknown>, Record<string, unknown>> => ({
  id: c.repeat(64), name, kind: 'query', plugin: 'flx-tasks', document: `query ${name} { __typename }`,
});
const A = op('FlxA', 'a');
const B = op('FlxB', 'b');
const C = op('FlxC', 'c');

const json = (status: number, body: unknown) => new Response(JSON.stringify(body), { status, headers: { 'content-type': 'application/json' } });

function setup(...responses: Response[]) {
  const f = vi.fn<typeof fetch>();
  for (const r of responses) f.mockResolvedValueOnce(r);
  const api = createClient({ siteUrl: 'https://x.org', fetchImpl: f, getCredentials: async () => ({ token: 't' }) });
  return { f, api };
}

describe('GraphQL batching', () => {
  it('sends queries of the same tick as one POST, also from two clients on one api', async () => {
    const { f, api } = setup(json(200, [{ data: { a: 1 } }, { data: { b: 2 } }]));
    const one = createGraphQLClient({ api });
    const two = createGraphQLClient({ api });
    const [ra, rb] = await Promise.all([one.request(A, { x: 1 }), two.request(B)]);
    expect(ra).toEqual({ a: 1 });
    expect(rb).toEqual({ b: 2 });
    expect(f).toHaveBeenCalledTimes(1);
    const init = f.mock.calls[0]![1]!;
    expect(init.method).toBe('POST');
    expect(JSON.parse(String(init.body))).toEqual([
      { queryId: A.id, operationName: 'FlxA', variables: { x: 1 } },
      { queryId: B.id, operationName: 'FlxB' },
    ]);
    expect((init.headers as Record<string, string>)['X-Flux-GraphQL-Operation']).toBe('FlxA,FlxB');
  });

  it('keeps a lone query a GET', async () => {
    const { f, api } = setup(json(200, { data: { a: 1 } }));
    await createGraphQLClient({ api }).request(A);
    expect(f.mock.calls[0]![1]!.method).toBe('GET');
  });

  it('resends one item with its document when the server does not know its id', async () => {
    const { f, api } = setup(
      json(200, [{ data: { a: 1 } }, { errors: [{ message: 'x', extensions: { code: 'PERSISTED_QUERY_NOT_FOUND' } }] }]),
      json(200, { data: { b: 2 } }),
    );
    const gql = createGraphQLClient({ api });
    await expect(Promise.all([gql.request(A), gql.request(B)])).resolves.toEqual([{ a: 1 }, { b: 2 }]);
    expect(JSON.parse(String(f.mock.calls[1]![1]!.body)).query).toBe(B.document);
  });

  it('falls back to single requests when the answer is not a list', async () => {
    const { f, api } = setup(json(200, { data: { a: 1 } }), json(200, { data: { b: 2 } }), json(200, { data: { c: 3 } }));
    const gql = createGraphQLClient({ api });
    await expect(Promise.all([gql.request(A), gql.request(B)])).resolves.toEqual([{ b: 2 }, { c: 3 }]);
    expect(f).toHaveBeenCalledTimes(3);
  });

  it('can be turned off', async () => {
    const { f, api } = setup(json(200, { data: { a: 1 } }), json(200, { data: { c: 3 } }));
    const gql = createGraphQLClient({ api, batch: false });
    await Promise.all([gql.request(A), gql.request(C)]);
    expect(f).toHaveBeenCalledTimes(2);
  });
});
