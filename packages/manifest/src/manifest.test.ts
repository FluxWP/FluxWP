import { describe, expect, it } from 'vitest';
import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import config from '../fixtures/config.json';
import bundle from '../fixtures/manifest.json';
import bootstrap from '../fixtures/bootstrap.json';
import { CORE_MANIFEST } from './core-manifest';
import { compile, fill, render, test as cond, timeAgo } from './expr';
import { formatTarget, matchRoute, parseTarget, safeAdminPath } from './target';
import { DEFAULT_CONFIG, mergeConfig, normalizeShell } from './config';
import { parseBootstrap, parseConfig, parseManifest, parseManifestBundle, type ParseIssue } from './schema';

describe('templates', () => {
  const ctx = { scope: { name: 'Ayşe Kaya', email: '', phone: '+90 532 000 00 01', ltv: 1500.5, currency: 'TRY', roles: [{ label: 'Bağışçı' }], consent: { email: true } }, params: { id: '7' }, locale: 'tr-TR' };

  it('renders paths, filters and literals', () => {
    expect(render('{{name}}', ctx)).toBe('Ayşe Kaya');
    expect(render('{{name|initials}}', ctx)).toBe('AK');
    expect(render('{{email|default:phone}}', ctx)).toBe('+90 532 000 00 01');
    expect(render("{{missing|default:'—'}}", ctx)).toBe('—');
    expect(render('{{phone|phone}}', ctx)).toBe('905320000001');
    expect(render("{{consent.email|bool:'Açık':'Kapalı'}}", ctx)).toBe('Açık');
    expect(render('#{{params.id}} {{roles|count}}', ctx)).toBe('#7 1');
    expect(render('{{ltv|money:currency}}', ctx)).toMatch(/1\.500,50/);
    expect(render('plain text', ctx)).toBe('plain text');
  });

  it('keeps the type of a whole-value template', () => {
    expect(compile('{{ltv}}')(ctx)).toBe(1500.5);
    expect(fill({ id: '{{params.id}}', n: '{{ltv}}', t: 'x {{name}}' }, ctx)).toEqual({ id: '7', n: 1500.5, t: 'x Ayşe Kaya' });
    expect(compile('{{params.id|int}}')(ctx)).toBe(7);
    expect(compile('{{name|int}}')(ctx)).toBeUndefined();
  });

  it('formats relative time in the user language', () => {
    expect(timeAgo(Date.now() - 31 * 60_000, 'en')).toBe('31 minutes ago');
    expect(timeAgo(Date.now() - 31 * 60_000, 'tr')).toBe('31 dakika önce');
  });

  it('evaluates conditions', () => {
    expect(cond({ path: 'ltv', op: 'gte', value: 1000 }, ctx)).toBe(true);
    expect(cond({ all: [{ path: 'email', op: 'empty' }, { not: { path: 'phone', op: 'empty' } }] }, ctx)).toBe(true);
    expect(cond({ any: [{ path: 'name', op: 'eq', value: 'x' }, { path: 'params.id', op: 'in', value: ['7'] }] }, ctx)).toBe(true);
    expect(cond(undefined, ctx)).toBe(true);
  });
});

describe('targets', () => {
  it('parses every kind and round-trips', () => {
    for (const t of ['screen:crm/contact', 'module:flxc-inbox', 'menu:edit.php?post_type=page', 'web:admin.php?page=flx-crm', 'url:https://wa.me/90', 'native:dashboard', 'more', 'settings']) {
      expect(formatTarget(parseTarget(t)!)).toBe(t);
    }
    expect(parseTarget('mod:flxb-review')).toEqual({ kind: 'module', module: 'flxb-review' });
  });

  it('rejects unsafe targets', () => {
    expect(parseTarget('web:https://evil.example')).toBeNull();
    expect(parseTarget('web://evil.example')).toBeNull();
    expect(parseTarget('url:javascript:alert(1)')).toBeNull();
    expect(parseTarget('url:http://plain.example')).toBeNull();
    expect(safeAdminPath('../wp-config.php')).toBeNull();
    expect(safeAdminPath('/wp-admin/post.php?post=5&action=edit')).toBe('post.php?post=5&action=edit');
  });

  it('matches manifest routes with captures, any query order', () => {
    expect(matchRoute('admin.php?page=flx-crm-contacts&id={id}', 'admin.php?id=12&page=flx-crm-contacts')).toEqual({ id: '12' });
    expect(matchRoute('admin.php?page=flx-crm', 'admin.php?page=flx-mail')).toBeNull();
  });
});

describe('schemas', () => {
  it('accepts the fixtures and the built-in core manifest', () => {
    const issues: ParseIssue[] = [];
    expect(parseConfig(config, issues)).not.toBeNull();
    expect(Object.keys(parseManifestBundle(bundle, issues).modules)).toEqual(['crm']);
    expect(parseManifest(CORE_MANIFEST, issues)).not.toBeNull();
    expect(parseBootstrap(bootstrap)).not.toBeNull();
    expect(issues).toEqual([]);
  });

  it('drops a broken module, keeps the rest', () => {
    const broken = { ...bundle.modules.crm, id: 'bad', entry: 'nope' };
    const issues: ParseIssue[] = [];
    const out = parseManifestBundle({ modules: { crm: bundle.modules.crm, bad: broken } }, issues);
    expect(Object.keys(out.modules)).toEqual(['crm']);
    expect(issues.some((i) => i.message.includes('entry'))).toBe(true);
  });

  it('rejects absolute source paths and a sixth tab', () => {
    const m = structuredClone(bundle.modules.crm) as unknown as { sources: { stats: { path: string } } };
    m.sources.stats.path = 'https://evil.example/x';
    expect(parseManifest(m)).toBeNull();
    const c = structuredClone(config) as unknown as { shell: { tabs: unknown[] } };
    c.shell.tabs = [...c.shell.tabs, { id: 'x', target: 'more' }];
    expect(parseConfig(c)).toBeNull();
  });

  it('committed JSON Schemas are current', () => {
    const dir = join(__dirname, '../schema');
    expect(readdirSync(dir).sort()).toEqual(['bootstrap.schema.json', 'brand.schema.json', 'config.schema.json', 'manifest-bundle.schema.json', 'manifest.schema.json']);
    expect(JSON.parse(readFileSync(join(dir, 'manifest.schema.json'), 'utf8')).properties.schema.const).toBe('flx-app-manifest/1');
  });
});

describe('config layers', () => {
  it('site overrides default, user and device on top, brand locks win', () => {
    const merged = mergeConfig({
      brand: { theme: { light: { primary: '#1F7A3A' } }, locked: ['theme.light.primary'] },
      site: { ...DEFAULT_CONFIG, theme: { light: { primary: '#000000' } } },
      device: { theme: { mode: 'dark' } },
    });
    expect(merged.theme?.light?.primary).toBe('#1F7A3A');
    expect(merged.theme?.mode).toBe('dark');
  });

  it('does not assign __proto__, constructor or prototype from a locked path', () => {
    const brand = { locked: ['__proto__.polluted', 'constructor.prototype.polluted'], __proto__: { polluted: true } };
    mergeConfig({ brand: brand as unknown as { locked: string[] } });
    expect(({} as { polluted?: boolean }).polluted).toBeUndefined();
    expect(Object.prototype).not.toHaveProperty('polluted');
  });

  it('guarantees "more" when there is no drawer', () => {
    const s = normalizeShell({ ...DEFAULT_CONFIG.shell, nav: 'tabs', tabs: DEFAULT_CONFIG.shell.tabs.filter((t) => t.target !== 'more') });
    expect(s.tabs.at(-1)?.target).toBe('more');
    expect(s.tabs.length).toBeLessThanOrEqual(5);
  });
});

describe('chart section', () => {
  it('accepts both chart dialects the plugins send', async () => {
    const { SectionSchema } = await import('./schema');
    const v = await import('valibot');
    for (const s of [
      { type: 'chart', chart: 'bar', path: 'series', series: [{ label: '{{label}}', value: '{{revenue}}' }] },
      { type: 'chart', chart: 'bar', path: 'series.items', x: 'd', series: [{ key: 'visitors', label: 'Visitors' }] },
      { type: 'chart', chart: 'bar', path: 'per_minute' },
    ]) expect(v.safeParse(SectionSchema, s).success).toBe(true);
  });
});

describe('plugin manifests (plugins/*/app/manifest.json)', () => {
  const root = join(__dirname, '../../../plugins');
  const files = readdirSync(root).map((p) => join(root, p, 'app/manifest.json')).filter((f) => { try { return !!readFileSync(f); } catch { return false; } });

  it.each(files)('%s parses', (f) => {
    const issues: ParseIssue[] = [];
    const m = parseManifest({ schema: 'flx-app-manifest/1', ...JSON.parse(readFileSync(f, 'utf8')) }, issues);
    expect(issues).toEqual([]);
    expect(m).not.toBeNull();
  });

  it('GraphQL operations named in manifests exist in the plugin', () => {
    for (const f of files) {
      const refs: { op: string; vars: string[] }[] = [];
      const walk = (n: unknown, key = ''): void => {
        if (Array.isArray(n)) n.forEach((x) => walk(x));
        else if (n && typeof n === 'object') {
          const r = n as { op?: unknown; variables?: Record<string, unknown>; values?: string };
          if (key.startsWith('graphql') && typeof r.op === 'string') refs.push({ op: r.op, vars: [...Object.keys(r.variables ?? {}), ...(r.values ? [r.values] : [])] });
          for (const [k, x] of Object.entries(n)) walk(x, k);
        }
      };
      walk(JSON.parse(readFileSync(f, 'utf8')));
      if (!refs.length) continue;
      const docs = Object.values(JSON.parse(readFileSync(join(f, '../../graphql/persisted-queries.json'), 'utf8')) as string[]);
      for (const { op, vars } of refs) {
        const doc = docs.find((d) => new RegExp(`\\b(query|mutation) ${op}\\b`).test(d));
        expect(doc, `${f}: ${op}`).toBeTruthy();
        // Variables the manifest sets must be declared by the operation.
        const head = doc!.slice(0, doc!.indexOf('{'));
        for (const name of vars) expect(head, `${f}: ${op} ${name}`).toContain(`$${name}:`);
      }
    }
  });
});
