import { describe, expect, it, vi } from 'vitest';
import { createClient } from '@fluxwp/core';
import { flxgGraphQL, graphQLUnavailable, patchToInput, taskFromGraphQL } from '../src/adapters/flx-tasks';
import { FlxGraphQLError } from '../src/errors';
import type { FlxTaskRowFragment } from '../src/generated/types';

const json = (status: number, body: unknown) => new Response(JSON.stringify(body), { status, headers: { 'content-type': 'application/json' } });

const row: FlxTaskRowFragment = {
  databaseId: 7, title: 'Pilot', kind: 'AGENDA', status: 'open', priority: 2, listId: 3, parentId: null, startDate: null, dueDate: '2026-12-01',
  position: 1.5, creatorId: 1, createdAt: { iso: '2026-11-15T10:00:00Z' }, updatedAt: null, completedAt: null, assigneeIds: [1], tagIds: [],
  fieldValues: { '4': 'x' }, commentCount: 2, fileCount: 0, subtaskCount: 1, agendaCount: 0, decisionCount: 3, coverFileId: null, archived: false, hasDescription: true,
};

function setup(...responses: Response[]) {
  const f = vi.fn<typeof fetch>();
  for (const r of responses) f.mockResolvedValueOnce(r);
  const api = createClient({ siteUrl: 'https://x.org', fetchImpl: f, getCredentials: async () => ({ token: 't' }) });
  return { f, tasks: flxgGraphQL(api) };
}

describe('Flux Tasks over GraphQL', () => {
  it('maps a GraphQL task to the REST shape the engine uses', () => {
    expect(taskFromGraphQL(row)).toEqual({
      id: 7, list: 3, parent: 0, kind: 'agenda', title: 'Pilot', status: 'open', priority: 2, start: '', due: '2026-12-01', cover: 0, pos: 1.5,
      creator: 1, created: '2026-11-15T10:00:00Z', updated: null, completed: null, assignees: [1], tags: [], fields: { '4': 'x' },
      comments: 2, files: 0, subtasks: 1, archived: 0, hasDesc: true, decisions: 3,
    });
  });

  it('turns REST patches into typed input, or refuses keys without one', () => {
    expect(patchToInput({ title: 'A', due: '', assignees: ['2'], kind: 'decision', detail: true })).toEqual({ title: 'A', dueDate: null, assigneeIds: [2], kind: 'DECISION' });
    expect(patchToInput({ position: 3 })).toBeNull();
    expect(patchToInput({ fields: { 1: 'x' } })).toBeNull();
  });

  it('updates through GraphQL (POST, typed input)', async () => {
    const { f, tasks } = setup(json(200, { data: { flxTaskUpdate: { task: row, errors: [] } } }));
    const r = await tasks.update(7, { title: 'Pilot' });
    expect(r.task.id).toBe(7);
    const [url, init] = f.mock.calls[0]!;
    expect(new URL(String(url)).pathname).toBe('/graphql');
    expect(JSON.parse(String(init?.body)).variables).toEqual({ input: { title: 'Pilot', id: '7' } });
    expect(tasks.transport()).toBe('graphql');
  });

  it('throws payload errors like a REST error', async () => {
    const { tasks } = setup(json(200, { data: { flxTaskUpdate: { task: null, errors: [{ code: 'INVALID_INPUT', message: 'Task name cannot be empty.', field: 'title' }] } } }));
    await expect(tasks.update(7, { title: '' })).rejects.toMatchObject({ code: 'INVALID_INPUT', message: 'Task name cannot be empty.', field: 'title' });
  });

  it('uses REST for keys GraphQL does not cover', async () => {
    const { f, tasks } = setup(json(200, { task: { id: 7 } }));
    await tasks.update(7, { position: 2 });
    expect(new URL(String(f.mock.calls[0]![0])).pathname).toBe('/wp-json/flx-tasks/v1/tasks/7');
  });

  it('falls back to REST for good on a site without GraphQL', async () => {
    const { f, tasks } = setup(
      new Response('<html>not found</html>', { status: 404, headers: { 'content-type': 'text/html' } }),
      json(200, { watchers: [1] }),
      json(200, { watchers: [] }),
    );
    expect(await tasks.watch(7, true)).toEqual({ watchers: [1] });
    expect(tasks.transport()).toBe('rest');
    await tasks.watch(7, false);
    expect(f.mock.calls.map((c) => new URL(String(c[0])).pathname)).toEqual(['/graphql', '/wp-json/flx-tasks/v1/tasks/7/watch', '/wp-json/flx-tasks/v1/tasks/7/watch']);
  });

  it('knows which errors mean "no GraphQL here"', () => {
    expect(graphQLUnavailable(new FlxGraphQLError('X', 200, [{ message: 'Not allowed', extensions: { code: 'QUERY_NOT_ALLOWED' } }]))).toBe(true);
    expect(graphQLUnavailable(new FlxGraphQLError('X', 200, [{ message: 'Cannot query field "flxTask" on type "RootQuery".' }]))).toBe(true);
    expect(graphQLUnavailable(new FlxGraphQLError('X', 200, [{ message: 'No', extensions: { code: 'FORBIDDEN' } }]))).toBe(false);
    expect(graphQLUnavailable(new Error('x'))).toBe(false);
  });
});
