// Flux Ads › Ad conversions. Same screen in wp-admin (wp-embed) and the FluxWP app; every read and write goes
// through the Flux Ads GraphQL operations (plugins/flx-ads/graphql/operations). It is the only admin page
// (no classic page): report, campaigns, suggestions, Studio, season plan, tests, donor value, ad spend, audiences,
// send log, ad link builder and settings.
import { useCallback, useContext, useEffect, useMemo, useState, type ComponentType } from 'react';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider, flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxAdsContext, FlxAdsHeartbeat, FlxAdsSuggestions } from '@fluxwp/graphql/operations';
import { Loading, Modal, Screen } from '@fluxwp/ui';
import { useHost } from '../host';
import { translator } from './i18n';
import { EnvContext, Btn, type Env } from './common';
import { LinksTab, LogTab, ReportTab, SpendTab, ValueTab } from './ReportTabs';
import { CampaignsTab, ExperimentsTab, SuggestionsTab } from './MetaTabs';
import { AudiencesTab, LEADS, PlannerTab, StudioTab } from './StudioTabs';
import { SettingsTab } from './SettingsTab';
import './ads.css';

const TABS: Record<string, ComponentType> = {
  report: ReportTab,
  campaigns: CampaignsTab,
  suggestions: SuggestionsTab,
  studio: StudioTab,
  planner: PlannerTab,
  experiments: ExperimentsTab,
  value: ValueTab,
  spend: SpendTab,
  audiences: AudiencesTab,
  log: LogTab,
  links: LinksTab,
  settings: SettingsTab,
};

type Notice = { kind: 'success' | 'error' | 'info'; text: string };
type Confirm = { text: string; ok: string; danger?: boolean; run: () => void };

/** Error state of a query. */
export function Fail({ q }: { q: { error: Error | null; refetch?: () => unknown } }) {
  const { t } = useEnvSafe();
  return (
    <div className="flxads-test flxads-test--bad" role="alert">
      {q.error?.message || t('The action could not be completed.')}
      {q.refetch ? (
        <>
          {' '}
          <Btn small onClick={() => void q.refetch?.()}>
            {t('Try again')}
          </Btn>
        </>
      ) : null}
    </div>
  );
}

function useEnvSafe() {
  const e = useContext(EnvContext);
  return { t: e?.t ?? ((s: string) => s) };
}

export function AdsScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <Ads />
    </FlxGraphQLProvider>
  );
}

function Ads() {
  const host = useHost();
  const { i18n } = useTranslation();
  const { t, tn } = useMemo(() => translator(i18n.language), [i18n.language]);
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const ctxq = useFlxQuery(FlxAdsContext, {});
  const [tab, setTab] = useState(host.startPage ?? 'report');
  const [params, setParams] = useState<Record<string, string>>({});
  const [notice, setNotice] = useState<Notice | null>(null);
  const [confirm, setConfirm] = useState<Confirm | null>(null);

  const fail = useCallback((e: unknown) => setNotice({ kind: 'error', text: e instanceof Error && e.message ? e.message : t('The action could not be completed.') }), [t]);
  const notify = useCallback((kind: Notice['kind'], text: string) => {
    setNotice({ kind, text });
    window.scrollTo?.({ top: 0, behavior: 'smooth' });
  }, []);

  // The send queue is flushed and spend and suggestions are refreshed on load and then by a timer.
  const beat = useFlxMutation(FlxAdsHeartbeat, {
    onSuccess: (r) => {
      const open = r.flxAdsHeartbeat?.openSuggestions;
      const cur = qc.getQueryData<{ flxAdsContext: { openSuggestions: number } }>(flxQueryKey(scope, FlxAdsContext, {}));
      if (open !== undefined && open !== null && cur && cur.flxAdsContext.openSuggestions !== open) {
        void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxAdsContext) });
        void qc.invalidateQueries({ queryKey: flxQueryKey(scope, FlxAdsSuggestions) });
      }
    },
  });
  const { mutate: beatNow } = beat;
  const ready = !!ctxq.data;
  useEffect(() => {
    if (!ready) return;
    beatNow({ input: {} });
    const id = window.setInterval(() => beatNow({ input: {} }), 5 * 60 * 1000);
    return () => window.clearInterval(id);
  }, [ready, beatNow]);

  const go = useCallback((to: string, p?: Record<string, string>) => {
    setTab(to);
    setParams(p ?? {});
    setNotice(null);
    window.scrollTo?.({ top: 0 });
  }, []);

  if (ctxq.isLoading) return <Loading />;
  const ctx = ctxq.data?.flxAdsContext;
  if (!ctx) return <div className="flxads flxads-app"><Fail q={ctxq} /></div>;

  const names: Record<string, string> = {
    report: t('Report'),
    campaigns: t('Campaigns'),
    suggestions: t('Suggestions'),
    studio: t('Studio'),
    planner: t('Season plan'),
    experiments: t('Tests'),
    value: t('Donor value'),
    spend: t('Ad spend'),
    audiences: t('Audiences'),
    log: t('Send log'),
    links: t('Ad link'),
    settings: t('Settings'),
  };
  const current = ctx.tabs.includes(tab) ? tab : 'report';
  // wp-admin: each tab is its own submenu page (classic): no tab bar, the page lead sits under the title.
  const classic = host.mode === 'wp';
  const leads: Record<string, string> = {
    campaigns: t('Your Meta campaigns, ad sets and ads with spend and the donations they brought on this site. Pause, turn on or change the daily budget here; every change goes to Meta at once and is written to the change history.'),
    suggestions: t('Flux Ads checks your active Meta campaigns every day against what they brought on this site, and suggests what to change. Nothing changes until you press Apply; every change goes to the change history under Campaigns.'),
    studio: t(LEADS.studio),
    planner: t(LEADS.planner),
    experiments: t('Find out what really works. A/B: two ads in the same campaign, compared by donations per click on this site. Holdout: a fixed share of an audience does not see the ads; comparing the two groups shows how many donations the ads really added.'),
    value: t('What the people an ad brought in gave in total, not only their first payment. A campaign whose first gifts are cheap but never come back shows up here.'),
    audiences: t(LEADS.audiences),
  };
  const Body = TABS[current] ?? ReportTab;
  const env: Env = {
    t,
    tn,
    locale: i18n.language,
    ctx,
    notify,
    fail,
    ask: setConfirm,
    go,
    params,
  };

  return (
    <EnvContext.Provider value={env}>
      <Screen
        width="wide"
        icon={host.appIcon}
        className={'flxads flxads-app' + (classic && leads[current] ? ' flxads-hidelead' : '')}
        title={current === 'report' ? t('Ad conversions') : names[current]}
        subtitle={current === 'report' ? t('Which ad, campaign and ad text brought which donation. Paid donations and orders only; bank transfers count when approved.') : classic ? leads[current] : undefined}
        tabs={classic ? undefined : ctx.tabs.map((k) => ({ id: k, label: names[k] ?? k, badge: k === 'suggestions' ? ctx.openSuggestions : null }))}
        tab={current}
        onTab={go}
      >
        {notice && (
          <div className={'flxads-notice is-' + notice.kind} role={notice.kind === 'error' ? 'alert' : 'status'}>
            <span>{notice.text}</span>
            <button type="button" className="flxads-x" aria-label={t('Close')} onClick={() => setNotice(null)}>
              ×
            </button>
          </div>
        )}
        <Body key={current + JSON.stringify(params)} />
        {confirm && (
          <Modal title={confirm.ok} onClose={() => setConfirm(null)}>
            <p className="flxads-confirm">{confirm.text}</p>
            <div className="flxads-modal-actions">
              <Btn onClick={() => setConfirm(null)}>{t('Cancel')}</Btn>
              <Btn
                primary={!confirm.danger}
                danger={confirm.danger}
                onClick={() => {
                  confirm.run();
                  setConfirm(null);
                }}
              >
                {confirm.ok}
              </Btn>
            </div>
          </Modal>
        )}
      </Screen>
    </EnvContext.Provider>
  );
}
