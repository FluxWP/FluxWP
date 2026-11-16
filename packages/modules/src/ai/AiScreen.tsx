// Flux AI & MCP screens. One module, one screen per wp-admin page (host.startPage): the same code runs in
// wp-admin (wp-embed) and the FluxWP app; every read and write goes through the Flux AI GraphQL operations
// (plugins/flx-ai/graphql/operations).
import { useMemo, type ComponentType } from 'react';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider } from '@fluxwp/graphql/react';
import { useHost } from '../host';
import { Billing } from './Billing';
import { useAi } from './common';
import { Logs } from './Logs';
import { Overview } from './Overview';
import { Systems } from './Systems';
import { Tools } from './Tools';
import './ai.css';

const VIEWS: Record<string, { title: string; sub: string; View: ComponentType }> = {
  overview: { title: 'Flux AI', sub: 'AI usage across all plugins: cost, time, cache and tools.', View: Overview },
  logs: { title: 'Logs', sub: 'Every model request and tool call: which system, for whom, which model, tokens, time and cost. Content is stored with personal data masked.', View: Logs },
  billing: { title: 'Billing', sub: 'Monthly breakdown, spending limits and provider credit. Amounts in USD; OpenRouter reports actual cost, Anthropic is computed from the price list.', View: Billing },
  systems: { title: 'Connected systems', sub: '', View: Systems },
  tools: { title: 'Tools', sub: '', View: Tools },
};

export function AiScreen() {
  const host = useHost();
  const client = useMemo(() => createGraphQLClient({ api: host.client }), [host.client]);
  return (
    <FlxGraphQLProvider client={client} scope={host.scope}>
      <Page />
    </FlxGraphQLProvider>
  );
}

function Page() {
  const host = useHost();
  const { t } = useAi();
  const v = VIEWS[host.startPage ?? 'overview'] ?? VIEWS.overview!;
  const View = v.View;
  return (
    <div className="flxai-app">
      <div className="flxai-head">
        <h1>{t(v.title)}</h1>
        {host.classicUrl && (
          <a className="flxai-classic" href={host.classicUrl}>
            {t('Classic view')}
          </a>
        )}
      </div>
      {v.sub && <p className="flxai-lead">{t(v.sub)}</p>}
      <View />
    </div>
  );
}
