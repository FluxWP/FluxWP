// Composer: pick accounts, shared and per-account text/media/options, AI help, live preview, schedule or share.
import { useEffect, useMemo, useRef, useState } from 'react';
import { useFlxMutation } from '@fluxwp/graphql/react';
import { FlxSocialAiWrite, FlxSocialPostCreate, FlxSocialPostUpdate } from '@fluxwp/graphql/operations';
import type { FlxSocialAiMode, FlxSocialPostAction, FlxSocialTargetInput } from '@fluxwp/graphql/types';
import { DatePicker, Modal } from '@fluxwp/ui';
import { Avatar, NetDot, StatusBadge, STATUS_KEYS, errorText, payloadError, useSocial, type Account, type Media, type Post } from './common';
import { camelKey, cleanOptions, optionDefs, textLength, type OptKey } from './options';
import { MediaPicker } from './MediaPicker';

interface Tg {
  content: string | null;
  media: Media[] | null;
  options: Record<string, unknown>;
}
const LOCKED = new Set(['PUBLISHED', 'PROCESSING', 'DELETED']);

/** Rules the networks enforce (the server checks them too). */
function problems(a: Account, text: string, media: Media[], opts: Record<string, unknown>, t: (s: string, p?: Record<string, string | number>) => string): string[] {
  const c = a.caps;
  const out: string[] = [];
  const imgs = media.filter((m) => m.kind === 'image').length;
  const gifs = media.filter((m) => m.kind === 'gif').length;
  const vids = media.filter((m) => m.kind === 'video').length;
  const len = textLength(text, a.provider);
  if (!text.trim() && !media.length) out.push(t('Text or media is required.'));
  if (c.maxText && len > c.maxText) out.push(t('Text is {n} characters too long (limit {max}).', { n: len - c.maxText, max: c.maxText }));
  if (c.needsMedia && !media.length) out.push(t('This network needs an image or a video.'));
  if (imgs && !c.image) out.push(t('This network does not accept images.'));
  if (vids && !c.video) out.push(t('This network does not accept videos.'));
  if (gifs && !c.gif && !c.image) out.push(t('GIFs are not supported.'));
  if (c.image && imgs + gifs > c.maxImages) out.push(t('At most {n} images.', { n: c.maxImages }));
  if (c.video && vids > c.maxVideos) out.push(t('At most {n} videos.', { n: c.maxVideos }));
  if (vids && imgs && !c.mix) out.push(t('Images and videos cannot be sent together.'));
  if (a.provider === 'youtube' && !vids) out.push(t('YouTube needs a video.'));
  if (a.provider === 'pinterest' && !opts.board) out.push(t('Pick a Pinterest board.'));
  if (a.provider === 'instagram' && opts.igType === 'reel' && !vids) out.push(t('Reels need a video.'));
  if (a.status === 'EXPIRED') out.push(t('The account connection expired; reconnect it in Accounts.'));
  return out;
}

export function Composer({ post, presetAt, onClose, onSaved }: { post: Post | null; presetAt?: number | undefined; onClose: () => void; onSaved: () => void }) {
  const s = useSocial();
  const { t, boot } = s;
  const cfg = boot.flxSocialConfig;
  const active = s.accounts.filter((a) => a.status !== 'DISABLED');

  const locked = useMemo(() => (post?.targets ?? []).filter((x) => LOCKED.has(x.status)), [post]);
  const [title, setTitle] = useState(post?.title ?? '');
  const [content, setContent] = useState(post?.content ?? '');
  const [media, setMedia] = useState<Media[]>(post?.media ?? []);
  const [tags, setTags] = useState((post?.tags ?? []).join(', '));
  const [repeat, setRepeat] = useState(post?.repeatDays ?? 0);
  const [at, setAt] = useState(post?.scheduledAt?.timestamp ?? presetAt ?? 0);
  const [sel, setSel] = useState<number[]>(() => {
    if (post) return post.targets.filter((x) => !LOCKED.has(x.status)).map((x) => x.accountId);
    try {
      const raw = JSON.parse(window.localStorage.getItem('flxsm_last_accounts') ?? '[]') as unknown;
      const ids = Array.isArray(raw) ? raw.map(Number) : [];
      return ids.filter((id) => active.some((a) => a.databaseId === id));
    } catch {
      return [];
    }
  });
  const [tg, setTg] = useState<Record<number, Tg>>(() => {
    const m: Record<number, Tg> = {};
    for (const x of post?.targets ?? []) {
      if (LOCKED.has(x.status)) continue;
      m[x.accountId] = { content: x.content ? x.content : null, media: x.media?.length ? x.media : null, options: { ...(cleanOptions(x.options as Record<string, unknown>) as Record<string, unknown>) } };
    }
    return m;
  });
  const [tab, setTab] = useState<'all' | number>('all');
  const [picker, setPicker] = useState<{ target: number | null } | null>(null);
  const [dirty, setDirty] = useState(false);
  const [leave, setLeave] = useState(false);
  const [busy, setBusy] = useState<string | null>(null);
  const [aiBusy, setAiBusy] = useState(false);
  const textRef = useRef<HTMLTextAreaElement>(null);
  const [now] = useState(() => Math.floor(Date.now() / 1000));

  const touch = () => setDirty(true);
  const acc = (id: number) => s.accounts.find((a) => a.databaseId === id);
  const tgOf = (id: number): Tg => tg[id] ?? { content: null, media: null, options: {} };
  const patch = (id: number, p: Partial<Tg>) => {
    setTg((m) => ({ ...m, [id]: { ...tgOf(id), ...p } }));
    touch();
  };
  const textOf = (id: number) => tgOf(id).content ?? content;
  const mediaOf = (id: number) => tgOf(id).media ?? media;

  useEffect(() => {
    if (tab !== 'all' && !sel.includes(tab)) setTab('all');
  }, [sel, tab]);

  const errors = sel.flatMap((id) => {
    const a = acc(id);
    return a ? problems(a, textOf(id), mediaOf(id), tgOf(id).options, t).map((e) => a.name + ': ' + e) : [];
  });

  const create = useFlxMutation(FlxSocialPostCreate);
  const update = useFlxMutation(FlxSocialPostUpdate);
  const aiMut = useFlxMutation(FlxSocialAiWrite);

  const providersOf = (id: number | null) => (id ? [acc(id)?.provider ?? ''] : sel.map((x) => acc(x)?.provider ?? '')).filter(Boolean);

  const ai = (mode: FlxSocialAiMode, id: number | null, extra: { brief?: string; limit?: number; to?: string } = {}) => {
    const text = id ? textOf(id) : content;
    if (mode !== 'WRITE' && !text.trim()) {
      s.toast(t('Write some text first.'), 'error');
      return;
    }
    setAiBusy(true);
    aiMut.mutate(
      { input: { mode, text, providers: providersOf(id), ...extra } },
      {
        onSuccess: (r) => {
          setAiBusy(false);
          const p = r.flxSocialAiWrite;
          const err = payloadError(p?.errors);
          if (err) return s.toast(err, 'error');
          if (mode === 'ADAPT') {
            for (const row of p?.texts ?? []) {
              for (const sid of sel) if (acc(sid)?.provider === row.provider) patch(sid, { content: row.text });
            }
            s.toast(t('A separate text was written for each account; see the tabs.'));
            return;
          }
          const out = p?.text ?? '';
          const next = mode === 'HASHTAGS' ? text.replace(/\s+$/, '') + '\n\n' + out : out;
          if (id) patch(id, { content: next });
          else {
            setContent(next);
            touch();
          }
        },
        onError: (e) => {
          setAiBusy(false);
          s.toast(errorText(e, t('Something went wrong.')), 'error');
        },
      },
    );
  };

  const save = (action: FlxSocialPostAction) => {
    if (!sel.length && action !== 'DRAFT') return s.toast(t('Pick at least one account.'), 'error');
    if (action === 'SCHEDULE' && (!at || at < now)) return s.toast(t('Pick a time in the future.'), 'error');
    const targets: FlxSocialTargetInput[] = sel.map((id) => {
      const x = tgOf(id);
      return {
        accountId: id,
        ...(x.content !== null ? { content: x.content } : {}),
        ...(x.media ? { media: x.media.map((m) => m.databaseId) } : {}),
        options: cleanOptions(x.options),
      };
    });
    const input = {
      title,
      content,
      media: media.map((m) => m.databaseId),
      tags: tags.split(',').map((x) => x.trim()).filter(Boolean),
      scheduledAt: at || 0,
      repeatDays: repeat || 0,
      action,
      targets,
    };
    setBusy(action);
    const done = (p: Post | null | undefined, errs: ReadonlyArray<{ message: string }> | undefined) => {
      setBusy(null);
      const err = payloadError(errs);
      if (err || !p) return s.toast(err ?? t('Something went wrong.'), 'error');
      try {
        window.localStorage.setItem('flxsm_last_accounts', JSON.stringify(sel));
      } catch {
        /* storage may be blocked */
      }
      let msg =
        action === 'DRAFT'
          ? t('Draft saved.')
          : action === 'SUBMIT'
            ? t('Sent for approval.')
            : action === 'SCHEDULE'
              ? t('Scheduled for {time}.', { time: s.fmt(p.scheduledAt?.timestamp ?? at) })
              : action === 'APPROVE'
                ? t('Approved.')
                : t('Queued; it goes out in a moment.');
      if (p.status === 'PENDING' && (action === 'SCHEDULE' || action === 'NOW')) msg = t('You cannot publish, so it was sent for approval.');
      s.toast(msg);
      setDirty(false);
      onSaved();
    };
    const fail = (e: unknown) => {
      setBusy(null);
      s.toast(errorText(e, t('Something went wrong.')), 'error');
    };
    if (post) update.mutate({ input: { id: post.id, ...input } }, { onSuccess: (r) => done(r.flxSocialPostUpdate?.post, r.flxSocialPostUpdate?.errors), onError: fail });
    else create.mutate({ input }, { onSuccess: (r) => done(r.flxSocialPostCreate?.post, r.flxSocialPostCreate?.errors), onError: fail });
  };

  const close = () => (dirty ? setLeave(true) : onClose());
  const future = at > now;
  const quick = (kind: string) => {
    const base = new Date((now + cfg.tzOffset) * 1000);
    const day = (d: number, h: number) => Date.UTC(base.getUTCFullYear(), base.getUTCMonth(), base.getUTCDate() + d, h, 0) / 1000 - cfg.tzOffset;
    const dow = base.getUTCDay();
    const v: Record<string, number> = {
      hour: now + 3600 - (now % 300),
      today: day(0, 19),
      tomorrow10: day(1, 10),
      tomorrow19: day(1, 19),
      weekend: day((6 - dow + 7) % 7 || 7, 11),
      none: 0,
    };
    setAt(v[kind] ?? 0);
    touch();
  };

  if (!active.length) {
    return (
      <Modal title={t('New post')} onClose={onClose}>
        <div className="flxsm-empty">
          <h2>{t('No accounts yet')}</h2>
          <p>{cfg.canManage ? t('Connect a social media account first.') : t('Ask an administrator to connect an account.')}</p>
          {cfg.canManage && (
            <button
              type="button"
              className="flxsm-btn is-primary"
              onClick={() => {
                onClose();
                s.go('accounts');
              }}
            >
              {t('Connect an account')}
            </button>
          )}
        </div>
      </Modal>
    );
  }

  const cur = tab === 'all' ? null : acc(tab);
  const own = cur ? tgOf(cur.databaseId) : null;
  const sharedCount = (list: number[]) => list.filter((x) => tgOf(x).content === null);

  return (
    <Modal title={post ? t('Edit post') : t('New post')} onClose={close} wide>
      <div className="flxsm-cmp">
        <div className="flxsm-cmp-left">
          {post && (
            <p>
              <StatusBadge status={post.status} label={t(STATUS_KEYS[post.status])} />
            </p>
          )}
          {post?.note && post.status === 'DRAFT' && <div className="flxsm-notice">{t('The approver left a note: {note}', { note: post.note })}</div>}
          {locked.length > 0 && <div className="flxsm-notice">{t('Published on {n} accounts; change that copy with Details › Edit on the network.', { n: locked.length })}</div>}

          <div className="flxsm-label">
            {t('Accounts')} <small className="flxsm-muted">{t('The post goes to every selected account; each can get its own text.')}</small>
          </div>
          <div className="flxsm-apick">
            {active.map((a) => {
              const on = sel.includes(a.databaseId);
              return (
                <button
                  key={a.id}
                  type="button"
                  className={'flxsm-apick-b' + (on ? ' is-on' : '') + (a.status === 'EXPIRED' ? ' is-bad' : '')}
                  title={a.network + ' › ' + a.name}
                  aria-pressed={on}
                  onClick={() => {
                    setSel(on ? sel.filter((x) => x !== a.databaseId) : [...sel, a.databaseId]);
                    touch();
                  }}
                >
                  <Avatar src={a.avatar} name={a.name} size={38} />
                  <NetDot provider={a.provider} size={14} />
                  {on && <span className="flxsm-apick-ck">✓</span>}
                </button>
              );
            })}
          </div>

          <div className="flxsm-ctabs" role="tablist">
            <button type="button" role="tab" aria-selected={tab === 'all'} className={'flxsm-ctab' + (tab === 'all' ? ' is-on' : '')} onClick={() => setTab('all')}>
              {t('Shared text')}
            </button>
            {sel.map((id) => {
              const a = acc(id);
              if (!a) return null;
              const errs = problems(a, textOf(id), mediaOf(id), tgOf(id).options, t);
              const custom = tgOf(id).content !== null || !!tgOf(id).media;
              return (
                <button key={id} type="button" role="tab" aria-selected={tab === id} className={'flxsm-ctab' + (tab === id ? ' is-on' : '') + (errs.length ? ' is-err' : '')} title={errs.join('\n')} onClick={() => setTab(id)}>
                  <Avatar src={a.avatar} name={a.name} size={20} />
                  <span>{a.name}</span>
                  {custom && <i className="flxsm-ctab-dot" title={t('Own text')} />}
                  {errs.length > 0 && <span aria-hidden>!</span>}
                </button>
              );
            })}
          </div>

          {tab === 'all' || !cur || !own ? (
            <div className="flxsm-ceditor">
              <textarea
                ref={textRef}
                rows={7}
                value={content}
                placeholder={t('What do you want to share?')}
                aria-label={t('Shared text')}
                onChange={(e) => {
                  setContent(e.target.value);
                  touch();
                }}
              />
              <Counters text={content} ids={sharedCount(sel)} />
              <div className="flxsm-ctext-foot">
                {cfg.signature && (
                  <button
                    type="button"
                    className="flxsm-btn is-ghost"
                    title={cfg.signature}
                    onClick={() => {
                      setContent((c) => c.replace(/\s+$/, '') + (c ? '\n\n' : '') + cfg.signature);
                      touch();
                    }}
                  >
                    {t('Add signature')}
                  </button>
                )}
                {cfg.ai && <AiBar busy={aiBusy} onRun={(m, x) => ai(m, null, x)} multi={sel.length > 1} limitFor={() => limitOf(sel.map(acc))} />}
              </div>
              <MediaStrip list={media} onChange={(l) => { setMedia(l); touch(); }} onAdd={() => setPicker({ target: null })} />
              <p className="flxsm-muted flxsm-small">{t('Tip: Instagram, TikTok and YouTube need an image or video; X takes up to 4 images.')}</p>
            </div>
          ) : (
            <div className="flxsm-ceditor">
              <div className="flxsm-cown">
                <Avatar src={cur.avatar} name={cur.name} size={28} />
                <strong>
                  {cur.network} › {cur.name}
                </strong>
                <span className="flxsm-grow" />
                <label className="flxsm-chk">
                  <input type="checkbox" checked={own.content !== null} onChange={(e) => patch(cur.databaseId, { content: e.target.checked ? textOf(cur.databaseId) : null })} />
                  <span>{t('Own text for this account')}</span>
                </label>
              </div>
              <textarea rows={7} disabled={own.content === null} value={own.content ?? content} aria-label={t('Text for {name}', { name: cur.name })} onChange={(e) => patch(cur.databaseId, { content: e.target.value })} />
              <Counters text={textOf(cur.databaseId)} ids={[cur.databaseId]} />
              {cfg.ai && own.content !== null && <AiBar busy={aiBusy} onRun={(m, x) => ai(m, cur.databaseId, x)} multi={false} limitFor={() => limitOf([cur])} />}
              <label className="flxsm-chk">
                <input type="checkbox" checked={!!own.media} onChange={(e) => patch(cur.databaseId, { media: e.target.checked ? [...media] : null })} />
                <span>{t('Own images/videos for this account')}</span>
              </label>
              {own.media && <MediaStrip list={own.media} onChange={(l) => patch(cur.databaseId, { media: l })} onAdd={() => setPicker({ target: cur.databaseId })} />}
              <Options account={cur} value={own.options} onChange={(o) => patch(cur.databaseId, { options: o })} />
            </div>
          )}

          <details className="flxsm-cextra" open={!!(title || tags || repeat)}>
            <summary>{t('More (internal title, tags, repeat)')}</summary>
            <label className="flxsm-field">
              <span>{t('Internal title (only shown in the list)')}</span>
              <input type="text" maxLength={190} value={title} onChange={(e) => { setTitle(e.target.value); touch(); }} />
            </label>
            <label className="flxsm-field">
              <span>{t('Tags (comma separated, for filtering)')}</span>
              <input type="text" value={tags} onChange={(e) => { setTags(e.target.value); touch(); }} />
            </label>
            <label className="flxsm-field">
              <span>{t('Share again after this many days (0 = no)')}</span>
              <input type="number" min={0} max={365} value={repeat} onChange={(e) => { setRepeat(Math.max(0, Number(e.target.value) || 0)); touch(); }} />
            </label>
          </details>
        </div>

        <div className="flxsm-cmp-right">
          <div className="flxsm-label">{t('Preview')}</div>
          {!sel.length ? (
            <p className="flxsm-muted">{t('Pick an account and the preview shows here.')}</p>
          ) : (
            (tab === 'all' ? sel.slice(0, 3) : [tab]).map((id) => {
              const a = acc(id);
              return a ? <Preview key={id} account={a} text={textOf(id)} media={mediaOf(id)} options={tgOf(id).options} /> : null;
            })
          )}
          {tab === 'all' && sel.length > 3 && <p className="flxsm-muted flxsm-small">{t('+{n} more accounts; see the tabs.', { n: sel.length - 3 })}</p>}
        </div>
      </div>

      <div className="flxsm-cmp-foot">
        <label className="flxsm-when">
          <span className="flxsm-sr">{t('Time to share')}</span>
          <DatePicker
            time
            aria-label={t('Time to share')}
            value={future ? s.tsToInput(at) : ''}
            min={s.tsToInput(now)}
            onChange={(v) => {
              setAt(v ? s.inputToTs(v) : 0);
              touch();
            }}
          />
        </label>
        <select
          aria-label={t('Quick time')}
          value=""
          onChange={(e) => {
            if (e.target.value) quick(e.target.value);
          }}
        >
          <option value="">{t('Quick time')}</option>
          <option value="hour">{t('In 1 hour')}</option>
          <option value="today">{t('Today 19:00')}</option>
          <option value="tomorrow10">{t('Tomorrow 10:00')}</option>
          <option value="tomorrow19">{t('Tomorrow 19:00')}</option>
          <option value="weekend">{t('Weekend 11:00')}</option>
          {at > 0 && <option value="none">{t('Remove the time')}</option>}
        </select>
        <span className="flxsm-muted flxsm-small">{cfg.timezone}</span>
        {errors.length > 0 && (
          <span className="flxsm-bad" title={errors.join('\n')}>
            ! {t('{n} problems', { n: errors.length })}
          </span>
        )}
        <span className="flxsm-grow" />
        <button type="button" className="flxsm-btn" disabled={!!busy} onClick={() => save('DRAFT')}>
          {t('Save draft')}
        </button>
        {cfg.canPublish ? (
          <>
            {post?.status === 'PENDING' && (
              <button type="button" className="flxsm-btn" disabled={!!busy} onClick={() => save('APPROVE')}>
                {t('Approve')}
              </button>
            )}
            {future ? (
              <button type="button" className="flxsm-btn is-primary" disabled={!!busy} onClick={() => save('SCHEDULE')}>
                {t('Schedule')}
              </button>
            ) : (
              <button type="button" className="flxsm-btn is-primary" disabled={!!busy} onClick={() => save('NOW')}>
                {t('Share now')}
              </button>
            )}
          </>
        ) : (
          <button type="button" className="flxsm-btn is-primary" disabled={!!busy} onClick={() => save('SUBMIT')}>
            {t('Send for approval')}
          </button>
        )}
      </div>

      {picker && (
        <MediaPicker
          onClose={() => setPicker(null)}
          onPick={(items) => {
            const merge = (list: Media[]) => [...list, ...items.filter((m) => !list.some((x) => x.databaseId === m.databaseId))];
            if (picker.target === null) setMedia((l) => merge(l));
            else patch(picker.target, { media: merge(tgOf(picker.target).media ?? []) });
            touch();
            setPicker(null);
          }}
        />
      )}
      {leave && (
        <Modal title={t('Unsaved changes')} onClose={() => setLeave(false)}>
          <p className="flxsm-confirm">{t('There are unsaved changes. Close anyway?')}</p>
          <div className="flxsm-actions">
            <button type="button" className="flxsm-btn" onClick={() => setLeave(false)}>
              {t('Keep editing')}
            </button>
            <button type="button" className="flxsm-btn is-danger" onClick={onClose}>
              {t('Close')}
            </button>
          </div>
        </Modal>
      )}
    </Modal>
  );

  function Counters({ text, ids }: { text: string; ids: number[] }) {
    const seen = new Set<string>();
    return (
      <div className="flxsm-counters">
        {ids.map((id) => {
          const a = acc(id);
          if (!a || seen.has(a.provider) || !a.caps.maxText) return null;
          seen.add(a.provider);
          const n = textLength(text, a.provider);
          const max = a.caps.maxText;
          return (
            <span key={a.provider} className={'flxsm-counter' + (n > max ? ' is-over' : n > max * 0.9 ? ' is-near' : '')} title={a.network}>
              <NetDot provider={a.provider} size={14} /> {n}/{max.toLocaleString(s.locale)}
            </span>
          );
        })}
      </div>
    );
  }
}

function limitOf(list: (Account | undefined)[]): number {
  let lim = 280;
  for (const a of list) if (a?.caps.maxText) lim = Math.min(lim, a.caps.maxText);
  return lim;
}

function AiBar({ busy, onRun, multi, limitFor }: { busy: boolean; onRun: (m: FlxSocialAiMode, x?: { brief?: string; limit?: number; to?: string }) => void; multi: boolean; limitFor: () => number }) {
  const { t } = useSocial();
  const [ask, setAsk] = useState(false);
  const [brief, setBrief] = useState('');
  return (
    <div className="flxsm-aibar">
      <button type="button" className="flxsm-btn is-ghost" disabled={busy} onClick={() => setAsk((v) => !v)}>
        ✨ {t('Write with AI')}
      </button>
      <button type="button" className="flxsm-btn is-ghost" disabled={busy} title={t('Fix and polish')} onClick={() => onRun('IMPROVE')}>
        {t('Polish')}
      </button>
      <button type="button" className="flxsm-btn is-ghost" disabled={busy} onClick={() => onRun('SHORTEN', { limit: limitFor() })}>
        {t('Shorten')}
      </button>
      <button type="button" className="flxsm-btn is-ghost" disabled={busy} onClick={() => onRun('HASHTAGS')}>
        #
      </button>
      <button type="button" className="flxsm-btn is-ghost" disabled={busy} onClick={() => onRun('TRANSLATE', { to: 'English' })}>
        {t('Translate to English')}
      </button>
      {multi && (
        <button type="button" className="flxsm-btn is-ghost" disabled={busy} title={t('Writes a separate text for each account in the style of its network')} onClick={() => onRun('ADAPT')}>
          {t('Adapt to networks')}
        </button>
      )}
      {ask && (
        <form
          className="flxsm-ai-ask"
          onSubmit={(e) => {
            e.preventDefault();
            if (!brief.trim()) return;
            onRun('WRITE', { brief: brief.trim() });
            setAsk(false);
            setBrief('');
          }}
        >
          <input type="text" autoFocus value={brief} placeholder={t('What should the AI write?')} onChange={(e) => setBrief(e.target.value)} />
          <button type="submit" className="flxsm-btn is-primary">
            {t('Write')}
          </button>
        </form>
      )}
    </div>
  );
}

function MediaStrip({ list, onChange, onAdd }: { list: Media[]; onChange: (l: Media[]) => void; onAdd: () => void }) {
  const { t } = useSocial();
  return (
    <div className="flxsm-cmedia">
      {list.map((m, i) => (
        <div key={m.databaseId} className="flxsm-mtile" title={m.name}>
          {m.kind === 'video' && !m.thumb ? <video src={m.url} muted preload="metadata" /> : <img src={m.thumb || m.url} alt="" />}
          {m.kind === 'video' && <span className="flxsm-mkind">▶ {m.duration ? Math.round(m.duration) + ' s' : ''}</span>}
          <div className="flxsm-mtile-acts">
            {i > 0 && (
              <button type="button" aria-label={t('Move left')} onClick={() => onChange([...list.slice(0, i - 1), m, list[i - 1]!, ...list.slice(i + 1)])}>
                ‹
              </button>
            )}
            <button type="button" aria-label={t('Remove')} onClick={() => onChange(list.filter((_, j) => j !== i))}>
              ×
            </button>
          </div>
        </div>
      ))}
      <button type="button" className="flxsm-madd" onClick={onAdd}>
        <span aria-hidden>＋</span>
        <span>{t('Image / video')}</span>
      </button>
    </div>
  );
}

function Options({ account, value, onChange }: { account: Account; value: Record<string, unknown>; onChange: (v: Record<string, unknown>) => void }) {
  const { t } = useSocial();
  const keys = (account.caps.options as string[]).map(camelKey);
  const defs = optionDefs(account, t);
  if (!keys.length) return null;
  const get = (k: OptKey) => value[k];
  const set = (k: OptKey, v: unknown) => {
    const n = { ...value };
    if (v === '' || v === null || v === undefined) delete n[k];
    else n[k] = v;
    onChange(n);
  };
  return (
    <div className="flxsm-copts">
      <div className="flxsm-label">{t('{net} options', { net: account.network })}</div>
      <div className="flxsm-copts-g">
        {keys.map((k) => {
          const d = defs[k];
          if (!d) return null;
          if (d.when && !(Object.keys(d.when) as OptKey[]).every((w) => {
            let cur = get(w);
            if (cur === undefined || cur === '') cur = defs[w]?.opts?.[0]?.[0] ?? '';
            return d.when?.[w]?.includes(String(cur));
          })) return null;
          const v = get(k);
          if (d.type === 'check') {
            return (
              <label key={k} className="flxsm-chk flxsm-copt">
                <input type="checkbox" checked={v === undefined ? !!d.def : !!v} onChange={(e) => set(k, e.target.checked)} />
                <span>{d.label}</span>
              </label>
            );
          }
          return (
            <label key={k} className={'flxsm-copt' + (d.type === 'area' ? ' is-wide' : '')}>
              <span>
                {d.label}
                {d.req ? ' *' : ''}
              </span>
              {d.type === 'select' ? (
                <select value={String(v ?? '')} onChange={(e) => set(k, e.target.value)}>
                  {d.opts?.map(([val, label]) => (
                    <option key={val} value={val}>
                      {label}
                    </option>
                  ))}
                </select>
              ) : d.type === 'area' ? (
                <textarea rows={2} value={String(v ?? '')} onChange={(e) => set(k, e.target.value)} />
              ) : d.type === 'tags' ? (
                <input type="text" value={Array.isArray(v) ? (v as string[]).join(', ') : ''} onChange={(e) => set(k, e.target.value.split(',').map((x) => x.trim()).filter(Boolean))} />
              ) : d.type === 'date' ? (
                <DatePicker value={String(v ?? '')} onChange={(nv) => set(k, nv)} />
              ) : (
                <input type={d.type === 'url' ? 'url' : 'text'} maxLength={d.max} value={String(v ?? '')} onChange={(e) => set(k, e.target.value)} />
              )}
              {d.hint && <small className="flxsm-muted">{d.hint}</small>}
            </label>
          );
        })}
      </div>
    </div>
  );
}

function Preview({ account, text, media, options }: { account: Account; text: string; media: Media[]; options: Record<string, unknown> }) {
  const { t } = useSocial();
  const errs = problems(account, text, media, options, t);
  const max = account.caps.maxText;
  const shown = max && textLength(text, account.provider) > max ? [...text].slice(0, max).join('') : text;
  const over = max && shown !== text;
  const parts = shown.split(/(https?:\/\/\S+|#[\p{L}\p{N}_]+|@[\w.]+)/u);
  const first = typeof options.firstComment === 'string' ? options.firstComment : '';
  return (
    <div className={'flxsm-pv is-' + account.provider}>
      <div className="flxsm-pv-h">
        <Avatar src={account.avatar} name={account.name} size={32} />
        <div>
          <strong>{account.name}</strong>
          <small className="flxsm-muted">{account.network}</small>
        </div>
      </div>
      <div className="flxsm-pv-text">
        {parts.map((p, i) => (i % 2 ? <span key={i} className="flxsm-pv-link">{p}</span> : <span key={i}>{p}</span>))}
        {over && <span className="flxsm-bad"> … {t('(cut off)')}</span>}
      </div>
      {media.length > 0 && (
        <div className={'flxsm-pv-media is-n' + Math.min(media.length, 4)}>
          {media.slice(0, 4).map((m, i) => (
            <div key={m.databaseId} className="flxsm-pv-m">
              {m.kind === 'video' && !m.thumb ? <video src={m.url} muted preload="metadata" /> : <img src={m.thumb || m.url} alt="" />}
              {m.kind === 'video' && <span className="flxsm-pv-play">▶</span>}
              {i === 3 && media.length > 4 && <span className="flxsm-pv-more">+{media.length - 4}</span>}
            </div>
          ))}
        </div>
      )}
      {first && <div className="flxsm-pv-comment">💬 {first}</div>}
      {errs.length > 0 && (
        <ul className="flxsm-pv-errs">
          {errs.map((e) => (
            <li key={e}>{e}</li>
          ))}
        </ul>
      )}
    </div>
  );
}
