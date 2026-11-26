// Ortak ekran kabuğu: her modül ekranı aynı başlığı, sekmeleri, kenar boşluklarını ve en fazla genişliği kullanır.
// Modül kendi başlık/sekme CSS'ini yazmaz; içerik `className` ile modülün kök sınıfını taşır (eski CSS'i çalışır).
import { createContext, useContext, type ReactNode } from 'react';

export interface TabItem<T extends string = string> {
  id: T;
  label: ReactNode;
  /** Sekme yanında küçük sayı (bekleyen, hata vb.). 0/boş gösterilmez. */
  badge?: number | string | null;
  hidden?: boolean;
}

export interface TabsProps<T extends string = string> {
  items: TabItem<T>[];
  current: T | null | undefined;
  onChange: (id: T) => void;
  /** Ekran okuyucu etiketi (genelde ekran başlığı). */
  label?: string | undefined;
  /** İkincil sekme sırası (ekran içinde alt görünüm): daha küçük ve alt çizgisiz. */
  /** md: underline (page sections), sm: pills, seg: segmented filter (classic package filter). */
  size?: 'md' | 'sm' | 'seg';
  className?: string;
}

/** Ortak sekme çubuğu: eski Flux Admin sekmeleriyle aynı görünüm (alt çizgi, taşarsa yatay kaydırma). */
export function Tabs<T extends string = string>({ items, current, onChange, label, size = 'md', className }: TabsProps<T>) {
  return (
    <nav className={'flx-tabs' + (size === 'md' ? '' : ' flx-tabs--' + size) + (className ? ' ' + className : '')} aria-label={label}>
      {items
        .filter((x) => !x.hidden)
        .map((x) => (
          <button key={x.id} type="button" className={'flx-tab' + (x.id === current ? ' is-active' : '')} aria-current={x.id === current ? 'page' : undefined} onClick={() => onChange(x.id)}>
            {x.label}
            {x.badge ? <span className="flx-tab__badge">{x.badge}</span> : null}
          </button>
        ))}
    </nav>
  );
}

export interface ScreenProps<T extends string = string> {
  title: ReactNode;
  subtitle?: ReactNode;
  /** Başlık simgesi (WP'de eklenti logosu: host.appIcon). */
  icon?: string | ReactNode;
  /** Başlığın üstündeki geri bağlantısı (klasik "‹ Liste" bağlantısı). */
  back?: ReactNode;
  /** Başlığın sağındaki düğmeler. */
  actions?: ReactNode;
  /** Başlık ile sekmeler arasındaki bildirim alanı. */
  notice?: ReactNode;
  tabs?: TabItem<T>[];
  tab?: T | null;
  onTab?: (id: T) => void;
  /** Sekmelerin altında, içerikten önce (filtre çubuğu vb.). */
  toolbar?: ReactNode;
  /** 'md' (varsayılan, ayar ve liste ekranları) | 'wide' (pano, tablo) | 'full' (tam genişlik). */
  width?: 'md' | 'wide' | 'full';
  /** Modülün kök sınıfı (ör. flxy-app): modül içerik CSS'i bunun altında kalır. */
  className?: string;
  children?: ReactNode;
}

/** Ekranın varsayılan alt başlığı (klasik Flux Admin başlığı gibi: ekran kendi alt başlığını vermezse paket açıklaması). */
export const ScreenDefaults = createContext<{ subtitle?: string | undefined; title?: string | undefined }>({});

/**
 * Ürün adıyla verilen başlık ("Flux Performance", "Flux Backup") paketin sol menüdeki adıyla değişir: kullanıcı
 * ekranı menüde gördüğü adla ("Performans") tanır. Kayıt/alt sayfa başlıkları (ürün adı olmayanlar) aynen kalır.
 */
export function screenTitle(title: ReactNode, menuTitle: string | undefined): ReactNode {
  return menuTitle && typeof title === 'string' && /^Flux\s/.test(title) ? menuTitle : title;
}

/** Ortak ekran kabuğu: başlık + sekmeler + içerik; padding, en fazla genişlik, safe area ve taşma tek yerde. */
export function Screen<T extends string = string>(p: ScreenProps<T>) {
  const def = useContext(ScreenDefaults);
  const subtitle = p.subtitle ?? def.subtitle;
  const title = screenTitle(p.title, def.title);
  const icon = typeof p.icon === 'string' ? <img className="flx-page__icon" src={p.icon} alt="" width={40} height={40} /> : p.icon;
  return (
    <div className={'flx-page flx-page--' + (p.width || 'md') + (p.className ? ' ' + p.className : '')}>
      {p.back ? <div className="flx-page__back">{p.back}</div> : null}
      <header className="flx-page__head">
        {icon}
        <div className="flx-page__title">
          <h1>{title}</h1>
          {subtitle ? <p>{subtitle}</p> : null}
        </div>
        {p.actions ? <div className="flx-page__actions">{p.actions}</div> : null}
      </header>
      {p.notice}
      {p.tabs && p.onTab ? <Tabs items={p.tabs} current={p.tab} onChange={p.onTab} label={typeof title === 'string' ? title : undefined} /> : null}
      {p.toolbar}
      <div className="flx-page__body">{p.children}</div>
    </div>
  );
}
