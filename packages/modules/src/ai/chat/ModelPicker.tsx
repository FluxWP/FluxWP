// Model choice for a chat (managers only): search, provider chips, recent models, price and context size.
import { useEffect, useMemo, useState } from 'react';
import { Loading, Modal } from '@fluxwp/ui';
import { useAi } from '../common';
import type { ChatApi } from './api';
import type { ModelInfo } from './types';

const RECENT = 'flxai-recent-models';
const fmtCtx = (n: number) => (n >= 1000000 ? Math.round(n / 100000) / 10 + 'M' : n >= 1000 ? Math.round(n / 1000) + 'K' : '');
const norm = (x: string) => x.toLowerCase().replace(/[-_/:()]+/g, ' ');

function readRecent(): string[] {
  try {
    const v: unknown = JSON.parse(localStorage.getItem(RECENT) || '[]');
    return Array.isArray(v) ? v.filter((x): x is string => typeof x === 'string') : [];
  } catch {
    return [];
  }
}

export function ModelPicker({ api, current, defaultLabel, onPick, onClose }: { api: ChatApi; current: string; defaultLabel: string; onPick: (id: string) => void; onClose: () => void }) {
  const { t } = useAi();
  const [models, setModels] = useState<ModelInfo[] | null>(null);
  const [err, setErr] = useState('');
  const [q, setQ] = useState('');
  const [fam, setFam] = useState('');
  useEffect(() => {
    api.models().then(setModels, (e: unknown) => setErr(e instanceof Error ? e.message : String(e)));
  }, [api]);
  const fams = useMemo(() => {
    const cnt = new Map<string, number>();
    for (const m of models ?? []) if (m.family) cnt.set(m.family, (cnt.get(m.family) ?? 0) + 1);
    return [...cnt.entries()].sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0])).slice(0, 8).map(([f]) => f);
  }, [models]);
  const words = norm(q).split(/\s+/).filter(Boolean);
  const list = (models ?? []).filter((m) => {
    if (fam && m.family !== fam) return false;
    const h = norm(`${m.id} ${m.name} ${m.family}`);
    const hc = h.replace(/\s+/g, '');
    return words.every((w) => h.includes(w) || hc.includes(w));
  });
  const byId = new Map((models ?? []).map((m) => [m.id, m]));
  const recent = readRecent().map((id) => byId.get(id)).filter((m): m is ModelInfo => !!m);
  const pick = (id: string) => {
    if (id) {
      try {
        localStorage.setItem(RECENT, JSON.stringify([id, ...readRecent().filter((x) => x !== id)].slice(0, 5)));
      } catch {
        /* storage may be blocked */
      }
    }
    onPick(id);
  };
  const row = (m: ModelInfo) => (
    <button key={m.id} type="button" className={'flxai-mrow' + (m.id === current ? ' is-cur' : '')} onClick={() => pick(m.id)}>
      <b>{m.name}</b>
      <small>{[m.id, fmtCtx(m.ctx), `$${Number(m.in).toFixed(2)} / $${Number(m.out).toFixed(2)}`].filter(Boolean).join(' · ')}</small>
    </button>
  );
  return (
    <Modal title={t('Choose a model')} onClose={onClose} wide>
      <input className="flxai-input" autoFocus type="search" value={q} placeholder={t('Search models')} aria-label={t('Search models')} onChange={(e) => setQ(e.target.value)} />
      {fams.length > 0 && (
        <div className="flxai-mfams">
          {['', ...fams].map((f) => (
            <button key={f} type="button" className={'flxai-pill-btn' + (fam === f ? ' is-on' : '')} onClick={() => setFam(f)}>
              {f || t('All')}
            </button>
          ))}
        </div>
      )}
      <div className="flxai-mlist">
        {!models && !err && <Loading />}
        {err && <div className="flxai-notice is-error">{err}</div>}
        {models && (
          <>
            <button type="button" className={'flxai-mrow' + (!current ? ' is-cur' : '')} onClick={() => pick('')}>
              <b>{t('Default')}</b>
              <small>{defaultLabel}</small>
            </button>
            {!words.length && !fam && recent.length > 0 && (
              <>
                <div className="flxai-msec">{t('Recently used')}</div>
                {recent.map(row)}
                <div className="flxai-msec">{t('All models')}</div>
              </>
            )}
            {list.slice(0, 150).map(row)}
            {!list.length && <p className="flxai-hint">{t('No results.')}</p>}
            {list.length > 150 && <p className="flxai-hint">{t('%s more models: narrow the search.', list.length - 150)}</p>}
          </>
        )}
      </div>
    </Modal>
  );
}
