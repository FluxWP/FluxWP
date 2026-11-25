import { useEffect, useMemo, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import type { FlxMailTemplateFragment } from '@fluxwp/graphql/types';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxMailPreview, FlxMailTemplate, FlxMailTemplateReset, FlxMailTemplateUpdate, FlxMailTemplates, FlxMailTestSend } from '@fluxwp/graphql/operations';
import { Loading, Modal, WpEditor } from '@fluxwp/ui';
import { useHost } from '../host';
import { errorText, payloadError, useMail } from './common';
import { Preview, type Device } from './Preview';

type Row = FlxMailTemplateFragment;

export function TemplatesPage({ editKey, onEdit }: { editKey: string | null; onEdit: (key: string | null) => void }) {
  const { t, go } = useMail();
  const [search, setSearch] = useState('');
  const [q, setQ] = useState('');
  const list = useFlxQuery(FlxMailTemplates, { search: q || undefined, first: 50 });
  useEffect(() => {
    const timer = window.setTimeout(() => setQ(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  if (editKey) return <Editor templateKey={editKey} onBack={() => onEdit(null)} />;
  if (list.isLoading) return <Loading />;
  if (list.isError) return <p className="flxmail-bad">{errorText(list.error, t('Something went wrong.'))}</p>;
  const stats = list.data?.flxMailLogStats;
  const nodes = list.data?.flxMailTemplates.nodes ?? [];
  const groups: { name: string; rows: Row[] }[] = [];
  for (const row of nodes) {
    const name = row.group || t('Other');
    const g = groups.find((x) => x.name === name);
    if (g) g.rows.push(row);
    else groups.push({ name, rows: [row] });
  }

  const local = (stats?.senderEmail ?? '').split('@')[0] ?? '';
  return (
    <div>
      {stats ? (
        <div className="flxi-stats">
          <a className="flxi-stat" href="#" onClick={(e) => (e.preventDefault(), go('logs', { status: 'sent' }))}>
            <span className="flxi-stat__n">{stats.sent.toLocaleString()}</span>
            <span className="flxi-stat__l">{t('sent (7 days)')}</span>
          </a>
          <a className={'flxi-stat' + (stats.failed ? ' flxi-stat--bad' : '')} href="#" onClick={(e) => (e.preventDefault(), go('logs', { status: 'failed' }))}>
            <span className="flxi-stat__n">{stats.failed.toLocaleString()}</span>
            <span className="flxi-stat__l">{t('failed')}</span>
          </a>
          <a className="flxi-stat" href="#" onClick={(e) => (e.preventDefault(), go('logs', { status: 'blocked' }))}>
            <span className="flxi-stat__n">{stats.blocked.toLocaleString()}</span>
            <span className="flxi-stat__l">{t('not sent because disabled')}</span>
          </a>
          <a className="flxi-stat flxi-stat--wide" href="#" title={`${stats.senderName} <${stats.senderEmail}> · ${stats.method}`} onClick={(e) => (e.preventDefault(), go('settings'))}>
            <span className="flxi-stat__l">{t('Sender')}</span>
            <span className="flxi-stat__v">{stats.senderName}</span>
            <span className="flxi-stat__e">{stats.senderEmail}</span>
            <span className="flxi-stat__l flxi-stat__m">{stats.method}</span>
          </a>
        </div>
      ) : null}
      <div className="flxi-toolbar">
        <input type="search" className="flxi-search" value={search} placeholder={t('Search emails…')} aria-label={t('Search emails')} onChange={(e) => setSearch(e.target.value)} />
      </div>
      {groups.map((g) => (
        <section key={g.name} className="flxi-card flxi-group">
          <h2 className="flxi-card__title">
            {g.name} <span className="flxi-muted">{g.rows.length.toLocaleString()}</span>
          </h2>
          <ul className="flxi-list">
            {g.rows.map((row) => (
              <TemplateRow key={row.key} row={row} onEdit={onEdit} local={local} />
            ))}
          </ul>
        </section>
      ))}
      {list.data?.flxMailTemplates.pageInfo.hasNextPage ? <p className="flxi-muted">{t('Showing the first 50. Narrow the search to see the rest.')}</p> : null}
      <p className="flxi-muted flxi-foot-note">{t('When a plugin not in the list sends an email for the first time, it is added here automatically.')}</p>
    </div>
  );
}

function TemplateRow({ row, onEdit, local }: { row: Row; onEdit: (key: string | null) => void; local: string }) {
  const { t, toast } = useMail();
  const host = useHost();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const toggle = useFlxMutation(FlxMailTemplateUpdate);
  const woo = row.kind === 'WOOCOMMERCE';
  const noreply = row.sender === 'NOREPLY';
  const open = () => (woo && row.wooUrl ? host.openUrl(row.wooUrl) : onEdit(row.key));
  return (
    <li className={'flxi-row' + (row.enabled ? '' : ' is-off') + (toggle.isPending ? ' is-saving' : '')}>
      <div className="flxi-row__toggle">
        {row.canDisable ? (
          <label className="flxi-switch">
            <input
              type="checkbox"
              checked={row.enabled}
              aria-label={t('%s enabled').replace('%s', row.label)}
              disabled={toggle.isPending}
              onChange={(e) =>
                void toggle
                  .mutateAsync({ input: { key: row.key, enabled: e.target.checked, toggleOnly: true } })
                  .then((r) => {
                    const err = payloadError(r.flxMailTemplateUpdate?.errors);
                    if (err) toast(err, 'error');
                    else void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxMailTemplates) });
                  })
                  .catch((err) => toast(errorText(err, t('Something went wrong.')), 'error'))
              }
            />
            <span className="flxi-switch__track" aria-hidden="true" />
          </label>
        ) : (
          <span className="flxi-lock dashicons dashicons-lock" title={t('Cannot be disabled')} />
        )}
      </div>
      <div className="flxi-row__main">
        <a className="flxi-row__title" href="#" onClick={(e) => (e.preventDefault(), open())}>
          {row.label}
        </a>
        {row.description ? <div className="flxi-row__desc">{row.description}</div> : null}
      </div>
      <div className="flxi-row__meta">
        {row.audience ? <span className="flxi-badge">{row.audience}</span> : null}
        <span className={'flxi-badge flxi-badge--' + (noreply ? 'muted' : 'green')}>{noreply ? 'no-reply' : local + '@'}</span>
        {row.enabled ? null : <span className="flxi-badge flxi-badge--off">{t('Disabled')}</span>}
      </div>
      <div className="flxi-row__actions">
        {woo && row.wooUrl ? (
          <button type="button" className="button" onClick={() => host.openUrl(row.wooUrl ?? '')}>
            {t('Edit in WooCommerce')}
          </button>
        ) : (
          <button type="button" className="button" onClick={() => onEdit(row.key)}>
            {row.editable ? t('Edit') : t('Settings')}
          </button>
        )}
      </div>
    </li>
  );
}

function Editor({ templateKey, onBack }: { templateKey: string; onBack: () => void }) {
  const { t, toast } = useMail();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxMailTemplate, { key: templateKey });
  const save = useFlxMutation(FlxMailTemplateUpdate);
  const reset = useFlxMutation(FlxMailTemplateReset);
  const test = useFlxMutation(FlxMailTestSend);
  const preview = useFlxMutation(FlxMailPreview);
  const row = q.data?.flxMailTemplate ?? null;
  const [subject, setSubject] = useState('');
  const [heading, setHeading] = useState('');
  const [body, setBody] = useState('');
  const [buttonText, setButtonText] = useState('');
  const [buttonUrl, setButtonUrl] = useState('');
  const [sender, setSender] = useState<'INFO' | 'NOREPLY'>('INFO');
  const [enabled, setEnabled] = useState(true);
  const [wrap, setWrap] = useState(false);
  const [device, setDevice] = useState<Device>('desktop');
  const [html, setHtml] = useState('');
  const [subjectLine, setSubjectLine] = useState('');
  const [ready, setReady] = useState('');
  const [confirmReset, setConfirmReset] = useState(false);

  const applyRow = (tpl: Row) => {
    setSubject(tpl.subject ?? '');
    setHeading(tpl.heading ?? '');
    setBody(tpl.body ?? '');
    setButtonText(tpl.buttonText ?? '');
    setButtonUrl(tpl.buttonUrl ?? '');
    setSender(tpl.sender);
    setEnabled(tpl.enabled);
    setWrap(!!tpl.wrap);
    setReady(tpl.key);
  };

  useEffect(() => {
    if (!row || ready === row.key) return;
    applyRow(row);
  }, [row, ready]);

  const sig = useMemo(() => [subject, heading, body, buttonText, buttonUrl, sender, wrap].join('\n'), [subject, heading, body, buttonText, buttonUrl, sender, wrap]);
  useEffect(() => {
    if (!row?.editable || ready !== row.key) return;
    const timer = window.setTimeout(
      () => {
        void preview
          .mutateAsync({ input: { key: row.key, subject, heading, body, buttonText, buttonUrl, sender } })
          .then((r) => {
            const p = r.flxMailPreview?.preview;
            const src = p?.html ?? '';
            setHtml(src);
            setSubjectLine(p?.subject ?? '');
          })
          .catch(() => undefined);
      },
      html ? 450 : 0,
    );
    return () => window.clearTimeout(timer);
  }, [sig, ready]); // eslint-disable-line react-hooks/exhaustive-deps

  if (q.isLoading) return <Loading />;
  if (!row)
    return (
      <p>
        {t('No emails found')}{' '}
        <button type="button" className="flxmail-link" onClick={onBack}>
          {t('All emails')}
        </button>
      </p>
    );

  const insert = (token: string) => {
    const ed = (window as unknown as { tinymce?: { get: (id: string) => { insertContent: (s: string) => void } | null } }).tinymce?.get('flxi_body');
    if (ed) ed.insertContent(token);
    else setBody((b) => b + token);
  };
  const persist = async () => {
    const r = await save.mutateAsync({
      input: { key: row.key, enabled, sender, wrap, subject, heading, body, buttonText, buttonUrl },
    });
    const err = payloadError(r.flxMailTemplateUpdate?.errors);
    if (err) toast(err, 'error');
    else {
      toast(t('Saved'));
      const tpl = r.flxMailTemplateUpdate?.template;
      if (tpl) applyRow(tpl);
      else setReady('');
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxMailTemplate, { key: row.key }) });
      void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxMailTemplates) });
    }
  };

  return (
    <>
      <div className={'flxi-editor' + (row.editable ? '' : ' flxi-editor--simple')}>
        <div className="flxi-editor__form">
          <div className="flxi-card">
            {row.description ? <p className="flxi-muted">{row.description}</p> : null}
            {row.lastSubject ? <p className="flxi-muted">{t('Last subject: {s}', { s: row.lastSubject })}</p> : null}
            <div className="flxi-inline">
              {row.canDisable ? (
                <label className="flxi-switch">
                  <input type="checkbox" checked={enabled} onChange={(e) => setEnabled(e.target.checked)} />
                  <span className="flxi-switch__track" aria-hidden="true" />
                  <span className="flxi-switch__label">{t('Send this email')}</span>
                </label>
              ) : (
                <span className="flxi-badge">
                  <span className="dashicons dashicons-lock" /> {t('Always sent')}
                </span>
              )}
            </div>
            <label className="flxi-field">
              <span>{t('Send from')}</span>
              <select value={sender} onChange={(e) => setSender(e.target.value === 'NOREPLY' ? 'NOREPLY' : 'INFO')}>
                <option value="INFO">{row.senderInfo}</option>
                <option value="NOREPLY">
                  {row.senderNoreply} · {t('no reply expected')}
                </option>
              </select>
            </label>
            {row.editable ? (
              <>
                <label className="flxi-field">
                  <span>{t('Subject')}</span>
                  <input type="text" value={subject} onChange={(e) => setSubject(e.target.value)} />
                </label>
                <label className="flxi-field">
                  <span>{t('Heading')}</span>
                  <input type="text" value={heading} onChange={(e) => setHeading(e.target.value)} />
                  <small>{t('The large heading inside the email.')}</small>
                </label>
                <div className="flxi-field">
                  <span>{t('Content')}</span>
                  <WpEditor id="flxi_body" value={body} onChange={setBody} label={t('Content')} />
                </div>
                <div className="flxi-grid2">
                  <label className="flxi-field">
                    <span>{t('Button text')}</span>
                    <input type="text" value={buttonText} onChange={(e) => setButtonText(e.target.value)} />
                    <small>{t('If empty, the button is not shown.')}</small>
                  </label>
                  <label className="flxi-field">
                    <span>{t('Button link')}</span>
                    <input type="text" value={buttonUrl} onChange={(e) => setButtonUrl(e.target.value)} />
                  </label>
                </div>
                {row.vars?.length ? (
                  <div className="flxi-vars">
                    <span>{t('Variables (click to insert at the cursor)')}</span>
                    <div className="flxi-chips">
                      {row.vars.map((v) => (
                        <button key={v.name} type="button" className="flxi-chip" title={v.description} onClick={() => insert('{' + v.name + '}')}>
                          {'{' + v.name + '}'}
                        </button>
                      ))}
                    </div>
                  </div>
                ) : null}
              </>
            ) : (
              <label className="flxi-switch">
                <input type="checkbox" checked={wrap} onChange={(e) => setWrap(e.target.checked)} />
                <span className="flxi-switch__track" aria-hidden="true" />
                <span className="flxi-switch__label">{t('Wrap in branded template (logo, organization details). HTML emails with their own design are left untouched.')}</span>
              </label>
            )}
            <div className="flxi-actions">
              <button type="button" className="button button-primary button-large" disabled={save.isPending} onClick={() => void persist().catch((e) => toast(errorText(e, t('Something went wrong.')), 'error'))}>
                {t('Save')}
              </button>
              {row.editable ? (
                <>
                  <button
                    type="button"
                    className="button button-large"
                    disabled={test.isPending}
                    onClick={() =>
                      void test
                        .mutateAsync({ input: { key: row.key } })
                        .then((r) => toast(payloadError(r.flxMailTestSend?.errors) || (r.flxMailTestSend?.ok ? t('Sent') : r.flxMailTestSend?.error || t('Could not send.')), r.flxMailTestSend?.ok ? 'ok' : 'error'))
                        .catch((e) => toast(errorText(e, t('Something went wrong.')), 'error'))
                    }
                  >
                    {t('Send me a test')}
                  </button>
                  <button type="button" className="button-link flxi-link-danger" onClick={() => setConfirmReset(true)}>
                    {t('Reset to default')}
                  </button>
                </>
              ) : null}
            </div>
          </div>
        </div>
        {row.editable ? (
          <div className="flxi-editor__preview">
            <Preview
              head={
                <div className="flxi-preview__subject">
                  <span className="flxi-muted">{t('Subject:')}</span> <strong>{subjectLine}</strong>
                </div>
              }
              html={html}
              device={device}
              onDevice={setDevice}
              pdf={() => ({ key: row.key, subject, heading, body, buttonText, buttonUrl, sender })}
              note={t('Sample values are used in the preview.')}
            />
          </div>
        ) : null}
      </div>
      {confirmReset ? (
        <Modal title={t('Reset to default')} onClose={() => setConfirmReset(false)}>
          <p>{t('Reset content to default?')}</p>
          <div className="flxi-actions">
            <button type="button" className="button" onClick={() => setConfirmReset(false)}>
              {t('Cancel')}
            </button>
            <button
              type="button"
              className="button button-primary flxi-btn-danger"
              disabled={reset.isPending}
              onClick={() =>
                void reset
                  .mutateAsync({ input: { key: row.key } })
                  .then((r) => {
                    const err = payloadError(r.flxMailTemplateReset?.errors);
                    if (err) {
                      toast(err, 'error');
                      return;
                    }
                    const tpl = r.flxMailTemplateReset?.template;
                    if (tpl) applyRow(tpl);
                    else setReady('');
                    void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxMailTemplate, { key: row.key }) });
                    void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxMailTemplates) });
                    toast(t('Default content restored.'));
                    setConfirmReset(false);
                  })
                  .catch((e) => toast(errorText(e, t('Something went wrong.')), 'error'))
              }
            >
              {t('Reset to default')}
            </button>
          </div>
        </Modal>
      ) : null}
    </>
  );
}
