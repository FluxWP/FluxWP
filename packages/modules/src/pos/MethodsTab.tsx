import { useState, type DragEvent } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { flxQueryKey, useFlxMutation, useFlxQuery, useGraphQL } from '@fluxwp/graphql/react';
import { FlxPosMethods, FlxPosMethodsUpdate } from '@fluxwp/graphql/operations';
import type { FlxPosMethodsQuery } from '@fluxwp/graphql/types';
import { Check, Loading } from '@fluxwp/ui';
import { ArrowDown, ArrowUp, CreditCard, GripVertical, Settings, Wallet } from 'lucide-react';
import { CheckoutPreview } from './CheckoutPreview';
import { Badge, EmptyState, Failed, InlineNotice, Panel, SaveBar, Tile, msg } from './components';
import type { Method, TabProps } from './types';

export function MethodsTab({ t, say }: TabProps) {
  const { scope } = useGraphQL();
  const qc = useQueryClient();
  const q = useFlxQuery(FlxPosMethods, {});
  const [order, setOrder] = useState<string[] | null>(null);
  const [on, setOn] = useState<Record<string, boolean>>({});
  const [card, setCard] = useState<string | null>(null);
  const [drag, setDrag] = useState<string | null>(null);
  const [pick, setPick] = useState<string | null>(null);
  const save = useFlxMutation(FlxPosMethodsUpdate, {
    onSuccess: (r) => {
      const p = r.flxPosMethodsUpdate;
      if (!p) return say({ kind: 'bad', text: t('The action could not be completed.') });
      if (p.errors.length) return say({ kind: 'bad', text: p.errors.map((e) => e.message).join(' ') });
      qc.setQueryData<FlxPosMethodsQuery>(flxQueryKey(scope, FlxPosMethods, {}), { flxPosMethods: p.methods });
      setOrder(null);
      setOn({});
      setCard(null);
      say({ kind: 'ok', text: t('Saved. The checkout shows the methods in this order.') });
    },
    onError: (e) => say({ kind: 'bad', text: msg(e, t) }),
  });

  if (q.isLoading) return <Loading />;
  if (q.isError || !q.data) return <Failed t={t} error={q.error} />;
  const d = q.data.flxPosMethods;
  const byId = new Map(d.methods.map((m) => [m.id, m]));
  const ids = (order ?? d.methods.map((m) => m.id)).filter((id) => byId.has(id));
  const list = ids.map((id) => byId.get(id) as Method);
  const enabled = (m: Method) => on[m.id] ?? m.enabled;
  const dirty = order !== null || Object.keys(on).length > 0 || card !== null;

  const move = (id: string, to: number) => {
    const next = ids.filter((x) => x !== id);
    next.splice(Math.max(0, Math.min(to, next.length)), 0, id);
    setOrder(next);
  };
  const onDrop = (e: DragEvent, target: string) => {
    e.preventDefault();
    if (drag && drag !== target) move(drag, ids.indexOf(target));
    setDrag(null);
  };
  const submit = () =>
    save.mutate({
      input: {
        order: ids,
        switches: Object.entries(on).map(([id, enabled]) => ({ id, enabled })),
        ...(card !== null ? { cardMethod: card } : {}),
      },
    });

  const cardIds = new Set([
    'flxpos_card',
    ...d.cardChoices.map((choice) => choice.value).filter((value) => !['auto', 'all', 'flxpos'].includes(value)),
  ]);
  const hidden = (method: Method) => {
    if (card === 'all') return false;
    if (!card || card === 'auto') return method.hidden;
    const selectedId = card === 'flxpos' ? 'flxpos_card' : card;
    return (cardIds.has(method.id) || method.hidden) && method.id !== selectedId;
  };
  const preview = list.filter((m) => enabled(m) && !hidden(m));
  const chosen = preview.find((m) => m.id === pick) ?? preview[0];

  return (
    <div className="flxpos-grid">
      <Panel icon={CreditCard} title={t('Payment methods')} description={t('Drag or use the arrows to change the checkout order.')}>
        {d.blockCheckout && <InlineNotice kind="info">{t('The checkout page uses the WooCommerce checkout block.')}</InlineNotice>}
        {list.length === 0 && <EmptyState icon={CreditCard} title={t('No payment methods found.')} />}
        <ul className="flxpos-methods">
          {list.map((m, i) => (
            <li
              key={m.id}
              className={'flxpos-method' + (drag === m.id ? ' is-drag' : '') + (enabled(m) ? '' : ' is-off')}
              draggable
              onDragStart={(e) => {
                setDrag(m.id);
                e.dataTransfer.setData('text/plain', m.id);
                e.dataTransfer.effectAllowed = 'move';
              }}
              onDragOver={(e) => e.preventDefault()}
              onDrop={(e) => onDrop(e, m.id)}
              onDragEnd={() => setDrag(null)}
            >
              <span className="flxpos-grip" aria-hidden="true">
                <GripVertical size={17} />
              </span>
              <Tile icon={m.offline ? Wallet : CreditCard} />
              <div className="flxpos-method__body">
                <div className="flxpos-method__title">
                  <strong>{m.title || m.methodTitle}</strong>
                  {m.title && m.methodTitle && m.title !== m.methodTitle && <small>{m.methodTitle}</small>}
                  {m.flux && <Badge tone="is-accent">FluxWP</Badge>}
                  {m.offline && <Badge>{t('offline')}</Badge>}
                  {hidden(m) && enabled(m) && (
                    <span className="flxpos-badge is-warn" title={t('Another card method is shown at checkout (one-card rule).')}>
                      {t('hidden by the card rule')}
                    </span>
                  )}
                </div>
                {m.caps.length > 0 && (
                  <div className="flxpos-caps">
                    {m.caps.map((c) => (
                      <span
                        key={c.key}
                        className={'flxpos-cap is-' + c.state.toLowerCase()}
                        title={[c.help, c.note].filter(Boolean).join(' · ')}
                      >
                        {c.label}
                      </span>
                    ))}
                  </div>
                )}
              </div>
              <div className="flxpos-method__tools">
                <button
                  type="button"
                  className="icon-btn"
                  disabled={i === 0}
                  aria-label={t('Move up')}
                  title={t('Move up')}
                  onClick={() => move(m.id, i - 1)}
                >
                  <ArrowUp size={15} aria-hidden="true" />
                </button>
                <button
                  type="button"
                  className="icon-btn"
                  disabled={i === list.length - 1}
                  aria-label={t('Move down')}
                  title={t('Move down')}
                  onClick={() => move(m.id, i + 1)}
                >
                  <ArrowDown size={15} aria-hidden="true" />
                </button>
                {m.settingsUrl && (
                  <a className="icon-btn" href={m.settingsUrl} aria-label={t('Settings')} title={t('Settings')}>
                    <Settings size={15} aria-hidden="true" />
                  </a>
                )}
                <Check switch checked={enabled(m)} onChange={(v) => setOn({ ...on, [m.id]: v })} aria-label={m.title || m.methodTitle} />
              </div>
            </li>
          ))}
        </ul>
        {d.cardChoices.length > 1 && (
          <label className="field flxpos-field flxpos-card-rule">
            <span>{t('Card payments at checkout')}</span>
            <select value={card ?? d.cardMethod} onChange={(e) => setCard(e.currentTarget.value)}>
              {d.cardChoices.map((o) => (
                <option key={o.value} value={o.value}>
                  {o.label}
                </option>
              ))}
            </select>
            <small>
              {d.cardMethodOwner === 'donate'
                ? t('Flux Donate rule: only one card method is shown so donors do not see two card forms.')
                : t('Only one card method is shown so customers do not see two card forms.')}
            </small>
          </label>
        )}
        <SaveBar
          t={t}
          label={t('Save')}
          dirty={dirty}
          pending={save.isPending}
          onSave={submit}
          onReset={() => {
            setOrder(null);
            setOn({});
            setCard(null);
          }}
        />
      </Panel>
      <CheckoutPreview t={t} methods={preview} selected={chosen?.id} onSelect={setPick} checkoutUrl={d.checkoutUrl} />
    </div>
  );
}
