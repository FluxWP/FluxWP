/**
 * Ortak liste filtre çubuğu + sayfalama: klasik Flux Admin liste API'siyle (flx-admin includes/list.php + list.js)
 * aynı işaretleme ve sınıflar (.flxa-list, .flxa-pages); CSS tek kaynak flx-admin/assets/css/list.css.
 * Arama + birincil filtreler görünür, kalanlar "Filtreler" düğmesiyle açılan panelde; sayfa numaraları + "Sayfa başı".
 */
import { useState, type FormEvent, type ReactNode } from 'react';

const STR = {
  en: { filters: 'Filters', clear: 'Clear', pages: 'Pagination', prev: 'Previous page', next: 'Next page', per: 'Per page', range: '%1$s–%2$s of %3$s' },
  tr: { filters: 'Filtreler', clear: 'Temizle', pages: 'Sayfalama', prev: 'Önceki sayfa', next: 'Sonraki sayfa', per: 'Sayfa başı', range: '%1$s–%2$s / %3$s' },
};
const lang = () => (typeof document !== 'undefined' && document.documentElement.lang) || 'en';
const str = () => (/^tr\b/i.test(lang()) ? STR.tr : STR.en);

function store(key: string, val?: string): string | null {
  try {
    if (val === undefined) return sessionStorage.getItem('flxa-list-' + key);
    sessionStorage.setItem('flxa-list-' + key, val);
  } catch {
    /* depolama kapalı */
  }
  return null;
}

export interface ListBarProps {
  /** sessionStorage anahtarı (panel açık/kapalı), klasik listeyle aynı: ör. "flxcrm-contacts". */
  id: string;
  /** Arama kutusu (input type=search). */
  search?: ReactNode;
  /** Her zaman görünen filtreler (klasikte data-flxa-primary). */
  primary?: ReactNode;
  /** "Filtreler" panelindeki filtreler. */
  more?: ReactNode;
  /** Paneldeki dolu filtre sayısı (düğmedeki rozet). */
  moreActive?: number;
  /** Herhangi bir filtre doluysa "Temizle" bağlantısı. */
  onClear?: () => void;
  /** Enter ile arama. */
  onSubmit?: () => void;
  className?: string;
}

export function ListBar({ id, search, primary, more, moreActive = 0, onClear, onSubmit, className }: ListBarProps) {
  const s = str();
  const [open, setOpen] = useState(() => store(id) === '1');
  const submit = (e: FormEvent) => {
    e.preventDefault();
    onSubmit?.();
  };
  const clear = onClear ? (
    <a
      className="flxa-list__clear"
      href="#"
      onClick={(e) => {
        e.preventDefault();
        onClear();
      }}
    >
      {s.clear}
    </a>
  ) : null;
  return (
    <form className={'flxa-list' + (className ? ' ' + className : '')} onSubmit={submit} role="search">
      <div className="flxa-list__top">
        {search ? <div className="flxa-list__search">{search}</div> : null}
        {more ? (
          <button
            type="button"
            className={'button flxa-list__toggle' + (open ? ' is-open' : '')}
            aria-controls={'flxa-list-more-' + id}
            aria-expanded={open}
            onClick={() => {
              setOpen(!open);
              store(id, open ? '0' : '1');
            }}
          >
            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
              <path d="M4 6h16M7 12h10M10 18h4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
            </svg>
            <span className="flxa-list__tl">{s.filters}</span>
            {moreActive > 0 ? <b className="flxa-list__n">{moreActive}</b> : null}
          </button>
        ) : null}
        {!primary ? clear : null}
      </div>
      {primary ? (
        <div className="flxa-list__row">
          {primary}
          {clear}
        </div>
      ) : null}
      {more ? (
        <div className="flxa-list__more" id={'flxa-list-more-' + id} hidden={!open}>
          {more}
        </div>
      ) : null}
      <button type="submit" className="flxa-list__hidden" tabIndex={-1} aria-hidden="true" />
    </form>
  );
}

/** Sayfa numaraları (WordPress paginate_links düzeni: mid_size 1, end_size 1). */
function pageList(cur: number, pages: number): Array<number | 'dots'> {
  const out: Array<number | 'dots'> = [];
  let dots = false;
  for (let n = 1; n <= pages; n++) {
    if (n === cur || n <= 1 || n > pages - 1 || (n >= cur - 1 && n <= cur + 1)) {
      out.push(n);
      dots = true;
    } else if (dots) {
      out.push('dots');
      dots = false;
    }
  }
  return out;
}

export const LIST_PER_PAGE = [10, 20, 50, 100];

/** Kayıtlı sayfa başı (klasikte kullanıcı meta'sı; burada tarayıcıda hatırlanır). */
export function listPerPage(id: string, fallback = 25): number {
  try {
    const v = parseInt(localStorage.getItem('flxa-per-' + id) || '', 10);
    return v > 0 ? v : fallback;
  } catch {
    return fallback;
  }
}

export interface ListPagesProps {
  total: number;
  per: number;
  /** 1'den başlayan sayfa. */
  page: number;
  onPage: (page: number) => void;
  /** Verilirse "Sayfa başı" seçici görünür ve seçim id ile hatırlanır. */
  onPer?: (per: number) => void;
  id?: string;
  options?: number[];
  locale?: string;
}

export function ListPages({ total, per, page, onPage, onPer, id, options = LIST_PER_PAGE, locale = lang() }: ListPagesProps) {
  const s = str();
  if (total < 1) return null;
  per = Math.max(1, per);
  const pages = Math.ceil(total / per);
  const cur = Math.min(Math.max(1, page), pages);
  const nf = new Intl.NumberFormat(locale);
  const from = (cur - 1) * per + 1;
  const to = Math.min(total, cur * per);
  const opts = options.includes(per) ? options : [...options, per].sort((a, b) => a - b);
  const go = (n: number) => (e: React.MouseEvent) => {
    e.preventDefault();
    onPage(n);
  };
  return (
    <nav className="flxa-pages" aria-label={s.pages}>
      <span className="flxa-pages__info">{s.range.replace('%1$s', nf.format(from)).replace('%2$s', nf.format(to)).replace('%3$s', nf.format(total))}</span>
      {pages > 1 ? (
        <span className="flxa-pages__links">
          {cur > 1 ? (
            <a className="prev page-numbers" href="#" onClick={go(cur - 1)}>
              <span aria-hidden="true">‹</span>
              <span className="screen-reader-text">{s.prev}</span>
            </a>
          ) : null}
          {pageList(cur, pages).map((n, i) =>
            n === 'dots' ? (
              <span key={'d' + i} className="page-numbers dots">
                …
              </span>
            ) : n === cur ? (
              <span key={n} aria-current="page" className="page-numbers current">
                {nf.format(n)}
              </span>
            ) : (
              <a key={n} className="page-numbers" href="#" onClick={go(n)}>
                {nf.format(n)}
              </a>
            ),
          )}
          {cur < pages ? (
            <a className="next page-numbers" href="#" onClick={go(cur + 1)}>
              <span aria-hidden="true">›</span>
              <span className="screen-reader-text">{s.next}</span>
            </a>
          ) : null}
        </span>
      ) : null}
      {onPer && total > Math.min(...opts) ? (
        <form className="flxa-pages__per" onSubmit={(e) => e.preventDefault()}>
          <label>
            <span>{s.per}</span>
            <select
              value={per}
              onChange={(e) => {
                const v = +e.target.value;
                if (id) {
                  try {
                    localStorage.setItem('flxa-per-' + id, String(v));
                  } catch {
                    /* depolama kapalı */
                  }
                }
                onPer(v);
              }}
            >
              {opts.map((o) => (
                <option key={o} value={o}>
                  {nf.format(o)}
                </option>
              ))}
            </select>
          </label>
        </form>
      ) : null}
    </nav>
  );
}
