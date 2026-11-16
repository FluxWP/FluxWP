// CRM › Segments: saved rule lists, the rule editor with a live count.
import { useEffect, useState, type FormEvent } from 'react';
import { useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxCrmSegmentDelete, FlxCrmSegmentEditor, FlxCrmSegmentPreview, FlxCrmSegmentSave } from '@fluxwp/graphql/operations';
import type { FlxCrmSegmentPreviewQuery, FlxSegmentMatch } from '@fluxwp/graphql/types';
import { Loading, Modal } from '@fluxwp/ui';
import { Card, Empty, Head, Ic, errText, href, useCrm, type Route } from './ui';

interface Rule {
  field: string;
  op: string;
  value: string;
}

export function Segments({ route, go }: { route: Route; go: (to: string) => void }) {
  if (route.id) return <SegmentEditor key={route.id} id={route.id} go={go} />;
  return <SegmentList go={go} />;
}

function SegmentList({ go }: { go: (to: string) => void }) {
  const crm = useCrm();
  const { t, num, can, shell } = crm;
  const del = useFlxMutation(FlxCrmSegmentDelete);
  const [ask, setAsk] = useState<{ id: number; name: string } | null>(null);
  const list = shell.flxSegments;
  const remove = async () => {
    if (!ask) return;
    const id = ask.id;
    setAsk(null);
    try {
      const r = (await del.mutateAsync({ input: { id } })).flxSegmentDelete;
      if (!r || r.errors.length) throw new Error(errText(r?.errors));
      crm.tell('ok', t('Segment deleted.'));
      crm.reloadShell();
    } catch (e) {
      crm.fail(e);
    }
  };
  return (
    <>
      <Head
        title={t('Segments')}
        lead={t('Live lists defined by rules: a contact joins when they match and leaves when they no longer do. Use them as recipients for bulk sends in Flux Mail.')}
        actions={
          can && (
            <a className="button button-primary" href={href('segments/new')}>
              <Ic n="plus" /> {t('New segment')}
            </a>
          )
        }
      />
      {list.length === 0 ? (
        <Empty icon="chart" title={t('No segments yet.')} hint={t('Examples: "Gave a qurban donation last year but not this year", "Donors who allowed WhatsApp".')} />
      ) : (
        <div className="flxcrm-seglist">
          {list.map((s) => (
            <article key={s.databaseId} className="flxcrm-card flxcrm-segcard">
              <div className="flxcrm-segcard__head">
                <div>
                  <h2>
                    <a href={href('contacts?sid=' + s.databaseId)}>{s.name}</a>
                  </h2>
                  {s.description && <p className="flxcrm-muted">{s.description}</p>}
                </div>
                <strong className="flxcrm-segcard__count">
                  {num(s.count)}
                  <small>{t('contacts')}</small>
                </strong>
              </div>
              <p className="flxcrm-rule-sum">
                <Ic n="chart" size={14} />
                {s.summary}
              </p>
              <div className="flxcrm-segcard__actions">
                <a className="button button-small" href={href('contacts?sid=' + s.databaseId)}>
                  {t('View contacts')}
                </a>
                {can && (
                  <>
                    <button type="button" className="button button-small" onClick={() => go('segments/' + s.databaseId)}>
                      {t('Edit')}
                    </button>
                    <button type="button" className="button button-small" onClick={() => setAsk({ id: s.databaseId, name: s.name })}>
                      {t('Delete')}
                    </button>
                  </>
                )}
              </div>
            </article>
          ))}
        </div>
      )}
      {ask && (
        <Modal title={t('Delete segment')} onClose={() => setAsk(null)}>
          <p>{t('"%s" segment will be deleted. Contacts stay.', ask.name)}</p>
          <div className="flxcrm-modal-actions">
            <button type="button" className="button" onClick={() => setAsk(null)}>
              {t('Cancel')}
            </button>
            <button type="button" className="button button-danger" onClick={() => void remove()}>
              {t('Delete')}
            </button>
          </div>
        </Modal>
      )}
    </>
  );
}

function SegmentEditor({ id, go }: { id: string; go: (to: string) => void }) {
  const crm = useCrm();
  const { t, num, shell } = crm;
  const { client } = useGraphQL();
  const ed = useFlxQuery(FlxCrmSegmentEditor, {}, { staleTime: 60_000 });
  const save = useFlxMutation(FlxCrmSegmentSave);
  const isNew = id === 'new';
  const existing = isNew ? undefined : shell.flxSegments.find((s) => s.databaseId === Number(id));
  const [name, setName] = useState(existing?.name ?? '');
  const [description, setDescription] = useState(existing?.description ?? '');
  const [match, setMatch] = useState<FlxSegmentMatch>(existing?.match ?? 'ALL');
  const [rules, setRules] = useState<Rule[]>(existing ? existing.rules.map((r) => ({ ...r })) : [{ field: 'role', op: 'has', value: 'donor' }]);
  const [preview, setPreview] = useState<FlxCrmSegmentPreviewQuery['flxSegmentPreview'] | null>(null);

  const key = JSON.stringify([match, rules]);
  useEffect(() => {
    const h = setTimeout(() => {
      client
        .request(FlxCrmSegmentPreview, { match, rules: rules.map((r) => ({ field: r.field, op: r.op, value: r.value })) })
        .then((r) => setPreview(r.flxSegmentPreview))
        .catch(() => setPreview(null));
    }, 350);
    return () => clearTimeout(h);
  }, [key]); // eslint-disable-line react-hooks/exhaustive-deps

  if (ed.isLoading) return <Loading />;
  if (!ed.data) return <div className="flxcrm-notice is-bad">{ed.error?.message}</div>;
  if (!isNew && !existing) return <Empty title={t('Segment not found.')} />;
  const e = ed.data.flxSegmentEditor;
  const field = (k: string) => e.fields.find((f) => f.key === k);
  const opLabel = (k: string) => e.ops.find((o) => o.key === k)?.label ?? k;
  const options = (type: string) => e.options.find((o) => o.type === type)?.values ?? [];
  const patch = (i: number, p: Partial<Rule>) => setRules((rs) => rs.map((r, j) => (j === i ? { ...r, ...p } : r)));
  const onField = (i: number, k: string) => {
    const f = field(k);
    const type = f?.type ?? 'text';
    patch(i, { field: k, op: f?.ops[0] ?? '', value: options(type)[0]?.value ?? '' });
  };

  const submit = async (ev: FormEvent) => {
    ev.preventDefault();
    try {
      const r = (await save.mutateAsync({ input: { id: isNew ? null : Number(id), name, description, match, rules: rules.map((x) => ({ field: x.field, op: x.op, value: x.value })) } })).flxSegmentSave;
      if (!r?.segment) throw new Error(errText(r?.errors));
      crm.reloadShell();
      crm.tell('ok', t('Segment saved.'));
      go('segments');
    } catch (err) {
      crm.fail(err);
    }
  };

  return (
    <>
      <Head title={isNew ? t('New segment') : t('Edit segment')} lead={t('Add rules; the number of matching contacts updates on the right as you go.')} back={{ to: 'segments', label: t('Segments') }} />
      <form className="flxcrm-segedit" onSubmit={(ev) => void submit(ev)}>
        <div className="flxcrm-segedit__main">
          <div className="flxcrm-card">
            <div className="flxcrm-form">
              <label>
                <span>{t('Name')}</span>
                <input type="text" value={name} required placeholder={t('E.g. Donors drifting away')} onChange={(x) => setName(x.currentTarget.value)} />
              </label>
              <label>
                <span>{t('Description')}</span>
                <input type="text" value={description} onChange={(x) => setDescription(x.currentTarget.value)} />
              </label>
            </div>
          </div>
          <Card
            title={t('Rules')}
            icon="chart"
            aside={
              <label className="flxcrm-match">
                {t('Contact')}{' '}
                <select value={match} onChange={(x) => setMatch(x.currentTarget.value as FlxSegmentMatch)}>
                  <option value="ALL">{t('all rules')}</option>
                  <option value="ANY">{t('any rule')}</option>
                </select>
              </label>
            }
          >
            <ol className="flxcrm-rules">
              {rules.map((r, i) => {
                const f = field(r.field);
                const type = f?.type ?? 'text';
                const choices = options(type);
                return (
                  <li key={i}>
                    {i > 0 && <span className="flxcrm-rules__and">{match === 'ALL' ? t('and') : t('or')}</span>}
                    <select value={r.field} aria-label={t('Field')} onChange={(x) => onField(i, x.currentTarget.value)}>
                      {e.fields.map((x) => (
                        <option key={x.key} value={x.key}>
                          {x.label}
                        </option>
                      ))}
                    </select>
                    <select value={r.op} aria-label={t('Operator')} onChange={(x) => patch(i, { op: x.currentTarget.value })}>
                      {(f?.ops ?? []).map((o) => (
                        <option key={o} value={o}>
                          {opLabel(o)}
                        </option>
                      ))}
                    </select>
                    {type === 'bool' ? null : choices.length > 0 ? (
                      <select value={r.value} aria-label={t('Value')} onChange={(x) => patch(i, { value: x.currentTarget.value })}>
                        {choices.map((c) => (
                          <option key={c.value} value={c.value}>
                            {c.label}
                          </option>
                        ))}
                      </select>
                    ) : (
                      <input type={type === 'number' || type === 'days' ? 'number' : 'text'} min={type === 'number' || type === 'days' ? 0 : undefined} value={r.value} aria-label={t('Value')} onChange={(x) => patch(i, { value: x.currentTarget.value })} />
                    )}
                    <button type="button" className="button-link" aria-label={t('Remove rule')} onClick={() => setRules((rs) => rs.filter((_, j) => j !== i))}>
                      ×
                    </button>
                  </li>
                );
              })}
            </ol>
            <button type="button" className="button" onClick={() => setRules((rs) => [...rs, { field: 'role', op: 'has', value: options('role')[0]?.value ?? '' }])}>
              <Ic n="plus" size={14} /> {t('Add rule')}
            </button>
          </Card>
          <p className="flxcrm-actions">
            <button className="button button-primary" disabled={save.isPending || !name.trim() || rules.length === 0}>
              {t('Save segment')}
            </button>
            <a className="button" href={href('segments')}>
              {t('Cancel')}
            </a>
          </p>
        </div>
        <aside className="flxcrm-card flxcrm-segedit__side" aria-live="polite">
          <span className="flxcrm-kpi__label">{t('Matching contacts')}</span>
          <strong className="flxcrm-preview__count">{preview ? num(preview.count) : '…'}</strong>
          <p className="flxcrm-muted">{preview?.summary}</p>
          {preview && preview.sample.length > 0 && (
            <ul className="flxcrm-preview__list">
              {preview.sample.map((s) => (
                <li key={s.databaseId}>
                  <a href={href('contact/' + s.databaseId)}>{s.name}</a>
                </li>
              ))}
            </ul>
          )}
        </aside>
      </form>
    </>
  );
}
