// Studio (AI drafts and the draft editor), Season plan and Audiences tabs.
import { useEffect, useState } from 'react';
import { useFlxQuery } from '@fluxwp/graphql/react';
import {
  FlxAdsAudienceCreate,
  FlxAdsAudienceDelete,
  FlxAdsAudienceLookalike,
  FlxAdsAudienceSync,
  FlxAdsAudiences,
  FlxAdsDraft,
  FlxAdsDraftDelete,
  FlxAdsDraftDuplicate,
  FlxAdsDraftGenerate,
  FlxAdsDraftSave,
  FlxAdsDraftSend,
  FlxAdsSeasonAdd,
  FlxAdsSeasonDelete,
  FlxAdsSeasons,
  FlxAdsStudio,
} from '@fluxwp/graphql/operations';
import type { FlxAdsDraftFragment } from '@fluxwp/graphql/types';
import { DatePicker, Loading } from '@fluxwp/ui';
import { Btn, Card, Empty, Field, Muted, Notice, Pill, Td, num, useAct, useEnv } from './common';
import { Fail } from './AdsScreen';

const LEADS = {
  studio:
    'Flux AI writes a campaign draft from your brief and this site\'s numbers: ad sets, audiences, budget split and ad text variants. You read and edit it here. "Send to Meta" creates everything paused; nothing is shown or spent until you turn it on under Campaigns.',
  planner:
    'The periods when giving peaks, with what the same period brought last year. Start the campaign before the period: Meta needs a few days to review and learn. "Write a draft" opens Studio with the brief and dates filled in; nothing goes to Meta from here.',
  audiences:
    'Send a group of people from Flux CRM to Meta as an audience: to show ads to similar people, or to stop showing ads to people who just donated. Only people who gave ad consent are sent, as encrypted (hashed) email and phone. The list is updated every day; someone who withdraws consent is removed straight away.',
};

const tone = (s: string) => ({ draft: 'warn', sent: 'ok', error: 'bad' } as Record<string, string>)[s] ?? 'warn';

/* ------------------------------------------------------------------ Studio */

export function StudioTab() {
  const { t, ctx, go, params } = useEnv();
  const [edit, setEdit] = useState<string | null>(null);
  if (edit !== null) return <Editor id={edit} onClose={() => setEdit(null)} />;
  return (
    <>
      <p className="flxads-lead">{t(LEADS.studio)}</p>
      <StudioHome onEdit={setEdit} pre={params} manage={ctx.canManage} goSettings={() => go('settings')} />
    </>
  );
}

function StudioHome({ onEdit, pre, manage, goSettings }: { onEdit: (id: string) => void; pre: Record<string, string>; manage: boolean; goSettings: () => void }) {
  const { t, locale } = useEnv();
  const q = useFlxQuery(FlxAdsStudio, {});
  const [f, setF] = useState({ brief: pre.brief ?? '', link: '', dailyBudget: '', objective: '', start: pre.start ?? '', end: pre.end ?? '' });
  const gen = useAct(FlxAdsDraftGenerate, (r) => r.flxAdsDraftGenerate, t('Draft written. Read it and edit what you need.'), [FlxAdsStudio], (r) => {
    const id = r.flxAdsDraftGenerate?.id;
    if (id) onEdit(id);
  });
  const dup = useAct(FlxAdsDraftDuplicate, (r) => r.flxAdsDraftDuplicate, t('Draft saved.'), [FlxAdsStudio], (r) => {
    const id = r.flxAdsDraftDuplicate?.id;
    if (id) onEdit(id);
  });
  const del = useAct(FlxAdsDraftDelete, (r) => r.flxAdsDraftDelete, t('Draft deleted.'), [FlxAdsStudio]);
  const { ask } = useEnv();
  const { go } = useEnv();
  if (q.isLoading) return <Loading />;
  const s = q.data?.flxAdsStudio;
  if (!s) return <Fail q={q} />;
  const objective = f.objective || s.objectives[0]?.key || '';
  return (
    <>
      {s.missing.length > 0 && (
        <Card pad>
          <p>{t('You can write drafts now. To send them to Meta, add: %s.', s.missing.join(', '))}</p>
          {manage && (
            <p>
              <Btn onClick={goSettings}>{t('Settings')}</Btn>
            </p>
          )}
        </Card>
      )}
      {manage && (
        <>
          <h2 className="flxads-h2">{t('Write a draft with AI')}</h2>
          <Card pad>
            {!s.aiReady && <p className="flxads-muted">{t('Flux AI is not ready. Install Flux AI & MCP and add a model key, or start from an empty draft.')}</p>}
            <form
              className="flxads-builder flxads-brief"
              onSubmit={(e) => {
                e.preventDefault();
                gen.mutate({
                  input: {
                    brief: {
                      brief: f.brief,
                      link: f.link || undefined,
                      dailyBudget: Number(f.dailyBudget),
                      objective,
                      start: f.start || undefined,
                      end: f.end || undefined,
                    },
                  },
                });
              }}
            >
              <Field label={t('Brief')}>
                <textarea rows={4} required maxLength={2000} value={f.brief} placeholder={t('e.g. Sapling campaign for November; reach people like our donors in Istanbul and Ankara, leave out people who donated this month.')} onChange={(e) => setF({ ...f, brief: e.target.value })} />
              </Field>
              <Field label={t('Page the ads open')}>
                <input type="url" value={f.link} placeholder={s.siteUrl} onChange={(e) => setF({ ...f, link: e.target.value })} />
              </Field>
              <Field label={t('Daily budget')}>
                <input type="number" min={1} step={1} required placeholder="500" value={f.dailyBudget} onChange={(e) => setF({ ...f, dailyBudget: e.target.value })} />
              </Field>
              <Field label={t('Goal')}>
                <select value={objective} onChange={(e) => setF({ ...f, objective: e.target.value })}>
                  {s.objectives.map((o) => (
                    <option key={o.key} value={o.key}>
                      {o.label}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label={t('Start')}>
                <DatePicker value={f.start} onChange={(v) => setF({ ...f, start: v })} />
              </Field>
              <Field label={t('End')}>
                <DatePicker value={f.end} onChange={(v) => setF({ ...f, end: v })} />
              </Field>
              <p className="flxads-actions flxads-actions--start">
                <Btn primary type="submit" disabled={!s.aiReady || gen.isPending}>
                  {gen.isPending ? t('Writing…') : t('Write draft')}
                </Btn>
                <Btn onClick={() => onEdit('new')}>{t('Empty draft')}</Btn>
              </p>
            </form>
          </Card>
        </>
      )}
      <h2 className="flxads-h2">{t('Drafts')}</h2>
      <Card>
        <table className="flxads-table">
          <thead>
            <tr>
              <th>{t('Campaign')}</th>
              <th>{t('Goal')}</th>
              <th className="num">{t('Daily budget')}</th>
              <th className="num">{t('Ad sets / ads')}</th>
              <th>{t('Updated')}</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {s.drafts.length === 0 && <Empty cols={6}>{t('No drafts yet.')}</Empty>}
            {s.drafts.map((d) => (
              <tr key={d.id}>
                <Td l={t('Campaign')}>
                  <a
                    className="flxads-mgr__name"
                    href="#"
                    onClick={(e) => {
                      e.preventDefault();
                      onEdit(d.id);
                    }}
                  >
                    {d.name}
                  </a>{' '}
                  <Pill tone={tone(d.status)}>{d.statusLabel}</Pill>
                </Td>
                <Td l={t('Goal')}>{d.objectiveLabel}</Td>
                <Td l={t('Daily budget')} num>
                  {d.dailyBudget.formatted}
                </Td>
                <Td l={t('Ad sets / ads')} num>
                  {num(d.adsetCount, locale)} / {num(d.adCount, locale)}
                </Td>
                <Td l={t('Updated')}>{d.updated}</Td>
                <td>
                  <div className="flxads-actions">
                    {d.status === 'sent' && d.metaCampaignId !== '' && <Btn small onClick={() => go('campaigns', { campaign: d.metaCampaignId })}>{t('Open in Campaigns')}</Btn>}
                    {manage && (
                      <>
                        <Btn small onClick={() => dup.mutate({ input: { id: d.id } })}>{t('Duplicate')}</Btn>
                        <Btn
                          small
                          danger
                          onClick={() =>
                            ask({
                              text: d.status === 'sent' ? t('Only the draft here is deleted. The paused campaign stays in Meta.') : t('The draft is deleted.'),
                              ok: t('Delete'),
                              danger: true,
                              run: () => del.mutate({ input: { id: d.id } }),
                            })
                          }
                        >
                          {t('Delete')}
                        </Btn>
                      </>
                    )}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </>
  );
}

interface AdsetForm {
  name: string;
  ageMin: number;
  ageMax: number;
  gender: string;
  countries: string;
  audiences: string[];
  exclude: string[];
  share: number;
}
interface AdForm {
  name: string;
  text: string;
  headline: string;
  description: string;
  cta: string;
  image: string;
}
interface Form {
  name: string;
  objective: string;
  link: string;
  dailyBudget: string;
  start: string;
  end: string;
  adsets: AdsetForm[];
  ads: AdForm[];
}

const newAdset = (): AdsetForm => ({ name: '', ageMin: 25, ageMax: 65, gender: 'all', countries: 'TR', audiences: [], exclude: [], share: 0 });
const newAd = (cta = 'DONATE_NOW'): AdForm => ({ name: '', text: '', headline: '', description: '', cta, image: '' });

function formOf(d: FlxAdsDraftFragment): Form {
  return {
    name: d.name,
    objective: d.objective,
    link: d.link,
    dailyBudget: d.dailyBudget ? String(d.dailyBudget) : '',
    start: d.start,
    end: d.end,
    adsets: d.adsets.map((s) => ({ ...s, countries: s.countries.join(', '), audiences: [...s.audiences], exclude: [...s.exclude] })),
    ads: d.ads.map((a) => ({ ...a })),
  };
}

function Editor({ id, onClose }: { id: string; onClose: () => void }) {
  const { t, ctx, go, ask } = useEnv();
  const isNew = id === 'new';
  const studio = useFlxQuery(FlxAdsStudio, {});
  const draft = useFlxQuery(FlxAdsDraft, { id }, { enabled: !isNew });
  const [cur, setCur] = useState(isNew ? '' : id);
  const [form, setForm] = useState<Form | null>(null);
  const s = studio.data?.flxAdsStudio;
  const d = draft.data?.flxAdsDraft ?? null;

  useEffect(() => {
    if (!s) return;
    if (isNew && !form) setForm({ name: '', objective: s.objectives[0]?.key ?? 'OUTCOME_SALES', link: s.siteUrl, dailyBudget: '', start: '', end: '', adsets: [{ ...newAdset(), share: 100 }], ads: [newAd()] });
    else if (d && !form) setForm(formOf(d));
  }, [s, d, isNew, form]);

  const save = useAct(FlxAdsDraftSave, (r) => r.flxAdsDraftSave, t('Draft saved.'), [FlxAdsStudio, FlxAdsDraft], (r) => {
    const p = r.flxAdsDraftSave;
    if (p?.id) setCur(p.id);
    if (p?.draft) setForm(formOf(p.draft));
  });
  const send = useAct(FlxAdsDraftSend, (r) => r.flxAdsDraftSend, t('The campaign is in Meta, paused. Turn it on under Campaigns when you are ready.'), [FlxAdsStudio, FlxAdsDraft]);

  if (studio.isLoading || (!isNew && draft.isLoading) || (!form && !studio.isError && !draft.isError)) return <Loading />;
  if (!s) return <Fail q={studio} />;
  if (!form || (!isNew && !d)) {
    return (
      <>
        <p>
          <a href="#" onClick={(e) => { e.preventDefault(); onClose(); }}>← {t('All drafts')}</a>
        </p>
        <Card pad>
          <p>{t('Draft not found.')}</p>
        </Card>
      </>
    );
  }
  const sent = d?.status === 'sent';
  const set = (p: Partial<Form>) => setForm({ ...form, ...p });
  const setAdset = (i: number, p: Partial<AdsetForm>) => set({ adsets: form.adsets.map((x, j) => (j === i ? { ...x, ...p } : x)) });
  const setAd = (i: number, p: Partial<AdForm>) => set({ ads: form.ads.map((x, j) => (j === i ? { ...x, ...p } : x)) });
  const toggle = (list: string[], v: string, on: boolean) => (on ? [...list, v] : list.filter((x) => x !== v));
  const dirtyId = cur || (d?.id ?? '');

  return (
    <>
      <p>
        <a href="#" onClick={(e) => { e.preventDefault(); onClose(); }}>← {t('All drafts')}</a>
      </p>
      {sent && (
        <Card pad>
          <p>{t('This draft is in Meta as a paused campaign. Turn it on or change it under Campaigns; to try a new version, duplicate it.')}</p>
          <p>
            <Btn primary onClick={() => go('campaigns')}>{t('Open in Campaigns')}</Btn>
          </p>
        </Card>
      )}
      {d && d.error !== '' && <Notice kind="bad">{d.error}</Notice>}
      {d && d.notes !== '' && (
        <section className="flxads-card flxads-pad flxads-note">
          <h3>{t('Why this plan (AI)')}</h3>
          <p style={{ whiteSpace: 'pre-wrap' }}>{d.notes}</p>
        </section>
      )}
      <form
        className="flxads-studio"
        onSubmit={(e) => {
          e.preventDefault();
          save.mutate({
            input: {
              id: dirtyId || undefined,
              draft: {
                name: form.name,
                objective: form.objective,
                link: form.link,
                dailyBudget: Number(form.dailyBudget) || 0,
                start: form.start || undefined,
                end: form.end || undefined,
                adsets: form.adsets.map((a) => ({ ...a, countries: a.countries.split(',').map((c) => c.trim().toUpperCase()).filter(Boolean) })),
                ads: form.ads,
              },
            },
          });
        }}
      >
        <fieldset disabled={sent}>
          <h2 className="flxads-h2">{t('Campaign')}</h2>
          <Card pad>
            <div className="flxads-builder">
              <Field label={t('Name')}>
                <input required maxLength={120} value={form.name} onChange={(e) => set({ name: e.target.value })} />
              </Field>
              <Field label={t('Goal')}>
                <select value={form.objective} onChange={(e) => set({ objective: e.target.value })}>
                  {s.objectives.map((o) => (
                    <option key={o.key} value={o.key}>
                      {o.label}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label={t('Page the ads open')}>
                <input type="url" required value={form.link} onChange={(e) => set({ link: e.target.value })} />
              </Field>
              <Field label={t('Daily budget')}>
                <input type="number" min={1} step={1} required value={form.dailyBudget} onChange={(e) => set({ dailyBudget: e.target.value })} />
              </Field>
              <Field label={t('Start')}>
                <DatePicker value={form.start} onChange={(v) => set({ start: v })} />
              </Field>
              <Field label={t('End')}>
                <DatePicker value={form.end} onChange={(v) => set({ end: v })} />
              </Field>
            </div>
          </Card>

          <h2 className="flxads-h2">{t('Ad sets (who sees it)')}</h2>
          {form.adsets.map((a, i) => (
            <section key={i} className="flxads-card flxads-pad flxads-row">
              <button type="button" className="flxads-row__del" aria-label={t('Remove')} onClick={() => set({ adsets: form.adsets.filter((_, j) => j !== i) })}>
                ×
              </button>
              <div className="flxads-builder">
                <Field label={t('Ad set name')}>
                  <input maxLength={120} value={a.name} onChange={(e) => setAdset(i, { name: e.target.value })} />
                </Field>
                <Field label={t('Ages')}>
                  <span className="flxads-inline">
                    <input type="number" min={18} max={65} value={a.ageMin} onChange={(e) => setAdset(i, { ageMin: Number(e.target.value) })} /> –{' '}
                    <input type="number" min={18} max={65} value={a.ageMax} onChange={(e) => setAdset(i, { ageMax: Number(e.target.value) })} />
                  </span>
                </Field>
                <Field label={t('Gender')}>
                  <select value={a.gender} onChange={(e) => setAdset(i, { gender: e.target.value })}>
                    <option value="all">{t('all genders')}</option>
                    <option value="female">{t('women')}</option>
                    <option value="male">{t('men')}</option>
                  </select>
                </Field>
                <Field label={t('Countries (ISO, comma)')}>
                  <input value={a.countries} onChange={(e) => setAdset(i, { countries: e.target.value })} />
                </Field>
                <Field label={t('Budget share %')}>
                  <input type="number" min={0} max={100} value={a.share} onChange={(e) => setAdset(i, { share: Number(e.target.value) })} />
                </Field>
                {s.audiences.length > 0 &&
                  (['audiences', 'exclude'] as const).map((k) => (
                    <fieldset key={k} className="flxads-auds">
                      <legend>{k === 'audiences' ? t('Show to audiences') : t('Leave out audiences')}</legend>
                      {s.audiences.map((o) => (
                        <label key={o.key} className="flxads-check">
                          <input type="checkbox" checked={a[k].includes(o.key)} onChange={(e) => setAdset(i, { [k]: toggle(a[k], o.key, e.target.checked) })} /> {o.label}
                        </label>
                      ))}
                    </fieldset>
                  ))}
              </div>
            </section>
          ))}
          {form.adsets.length < 6 && (
            <p>
              <Btn onClick={() => set({ adsets: [...form.adsets, newAdset()] })}>+ {t('Add ad set')}</Btn>
            </p>
          )}

          <h2 className="flxads-h2">{t('Ads (text variants)')}</h2>
          <p className="flxads-muted">{t('Each variant runs in every ad set, so Meta can find the text that works best.')}</p>
          {form.ads.map((a, i) => (
            <section key={i} className="flxads-card flxads-pad flxads-row flxads-adrow">
              <button type="button" className="flxads-row__del" aria-label={t('Remove')} onClick={() => set({ ads: form.ads.filter((_, j) => j !== i) })}>
                ×
              </button>
              <div className="flxads-builder">
                <Field label={t('Variant name')}>
                  <input maxLength={80} value={a.name} onChange={(e) => setAd(i, { name: e.target.value })} />
                </Field>
                <Field label={t('Primary text')} className="flxads-wide">
                  <textarea rows={3} maxLength={2000} value={a.text} onChange={(e) => setAd(i, { text: e.target.value })} />
                </Field>
                <Field label={t('Headline')}>
                  <input maxLength={100} value={a.headline} onChange={(e) => setAd(i, { headline: e.target.value })} />
                </Field>
                <Field label={t('Description')}>
                  <input maxLength={120} value={a.description} onChange={(e) => setAd(i, { description: e.target.value })} />
                </Field>
                <Field label={t('Button')}>
                  <select value={a.cta} onChange={(e) => setAd(i, { cta: e.target.value })}>
                    {s.ctas.map((o) => (
                      <option key={o.key} value={o.key}>
                        {o.label}
                      </option>
                    ))}
                  </select>
                </Field>
                <Field label={t('Image address (optional)')}>
                  <input type="url" placeholder="https://…/image.jpg" value={a.image} onChange={(e) => setAd(i, { image: e.target.value })} />
                </Field>
              </div>
              <Preview ad={a} host={hostOf(s.siteUrl)} ctaLabel={s.ctas.find((c) => c.key === a.cta)?.label ?? ''} />
            </section>
          ))}
          {form.ads.length < 6 && (
            <p>
              <Btn onClick={() => set({ ads: [...form.ads, newAd()] })}>+ {t('Add variant')}</Btn>
            </p>
          )}
          {!sent && (
            <p className="flxads-actions flxads-actions--start">
              <Btn primary type="submit" disabled={save.isPending}>
                {t('Save draft')}
              </Btn>
              <Btn onClick={onClose}>{t('Cancel')}</Btn>
            </p>
          )}
        </fieldset>
      </form>

      {!sent && dirtyId !== '' && ctx.canManage && (
        <>
          <h2 className="flxads-h2">{t('Send to Meta')}</h2>
          <Card pad>
            {s.missing.length > 0 ? (
              <p>{t('Missing: %s.', s.missing.join(', '))}</p>
            ) : (
              <>
                <p className="flxads-muted">{t('Save your edits first. Ads open the page with UTM tags, so donations show up in the report and under Campaigns.')}</p>
                <Btn
                  primary
                  disabled={send.isPending}
                  onClick={() =>
                    ask({
                      text: t('A paused campaign with %1$d ad sets and %2$d ads is created in Meta. Nothing is shown or spent until you turn it on under Campaigns.', form.adsets.length, form.adsets.length * form.ads.length),
                      ok: t('Create paused'),
                      run: () => send.mutate({ input: { id: dirtyId } }),
                    })
                  }
                >
                  {t('Create in Meta (paused)')}
                </Btn>
              </>
            )}
          </Card>
        </>
      )}
    </>
  );
}

function hostOf(url: string): string {
  try {
    return new URL(url).host;
  } catch {
    return url;
  }
}

/** Live preview of one ad: text, image, headline and button. */
function Preview({ ad, host, ctaLabel }: { ad: AdForm; host: string; ctaLabel: string }) {
  const safe = /^https?:\/\//i.test(ad.image) ? ad.image : '';
  return (
    <div className="flxads-pv" aria-hidden="true">
      <p>{ad.text}</p>
      <div className="flxads-pv__img" style={safe ? { backgroundImage: 'url("' + safe.replace(/["\\]/g, '') + '")' } : undefined} />
      <div className="flxads-pv__foot">
        <div>
          <small>{host}</small>
          <strong>{ad.headline}</strong>
          <Muted>{ad.description}</Muted>
        </div>
        <span>{ctaLabel}</span>
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ Season plan */

export function PlannerTab() {
  const { t, tn, locale, ctx, go, ask } = useEnv();
  const q = useFlxQuery(FlxAdsSeasons, {});
  const [f, setF] = useState({ name: '', prep: '14', start: '', end: '' });
  const add = useAct(FlxAdsSeasonAdd, (r) => r.flxAdsSeasonAdd, t('Date saved.'), [FlxAdsSeasons], () => setF({ name: '', prep: '14', start: '', end: '' }));
  const del = useAct(FlxAdsSeasonDelete, (r) => r.flxAdsSeasonDelete, t('Date deleted.'), [FlxAdsSeasons]);
  if (q.isLoading) return <Loading />;
  const list = q.data?.flxAdsSeasons;
  if (!list) return <Fail q={q} />;
  return (
    <>
      <p className="flxads-lead">{t(LEADS.planner)}</p>
      <div className="flxads-opts">
        {list.map((e) => (
          <article key={e.key} className={'flxads-card flxads-opt flxads-opt--' + e.tone}>
            <header>
              <span className={'flxads-pill flxads-pill--' + e.tone}>{e.stateLabel}</span>{' '}
              <small className="flxads-muted">{e.state === 'now' ? t('running') : tn('in %s day', 'in %s days', e.days, num(e.days, locale))}</small>
            </header>
            <h3>{e.name}</h3>
            <p className="flxads-muted flxads-opt__ctx">
              {e.startLabel} – {e.endLabel}
              {e.approx ? ' · ' + t('approximate; check the official calendar') : ''}
            </p>
            {e.hasLast ? (
              <p>
                {t('Last year: %1$s payments, %2$s, %3$s new donors.', num(e.last.payments, locale), e.last.income.formatted, num(e.last.newCount, locale))}
                {e.last.spend.amount > 0 && ' ' + t('Ad spend %1$s (ROAS %2$s).', e.last.spend.formatted, e.last.roas === null ? '—' : num(e.last.roas, locale, 1) + '×')}
              </p>
            ) : (
              <p className="flxads-muted">{t('No payments in this period last year.')}</p>
            )}
            <div className="flxads-actions flxads-actions--start">
              <Btn primary onClick={() => go('studio', { brief: e.brief, start: e.start, end: e.end })}>
                {t('Write a draft')}
              </Btn>
              {ctx.canManage && e.custom && e.id && (
                <Btn danger onClick={() => ask({ text: t('Delete this date?'), ok: t('Delete'), danger: true, run: () => del.mutate({ input: { id: e.id as string } }) })}>
                  {t('Delete')}
                </Btn>
              )}
            </div>
          </article>
        ))}
      </div>
      {ctx.canManage && (
        <>
          <h2 className="flxads-h2">{t('Add your own date')}</h2>
          <Card pad>
            <p className="flxads-muted">{t('A campaign day of your own: an anniversary, an event, a planting day.')}</p>
            <form
              className="flxads-builder"
              onSubmit={(e) => {
                e.preventDefault();
                add.mutate({ input: { season: { name: f.name, prep: Number(f.prep) || 14, start: f.start, end: f.end || undefined } } });
              }}
            >
              <Field label={t('Name')}>
                <input required maxLength={80} value={f.name} onChange={(e) => setF({ ...f, name: e.target.value })} />
              </Field>
              <Field label={t('Prepare days before')}>
                <input type="number" min={3} max={60} value={f.prep} onChange={(e) => setF({ ...f, prep: e.target.value })} />
              </Field>
              <Field label={t('Start')}>
                <DatePicker required value={f.start} onChange={(v) => setF({ ...f, start: v })} />
              </Field>
              <Field label={t('End')}>
                <DatePicker value={f.end} onChange={(v) => setF({ ...f, end: v })} />
              </Field>
              <p className="flxads-actions flxads-actions--start">
                <Btn primary type="submit" disabled={add.isPending}>
                  {t('Add')}
                </Btn>
              </p>
            </form>
          </Card>
        </>
      )}
    </>
  );
}

/* ------------------------------------------------------------------ Audiences */

const RATIOS = [1, 2, 3, 5, 10];

export function AudiencesTab() {
  const { t, locale, ctx, go, ask } = useEnv();
  const q = useFlxQuery(FlxAdsAudiences, {});
  const [f, setF] = useState({ name: '', source: '', purpose: '', lookalike: false, country: 'TR', ratio: '1' });
  const [lk, setLk] = useState({ id: '', country: 'TR', ratio: '1' });
  const create = useAct(FlxAdsAudienceCreate, (r) => r.flxAdsAudienceCreate, t('Audience created in Meta and filled.'), [FlxAdsAudiences], () => setF((o) => ({ ...o, name: '' })));
  const look = useAct(FlxAdsAudienceLookalike, (r) => r.flxAdsAudienceLookalike, t('Lookalike audience created. Meta fills it within a few hours.'), [FlxAdsAudiences]);
  const sync = useAct(FlxAdsAudienceSync, (r) => r.flxAdsAudienceSync, t('Audience updated.'), [FlxAdsAudiences]);
  const del = useAct(FlxAdsAudienceDelete, (r) => r.flxAdsAudienceDelete, t('Audience deleted.'), [FlxAdsAudiences]);
  if (q.isLoading) return <Loading />;
  const p = q.data?.flxAdsAudiences;
  if (!p) return <Fail q={q} />;
  const lead = <p className="flxads-lead">{t(LEADS.audiences)}</p>;
  if (!p.crmReady) {
    return (
      <>
        {lead}
        <Card pad>
          <p>{t('Audiences need Flux CRM: people, ad consent and segments come from there.')}</p>
        </Card>
      </>
    );
  }
  const man = ctx.canManage;
  const source = f.source || p.sources[0]?.key || '';
  const purpose = f.purpose || p.purposes[0]?.key || '';
  const lkId = lk.id || p.customAudiences[0]?.key || '';
  return (
    <>
      {lead}
      {!p.metaReady && (
        <Card pad>
          <p>{t('Enter the Meta ad account ID and a Marketing API key with the ads_management permission in Settings › Meta.')}</p>
          {man && (
            <p>
              <Btn onClick={() => go('settings')}>{t('Settings')}</Btn>
            </p>
          )}
        </Card>
      )}
      <Card>
        <table className="flxads-table">
          <thead>
            <tr>
              <th>{t('Audience')}</th>
              <th>{t('Group')}</th>
              <th>{t('Use')}</th>
              <th className="num">{t('People')}</th>
              <th>{t('Updated')}</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {p.audiences.length === 0 && <Empty cols={6}>{t('No audience yet.')}</Empty>}
            {p.audiences.map((a) => (
              <tr key={a.id}>
                <Td l={t('Audience')}>
                  <strong>{a.name}</strong>
                  <small className="flxads-muted"> Meta ID {a.metaId}</small>
                </Td>
                <Td l={t('Group')}>{a.groupLabel}</Td>
                <Td l={t('Use')}>
                  <span className={'flxads-pill flxads-pill--' + a.purpose}>{a.purposeLabel}</span>
                </Td>
                <Td l={t('People')} num>
                  {a.kind === 'lookalike' ? <Muted>{t('Meta fills it')}</Muted> : num(a.count, locale)}
                </Td>
                <Td l={t('Updated')}>
                  {a.error !== '' ? (
                    <span className="flxads-test flxads-test--bad">{a.error}</span>
                  ) : a.syncedAt && a.added !== null ? (
                    t('%1$s · +%2$d / −%3$d', a.syncedAt.relative, a.added, a.removed ?? 0)
                  ) : (
                    (a.syncedAt?.relative ?? '')
                  )}
                </Td>
                <td>
                  <div className="flxads-actions">
                    {man && a.canSync && (
                      <Btn small disabled={sync.isPending} onClick={() => sync.mutate({ input: { id: a.id } })}>
                        {t('Update now')}
                      </Btn>
                    )}
                    {man && (
                      <Btn small danger onClick={() => ask({ text: t('The audience is deleted in Meta too. Ad sets that use it stop reaching these people.'), ok: t('Delete'), danger: true, run: () => del.mutate({ input: { id: a.id } }) })}>
                        {t('Delete')}
                      </Btn>
                    )}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>

      {man && p.metaReady && (
        <>
          <h2 className="flxads-h2">{t('New audience')}</h2>
          <Card pad>
            <form
              className="flxads-builder"
              onSubmit={(e) => {
                e.preventDefault();
                create.mutate({ input: { audience: { name: f.name, source, purpose, lookalike: f.lookalike, country: f.lookalike ? f.country : undefined, ratio: f.lookalike ? Number(f.ratio) : undefined } } });
              }}
            >
              <Field label={t('Name in Meta')}>
                <input required maxLength={100} value={f.name} placeholder={t('Donors – last 12 months')} onChange={(e) => setF({ ...f, name: e.target.value })} />
              </Field>
              <Field label={t('Group')}>
                <select value={source} onChange={(e) => setF({ ...f, source: e.target.value })}>
                  {p.sources.map((o) => (
                    <option key={o.key} value={o.key}>
                      {o.label + ' (' + t('%s people', num(o.people, locale)) + ')'}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label={t('Use')}>
                <select value={purpose} onChange={(e) => setF({ ...f, purpose: e.target.value })}>
                  {p.purposes.map((o) => (
                    <option key={o.key} value={o.key}>
                      {o.label}
                    </option>
                  ))}
                </select>
              </Field>
              <label className="flxads-check">
                <input type="checkbox" checked={f.lookalike} onChange={(e) => setF({ ...f, lookalike: e.target.checked })} /> <span>{t('Also create a lookalike audience (people similar to this group)')}</span>
              </label>
              {f.lookalike && (
                <>
                  <Field label={t('Lookalike country')}>
                    <input maxLength={2} value={f.country} onChange={(e) => setF({ ...f, country: e.target.value })} />
                  </Field>
                  <Field label={t('Lookalike size')}>
                    <select value={f.ratio} onChange={(e) => setF({ ...f, ratio: e.target.value })}>
                      {RATIOS.map((r) => (
                        <option key={r} value={r}>
                          {t('%d%% of the country', r)}
                        </option>
                      ))}
                    </select>
                  </Field>
                </>
              )}
              <p>
                <Btn primary type="submit" disabled={create.isPending}>
                  {t('Create in Meta')}
                </Btn>
              </p>
            </form>
            <p className="flxads-muted">{t('Meta needs at least 100 matched people for a lookalike. 1% is the closest match; larger sizes reach more people with less similarity.')}</p>
          </Card>

          {p.customAudiences.length > 0 && (
            <>
              <h2 className="flxads-h2">{t('Lookalike from an existing audience')}</h2>
              <Card pad>
                <form
                  className="flxads-builder"
                  onSubmit={(e) => {
                    e.preventDefault();
                    look.mutate({ input: { id: lkId, country: lk.country, ratio: Number(lk.ratio) } });
                  }}
                >
                  <Field label={t('Source audience')}>
                    <select value={lkId} onChange={(e) => setLk({ ...lk, id: e.target.value })}>
                      {p.customAudiences.map((o) => (
                        <option key={o.key} value={o.key}>
                          {o.label}
                        </option>
                      ))}
                    </select>
                  </Field>
                  <Field label={t('Lookalike country')}>
                    <input maxLength={2} value={lk.country} onChange={(e) => setLk({ ...lk, country: e.target.value })} />
                  </Field>
                  <Field label={t('Lookalike size')}>
                    <select value={lk.ratio} onChange={(e) => setLk({ ...lk, ratio: e.target.value })}>
                      {RATIOS.map((r) => (
                        <option key={r} value={r}>
                          {t('%d%% of the country', r)}
                        </option>
                      ))}
                    </select>
                  </Field>
                  <p>
                    <Btn type="submit" disabled={look.isPending}>
                      {t('Create lookalike')}
                    </Btn>
                  </p>
                </form>
              </Card>
            </>
          )}
        </>
      )}
    </>
  );
}
