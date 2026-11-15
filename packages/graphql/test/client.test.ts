import { describe, expect, it, vi } from 'vitest';
import { createClient } from '@fluxwp/core';
import { createGraphQLClient } from '../src/client';
import { FlxGraphQLError } from '../src/errors';
import type { FlxOperation } from '../src/operation';

const Tasks: FlxOperation<{ flxTasks: { id: string }[] }, { first?: number | null }> = {
  id: 'a'.repeat(64), name: 'FlxTasks', kind: 'query', plugin: 'flx-tasks', document: 'query FlxTasks($first: Int) { flxTasks(first: $first) { id } }',
};
const Save: FlxOperation<{ flxTaskSave: { errors: { code: string; message: string; field: string | null }[] } }, { input: { title: string } }> = {
  id: 'b'.repeat(64), name: 'FlxTaskSave', kind: 'mutation', plugin: 'flx-tasks', document: 'mutation FlxTaskSave($input: FlxTaskSaveInput!) { flxTaskSave(input: $input) { errors { code message field } } }',
};

const json = (status: number, body: unknown) => new Response(JSON.stringify(body), { status, headers: { 'content-type': 'application/json' } });

function setup(...responses: Response[]) {
  const f = vi.fn<typeof fetch>();
  for (const r of responses) f.mockResolvedValueOnce(r);
  const api = createClient({ siteUrl: 'https://x.org/blog', fetchImpl: f, getCredentials: async () => ({ token: 't' }) });
  return { f, gql: createGraphQLClient({ api }) };
}

describe('GraphQL client', () => {
  it('reads with a persisted GET next to wp-json', async () => {
    const { f, gql } = setup(json(200, { data: { flxTasks: [{ id: '1' }] } }));
    const r = await gql.request(Tasks, { first: 5 });
    expect(r.flxTasks[0]?.id).toBe('1');
    const [url, init] = f.mock.calls[0]!;
    const u = new URL(String(url));
    expect(u.pathname).toBe('/blog/graphql');
    expect(init?.method).toBe('GET');
    expect(u.searchParams.get('queryId')).toBe(Tasks.id);
    expect(u.searchParams.get('variables')).toBe('{"first":5}');
    expect(u.searchParams.get('_locale')).toBe('user');
    expect((init?.headers as Record<string, string>).Authorization).toBe('Bearer t');
  });

  it('writes with POST and returns payload errors as data', async () => {
    const { f, gql } = setup(json(200, { data: { flxTaskSave: { errors: [{ code: 'INVALID_INPUT', message: 'Title?', field: 'title' }] } } }));
    const r = await gql.request(Save, { input: { title: '' } });
    expect(r.flxTaskSave.errors[0]?.field).toBe('title');
    const init = f.mock.calls[0]![1]!;
    expect(init.method).toBe('POST');
    expect(JSON.parse(String(init.body))).toEqual({ queryId: Save.id, operationName: 'FlxTaskSave', variables: { input: { title: '' } } });
  });

  it('sends the document once when the server does not know the id', async () => {
    const { f, gql } = setup(
      json(200, { errors: [{ message: 'unknown', extensions: { code: 'PERSISTED_QUERY_NOT_FOUND' } }] }),
      json(200, { data: { flxTasks: [] } }),
    );
    await expect(gql.request(Tasks)).resolves.toEqual({ flxTasks: [] });
    expect(JSON.parse(String(f.mock.calls[1]![1]!.body)).query).toBe(Tasks.document);
  });

  it('throws typed errors', async () => {
    const forbidden = setup(json(200, { data: { flxTasks: null }, errors: [{ message: 'No', path: ['flxTasks'], extensions: { code: 'FORBIDDEN' } }] }));
    await expect(forbidden.gql.request(Tasks)).rejects.toMatchObject({ name: 'FlxGraphQLError', code: 'FORBIDDEN', operation: 'FlxTasks' });

    const notAllowed = setup(json(400, { errors: [{ message: 'Not allowed', extensions: { code: 'QUERY_NOT_ALLOWED' } }] }));
    await expect(notAllowed.gql.request(Tasks)).rejects.toMatchObject({ code: 'QUERY_NOT_ALLOWED', status: 400 });

    const html = setup(new Response('<html>', { status: 502 }));
    const e = await html.gql.request(Tasks).catch((x: unknown) => x);
    expect(e).toBeInstanceOf(FlxGraphQLError);
    expect((e as FlxGraphQLError).code).toBe('BAD_RESPONSE');
  });

  it('keeps partial data when only a nested field failed', async () => {
    const { gql } = setup(json(200, { data: { flxTasks: [{ id: '1' }] }, errors: [{ message: 'hidden', path: ['flxTasks', 0, 'owner'] }] }));
    await expect(gql.request(Tasks)).resolves.toEqual({ flxTasks: [{ id: '1' }] });
  });
});
