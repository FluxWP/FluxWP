import { useEffect, useMemo, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import type { FlxMailTemplateFragment } from '@fluxwp/graphql/types';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxMailPreview, FlxMailTemplate, FlxMailTemplateReset, FlxMailTemplateUpdate, FlxMailTemplates, FlxMailTestSend } from '@fluxwp/graphql/operations';
import { Loading, Modal } from '@fluxwp/ui';
import { useHost } from '../host';
import { errorText, payloadError, useMail } from './common';

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

  return (
    <div>
      {stats ? (
        <div className="flxmail-stats">
          <button type="button" className="flxmail-stat" onClick={() => go('logs', { status: 'sent' })}>
            <span>{stats.sent}</span>
            {t('sent (7 days)')}
          </button>
          <button type="button" className={'flxmail-stat' + (stats.failed ? ' is-bad' : '')} onClick={() => go('logs', { status: 'failed' })}>
            <span>{stats.failed}</span>
            {t('failed')}
          </button>
          <button type="button" className="flxmail-stat" onClick={() => go('logs', { status: 'blocked' })}>
            <span>{stats.blocked}</span>
            {t('not sent because disabled')}
          </button>
          <div className="flxmail-stat is-wide">
            <span>{t('Sender')}</span>
            <strong>{stats.senderName}</strong>
            <small>{stats.senderEmail}</small>
            <small>{stats.method}</small>
          </div>
        </div>
      ) : null}
      <input className="flxmail-search" type="search" value={search} placeholder={t('Search emails')} aria-label={t('Search emails')} onChange={(e) => setSearch(e.target.value)} />
      {groups.map((g) => (
        <section key={g.name} className="flxmail-card">
          <h2>
            {g.name} <span className="flxmail-muted">{g.rows.length}</span>
          </h2>
          <ul className="flxmail-list">
            {g.rows.map((row) => (
              <TemplateRow key={row.key} row={row} onEdit={onEdit} />
            ))}
          </ul>
        </section>
      ))}
      {list.data?.flxMailTemplates.pageInfo.hasNextPage ? <p className="flxmail-muted">{t('Showing the first 50. Narrow the search to see the rest.')}</p> : null}
      <p className="flxmail-muted">{t('When a plugin sends an email for the first time, it is added here.')}</p>
    </div>
  );
}

function TemplateRow({ row, onEdit }: { row: Row; onEdit: (key: string | null) => void }) {
  const { t, toast } = useMail();
  const host = useHost();
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const toggle = useFlxMutation(FlxMailTemplateUpdate);
  const woo = row.kind === 'WOOCOMMERCE';
  return (
    <li className={'flxmail-row' + (row.enabled ? '' : ' is-off')}>
      {row.canDisable ? (
        <input
          type="checkbox"
          checked={row.enabled}
          aria-label={row.label}
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
      ) : (
        <span title={t('Always sent')}>●</span>
      )}
      <div>
        <button type="button" className="flxmail-link" onClick={() => (woo && row.wooUrl ? host.openUrl(row.wooUrl) : onEdit(row.key))}>
          {row.label}
        </button>
        {row.description ? <div className="flxmail-muted">{row.description}</div> : null}
      </div>
      <div className="flxmail-meta">
        {row.audience ? <span className="flxmail-badge">{row.audience}</span> : null}
        <span className="flxmail-badge">{row.sender === 'NOREPLY' ? 'no-reply' : 'info'}</span>
        {row.enabled ? null : <span className="flxmail-badge">{t('Disabled')}</span>}
      </div>
      {woo && row.wooUrl ? (
        <button type="button" className="flxmail-btn" onClick={() => host.openUrl(row.wooUrl ?? '')}>
          {t('Edit in WooCommerce')}
        </button>
      ) : (
        <button type="button" className="flxmail-btn" onClick={() => onEdit(row.key)}>
          {row.editable ? t('Edit') : t('Settings')}
        </button>
      )}
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
  const [device, setDevice] = useState<'desktop' | 'phone' | 'dark'>('desktop');
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
    const timer = window.setTimeout(() => {
      void preview
        .mutateAsync({ input: { key: row.key, subject, heading, body, buttonText, buttonUrl, sender } })
        .then((r) => {
          const p = r.flxMailPreview?.preview;
          const src = p?.html ?? '';
          setHtml(device === 'dark' ? src.replace(/@media\s*\(\s*prefers-color-scheme\s*:\s*dark\s*\)/gi, '@media all') : src);
          setSubjectLine(p?.subject ?? '');
        })
        .catch(() => undefined);
    }, html ? 450 : 0);
    return () => window.clearTimeout(timer);
  }, [sig, device, ready]); // eslint-disable-line react-hooks/exhaustive-deps

  if (q.isLoading) return <Loading />;
  if (!row) return <p>{t('No emails found')} <button type="button" className="flxmail-link" onClick={onBack}>{t('All emails')}</button></p>;

  const insert = (token: string) => setBody((b) => b + token);
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
      <p>
        <button type="button" className="flxmail-link" onClick={onBack}>
          ← {t('All emails')}
        </button>
        {row.group ? <span className="flxmail-muted"> · {row.group}</span> : null}
      </p>
      <div className={row.editable ? 'flxmail-editor' : ''}>
      <div className="flxmail-card">
        <h2>{row.label}</h2>
        {row.description ? <p className="flxmail-muted">{row.description}</p> : null}
        {row.lastSubject ? <p className="flxmail-muted">{t('Last subject: {s}', { s: row.lastSubject })}</p> : null}
        {row.canDisable ? (
          <label className="flxmail-check">
            <input type="checkbox" checked={enabled} onChange={(e) => setEnabled(e.target.checked)} />
            <span>{t('Send this email')}</span>
          </label>
        ) : (
          <p>{t('Always sent')}</p>
        )}
        <label className="flxmail-field">
          <span>{t('Send from')}</span>
          <select value={sender} onChange={(e) => setSender(e.target.value === 'NOREPLY' ? 'NOREPLY' : 'INFO')}>
            <option value="INFO">{row.senderInfo}</option>
            <option value="NOREPLY">{row.senderNoreply} · {t('no reply expected')}</option>
          </select>
        </label>
        {row.editable ? (
          <>
            <label className="flxmail-field">
              <span>{t('Subject')}</span>
              <input value={subject} onChange={(e) => setSubject(e.target.value)} />
            </label>
            <label className="flxmail-field">
              <span>{t('Heading')}</span>
              <input value={heading} onChange={(e) => setHeading(e.target.value)} />
            </label>
            <label className="flxmail-field">
              <span>{t('Content')}</span>
              <textarea rows={12} value={body} onChange={(e) => setBody(e.target.value)} />
            </label>
            <div className="flxmail-grid2">
              <label className="flxmail-field">
                <span>{t('Button text')}</span>
                <input value={buttonText} onChange={(e) => setButtonText(e.target.value)} />
              </label>
              <label className="flxmail-field">
                <span>{t('Button link')}</span>
                <input value={buttonUrl} onChange={(e) => setButtonUrl(e.target.value)} />
              </label>
            </div>
            {row.vars?.length ? (
              <div className="flxmail-chips">
                {row.vars.map((v) => (
                  <button key={v.name} type="button" className="flxmail-btn" title={v.description} onClick={() => insert('{' + v.name + '}')}>
                    {'{' + v.name + '}'}
                  </button>
                ))}
              </div>
            ) : null}
          </>
        ) : (
          <label className="flxmail-check">
            <input type="checkbox" checked={wrap} onChange={(e) => setWrap(e.target.checked)} />
            <span>{t('Wrap in branded template')}</span>
          </label>
        )}
        <div className="flxmail-actions">
          <button type="button" className="flxmail-btn is-primary" disabled={save.isPending} onClick={() => void persist().catch((e) => toast(errorText(e, t('Something went wrong.')), 'error'))}>
            {t('Save')}
          </button>
          {row.editable ? (
            <>
              <button
                type="button"
                className="flxmail-btn"
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
              <button type="button" className="flxmail-link" onClick={() => setConfirmReset(true)}>
                {t('Reset to default')}
              </button>
            </>
          ) : null}
        </div>
      </div>
      {row.editable ? (
        <div className="flxmail-preview">
          <div className="flxmail-preview-bar">
            <span>{subjectLine}</span>
            <div className="flxmail-seg">
              {(['desktop', 'phone', 'dark'] as const).map((d) => (
                <button key={d} type="button" className={device === d ? 'is-on' : ''} onClick={() => setDevice(d)}>
                  {t(d === 'desktop' ? 'Desktop' : d === 'phone' ? 'Phone' : 'Dark mode')}
                </button>
              ))}
            </div>
          </div>
          <iframe className={device === 'phone' ? 'is-phone' : ''} title={t('Email preview')} sandbox="allow-same-origin" srcDoc={html} />
          <p className="flxmail-muted">{t('Sample values are used in the preview.')}</p>
        </div>
      ) : null}
      </div>
      {confirmReset ? (
        <Modal title={t('Reset to default')} onClose={() => setConfirmReset(false)}>
          <p>{t('Reset content to default?')}</p>
          <div className="flxmail-actions">
            <button type="button" className="flxmail-btn" onClick={() => setConfirmReset(false)}>
              {t('Cancel')}
            </button>
            <button
              type="button"
              className="flxmail-btn is-danger"
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
