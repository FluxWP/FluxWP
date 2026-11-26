// New certificate (list based): the classic page-create.php + assets/js/create.js layout. Template cards, people
// rows with shared fields ("Spread over people"), optional sending, a live PDF preview on the right and the
// progress window that can carry on in the background. Classic classes, styled by the plugin's admin.css.
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import type { ApiClient } from '@fluxwp/core';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import {
  FlxCertificateAttachFiles,
  FlxCertificateCreate,
  FlxCertificateCreateConfig,
  FlxCertificateOne,
  FlxCertificatePeople,
  FlxCertificateRecords,
  FlxCertificateSend,
  FlxCertificateUpdate,
} from '@fluxwp/graphql/operations';
import type { FlxCertificateChannel, FlxCertificateCreateConfigQuery, FlxCertificateOneQuery } from '@fluxwp/graphql/types';
import { useHost } from '../host';
import type { Translate } from './i18n';
import { bytesToBlob, ensureRenderer, mergePdfs, type FlxsRenderApi } from './render';
import { Forward, HeadActions, admin, ask, fmtPhone, normPhone, useHead, validEmail, type Notice } from './shared';

type Config = FlxCertificateCreateConfigQuery['flxCertificateCreateConfig'];
type Tpl = Config['templates'][number];
interface Field {
  key: string;
  label: string;
  type: string;
  required?: boolean;
  placeholder?: string;
  default?: string;
  options?: string;
}
interface TplConfig {
  fields?: Field[];
  page?: { w?: number; h?: number };
}
interface Made {
  id: number;
  number: string;
  title: string;
  code: string;
  url: string;
  pdf: string;
  image: string;
  bytes: Uint8Array;
  name: string;
  phone: string;
  msgs: Array<{ channel: string; ok: boolean; message: string }>;
}
interface Draft {
  uid: number;
  personId: number;
  name: string;
  phone: string;
  email: string;
  values: Record<string, string>;
  result: Made | null;
  step?: Step;
  error?: string;
}
type Step = 'wait' | 'save' | 'pdf' | 'upload' | 'send' | 'done' | 'err';
interface Job {
  id: number;
  rows: Draft[];
  done: Made[];
  finished: boolean;
  detached: boolean;
  hidden: boolean;
  edit: boolean;
}
type Person = { databaseId: number; name: string; email?: string | null; phone?: { digits: string; display: string } | null };

const NOINPUT = ['static', 'qr', 'number_auto'];
const CHANNELS: FlxCertificateChannel[] = ['WHATSAPP', 'SMS', 'EMAIL'];
const STEP: Record<Step, string> = { wait: 'In line', save: 'Saving', pdf: 'Preparing the PDF', upload: 'Uploading', send: 'Queueing for sending', done: 'Ready', err: 'Error' };

let uidSeq = 0;
const newRow = (d: Partial<Draft> = {}): Draft => ({ uid: ++uidSeq, personId: d.personId || 0, name: d.name || '', phone: d.phone || '', email: d.email || '', values: d.values || {}, result: null });

function parse(config: string): TplConfig {
  try {
    return JSON.parse(config) as TplConfig;
  } catch {
    return {};
  }
}
function store<T>(key: string, fallback: T): T {
  try {
    const v = localStorage.getItem(key);
    return v === null ? fallback : (JSON.parse(v) as T);
  } catch {
    return fallback;
  }
}
function save(key: string, v: unknown) {
  try {
    localStorage.setItem(key, JSON.stringify(v));
  } catch {
    /* storage off */
  }
}
/** A stored person's phone as typed in Turkey (0532…) or with its country code. */
const typedPhone = (digits: string) => (!digits ? '' : digits.indexOf('90') === 0 ? '0' + digits.slice(2) : '+' + digits);

function useDebounced<T>(value: T, ms: number): T {
  const [v, setV] = useState(value);
  useEffect(() => {
    const id = setTimeout(() => setV(value), ms);
    return () => clearTimeout(id);
  }, [value, ms]);
  return v;
}

export function Create({ t, id, onNotice, onNew }: { t: Translate; id?: number; onNotice: (n: Notice) => void; onNew: () => void }) {
  const cfg = useFlxQuery(FlxCertificateCreateConfig, { certificateId: id ?? null });
  const existing = useFlxQuery(FlxCertificateOne, { id: id ?? 0 }, { enabled: !!id });
  const edit = id ? existing.data?.flxCertificate : undefined;
  useHead(id ? t('Edit certificate') : t('New certificate'), id ? edit?.number || undefined : t('Add people to the list; each one gets a certificate and a link of their own. Live preview on the right.'));
  const config = cfg.data?.flxCertificateCreateConfig;
  if (cfg.isLoading || (id && existing.isLoading)) return null;
  if (!config?.templates.length) {
    return (
      <div className="notice notice-warning">
        <p>{t('No active template.')}</p>
      </div>
    );
  }
  return (
    <>
      {edit ? (
        <HeadActions>
          <a href={edit.pageUrl} className="button" target="_blank" rel="noreferrer">
            {t('Open page')}
          </a>
        </HeadActions>
      ) : null}
      <Form key={edit?.id ?? 'new'} t={t} config={config} edit={edit ?? null} onNotice={onNotice} onNew={onNew} />
    </>
  );
}

type Edit = NonNullable<FlxCertificateOneQuery['flxCertificate']>;

function Form({ t, config, edit, onNotice, onNew }: { t: Translate; config: Config; edit: Edit | null; onNotice: (n: Notice) => void; onNew: () => void }) {
  const host = useHost();
  const qc = useQueryClient();
  const { scope, client } = useGraphQL();
  const create = useFlxMutation(FlxCertificateCreate);
  const update = useFlxMutation(FlxCertificateUpdate);
  const attach = useFlxMutation(FlxCertificateAttachFiles);
  const send = useFlxMutation(FlxCertificateSend);
  const today = config.today;

  const [tplId, setTplId] = useState<number>(() => {
    if (edit) return edit.templateId;
    const last = store<number>('flxs_tpl', 0) || config.lastTemplateId;
    return config.templates.some((x) => x.id === last) ? last : (config.templates[0]?.id ?? 0);
  });
  const tpl: Tpl | undefined = config.templates.find((x) => x.id === tplId) ?? config.templates[0];
  const tc = useMemo(() => parse(tpl?.config ?? '{}'), [tpl?.config]);
  const fields = useMemo(() => (tc.fields ?? []).filter((f) => !NOINPUT.includes(f.type) && f.key !== 'ad_soyad'), [tc]);
  const nameField = (tc.fields ?? []).find((f) => f.key === 'ad_soyad');

  const [rows, setRows] = useState<Draft[]>(() => {
    if (!edit) return [newRow()];
    let values: Record<string, string> = {};
    try {
      values = JSON.parse(edit.values) as Record<string, string>;
    } catch {
      values = {};
    }
    const p = edit.person && 'name' in edit.person ? edit.person : null;
    const name = values.ad_soyad || p?.name || edit.holderName;
    delete values.ad_soyad;
    return [newRow({ personId: p?.databaseId ?? 0, name, phone: typedPhone(p?.phone?.digits ?? ''), email: p?.email ?? '', values })];
  });
  const [common, setCommon] = useState<Record<string, string>>({});
  const [dist, setDist] = useState<Record<string, boolean>>(() => store('flxs_dist', {}));
  const [active, setActive] = useState(0);
  const [backdate, setBackdate] = useState(false);
  const [issuedDate, setIssuedDate] = useState(today);
  const [backOpen, setBackOpen] = useState(false);
  const [eventId, setEventId] = useState<number>(() => {
    if (edit) return edit.eventId;
    const open = config.events.filter((e) => e.status === 'OPEN');
    const saved = store<number | string | null>('flxs_event', null);
    const w = Number(saved ?? 0);
    if (w && open.some((e) => e.id === w)) return w;
    return w === 0 && saved !== null ? 0 : (open[0]?.id ?? 0);
  });
  const [referrerId, setReferrerId] = useState<number>(() => {
    if (edit) return edit.referrerId;
    const saved = Number(store<number | string | null>('flxs_ref', config.me));
    return config.referrers.some((u) => u.id === saved) || saved === 0 ? saved : config.me;
  });
  const provs = (ch: FlxCertificateChannel) => config.providers.filter((p) => p.channel === ch);
  const [channels, setChannels] = useState<FlxCertificateChannel[]>(() => {
    const saved = store<string[]>('flxs_send', []).map((c) => c.toUpperCase() as FlxCertificateChannel);
    const ok = saved.filter((c) => CHANNELS.includes(c) && provs(c).length);
    if (ok.length) return ok;
    const first = CHANNELS.find((c) => provs(c).length);
    return first && !edit ? [first] : [];
  });
  const [providers, setProviders] = useState<Record<string, number>>(() => store('flxs_send_prov', {}));
  const [showPaste, setShowPaste] = useState(false);
  const [paste, setPaste] = useState('');
  const [results, setResults] = useState<Made[]>([]);
  const [progress, setProgress] = useState('');
  const [forward, setForward] = useState(0);
  const [suggest, setSuggest] = useState<{ uid: number; list: Person[] } | null>(null);
  const [hints, setHints] = useState<Record<string, { cls: string; text: string; person?: Person }>>({});
  const jobs = useRef<Job[]>([]);
  const current = useRef<Job | null>(null);
  const [viewJob, setViewJob] = useState<Job | null>(null);
  const [, bump] = useState(0);
  const refreshJobs = () => bump((n) => n + 1);
  const jobSeq = useRef(0);
  const lookCache = useRef<Record<string, Person | null>>({});

  useEffect(() => {
    for (const r of rows) {
      checkContact(r, 'phone', true);
      checkContact(r, 'email', true);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const isBack = backdate && !!issuedDate && issuedDate < today;
  const issued = isBack ? issuedDate : today;
  const multi = rows.length > 1 && !edit;

  // Shared values start from the template (sapling count 1, dates the issue date).
  useEffect(() => {
    setCommon((cur) => {
      const next = { ...cur };
      let changed = false;
      for (const f of fields) {
        if (next[f.key] !== undefined) continue;
        let d = f.default || '';
        if (f.type === 'date') d = d || issued;
        if (!d && f.type === 'number' && /fidan/.test(f.key)) d = '1';
        next[f.key] = d;
        changed = true;
      }
      return changed ? next : cur;
    });
  }, [fields, issued]);

  // Date fields still on the old issue date follow a new one.
  const lastIssued = useRef(today);
  useEffect(() => {
    if (lastIssued.current === issued) return;
    const prev = lastIssued.current;
    setCommon((cur) => {
      const next = { ...cur };
      for (const f of fields) if (f.type === 'date' && (!next[f.key] || next[f.key] === prev)) next[f.key] = issued;
      return next;
    });
    lastIssued.current = issued;
  }, [issued, fields]);

  const distOn = (key: string) => {
    const f = fields.find((x) => x.key === key);
    return !!(f && f.type === 'number' && dist[key] && rows.length > 1 && !edit);
  };
  const distMap = (key: string) => {
    if (!distOn(key)) return null;
    const total = parseInt(common[key] ?? '', 10);
    if (isNaN(total) || total < 0) return null;
    let own = 0;
    const free: number[] = [];
    const map: Record<number, string> = {};
    for (const r of rows) {
      const x = r.values[key];
      if (x === undefined || x === '') free.push(r.uid);
      else own += parseInt(x, 10) || 0;
    }
    const left = Math.max(0, total - own);
    const base = free.length ? Math.floor(left / free.length) : 0;
    const rem = free.length ? left % free.length : 0;
    free.forEach((uid, i) => (map[uid] = String(base + (i < rem ? 1 : 0))));
    return { map, total, own, left, free: free.length, zero: free.length > left };
  };
  const rowValues = (row: Draft): Record<string, string> => {
    const v: Record<string, string> = {};
    for (const f of fields) {
      let x = row.values[f.key];
      if (x === undefined || x === '') {
        const d = distMap(f.key);
        if (d) x = d.map[row.uid];
      }
      if (x === undefined || x === '') x = common[f.key];
      if (x === undefined || x === '') x = f.default || '';
      if (f.type === 'date' && !x) x = issued;
      v[f.key] = x;
    }
    v.ad_soyad = row.name;
    return v;
  };
  const distInfo = (key: string): { text: string; warn: boolean } => {
    const d = distMap(key);
    if (!distOn(key)) return { text: t('Divides the total among the people.'), warn: false };
    if (!d) return { text: t('Enter the total.'), warn: false };
    if (!d.free) return { text: t('Everyone has a value typed in.'), warn: false };
    const groups: Record<string, number> = {};
    Object.values(d.map).forEach((v) => (groups[v] = (groups[v] || 0) + 1));
    let txt = Object.keys(groups)
      .sort((a, b) => Number(b) - Number(a))
      .map((v) => t('%1$s to %2$s people', v, groups[v] ?? 0))
      .join(', ');
    if (d.own) txt += ' ' + t('(not counting the %d typed in)', d.own);
    if (d.own > d.total) txt = t('The typed values (%d) are more than the total.', d.own) + ' ' + txt;
    if (d.zero) txt += '. ' + t('Some people get 0!');
    return { text: txt, warn: d.zero };
  };

  /* ---------------- Preview ---------------- */
  const canvas = useRef<HTMLCanvasElement>(null);
  const stage = useRef<HTMLDivElement>(null);
  const lastBytes = useRef<Uint8Array | null>(null);
  const [spin, setSpin] = useState(false);
  const [previewErr, setPreviewErr] = useState('');
  const [stageW, setStageW] = useState(600);
  useEffect(() => {
    const el = stage.current;
    if (!el) return;
    const ro = new ResizeObserver(() => setStageW(el.clientWidth || 600));
    ro.observe(el);
    return () => ro.disconnect();
  }, []);
  const row = rows[active] ?? rows[0];
  const meta = (r?: Draft): Record<string, string> => {
    if (r?.result) return { number: r.result.number, link: r.result.url, code: r.result.code, created: issued };
    if (edit) return { number: edit.number, link: edit.pageUrl, code: edit.code, created: today };
    return {};
  };
  const sig = JSON.stringify([tpl?.id, row ? rowValues(row) : {}, meta(row), !row?.result && !edit, stageW]);
  const slow = useDebounced(sig, 450);
  const token = useRef(0);
  useEffect(() => {
    if (!tpl || !config.rendererScripts.length || !row) return;
    const my = ++token.current;
    setSpin(true);
    const [, values, m, isPreview, width] = JSON.parse(slow) as [number, Record<string, string>, Record<string, string>, boolean, number];
    void ensureRenderer(config.rendererScripts, config.rendererConfig)
      .then(async (api) => {
        const bytes = await api.renderPdf(tc, values, m, { preview: isPreview });
        if (my !== token.current || !canvas.current) return;
        lastBytes.current = bytes;
        await api.renderToCanvas(bytes, canvas.current, width || 600);
        if (my === token.current) setPreviewErr('');
      })
      .catch((e: unknown) => setPreviewErr(t('Preview error: %s', e instanceof Error ? e.message : String(e))))
      .finally(() => {
        if (my === token.current) setSpin(false);
      });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [slow]);

  /* ---------------- Contact hints and suggestions ---------------- */
  const lookup = async (term: string): Promise<Person[]> => {
    const r = await client.request(FlxCertificatePeople, { search: term });
    return (r.flxCertificatePeople ?? []) as Person[];
  };
  const hintKey = (uid: number, k: string) => uid + ':' + k;
  const checkContact = (r: Draft, k: 'phone' | 'email', now = false) => {
    const v = String(r[k] || '').trim();
    const key = hintKey(r.uid, k);
    const setHint = (h: { cls: string; text: string; person?: Person } | null) =>
      setHints((cur) => {
        const next = { ...cur };
        if (h) next[key] = h;
        else delete next[key];
        return next;
      });
    if (!v) return setHint(null);
    const norm = k === 'phone' ? normPhone(v) : validEmail(v) ? v.toLowerCase() : '';
    if (!norm) return setHint({ cls: 'is-err', text: k === 'phone' ? '✗ ' + t('Number missing or wrong') : '✗ ' + t('E-mail is wrong') });
    const ok = { cls: 'is-ok', text: '✓ ' + (k === 'phone' ? fmtPhone(norm) : t('Valid e-mail')) };
    setHint(ok);
    if (r.personId) return;
    const show = (p: Person | null) => p && setHint({ ...ok, person: p });
    if (norm in lookCache.current) return show(lookCache.current[norm] ?? null);
    if (now) return;
    void lookup(norm)
      .then((list) => {
        const p = list.find((x) => (k === 'phone' ? x.phone?.digits === norm : String(x.email || '').toLowerCase() === norm)) ?? null;
        lookCache.current[norm] = p;
        show(p);
      })
      .catch(() => undefined);
  };
  const timers = useRef<Record<string, ReturnType<typeof setTimeout>>>({});
  const later = (key: string, fn: () => void, ms: number) => {
    clearTimeout(timers.current[key]);
    timers.current[key] = setTimeout(fn, ms);
  };

  const setRow = (uid: number, patch: Partial<Draft>) => setRows((cur) => cur.map((r) => (r.uid === uid ? { ...r, ...patch } : r)));
  const onField = (r: Draft, k: 'name' | 'phone' | 'email', value: string) => {
    const next = { ...r, [k]: value, personId: 0 };
    setRow(r.uid, { [k]: value, personId: 0 });
    if (k === 'name') {
      later(
        's' + r.uid,
        () => {
          if (value.trim().length < 3) return setSuggest(null);
          void lookup(value)
            .then((list) => setSuggest(list.length ? { uid: r.uid, list } : null))
            .catch(() => undefined);
        },
        300,
      );
    } else {
      setHints((cur) => ({ ...cur, [hintKey(r.uid, k)]: { cls: 'is-wait', text: '…' } }));
      later(hintKey(r.uid, k), () => checkContact(next, k), 500);
    }
  };
  const usePerson = (r: Draft, p: Person) => {
    const next = { ...r, personId: p.databaseId, name: p.name, phone: typedPhone(p.phone?.digits ?? ''), email: p.email ?? '' };
    setRow(r.uid, next);
    setSuggest(null);
    checkContact(next, 'phone', true);
    checkContact(next, 'email', true);
  };
  useEffect(() => {
    const close = (e: MouseEvent) => {
      if (!(e.target as HTMLElement).closest('.flxs-suggest')) setSuggest(null);
    };
    document.addEventListener('click', close);
    return () => document.removeEventListener('click', close);
  }, []);

  const addRow = () => {
    setRows((cur) => [...cur, newRow()]);
    setActive(rows.length);
    setTimeout(() => document.querySelector<HTMLInputElement>('#flxs-rows .flxs-row:last-child [data-k=name]')?.focus(), 0);
  };
  const delRow = (uid: number) => {
    const next = rows.filter((r) => r.uid !== uid);
    setRows(next.length ? next : [newRow()]);
    setActive((a) => Math.min(a, Math.max(0, next.length - 1)));
  };
  const applyPaste = () => {
    let added = 0;
    const list = [...rows];
    for (const line of paste.split(/\r?\n/)) {
      if (!line.trim()) continue;
      const parts = line.split(/\t|;|,(?=\s*[^\s])/).map((s) => s.trim());
      const d: Partial<Draft> & { values: Record<string, string> } = { name: '', phone: '', email: '', values: {} };
      const rest: string[] = [];
      for (const p of parts) {
        if (!p) continue;
        if (!d.email && /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(p)) d.email = p;
        else if (!d.phone && /^[+(]?[\d\s().-]+$/.test(p) && p.replace(/\D/g, '').length >= 7) d.phone = p;
        else if (!d.name) d.name = p;
        else rest.push(p);
      }
      fields.forEach((f, i) => {
        if (rest[i] !== undefined) d.values[f.key] = rest[i];
      });
      if (d.name) {
        list.push(newRow(d));
        added++;
      }
    }
    const kept = list.filter((r) => r.name || r.phone || r.email);
    setRows(kept.length ? kept : [newRow()]);
    setPaste('');
    setShowPaste(false);
    onNotice({ kind: 'ok', text: t('%d people added', added) });
  };

  /* ---------------- Save ---------------- */
  const validate = (): string[] => {
    const errs: string[] = [];
    rows.forEach((r, i) => {
      const n = rows.length > 1 ? t('Person %d:', i + 1) + ' ' : '';
      if (!r.name.trim()) errs.push(n + t('name is empty'));
      if (r.phone.trim() && !normPhone(r.phone)) errs.push(n + t('phone is wrong'));
      if (r.email.trim() && !validEmail(r.email)) errs.push(n + t('e-mail is wrong'));
      const v = rowValues(r);
      for (const f of fields) {
        if (f.required && !String(v[f.key] || '').trim()) errs.push(n + t('%s is empty', f.label));
        else if (distOn(f.key) && String(v[f.key]) === '0') errs.push(n + t('%s is 0 (the number to spread is not enough)', f.label));
      }
    });
    return errs;
  };

  const processRow = async (
    job: Job,
    r: Draft,
    snap: Record<string, string>,
    step: (s: Step) => void,
    opts: { event: number; ref: number; issued: string; channels: FlxCertificateChannel[]; providers: Record<string, number> },
  ): Promise<Made> => {
    const api: FlxsRenderApi = await ensureRenderer(config.rendererScripts, config.rendererConfig);
    step('save');
    const person = { id: r.personId || undefined, name: r.name, phone: r.phone, email: r.email };
    const values = JSON.stringify(snap);
    const payload =
      job.edit && edit
        ? (await update.mutateAsync({ input: { id: edit.databaseId, person, values, eventId: opts.event, referrerId: opts.ref } })).flxCertificateUpdate
        : (await create.mutateAsync({ input: { templateId: tpl!.id, person, values, eventId: opts.event, referrerId: opts.ref, issued: opts.issued || undefined } })).flxCertificateCreate;
    if (!payload || payload.errors.length || !payload.certificate) throw new Error(payload?.errors.map((e) => e.message).join(' ') || t('Could not save.'));
    if (payload.notes?.length) onNotice({ kind: 'ok', text: payload.notes.join(' ') });
    const cert = payload.certificate;
    r.personId = payload.person?.databaseId ?? r.personId;
    step('pdf');
    const bytes = await api.renderPdf(tc, snap, { number: cert.number, link: cert.pageUrl, code: cert.code, created: opts.issued || today });
    const pg = tc.page || {};
    const jpeg = await api.toJpeg(bytes, Number(pg.w) >= Number(pg.h) ? 1600 : 1100, 0.82);
    let ogId = '';
    step('upload');
    try {
      const og = await api.toShareImage(bytes, { title: cert.title, name: r.name, org: String((window.FLXS_RENDER?.org as string) || '') });
      ogId = await uploadFile(host.client, og.blob, 'paylasim.jpg');
    } catch {
      ogId = '';
    }
    const pdfId = await uploadFile(host.client, bytesToBlob(bytes, 'application/pdf'), 'sertifika.pdf');
    const imageId = await uploadFile(host.client, jpeg.blob, 'sertifika.jpg');
    const attached = await attach.mutateAsync({ input: { id: cert.databaseId, pdfUploadId: pdfId, imageUploadId: imageId, ogUploadId: ogId || undefined, width: jpeg.w, height: jpeg.h } });
    const files = attached.flxCertificateAttachFiles?.certificate;
    let msgs: Made['msgs'] = [];
    if (opts.channels.length) {
      step('send');
      const sent = await send.mutateAsync({
        input: { id: cert.databaseId, channels: opts.channels, providers: opts.channels.map((c) => ({ channel: c, id: opts.providers[c] || 0 })), source: 'create' },
      });
      msgs = sent.flxCertificateSend?.results ?? [];
    }
    return {
      id: cert.databaseId,
      number: cert.number,
      title: cert.title,
      code: cert.code,
      url: cert.pageUrl,
      pdf: files?.pdfUrl || cert.pdfUrl || '',
      image: files?.imageUrl || '',
      bytes,
      name: r.name,
      phone: r.phone,
      msgs,
    };
  };

  const counts = (j: Job) => {
    const c = { done: 0, err: 0, total: j.rows.length, finished: 0 };
    for (const r of j.rows) {
      if (r.step === 'done') c.done++;
      else if (r.step === 'err') c.err++;
    }
    c.finished = c.done + c.err;
    return c;
  };
  const readyText = (c: ReturnType<typeof counts>) => t('%d certificates ready', c.done) + (c.err ? ' · ' + t('%d errors', c.err) : '');

  const submit = async () => {
    if (current.current) {
      current.current.hidden = false;
      setViewJob(current.current);
      return;
    }
    const errs = validate();
    if (errs.length) {
      onNotice({ kind: 'err', text: errs.slice(0, 3).join(' · ') });
      return;
    }
    if (channels.length && !edit && !(await ask(t('%1$s people will get it by %2$s. Continue?', rows.length, channels.map(chLabel).join(' + '))))) return;
    const todo = rows.filter((r) => !r.result || !!edit);
    if (!todo.length) {
      onNotice({ kind: 'err', text: t('The certificates in this list are already made. Clear for a new list.') });
      return;
    }
    if (isBack && !(await ask(t('These certificates will be dated %s. Continue?', issued.split('-').reverse().join('.'))))) return;
    const snaps = new Map(todo.map((r) => [r.uid, rowValues(r)]));
    todo.forEach((r) => {
      r.step = 'wait';
      r.error = '';
    });
    const opts = { event: eventId, ref: referrerId, issued: isBack ? issued : '', channels: edit ? [] : channels, providers };
    const job: Job = { id: ++jobSeq.current, rows: todo, done: [], finished: false, detached: false, hidden: false, edit: !!edit };
    jobs.current.push(job);
    current.current = job;
    setViewJob(job);
    setProgress('');
    for (const r of todo) {
      if (!job.detached) setActive(rows.indexOf(r));
      try {
        const res = await processRow(
          job,
          r,
          snaps.get(r.uid) ?? {},
          (s) => {
            r.step = s;
            refreshJobs();
          },
          opts,
        );
        r.step = 'done';
        r.result = res;
        job.done.push(res);
      } catch (e) {
        r.step = 'err';
        r.error = e instanceof Error ? e.message : String(e);
      }
      refreshJobs();
    }
    job.finished = true;
    void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxCertificateRecords) });
    setResults((cur) => (job.edit ? job.done : [...cur, ...job.done]));
    if (current.current === job) {
      current.current = null;
      const c = counts(job);
      setProgress(readyText(c));
      setRows((cur) => [...cur]);
      if (!c.err) {
        jobs.current = jobs.current.filter((k) => k !== job);
        setViewJob((v) => (v === job ? null : v));
      }
    }
    refreshJobs();
  };

  const resetForm = () => {
    setBackdate(false);
    setIssuedDate(today);
    setBackOpen(false);
    setRows([newRow()]);
    setCommon({});
    setActive(0);
    setProgress('');
    setPaste('');
    setShowPaste(false);
    setHints({});
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };
  const resetAll = () => {
    if (edit) return onNew();
    if (current.current) {
      current.current.detached = true;
      current.current = null;
    }
    setResults([]);
    resetForm();
  };
  const askReset = async () => {
    const dirty = results.length || rows.some((r) => r.name || r.phone || r.email || Object.values(r.values).some(Boolean));
    if (dirty && !(await ask(t('Clear the form? The people and results leave the screen (the certificates made stay in the system).'), true))) return;
    resetAll();
  };

  const chLabel = (c: string) => (c === 'EMAIL' ? t('E-mail') : c === 'SMS' ? 'SMS' : 'WhatsApp');
  const waLink = (r: Made) => {
    let p = r.phone.replace(/\D/g, '');
    if (p.length === 11 && p[0] === '0') p = '90' + p.slice(1);
    else if (p.length === 10 && p[0] === '5') p = '90' + p;
    return 'https://wa.me/' + p + '?text=' + encodeURIComponent(t('Hello %1$s, %2$s is ready: %3$s', r.name.split(' ')[0] ?? '', r.title, r.url));
  };
  const download = (blob: Blob, name: string) => {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = name;
    a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 4000);
  };

  const input = (f: Field, value: string, onChange: (v: string) => void, extra: { placeholder?: string; dataV?: string } = {}): ReactNode => {
    const ph = extra.placeholder ?? f.placeholder ?? '';
    if (f.type === 'textarea') return <textarea rows={2} placeholder={ph} value={value} onChange={(e) => onChange(e.target.value)} data-v={extra.dataV} />;
    if (f.type === 'select') {
      const opts = String(f.options || '')
        .split('\n')
        .map((o) => o.trim())
        .filter(Boolean);
      return (
        <select value={value} onChange={(e) => onChange(e.target.value)} data-v={extra.dataV}>
          <option value=""></option>
          {opts.map((o) => (
            <option key={o}>{o}</option>
          ))}
        </select>
      );
    }
    const type = f.type === 'date' ? 'date' : f.type === 'number' ? 'number' : 'text';
    return <input type={type} value={value} placeholder={ph} onChange={(e) => onChange(e.target.value)} data-v={extra.dataV} {...(type === 'number' ? { min: 0, inputMode: 'numeric' as const } : {})} />;
  };
  const distMaps = useMemo(() => Object.fromEntries(fields.map((f) => [f.key, distMap(f.key)])), [fields, rows, common, dist]); // eslint-disable-line react-hooks/exhaustive-deps

  const queueText = t('Messages are queued and sent in the background. Follow them in the {link}.').split('{link}');
  const job = viewJob;
  const jc = job ? counts(job) : null;
  const tray = jobs.current.filter((j) => j.detached || j.hidden);
  const events = config.events;
  const submitLabel = edit ? t('Save and refresh the PDF') : rows.length > 1 ? t('Create %d certificates', rows.length) : t('Create the certificate');

  return (
    <div className="flxs-create" id="flxs-create">
      <div className="flxs-create__form">
        <section className="flxs-card">
          <h2 className="flxs-card__title">
            <span className="flxs-step">1</span> {t('Template')}
          </h2>
          <div className="flxs-tpls" id="flxs-tpls">
            {config.templates.map((x) => {
              const on = x.id === tpl?.id;
              return (
                <button
                  type="button"
                  key={x.id}
                  className={'flxs-tpl' + (on ? ' is-on' : '')}
                  disabled={!!edit && !on}
                  data-tpl={x.id}
                  onClick={(e) => {
                    if (edit) return;
                    setTplId(x.id);
                    // The remembered choice is read back from the page, not from the server data.
                    save('flxs_tpl', Number(e.currentTarget.dataset.tpl) || 0);
                  }}
                >
                  {x.thumb ? <img src={x.thumb} alt="" /> : <span className="flxs-tpl__ph dashicons dashicons-awards"></span>}
                  <span>{x.name}</span>
                </button>
              );
            })}
          </div>
        </section>

        <section className="flxs-card">
          <div className="flxs-card__head">
            <h2 className="flxs-card__title">
              <span className="flxs-step">2</span> {t('People')} <span className="flxs-count">{rows.length > 1 ? t('%d people', rows.length) : ''}</span>
            </h2>
            {!edit && (
              <span className="flxs-card__acts">
                <button type="button" className="button button-small" title={t('Clear the form')} onClick={() => void askReset()}>
                  {t('Clear')}
                </button>{' '}
                <button type="button" className="button button-small" onClick={() => setShowPaste((v) => !v)}>
                  {t('Paste from a list')}
                </button>
              </span>
            )}
          </div>
          <div className="flxs-paste" hidden={!showPaste}>
            <p className="description">
              {t('One person per line:')} <code>{t('Full name; phone; e-mail')}</code> {t('(tab-separated rows copied from Excel work too). Extra template fields follow in order, e.g. the sapling count.')}
            </p>
            <textarea rows={5} value={paste} placeholder={'Seyfullah Doğan; 0532 111 22 33; seyfullah@ornek.com; 1\nRefika Doğan; 0533 444 55 66'} onChange={(e) => setPaste(e.target.value)} />
            <p>
              <button type="button" className="button" onClick={applyPaste}>
                {t('Add to the list')}
              </button>
            </p>
          </div>
          <label className="flxs-ref flxs-ref--event">
            <span>{t('Event')}</span>
            <select
              value={eventId}
              onChange={(e) => {
                setEventId(Number(e.target.value));
                if (!edit) save('flxs_event', e.target.value);
              }}
            >
              <option value={0}>{t('— Without event —')}</option>
              {events.map((ev) => (
                <option key={ev.id} value={ev.id} disabled={ev.status === 'CLOSED' && ev.id !== (edit?.eventId ?? 0)}>
                  {ev.name}
                  {ev.status === 'CLOSED' ? ' (' + t('archive') + ')' : ''}
                </option>
              ))}
            </select>
            <small>
              {events.length ? (
                t('Saplings are collected for this event; the Dashboard and the list show them by event.')
              ) : (
                <>
                  {t('No open event.')} {config.canManage ? <a href={admin(host.adminUrl, 'page=flxs-etkinlikler&add=1')}>{t('Create an event')}</a> : null}
                </>
              )}
            </small>
          </label>
          <label className="flxs-ref">
            <span>{t('Referrer')}</span>
            <select
              value={referrerId}
              onChange={(e) => {
                setReferrerId(Number(e.target.value));
                if (!edit) save('flxs_ref', e.target.value);
              }}
            >
              <option value={0}>{t('— No referrer —')}</option>
              {config.referrers.map((u) => (
                <option key={u.id} value={u.id}>
                  {u.name}
                </option>
              ))}
            </select>
            <small>{t('On whose behalf or through whom the certificates in this list are given; used in reports.')}</small>
          </label>
          {!edit && config.canManage && (
            <details className={'flxs-backdate' + (isBack ? ' is-on' : '')} open={backOpen} onToggle={(e) => setBackOpen((e.currentTarget as HTMLDetailsElement).open)}>
              <summary>
                {t('Create backdated')} <small>{t('(for reconciliation, rarely)')}</small>
              </summary>
              <label className="flxs-check">
                <input type="checkbox" checked={backdate} onChange={(e) => setBackdate(e.target.checked)} /> {t('Create this list backdated')}
              </label>
              <input type="date" value={issuedDate} max={today} min="2015-01-01" disabled={!backdate} onChange={(e) => setIssuedDate(e.target.value)} />
              <small>{t('The date on the certificate, the year in the number, the list and the Dashboard reports use this date. Sending works as usual. Clear turns it off.')}</small>
            </details>
          )}
          <div className="flxs-common" id="flxs-common">
            {fields.length ? (
              <>
                <div className="flxs-common__title">
                  {t('Shared details')} <small>{rows.length > 1 ? t('applies to everyone; a different value in a person row wins') : ''}</small>
                </div>
                <div className="flxs-grid">
                  {fields.map((f) => {
                    const isDist = multi && f.type === 'number';
                    const info = isDist ? distInfo(f.key) : null;
                    return (
                      <div key={f.key} className={'flxs-field' + (isDist ? ' flxs-field--dist' : '')}>
                        <label>
                          <span>
                            {f.label}
                            {f.required ? ' *' : ''}
                          </span>
                          {input(f, common[f.key] || '', (v) => setCommon((c) => ({ ...c, [f.key]: v })))}
                        </label>
                        {isDist ? (
                          <>
                            <label className="flxs-dist">
                              <input
                                type="checkbox"
                                value={f.key}
                                checked={!!dist[f.key]}
                                onChange={(e) => {
                                  const next = { ...dist, [e.target.value]: e.target.checked };
                                  setDist(next);
                                  save('flxs_dist', next);
                                }}
                              />{' '}
                              {t('Spread over people')}
                            </label>
                            <small className={'flxs-dist__info' + (info?.warn ? ' is-warn' : '')}>{info?.text}</small>
                          </>
                        ) : null}
                      </div>
                    );
                  })}
                </div>
              </>
            ) : null}
          </div>
          <div
            className="flxs-rows"
            id="flxs-rows"
            onFocus={(e) => {
              const el = (e.target as HTMLElement).closest<HTMLElement>('[data-row]');
              const i = el ? rows.findIndex((r) => String(r.uid) === el.dataset.row) : -1;
              if (i >= 0 && i !== active) setActive(i);
            }}
          >
            {rows.map((r, i) => {
              const hp = hints[hintKey(r.uid, 'phone')];
              const he = hints[hintKey(r.uid, 'email')];
              const hint = (h: typeof hp) =>
                h ? (
                  <small className={'flxs-hint ' + h.cls}>
                    {h.text}
                    {h.person ? (
                      <>
                        {' · '}
                        {t('Registered:')}{' '}
                        <button type="button" className="button-link" title={t('Use the registered person')} onClick={() => usePerson(r, h.person as Person)}>
                          {h.person.name}
                        </button>
                      </>
                    ) : null}
                  </small>
                ) : (
                  <small className="flxs-hint"></small>
                );
              return (
                <div key={r.uid} className={'flxs-row' + (i === active ? ' is-active' : '') + (r.result ? ' is-done' : '')} data-row={r.uid}>
                  <div className="flxs-row__num">{i + 1}</div>
                  <div className="flxs-row__body">
                    <div className="flxs-row__main">
                      <label className="flxs-field flxs-field--name">
                        <span>{(nameField?.label || t('Full name')) + ' *'}</span>
                        <input type="text" data-k="name" value={r.name} placeholder={nameField?.placeholder || t('Full name')} autoComplete="off" onChange={(e) => onField(r, 'name', e.target.value)} />
                      </label>
                      <label className="flxs-field">
                        <span>{t('Phone')}</span>
                        <input type="tel" value={r.phone} placeholder="0532 123 45 67" autoComplete="off" inputMode="tel" onChange={(e) => onField(r, 'phone', e.target.value)} />
                        {hint(hp)}
                      </label>
                      <label className="flxs-field">
                        <span>{t('E-mail')}</span>
                        <input type="email" value={r.email} placeholder="ornek@eposta.com" autoComplete="off" inputMode="email" onChange={(e) => onField(r, 'email', e.target.value)} />
                        {hint(he)}
                      </label>
                    </div>
                    {fields.length && (rows.length > 1 || edit) ? (
                      <div className="flxs-row__extra">
                        {fields.map((f) => {
                          const dm = distMaps[f.key];
                          const ph = f.type === 'date' ? undefined : dm && dm.map[r.uid] !== undefined ? t('spread: %s', dm.map[r.uid] ?? '') : common[f.key] ? t('shared: %s', common[f.key] ?? '') : '';
                          return (
                            <label key={f.key} className="flxs-field flxs-field--sm">
                              <span>{f.label}</span>
                              {input(f, r.values[f.key] || '', (v) => setRow(r.uid, { values: { ...r.values, [f.key]: v } }), { placeholder: ph, dataV: f.key })}
                            </label>
                          );
                        })}
                      </div>
                    ) : null}
                    {r.personId ? (
                      <div className="flxs-row__person">
                        <span className="dashicons dashicons-id"></span> {t('Registered person #%d', r.personId)}{' '}
                        <button type="button" className="button-link" onClick={() => setRow(r.uid, { personId: 0 })}>
                          {t('unlink')}
                        </button>
                      </div>
                    ) : null}
                    <div className="flxs-suggest" hidden={suggest?.uid !== r.uid}>
                      {suggest?.uid === r.uid ? (
                        <>
                          <div className="flxs-suggest__title">{t('Registered people')}</div>
                          {suggest.list.map((p) => (
                            <button type="button" key={p.databaseId} onClick={() => usePerson(r, p)}>
                              <strong>{p.name}</strong> <span>{[p.phone?.display, p.email].filter(Boolean).join(' · ')}</span>
                            </button>
                          ))}
                        </>
                      ) : null}
                    </div>
                  </div>
                  {edit ? null : (
                    <button type="button" className="flxs-row__del" title={t('Delete the row')} aria-label={t('Delete the row')} onClick={() => delRow(r.uid)}>
                      <span className="dashicons dashicons-no-alt"></span>
                    </button>
                  )}
                </div>
              );
            })}
          </div>
          {!edit && (
            <button type="button" className="flxs-add-row" onClick={addRow}>
              <span className="dashicons dashicons-plus-alt2"></span> {t('Add person')}
            </button>
          )}
        </section>

        <section className="flxs-card">
          <h2 className="flxs-card__title">
            <span className="flxs-step">3</span> {t('Sending')} <small>{t('optional')}</small>
          </h2>
          <div className="flxs-send" id="flxs-send">
            {CHANNELS.map((ch) => {
              const list = provs(ch);
              const none = !list.length;
              const named = list.filter((p) => p.id);
              return (
                <div key={ch} className={'flxs-send__item' + (none ? ' is-off' : '')}>
                  <label>
                    <input
                      type="checkbox"
                      disabled={none}
                      checked={channels.includes(ch)}
                      onChange={(e) => {
                        const next = e.target.checked ? [...channels, ch] : channels.filter((c) => c !== ch);
                        setChannels(next);
                        save(
                          'flxs_send',
                          next.map((c) => c.toLowerCase()),
                        );
                      }}
                    />{' '}
                    <span className={'flxs-ch flxs-ch--' + ch.toLowerCase()}></span> {chLabel(ch)}
                  </label>
                  {none ? (
                    <small>
                      {t('No connection in Flux Mail')}
                      {config.canManage ? (
                        <>
                          {' · '}
                          <a href={admin(host.adminUrl, 'page=flxi-baglantilar')}>{t('add')}</a>
                        </>
                      ) : null}
                    </small>
                  ) : list.length > 1 ? (
                    <select
                      value={providers[ch] || 0}
                      onChange={(e) => {
                        const next = { ...providers, [ch]: Number(e.target.value) };
                        setProviders(next);
                        save('flxs_send_prov', next);
                      }}
                    >
                      <option value={0}>{t('Default')}</option>
                      {named.map((p) => (
                        <option key={p.id} value={p.id}>
                          {p.name}
                        </option>
                      ))}
                    </select>
                  ) : (
                    <small>{list[0]?.name}</small>
                  )}
                </div>
              );
            })}
          </div>
          <p className="description">
            {queueText[0]}
            <a href={admin(host.adminUrl, 'page=flxs-kuyruk')}>{t('Send queue')}</a>
            {queueText[1]}
          </p>
        </section>

        <div className="flxs-submit">
          <button type="button" className="button button-primary button-hero" onClick={() => void submit()}>
            {submitLabel}
          </button>{' '}
          <button type="button" className="button flxs-reset" onClick={() => void askReset()}>
            <span className="dashicons dashicons-update"></span> {edit ? t('Go to a new certificate') : t('Clear')}
          </button>
          <span className="flxs-progress" aria-live="polite">
            {progress}
          </span>
        </div>

        {results.length ? (
          <section className="flxs-card flxs-results">
            <div className="flxs-card__head">
              <h2 className="flxs-card__title">{t('Created')}</h2>
              <div className="flxs-results__actions">
                <button type="button" className="button" onClick={() => void navigator.clipboard.writeText(results.map((r) => r.name + ': ' + r.url).join('\n')).then(() => onNotice({ kind: 'ok', text: t('Copied.') }))}>
                  {t('Copy links')}
                </button>{' '}
                {results.length > 1 ? (
                  <button
                    type="button"
                    className="button button-primary"
                    onClick={() =>
                      void mergePdfs(results.map((r) => r.bytes))
                        .then((blob) => download(blob, 'sertifikalar-' + today + '.pdf'))
                        .catch((e: unknown) => onNotice({ kind: 'err', text: e instanceof Error ? e.message : String(e) }))
                    }
                  >
                    {t('Download one PDF')}
                  </button>
                ) : null}{' '}
                <button type="button" className="button" onClick={resetAll}>
                  {t('New list')}
                </button>
              </div>
            </div>
            <div>
              {results.map((r) => (
                <div className="flxs-res" key={r.id}>
                  {r.image ? <img src={r.image} alt="" /> : null}
                  <div className="flxs-res__info">
                    <strong>{r.name}</strong>
                    <span>{r.number}</span>
                    <a href={r.url} target="_blank" rel="noreferrer" className="flxs-res__link">
                      {r.url.replace(/^https?:\/\//, '')}
                    </a>
                    <div className="flxs-res__msgs">
                      {r.msgs.map((m) => (
                        <span key={m.channel} className={'flxs-pill flxs-pill--' + (m.ok ? 'queued' : 'failed')} title={m.message}>
                          {chLabel(m.channel.toUpperCase())}: {m.ok ? t('Queued') : t('Failed')}
                        </span>
                      ))}
                    </div>
                  </div>
                  <div className="flxs-res__actions">
                    <button type="button" className="button" onClick={() => void navigator.clipboard.writeText(r.url).then(() => onNotice({ kind: 'ok', text: t('Copied.') }))}>
                      {t('Copy')}
                    </button>{' '}
                    {r.phone ? (
                      <a className="button" target="_blank" rel="noopener noreferrer" href={waLink(r)}>
                        {t('Open in WhatsApp')}
                      </a>
                    ) : null}{' '}
                    <a className="button" target="_blank" rel="noreferrer" href={r.pdf}>
                      {t('PDF')}
                    </a>{' '}
                    <button type="button" className="button" onClick={() => setForward(r.id)}>
                      {t('Send to someone else')}
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </section>
        ) : null}
      </div>

      <div className="flxs-create__preview">
        <div className="flxs-preview" id="flxs-preview">
          <div className="flxs-preview__bar">
            <strong>{t('Live preview')}</strong>
            <span className="flxs-preview__nav">
              <button type="button" className="button button-small" aria-label={t('Previous')} onClick={() => active > 0 && setActive(active - 1)}>
                ‹
              </button>
              <span>
                {active + 1} / {rows.length}
              </span>
              <button type="button" className="button button-small" aria-label={t('Next')} onClick={() => active < rows.length - 1 && setActive(active + 1)}>
                ›
              </button>
            </span>
            <button type="button" className="button button-small" onClick={() => lastBytes.current && download(bytesToBlob(lastBytes.current, 'application/pdf'), 'onizleme.pdf')}>
              PDF
            </button>
          </div>
          <div className="flxs-preview__stage" ref={stage}>
            <canvas ref={canvas} id="flxs-canvas"></canvas>
            <div className={'flxs-preview__spin' + (spin ? ' is-on' : '')}></div>
          </div>
          {previewErr ? <p className="flxs-preview__err">{previewErr}</p> : null}
        </div>
      </div>

      {job && jc ? (
        <div className={'flxs-job' + (job.finished ? ' is-done' : '')}>
          <div className="flxs-job__box" role="dialog" aria-modal="true" aria-labelledby="flxs-job-title">
            <div className="flxs-job__head">
              <span className="flxs-job__spin"></span>
              <h2 id="flxs-job-title">{job.finished ? readyText(jc) : t('Preparing certificates · %1$s / %2$s', jc.finished, jc.total)}</h2>
            </div>
            <div className="flxs-job__bar">
              <span style={{ width: Math.round((jc.finished / jc.total) * 100) + '%' }}></span>
            </div>
            <ol className="flxs-job__list">
              {job.rows.map((r) => (
                <li key={r.uid} className={'is-' + (r.step ?? 'wait')}>
                  <span className="flxs-job__dot"></span>
                  <strong>{r.name}</strong>
                  <em>{t(STEP[r.step ?? 'wait']) + (r.error ? ': ' + r.error : '')}</em>
                </li>
              ))}
            </ol>
            <div className="flxs-job__foot">
              {!job.finished && !job.edit && !job.detached ? (
                <button
                  type="button"
                  className="button button-primary"
                  onClick={() => {
                    job.detached = true;
                    current.current = null;
                    setViewJob(null);
                    resetForm();
                    onNotice({ kind: 'ok', text: t('Carrying on in the background. You can enter the new list.') });
                  }}
                >
                  {t('Carry on in the background, open a new list')}
                </button>
              ) : null}
              {!job.finished ? (
                <button
                  type="button"
                  className="button"
                  onClick={() => {
                    if (!job.detached) job.hidden = true;
                    setViewJob(null);
                  }}
                >
                  {t('Hide')}
                </button>
              ) : (
                <button
                  type="button"
                  className="button button-primary"
                  onClick={() => {
                    jobs.current = jobs.current.filter((k) => k !== job);
                    setViewJob(null);
                  }}
                >
                  {t('See the results')}
                </button>
              )}
            </div>
            <p className="flxs-job__note">{t('Do not close this tab while it carries on in the background. Messages are sent from the server once they are queued.')}</p>
          </div>
        </div>
      ) : null}
      {tray.length ? (
        <div className="flxs-tray">
          {tray.map((j) => {
            const c = counts(j);
            return (
              <div key={j.id} className={'flxs-tray__item' + (j.finished ? ' is-done' : '') + (c.err && j.finished ? ' has-err' : '')}>
                {j.finished ? null : <span className="flxs-job__spin"></span>}
                <button
                  type="button"
                  className="flxs-tray__open"
                  onClick={() => {
                    if (j.finished && j.detached) {
                      jobs.current = jobs.current.filter((k) => k !== j);
                      refreshJobs();
                      return;
                    }
                    j.hidden = false;
                    setViewJob(j);
                  }}
                >
                  {j.finished ? '✓ ' + readyText(c) : t('Preparing %1$s / %2$s', c.finished, c.total)}
                </button>
                {j.finished ? (
                  <button
                    type="button"
                    className="flxs-tray__x"
                    aria-label={t('Close')}
                    onClick={() => {
                      jobs.current = jobs.current.filter((k) => k !== j);
                      refreshJobs();
                    }}
                  >
                    ×
                  </button>
                ) : null}
              </div>
            );
          })}
        </div>
      ) : null}
      {forward ? <Forward t={t} id={forward} providers={config.providers} onClose={() => setForward(0)} onNotice={onNotice} /> : null}
    </div>
  );
}

async function uploadFile(client: ApiClient, blob: Blob, name: string): Promise<string> {
  const fd = new FormData();
  fd.append('context', 'flx-certificates/file');
  fd.append('file', blob, name);
  const res = await client<{ uploadId: string }>('flx/v1/upload', { method: 'POST', formData: fd });
  return res.uploadId;
}
