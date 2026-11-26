// Flux AI › Skills: the list, the edit screen (SKILL.md, Files, Tests), import and the brand kit. Everything goes
// over the flxAiSkill* and flxAiBrand* operations.
import { useQueryClient } from '@tanstack/react-query';
import { useState, type ReactNode } from 'react';
import {
  FlxAiSkill,
  FlxAiSkillDelete,
  FlxAiSkillEvalsSave,
  FlxAiSkillFile,
  FlxAiSkillFileDelete,
  FlxAiSkillFileSave,
  FlxAiSkillFileUpload,
  FlxAiSkillSave,
  FlxAiSkills,
  FlxAiSkillsSettings,
  FlxAiSkillTestRun,
  FlxAiSkillToggle,
} from '@fluxwp/graphql/operations';
import type { FlxAiSkillDetailFieldsFragment, FlxAiSkillFieldsFragment } from '@fluxwp/graphql/types';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { Loading, Modal, Tabs } from '@fluxwp/ui';
import { Card, NoticeBar, Pill, errText, useAi, type AiT, type Notice, Lead } from './common';
import { Brand, ImportSkills } from './SkillsExtra';

type Skill = FlxAiSkillFieldsFragment;
type Detail = FlxAiSkillDetailFieldsFragment;
type View = { kind: 'list' } | { kind: 'edit'; slug: string | null; tab: string } | { kind: 'file'; slug: string; path: string } | { kind: 'import' } | { kind: 'brand' };

export function Field({ label, children, hint }: { label: string; children: ReactNode; hint?: string }) {
  return (
    <div className="flxai-field">
      <div className="flxai-field-label">{label}</div>
      <div className="flxai-field-body">
        {children}
        {hint && <p className="flxai-hint">{hint}</p>}
      </div>
    </div>
  );
}

/** First error of a mutation payload, or null when it went through. */
export function firstError(errors: ReadonlyArray<{ message: string; field?: string | null }> | undefined | null): { text: string; field?: string } | null {
  return errors?.length ? { text: errors.map((e) => e.message).join(' '), field: errors[0]?.field ?? undefined } : null;
}

export function toBase64(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const r = new FileReader();
    r.onload = () => resolve(String(r.result).split(',')[1] ?? '');
    r.onerror = () => reject(r.error);
    r.readAsDataURL(file);
  });
}

export function Skills() {
  const [view, setView] = useState<View>({ kind: 'list' });
  const back = () => setView({ kind: 'list' });
  if (view.kind === 'brand') return <Brand onBack={back} />;
  if (view.kind === 'import') return <ImportSkills onBack={back} onDone={back} />;
  if (view.kind === 'edit') return <SkillEditor slug={view.slug} tab={view.tab} go={setView} />;
  if (view.kind === 'file') return <FileEditor slug={view.slug} path={view.path} go={setView} />;
  return <SkillList go={setView} />;
}

function SkillList({ go }: { go: (v: View) => void }) {
  const { t, tn, num } = useAi();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const [notice, setNotice] = useState<Notice | null>(null);
  const [asking, setAsking] = useState<Skill | null>(null);
  const q = useFlxQuery(FlxAiSkills, {});
  const key = flxQueryKey(scope, FlxAiSkills, {});
  const refresh = () => qc.invalidateQueries({ queryKey: key });
  const fail = (e: unknown) => setNotice({ kind: 'error', text: errText(e, t('The action could not be completed.')) });
  const toggle = useFlxMutation(FlxAiSkillToggle, {
    onSuccess: (r) => {
      const e = firstError(r.flxAiSkillToggle?.errors);
      if (e) setNotice({ kind: 'error', text: e.text });
      refresh();
    },
    onError: fail,
  });
  const del = useFlxMutation(FlxAiSkillDelete, {
    onSuccess: (r) => {
      const e = firstError(r.flxAiSkillDelete?.errors);
      setNotice(e ? { kind: 'error', text: e.text } : { kind: 'success', text: asking?.bundled ? t('Skill reset to the version its plugin ships.') : t('Skill deleted.') });
      setAsking(null);
      refresh();
    },
    onError: fail,
  });
  const restrict = useFlxMutation(FlxAiSkillsSettings, { onSuccess: refresh, onError: fail });

  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <div className="flxai-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>;
  const o = q.data.flxAiSkills;
  const site = t('This site');
  const groups = new Map<string, Skill[]>();
  for (const s of o.skills) {
    const g = s.bundled ? s.source : site;
    groups.set(g, [...(groups.get(g) ?? []), s]);
  }
  const rank = (k: string) => (k === site ? 0 : k === 'Flux AI & MCP' ? 1 : 2);
  const names = [...groups.keys()].sort((a, b) => rank(a) - rank(b) || a.localeCompare(b));
  const busy = toggle.isPending || del.isPending;

  return (
    <>
      <NoticeBar notice={notice} onClose={() => setNotice(null)} t={t} />
      <Lead text={t('Instruction folders the assistant follows, in the open Agent Skills format (the same SKILL.md folders Claude uses). Chats and scheduled tasks use them by name; edit a skill once and every task that uses it follows the new version.')} />
      <Card
        title={
          <span className="flxai-card-head">
            {t('Skills')}
            <span className="flxai-card-acts">
              <button type="button" className="flxai-btn" onClick={() => go({ kind: 'brand' })}>
                {t('Brand kit')}
              </button>
              <button type="button" className="flxai-btn" onClick={() => go({ kind: 'import' })}>
                {t('Import')}
              </button>
              <button type="button" className="flxai-btn is-primary" onClick={() => go({ kind: 'edit', slug: null, tab: '' })}>
                {t('New skill')}
              </button>
            </span>
          </span>
        }
      >
        {names.map((g) => (
          <div key={g}>
            {names.length > 1 && (
              <h3 className="flxai-skill-group">
                {g} <span className="flxai-hint">{num(groups.get(g)?.length ?? 0)}</span>
              </h3>
            )}
            <div className="flxai-skill-grid">
              {groups.get(g)?.map((s) => (
                <article key={s.slug} className={'flxai-skill' + (s.status !== 'active' ? ' is-off' : '')}>
                  <div className="flxai-skill-top">
                    <strong>{s.name}</strong> {s.status !== 'active' && <Pill>{t('Off')}</Pill>}
                  </div>
                  <code className="flxai-skill-slug">/{s.slug}</code>
                  <p className="flxai-skill-desc">{s.description}</p>
                  <div className="flxai-skill-meta">
                    {s.bundled ? <Pill>{s.source}</Pill> : <Pill kind="ok">{t('This site')}</Pill>}
                    {s.bundled && s.changed && <Pill kind="warn">{t('Edited')}</Pill>}
                    <span className="flxai-hint">{t('v%s', String(s.version))}</span>
                    {s.fileCount > 0 && <span className="flxai-hint">{tn('%s file', '%s files', s.fileCount, num(s.fileCount))}</span>}
                    {s.hasScripts && <Pill kind="warn">{t('Scripts')}</Pill>}
                    {s.tasks > 0 && <span className="flxai-hint">{t('Tasks: %s', num(s.tasks))}</span>}
                  </div>
                  <div className="flxai-skill-acts">
                    <button type="button" className="flxai-btn is-small" onClick={() => go({ kind: 'edit', slug: s.slug, tab: '' })}>
                      {t('Edit')}
                    </button>
                    <button type="button" className="flxai-btn is-small" disabled={busy} onClick={() => toggle.mutate({ input: { slug: s.slug } })}>
                      {s.status === 'active' ? t('Turn off') : t('Turn on')}
                    </button>
                    <a className="flxai-btn is-small" href={s.exportUrl}>
                      {t('Export')}
                    </a>
                    {(!s.bundled || s.changed || s.saved) && (
                      <button type="button" className={'flxai-btn is-small' + (s.bundled ? '' : ' is-danger')} onClick={() => setAsking(s)}>
                        {s.bundled ? t('Reset') : t('Delete')}
                      </button>
                    )}
                  </div>
                </article>
              ))}
            </div>
          </div>
        ))}
        <p className="flxai-hint">{t('Tip: start a chat message with /name to use a skill, e.g. "/gunluk-brifing only donations"; several work together ("/a /b …"). Ask "/skill-olusturucu" to write and test a new one. Plugins can ship ready skill folders with the flxai_skills filter.')}</p>
        <label className="flxai-check">
          <input type="checkbox" checked={o.restrict} disabled={restrict.isPending} onChange={(e) => restrict.mutate({ input: { restrict: e.target.checked } })} />{' '}
          {t('When a message uses a skill, offer only the tools in its allowed-tools list')}
        </label>
      </Card>
      {asking && (
        <Modal title={asking.bundled ? t('Reset') : t('Delete')} onClose={() => setAsking(null)}>
          <p>{asking.bundled ? t('Reset "%s" to the version its plugin ships? Your changes are lost.', asking.name) : t('Delete the skill "%s"? Tasks that use it will run without it.', asking.name)}</p>
          <div className="flxai-appr-acts">
            <button type="button" className="flxai-btn" onClick={() => setAsking(null)}>
              {t('Cancel')}
            </button>
            <button type="button" className={'flxai-btn' + (asking.bundled ? '' : ' is-danger')} disabled={del.isPending} onClick={() => del.mutate({ input: { slug: asking.slug } })}>
              {asking.bundled ? t('Reset') : t('Delete')}
            </button>
          </div>
        </Modal>
      )}
    </>
  );
}

function SkillEditor({ slug, tab, go }: { slug: string | null; tab: string; go: (v: View) => void }) {
  const { t } = useAi();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxAiSkill, { slug: slug ?? '' }, { enabled: !!slug });
  const refresh = () => qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxAiSkill, { slug: slug ?? '' }) });
  const tabs: [string, string][] = [
    ['', t('SKILL.md')],
    ['files', t('Files')],
    ['tests', t('Tests')],
  ];
  if (slug && q.isLoading) return <Loading />;
  const d = q.data?.flxAiSkill ?? null;
  if (slug && !d) return <div className="flxai-notice is-error">{q.error?.message || t('Skill not found.')}</div>;
  return (
    <>
      <p>
        <button type="button" className="flxai-link" onClick={() => go({ kind: 'list' })}>
          ← {t('Skills')}
        </button>
      </p>
      {slug && (
        <Tabs items={tabs.map(([id, label]) => ({ id, label }))} current={tab} onChange={(k) => go({ kind: 'edit', slug, tab: k })} label={t('Skills')} />
      )}
      {d && tab === 'files' && <FilesTab d={d} go={go} refresh={refresh} />}
      {d && tab === 'tests' && <TestsTab d={d} refresh={refresh} />}
      {(!d || (tab !== 'files' && tab !== 'tests')) && (
        <SkillForm
          d={d}
          onSaved={(s) => {
            refresh();
            go({ kind: 'edit', slug: s, tab: '' });
          }}
          onCancel={() => go({ kind: 'list' })}
        />
      )}
      <span className="flxai-sr">{t('Skills')}</span>
    </>
  );
}

function SkillForm({ d, onSaved, onCancel }: { d: Detail | null; onSaved: (slug: string) => void; onCancel: () => void }) {
  const { t, num } = useAi();
  const [err, setErr] = useState<{ text: string; field?: string } | null>(null);
  const [ok, setOk] = useState(false);
  const s = d?.skill;
  const [f, setF] = useState(() => ({
    name: s?.name ?? '',
    slug: s?.slug ?? '',
    description: s?.description ?? '',
    body: d?.body ?? '',
    tools: (d?.tools ?? []).join(' '),
    example: d?.example ?? '',
    license: d?.license ?? '',
    compatibility: d?.compatibility ?? '',
    active: !s || s.status === 'active',
  }));
  const set = <K extends keyof typeof f>(k: K, v: (typeof f)[K]) => {
    setOk(false);
    setF((x) => ({ ...x, [k]: v }));
  };
  const save = useFlxMutation(FlxAiSkillSave, {
    onSuccess: (r) => {
      const p = r.flxAiSkillSave;
      const e = firstError(p?.errors);
      setErr(e);
      if (!e && p?.skill) {
        setOk(true);
        onSaved(p.skill.skill.slug);
      }
    },
    onError: (e) => setErr({ text: errText(e, t('The action could not be completed.')) }),
  });
  const bad = /[<>]/.test(f.description) || f.description.length > 1024;
  const submit = () =>
    save.mutate({
      input: {
        current: s?.slug ?? null,
        slug: f.slug,
        name: f.name,
        description: f.description,
        body: f.body,
        tools: f.tools.split(/\s+/).filter(Boolean),
        example: f.example,
        license: f.license,
        compatibility: f.compatibility,
        active: f.active,
      },
    });
  const e = (field: string) => err?.field === field && <p className="flxai-hint is-error">{err.text}</p>;
  return (
    <Card title={s ? t('Edit skill') : t('New skill')}>
      {err && !err.field && <div className="flxai-notice is-error">{err.text}</div>}
      {ok && <div className="flxai-notice">{t('Saved.')}</div>}
      {s?.bundled && <p className="flxai-hint">{t('This skill comes with %s. Your edits are kept on this site; "Reset" brings back the original.', s.source)}</p>}
      <Field label={t('Title')}>
        <input className="flxai-input" value={f.name} maxLength={190} placeholder={t('Donor review')} onChange={(x) => set('name', x.target.value)} />
        {e('name')}
      </Field>
      <Field label={t('Name')} hint={t('The skill folder name: lowercase letters, numbers and dashes, up to 64; cannot contain "claude" or "anthropic". Used in chat as /name. Left empty, it is made from the title.')}>
        <input className="flxai-input code" value={f.slug} maxLength={64} placeholder="bagisci-incelemesi" readOnly={!!s?.bundled} onChange={(x) => set('slug', x.target.value)} />
        {e('slug')}
      </Field>
      <Field label={t('Description')} hint={t('What it does and when to use it, with the words users actually say (e.g. "Use when the user asks for a donor review or loyal donors"). The assistant picks skills by this. Up to 1024 characters, no < or >.')}>
        <textarea className="flxai-input" rows={3} value={f.description} maxLength={1024} onChange={(x) => set('description', x.target.value)} />
        <span className={'flxai-hint' + (bad ? ' is-error' : '')}>{t('%s / 1024', num(f.description.length))}</span>
        {e('description')}
      </Field>
      <Field label={t('Instructions')} hint={t('The SKILL.md body, Markdown. Step by step: what to look at, which tools to use, how the result should look. Put long details in references/ files (Files tab) and mention them here.')}>
        <textarea className="flxai-input code" rows={16} value={f.body} onChange={(x) => set('body', x.target.value)} />
        {e('body')}
      </Field>
      <Field label={t('Allowed tools')} hint={t('allowed-tools: tool names separated by spaces (see Tools); a trailing * matches a prefix (flxcrm_*). A hint for the assistant; with the setting on the Skills page they also limit the tools.')}>
        <input className="flxai-input code" list="flxai-tool-names" value={f.tools} onChange={(x) => set('tools', x.target.value)} />
        <datalist id="flxai-tool-names">
          {(d?.toolNames ?? []).map((n) => (
            <option key={n} value={n} />
          ))}
        </datalist>
      </Field>
      <Field label={t('Example output')} hint={t('Saved as references/examples.md.')}>
        <textarea className="flxai-input code" rows={6} value={f.example} onChange={(x) => set('example', x.target.value)} />
      </Field>
      <Field label={t('License')}>
        <input className="flxai-input" value={f.license} maxLength={200} placeholder="MIT" onChange={(x) => set('license', x.target.value)} />
      </Field>
      <Field label={t('Compatibility')}>
        <input className="flxai-input" value={f.compatibility} maxLength={500} placeholder={t('Needs Flux CRM')} onChange={(x) => set('compatibility', x.target.value)} />
        {e('compatibility')}
      </Field>
      <Field label={t('Status')}>
        <label className="flxai-check">
          <input type="checkbox" checked={f.active} onChange={(x) => set('active', x.target.checked)} /> {t('Active')}
        </label>
      </Field>
      <div className="flxai-appr-acts">
        <button type="button" className="flxai-btn is-primary" disabled={save.isPending || bad} onClick={submit}>
          {t('Save')}
        </button>
        <button type="button" className="flxai-btn" onClick={onCancel}>
          {t('Back')}
        </button>
      </div>
    </Card>
  );
}

function FilesTab({ d, go, refresh }: { d: Detail; go: (v: View) => void; refresh: () => void }) {
  const { t, num } = useAi();
  const [notice, setNotice] = useState<Notice | null>(null);
  const [folder, setFolder] = useState('references');
  const [asking, setAsking] = useState<string | null>(null);
  const slug = d.skill.slug;
  const done = (errors: { message: string }[] | undefined | null, ok: string) => {
    setNotice(errors?.length ? { kind: 'error', text: errors.map((x) => x.message).join(' ') } : { kind: 'success', text: ok });
    setAsking(null);
    refresh();
  };
  const fail = (e: unknown) => setNotice({ kind: 'error', text: errText(e, t('The action could not be completed.')) });
  const del = useFlxMutation(FlxAiSkillFileDelete, { onSuccess: (r) => done(r.flxAiSkillFileDelete?.errors, t('File deleted.')), onError: fail });
  const up = useFlxMutation(FlxAiSkillFileUpload, { onSuccess: (r) => done(r.flxAiSkillFileUpload?.errors, t('File saved.')), onError: fail });
  const size = (n: number) => (n < 1024 ? `${num(n)} B` : n < 1048576 ? `${num(n / 1024, 1)} KB` : `${num(n / 1048576, 1)} MB`);
  const upload = async (file: File | undefined) => {
    if (!file) return setNotice({ kind: 'error', text: t('Choose a file to upload.') });
    up.mutate({ input: { slug, folder, fileName: file.name, contentBase64: await toBase64(file) } });
  };
  return (
    <Card title={t('Skill folder')}>
      <NoticeBar notice={notice} onClose={() => setNotice(null)} t={t} />
      <p className="flxai-hint">{t('references/ holds documents the assistant reads only when needed, assets/ templates and images, scripts/ code (stored, not run on this server), evals/ tests. Files are also visible in Flux Files.')}</p>
      {d.skill.bundled && !d.skill.saved && <p className="flxai-hint">{t('Changing a file keeps an edited copy of this skill on the site.')}</p>}
      <table className="flxai-table">
        <thead>
          <tr>
            <th>{t('File')}</th>
            <th>{t('Size')}</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>
              <code>SKILL.md</code>
            </td>
            <td>—</td>
            <td>
              <button type="button" className="flxai-link" onClick={() => go({ kind: 'edit', slug, tab: '' })}>
                {t('Edit')}
              </button>
            </td>
          </tr>
          {d.files.map((f) => (
            <tr key={f.path}>
              <td>
                <code>{f.path}</code>
              </td>
              <td>{size(f.size)}</td>
              <td className="flxai-file-acts">
                <button type="button" className="flxai-link" onClick={() => go({ kind: 'file', slug, path: f.path })}>
                  {t('Open')}
                </button>
                {' · '}
                <a href={f.downloadUrl}>{t('Download')}</a>
                {' · '}
                <button type="button" className="flxai-link is-danger" onClick={() => setAsking(f.path)}>
                  {t('Delete')}
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
      <div className="flxai-skill-file-new">
        <label>
          {t('Upload to')}{' '}
          <select className="flxai-input is-small" value={folder} onChange={(e) => setFolder(e.target.value)}>
            {['references', 'assets', 'scripts', 'evals'].map((x) => (
              <option key={x} value={x}>
                {x}/
              </option>
            ))}
          </select>
        </label>
        <input type="file" disabled={up.isPending} onChange={(e) => void upload(e.target.files?.[0])} />
        <button type="button" className="flxai-btn" onClick={() => go({ kind: 'file', slug, path: 'references/' })}>
          {t('New text file')}
        </button>
      </div>
      {asking && (
        <Modal title={t('Delete')} onClose={() => setAsking(null)}>
          <p>{t('Delete %s?', asking)}</p>
          <div className="flxai-appr-acts">
            <button type="button" className="flxai-btn" onClick={() => setAsking(null)}>
              {t('Cancel')}
            </button>
            <button type="button" className="flxai-btn is-danger" disabled={del.isPending} onClick={() => del.mutate({ input: { slug, path: asking } })}>
              {t('Delete')}
            </button>
          </div>
        </Modal>
      )}
    </Card>
  );
}

function FileEditor({ slug, path, go }: { slug: string; path: string; go: (v: View) => void }) {
  const { t, num } = useAi();
  const isNew = path === '' || path.endsWith('/');
  const q = useFlxQuery(FlxAiSkillFile, { slug, path }, { enabled: !isNew });
  const files: View = { kind: 'edit', slug, tab: 'files' };
  if (!isNew && q.isLoading) return <Loading />;
  const file = q.data?.flxAiSkillFile ?? null;
  if (!isNew && !file) return <div className="flxai-notice is-error">{q.error?.message || t('The skill has no file "%s".', path)}</div>;
  if (file && !file.text)
    return (
      <Card title={t('%s · file', slug)}>
        <p className="flxai-hint">
          {file.note} ({num(file.size)} B)
        </p>
        <button type="button" className="flxai-btn" onClick={() => go(files)}>
          {t('Back')}
        </button>
      </Card>
    );
  return <FileForm slug={slug} path={path} isNew={isNew} initial={file?.content ?? ''} back={() => go(files)} />;
}

function FileForm({ slug, path, isNew, initial, back }: { slug: string; path: string; isNew: boolean; initial: string; back: () => void }) {
  const { t } = useAi();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const [p, setP] = useState(path);
  const [content, setContent] = useState(initial);
  const [err, setErr] = useState<string | null>(null);
  const save = useFlxMutation(FlxAiSkillFileSave, {
    onSuccess: (r) => {
      const e = firstError(r.flxAiSkillFileSave?.errors);
      setErr(e?.text ?? null);
      if (!e) {
        void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxAiSkill, { slug }) });
        void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxAiSkillFile, { slug, path: p }) });
        back();
      }
    },
    onError: (e) => setErr(errText(e, t('The action could not be completed.'))),
  });
  return (
    <Card title={t('%s · file', slug)}>
      {err && <div className="flxai-notice is-error">{err}</div>}
      <Field label={t('Path')}>
        <input className="flxai-input code" style={{ width: '100%', maxWidth: 560 }} value={p} readOnly={!isNew} placeholder="references/guide.md" onChange={(e) => setP(e.target.value)} />
      </Field>
      <textarea className="flxai-input code" rows={24} value={content} onChange={(e) => setContent(e.target.value)} />
      <div className="flxai-appr-acts">
        <button type="button" className="flxai-btn is-primary" disabled={save.isPending || !p || p.endsWith('/')} onClick={() => save.mutate({ input: { slug, path: p, content } })}>
          {t('Save')}
        </button>
        <button type="button" className="flxai-btn" onClick={back}>
          {t('Back')}
        </button>
      </div>
    </Card>
  );
}

const yes = (b: boolean) => <Pill kind={b ? 'ok' : 'err'}>{b ? '✓' : '✕'}</Pill>;

function TestsTab({ d, refresh }: { d: Detail; refresh: () => void }) {
  const { t, num, usd } = useAi();
  const slug = d.skill.slug;
  const [err, setErr] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const [trig, setTrig] = useState(() => d.evals.triggers.map((x) => (x.shouldTrigger ? '+ ' : '- ') + x.query).join('\n'));
  const [cases, setCases] = useState(() => [...d.evals.cases.map((c) => ({ prompt: c.prompt, expect: c.expectations.join('\n') })), { prompt: '', expect: '' }].slice(0, 10));
  const [running, setRunning] = useState('');
  const fail = (e: unknown) => setErr(errText(e, t('The action could not be completed.')));
  const save = useFlxMutation(FlxAiSkillEvalsSave, {
    onSuccess: (r) => {
      const e = firstError(r.flxAiSkillEvalsSave?.errors);
      setErr(e?.text ?? null);
      setSaved(!e);
      refresh();
    },
    onError: fail,
  });
  const run = useFlxMutation(FlxAiSkillTestRun, {
    onSuccess: (r) => {
      setErr(firstError(r.flxAiSkillTestRun?.errors)?.text ?? null);
      setRunning('');
      refresh();
    },
    onError: (e) => {
      fail(e);
      setRunning('');
    },
  });
  const triggers = trig
    .split(/\r?\n/)
    .map((l) => l.trim())
    .filter(Boolean)
    .map((l) => ({ query: l.replace(/^[+-]\s*/, '').trim(), shouldTrigger: !l.startsWith('-') }));
  const submit = () =>
    save.mutate({
      input: {
        slug,
        triggers,
        cases: cases.filter((c) => c.prompt.trim()).map((c) => ({ prompt: c.prompt, expectations: c.expect.split(/\r?\n/).map((x) => x.trim()).filter(Boolean) })),
      },
    });
  const go = (kind: string, id = '') => {
    setRunning(kind + id);
    run.mutate({ input: { slug, kind, id } });
  };
  const r = d.results;
  const side = (label: string, s: Detail['results']['functional'][number]['with']) => (
    <div className="flxai-skill-side">
      <h4>
        {label} · {t('%1$s/%2$s checks', num(s.passed), num(s.total))}
      </h4>
      <p className="flxai-hint">{t('%1$s in · %2$s out tokens · %3$s · %4$s s', num(s.in), num(s.out), usd(s.cost), num(s.ms / 1000, 1))}</p>
      <pre>{s.output}</pre>
    </div>
  );
  return (
    <Card>
      {err && <div className="flxai-notice is-error">{err}</div>}
      {saved && <div className="flxai-notice">{t('Saved.')}</div>}
      <h2>{t('Trigger test')}</h2>
      <p className="flxai-hint">{t('Does the assistant pick this skill from its name and description alone? One prompt per line: "+" should use the skill, "-" should not. Aim for 90% or more.')}</p>
      <textarea className="flxai-input code" rows={8} value={trig} placeholder={'+ …\n- …'} onChange={(e) => (setSaved(false), setTrig(e.target.value))} />
      <h2>{t('Functional tests')}</h2>
      <p className="flxai-hint">{t('A request and what a good answer must contain (one check per line). The test answers it with and without the skill (no tools, nothing is changed) and compares checks, tokens and cost.')}</p>
      {cases.map((c, i) => (
        <div key={i} className="flxai-skill-case">
          <strong>{i < d.evals.cases.length ? `#${d.evals.cases[i]?.id}` : t('New test')}</strong>
          <textarea className="flxai-input" rows={2} value={c.prompt} placeholder={t('Request')} onChange={(e) => setCases((x) => x.map((y, j) => (j === i ? { ...y, prompt: e.target.value } : y)))} />
          <textarea className="flxai-input code" rows={3} value={c.expect} placeholder={t('Checks, one per line')} onChange={(e) => setCases((x) => x.map((y, j) => (j === i ? { ...y, expect: e.target.value } : y)))} />
        </div>
      ))}
      <div className="flxai-appr-acts">
        <button type="button" className="flxai-btn" disabled={save.isPending} onClick={submit}>
          {t('Save tests')}
        </button>
      </div>
      <h2>{t('Run')}</h2>
      <p className="flxai-hint">{t('Each run uses the chat model and is billed like a short chat (purpose "skill_test" in Logs).')}</p>
      <div className="flxai-appr-acts">
        <button type="button" className="flxai-btn is-primary" disabled={run.isPending || !d.evals.triggers.length} onClick={() => go('trigger')}>
          {running === 'trigger' ? t('Running…') : t('Run trigger test')}
        </button>
        {d.evals.cases.map((c) => (
          <button key={c.id} type="button" className="flxai-btn" disabled={run.isPending} onClick={() => go('functional', c.id)}>
            {running === 'functional' + c.id ? t('Running…') : t('Run test #%s', c.id)}
          </button>
        ))}
      </div>
      {!r.trigger && !r.functional.length && <p className="flxai-hint">{t('No results yet.')}</p>}
      {r.trigger && (
        <div className="flxai-skill-res">
          <h3>
            {t('Trigger test')} · <span className={'flxai-rate' + (r.trigger.rate >= 90 ? ' is-ok' : ' is-warn')}>{t('%s% correct', num(r.trigger.rate))}</span>
          </h3>
          <p className="flxai-hint">
            {t('%1$s of %2$s prompts · %3$s · %4$s', num(r.trigger.hits), num(r.trigger.total), usd(r.trigger.cost), r.trigger.ts.label)}
            {r.trigger.partial ? ' · ' + t('Stopped early (time limit); run again for the rest.') : ''}
          </p>
          <table className="flxai-table">
            <thead>
              <tr>
                <th>{t('Prompt')}</th>
                <th>{t('Expected')}</th>
                <th>{t('Picked skills')}</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {r.trigger.rows.map((x, i) => (
                <tr key={i}>
                  <td>{x.query}</td>
                  <td>{x.shouldTrigger ? t('Use') : t('Do not use')}</td>
                  <td>
                    <code>{x.picked.join(', ') || '—'}</code>
                  </td>
                  <td>{yes(x.triggered === x.shouldTrigger)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {r.functional.map((f) => (
        <div key={f.id} className="flxai-skill-res">
          <h3>{t('Test #%s', f.id)}</h3>
          <p className="flxai-hint">
            {f.prompt} · {f.ts.label}
          </p>
          {f.checks.length > 0 && (
            <table className="flxai-table">
              <thead>
                <tr>
                  <th>{t('Check')}</th>
                  <th>{t('With skill')}</th>
                  <th>{t('Without')}</th>
                </tr>
              </thead>
              <tbody>
                {f.checks.map((c, i) => (
                  <tr key={i}>
                    <td>{c.text}</td>
                    <td>{yes(c.with)}</td>
                    <td>{yes(c.without)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
          {f.note && <p>
            <em>{f.note}</em>
          </p>}
          <div className="flxai-skill-sides">
            {side(t('With skill'), f.with)}
            {side(t('Without'), f.without)}
          </div>
        </div>
      ))}
    </Card>
  );
}

export type { AiT };
