// Flux Tasks over GraphQL (pilot). Same API object as core's REST `flxg(api)`, so the task engine and
// every screen (wp-admin via wp-embed, FluxApp desktop/web, mobile) switch by changing one import.
// The task screen, create / update / delete, follow and comment go through the generated, typed
// operations; the rest (board bulk read, sync, views, files, AI) stays on REST.
//
// Older sites (no Flux GraphQL, or Flux Tasks before 0.9.0) answer REST only: the first sign of that
// switches this client to REST for good, so FluxApp keeps working with every site version.
import { flxg, type ApiClient, type FlxgApi, type FlxgComment, type FlxgFile, type FlxgRole, type FlxgTask, type FlxgTaskDetail } from '@fluxwp/core';
import { createGraphQLClient, type GraphQLClient } from '../client';
import { FlxGraphQLError, isFlxGraphQLError } from '../errors';
import {
  FlxTaskCommentCreate,
  FlxTaskCreate,
  FlxTaskDelete,
  FlxTaskDetail,
  FlxTaskNotificationsRead,
  FlxTaskUpdate,
  FlxTaskUpdateDetail,
  FlxTaskWatch,
} from '../generated/operations';
import type {
  FlxTaskCommentRowFragment,
  FlxTaskCreateInput,
  FlxTaskFileRowFragment,
  FlxTaskRowFragment,
  FlxTaskScreenFragment,
  FlxTaskUpdateInput,
} from '../generated/types';

const iso = (t: { iso: string } | null): string | null => t?.iso ?? null;

export function taskFromGraphQL(t: FlxTaskRowFragment): FlxgTask {
  const out: FlxgTask = {
    id: t.databaseId,
    list: t.listId,
    parent: t.parentId ?? 0,
    kind: t.kind.toLowerCase(),
    title: t.title,
    status: t.status,
    priority: t.priority,
    start: t.startDate ?? '',
    due: t.dueDate ?? '',
    cover: t.coverFileId ?? 0,
    pos: t.position,
    creator: t.creatorId,
    created: iso(t.createdAt),
    updated: iso(t.updatedAt),
    completed: iso(t.completedAt),
    assignees: t.assigneeIds,
    tags: t.tagIds,
    fields: t.fieldValues && typeof t.fieldValues === 'object' ? (t.fieldValues as Record<string, unknown>) : {},
    comments: t.commentCount,
    files: t.fileCount,
    subtasks: t.subtaskCount,
    archived: t.archived ? 1 : 0,
    hasDesc: t.hasDescription,
  };
  if (t.agendaCount) out.agenda = t.agendaCount;
  if (t.decisionCount) out.decisions = t.decisionCount;
  return out;
}

export function fileFromGraphQL(f: FlxTaskFileRowFragment): FlxgFile {
  return {
    id: f.databaseId,
    task: f.taskId,
    comment: f.commentId ?? 0,
    field: f.fieldId ?? 0,
    context: f.context,
    user: f.uploaderId,
    name: f.name,
    mime: f.mime,
    size: f.size,
    image: f.image,
    thumb: f.hasThumb ? '1' : '',
    w: f.width,
    h: f.height,
    created: iso(f.createdAt),
  };
}

export function commentFromGraphQL(c: FlxTaskCommentRowFragment): FlxgComment {
  return {
    id: c.databaseId,
    task: c.taskId,
    user: c.authorId,
    body: c.body,
    created: iso(c.createdAt) ?? '',
    edited: iso(c.editedAt),
    files: c.files.map(fileFromGraphQL),
  };
}

export function detailFromGraphQL(t: FlxTaskScreenFragment): FlxgTaskDetail {
  const childFiles: Record<number, FlxgFile[]> = {};
  for (const g of t.childFiles) childFiles[g.taskId] = g.files.map(fileFromGraphQL);
  return {
    ...taskFromGraphQL(t),
    description: t.description,
    comments_list: t.comments.map(commentFromGraphQL),
    activity: t.activity.map((a) => ({
      id: a.databaseId,
      user: a.userId,
      action: a.action,
      data: a.data && typeof a.data === 'object' ? (a.data as Record<string, unknown>) : {},
      created: iso(a.createdAt) ?? '',
    })),
    attachments: t.attachments.map(fileFromGraphQL),
    watchers: t.watcherIds,
    watching: t.watching,
    children: t.children.map((c) => ({ ...taskFromGraphQL(c), description: c.description })),
    subtree: t.subtree.map(taskFromGraphQL),
    role: (t.role?.toLowerCase() ?? 'view') as FlxgRole,
    parentInfo: t.parent ? { id: t.parent.databaseId, title: t.parent.title } : null,
    depth: t.depth,
    depthLimit: t.depthLimit,
    childFiles,
  };
}

/** REST patch keys the typed input covers; any other key (position, fields, cover …) goes over REST. */
const PATCH: Record<string, keyof FlxTaskUpdateInput> = {
  title: 'title',
  description: 'description',
  status: 'status',
  priority: 'priority',
  start: 'startDate',
  due: 'dueDate',
  assignees: 'assigneeIds',
  tags: 'tagIds',
  list: 'listId',
  parent: 'parentId',
  archived: 'archived',
  kind: 'kind',
};

const ints = (v: unknown): number[] => (Array.isArray(v) ? v.map(Number).filter((n) => Number.isFinite(n)) : []);

/** REST-shaped patch → typed input; null when some key has no typed counterpart. */
export function patchToInput(patch: Record<string, unknown>): Omit<FlxTaskUpdateInput, 'id'> | null {
  const out: Omit<FlxTaskUpdateInput, 'id'> = {};
  for (const [k, v] of Object.entries(patch)) {
    if (k === 'detail' || v === undefined) continue;
    const to = PATCH[k];
    if (!to) return null;
    switch (to) {
      case 'title':
      case 'description':
      case 'status':
        out[to] = String(v);
        break;
      case 'startDate':
      case 'dueDate':
        out[to] = v ? String(v) : null;
        break;
      case 'priority':
      case 'listId':
      case 'parentId':
        out[to] = Number(v) || 0;
        break;
      case 'assigneeIds':
      case 'tagIds':
        out[to] = ints(v);
        break;
      case 'archived':
        out.archived = !!v;
        break;
      case 'kind':
        out.kind = v === 'agenda' ? 'AGENDA' : v === 'decision' ? 'DECISION' : 'TASK';
        break;
    }
  }
  return out;
}

/** The site has no GraphQL for this (no Flux GraphQL, older Flux Tasks): use REST. */
export function graphQLUnavailable(e: unknown): boolean {
  if (!isFlxGraphQLError(e)) return false;
  if (e.code === 'QUERY_NOT_ALLOWED' || e.code === 'PERSISTED_QUERY_NOT_FOUND') return true;
  if (e.status === 404 && !e.errors[0]?.extensions?.code) return true;
  return e.errors.some((x) => /Cannot query field|Unknown type|Unknown argument|Cannot return null for non-nullable field "RootMutation/.test(x.message));
}

/** Mutation payload errors (INVALID_INPUT, CONFLICT) → thrown like a REST error. */
function check(op: string, payload: { errors: ReadonlyArray<{ code: string; message: string; field: string | null }> } | null): void {
  const first = payload?.errors[0];
  if (!first) return;
  throw new FlxGraphQLError(op, 400, payload.errors.map((x) => ({ message: x.message, extensions: { code: x.code, ...(x.field ? { field: x.field } : {}) } })));
}

export interface FlxgGraphQLOptions {
  /** Ready GraphQL client (tests); default: one built on `api`. */
  graphql?: GraphQLClient;
}

/** Flux Tasks API over GraphQL with REST fallback. Drop-in for `flxg(api)`. */
export function flxgGraphQL(api: ApiClient, o: FlxgGraphQLOptions = {}): FlxgApi & { readonly transport: () => 'graphql' | 'rest' } {
  const rest = flxg(api);
  const gql = o.graphql ?? createGraphQLClient({ api });
  let useRest = false;

  async function via<T>(run: () => Promise<T>, fallback: () => Promise<T>): Promise<T> {
    if (useRest) return fallback();
    try {
      return await run();
    } catch (e) {
      if (!graphQLUnavailable(e)) throw e;
      useRest = true;
      return fallback();
    }
  }

  return {
    ...rest,
    transport: () => (useRest ? 'rest' : 'graphql'),

    task: (id) =>
      via(
        async () => {
          const [r, read] = await Promise.all([
            gql.request(FlxTaskDetail, { id: String(id) }),
            gql.request(FlxTaskNotificationsRead, { input: { taskId: String(id) } }).catch(() => null),
          ]);
          if (!r.flxTask) throw new FlxGraphQLError('FlxTaskDetail', 404, [{ message: 'Task not found or you don\'t have access.', extensions: { code: 'NOT_FOUND' } }]);
          const out: { task: FlxgTaskDetail; unread?: number } = { task: detailFromGraphQL(r.flxTask) };
          if (read?.flxTaskNotificationsRead) out.unread = read.flxTaskNotificationsRead.unread;
          return out;
        },
        () => rest.task(id),
      ),

    create: (data) => {
      const input = patchToInput(data);
      const listId = Number(data.list) || 0;
      if (!input || !listId) return rest.create(data);
      const full: FlxTaskCreateInput = { ...input, listId };
      return via(
        async () => {
          const r = await gql.request(FlxTaskCreate, { input: full });
          check('FlxTaskCreate', r.flxTaskCreate);
          if (!r.flxTaskCreate?.task) throw new FlxGraphQLError('FlxTaskCreate', 500, [], 'BAD_RESPONSE');
          return { task: taskFromGraphQL(r.flxTaskCreate.task) };
        },
        () => rest.create(data),
      );
    },

    update: (id, patch) => {
      const input = patchToInput(patch);
      if (!input) return rest.update(id, patch);
      const full: FlxTaskUpdateInput = { ...input, id: String(id) };
      return via(
        async () => {
          if (patch.detail) {
            const r = await gql.request(FlxTaskUpdateDetail, { input: full });
            check('FlxTaskUpdateDetail', r.flxTaskUpdate);
            if (!r.flxTaskUpdate?.task) throw new FlxGraphQLError('FlxTaskUpdateDetail', 500, [], 'BAD_RESPONSE');
            return { task: detailFromGraphQL(r.flxTaskUpdate.task) };
          }
          const r = await gql.request(FlxTaskUpdate, { input: full });
          check('FlxTaskUpdate', r.flxTaskUpdate);
          if (!r.flxTaskUpdate?.task) throw new FlxGraphQLError('FlxTaskUpdate', 500, [], 'BAD_RESPONSE');
          return { task: taskFromGraphQL(r.flxTaskUpdate.task) };
        },
        () => rest.update(id, patch),
      );
    },

    remove: (id) =>
      via(
        async () => {
          const r = await gql.request(FlxTaskDelete, { input: { id: String(id) } });
          check('FlxTaskDelete', r.flxTaskDelete);
          return { deleted: r.flxTaskDelete?.deletedId ?? id };
        },
        () => rest.remove(id),
      ),

    watch: (id, watch) =>
      via(
        async () => {
          const r = await gql.request(FlxTaskWatch, { input: { id: String(id), watch } });
          check('FlxTaskWatch', r.flxTaskWatch);
          return { watchers: r.flxTaskWatch?.watcherIds ?? [] };
        },
        () => rest.watch(id, watch),
      ),

    comment: (id, body, files) =>
      via(
        async () => {
          const r = await gql.request(FlxTaskCommentCreate, { input: { taskId: String(id), body, fileIds: files } });
          check('FlxTaskCommentCreate', r.flxTaskCommentCreate);
          if (!r.flxTaskCommentCreate?.comment) throw new FlxGraphQLError('FlxTaskCommentCreate', 500, [], 'BAD_RESPONSE');
          return commentFromGraphQL(r.flxTaskCommentCreate.comment);
        },
        () => rest.comment(id, body, files),
      ),
  };
}
