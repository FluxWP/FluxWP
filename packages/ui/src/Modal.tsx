import { useEffect, type ReactNode } from 'react';
import { useOverlay } from './overlay';

export function Modal({ title, onClose, children, wide }: { title?: string; onClose: () => void; children: ReactNode; wide?: boolean }) {
  useEffect(() => useOverlay.getState().pushOverlay(), []);
  useEffect(() => {
    const k = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
    window.addEventListener('keydown', k);
    return () => window.removeEventListener('keydown', k);
  }, [onClose]);
  return (
    <div className="overlay center-y" onMouseDown={onClose}>
      <div className={'modal' + (wide ? ' wide' : '')} role="dialog" aria-label={title} onMouseDown={(e) => e.stopPropagation()}>
        {title && <h2 className="modal-title">{title}</h2>}
        {children}
      </div>
    </div>
  );
}
