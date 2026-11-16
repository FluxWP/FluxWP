import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { buildSchema, lexicographicSortSchema, printSchema } from 'graphql';
import { describe, expect, it } from 'vitest';
import { assembleSdl, CORE_PART, partOwner, partPath, splitSchema } from '../scripts/parts';

const sdl = `
schema { query: RootQuery mutation: RootMutation }
interface Node { id: ID! }
interface ContentNode { id: ID! title: String flxBlocks: FlxBlocks }
type Post implements Node & ContentNode { id: ID! title: String flxBlocks: FlxBlocks flxSeo: FlxSeo }
type Page implements Node & ContentNode { id: ID! title: String flxBlocks: FlxBlocks }
type FlxBlocks { html: String! }
type FlxSeo { title: String! robots: FlxSeoRobots }
enum FlxSeoRobots { INDEX NOINDEX }
type FlxSettings { flxseo: FlxSettingsFlxseo general: String }
type FlxSettingsFlxseo { sitemap: Boolean }
input FlxSettingsUpdateInput { flxseo: FlxSettingsFlxseoInput general: String }
input FlxSettingsFlxseoInput { sitemap: Boolean }
type FlxSettingsUpdatePayload { settings: FlxSettings }
type FlxTask implements Node { id: ID! title: String! }
type FlxTaskUpdatePayload { task: FlxTask }
input FlxTaskUpdateInput { id: ID! }
type FlxTaskHelper { n: Int }
type FlxTaskBox { helper: FlxTaskHelper }
type RootQuery {
  post(id: ID!): Post
  flxTask(id: ID!): FlxTask
  flxTaskBox: FlxTaskBox
  flxSettings: FlxSettings!
}
type RootMutation {
  flxTaskUpdate(input: FlxTaskUpdateInput!): FlxTaskUpdatePayload
  flxSettingsUpdate(input: FlxSettingsUpdateInput!): FlxSettingsUpdatePayload
}
`;

const owners: Record<string, string> = {
  'ContentNode.flxBlocks': 'plugins/flx-blocks',
  FlxBlocks: 'plugins/flx-blocks',
  'Post.flxSeo': 'plugins/flx-seo',
  FlxSeo: 'plugins/flx-seo',
  FlxSeoRobots: 'plugins/flx-seo',
  FlxSettings: 'plugins/flx-graphql',
  'FlxSettings.flxseo': 'plugins/flx-seo',
  FlxSettingsFlxseo: 'plugins/flx-seo',
  FlxSettingsFlxseoInput: 'plugins/flx-seo',
  FlxSettingsUpdateInput: 'plugins/flx-graphql',
  'FlxSettingsUpdateInput.flxseo': 'plugins/flx-seo',
  FlxSettingsUpdatePayload: 'plugins/flx-graphql',
  'RootQuery.flxSettings': 'plugins/flx-graphql',
  'RootMutation.flxSettingsUpdate': 'plugins/flx-graphql',
  FlxTask: 'plugins/flx-tasks',
  FlxTaskUpdatePayload: 'plugins/flx-tasks',
  FlxTaskUpdateInput: 'plugins/flx-tasks',
  FlxTaskBox: 'plugins/flx-tasks',
  'RootQuery.flxTask': 'plugins/flx-tasks',
  'RootQuery.flxTaskBox': 'plugins/flx-tasks',
  'RootMutation.flxTaskUpdate': 'plugins/flx-tasks',
};

const sorted = (s: string) => printSchema(lexicographicSortSchema(buildSchema(s))) + '\n';
const part = (parts: { path: string; body: string }[], path: string) => parts.find((p) => p.path === path)?.body ?? '';

describe('splitSchema', () => {
  const parts = splitSchema(sdl, owners);

  it('writes one part per owner, core first', () => {
    expect(parts.map((p) => p.path)).toEqual([
      CORE_PART,
      'plugins/flx-blocks/graphql/schema.graphql',
      'plugins/flx-graphql/graphql/schema.graphql',
      'plugins/flx-seo/graphql/schema.graphql',
      'plugins/flx-tasks/graphql/schema.graphql',
    ]);
  });

  it('puts fields added to foreign types in an extension of the adding package', () => {
    const seo = part(parts, 'plugins/flx-seo/graphql/schema.graphql');
    expect(seo).toContain('extend type Post {\n  flxSeo: FlxSeo\n}');
    expect(seo).toContain('extend type FlxSettings {\n  flxseo: FlxSettingsFlxseo\n}');
    expect(seo).toContain('extend input FlxSettingsUpdateInput {\n  flxseo: FlxSettingsFlxseoInput\n}');
    const core = part(parts, CORE_PART);
    expect(core).not.toContain('flxSeo');
    expect(core).not.toContain('flxBlocks');
    expect(core).toContain('schema {');
  });

  it('follows interface fields to the implementing types', () => {
    const blocks = part(parts, 'plugins/flx-blocks/graphql/schema.graphql');
    for (const t of ['interface ContentNode', 'type Page', 'type Post']) expect(blocks).toContain(`extend ${t} {\n  flxBlocks: FlxBlocks\n}`);
  });

  it('gives an unowned Flx type to the only package that reaches it', () => {
    expect(part(parts, 'plugins/flx-tasks/graphql/schema.graphql')).toContain('type FlxTaskHelper');
    expect(part(parts, CORE_PART)).not.toContain('FlxTaskHelper');
  });

  it('assembles back to the same schema', () => {
    expect(assembleSdl(parts)).toBe(sorted(sdl));
  });

  it('does not depend on the order of the printed schema', () => {
    expect(splitSchema(sorted(sdl), owners)).toEqual(parts);
  });

  it('rejects owners outside plugins/ and themes/', () => {
    expect(() => splitSchema(sdl, { FlxTask: '../x' })).toThrow(/invalid schema owner/);
  });
});

describe('parts', () => {
  it('maps owners to files and back', () => {
    expect(partPath('plugins/flx-tasks')).toBe('plugins/flx-tasks/graphql/schema.graphql');
    expect(partPath('themes/fluxwp')).toBe('themes/fluxwp/graphql/schema.graphql');
    expect(partPath('')).toBe(CORE_PART);
    expect(partOwner('themes/fluxwp/graphql/schema.graphql')).toBe('themes/fluxwp');
  });

  it('fails on an extension whose type no part defines', () => {
    expect(() => assembleSdl([{ path: CORE_PART, body: 'type RootQuery { a: Int }' }, { path: 'plugins/x/graphql/schema.graphql', body: 'extend type Gone { b: Int }' }])).toThrow();
  });
});

describe('the committed schema', () => {
  it('splits and assembles without loss', () => {
    const root = join(__dirname, '../../..');
    let legacy = '';
    try {
      legacy = readFileSync(join(root, 'packages/graphql/schema/schema.graphql'), 'utf8');
    } catch {
      return; // already split
    }
    const owners: Record<string, string> = { FlxTask: 'plugins/flx-tasks', 'RootQuery.flxTask': 'plugins/flx-tasks' };
    expect(assembleSdl(splitSchema(legacy, owners))).toBe(sorted(legacy));
  });
});
