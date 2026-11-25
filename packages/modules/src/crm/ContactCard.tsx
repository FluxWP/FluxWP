// CRM › one contact: summary, timeline with notes, details (view + edit), tags, sources; and the new-contact form.
import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import {
  FlxCrmContact,
  FlxCrmContactCreate,
  FlxCrmContactDelete,
  FlxCrmContactUpdate,
  FlxCrmEventAdd,
  FlxCrmEventDelete,
  FlxCrmTagAdd,
  FlxCrmTagRemove,
  FlxCrmTimeline,
} from '@fluxwp/graphql/operations';
import type { FlxContactNoteType, FlxCrmContactCardFragment, FlxCrmEventFragment, FlxCrmTimelineQuery } from '@fluxwp/graphql/types';
import { Loading, Modal } from '@fluxwp/ui';
import { Avatar, Card, Head, Ic, Kpi, Money, RolePills, errText, href, siteTimeZone, useCrm } from './ui';
import { PersonSections } from './Sections';

type Card_ = FlxCrmContactCardFragment;
const NOTE_TYPES: Record<string, FlxContactNoteType> = { note: 'NOTE', call: 'CALL', meeting: 'MEETING', email_sent: 'EMAIL_SENT' };

interface Form {
  name: string;
  email: string;
  phone: string;
  company: string;
  city: string;
  roles: string[];
  consent: { email: boolean; sms: boolean; whatsapp: boolean; ads: boolean };
  ownerId: string;
  note: string;
  tags: string;
}
const blank: Form = { name: '', email: '', phone: '', company: '', city: '', roles: [], consent: { email: false, sms: false, whatsapp: false, ads: false }, ownerId: '0', note: '', tags: '' };
const formOf = (c: Card_): Form => ({
  name: c.rawName,
  email: c.email ?? '',
  phone: c.phone ? '+' + c.phone.e164.replace(/\D/g, '') : '',
  company: c.company ?? '',
  city: c.city ?? '',
  roles: [...(c.roles ?? [])],
  consent: { email: false, sms: false, whatsapp: false, ads: false, ...c.consent },
  ownerId: String(c.ownerId ?? 0),
  note: c.note ?? '',
  tags: '',
});

function Fields({ f, set, withTags }: { f: Form; set: (f: Form) => void; withTags?: boolean }) {
  const { t, shell } = useCrm();
  const m = shell.flxCrmMeta;
  const up = (patch: Partial<Form>) => set({ ...f, ...patch });
  return (
    <div className="flxcrm-form">
      <label>
        <span>{t('Full name')}</span>
        <input type="text" value={f.name} autoComplete="off" onChange={(e) => up({ name: e.currentTarget.value })} />
      </label>
      <label>
        <span>{t('E-mail')}</span>
        <input type="email" value={f.email} autoComplete="off" onChange={(e) => up({ email: e.currentTarget.value })} />
      </label>
      <label>
        <span>{t('Phone')}</span>
        <input type="tel" value={f.phone} placeholder="+90 5xx xxx xx xx" autoComplete="off" onChange={(e) => up({ phone: e.currentTarget.value })} />
      </label>
      <div className="flxcrm-form__row">
        <label>
          <span>{t('Organisation')}</span>
          <input type="text" value={f.company} onChange={(e) => up({ company: e.currentTarget.value })} />
        </label>
        <label>
          <span>{t('City')}</span>
          <input type="text" value={f.city} onChange={(e) => up({ city: e.currentTarget.value })} />
        </label>
      </div>
      <fieldset className="flxcrm-fs">
        <legend>{t('Roles')}</legend>
        <div className="flxcrm-rolepick">
          {m.roles
            .filter((r) => !r.hidden || f.roles.includes(r.key))
            .map((r) => (
              <label key={r.key} style={{ ['--rc' as string]: r.color }}>
                <input type="checkbox" checked={f.roles.includes(r.key)} onChange={(e) => up({ roles: e.currentTarget.checked ? [...f.roles, r.key] : f.roles.filter((x) => x !== r.key) })} />
                <span>{r.label}</span>
              </label>
            ))}
        </div>
      </fieldset>
      <fieldset className="flxcrm-fs">
        <legend>{t('Contact consents')}</legend>
        <div className="flxcrm-switches">
          {m.consents.map((c) => {
            const k = c.key as keyof Form['consent'];
            return (
              <label key={c.key} className="flxcrm-switch">
                <input type="checkbox" checked={f.consent[k] ?? false} onChange={(e) => up({ consent: { ...f.consent, [k]: e.currentTarget.checked } })} />
                <span>{c.label}</span>
              </label>
            );
          })}
        </div>
      </fieldset>
      <label>
        <span>{t('Owner')}</span>
        <select value={f.ownerId} onChange={(e) => up({ ownerId: e.currentTarget.value })}>
          <option value="0">{t('— none —')}</option>
          {m.owners.map((o) => (
            <option key={o.databaseId} value={o.databaseId}>
              {o.name}
            </option>
          ))}
        </select>
      </label>
      <label>
        <span>{t('Short note')}</span>
        <textarea rows={3} value={f.note} onChange={(e) => up({ note: e.currentTarget.value })} />
      </label>
      {withTags && (
        <label>
          <span>{t('Tags')}</span>
          <input type="text" value={f.tags} placeholder={t('separate with commas')} onChange={(e) => up({ tags: e.currentTarget.value })} />
        </label>
      )}
    </div>
  );
}

export function ContactNew({ go }: { go: (to: string) => void }) {
  const crm = useCrm();
  const { t } = crm;
  const [f, setF] = useState<Form>(blank);
  const create = useFlxMutation(FlxCrmContactCreate);
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  if (!crm.can) return <div className="flxcrm-notice is-bad">{t('You are not allowed to do this.')}</div>;
  const submit = async (e: FormEvent) => {
    e.preventDefault();
    try {
      const r = (await create.mutateAsync({
        input: {
          name: f.name,
          email: f.email,
          phone: f.phone,
          company: f.company,
          city: f.city,
          roles: f.roles,
          consent: f.consent,
          ownerId: Number(f.ownerId) || null,
          note: f.note,
          tags: f.tags.split(',').map((x) => x.trim()).filter(Boolean),
        },
      })).flxContactCreate;
      if (!r || !r.contact) throw new Error(errText(r?.errors));
      void qc.invalidateQueries({ queryKey: [scope, 'gql'] });
      crm.reloadShell();
      crm.tell('ok', r.existed ? t('A contact with this e-mail or phone already exists; their card is open.') : t('Contact added.'));
      go('contact/' + r.contact.databaseId);
    } catch (err) {
      crm.fail(err);
    }
  };
  return (
    <>
      <Head title={t('New contact')} lead={t('An e-mail or a phone number is enough. If a contact with the same e-mail or phone exists, their card opens.')} back={{ to: 'contacts', label: t('Contacts') }} />
      <div className="flxcrm-card flxcrm-narrow">
        <form onSubmit={(e) => void submit(e)}>
          <Fields f={f} set={setF} withTags />
          <p className="flxcrm-actions">
            <button className="button button-primary" disabled={create.isPending}>
              {t('Add contact')}
            </button>
            <a className="button" href={href('contacts')}>
              {t('Cancel')}
            </a>
          </p>
        </form>
      </div>
    </>
  );
}

/** Timeline pages: first page on load / filter change, then "load more". */
function useTimeline(id: string, filter: string) {
  const { client } = useGraphQL();
  const [events, setEvents] = useState<FlxCrmEventFragment[]>([]);
  const [cursor, setCursor] = useState<string | null>(null);
  const [more, setMore] = useState(false);
  const [loading, setLoading] = useState(true);
  const [tick, setTick] = useState(0);
  const load = useCallback(
    async (after: string | null, replace: boolean) => {
      setLoading(true);
      try {
        const r: FlxCrmTimelineQuery = await client.request(FlxCrmTimeline, { id, filter: filter || null, first: 20, after });
        const tl = r.flxContact?.timeline;
        setEvents((old) => (replace ? (tl?.nodes ?? []) : [...old, ...(tl?.nodes ?? [])]));
        setCursor(tl?.pageInfo.endCursor ?? null);
        setMore(tl?.pageInfo.hasNextPage ?? false);
      } finally {
        setLoading(false);
      }
    },
    [client, id, filter],
  );
  useEffect(() => {
    void load(null, true);
  }, [load, tick]);
  return { events, more, loading, loadMore: () => void load(cursor, false), reload: () => setTick((n) => n + 1) };
}

export function ContactCard({ id, go }: { id: string; go: (to: string) => void }) {
  const crm = useCrm();
  const { t, num, can, shell } = crm;
  const qc = useQueryClient();
  const { scope } = useGraphQL();
  const q = useFlxQuery(FlxCrmContact, { id });
  const [filter, setFilter] = useState('');
  const tl = useTimeline(id, filter);
  const [editing, setEditing] = useState(false);
  const [f, setF] = useState<Form>(blank);
  const [note, setNote] = useState('');
  const [noteType, setNoteType] = useState<FlxContactNoteType>('NOTE');
  const [tag, setTag] = useState('');
  const [menu, setMenu] = useState(false);
  const [confirm, setConfirm] = useState<'' | 'contact' | number>('');
  const update = useFlxMutation(FlxCrmContactUpdate);
  const del = useFlxMutation(FlxCrmContactDelete);
  const addEvent = useFlxMutation(FlxCrmEventAdd);
  const delEvent = useFlxMutation(FlxCrmEventDelete);
  const addTag = useFlxMutation(FlxCrmTagAdd);
  const rmTag = useFlxMutation(FlxCrmTagRemove);

  const refresh = () => {
    void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxCrmContact) });
    crm.reloadShell();
  };

  if (q.isLoading) return <Loading />;
  const c = q.data?.flxContact;
  if (!c) {
    return (
      <>
        <Head title={t('Contact not found')} back={{ to: 'contacts', label: t('Contacts') }} />
        <p>
          <a href={href('contacts')}>{t('Back to contacts')}</a>
        </p>
      </>
    );
  }
  const paid = (c.paidDays ?? 0) > 0;
  const manual = shell.flxCrmMeta.eventTypes.filter((e) => e.manual);
  const wa = c.phone ? c.phone.e164.replace(/\D/g, '') : '';

  const save = async (e: FormEvent) => {
    e.preventDefault();
    try {
      const r = (await update.mutateAsync({ input: { id: c.id, name: f.name, email: f.email, phone: f.phone, company: f.company, city: f.city, roles: f.roles, consent: f.consent, ownerId: Number(f.ownerId) || 0, note: f.note } })).flxContactUpdate;
      if (!r?.contact) throw new Error(errText(r?.errors));
      qc.setQueryData(flxQueryKey(scope, FlxCrmContact, { id }), { flxContact: r.contact });
      setEditing(false);
      crm.tell('ok', t('Saved.'));
      refresh();
      tl.reload();
    } catch (err) {
      crm.fail(err);
    }
  };
  const addNote = async (e: FormEvent) => {
    e.preventDefault();
    if (!note.trim()) return;
    try {
      const r = (await addEvent.mutateAsync({ input: { contactId: c.id, text: note, type: noteType } })).flxContactEventAdd;
      if (!r?.event) throw new Error(errText(r?.errors));
      setNote('');
      tl.reload();
      refresh();
    } catch (err) {
      crm.fail(err);
    }
  };
  const addTags = async (e: FormEvent) => {
    e.preventDefault();
    try {
      for (const name of tag.split(',').map((x) => x.trim()).filter(Boolean)) {
        const r = (await addTag.mutateAsync({ input: { contactId: c.id, name } })).flxContactTagAdd;
        if (!r?.contact) throw new Error(errText(r?.errors));
      }
      setTag('');
      refresh();
    } catch (err) {
      crm.fail(err);
    }
  };
  const removeTag = async (tagId: number) => {
    try {
      const r = (await rmTag.mutateAsync({ input: { contactId: c.id, tagId } })).flxContactTagRemove;
      if (!r?.contact) throw new Error(errText(r?.errors));
      refresh();
    } catch (err) {
      crm.fail(err);
    }
  };
  const confirmed = async () => {
    const what = confirm;
    setConfirm('');
    try {
      if (what === 'contact') {
        const r = (await del.mutateAsync({ input: { id: c.id } })).flxContactDelete;
        if (!r || r.errors.length) throw new Error(errText(r?.errors));
        void qc.invalidateQueries({ queryKey: [scope, 'gql'] });
        crm.reloadShell();
        crm.tell('ok', t('Contact deleted.'));
        go('contacts');
      } else if (typeof what === 'number') {
        const r = (await delEvent.mutateAsync({ input: { id: what } })).flxContactEventDelete;
        if (!r || r.errors.length) throw new Error(errText(r?.errors));
        tl.reload();
      }
    } catch (err) {
      crm.fail(err);
    }
  };

  let lastDay = '';
  const tz = siteTimeZone(shell.flxCrmMeta.timezone);
  const none = <span className="flxcrm-muted">—</span>;
  return (
    <>
      <a className="flxcrm-back" href={href('contacts')}>
        <Ic n="back" size={15} />
        {t('Contacts')}
      </a>
      <header className="flxcrm-card flxcrm-hero">
        <Avatar name={c.name} size="lg" />
        <div className="flxcrm-hero__main">
          <h2 className="flxcrm-hero__name" aria-level={1}>{c.name}</h2>
          <span className="flxcrm-pills">
            <RolePills keys={c.roles ?? []} />
            {c.status === 'ARCHIVED' && <span className="flxcrm-badge">{t('Archived')}</span>}
            {c.segmentLabel && paid && <span className="flxcrm-badge is-info">{c.segmentLabel}</span>}
          </span>
          <p className="flxcrm-hero__meta">
            {c.email && (
              <a href={'mailto:' + c.email}>
                <Ic n="mail" size={14} />
                {c.email}
              </a>
            )}
            {c.phone && (
              <a href={'tel:' + c.phone.e164}>
                <Ic n="phone" size={14} />
                {c.phone.display}
              </a>
            )}
            {c.company && <span>{c.company}</span>}
            {c.city && <span>{c.city}</span>}
            <span className="flxcrm-muted">{t('Since %1$s · %2$s', c.createdAt?.label ?? '—', c.sourceLabel ?? t('Added by hand.'))}</span>
          </p>
        </div>
        <div className="flxcrm-hero__actions">
          {wa && (
            <a className="button" href={'https://wa.me/' + wa} target="_blank" rel="noopener">
              <Ic n="message" /> {t('WhatsApp')}
            </a>
          )}
          {c.email && can && (
            <a className="button" href={'mailto:' + c.email}>
              <Ic n="mail" /> {t('Write an e-mail')}
            </a>
          )}
          <PersonSections sections={c.sections} placement="ACTIONS" />
          {can && (
            <span style={{ position: 'relative' }}>
              <button type="button" className="button" aria-label={t('More actions')} aria-expanded={menu} onClick={() => setMenu((m) => !m)}>
                ⋯
              </button>
              {menu && (
                <div className="flxcrm-more__menu" role="menu">
                  <button
                    type="button"
                    className="flxcrm-more__item is-bad"
                    role="menuitem"
                    onClick={() => {
                      setMenu(false);
                      setConfirm('contact');
                    }}
                  >
                    {t('Delete permanently')}
                  </button>
                </div>
              )}
            </span>
          )}
        </div>
      </header>

      <div className="flxcrm-kpis flxcrm-kpis--6">
        <Kpi icon="coins" label={t('Total value')} value={paid && c.ltv ? c.ltv.formatted : '—'} hint={[c.donations ? t('%s donations', num(c.donations)) : '', c.orders ? t('%s orders', num(c.orders)) : ''].filter(Boolean).join(' · ')} />
        <Kpi icon="heart" label={t('First payment')} value={paid && c.firstPaidAt ? c.firstPaidAt.label.split(' ')[0] : '—'} />
        <Kpi icon="clock" label={t('Last payment')} value={paid && c.lastPaidAt ? c.lastPaidAt.relative : '—'} hint={c.avgGapDays ? t('once every %s days on average', num(c.avgGapDays)) : ''} />
        <Kpi icon="alert" label={t('Churn risk')} value={paid ? '%' + c.churn : '—'} warn={paid && !!c.atRisk} />
        <Kpi icon="chart" label={t('RFM')} value={paid && c.rfm && c.rfm.recency ? `${c.rfm.recency} · ${c.rfm.frequency} · ${c.rfm.monetary}` : '—'} hint={t('recency · frequency · value (1–5)')} />
        <Kpi icon="trend" label={t('Last activity')} value={c.lastActivityAt?.relative ?? '—'} />
      </div>

      <div className="flxcrm-profile">
        <div className="flxcrm-profile__main">
          <PersonSections sections={c.sections} placement="MAIN_BEFORE" />
          <Card
            title={t('Timeline')}
            icon="clock"
            aside={
              <div className="flxcrm-seg">
                {shell.flxCrmMeta.timelineFilters.map((x) => (
                  <button key={x.key} type="button" className={'flxcrm-seg__btn' + (x.key === filter ? ' is-on' : '')} onClick={() => setFilter(x.key)}>
                    {x.label}
                  </button>
                ))}
              </div>
            }
          >
            {can && (
              <form className="flxcrm-composer" onSubmit={(e) => void addNote(e)}>
                <label className="screen-reader-text" htmlFor="flxcrm-note" style={{ position: 'absolute', left: -9999 }}>
                  {t('Note')}
                </label>
                <textarea id="flxcrm-note" rows={2} value={note} required placeholder={t('Add a note: talked on the phone, call again about the qurban…')} onChange={(e) => setNote(e.currentTarget.value)} />
                <div className="flxcrm-composer__bar">
                  <select value={noteType} aria-label={t('Type')} onChange={(e) => setNoteType(e.currentTarget.value as FlxContactNoteType)}>
                    {manual.map((m) => (
                      <option key={m.key} value={NOTE_TYPES[m.key] ?? 'NOTE'}>
                        {m.label}
                      </option>
                    ))}
                  </select>
                  <button className="button button-primary" disabled={addEvent.isPending}>
                    {t('Add')}
                  </button>
                </div>
              </form>
            )}
            {tl.loading && tl.events.length === 0 ? (
              <Loading />
            ) : tl.events.length === 0 ? (
              <p className="flxcrm-empty">{t('No events for this filter.')}</p>
            ) : (
              <ol className="flxcrm-timeline">
                {tl.events.map((e) => {
                  const d = new Date(e.at.iso);
                  const day = d.toLocaleDateString(crm.lang, { day: 'numeric', month: 'long', year: 'numeric', timeZone: tz });
                  const head = day !== lastDay;
                  lastDay = day;
                  return (
                    <li key={e.databaseId} style={{ display: 'contents' }}>
                      {head && <div className="flxcrm-timeline__day">{day}</div>}
                      <div className={'flxcrm-ev is-' + e.type}>
                        <span className="flxcrm-ev__icon">
                          <Ic n={e.icon} />
                        </span>
                        <div className="flxcrm-ev__body">
                          <div className="flxcrm-ev__top">
                            <span className="flxcrm-ev__type">{e.typeLabel}</span>
                            {e.amount && e.amount.amount > 0 && (
                              <>
                                <Money value={e.amount} strike={!e.paid} />
                                {!e.paid && e.status && <span className="flxcrm-badge is-warn">{statusLabel(t, e.status)}</span>}
                              </>
                            )}
                            <time dateTime={e.at.iso}>{d.toLocaleTimeString(crm.lang, { hour: '2-digit', minute: '2-digit', timeZone: tz })}</time>
                          </div>
                          {e.title && <p className="flxcrm-ev__title">{e.url ? <a href={e.url}>{e.title}</a> : e.title}</p>}
                          {e.body && e.body.trim() !== e.title && (
                            <div className="flxcrm-ev__text">
                              {e.body.split(/\n+/).map((l, i) => (
                                <p key={i}>{l}</p>
                              ))}
                            </div>
                          )}
                          {e.answers.length > 0 && (
                            <details className="flxcrm-ev__more">
                              <summary>{t('Answers')}</summary>
                              <dl>
                                {e.answers.map((a, i) => (
                                  <div key={i} style={{ display: 'contents' }}>
                                    <dt>{a.question}</dt>
                                    <dd>{a.answer}</dd>
                                  </div>
                                ))}
                              </dl>
                            </details>
                          )}
                          {(e.userName || e.deletable) && (
                            <div className="flxcrm-ev__foot">
                              {e.userName && <span className="flxcrm-muted">{e.userName}</span>}
                              {e.deletable && can && (
                                <button type="button" className="button-link is-bad" onClick={() => setConfirm(e.databaseId)}>
                                  {t('Delete')}
                                </button>
                              )}
                            </div>
                          )}
                        </div>
                      </div>
                    </li>
                  );
                })}
                {tl.more && (
                  <li className="flxcrm-timeline__more" style={{ display: 'flex' }}>
                    <button type="button" className="button" disabled={tl.loading} onClick={tl.loadMore}>
                      {tl.loading ? t('Loading…') : t('Load more')}
                    </button>
                  </li>
                )}
              </ol>
            )}
          </Card>
          <PersonSections sections={c.sections} placement="MAIN" />
        </div>

        <aside className="flxcrm-profile__side">
          <Card
            title={t('Details')}
            icon="user"
            aside={
              can && !editing ? (
                <button
                  type="button"
                  className="button button-small"
                  onClick={() => {
                    setF(formOf(c));
                    setEditing(true);
                  }}
                >
                  {t('Edit')}
                </button>
              ) : undefined
            }
          >
            {editing ? (
              <form onSubmit={(e) => void save(e)}>
                <Fields f={f} set={setF} />
                <p className="flxcrm-actions">
                  <button className="button button-primary" disabled={update.isPending}>
                    {t('Save')}
                  </button>
                  <button type="button" className="button" onClick={() => setEditing(false)}>
                    {t('Cancel')}
                  </button>
                </p>
              </form>
            ) : (
              <dl className="flxcrm-dl">
                <div>
                  <dt>{t('Full name')}</dt>
                  <dd>{c.rawName || none}</dd>
                </div>
                <div>
                  <dt>{t('E-mail')}</dt>
                  <dd>{c.email ? <a href={'mailto:' + c.email}>{c.email}</a> : none}</dd>
                </div>
                <div>
                  <dt>{t('Phone')}</dt>
                  <dd>{c.phone ? <a href={'tel:' + c.phone.e164}>{c.phone.display}</a> : none}</dd>
                </div>
                <div className="is-half">
                  <dt>{t('Organisation')}</dt>
                  <dd>{c.company || none}</dd>
                </div>
                <div className="is-half">
                  <dt>{t('City')}</dt>
                  <dd>{c.city || none}</dd>
                </div>
                <div>
                  <dt>{t('Roles')}</dt>
                  <dd>{c.roles?.length ? <span className="flxcrm-chips"><RolePills keys={c.roles} /></span> : none}</dd>
                </div>
                <div>
                  <dt>{t('Contact consents')}</dt>
                  <dd>
                    <span className="flxcrm-consents">
                      {shell.flxCrmMeta.consents.map((x) => {
                        const on = c.consent?.[x.key as keyof Form['consent']] ?? false;
                        return (
                          <span key={x.key} className={'flxcrm-consent' + (on ? ' is-on' : '')}>
                            <Ic n={on ? 'check' : 'x'} size={13} />
                            <span>{x.label}</span>
                            <span style={{ position: 'absolute', left: -9999 }}>{on ? t('allowed') : t('not allowed')}</span>
                          </span>
                        );
                      })}
                      {c.consentAt && <small className="flxcrm-muted">{t('Last changed: %s', c.consentAt.label)}</small>}
                    </span>
                  </dd>
                </div>
                <div>
                  <dt>{t('Owner')}</dt>
                  <dd>{c.ownerName || none}</dd>
                </div>
                <div>
                  <dt>{t('Short note')}</dt>
                  <dd>{c.note?.trim() ? c.note : none}</dd>
                </div>
              </dl>
            )}
          </Card>

          <Card title={t('Tags')} icon="tag">
            <div className="flxcrm-tags flxcrm-tags--edit">
              {(c.tags ?? []).length === 0 && <span className="flxcrm-muted">{t('No tags.')}</span>}
              {(c.tags ?? []).map((x) => (
                <span key={x.databaseId} className="flxcrm-tag" style={{ ['--tc' as string]: x.color ?? '#64748b' }}>
                  <a href={href('contacts?tag=' + x.databaseId)}>{x.name}</a>
                  {can && (
                    <button type="button" aria-label={t('Remove the %s tag', x.name)} onClick={() => void removeTag(x.databaseId)}>
                      ×
                    </button>
                  )}
                </span>
              ))}
            </div>
            {can && (
              <form className="flxcrm-addtag" onSubmit={(e) => void addTags(e)}>
                <input type="text" list="flxcrm-tag-list" value={tag} placeholder={t('Add tags (comma for several)')} aria-label={t('Add tag')} onChange={(e) => setTag(e.currentTarget.value)} />
                <datalist id="flxcrm-tag-list">
                  {shell.flxTags.map((x) => (
                    <option key={x.databaseId} value={x.name} />
                  ))}
                </datalist>
                <button className="button" disabled={addTag.isPending}>
                  {t('Add')}
                </button>
              </form>
            )}
          </Card>

          <Card title={t('Sources')} icon="link">
            <ul className="flxcrm-list flxcrm-list--plain">
              {(c.sources ?? []).length === 0 && !c.userId && <li className="flxcrm-muted">{t('Added by hand.')}</li>}
              {(c.sources ?? []).map((s, i) => (
                <li key={i}>
                  {s.url ? (
                    <a href={s.url}>
                      <span className="flxcrm-list__main">
                        <strong>{s.label}</strong>
                        {s.date && <small>{s.date}</small>}
                      </span>
                      <Ic n="arrow" size={14} />
                    </a>
                  ) : (
                    <span>
                      <span className="flxcrm-list__main">
                        <strong>{s.label}</strong>
                        {s.date && <small>{s.date}</small>}
                      </span>
                    </span>
                  )}
                </li>
              ))}
            </ul>
            {(c.utmSource || c.utmCampaign) && (
              <p className="flxcrm-muted">
                {t('First campaign')}: {[c.utmSource, c.utmCampaign].filter(Boolean).join(' / ')}
              </p>
            )}
          </Card>
          <PersonSections sections={c.sections} placement="SIDE" />
        </aside>
      </div>

      {confirm !== '' && (
        <Modal title={confirm === 'contact' ? t('Delete contact') : t('Delete entry')} onClose={() => setConfirm('')}>
          <p>{confirm === 'contact' ? t('Donations and orders stay in their own plugins; only the CRM card is deleted.') : t('This timeline entry will be deleted.')}</p>
          <div className="flxcrm-modal-actions">
            <button type="button" className="button" onClick={() => setConfirm('')}>
              {t('Cancel')}
            </button>
            <button type="button" className="button button-danger" onClick={() => void confirmed()}>
              {t('Delete')}
            </button>
          </div>
        </Modal>
      )}
    </>
  );
}

function statusLabel(t: (s: string) => string, st: string): string {
  const m: Record<string, string> = {
    pending: t('Pending'),
    'on-hold': t('On hold'),
    failed: t('Failed'),
    canceled: t('Cancelled'),
    cancelled: t('Cancelled'),
    refunded: t('Refunded'),
    partially_refunded: t('Partly refunded'),
  };
  return m[st] ?? st;
}

