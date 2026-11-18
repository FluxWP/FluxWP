// Flux AI & MCP screens. One module, one screen per wp-admin page (host.startPage): the same code runs in
// wp-admin (wp-embed) and the FluxWP app; every read and write goes through the Flux AI GraphQL operations
// (plugins/flx-ai/graphql/operations).
import { useMemo, type ComponentType } from 'react';
import { createGraphQLClient } from '@fluxwp/graphql';
import { FlxGraphQLProvider } from '@fluxwp/graphql/react';
import { useHost } from '../host';
import { Approvals } from './Approvals';
import { Billing } from './Billing';
import { useAi } from './common';
import { ChatScreen } from './chat/ChatScreen';
import { Connections } from './Connections';
import { History } from './History';
import { Logs } from './Logs';
import { Mcp } from './Mcp';
import { Overview } from './Overview';
import { Settings } from './Settings';
import { Skills } from './Skills';
import { Systems } from './Systems';
import { Tasks } from './Tasks';
import { Tools } from './Tools';
import './ai.css';
import { Screen } from '@fluxwp/ui';

const VIEWS: Record<string, { title: string; sub: string; View: ComponentType; wide?: boolean }> = {
  overview: { title: 'Flux AI', sub: 'AI usage across all plugins: cost, time, cache and tools.', View: Overview, wide: true },
  logs: { title: 'Logs', sub: 'Every model request and tool call: which system, for whom, which model, tokens, time and cost. Content is stored with personal data masked.', View: Logs, wide: true },
  billing: { title: 'Billing', sub: 'Monthly breakdown, spending limits and provider credit. Amounts in USD; OpenRouter reports actual cost, Anthropic is computed from the price list.', View: Billing, wide: true },
  systems: { title: 'Connected systems', sub: '', View: Systems },
  tools: { title: 'Tools', sub: '', View: Tools },
  approvals: { title: 'Awaiting approval', sub: '', View: Approvals },
  tasks: { title: 'Scheduled tasks', sub: '', View: Tasks },
  skills: { title: 'Skills', sub: '', View: Skills },
  settings: { title: 'Settings', sub: '', View: Settings },
  mcp: { title: 'MCP', sub: '', View: Mcp },
  connections: { title: 'Connections', sub: '', View: Connections },
  history: { title: 'Chat history', sub: '', View: History },
  chat: { title: 'Chat', sub: '', View: ChatScreen, wide: true },
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
    <Screen className="flxai-app" width={v.wide ? 'wide' : 'md'} icon={host.appIcon} title={t(v.title)} subtitle={v.sub ? t(v.sub) : undefined}>
      <View />
    </Screen>
  );
}
