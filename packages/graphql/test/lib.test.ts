import { buildSchema, parse, print } from 'graphql';
import { describe, expect, it } from 'vitest';
import { collectOperations, manifests, operationsModule, sha256, typeNames } from '../scripts/lib';

const schema = buildSchema(`
  type Query { flxTask(id: ID!): FlxTask, flxTasks(first: Int): [FlxTask!]! }
  type Mutation { flxTaskSave(input: FlxTaskSaveInput!): FlxTaskSavePayload }
  input FlxTaskSaveInput { title: String! }
  type FlxTaskSavePayload { task: FlxTask, errors: [FlxUserError!]! }
  type FlxUserError { code: String!, message: String!, field: String }
  type FlxTask { id: ID!, title: String!, owner: FlxTask }
`);

const file = (body: string, plugin = 'flx-tasks', path = 'plugins/flx-tasks/graphql/operations/a.graphql') => ({ plugin, path, body });

describe('collectOperations', () => {
  it('attaches used fragments and hashes the printed document', () => {
    const ops = collectOperations(schema, [
      file('query FlxTask($id: ID!) { flxTask(id: $id) { ...TaskBits } }'),
      file('fragment TaskBits on FlxTask { id title owner { ...Owner } }\nfragment Owner on FlxTask { id }\nfragment Unused on FlxTask { title }', 'flx-tasks', 'plugins/flx-tasks/graphql/operations/f.graphql'),
    ]);
    expect(ops).toHaveLength(1);
    const op = ops[0]!;
    expect(op.kind).toBe('query');
    expect(op.document).toContain('fragment TaskBits');
    expect(op.document).toContain('fragment Owner');
    expect(op.document).not.toContain('Unused');
    expect(op.id).toBe(sha256(op.document));
    // Whitespace does not change the id: the document is printed canonically.
    const again = collectOperations(schema, [
      file('query   FlxTask($id: ID!) {\n flxTask(id: $id) { ...TaskBits }\n}'),
      file('fragment TaskBits on FlxTask { id title owner { ...Owner } } fragment Owner on FlxTask { id }', 'flx-tasks', 'x.graphql'),
    ]);
    expect(again[0]!.id).toBe(op.id);
    expect(print(parse(op.document))).toBe(op.document);
  });

  it('refuses invalid, unnamed, duplicate and badly named operations', () => {
    expect(() => collectOperations(schema, [file('query { flxTasks { id } }')])).toThrow(/needs a name/);
    expect(() => collectOperations(schema, [file('query FlxX { nope }')])).toThrow(/Cannot query field "nope"/);
    expect(() => collectOperations(schema, [file('query tasks { flxTasks { id } }')])).toThrow(/start with "Flx"/);
    expect(() => collectOperations(schema, [file('query FlxA { flxTasks { id } }'), file('query FlxA { flxTasks { title } }', 'flx-crm', 'b.graphql')])).toThrow(/also defined/);
    expect(() => collectOperations(schema, [file('query FlxA { flxTasks { ...Other } }'), file('fragment Other on FlxTask { id }', 'flx-crm', 'b.graphql')])).toThrow(/not defined in flx-tasks/);
    expect(() => collectOperations(schema, [file('subscription FlxS { flxTasks { id } }')])).toThrow(/subscriptions/);
  });

  it('splits manifests per plugin and generates typed constants', () => {
    const ops = collectOperations(schema, [
      file('query FlxTasks { flxTasks(first: 5) { id } }'),
      file('mutation FlxTaskSave($input: FlxTaskSaveInput!) { flxTaskSave(input: $input) { task { id } errors { code message field } } }'),
      file('query FlxCrmPeopleQuery { flxTasks { title } }', 'flx-crm', 'plugins/flx-crm/graphql/operations/p.graphql'),
    ]);
    const m = manifests(ops);
    expect([...m.keys()].sort()).toEqual(['flx-crm', 'flx-tasks']);
    const tasks = JSON.parse(m.get('flx-tasks')!) as Record<string, string>;
    expect(Object.keys(tasks)).toHaveLength(2);
    for (const [id, doc] of Object.entries(tasks)) expect(sha256(doc)).toBe(id);
    expect(typeNames({ name: 'FlxCrmPeopleQuery', kind: 'query' })).toEqual({ result: 'FlxCrmPeopleQuery', variables: 'FlxCrmPeopleQueryVariables' });
    expect(typeNames({ name: 'FlxTaskSave', kind: 'mutation' }).result).toBe('FlxTaskSaveMutation');
    const mod = operationsModule(ops);
    expect(mod).toContain("export const FlxTaskSave: FlxOperation<T.FlxTaskSaveMutation, T.FlxTaskSaveMutationVariables>");
    expect(mod).toContain("kind: 'mutation'");
  });
});
