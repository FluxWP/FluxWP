import type { ComponentProps, ReactNode } from 'react';
import { Empty, Modal } from '@fluxwp/ui';
import { CircleAlert, CircleCheck, Info, Save, Search, X, type LucideIcon } from 'lucide-react';
import type { Translate } from './i18n';
import type { Notice } from './types';

export function msg(error: unknown, t: Translate) {
  return error instanceof Error && error.message ? error.message : t('The action could not be completed.');
}

export function Tile({ icon: Icon }: { icon: LucideIcon }) {
  return (
    <span className="flxpos-tile" aria-hidden="true">
      <Icon size={18} />
    </span>
  );
}

export function Panel({
  icon,
  title,
  description,
  action,
  className = '',
  children,
}: {
  icon: LucideIcon;
  title: string;
  description?: string;
  action?: ReactNode;
  className?: string;
  children: ReactNode;
}) {
  return (
    <section className={'flxpos-card ' + className}>
      <header className="flxpos-card__head">
        <Tile icon={icon} />
        <div className="flxpos-card__title">
          <h2>{title}</h2>
          {description && <p className="flxpos-muted">{description}</p>}
        </div>
        {action}
      </header>
      <div className="flxpos-card__body">{children}</div>
    </section>
  );
}

export function FieldGroup({ icon: Icon, title, children }: { icon: LucideIcon; title: string; children: ReactNode }) {
  return (
    <section className="flxpos-fieldgroup">
      <h3>
        <Icon size={16} aria-hidden="true" />
        {title}
      </h3>
      {children}
    </section>
  );
}

export function Badge({ children, tone = '' }: { children: ReactNode; tone?: string }) {
  return <span className={'flxpos-badge ' + tone}>{children}</span>;
}

export function InlineNotice({
  kind,
  children,
  close,
}: {
  kind: Notice['kind'] | 'info';
  children: ReactNode;
  close?: { label: string; action: () => void };
}) {
  const Icon = kind === 'ok' ? CircleCheck : kind === 'info' ? Info : CircleAlert;
  return (
    <div className={'flxpos-notice is-' + kind} role={kind === 'bad' ? 'alert' : 'status'}>
      <Icon size={17} aria-hidden="true" />
      <span>{children}</span>
      {close && (
        <button type="button" className="icon-btn" aria-label={close.label} onClick={close.action}>
          <X size={16} aria-hidden="true" />
        </button>
      )}
    </div>
  );
}

export function Failed({ error, t }: { error: unknown; t: Translate }) {
  return <InlineNotice kind="bad">{msg(error, t)}</InlineNotice>;
}

export function EmptyState({ icon, ...props }: { icon: LucideIcon } & Omit<ComponentProps<typeof Empty>, 'icon'>) {
  return (
    <div className="flxpos-empty">
      <Empty icon={<Tile icon={icon} />} {...props} />
    </div>
  );
}

export function SaveBar({
  t,
  label,
  dirty,
  pending,
  onSave,
  onReset,
}: {
  t: Translate;
  label: string;
  dirty: boolean;
  pending: boolean;
  onSave: () => void;
  onReset: () => void;
}) {
  return (
    <div className="flxpos-actions flxpos-savebar">
      {dirty && (
        <span className="flxpos-dirty" role="status">
          {t('Unsaved changes')}
        </span>
      )}
      {dirty && (
        <button type="button" className="btn-ghost" disabled={pending} onClick={onReset}>
          {t('Undo')}
        </button>
      )}
      <button type="button" className="btn-primary" disabled={!dirty || pending} onClick={onSave}>
        <Save size={16} aria-hidden="true" />
        {pending ? t('Saving…') : label}
      </button>
    </div>
  );
}

export function SearchField({ label, ...props }: Omit<ComponentProps<'input'>, 'type'> & { label: string }) {
  return (
    <label className="flxpos-search">
      <Search size={17} aria-hidden="true" />
      <input type="search" aria-label={label} placeholder={label} {...props} />
    </label>
  );
}

export function Dialog({
  title,
  t,
  onClose,
  wide,
  children,
  actions,
}: {
  title: string;
  t: Translate;
  onClose: () => void;
  wide?: boolean;
  children: ReactNode;
  actions?: ReactNode;
}) {
  return (
    <Modal title={title} onClose={onClose} wide={wide ?? false}>
      <button type="button" className="icon-btn flxpos-dialog__close" aria-label={t('Close')} onClick={onClose}>
        <X size={19} aria-hidden="true" />
      </button>
      <div className="flxpos-dialog__body">{children}</div>
      {actions && <div className="flxpos-actions flxpos-dialog__actions">{actions}</div>}
    </Modal>
  );
}
