// Flux Forms panel: forms with their responses, and the settings. Same screen in wp-admin (wp-embed) and
// the FluxWP app; every read and write goes through the Flux Forms GraphQL operations
// (plugins/flx-forms/graphql/operations). The form builder (Create) is not part of it: it stays the
// plugin's own page and is linked from here.
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, useFlxQuery } from '@fluxwp/graphql/react';
import { FlxFormResponseByTrack } from '@fluxwp/graphql/operations';
import { Icon } from '@fluxwp/ui';
import { useHost } from '../host';
import { translator } from './i18n';
import { Responses } from './Responses';
import { Settings } from './Settings';
import { FormsList } from './FormsList';
import { Help } from './Help';
import { NoticeBar, type Notice } from './ui';
import './forms.css';

type Tab = 'forms' | 'settings' | 'help';

/** The classic panel's own URL parameters, so a bookmark or a dashboard link opens the same place. */
function fromUrl(): { tab: Tab; form: number; msg: number; settings: string } {
  const p = new URLSearchParams(window.location.search);
  const num = (v: string | null) => {
    const n = Number(v);
    return Number.isInteger(n) && n > 0 ? n : 0;
  };
  const state = p.get('state');
  const form = num(p.get('flxf_form')) || (state === 'show-messages' ? num(p.get('id')) : 0);
  const msg = num(p.get('flxf_msg'));
  if (state === 'setting') return { tab: 'settings', form: 0, msg: 0, settings: p.get('tab') ?? 'general' };
  if (state === 'help') return { tab: 'help', form: 0, msg: 0, settings: 'general' };
  return { tab: 'forms', form, msg, settings: 'general' };
}

export function FormsScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <Panel />
    </FlxGraphQLProvider>
  );
}

function Panel() {
  const host = useHost();
  const { i18n } = useTranslation();
  const { t } = useMemo(() => translator(i18n.language), [i18n.language]);
  const start = useMemo(fromUrl, []);
  const [tab, setTab] = useState<Tab>(start.tab);
  const [formId, setFormId] = useState<number>(start.form);
  const [msgId, setMsgId] = useState<number>(start.msg);
  const [settingsTab, setSettingsTab] = useState(start.settings);
  const [notice, setNotice] = useState<Notice | null>(null);
  const [code, setCode] = useState('');
  const [lookup, setLookup] = useState('');

  const adminUrl = host.adminUrl ?? '';
  const createUrl = adminUrl ? adminUrl + 'admin.php?page=Emsfb_create' : '';

  // Keep the classic panel's address in the bar (wp-admin only; the app has its own router).
  useEffect(() => {
    if (host.mode !== 'wp') return;
    const p = new URLSearchParams();
    p.set('page', 'Emsfb');
    if (tab === 'settings') {
      p.set('state', 'setting');
      p.set('tab', settingsTab);
    } else if (tab === 'help') {
      p.set('state', 'help');
    } else if (formId) {
      p.set('state', 'show-messages');
      p.set('id', String(formId));
    }
    try {
      window.history.replaceState(window.history.state, '', window.location.pathname + '?' + p.toString());
    } catch {
      /* the address cannot be changed here; the screen still works */
    }
  }, [host.mode, tab, formId, settingsTab]);

  const track = useFlxQuery(FlxFormResponseByTrack, { code: lookup }, { enabled: lookup !== '', retry: false });
  useEffect(() => {
    if (!lookup) return;
    if (track.data?.flxFormResponseByTrack) {
      const r = track.data.flxFormResponseByTrack;
      setTab('forms');
      setFormId(Number(r.formId));
      setMsgId(Number(r.id));
      setLookup('');
    } else if (track.isError || (track.isSuccess && !track.data?.flxFormResponseByTrack)) {
      setNotice({ kind: 'error', text: t('No response has this tracking code.') });
      setLookup('');
    }
  }, [lookup, track.data, track.isError, track.isSuccess, t]);

  const find = useCallback(() => {
    const c = code.trim();
    if (c) setLookup(c);
  }, [code]);

  const open = (next: Tab) => {
    setTab(next);
    setNotice(null);
    if (next !== 'forms') setMsgId(0);
  };

  return (
    <div className="flxf-app">
      <div className="flxf-head">
        <div>
          <h1>{t('Forms')}</h1>
          <p className="flxf-lead">{t('Responses of your forms, and the settings of the form builder.')}</p>
        </div>
        <div className="flxf-head__side">
          <form
            className="flxf-find"
            role="search"
            onSubmit={(e) => {
              e.preventDefault();
              find();
            }}
          >
            <input type="search" value={code} placeholder={t('Tracking code')} aria-label={t('Find a response by its tracking code')} onChange={(e) => setCode(e.currentTarget.value)} />
            <button type="submit" className="flxf-btn" disabled={!code.trim() || lookup !== ''}>
              <Icon name="search" size={14} />
              {t('Find')}
            </button>
          </form>
          {host.classicUrl && (
            <a className="flxf-classic" href={host.classicUrl}>
              {t('Classic view')}
            </a>
          )}
        </div>
      </div>

      <nav className="flxf-tabs" aria-label={t('Forms')}>
        <button type="button" className={tab === 'forms' ? 'is-on' : ''} aria-current={tab === 'forms' ? 'page' : undefined} onClick={() => open('forms')}>
          <Icon name="forms" size={15} />
          {t('Forms')}
        </button>
        <button type="button" className={tab === 'settings' ? 'is-on' : ''} aria-current={tab === 'settings' ? 'page' : undefined} onClick={() => open('settings')}>
          <Icon name="settings" size={15} />
          {t('Settings')}
        </button>
        {createUrl && (
          <a className="flxf-tabs__link" href={createUrl}>
            <Icon name="plus-alt" size={15} />
            {t('Create')}
          </a>
        )}
        <button type="button" className={tab === 'help' ? 'is-on' : ''} aria-current={tab === 'help' ? 'page' : undefined} onClick={() => open('help')}>
          <Icon name="info" size={15} />
          {t('Help')}
        </button>
      </nav>

      <NoticeBar notice={notice} label={t('Close')} onClose={() => setNotice(null)} />

      {tab === 'forms' &&
        (formId ? (
          <Responses t={t} lang={i18n.language} formId={formId} openId={msgId} onOpen={setMsgId} onBack={() => {
              setFormId(0);
              setMsgId(0);
            }} />
        ) : (
          <FormsList t={t} lang={i18n.language} onOpen={(id) => {
              setFormId(id);
              setMsgId(0);
            }} createUrl={createUrl} classicUrl={host.classicUrl} />
        ))}
      {tab === 'settings' && <Settings t={t} tab={settingsTab} onTab={setSettingsTab} classicUrl={host.classicUrl} onNotice={setNotice} />}
      {tab === 'help' && <Help t={t} />}
    </div>
  );
}
