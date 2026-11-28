import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState, type KeyboardEvent as RKeyboardEvent } from 'react';
import { useOverlay } from './overlay';
import STR from './date-languages.json';

/**
 * Ortak tarih seçici. Görünüm ve sınıflar flx-brand `date/` ile aynı (`.flx-dp`, `.flx-dt`); CSS'i styles.css içe aktarır.
 * Değer biçimi `YYYY-MM-DD`, `time` ile `YYYY-MM-DDTHH:MM`; boş = `''`.
 * Dil: `locale` (yoksa `<html lang>`). Takvim etiketleri desteklenen tüm panel dillerini izler.
 */

export type DateQuick = { label: string; value: string; hint?: string };

type Common = {
  /** Saat satırı (24 saat). */
  time?: boolean;
  /** Soldaki hızlı seçim (varsayılan açık: Bugün, Yarın, Bu cuma, Gelecek hafta, 2 hafta sonra); false ya da kendi listen. */
  quick?: boolean | DateQuick[];
  min?: string;
  max?: string;
  /** Temizle düğmesi yok. */
  required?: boolean;
  /** "tr-TR", "en"… */
  locale?: string;
  /** 0 = Pazar, 1 = Pazartesi. Varsayılan dilden. */
  weekStart?: number;
};

type Strings = (typeof STR)['en'];

function docLocale(): string {
  return (typeof document !== 'undefined' && document.documentElement.lang) || (typeof navigator !== 'undefined' ? navigator.language : 'en') || 'en';
}
function strings(locale: string): Strings {
  const language = locale.toLowerCase().split(/[-_]/)[0];
  const key = (language === 'pt' ? 'pt-br' : language) as keyof typeof STR;
  return STR[key] ?? STR.en;
}
function fmt(locale: string, o: Intl.DateTimeFormatOptions) {
  try {
    return new Intl.DateTimeFormat(locale.replaceAll('_', '-'), { calendar: 'gregory', ...o });
  } catch {
    return new Intl.DateTimeFormat('en', o);
  }
}
function weekStartFor(locale: string): number {
  try {
    type WeekInfo = { firstDay: number };
    const language = new Intl.Locale(locale.replaceAll('_', '-')) as Intl.Locale & {
      getWeekInfo?(): WeekInfo;
      weekInfo?: WeekInfo;
    };
    const week = language.getWeekInfo?.() ?? language.weekInfo;
    if (week) return week.firstDay % 7;
  } catch {
    // The caller can still use a site-specific weekStart when Intl rejects the locale.
  }
  return /^tr\b/i.test(locale) ? 1 : 0;
}
const pad = (n: number) => (n < 10 ? '0' : '') + n;
const ymd = (x: Date) => `${x.getFullYear()}-${pad(x.getMonth() + 1)}-${pad(x.getDate())}`;
export function parseDate(s?: string | null): Date | null {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s ?? '');
  if (!m) return null;
  const x = new Date(+m[1]!, +m[2]! - 1, +m[3]!, 12);
  return isNaN(x.getTime()) || x.getDate() !== +m[3]! ? null : x;
}
const timeOf = (s?: string | null) => /T(\d{2}:\d{2})/.exec(s ?? '')?.[1] ?? '';
const today = () => {
  const n = new Date();
  return new Date(n.getFullYear(), n.getMonth(), n.getDate(), 12);
};
const addDays = (x: Date, n: number) => new Date(x.getFullYear(), x.getMonth(), x.getDate() + n, 12);
const addMonths = (x: Date, n: number) => {
  const y = x.getFullYear(),
    m = x.getMonth() + n,
    last = new Date(y, m + 1, 0).getDate();
  return new Date(y, m, Math.min(x.getDate(), last), 12);
};
const cap = (s: string, l: string) => (s ? s.charAt(0).toLocaleUpperCase(l.replaceAll('_', '-')) + s.slice(1) : s);
function cleanTime(v: string): string {
  const m = /^(\d{1,2})(?::?(\d{2}))?$/.exec(v.trim());
  if (!m) return '';
  const h = +m[1]!,
    mi = m[2] ? +m[2] : 0;
  return h > 23 || mi > 59 ? '' : `${pad(h)}:${pad(mi)}`;
}

/** "2026-11-20T14:30" → "20 Kasım 2026 · 14:30" (style short: "20 Kas"). */
export function formatDate(v: string, locale = docLocale(), style: 'long' | 'short' = 'long'): string {
  const x = parseDate(v);
  if (!x) return '';
  const tm = timeOf(v);
  return fmt(locale, style === 'short' ? { day: 'numeric', month: 'short' } : { day: 'numeric', month: 'long', year: 'numeric' }).format(x) + (tm ? ' · ' + tm : '');
}

function defaultQuick(s: Strings, locale: string, weekStart: number): DateQuick[] {
  const td = today();
  const add = (weekStart - td.getDay() + 7) % 7 || 7;
  const fri = (5 - td.getDay() + 7) % 7;
  const wd = fmt(locale, { weekday: 'short' });
  const sh = fmt(locale, { day: 'numeric', month: 'short' });
  const w = (x: Date) => cap(wd.format(x), locale).replace('.', '');
  const list: DateQuick[] = [
    { label: s.today, value: ymd(td), hint: w(td) },
    { label: s.tomorrow, value: ymd(addDays(td, 1)), hint: w(addDays(td, 1)) },
  ];
  if (fri > 1) list.push({ label: s.thisFriday, value: ymd(addDays(td, fri)), hint: sh.format(addDays(td, fri)) });
  list.push({ label: s.nextWeek, value: ymd(addDays(td, add)), hint: w(addDays(td, add)) });
  list.push({ label: s.in2Weeks, value: ymd(addDays(td, 14)), hint: sh.format(addDays(td, 14)) });
  return list;
}

/** Yazılan tarih: sıra ve ayraç dilden (TR "20.11.2026", ABD "11/20/2026"). */
function pattern(locale: string) {
  const order: ('day' | 'month' | 'year')[] = [];
  let sep = '.';
  try {
    for (const p of fmt(locale, { day: '2-digit', month: '2-digit', year: 'numeric' }).formatToParts(new Date(2026, 10, 20))) {
      if (p.type === 'day' || p.type === 'month' || p.type === 'year') order.push(p.type);
      else if (p.type === 'literal') sep = p.value.replace(/[\u061c\u200e\u200f]/g, '').trim().charAt(0) || sep;
    }
  } catch {
    /* varsayılan */
  }
  const o = order.length === 3 ? order : (['day', 'month', 'year'] as const).slice();
  const tr = /^tr\b/i.test(locale);
  const ph = o.map((k) => (k === 'year' ? 'YYYY' : k === 'day' ? (tr ? 'GG' : 'DD') : tr ? 'AA' : 'MM')).join(sep);
  const toText = (v: string) => {
    const x = parseDate(v);
    if (!x) return '';
    const m = { day: pad(x.getDate()), month: pad(x.getMonth() + 1), year: String(x.getFullYear()) };
    return o.map((k) => m[k]).join(sep);
  };
  const fromText = (str: string) => {
    const digits = str.replace(/[٠-٩۰-۹]/g, (digit) => String(digit.charCodeAt(0) & 15));
    const p = digits.trim().split(/[^\d]+/).filter(Boolean);
    if (p.length !== 3) return '';
    const m = { day: 0, month: 0, year: 0 };
    o.forEach((k, i) => (m[k] = +p[i]!));
    if (m.year < 100) m.year += 2000;
    const v = `${m.year}-${pad(m.month)}-${pad(m.day)}`;
    return parseDate(v) ? v : '';
  };
  return { ph, toText, fromText };
}

function themeOf(el: Element | null): 'dark' | 'light' {
  const a = el?.closest('[data-theme]');
  const v = a?.getAttribute('data-theme');
  if (v === 'dark' || v === 'light') return v;
  const h = document.documentElement.classList;
  return h.contains('flxa-dark') || h.contains('flxfw-dark') || h.contains('flxg-dark') || h.contains('dark') ? 'dark' : 'light';
}

const Ico = ({ d, s = 18 }: { d: string; s?: number }) => (
  <svg viewBox="0 0 24 24" width={s} height={s} fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" dangerouslySetInnerHTML={{ __html: d }} />
);
const I_L = '<path d="m15 6-6 6 6 6"/>';
const I_R = '<path d="m9 6 6 6-6 6"/>';
const I_DOWN = '<path d="m6 9 6 6 6-6"/>';
const I_CLK = '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>';
const I_CAL = '<rect x="3" y="4.5" width="18" height="16.5" rx="3"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/>';

type CalendarProps = Common & {
  value: string;
  onPick: (v: string) => void;
  onCancel?: () => void;
  /** Başka bir açılır kutunun içinde: çerçeve ve gölge yok. */
  bare?: boolean;
  /** Telefon görünümü (alttan açılan sayfa). */
  sheet?: boolean;
  style?: React.CSSProperties | undefined;
  theme?: 'dark' | 'light';
  label?: string | undefined;
};

/**
 * Takvim paneli (konumlama yok). Görünüm B (Ahmet 2026-11-17): solda hızlı seçim, sağda takvim (ay / yıl menüsü),
 * altta yazılabilir tarih (+ saat), Vazgeç ve Uygula. Güne tıklamak seçer, Uygula (Enter, çift tık) onaylar; hızlı seçim hemen onaylar.
 * Tasks gibi kendi açılır kutusu olan ekranlar doğrudan kullanır (`bare`).
 */
export function DateCalendar({ value, onPick, onCancel, time, quick = true, min, max, required, locale = docLocale(), weekStart, bare, sheet, style, theme, label }: CalendarProps) {
  const s = strings(locale);
  const ws = weekStart ?? weekStartFor(locale);
  const rtl = /^ar(?:[-_]|$)/i.test(locale);
  const previousIcon = rtl ? I_R : I_L;
  const nextIcon = rtl ? I_L : I_R;
  const F = useMemo(
    () => ({
      month: fmt(locale, { month: 'long' }),
      wd: fmt(locale, { weekday: 'short' }),
      full: fmt(locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }),
    }),
    [locale],
  );
  const P = useMemo(() => pattern(locale), [locale]);
  const minD = min ? min.slice(0, 10) : '';
  const maxD = max ? max.slice(0, 10) : '';
  const allowed = (d: string) => !((minD && d < minD) || (maxD && d > maxD));
  const init = (() => {
    const v = parseDate(value);
    if (v) return v;
    let b = today();
    const mn = parseDate(minD), mx = parseDate(maxD);
    if (mn && b < mn) b = mn;
    if (mx && b > mx) b = mx;
    return b;
  })();
  const [day, setDay] = useState(parseDate(value) ? value.slice(0, 10) : '');
  const [typed, setTyped] = useState(P.toText(value));
  const [tm, setTm] = useState(timeOf(value));
  const [view, setView] = useState(new Date(init.getFullYear(), init.getMonth(), 1, 12));
  const [focus, setFocus] = useState(init);
  const [mode, setMode] = useState<'days' | 'months' | 'years'>('days');
  const ref = useRef<HTMLDivElement>(null);
  const wantFocus = useRef(true);
  const [th, setTh] = useState<'dark' | 'light'>(theme ?? 'light');
  useLayoutEffect(() => {
    if (!theme) setTh(themeOf(ref.current?.parentElement ?? null));
  }, [theme]);
  useEffect(() => {
    if (!wantFocus.current) return;
    wantFocus.current = false;
    ref.current?.querySelector<HTMLElement>('.flx-dp-day[tabindex="0"], .flx-dp-cell[tabindex="0"]')?.focus({ preventScroll: true });
  });
  const q = !quick ? [] : Array.isArray(quick) ? quick : defaultQuick(s, locale, ws);
  const commit = (d: string) => {
    if (d && !allowed(d)) return;
    const t = time ? cleanTime(tm) : '';
    const out = d ? d + (t ? 'T' + t : '') : '';
    if (out === value) onCancel?.();
    else onPick(out);
  };
  const apply = () => commit(P.fromText(typed) || day || (time && cleanTime(tm) ? ymd(today()) : ''));
  const go = (fn: () => void) => {
    wantFocus.current = true;
    fn();
  };
  const select = (d: string) => {
    const x = parseDate(d)!;
    setDay(d);
    setTyped(P.toText(d));
    setFocus(x);
    if (x.getMonth() !== view.getMonth() || x.getFullYear() !== view.getFullYear()) setView(new Date(x.getFullYear(), x.getMonth(), 1, 12));
  };
  const clampFocus = (v: Date) => new Date(v.getFullYear(), v.getMonth(), Math.min(focus.getDate(), new Date(v.getFullYear(), v.getMonth() + 1, 0).getDate()), 12);

  const onKey = (e: RKeyboardEvent<HTMLDivElement>) => {
    const tg = e.target as HTMLElement;
    if (e.key === 'Escape') {
      e.preventDefault();
      e.stopPropagation();
      if (mode !== 'days') go(() => setMode('days'));
      else onCancel?.();
      return;
    }
    if (e.key === 'Enter' && (tg.tagName === 'INPUT' || tg.classList.contains('flx-dp-day'))) {
      e.preventDefault();
      if (tg.classList.contains('flx-dp-day')) commit(tg.dataset.day ?? '');
      else apply();
      return;
    }
    if (tg.classList.contains('flx-dp-cell')) {
      const cells = Array.from(ref.current!.querySelectorAll<HTMLElement>('.flx-dp-cell'));
      const ix = cells.indexOf(tg);
      const mv = ({ ArrowLeft: rtl ? 1 : -1, ArrowRight: rtl ? -1 : 1, ArrowUp: -3, ArrowDown: 3 } as Record<string, number>)[e.key];
      if (mv && cells[ix + mv]) {
        e.preventDefault();
        tg.tabIndex = -1;
        cells[ix + mv]!.tabIndex = 0;
        cells[ix + mv]!.focus();
      }
      return;
    }
    if (!tg.classList.contains('flx-dp-day')) return;
    const step = ({ ArrowLeft: rtl ? 1 : -1, ArrowRight: rtl ? -1 : 1, ArrowUp: -7, ArrowDown: 7 } as Record<string, number>)[e.key];
    let nd: Date | null = null;
    if (step) nd = addDays(focus, step);
    else if (e.key === 'PageUp') nd = addMonths(focus, e.shiftKey ? -12 : -1);
    else if (e.key === 'PageDown') nd = addMonths(focus, e.shiftKey ? 12 : 1);
    else if (e.key === 'Home') nd = addDays(focus, -((focus.getDay() - ws + 7) % 7));
    else if (e.key === 'End') nd = addDays(focus, 6 - ((focus.getDay() - ws + 7) % 7));
    if (!nd) return;
    e.preventDefault();
    const n = nd;
    go(() => {
      setFocus(n);
      if (n.getMonth() !== view.getMonth() || n.getFullYear() !== view.getFullYear()) setView(new Date(n.getFullYear(), n.getMonth(), 1, 12));
    });
  };

  const td = ymd(today());
  const fd = ymd(focus);
  let body: React.ReactNode;
  if (mode === 'days') {
    const first = new Date(view.getFullYear(), view.getMonth(), 1, 12);
    const start = addDays(first, -((first.getDay() - ws + 7) % 7));
    const mn = parseDate(minD), mx = parseDate(maxD);
    body = (
      <>
        <div className="flx-dp-head">
          <span className="flx-dp-sels">
            <button type="button" className="flx-dp-sel" aria-label={s.pickMonth} onClick={() => go(() => setMode('months'))}>
              {cap(F.month.format(view), locale)}
              <Ico d={I_DOWN} s={14} />
            </button>
            <button type="button" className="flx-dp-sel" aria-label={s.pickYear} onClick={() => go(() => setMode('years'))}>
              {view.getFullYear()}
              <Ico d={I_DOWN} s={14} />
            </button>
          </span>
          <button type="button" className="flx-dp-nav" aria-label={s.prev} disabled={!!(mn && new Date(view.getFullYear(), view.getMonth(), 0, 12) < mn)} onClick={() => { const v = addMonths(view, -1); setView(v); setFocus(clampFocus(v)); }}>
            <Ico d={previousIcon} />
          </button>
          <button type="button" className="flx-dp-nav" aria-label={s.next} disabled={!!(mx && new Date(view.getFullYear(), view.getMonth() + 1, 1, 12) > mx)} onClick={() => { const v = addMonths(view, 1); setView(v); setFocus(clampFocus(v)); }}>
            <Ico d={nextIcon} />
          </button>
        </div>
        <div className="flx-dp-week" aria-hidden="true">
          {Array.from({ length: 7 }, (_, i) => (
            <span key={i}>{cap(F.wd.format(addDays(new Date(2024, 0, 1, 12), ((ws + 6) % 7) + i)), locale).replace('.', '')}</span>
          ))}
        </div>
        <div className="flx-dp-grid" role="grid">
          {Array.from({ length: 6 }, (_, r) => (
            <div key={r} className="flx-dp-row" role="row">
              {Array.from({ length: 7 }, (_, c) => {
                const x = addDays(start, r * 7 + c);
                const d = ymd(x);
                const cls = ['flx-dp-day', x.getMonth() !== view.getMonth() ? 'is-out' : '', d === td ? 'is-today' : '', d === day ? 'is-sel' : ''].filter(Boolean).join(' ');
                return (
                  <button
                    key={d}
                    type="button"
                    role="gridcell"
                    className={cls}
                    data-day={d}
                    tabIndex={d === fd ? 0 : -1}
                    aria-label={F.full.format(x)}
                    aria-selected={d === day || undefined}
                    disabled={!allowed(d)}
                    onClick={(e) => (e.detail > 1 && d === day ? commit(d) : go(() => select(d)))}
                  >
                    {x.getDate()}
                  </button>
                );
              })}
            </div>
          ))}
        </div>
      </>
    );
  } else if (mode === 'months') {
    body = (
      <>
        <div className="flx-dp-head">
          <span className="flx-dp-sels">
            <button type="button" className="flx-dp-sel is-on" onClick={() => go(() => setMode('days'))}>
              {s.month}
            </button>
            <button type="button" className="flx-dp-sel" aria-label={s.pickYear} onClick={() => go(() => setMode('years'))}>
              {view.getFullYear()}
              <Ico d={I_DOWN} s={14} />
            </button>
          </span>
          <button type="button" className="flx-dp-nav" aria-label={s.prevYear} onClick={() => setView(new Date(view.getFullYear() - 1, view.getMonth(), 1, 12))}>
            <Ico d={previousIcon} />
          </button>
          <button type="button" className="flx-dp-nav" aria-label={s.nextYear} onClick={() => setView(new Date(view.getFullYear() + 1, view.getMonth(), 1, 12))}>
            <Ico d={nextIcon} />
          </button>
        </div>
        <div className="flx-dp-cells">
          {Array.from({ length: 12 }, (_, i) => {
            const mx = new Date(view.getFullYear(), i, 1, 12);
            const sel = !!day && day.slice(0, 7) === ymd(mx).slice(0, 7);
            return (
              <button
                key={i}
                type="button"
                className={'flx-dp-cell' + (sel ? ' is-sel' : '') + (i === view.getMonth() ? ' is-focus' : '')}
                tabIndex={i === view.getMonth() ? 0 : -1}
                onClick={() => go(() => { setView(mx); setFocus(clampFocus(mx)); setMode('days'); })}
              >
                {cap(F.month.format(mx), locale)}
              </button>
            );
          })}
        </div>
      </>
    );
  } else {
    const y0 = view.getFullYear() - (view.getFullYear() % 12);
    body = (
      <>
        <div className="flx-dp-head">
          <span className="flx-dp-sels">
            <span className="flx-dp-range">
              {y0} – {y0 + 11}
            </span>
          </span>
          <button type="button" className="flx-dp-nav" aria-label={s.prev} onClick={() => setView(new Date(view.getFullYear() - 12, view.getMonth(), 1, 12))}>
            <Ico d={previousIcon} />
          </button>
          <button type="button" className="flx-dp-nav" aria-label={s.next} onClick={() => setView(new Date(view.getFullYear() + 12, view.getMonth(), 1, 12))}>
            <Ico d={nextIcon} />
          </button>
        </div>
        <div className="flx-dp-cells">
          {Array.from({ length: 12 }, (_, i) => {
            const yy = y0 + i;
            return (
              <button
                key={yy}
                type="button"
                className={'flx-dp-cell' + (day && +day.slice(0, 4) === yy ? ' is-sel' : '') + (yy === view.getFullYear() ? ' is-focus' : '')}
                tabIndex={yy === view.getFullYear() ? 0 : -1}
                onClick={() => go(() => { setView(new Date(yy, view.getMonth(), 1, 12)); setMode('months'); })}
              >
                {yy}
              </button>
            );
          })}
        </div>
      </>
    );
  }
  const quickShown = q.filter((o) => allowed(o.value));
  return (
    <div
      ref={ref}
      className={'flx-dp' + (bare ? ' is-bare' : '') + (sheet ? ' is-sheet' : '') + (q.length ? ' has-side' : '')}
      role="dialog"
      dir={rtl ? 'rtl' : undefined}
      aria-label={label ?? s.pick}
      data-theme={theme ?? th}
      style={style}
      onKeyDown={onKey}
    >
      {q.length > 0 && (
        <div className="flx-dp-side">
          {quickShown.map((o) => (
            <button key={o.label} type="button" className={'flx-dp-q' + (o.value === day ? ' is-on' : '')} onClick={() => commit(o.value)}>
              <span>{o.label}</span>
              {o.hint && <small>{o.hint}</small>}
            </button>
          ))}
          {!required && (
            <>
              <hr />
              <button type="button" className="flx-dp-q is-none" onClick={() => commit('')}>
                <span>{s.noDate}</span>
              </button>
            </>
          )}
        </div>
      )}
      <div className="flx-dp-main">
        {body}
        <div className="flx-dp-foot">
          <label className="flx-dp-in flx-dp-in--date">
            <Ico d={I_CAL} s={16} />
            <input
              type="text"
              inputMode="numeric"
              autoComplete="off"
              maxLength={10}
              placeholder={P.ph}
              aria-label={s.pick}
              value={typed}
              onChange={(e) => {
                setTyped(e.target.value);
                const d = P.fromText(e.target.value);
                if (d && allowed(d)) select(d);
              }}
              onBlur={() => {
                if (!P.fromText(typed)) setTyped(P.toText(day));
              }}
            />
          </label>
          {time && (
            <label className="flx-dp-in flx-dp-in--time">
              <Ico d={I_CLK} s={16} />
              <input
                type="text"
                inputMode="numeric"
                autoComplete="off"
                maxLength={5}
                placeholder="--:--"
                aria-label={s.time}
                value={tm}
                onChange={(e) => {
                  const raw = e.target.value.replace(/\D/g, '').slice(0, 4);
                  setTm(raw.length > 2 ? raw.slice(0, 2) + ':' + raw.slice(2) : raw);
                }}
                onBlur={() => {
                  const c = cleanTime(tm);
                  if (c || !tm) setTm(c);
                }}
              />
            </label>
          )}
        </div>
        <div className="flx-dp-acts">
          {!q.length && !required && (!!value || !!day) && (
            <button type="button" className="flx-dp-act" onClick={() => commit('')}>
              {s.clear}
            </button>
          )}
          <span className="flx-dp-sp" />
          <button type="button" className="flx-dp-act" onClick={() => onCancel?.()}>
            {s.cancel}
          </button>
          <button type="button" className="flx-dp-act is-main" onClick={apply}>
            {s.apply}
          </button>
        </div>
      </div>
    </div>
  );
}

type PickerProps = Common & {
  value: string;
  onChange: (v: string) => void;
  placeholder?: string;
  disabled?: boolean;
  id?: string;
  name?: string;
  'aria-label'?: string;
  className?: string;
  /** Takvim ikonu (varsayılan açık). */
  icon?: boolean;
};

/**
 * Tarih alanı: değeri sitenin dilinde gösteren düğme + takvim. Tarayıcının `<input type="date">`'i yerine.
 *   <DatePicker value={start} onChange={setStart} />
 *   <DatePicker value={at} onChange={setAt} time />            // tarih + saat
 *   <DatePicker value={birth} onChange={setBirth} quick={false} />  // hızlı seçim yok
 */
export function DatePicker({ value, onChange, placeholder, disabled, id, name, className, icon = true, locale = docLocale(), ...rest }: PickerProps) {
  const [open, setOpen] = useState(false);
  const [pos, setPos] = useState<{ left: number; top: number } | null>(null);
  const [sheet, setSheet] = useState(false);
  const btn = useRef<HTMLButtonElement>(null);
  const wrap = useRef<HTMLSpanElement>(null);
  const s = strings(locale);
  const close = useCallback((refocus: boolean) => {
    setOpen(false);
    setPos(null);
    if (refocus) btn.current?.focus({ preventScroll: true });
  }, []);
  useEffect(() => (open ? useOverlay.getState().pushOverlay() : undefined), [open]);
  const place = useCallback(() => {
    const b = btn.current;
    const p = wrap.current?.querySelector<HTMLElement>('.flx-dp');
    if (!b || !p) return;
    const isSheet = window.innerWidth <= 600;
    setSheet(isSheet);
    if (isSheet) return setPos({ left: 0, top: 0 });
    const r = b.getBoundingClientRect();
    const vw = window.innerWidth, vh = window.innerHeight, pw = p.offsetWidth, ph = p.offsetHeight;
    let top = r.bottom + 6;
    if (top + ph > vh - 8 && r.top - ph - 6 > 8) top = r.top - ph - 6;
    if (top + ph > vh - 8) top = Math.max(8, vh - ph - 8);
    setPos({ left: Math.min(Math.max(8, r.left), vw - pw - 8), top });
  }, []);
  useLayoutEffect(() => {
    if (open) place();
  }, [open, place]);
  useEffect(() => {
    if (!open) return;
    const down = (e: MouseEvent) => {
      if (!wrap.current?.contains(e.target as Node)) close(false);
    };
    const re = () => place();
    document.addEventListener('mousedown', down, true);
    window.addEventListener('resize', re);
    window.addEventListener('scroll', re, true);
    return () => {
      document.removeEventListener('mousedown', down, true);
      window.removeEventListener('resize', re);
      window.removeEventListener('scroll', re, true);
    };
  }, [open, close, place]);
  const text = value ? formatDate(value, locale) : placeholder ?? s.pick;
  // Flux Admin (body.flxa) içinde klasik ekrandaki tarih düğmesiyle aynı görünüm: .flxa-date (beyaz zemin, ok).
  const flxa = typeof document !== 'undefined' && document.body.classList.contains('flxa');
  return (
    <span ref={wrap} className="flx-dt-wrap">
      <button
        ref={btn}
        type="button"
        id={id}
        name={name}
        className={['flx-dt', flxa ? 'flxa-date' : '', !value ? 'is-empty' : '', open ? 'is-open' : '', className ?? ''].filter(Boolean).join(' ')}
        disabled={disabled}
        aria-haspopup="dialog"
        aria-expanded={open}
        aria-label={rest['aria-label'] ? `${rest['aria-label']}: ${text}` : undefined}
        onClick={() => (open ? close(true) : setOpen(true))}
        onKeyDown={(e) => {
          if (e.key === 'ArrowDown') {
            e.preventDefault();
            setOpen(true);
          } else if ((e.key === 'Backspace' || e.key === 'Delete') && !rest.required && value) {
            e.preventDefault();
            onChange('');
          }
        }}
      >
        {icon && (
          <span className="flx-dt__ico flxa-date-ico">
            <Ico d={I_CAL} />
          </span>
        )}
        <span className="flx-dt__val flxa-date-val">{text}</span>
      </button>
      {open && (
        <>
          {sheet && <div className="flx-dp-shade" data-theme={themeOf(btn.current)} onMouseDown={() => close(false)} />}
          <DateCalendar
            {...rest}
            locale={locale}
            value={value}
            sheet={sheet}
            label={rest['aria-label']}
            style={sheet ? undefined : pos ? { left: pos.left, top: pos.top } : { visibility: 'hidden', left: 0, top: 0 }}
            onPick={(v) => {
              close(true);
              if (v !== value) onChange(v);
            }}
            onCancel={() => close(true)}
          />
        </>
      )}
    </span>
  );
}
