// Dialogs of the Studio screen: gallery picker, Brainstorm, people, settings.
import { useState } from 'react';
import { useGraphQL } from '@fluxwp/graphql/react';
import { FlxStudioBrainstorm, FlxStudioItems, FlxStudioPersonCreate, FlxStudioPersonDelete, FlxStudioPersonRename, FlxStudioRefCreate, FlxStudioRefDelete, FlxStudioSettingsUpdate } from '@fluxwp/graphql/operations';
import { useQuery } from '@tanstack/react-query';
import { useHost } from '../host';
import { Btn, Ic, IconBtn, Modal, Select, useT, useUi } from './ui';
import { call, modelsOf, money, refPayload, toKind, uploadFile, type Cfg, type CRef, type Item, type Model, type Workspace } from './lib';

export function GalleryPicker({ onClose, onPick }: { onClose: () => void; onPick: (items: Item[]) => void }) {
  const t = useT();
  const { client, scope } = useGraphQL();
  const [chosen, setChosen] = useState<Map<number, Item>>(new Map());
  const q = useQuery({ queryKey: [scope, 'gql', 'FlxStudioPicker'], queryFn: () => client.request(FlxStudioItems, { first: 100, kind: 'IMAGE' }), staleTime: 0 });
  const rows = (q.data?.flxStudioItems.nodes ?? []).filter((x) => x.state === 'DONE');
  return (
    <Modal
      title={t('Pick references from gallery')}
      wide
      onClose={onClose}
      foot={
        <>
          <Btn label={t('Cancel')} onClick={onClose} />
          <Btn
            label={t('Add')}
            cls="is-primary"
            onClick={() => {
              onPick([...chosen.values()]);
              onClose();
            }}
          />
        </>
      }
    >
      <div className="flxst-pick">
        {q.isLoading && <div className="flxst-spin" />}
        {rows.map((it) => {
          const on = chosen.has(it.databaseId);
          return (
            <button
              key={it.databaseId}
              type="button"
              className={'flxst-pick-i' + (on ? ' is-sel' : '')}
              onClick={() =>
                setChosen((m) => {
                  const n = new Map(m);
                  if (on) n.delete(it.databaseId);
                  else n.set(it.databaseId, it);
                  return n;
                })
              }
            >
              <img src={it.thumb ?? ''} alt="" loading="lazy" />
              <span className="flxst-check">
                <Ic n="check" size={14} />
              </span>
            </button>
          );
        })}
      </div>
      {!q.isLoading && !rows.length && <p className="flxst-muted">{t('No images yet.')}</p>}
    </Modal>
  );
}

interface IdeaRow {
  title: string;
  prompt: string;
  on: boolean;
}

export function Brainstorm({ ws, cfg, model, refs, brief0, locale, onRun, onClose }: { ws: Workspace; cfg: Cfg; model: Model; refs: CRef[]; brief0: string; locale: string; onRun: (idea: { prompt: string; title: string; batch: string }) => Promise<boolean>; onClose: () => void }) {
  const t = useT();
  const ui = useUi();
  const { client } = useGraphQL();
  const max = Math.max(2, Math.min(8, ws.settings.maxBatch));
  const [brief, setBrief] = useState(brief0);
  const [count, setCount] = useState(Math.min(4, max));
  const [style, setStyle] = useState<'SIMPLE' | 'YOUTUBE'>('SIMPLE');
  const [busy, setBusy] = useState(false);
  const [ideas, setIdeas] = useState<IdeaRow[]>([]);
  const [batch, setBatch] = useState('');
  const [sending, setSending] = useState<{ i: number; n: number } | null>(null);

  const go = async () => {
    if (!brief.trim()) return;
    setBusy(true);
    setIdeas([]);
    try {
      const r = await call(client.request(FlxStudioBrainstorm, { input: { brief, count, style, kind: toKind(cfg.kind), refs: refPayload(refs) } }).then((x) => x.flxStudioBrainstorm));
      setBatch(r.batch ?? '');
      setIdeas((r.ideas ?? []).map((x) => ({ title: x.title, prompt: x.prompt, on: true })));
    } catch (e) {
      ui.fail(e);
    } finally {
      setBusy(false);
    }
  };
  const todo = ideas.filter((x) => x.on && x.prompt.trim());
  const unit = (model.prices.find((x) => x.resolution === cfg.resolution)?.usd ?? 0) * (cfg.kind === 'video' ? cfg.duration || 5 : 1);
  const run = async () => {
    let ok = 0;
    for (let i = 0; i < todo.length; i++) {
      const idea = todo[i];
      if (!idea) continue;
      setSending({ i: i + 1, n: todo.length });
      if (await onRun({ prompt: idea.prompt, title: idea.title, batch })) ok++;
    }
    setSending(null);
    onClose();
    if (ok) ui.toast(t('{n} generations started', { n: ok }));
  };
  const upd = (i: number, patch: Partial<IdeaRow>) => setIdeas((l) => l.map((x, j) => (j === i ? { ...x, ...patch } : x)));

  return (
    <Modal
      title={t('Brainstorm')}
      wide
      onClose={onClose}
      foot={
        <>
          <Btn label={t('Close')} onClick={onClose} />
          {ideas.length > 0 && (
            <button type="button" className="flxst-btn is-primary" disabled={!todo.length || !!sending} onClick={() => void run()}>
              <span>{sending ? t('Sending {i}/{n}', sending) : t('Generate selected ({n})', { n: todo.length })}</span>
              {!sending && unit > 0 && <small>~{money(unit * todo.length, locale)}</small>}
            </button>
          )}
        </>
      }
    >
      <p className="flxst-muted">{t('Creates distinct prompts from one idea. Pick the ones you like and generate them in one go. Model and ratio come from the generation bar.')}</p>
      <div className="flxst-field">
        <label>{t('Idea')}</label>
        <textarea className="flxst-input" rows={3} value={brief} placeholder={t('Describe the idea: e.g. Instagram post for the 11 November tree planting event')} onChange={(e) => setBrief(e.target.value)} />
      </div>
      <div className="flxst-row">
        <Select label={t('Number of ideas')} value={count} options={Array.from({ length: max - 1 }, (_, i) => [i + 2, t('{n} ideas', { n: i + 2 })] as [number, string])} onChange={(v) => setCount(+v)} />
        <Select label={t('Style')} value={style} options={[['SIMPLE', t('General')], ['YOUTUBE', t('YouTube thumbnail')]]} onChange={(v) => setStyle(v === 'YOUTUBE' ? 'YOUTUBE' : 'SIMPLE')} />
        <span className="flxst-grow" />
        <button type="button" className={'flxst-btn is-primary' + (busy ? ' is-busy' : '')} disabled={busy} onClick={() => void go()}>
          <Ic n="bulb" size={16} />
          <span>{t('Generate ideas')}</span>
        </button>
      </div>
      <div className="flxst-ideas">
        {busy && (
          <div className="flxst-pend">
            <div className="flxst-spin" />
            <span>{t('Preparing ideas…')}</span>
          </div>
        )}
        {ideas.map((idea, i) => (
          <div key={i} className={'flxst-idea' + (idea.on ? ' is-on' : '')}>
            <label className="flxst-chk">
              <input type="checkbox" checked={idea.on} onChange={(e) => upd(i, { on: e.target.checked })} />
              <input className="flxst-input is-title" type="text" value={idea.title} aria-label={t('Title')} onChange={(e) => upd(i, { title: e.target.value })} />
            </label>
            <textarea className="flxst-input" rows={4} value={idea.prompt} onChange={(e) => upd(i, { prompt: e.target.value })} />
          </div>
        ))}
      </div>
    </Modal>
  );
}

export function People({ ws, addNew, onClose, onChanged }: { ws: Workspace; addNew: boolean; onClose: () => void; onChanged: () => void }) {
  const t = useT();
  const ui = useUi();
  const host = useHost();
  const { client } = useGraphQL();
  const run = (p: Promise<unknown>) => p.then(onChanged, (e: unknown) => ui.fail(e));
  const add = async () => {
    const name = await ui.prompt(t('New person'), '', t('Add'));
    if (name) await run(call(client.request(FlxStudioPersonCreate, { input: { name } }).then((x) => x.flxStudioPersonCreate)));
  };
  const addPhotos = (personId: number, name: string, files: File[]) =>
    run(
      Promise.all(
        files.map(async (f) => {
          const uploadId = await uploadFile(host.client, 'flx-studio/reference', f, f.name);
          await call(client.request(FlxStudioRefCreate, { input: { uploadId, personId, role: 'identity', name } }).then((x) => x.flxStudioRefCreate));
        }),
      ),
    );
  const [started, setStarted] = useState(false);
  if (addNew && !started) {
    setStarted(true);
    void add();
  }
  return (
    <Modal
      title={t('People')}
      wide
      onClose={onClose}
      foot={
        <>
          <Btn label={t('Close')} onClick={onClose} />
          <Btn icon="plus" label={t('Add person')} cls="is-primary" onClick={() => void add()} />
        </>
      }
    >
      <div className="flxst-plist">
        {!ws.people.length && <p className="flxst-muted">{t('No people yet. Add a person and upload 1-4 clear face photos; typing @name in the prompt sends them as identity references.')}</p>}
        {ws.people.map((pe) => {
          const mine = pe.ownerId === ws.userId || ws.canManage;
          return (
            <div key={pe.databaseId} className="flxst-pcard">
              <div className="flxst-pcard-h">
                <strong>{pe.name}</strong>
                <small className="flxst-muted">{t('{n} photos', { n: pe.photos.length })}</small>
                <span className="flxst-grow" />
                {mine && (
                  <IconBtn
                    icon="edit"
                    title={t('Rename')}
                    cls="is-xs"
                    onClick={() => {
                      void ui.prompt(t('Person name'), pe.name).then((n) => {
                        if (n) void run(call(client.request(FlxStudioPersonRename, { input: { id: pe.databaseId, name: n } }).then((x) => x.flxStudioPersonRename)));
                      });
                    }}
                  />
                )}
                {mine && (
                  <IconBtn
                    icon="trash"
                    title={t('Delete person')}
                    cls="is-xs"
                    onClick={() => {
                      void ui.confirm(t('Delete {name}?', { name: pe.name }), t('The person and their photos are deleted. Earlier generations stay.'), t('Delete'), true).then((ok) => {
                        if (ok) void run(call(client.request(FlxStudioPersonDelete, { input: { id: pe.databaseId } }).then((x) => x.flxStudioPersonDelete)));
                      });
                    }}
                  />
                )}
              </div>
              <div className="flxst-pphotos">
                {pe.photos.map((ph) => (
                  <div key={ph.databaseId} className="flxst-pphoto">
                    <img src={ph.thumb} alt="" />
                    {mine && (
                      <button type="button" className="flxst-ref-x" title={t('Delete photo')} aria-label={t('Delete photo')} onClick={() => void run(call(client.request(FlxStudioRefDelete, { input: { id: ph.databaseId } }).then((x) => x.flxStudioRefDelete)))}>
                        <Ic n="x" size={12} />
                      </button>
                    )}
                  </div>
                ))}
                {mine && pe.photos.length < 8 && (
                  <label className="flxst-ref-add" title={t('Add photo')}>
                    <Ic n="plus" size={18} />
                    <input
                      type="file"
                      hidden
                      multiple
                      accept="image/png,image/jpeg,image/webp"
                      onChange={(e) => {
                        void addPhotos(pe.databaseId, pe.name, Array.from(e.target.files ?? []));
                        e.target.value = '';
                      }}
                    />
                  </label>
                )}
              </div>
            </div>
          );
        })}
      </div>
    </Modal>
  );
}

export function SettingsDialog({ ws, onClose, onChanged }: { ws: Workspace; onClose: () => void; onChanged: () => void }) {
  const t = useT();
  const ui = useUi();
  const { client } = useGraphQL();
  const s = ws.settings;
  const [imageModel, setImageModel] = useState(s.imageModel ?? ws.catalog.defaultImage ?? '');
  const [videoModel, setVideoModel] = useState(s.videoModel ?? ws.catalog.defaultVideo ?? '');
  const [textModel, setTextModel] = useState(s.textModel ?? '');
  const [maxCount, setMaxCount] = useState(String(s.maxCount));
  const [maxBatch, setMaxBatch] = useState(String(s.maxBatch));
  const [shared, setShared] = useState(s.sharedPeople);
  const [busy, setBusy] = useState(false);
  const save = async () => {
    setBusy(true);
    try {
      await call(client.request(FlxStudioSettingsUpdate, { input: { imageModel, videoModel, textModel: textModel.trim(), maxCount: Number(maxCount), maxBatch: Number(maxBatch), sharedPeople: shared } }).then((x) => x.flxStudioSettingsUpdate));
      onChanged();
      onClose();
      ui.toast(t('Settings saved'));
    } catch (e) {
      ui.fail(e);
      setBusy(false);
    }
  };
  const opts = (kind: 'image' | 'video'): Array<[string, string]> => modelsOf(ws, kind).map((m) => [m.id, m.label]);
  return (
    <Modal
      title={t('Studio settings')}
      onClose={onClose}
      foot={
        <>
          <Btn label={t('Cancel')} onClick={onClose} />
          <Btn label={t('Save')} cls="is-primary" onClick={() => void save()} disabled={busy} />
        </>
      }
    >
      <div className="flxst-field">
        <label>{t('Default image model')}</label>
        <Select label={t('Default image model')} value={imageModel} options={opts('image')} onChange={setImageModel} />
      </div>
      <div className="flxst-field">
        <label>{t('Default video model')}</label>
        <Select label={t('Default video model')} value={videoModel} options={opts('video')} onChange={setVideoModel} />
      </div>
      <div className="flxst-field">
        <label>{t('Enhance / Brainstorm text model')}</label>
        <input className="flxst-input" type="text" value={textModel} placeholder={t('Empty: Flux AI default')} onChange={(e) => setTextModel(e.target.value)} />
      </div>
      <div className="flxst-row">
        <div className="flxst-field">
          <label>{t('Max images per request')}</label>
          <input className="flxst-input" type="number" min={1} max={8} value={maxCount} onChange={(e) => setMaxCount(e.target.value)} />
        </div>
        <div className="flxst-field">
          <label>{t('Max brainstorm ideas')}</label>
          <input className="flxst-input" type="number" min={1} max={40} value={maxBatch} onChange={(e) => setMaxBatch(e.target.value)} />
        </div>
      </div>
      <label className="flxst-chk">
        <input type="checkbox" checked={shared} onChange={(e) => setShared(e.target.checked)} />
        <span>{t('Share people with all Studio users')}</span>
      </label>
      <p className="flxst-muted">
        {t('Keys, providers and the spending limit are in Flux AI & MCP › Settings.')} {ws.setupUrl && <a href={ws.setupUrl}>{t('Flux AI settings')}</a>}
      </p>
    </Modal>
  );
}
