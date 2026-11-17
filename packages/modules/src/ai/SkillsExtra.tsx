// Flux AI › Skills: importing skill folders and the brand kit.
import { useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { FlxAiBrand, FlxAiBrandSave, FlxAiSkillImport, FlxAiSkills } from '@fluxwp/graphql/operations';
import type { FlxAiBrandFieldsFragment } from '@fluxwp/graphql/types';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { Loading } from '@fluxwp/ui';
import { Card, errText, useAi } from './common';
import { Field, firstError, toBase64 } from './Skills';

export function ImportSkills({ onBack, onDone }: { onBack: () => void; onDone: () => void }) {
  const { t } = useAi();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const [md, setMd] = useState('');
  const [file, setFile] = useState<File | null>(null);
  const [err, setErr] = useState<string | null>(null);
  const [res, setRes] = useState<string | null>(null);
  const imp = useFlxMutation(FlxAiSkillImport, {
    onSuccess: (r) => {
      const p = r.flxAiSkillImport;
      const e = firstError(p?.errors);
      setErr(e?.text ?? null);
      if (!e && p?.result) {
        void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxAiSkills, {}) });
        setRes([t('Imported: %s', p.result.imported.join(', ')), ...p.result.notes].join(' · '));
      }
    },
    onError: (e) => setErr(errText(e, t('The action could not be completed.'))),
  });
  const submit = async () => {
    setRes(null);
    if (file) return imp.mutate({ input: { fileName: file.name, contentBase64: await toBase64(file) } });
    imp.mutate({ input: { markdown: md } });
  };
  return (
    <>
      <p>
        <button type="button" className="flxai-link" onClick={onBack}>
          ← {t('Skills')}
        </button>
      </p>
      <Card title={t('Import skills')}>
        {err && <div className="flxai-notice is-error">{err}</div>}
        {res && <div className="flxai-notice">{res}</div>}
        <p className="flxai-hint">{t('Upload a skill folder as .zip (one or more folders, each with a SKILL.md, e.g. exported from Claude) or a single SKILL.md, or paste its content. A skill with the same name is updated. Scripts are stored but not run.')}</p>
        <Field label={t('File')}>
          <input type="file" accept=".zip,.skill,.md,application/zip,text/markdown,text/plain" onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
        </Field>
        <Field label={t('or paste SKILL.md')}>
          <textarea className="flxai-input code" rows={14} value={md} placeholder={'---\nname: bagisci-incelemesi\ndescription: …\n---\n\n# …'} onChange={(e) => setMd(e.target.value)} />
        </Field>
        <div className="flxai-appr-acts">
          <button type="button" className="flxai-btn is-primary" disabled={imp.isPending || (!file && !md.trim())} onClick={() => void submit()}>
            {t('Import')}
          </button>
          <button type="button" className="flxai-btn" onClick={res ? onDone : onBack}>
            {res ? t('Back') : t('Cancel')}
          </button>
        </div>
      </Card>
    </>
  );
}

type BrandData = FlxAiBrandFieldsFragment;

const TEXT: [keyof BrandData, string, 'name' | 'tagline' | 'website' | 'phone' | 'handle'][] = [
  ['name', 'Organisation name', 'name'],
  ['tagline', 'Tagline', 'tagline'],
  ['website', 'Website shown on visuals', 'website'],
  ['phone', 'Phone / WhatsApp', 'phone'],
  ['handle', 'Social media handle or hashtag', 'handle'],
];
const COLORS: [keyof BrandData, string][] = [
  ['colorPrimary', 'Primary color'],
  ['colorAccent', 'Accent color'],
  ['colorDark', 'Dark color (backgrounds)'],
  ['colorLight', 'Light color (backgrounds)'],
];

export function Brand({ onBack }: { onBack: () => void }) {
  const { t } = useAi();
  const q = useFlxQuery(FlxAiBrand, {});
  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <div className="flxai-notice is-error">{q.error?.message || t('The action could not be completed.')}</div>;
  return <BrandForm b={q.data.flxAiBrand} onBack={onBack} />;
}

function BrandForm({ b, onBack }: { b: BrandData; onBack: () => void }) {
  const { t } = useAi();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const [err, setErr] = useState<{ text: string; field?: string } | null>(null);
  const [ok, setOk] = useState(false);
  const [f, setF] = useState(() => ({
    name: b.name,
    tagline: b.tagline,
    website: b.website,
    phone: b.phone,
    handle: b.handle,
    colorPrimary: b.colorPrimary,
    colorAccent: b.colorAccent,
    colorDark: b.colorDark,
    colorLight: b.colorLight,
    logoId: b.logoId ? String(b.logoId) : '',
    logoLightId: b.logoLightId ? String(b.logoLightId) : '',
    partnerIds: b.partnerIds.join(', '),
    notes: b.notes,
  }));
  const [docs, setDocs] = useState<Record<string, string>>(() => Object.fromEntries(b.docs.map((d) => [d.name, d.content])));
  const [newName, setNewName] = useState('');
  const [newBody, setNewBody] = useState('');
  const set = (k: keyof typeof f, v: string) => {
    setOk(false);
    setF((x) => ({ ...x, [k]: v }));
  };
  const save = useFlxMutation(FlxAiBrandSave, {
    onSuccess: (r) => {
      const e = firstError(r.flxAiBrandSave?.errors);
      setErr(e);
      setOk(!e);
      if (!e) {
        setNewName('');
        setNewBody('');
        void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxAiBrand, {}) });
      }
    },
    onError: (e) => setErr({ text: errText(e, t('The action could not be completed.')) }),
  });
  const num = (v: string) => Number.parseInt(v, 10) || 0;
  const submit = () => {
    const list = Object.entries(docs).map(([name, content]) => ({ name, content }));
    if (newName.trim() && newBody.trim()) list.push({ name: newName.trim(), content: newBody });
    save.mutate({
      input: {
        name: f.name,
        tagline: f.tagline,
        website: f.website,
        phone: f.phone,
        handle: f.handle,
        colorPrimary: f.colorPrimary,
        colorAccent: f.colorAccent,
        colorDark: f.colorDark,
        colorLight: f.colorLight,
        logoId: num(f.logoId),
        logoLightId: num(f.logoLightId),
        partnerIds: f.partnerIds.split(/[\s,]+/).map(num).filter(Boolean),
        notes: f.notes,
        docs: list,
      },
    });
  };
  const defaults: Record<string, string> = { name: b.defaultName, tagline: b.defaultTagline, website: b.defaultWebsite };
  return (
    <>
      <p>
        <button type="button" className="flxai-link" onClick={onBack}>
          ← {t('Skills')}
        </button>
      </p>
      <Card title={t('Brand kit')}>
        {err && <div className="flxai-notice is-error">{err.text}</div>}
        {ok && <div className="flxai-notice">{t('Saved.')}</div>}
        <p className="flxai-hint">{t('Design and writing skills read this before they start: colors and logos for visuals, voice documents for posts. Empty fields use the site name, logo and theme colors. The assistant can also update it from the chat (you approve each change).')}</p>
        {TEXT.map(([k, label, key]) => (
          <Field key={k} label={t(label)}>
            <input className="flxai-input" value={f[key]} maxLength={200} placeholder={defaults[key] ?? ''} onChange={(e) => set(key, e.target.value)} />
          </Field>
        ))}
        {COLORS.map(([k, label]) => {
          const key = k as 'colorPrimary' | 'colorAccent' | 'colorDark' | 'colorLight';
          return (
            <Field key={k} label={t(label)}>
              <input className="flxai-input code" value={f[key]} placeholder="#rrggbb" onChange={(e) => set(key, e.target.value)} />
              <span className="flxai-brand-swatch" style={{ background: f[key] || 'transparent' }} />
              {err?.field === 'colorPrimary' && key === 'colorPrimary' && <p className="flxai-hint is-error">{err.text}</p>}
            </Field>
          );
        })}
        <Field label={t('Logo (media ID)')}>
          <input className="flxai-input is-small" inputMode="numeric" value={f.logoId} placeholder={b.logoUrl ? '' : ''} onChange={(e) => set('logoId', e.target.value)} />
          {b.logoUrl && <img className="flxai-brand-logo" src={b.logoUrl} alt="" />}
        </Field>
        <Field label={t('White logo for dark backgrounds (media ID)')}>
          <input className="flxai-input is-small" inputMode="numeric" value={f.logoLightId} onChange={(e) => set('logoLightId', e.target.value)} />
          {b.logoLightUrl && <img className="flxai-brand-logo is-dark" src={b.logoLightUrl} alt="" />}
        </Field>
        <Field label={t('Partner logos (media IDs, comma separated)')}>
          <input className="flxai-input" value={f.partnerIds} onChange={(e) => set('partnerIds', e.target.value)} />
          {b.partnerUrls.filter(Boolean).map((u) => (
            <img key={u} className="flxai-brand-logo" src={u} alt="" />
          ))}
        </Field>
        <Field label={t('Design notes')}>
          <textarea className="flxai-input" rows={3} value={f.notes} placeholder={t('e.g. Rounded shapes, lots of green, real photos of our volunteers')} onChange={(e) => set('notes', e.target.value)} />
        </Field>
        {b.themePalette.length > 0 && (
          <Field label={t('Theme colors')}>
            {b.themePalette.map((c) => (
              <span key={c.slug + c.color} title={c.name} className="flxai-brand-chip">
                <i className="flxai-brand-swatch" style={{ background: c.color }} /> <code>{c.color}</code>
              </span>
            ))}
          </Field>
        )}
        <h3>{t('Documents')}</h3>
        <p className="flxai-hint">{t('Markdown documents skills read, e.g. about-me.md and voice.md (written by the voice-builder skill). Clear a document to delete it.')}</p>
        {Object.entries(docs).map(([name, content]) => (
          <div key={name} className="flxai-brand-doc">
            <strong>
              <code>{name}</code>
            </strong>
            <textarea className="flxai-input code" rows={8} value={content} onChange={(e) => (setOk(false), setDocs((d) => ({ ...d, [name]: e.target.value })))} />
          </div>
        ))}
        <div className="flxai-brand-doc">
          <input className="flxai-input code" value={newName} placeholder="voice.md" onChange={(e) => setNewName(e.target.value)} />
          <textarea className="flxai-input code" rows={5} value={newBody} placeholder={t('New document (Markdown)')} onChange={(e) => setNewBody(e.target.value)} />
        </div>
        <div className="flxai-appr-acts">
          <button type="button" className="flxai-btn is-primary" disabled={save.isPending} onClick={submit}>
            {t('Save')}
          </button>
        </div>
      </Card>
    </>
  );
}
