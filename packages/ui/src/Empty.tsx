import type { ReactNode } from 'react';
import { Loader2 } from 'lucide-react';

export const Loading = () => (
  <div className="loading"><Loader2 className="spin" size={20} /></div>
);

export function Empty({ icon, title, body }: { icon?: ReactNode; title: string; body?: string }) {
  return (
    <div className="empty-mini">
      {icon}
      <strong>{title}</strong>
      {body && <span className="muted small">{body}</span>}
    </div>
  );
}
