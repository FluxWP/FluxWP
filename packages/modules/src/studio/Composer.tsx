// The generation bar: references, prompt, model picker, settings, Enhance, Brainstorm, templates.
import { useEffect, useRef, useState, type DragEvent } from 'react';
import { useGraphQL } from '@fluxwp/graphql/react';
import { FlxStudioEnhance, FlxStudioRefCreate, FlxStudioRefUpdate, FlxStudioTemplateCreate, FlxStudioTemplateDelete } from '@fluxwp/graphql/operations';
import { useHost } from '../host';
import type { Translate } from './i18n';
import { Btn, Ic, IconBtn, Modal, Select, useT, useUi, type MenuItem } from './ui';
import { GalleryPicker } from './Dialogs';
import { call, modelsOf, priceOf, providerLabel, qualityLabel, refPayload, roleLabel, ROLE_KEYS, money, norm, toKind, uploadFile, type Cfg, type CRef, type Item, type Model, type Template, type Workspace } from './lib';

const IMG_TYPES = /^image\/(png|jpeg|webp|gif)$/;
const range = (a: number, b: number): number[] => Array.from({ length: Math.max(0, b - a + 1) }, (_, i) => a + i);

export interface ComposerProps {
  ws: Workspace;
  cfg: Cfg;
  model: Model;
  setCfg: (patch: Partial<Cfg>) => void;
  refs: CRef[];
  setRefs: (f: (r: CRef[]) => CRef[]) => void;
  addRef: (r: CRef) => void;
  items: Item[];
  generating: boolean;
  locale: string;
  onGenerate: () => void;
  onBrainstorm: () => void;
  onApplyTemplate: (tp: Template) => void;
  onChanged: () => void;
}

export function Composer(p: ComposerProps) {
  const { ws, cfg: c, model: m, setCfg, refs, setRefs, addRef, locale } = p;
  const t = useT();
  const ui = useUi();
  const host = useHost();
  const { client } = useGraphQL();
  const ta = useRef<HTMLTextAreaElement>(null);
  const [drop, setDrop] = useState(false);
  const [undo, setUndo] = useState<string | null>(null);
  const [enhancing, setEnhancing] = useState(false);
  const [picking, setPicking] = useState(false);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const fileInput = useRef<HTMLInputElement>(null);

  useEffect(() => {
    const el = ta.current;
    if (!el) return;
    el.style.height = 'auto';
    const lh = parseFloat(getComputedStyle(el).lineHeight) || 22;
    el.style.height = Math.min(lh * 2 + 12, el.scrollHeight + 2) + 'px';
  }, [c.prompt]);
  useEffect(() => {
    if (undo === null) return;
    const id = window.setTimeout(() => setUndo(null), 15000);
    return () => window.clearTimeout(id);
  }, [undo]);

  const textOk = ws.aiText;
  const prov = c.provider && c.provider !== 'auto' ? c.provider : m.auto || m.providers[0] || '';
  const price = priceOf(m, c);
  const list = modelsOf(ws, c.kind);

  const uploadRefs = (files: File[]) => {
    const ok = files.filter((f) => IMG_TYPES.test(f.type));
    if (!ok.length) return ui.toast(t('Only PNG, JPEG, WebP or GIF can be added.'), 'error');
    for (const f of ok) {
      const key = 'tmp' + Math.random().toString(36).slice(2);
      const tmp: CRef = { type: 'REF', id: key, role: '', name: f.name, thumb: URL.createObjectURL(f) };
      setRefs((l) => [...l, tmp]);
      void (async () => {
        try {
          const uploadId = await uploadFile(host.client, 'flx-studio/reference', f, f.name);
          const r = await call(client.request(FlxStudioRefCreate, { input: { uploadId, name: f.name.replace(/\.[^.]+$/, '') } }).then((x) => x.flxStudioRefCreate));
          const ref = r.ref;
          if (!ref) throw new Error('');
          setRefs((l) => l.map((x) => (x.id === key ? { ...x, id: ref.databaseId, thumb: ref.thumb, name: ref.name } : x)));
          p.onChanged();
        } catch (e) {
          setRefs((l) => l.filter((x) => x.id !== key));
          ui.fail(e);
        }
      })();
    }
  };

  const roleMenu = (anchor: HTMLElement, r: CRef) => {
    const items: MenuItem[] = ROLE_KEYS.map((k) => ({ label: roleLabel(t, k), checked: r.role === k, onClick: () => setRefs((l) => l.map((x) => (x === r ? { ...x, role: k } : x))) }));
    if (r.type === 'REF' && typeof r.id === 'number') {
      const id = r.id;
      items.push({ sep: true }, {
        icon: 'star',
        label: t('Save to references'),
        onClick: () => {
          void call(client.request(FlxStudioRefUpdate, { input: { id, saved: true, role: r.role } }).then((x) => x.flxStudioRefUpdate)).then(
            () => {
              p.onChanged();
              ui.toast(t('Saved'));
            },
            ui.fail,
          );
        },
      });
    }
    ui.menu(anchor, items);
  };

  const refMenu = (anchor: HTMLElement) => {
    const saved = ws.refs.filter((r) => r.saved && !r.personId);
    const items: MenuItem[] = [
      { icon: 'upload', label: t('Upload file'), onClick: () => fileInput.current?.click() },
      { icon: 'grid', label: t('Pick from gallery'), onClick: () => setPicking(true) },
    ];
    if (ws.people.length) {
      items.push({ head: t('People') });
      for (const pe of ws.people) {
        const photo = pe.photos[0];
        items.push({ icon: 'user', label: pe.name, hint: pe.photos.length ? t('{n} photos', { n: pe.photos.length }) : t('no photos'), disabled: !pe.photos.length, onClick: () => addRef({ type: 'PERSON', id: pe.databaseId, role: '', name: pe.name, thumb: photo?.thumb ?? '' }) });
      }
    }
    if (saved.length) {
      items.push({ head: t('Saved references') });
      for (const r of saved.slice(0, 12)) items.push({ icon: 'image', label: r.name || roleLabel(t, r.role ?? '') || t('Reference'), onClick: () => addRef({ type: 'REF', id: r.databaseId, role: r.role ?? '', name: r.name, thumb: r.thumb }) });
    }
    ui.menu(anchor, items);
  };

  const mention = (el: HTMLTextAreaElement) => {
    const before = el.value.slice(0, el.selectionStart);
    const mm = /(^|\s)@([^\s@]{0,30})$/.exec(before);
    if (!mm || !ws.people.length) return ui.closePop();
    const q = norm(mm[2] ?? '', locale);
    const hits = ws.people.filter((x) => norm(x.name, locale).startsWith(q) || norm(x.name, locale).includes(' ' + q)).slice(0, 8);
    if (!hits.length) return ui.closePop();
    ui.menu(
      el,
      hits.map((pe) => ({
        icon: 'user' as const,
        label: pe.name,
        hint: t('{n} photos', { n: pe.photos.length }),
        onClick: () => {
          const start = before.length - (mm[2] ?? '').length - 1;
          const next = el.value.slice(0, start) + '@' + pe.name + ' ' + el.value.slice(el.selectionStart);
          setCfg({ prompt: next });
          el.focus();
          const pos = start + pe.name.length + 2;
          window.setTimeout(() => el.setSelectionRange(pos, pos), 0);
          const photo = pe.photos[0];
          if (photo) addRef({ type: 'PERSON', id: pe.databaseId, role: '', name: pe.name, thumb: photo.thumb });
          else ui.toast(t('{name} has no photos; add some in Manage people', { name: pe.name }), 'error');
        },
      })),
      { cls: 'is-mention' },
    );
  };

  const enhance = async () => {
    const draft = c.prompt;
    if (!draft.trim()) return ui.toast(t('Write a short draft first.'), 'error');
    setEnhancing(true);
    try {
      const r = await call(client.request(FlxStudioEnhance, { input: { prompt: draft, mode: c.kind === 'video' ? 'VIDEO' : 'IMAGE', refs: refPayload(refs), modelLabel: m.label } }).then((x) => x.flxStudioEnhance));
      setCfg({ prompt: r.prompt ?? draft, enhanced: true, draft });
      ui.toast(t('Prompt enhanced'));
      setUndo(draft);
    } catch (e) {
      ui.fail(e);
    } finally {
      setEnhancing(false);
    }
  };

  const onDrop = (e: DragEvent) => {
    e.preventDefault();
    setDrop(false);
    const raw = e.dataTransfer.getData('application/x-flxst-items');
    if (raw) {
      try {
        for (const id of JSON.parse(raw) as number[]) {
          const it = p.items.find((x) => x.databaseId === id);
          if (it && it.kind === 'IMAGE' && it.state === 'DONE') addRef({ type: 'ITEM', id, role: '', name: it.title, thumb: it.thumb ?? '' });
        }
      } catch {
        /* not ours */
      }
      return;
    }
    const files = Array.from(e.dataTransfer.files);
    if (files.length) uploadRefs(files);
  };

  const maxRefs = m.maxRefs || 14;
  const maxCount = Math.min(ws.settings.maxCount, m.maxCount);

  return (
    <div
      className={'flxst-composer' + (drop ? ' is-drop' : '')}
      onDragOver={(e) => {
        e.preventDefault();
        setDrop(true);
      }}
      onDragLeave={(e) => {
        if (!e.currentTarget.contains(e.relatedTarget as Node | null)) setDrop(false);
      }}
      onDrop={onDrop}
    >
      <input
        ref={fileInput}
        type="file"
        hidden
        multiple
        accept="image/png,image/jpeg,image/webp,image/gif"
        onChange={(e) => {
          uploadRefs(Array.from(e.target.files ?? []));
          e.target.value = '';
        }}
      />
      <div className="flxst-compose-top">
        <div className="flxst-refs">
          {refs.map((r, i) => (
            <div key={`${r.type}-${r.id}`} className="flxst-ref" title={r.name || roleLabel(t, r.role)}>
              {r.thumb ? <img src={r.thumb} alt="" /> : <span className="flxst-av">{(r.name || '?').charAt(0)}</span>}
              {r.type === 'PERSON' ? (
                <span className="flxst-ref-n">@{r.name}</span>
              ) : (
                <button type="button" className="flxst-ref-role" onClick={(e) => roleMenu(e.currentTarget, r)}>
                  {roleLabel(t, r.role)}
                </button>
              )}
              <button type="button" className="flxst-ref-x" title={t('Remove')} aria-label={t('Remove')} onClick={() => setRefs((l) => l.filter((_, j) => j !== i))}>
                <Ic n="x" size={12} />
              </button>
            </div>
          ))}
          <button type="button" className="flxst-ref-add" title={t('Add reference')} onClick={(e) => refMenu(e.currentTarget)}>
            <Ic n="plus" size={18} />
          </button>
          {refs.length > 0 && (
            <small className="flxst-muted">
              {refs.length} / {maxRefs}
            </small>
          )}
        </div>
        <div className="flxst-opts">
          {m.aspects.length > 0 && <Select label={t('Aspect ratio')} value={c.aspect} options={m.aspects.map((a) => [a, a === 'adaptive' ? t('Adaptive') : a])} onChange={(v) => setCfg({ aspect: v })} />}
          {m.resolutions.length > 0 && <Select label={t('Resolution')} value={c.resolution} options={m.resolutions.map((r) => [r, r])} onChange={(v) => setCfg({ resolution: v })} />}
          {m.qualities.length > 0 && <Select label={t('Quality')} value={c.quality} options={m.qualities.map((q) => [q, qualityLabel(t, q)])} onChange={(v) => setCfg({ quality: v })} />}
          {c.kind === 'image' && m.maxCount > 1 && <Select label={t('Count')} value={c.count} options={range(1, maxCount).map((n) => [n, '×' + n])} onChange={(v) => setCfg({ count: +v })} />}
          {c.kind === 'video' && m.durationMin !== null && m.durationMax !== null && <Select label={t('Duration')} value={c.duration} options={range(m.durationMin, m.durationMax).map((n) => [n, n + ' s'])} onChange={(v) => setCfg({ duration: +v })} />}
          {c.kind === 'video' && (
            <label className="flxst-chk" title={t('Audio')}>
              <input type="checkbox" checked={c.audio} onChange={(e) => setCfg({ audio: e.target.checked })} />
              <span>{t('Audio')}</span>
            </label>
          )}
        </div>
      </div>
      <div className="flxst-compose-main">
        <textarea
          ref={ta}
          className="flxst-prompt"
          rows={1}
          value={c.prompt}
          placeholder={c.kind === 'video' ? t('What happens in the video? Subject, motion, camera… (add people with @)') : t('What do you want to create? (add people with @, drop images here)')}
          onChange={(e) => {
            setCfg({ prompt: e.target.value });
            mention(e.target);
          }}
          onKeyDown={(e) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
              e.preventDefault();
              p.onGenerate();
            }
          }}
          onPaste={(e) => {
            const files = Array.from(e.clipboardData.files).filter((f) => /^image\//.test(f.type));
            if (files.length) {
              e.preventDefault();
              uploadRefs(files);
            }
          }}
        />
        {undo !== null && (
          <button
            type="button"
            className="flxst-undo"
            onClick={() => {
              setCfg({ prompt: undo, enhanced: false, draft: '' });
              setUndo(null);
            }}
          >
            <Ic n="undo" size={14} />
            {t('Back to draft')}
          </button>
        )}
      </div>
      <div className="flxst-compose-foot">
        <div className="flxst-ctrls">
          <div className="flxst-seg">
            {(['image', 'video'] as const).map((k) => (
              <button key={k} type="button" className={'flxst-seg-b' + (c.kind === k ? ' is-on' : '')} onClick={() => setCfg({ kind: k, model: '' })}>
                <Ic n={k} size={15} />
                <span>{k === 'image' ? t('Image') : t('Video')}</span>
              </button>
            ))}
          </div>
          {list.length ? (
            <button type="button" className="flxst-mpick" title={t('Provider and model')} onClick={(e) => ui.pop(e.currentTarget, (close) => <ModelPicker ws={ws} cfg={c} model={m} list={list} onPick={(id, pv) => { setCfg({ model: id, provider: pv }); close(); }} />, { cls: 'flxst-mp-pop' })}>
              {prov && <small>{providerLabel(ws, prov)}</small>}
              <span>{m.label || t('Choose model')}</span>
              <Ic n="chevD" size={14} />
            </button>
          ) : (
            <span className="flxst-muted">{t('No models')}</span>
          )}
        </div>
        <div className="flxst-acts">
          <IconBtn icon="template" title={t('Templates')} onClick={(e) => templateMenu(e.currentTarget, ws, t, ui.menu, p.onApplyTemplate, () => setSaving(true), () => setDeleting(true))} />
          <button type="button" className="flxst-btn is-ghost" onClick={p.onBrainstorm} disabled={!textOk} title={textOk ? t('Several different prompts from one idea') : t('Flux AI text model is not ready')}>
            <Ic n="bulb" size={16} />
            <span>{t('Brainstorm')}</span>
          </button>
          <button type="button" className={'flxst-btn is-ghost flxst-enh' + (enhancing ? ' is-busy' : '')} onClick={() => void enhance()} disabled={!textOk || enhancing} title={textOk ? t('Turn the draft into a detailed prompt for the model') : t('Flux AI text model is not ready')}>
            <Ic n="sparkle" size={16} />
            <span>{t('Enhance')}</span>
          </button>
          <button type="button" className={'flxst-btn is-primary flxst-gen' + (p.generating ? ' is-busy' : '')} onClick={p.onGenerate} disabled={!m.providers.length} title={t('Generate (Ctrl/⌘+Enter)')}>
            <Ic n="sparkle" size={16} />
            <span>{t('Generate')}</span>
            {price > 0 && <small>~{money(price, locale)}</small>}
          </button>
        </div>
      </div>
      {picking && <GalleryPicker onClose={() => setPicking(false)} onPick={(its) => its.forEach((it) => addRef({ type: 'ITEM', id: it.databaseId, role: '', name: it.title, thumb: it.thumb ?? '' }))} />}
      {saving && <SaveTemplate cfg={c} refs={refs} prompt={c.prompt} onClose={() => setSaving(false)} onSaved={p.onChanged} />}
      {deleting && <TemplateList ws={ws} onClose={() => setDeleting(false)} onChanged={p.onChanged} />}
    </div>
  );
}

function templateMenu(anchor: HTMLElement, ws: Workspace, t: Translate, menu: (a: HTMLElement, items: MenuItem[]) => void, apply: (tp: Template) => void, save: () => void, del: () => void) {
  const items: MenuItem[] = ws.templates.map((tp) => ({ icon: 'template' as const, label: tp.name, hint: tp.shared ? t('team') : '', onClick: () => apply(tp) }));
  if (!items.length) items.push({ label: t('No templates yet'), disabled: true });
  items.push({ sep: true }, { icon: 'plus', label: t('Save current settings'), onClick: save });
  if (ws.templates.length) items.push({ icon: 'trash', label: t('Delete template…'), onClick: del });
  menu(anchor, items);
}

function ModelPicker({ ws, cfg: c, model: cur, list, onPick }: { ws: Workspace; cfg: Cfg; model: Model; list: Model[]; onPick: (id: string, provider: string) => void }) {
  const t = useT();
  const active = c.provider && c.provider !== 'auto' ? c.provider : cur.auto || '';
  const [step, setStep] = useState<string>(active);
  const provs = ws.catalog.providers.filter((pr) => list.some((x) => (x.routes.length ? x.routes : x.providers).includes(pr.id))).sort((a, b) => Number(b.ready) - Number(a.ready));
  const label = (id: string) => providerLabel(ws, id);
  const routes = (x: Model) => (x.routes.length ? x.routes : x.providers);
  if (!step) {
    return (
      <div className="flxst-mp">
        <div className="flxst-mp-h">{t('Provider')}</div>
        {provs.map((pr) => (
          <button key={pr.id} type="button" className={'flxst-mp-i' + (pr.id === active ? ' is-on' : '') + (pr.ready ? '' : ' is-off')} onClick={() => setStep(pr.id)}>
            <span>{pr.label}</span>
            <small>{pr.ready ? t('{n} models', { n: list.filter((x) => routes(x).includes(pr.id)).length }) : t('no key')}</small>
            <Ic n="chevR" size={14} />
          </button>
        ))}
      </div>
    );
  }
  return (
    <div className="flxst-mp">
      <button type="button" className="flxst-mp-back" onClick={() => setStep('')}>
        <Ic n="chevL" size={14} />
        <span>{label(step)}</span>
      </button>
      {list
        .filter((x) => routes(x).includes(step))
        .map((x) => {
          const ok = x.providers.includes(step);
          const on = x.id === c.model && step === active;
          return (
            <button key={x.id} type="button" className={'flxst-mp-i' + (on ? ' is-on' : '') + (ok ? '' : ' is-off')} disabled={!ok} title={ok ? '' : t('No {p} key (Flux AI & MCP › Settings › Images and video).', { p: label(step) })} onClick={() => onPick(x.id, step)}>
              <span>{x.label}</span>
              {on && <Ic n="check" size={14} />}
            </button>
          );
        })}
    </div>
  );
}

function SaveTemplate({ cfg: c, refs, prompt, onClose, onSaved }: { cfg: Cfg; refs: CRef[]; prompt: string; onClose: () => void; onSaved: () => void }) {
  const t = useT();
  const ui = useUi();
  const { client } = useGraphQL();
  const [name, setName] = useState('');
  const [shared, setShared] = useState(false);
  const [busy, setBusy] = useState(false);
  const save = async () => {
    if (!name.trim()) return;
    setBusy(true);
    try {
      await call(
        client
          .request(FlxStudioTemplateCreate, { input: { name: name.trim(), shared, prompt, params: { kind: toKind(c.kind), model: c.model, aspect: c.aspect, resolution: c.resolution, quality: c.quality, count: c.count, duration: c.duration, audio: c.audio, refs: refPayload(refs) } } })
          .then((x) => x.flxStudioTemplateCreate),
      );
      onSaved();
      onClose();
      ui.toast(t('Template saved'));
    } catch (e) {
      ui.fail(e);
      setBusy(false);
    }
  };
  return (
    <Modal
      title={t('Save as template')}
      onClose={onClose}
      foot={
        <>
          <Btn label={t('Cancel')} onClick={onClose} />
          <Btn label={t('Save')} cls="is-primary" onClick={() => void save()} disabled={busy} />
        </>
      }
    >
      <div className="flxst-field">
        <label>{t('Name')}</label>
        <input className="flxst-input" type="text" value={name} placeholder={t('E.g. Event poster 4:5')} onChange={(e) => setName(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && void save()} />
      </div>
      <label className="flxst-chk">
        <input type="checkbox" checked={shared} onChange={(e) => setShared(e.target.checked)} />
        <span>{t('Share with team')}</span>
      </label>
      <p className="flxst-muted">{t('Prompt, model, ratio, resolution and references are saved.')}</p>
    </Modal>
  );
}

function TemplateList({ ws, onClose, onChanged }: { ws: Workspace; onClose: () => void; onChanged: () => void }) {
  const t = useT();
  const ui = useUi();
  const { client } = useGraphQL();
  const [list, setList] = useState(ws.templates);
  return (
    <Modal title={t('Templates')} onClose={onClose}>
      <div className="flxst-list">
        {list.map((tp) => {
          const mine = tp.ownerId === ws.userId || ws.canManage;
          return (
            <div key={tp.databaseId} className="flxst-list-i">
              <Ic n="template" size={16} />
              <span className="flxst-grow">{tp.name}</span>
              {mine && (
                <IconBtn
                  icon="trash"
                  title={t('Delete')}
                  onClick={() => {
                    void call(client.request(FlxStudioTemplateDelete, { input: { id: tp.databaseId } }).then((x) => x.flxStudioTemplateDelete)).then((r) => {
                      setList(r.templates ?? []);
                      onChanged();
                    }, ui.fail);
                  }}
                />
              )}
            </div>
          );
        })}
      </div>
    </Modal>
  );
}
