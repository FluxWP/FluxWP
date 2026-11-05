// Incoming WhatsApp message: a short beep, and a notification when the app is in the background.
import type { FlxcSync } from '@fluxwp/core';
import { browserPorts, type ModulePorts } from '../ports';

const seen = new Set<number>();

export function notifyIncoming(
  items: FlxcSync['incoming'],
  active: number | null,
  native: ((n: { title: string; body: string; icon?: string; onClick?: () => void }) => void) | undefined,
  open: (id: number) => void,
  ports: ModulePorts = browserPorts(),
) {
  const hidden = !ports.visible();
  const fresh = items.filter((m) => !seen.has(m.id) && (hidden || m.chat !== active));
  items.forEach((m) => seen.add(m.id));
  if (!fresh.length) return;
  ports.beep();
  if (!hidden) return;
  const m = fresh[0]!;
  const n = {
    title: m.title,
    body: fresh.length > 1 ? `${m.body} (+${fresh.length - 1})` : m.body,
    icon: m.avatar || undefined,
    tag: 'flxc-' + m.chat,
    onClick: () => (ports.focus(), open(m.chat)),
  };
  if (native) return native(n);
  ports.showNotification(n);
}
