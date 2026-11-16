// Media picker: the media library, Flux Studio, upload and "add from address".
// Upload goes through the shared POST /flx/v1/upload (REST exception), then FlxSocialMediaAdd takes the file over.
import { useRef, useState } from 'react';
import { useFlxMutation, useFlxQuery } from '@fluxwp/graphql/react';
import { FlxSocialMedia, FlxSocialMediaAdd, FlxSocialMediaImport, FlxSocialStudioMedia } from '@fluxwp/graphql/operations';
import { Loading, Modal } from '@fluxwp/ui';
import { useHost } from '../host';
import { errorText, payloadError, useSocial, type Media } from './common';

interface Item {
  key: string;
  media?: Media;
  url: string;
  thumb: string;
  kind: string;
  name: string;
}

export function MediaPicker({ onClose, onPick }: { onClose: () => void; onPick: (items: Media[]) => void }) {
  const s = useSocial();
  const { t } = s;
  const cfg = s.boot.flxSocialConfig;
  const host = useHost();
  const [tab, setTab] = useState<'lib' | 'studio'>('lib');
  const [search, setSearch] = useState('');
  const [q, setQ] = useState('');
  const [page, setPage] = useState(1);
  const [chosen, setChosen] = useState<Item[]>([]);
  const [busy, setBusy] = useState(false);
  const [urlOpen, setUrlOpen] = useState(false);
  const [url, setUrl] = useState('');
  const file = useRef<HTMLInputElement>(null);
  const add = useFlxMutation(FlxSocialMediaAdd);
  const imp = useFlxMutation(FlxSocialMediaImport);

  const lib = useFlxQuery(FlxSocialMedia, { search: q || undefined, page }, { enabled: tab === 'lib', placeholderData: (p) => p });
  const studio = useFlxQuery(FlxSocialStudioMedia, {}, { enabled: tab === 'studio' && cfg.studio });

  const libItems: Item[] = (lib.data?.flxSocialMedia.items ?? []).map((m) => ({ key: 'm' + m.databaseId, media: m, url: m.url, thumb: m.thumb, kind: m.kind, name: m.name }));
  const studioItems: Item[] = (studio.data?.flxSocialStudioMedia ?? []).map((x) => ({
    key: 's' + x.databaseId,
    url: x.url,
    thumb: x.thumb,
    kind: x.kind,
    name: x.title || x.prompt,
  }));
  const items = tab === 'lib' ? libItems : studioItems;
  const toggle = (it: Item) => setChosen((c) => (c.some((x) => x.key === it.key) ? c.filter((x) => x.key !== it.key) : [...c, it]));

  const importUrl = async (u: string): Promise<Media | null> => {
    const r = await imp.mutateAsync({ input: { url: u } });
    const err = payloadError(r.flxSocialMediaImport?.errors);
    if (err) throw new Error(err);
    return r.flxSocialMediaImport?.media ?? null;
  };

  const finish = async () => {
    setBusy(true);
    try {
      const out: Media[] = [];
      for (const it of chosen) {
        if (it.media) out.push(it.media);
        else {
          const m = await importUrl(it.url);
          if (m) out.push(m);
        }
      }
      onPick(out);
    } catch (e) {
      s.toast(errorText(e, t('Something went wrong.')), 'error');
      setBusy(false);
    }
  };

  const upload = async (files: FileList | null) => {
    if (!files?.length) return;
    setBusy(true);
    s.toast(t('Uploading {n} files…', { n: files.length }));
    try {
      const out: Media[] = [];
      for (const f of Array.from(files)) {
        const fd = new FormData();
        fd.append('file', f);
        const up = await host.client<{ uploadId: string }>('flx/v1/upload', { method: 'POST', query: { context: 'flx-social/media' }, formData: fd });
        const r = await add.mutateAsync({ input: { uploadId: up.uploadId } });
        const err = payloadError(r.flxSocialMediaAdd?.errors);
        if (err) throw new Error(err);
        if (r.flxSocialMediaAdd?.media) out.push(r.flxSocialMediaAdd.media);
      }
      onPick([...chosen.flatMap((c) => (c.media ? [c.media] : [])), ...out]);
    } catch (e) {
      s.toast(errorText(e, t('Something went wrong.')), 'error');
      setBusy(false);
    }
  };

  const loading = tab === 'lib' ? lib.isLoading : studio.isLoading;
  return (
    <Modal title={t('Pick images or videos')} onClose={onClose} wide>
      <div className="flxsm-mp-top">
        {cfg.studio && (
          <div className="flxsm-seg" role="group">
            <button type="button" className={tab === 'lib' ? 'is-on' : ''} onClick={() => setTab('lib')}>
              {t('Media library')}
            </button>
            <button type="button" className={tab === 'studio' ? 'is-on' : ''} onClick={() => setTab('studio')}>
              {t('Flux Studio')}
            </button>
          </div>
        )}
        {tab === 'lib' && (
          <input
            type="search"
            value={search}
            placeholder={t('Search')}
            aria-label={t('Search')}
            onChange={(e) => setSearch(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                setQ(search.trim());
                setPage(1);
              }
            }}
            onBlur={() => {
              if (search.trim() !== q) {
                setQ(search.trim());
                setPage(1);
              }
            }}
          />
        )}
        <span className="flxsm-grow" />
        {cfg.canUpload && (
          <>
            <button type="button" className="flxsm-btn" disabled={busy} onClick={() => file.current?.click()}>
              {t('Upload')}
            </button>
            <button type="button" className="flxsm-btn" disabled={busy} onClick={() => setUrlOpen((v) => !v)}>
              {t('Add from address')}
            </button>
            <input ref={file} type="file" hidden multiple accept="image/*,video/*" onChange={(e) => void upload(e.target.files)} />
          </>
        )}
      </div>
      {urlOpen && (
        <form
          className="flxsm-mp-url"
          onSubmit={(e) => {
            e.preventDefault();
            if (!url.trim()) return;
            setBusy(true);
            s.toast(t('Downloading…'));
            importUrl(url.trim())
              .then((m) => m && onPick([...chosen.flatMap((c) => (c.media ? [c.media] : [])), m]))
              .catch((er: unknown) => {
                s.toast(errorText(er, t('Something went wrong.')), 'error');
                setBusy(false);
              });
          }}
        >
          <input type="url" value={url} placeholder="https://…" onChange={(e) => setUrl(e.target.value)} />
          <button type="submit" className="flxsm-btn is-primary" disabled={busy}>
            {t('Add')}
          </button>
        </form>
      )}
      {loading ? (
        <Loading />
      ) : !items.length ? (
        <p className="flxsm-muted">{t('No files.')}</p>
      ) : (
        <div className="flxsm-mp-grid">
          {items.map((it) => {
            const on = chosen.some((x) => x.key === it.key);
            return (
              <button key={it.key} type="button" className={'flxsm-mp-i' + (on ? ' is-on' : '')} title={it.name} aria-pressed={on} onClick={() => toggle(it)}>
                {it.kind === 'video' && !it.thumb ? <video src={it.url} muted preload="metadata" /> : <img src={it.thumb || it.url} alt="" loading="lazy" />}
                {it.kind === 'video' && <span className="flxsm-mkind">▶</span>}
                {on && <span className="flxsm-mp-ck">✓</span>}
              </button>
            );
          })}
        </div>
      )}
      {tab === 'lib' && (lib.data?.flxSocialMedia.pages ?? 1) > 1 && (
        <p className="flxsm-pager">
          <button type="button" className="flxsm-btn is-ghost" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
            ‹
          </button>
          <span className="flxsm-muted">{t('Page {n} of {total}', { n: page, total: lib.data?.flxSocialMedia.pages ?? 1 })}</span>
          <button type="button" className="flxsm-btn is-ghost" disabled={page >= (lib.data?.flxSocialMedia.pages ?? 1)} onClick={() => setPage((p) => p + 1)}>
            ›
          </button>
        </p>
      )}
      <div className="flxsm-actions">
        <span className="flxsm-muted">{chosen.length ? t('{n} selected', { n: chosen.length }) : ''}</span>
        <span className="flxsm-grow" />
        <button type="button" className="flxsm-btn" onClick={onClose}>
          {t('Cancel')}
        </button>
        <button type="button" className="flxsm-btn is-primary" disabled={!chosen.length || busy} onClick={() => void finish()}>
          {t('Add')}
        </button>
      </div>
    </Modal>
  );
}
