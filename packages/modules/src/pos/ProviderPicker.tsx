import { useState } from 'react';
import type { FlxPosProviderGroup } from '@fluxwp/graphql/types';
import { ChevronRight, CreditCard, Gift, Landmark, Search, Wallet } from 'lucide-react';
import { Badge, Dialog, EmptyState, SearchField, Tile } from './components';
import type { Translate } from './i18n';
import type { Provider } from './types';

const groupsInOrder: FlxPosProviderGroup[] = ['BANK', 'PSP', 'WALLET', 'MEAL'];
const groupIcons = { BANK: Landmark, PSP: CreditCard, WALLET: Wallet, MEAL: Gift };
export const providerIcon = (group?: FlxPosProviderGroup) => (group ? groupIcons[group] : Landmark);

export function ProviderPicker({
  t,
  providers,
  onPick,
  onClose,
}: {
  t: Translate;
  providers: Provider[];
  onPick: (p: Provider) => void;
  onClose: () => void;
}) {
  const [s, setS] = useState('');
  const groups: Record<FlxPosProviderGroup, string> = {
    BANK: t('Banks'),
    PSP: t('Payment institutions'),
    WALLET: t('Wallets and other methods'),
    MEAL: t('Meal and gift cards'),
  };
  const norm = (x: string) => x.toLocaleLowerCase('tr').normalize('NFD').replace(/[̀-ͯ]/g, '');
  const hit = providers.filter((p) => norm(p.name + ' ' + p.id).includes(norm(s.trim())));
  return (
    <Dialog t={t} title={t('Pick a provider')} onClose={onClose} wide>
      <SearchField autoFocus label={t('Search: bank or company name')} value={s} onChange={(e) => setS(e.currentTarget.value)} />
      {groupsInOrder.map((g) => {
        const rows = hit.filter((p) => p.group === g);
        if (!rows.length) return null;
        return (
          <section className="flxpos-provider-group" key={g}>
            <h3 className="flxpos-sub">{groups[g]}</h3>
            <div className="flxpos-providers">
              {rows.map((p) => (
                <button key={p.id} type="button" className="flxpos-provider" onClick={() => onPick(p)}>
                  <Tile icon={providerIcon(g)} />
                  <span className="flxpos-provider__main">
                    <strong>{p.name}</strong>
                    <small>{p.currencies.slice(0, 4).join(' · ')}</small>
                    {!p.ready && <Badge tone="is-warn">{t('needs a live test')}</Badge>}
                  </span>
                  <ChevronRight size={16} aria-hidden="true" />
                </button>
              ))}
            </div>
          </section>
        );
      })}
      {hit.length === 0 && <EmptyState icon={Search} title={t('Nothing found.')} />}
    </Dialog>
  );
}
