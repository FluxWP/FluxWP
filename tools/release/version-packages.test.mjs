import { test } from 'node:test';
import assert from 'node:assert/strict';
import { parseNote, bump, plan, changelogSection, prependChangelog } from './version-packages.mjs';

const note = (t, f = 'a.md') => parseNote(f, t);

test('parse note and reject bad input', () => {
  const n = note('---\n"@fluxwp/modules": patch\n"@fluxwp/graphql": minor\n---\nBackup screen\n');
  assert.deepEqual(n.bumps, { '@fluxwp/modules': 'patch', '@fluxwp/graphql': 'minor' });
  assert.equal(n.body, 'Backup screen');
  assert.throws(() => note('no front matter'));
  assert.throws(() => note('---\n"@fluxwp/other": patch\n---\nx'));
  assert.throws(() => note('---\n"@fluxwp/modules": huge\n---\nx'));
  assert.throws(() => note('---\n"@fluxwp/modules": patch\n---\n'));
});

test('highest bump wins, one version per release', () => {
  const v = { '@fluxwp/modules': '0.3.18', '@fluxwp/graphql': '0.3.6' };
  const p = plan([
    note('---\n"@fluxwp/modules": patch\n---\nA', 'a.md'),
    note('---\n"@fluxwp/modules": minor\n"@fluxwp/graphql": patch\n---\nB', 'b.md'),
  ], v);
  assert.equal(p['@fluxwp/modules'].to, '0.4.0');
  assert.equal(p['@fluxwp/graphql'].to, '0.3.7');
  assert.deepEqual(p['@fluxwp/modules'].entries, ['A', 'B']);
  assert.equal(bump('1.2.3', 'major'), '2.0.0');
});

test('changelog section goes above the newest version', () => {
  const out = prependChangelog('# Changelog\n\n## 0.3.6\n- old\n', changelogSection('0.3.7', ['new']));
  assert.equal(out, '# Changelog\n\n## 0.3.7\n- new\n\n## 0.3.6\n- old\n');
});
