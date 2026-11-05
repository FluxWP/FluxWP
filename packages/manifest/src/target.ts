// §6 targets: one syntax for shell, dashboard cards, menu, push and webview link capture.

export type ShellName = 'more' | 'accounts' | 'settings' | 'search' | 'notifications';
export const SHELL_NAMES: readonly ShellName[] = ['more', 'accounts', 'settings', 'search', 'notifications'];

export type Target =
  | { kind: 'screen'; module: string; screen: string }
  | { kind: 'module'; module: string }
  | { kind: 'menu'; slug: string }
  | { kind: 'web'; path: string }
  | { kind: 'url'; url: string }
  | { kind: 'native'; component: string }
  | { kind: 'shell'; name: ShellName };

const SAFE_URL = /^(https:\/\/|tel:|mailto:|sms:|whatsapp:)/i;

/** wp-admin relative path only (no scheme, no protocol-relative, no traversal out of wp-admin). */
export function safeAdminPath(p: string): string | null {
  const t = p.trim();
  if (t.startsWith('//')) return null;
  const s = t.replace(/^\/?wp-admin\//, '').replace(/^\/+/, '');
  if (!s || /^[a-z][a-z0-9+.-]*:/i.test(s) || s.startsWith('//') || s.includes('..') || /[\s\\]/.test(s)) return null;
  return s;
}

export function parseTarget(input: string | null | undefined): Target | null {
  if (!input) return null;
  const s = input.trim();
  if ((SHELL_NAMES as readonly string[]).includes(s)) return { kind: 'shell', name: s as ShellName };
  const i = s.indexOf(':');
  if (i < 1) return null;
  const kind = s.slice(0, i);
  const rest = s.slice(i + 1);
  switch (kind) {
    case 'screen': {
      const m = rest.match(/^([\w-]+)\/([\w-]+)$/);
      return m ? { kind: 'screen', module: m[1]!, screen: m[2]! } : null;
    }
    case 'module':
      return /^[\w-]+$/.test(rest) ? { kind: 'module', module: rest } : null;
    case 'menu':
      return rest ? { kind: 'menu', slug: rest } : null;
    case 'web': {
      const p = safeAdminPath(rest);
      return p ? { kind: 'web', path: p } : null;
    }
    case 'url':
      return SAFE_URL.test(rest) ? { kind: 'url', url: rest } : null;
    case 'native':
      return /^[\w-]+$/.test(rest) ? { kind: 'native', component: rest } : null;
    // Legacy dashboard card target ("mod:flxb-review").
    case 'mod':
      return /^[\w-]+$/.test(rest) ? { kind: 'module', module: rest } : null;
    default:
      return null;
  }
}

export function formatTarget(t: Target): string {
  switch (t.kind) {
    case 'screen':
      return `screen:${t.module}/${t.screen}`;
    case 'module':
      return `module:${t.module}`;
    case 'menu':
      return `menu:${t.slug}`;
    case 'web':
      return `web:${t.path}`;
    case 'url':
      return `url:${t.url}`;
    case 'native':
      return `native:${t.component}`;
    case 'shell':
      return t.name;
  }
}

/**
 * Manifest `routes` matching for webview link capture: "admin.php?page=flx-crm-contacts&id={id}".
 * Query order does not matter; `{name}` captures a value.
 */
export function matchRoute(pattern: string, path: string): Record<string, string> | null {
  const [pp, pq = ''] = pattern.split('?', 2);
  const [ap, aq = ''] = path.replace(/^\/?wp-admin\//, '').split('?', 2);
  if (pp !== ap) return null;
  const want = new URLSearchParams(pq);
  const have = new URLSearchParams(aq);
  const out: Record<string, string> = {};
  for (const [k, v] of want) {
    const got = have.get(k);
    if (got === null) return null;
    const cap = v.match(/^\{(\w+)\}$/);
    if (cap) out[cap[1]!] = got;
    else if (got !== v) return null;
  }
  return out;
}
