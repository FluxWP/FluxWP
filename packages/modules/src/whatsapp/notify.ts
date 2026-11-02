// Gelen mesaj bildirimi: sekme gizliyken ya da başka sohbetteyken kısa ses + masaüstü bildirimi.
import type { FlxcSync } from '@fluxwp/core';

let ctx: AudioContext | null = null;
function beep() {
  try {
    ctx ??= new AudioContext();
    const o = ctx.createOscillator();
    const g = ctx.createGain();
    o.frequency.value = 880;
    g.gain.setValueAtTime(0.08, ctx.currentTime);
    g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.25);
    o.connect(g).connect(ctx.destination);
    o.start();
    o.stop(ctx.currentTime + 0.26);
  } catch {
    /* ses yok */
  }
}

const seen = new Set<number>();

export function notifyIncoming(
  items: FlxcSync['incoming'],
  active: number | null,
  native: ((n: { title: string; body: string; icon?: string; onClick?: () => void }) => void) | undefined,
  open: (id: number) => void,
) {
  const fresh = items.filter((m) => !seen.has(m.id) && (document.hidden || m.chat !== active));
  items.forEach((m) => seen.add(m.id));
  if (!fresh.length) return;
  beep();
  if (!document.hidden) return;
  const m = fresh[0]!;
  const n = { title: m.title, body: fresh.length > 1 ? `${m.body} (+${fresh.length - 1})` : m.body, icon: m.avatar || undefined, onClick: () => (window.focus(), open(m.chat)) };
  if (native) return native(n);
  if (typeof Notification === 'undefined') return;
  const show = () => {
    const x = new Notification(n.title, { body: n.body, icon: n.icon, tag: 'flxc-' + m.chat });
    x.onclick = n.onClick;
  };
  if (Notification.permission === 'granted') show();
  else if (Notification.permission === 'default') void Notification.requestPermission().then((p) => p === 'granted' && show());
}
