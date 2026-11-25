// Forms › New form / Edit: the form's Flux Forms blocks in a block editor of their own (the same blocks as in the page
// editor, so the form looks the same in both). wp-admin's Emsfb_create page loads the editor
// (plugins/flx-forms/includes/flx/blocks/src/builder.js → window.flxfBuilder); this screen reads and saves the form
// over flxFormsBuilderForm / flxFormsBuilderSave and starts a new form blank or from a template.
import { useCallback, useEffect, useRef, useState } from 'react';
import type { FlxFormsBuilderTemplatesQuery } from '@fluxwp/graphql/types';
import { useFlxMutation, useFlxQuery } from '@fluxwp/graphql/react';
import { FlxFormsBuilderForm, FlxFormsBuilderSave, FlxFormsBuilderTemplates } from '@fluxwp/graphql/operations';
import { Loading, Modal } from '@fluxwp/ui';
import { copyText, errorText, typeLabel, useForms } from './common';

type Structure = unknown[];

interface BuilderHandle {
  value(): { name: string; structure: Structure } | null;
  setId(id: number): void;
  setName(name: string): void;
  destroy(): void;
}

interface BuilderApi {
  mount(el: HTMLElement, opts: { id?: number; name?: string; structure?: Structure; onChange?: () => void }): BuilderHandle;
}

declare global {
  interface Window {
    flxfBuilder?: BuilderApi;
  }
}

/** The block editor is on this page (wp-admin Forms › New form). */
export function builderHere(): boolean {
  return typeof window !== 'undefined' && !!window.flxfBuilder;
}

type Template = FlxFormsBuilderTemplatesQuery['flxFormsBuilderTemplates'][number];

interface Start {
  id: number;
  name: string;
  structure: Structure;
  hash: string;
}

export function BuilderPage({ form }: { form: number }) {
  const { t } = useForms();
  if (!builderHere()) return <p className="flxfrm-muted">{t('The form builder opens in wp-admin.')}</p>;
  return form > 0 ? <EditForm id={form} /> : <NewForm />;
}

function EditForm({ id }: { id: number }) {
  const { t } = useForms();
  const q = useFlxQuery(FlxFormsBuilderForm, { id });
  if (q.isError) return <p className="flxfrm-bad">{errorText(q.error, t('Something went wrong.'))}</p>;
  if (!q.data) return <Loading />;
  const f = q.data.flxFormsBuilderForm;
  if (!f) return <p className="flxfrm-bad">{t('Form not found.')}</p>;
  return <Editor start={{ id: f.id, name: f.name, structure: Array.isArray(f.structure) ? (f.structure as Structure) : [], hash: f.hash }} />;
}

function NewForm() {
  const { t } = useForms();
  const q = useFlxQuery(FlxFormsBuilderTemplates, {});
  const [start, setStart] = useState<Start | null>(null);
  if (start) return <Editor start={start} />;
  if (q.isError) return <p className="flxfrm-bad">{errorText(q.error, t('Something went wrong.'))}</p>;
  if (!q.data) return <Loading />;
  const list = q.data.flxFormsBuilderTemplates;
  const pick = (tpl: Template | null) =>
    setStart({ id: 0, name: tpl ? tpl.title : '', structure: tpl && Array.isArray(tpl.structure) ? (tpl.structure as Structure) : [], hash: '' });
  return (
    <div>
      <p className="flxfrm-muted flxfrm-lead">{t('Start with a blank form or a template. You can change everything after.')}</p>
      <div className="flxfrm-templates">
        <button type="button" className="flxfrm-template" onClick={() => pick(null)}>
          <strong>{t('Blank form')}</strong>
          <span className="flxfrm-muted">{t('Name, email and message fields to start with.')}</span>
        </button>
        {list.map((tpl) => (
          <button key={tpl.key} type="button" className="flxfrm-template" onClick={() => pick(tpl)}>
            <strong>{tpl.title}</strong>
            <span className="flxfrm-badge">{typeLabel(tpl.type, t)}</span>
            {tpl.description ? <span className="flxfrm-muted">{tpl.description}</span> : null}
          </button>
        ))}
      </div>
    </div>
  );
}

function Editor({ start }: { start: Start }) {
  const { t, toast, go } = useForms();
  const save = useFlxMutation(FlxFormsBuilderSave);
  const box = useRef<HTMLDivElement>(null);
  const ed = useRef<BuilderHandle | null>(null);
  const idRef = useRef(start.id);
  const hashRef = useRef(start.hash);
  const [id, setId] = useState(start.id);
  const [name, setName] = useState(start.name);
  const [dirty, setDirty] = useState(start.id === 0);
  // What was last saved (or opened): the blocks settle their attributes right after opening, so "changed" compares
  // with this rather than counting every editor change.
  const saved = useRef<string | null>(null);
  const snap = () => JSON.stringify(ed.current?.value() ?? null);
  const [conflict, setConflict] = useState(false);
  const leaving = useRef(false);

  useEffect(() => {
    if (!box.current || !window.flxfBuilder) return;
    const h = window.flxfBuilder.mount(box.current, {
      id: start.id,
      name: start.name,
      structure: start.structure,
      onChange: () => {
        setName(h.value()?.name ?? '');
        if (saved.current !== null) setDirty(idRef.current === 0 || JSON.stringify(h.value()) !== saved.current);
      },
    });
    ed.current = h;
    const settle = window.setTimeout(() => {
      saved.current = snap();
    }, 400);
    return () => {
      window.clearTimeout(settle);
      h.destroy();
      ed.current = null;
    };
    // The editor keeps its own state after it opens.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Unsaved changes: the browser asks before leaving.
  useEffect(() => {
    if (!dirty) return;
    const on = (e: BeforeUnloadEvent) => {
      if (leaving.current) return;
      e.preventDefault();
      e.returnValue = '';
    };
    window.addEventListener('beforeunload', on);
    return () => window.removeEventListener('beforeunload', on);
  }, [dirty]);

  const submit = useCallback(
    async (force = false) => {
      const v = ed.current?.value();
      if (!v || save.isPending) return;
      try {
        const r = (await save.mutateAsync({ input: { id: idRef.current, name: v.name, structure: v.structure, hash: hashRef.current || null, force } })).flxFormsBuilderSave;
        const errs = r?.errors ?? [];
        if (errs.some((e) => e.code === 'CONFLICT')) {
          setConflict(true);
          return;
        }
        if (errs.length) throw new Error(errs.map((e) => e.message).join(' '));
        if (!r?.id) throw new Error(t('The form could not be saved.'));
        hashRef.current = r.hash ?? '';
        saved.current = JSON.stringify({ ...v, name: r.name ?? v.name });
        setConflict(false);
        setDirty(false);
        setName(r.name ?? v.name);
        if (!idRef.current) {
          idRef.current = r.id;
          setId(r.id);
          ed.current?.setId(r.id);
          const url = new URL(window.location.href);
          url.searchParams.set('form', String(r.id));
          url.hash = '';
          window.history.replaceState(null, '', url.toString());
        }
        toast(t('Form saved.'));
      } catch (e) {
        toast(errorText(e, t('The form could not be saved.')), 'error');
      }
    },
    [save, t, toast],
  );

  // Ctrl/Cmd+S saves.
  useEffect(() => {
    const on = (e: KeyboardEvent) => {
      if ((e.ctrlKey || e.metaKey) && !e.altKey && e.key.toLowerCase() === 's') {
        e.preventDefault();
        void submit();
      }
    };
    window.addEventListener('keydown', on);
    return () => window.removeEventListener('keydown', on);
  }, [submit]);

  const shortcode = id ? '[EMS_Form_Builder id=' + id + ']' : '';

  return (
    <div className="flxfrm-builder">
      <div className="flxfrm-toolbar flxfrm-builder__bar">
        <label className="flxfrm-builder__name">
          <span className="screen-reader-text">{t('Form name')}</span>
          <input
            type="text"
            value={name}
            placeholder={t('Untitled form')}
            onChange={(e) => {
              setName(e.target.value);
              ed.current?.setName(e.target.value);
            }}
          />
        </label>
        <div className="flxfrm-actions">
          {id ? (
            <>
              <button type="button" className="flxfrm-code" title={t('Copy shortcode')} onClick={() => void copyText(shortcode, toast, t)}>
                {shortcode}
              </button>
              <button type="button" className="flxfrm-btn" onClick={() => go('responses', { form: id })}>
                {t('Responses')}
              </button>
            </>
          ) : null}
          <span className="flxfrm-muted" aria-live="polite">
            {dirty ? t('Unsaved changes') : t('Saved')}
          </span>
          <button type="button" className="flxfrm-btn is-primary" disabled={save.isPending} onClick={() => void submit()}>
            {save.isPending ? t('Saving…') : t('Save')}
          </button>
        </div>
      </div>
      <div ref={box} className="flxfrm-builder__editor" />
      {conflict ? (
        <Modal title={t('The form was changed')} onClose={() => setConflict(false)}>
          <p>{t('This form was changed somewhere else after you opened it. Save your version over it, or reload to see the other one (your changes here are lost).')}</p>
          <div className="flxfrm-actions is-end">
            <button type="button" className="flxfrm-btn" onClick={() => {
                leaving.current = true;
                window.location.reload();
              }}>
              {t('Reload')}
            </button>
            <button type="button" className="flxfrm-btn is-primary" disabled={save.isPending} onClick={() => void submit(true)}>
              {t('Save my version')}
            </button>
          </div>
        </Modal>
      ) : null}
    </div>
  );
}
