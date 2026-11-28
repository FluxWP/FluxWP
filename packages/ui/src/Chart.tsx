/**
 * Ortak etkileşimli grafik (Chart.js 4, canvas). Modüller kendi grafik kodunu yazmaz, bunu kullanır.
 * Hover tooltip, legend'dan seri aç/kapa, tıklama (onPoint), koyu/açık tema ve --flux-* token'ları.
 * Yalnız gereken Chart.js parçaları kaydedilir; kütüphane grafik kullanan ekranların parçasına düşer.
 */
import { useEffect, useMemo, useRef } from 'react';
import {
  ArcElement,
  BarController,
  BarElement,
  BubbleController,
  CategoryScale,
  Chart as ChartJS,
  DoughnutController,
  Filler,
  Legend,
  LinearScale,
  LineController,
  LineElement,
  PieController,
  PointElement,
  PolarAreaController,
  RadarController,
  RadialLinearScale,
  ScatterController,
  Tooltip,
  type ChartConfiguration,
  type ChartDataset,
  type ChartType,
} from 'chart.js';

ChartJS.register(
  ArcElement,
  BarController,
  BarElement,
  BubbleController,
  CategoryScale,
  DoughnutController,
  Filler,
  Legend,
  LinearScale,
  LineController,
  LineElement,
  PieController,
  PointElement,
  PolarAreaController,
  RadarController,
  RadialLinearScale,
  ScatterController,
  Tooltip,
);

export type ChartKind = 'bar' | 'line' | 'area' | 'doughnut' | 'pie' | 'radar' | 'polar' | 'scatter' | 'bubble';

export interface ChartPoint {
  x: number;
  y: number;
  /** bubble yarıçapı (px). */
  r?: number;
}

export interface ChartSeries {
  label: string;
  data: Array<number | null> | ChartPoint[];
  /** CSS rengi ya da `var(--flux-x, #yedek)`. Boşsa ortak paletten sırayla. */
  color?: string;
  /** Karma grafik: bar grafiğinde bir seriyi çizgi/alan çiz. */
  kind?: 'bar' | 'line' | 'area';
  /** Aynı stack adındaki seriler üst üste biner (stacked:true hepsini birleştirir). */
  stack?: string;
  /** İkinci (sağ) eksen. */
  axis?: 'y' | 'y2';
  /** Seri başta gizli (legend'dan açılır). */
  hidden?: boolean;
  /** Kesikli çizgi (ör. kota/hedef). Noktasız, dolgusuz çizilir. */
  dashed?: boolean;
}

export interface ChartProps {
  kind: ChartKind;
  labels?: string[];
  series: ChartSeries[];
  /** Bar/alan serileri üst üste. */
  stacked?: boolean;
  /** Yatay bar. */
  horizontal?: boolean;
  /** Değer biçimi (tooltip + eksen). */
  format?: (v: number, series: number) => string;
  /** Değer ekseni biçimi (kısa: 1,2 B); yoksa format. */
  formatTick?: (v: number) => string;
  /** Sağ eksen biçimi; yoksa format. */
  format2?: (v: number) => string;
  /** Kategori/tooltip başlığı biçimi (ör. tarih kısaltma). */
  formatLabel?: (label: string, index: number) => string;
  /** Eksendeki kısa etiket; yoksa formatLabel. */
  tickLabel?: (label: string, index: number) => string;
  height?: number;
  /** Varsayılan: birden çok seri ya da pasta/halka/polar varsa göster. */
  legend?: boolean;
  /** Pasta/halka dilim renkleri (sırayla); seri rengi yerine. */
  colors?: string[];
  /** Bir noktaya/dilime/çubuğa tıklama. */
  onPoint?: (index: number, series: number) => void;
  /** Ekran okuyucu metni. */
  label?: string;
  className?: string;
  /** Y ekseni 0'dan başlasın (varsayılan true). */
  zero?: boolean;
  /** Y ekseni önerilen üst sınırı (veri hep 0 iken eksen anlamlı kalsın). */
  max?: number;
  /** Çizgi noktaları (varsayılan: 40 noktaya kadar açık). */
  points?: boolean;
}

/** Ortak palet: tema token'ları, sonra tarafsız yedekler. */
export const chartPalette = [
  'var(--flux-primary, #4F46E5)',
  'var(--flux-info, #1D5FA8)',
  'var(--flux-warning, #A04A06)',
  'var(--flux-success, #1F7A4D)',
  'var(--flux-danger, #B42318)',
  'var(--flux-accent, #7C3AED)',
  '#0EA5A4',
  '#DB2777',
  '#65A30D',
  '#64748B',
];

/**
 * Herhangi bir CSS rengi (`var(--x)`, `color-mix(...)`, ad) → canvas'ın okuyacağı rgb().
 * Kabın içinde gizli bir öğeye uygulanıp hesaplanan renk okunur; CSS değişkenleri kabın temasından gelir.
 */
export function resolveColor(el: Element, c: string): string {
  if (/^#|^rgba?\(/i.test(c.trim())) return c;
  const host = el.parentElement ?? el;
  const probe = document.createElement('span');
  probe.style.cssText = 'position:absolute;width:0;height:0;overflow:hidden;visibility:hidden';
  probe.style.color = c;
  host.appendChild(probe);
  const v = getComputedStyle(probe).color;
  probe.remove();
  return normColor(v) || '#888';
}

/** "color(srgb r g b / a)" (color-mix sonucu) → rgba(); diğerleri aynen. */
function normColor(v: string): string {
  const m = /^color\(srgb\s+([\d.e-]+)\s+([\d.e-]+)\s+([\d.e-]+)(?:\s*\/\s*([\d.e-]+))?\)$/.exec(v.trim());
  if (!m) return v;
  const ch = (x: string) => Math.round(Math.max(0, Math.min(1, Number(x))) * 255);
  return `rgba(${ch(m[1]!)},${ch(m[2]!)},${ch(m[3]!)},${m[4] ?? 1})`;
}

/** #rgb/#rrggbb/rgb() → saydamlıklı renk; çözülemezse aynen. */
export function alphaColor(c: string, a: number): string {
  const h = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(c);
  if (h) {
    const s = h[1]!.length === 3 ? h[1]!.replace(/./g, (x) => x + x) : h[1]!;
    const n = parseInt(s, 16);
    return `rgba(${(n >> 16) & 255},${(n >> 8) & 255},${n & 255},${a})`;
  }
  const r = /^rgba?\(([^)]+)\)$/.exec(c);
  if (r) {
    const p = r[1]!.split(/[\s,/]+/).filter(Boolean).slice(0, 3);
    return `rgba(${p.join(',')},${a})`;
  }
  return c;
}

const NARROW = 440;

const ROUND = new Set<ChartKind>(['doughnut', 'pie', 'polar']);

interface Theme {
  text: string;
  muted: string;
  grid: string;
  surface: string;
  border: string;
  font: string;
}

function readTheme(el: Element): Theme {
  return {
    text: resolveColor(el, 'var(--flux-text, #111827)'),
    muted: resolveColor(el, 'var(--flux-text-muted, #4B5563)'),
    grid: alphaColor(resolveColor(el, 'var(--flux-border, #E6E0D6)'), 0.8),
    surface: resolveColor(el, 'var(--flux-surface, #FFFFFF)'),
    border: resolveColor(el, 'var(--flux-border, #E6E0D6)'),
    font: getComputedStyle(el).fontFamily || 'system-ui, sans-serif',
  };
}

function build(el: Element, p: ChartProps, th: Theme): ChartConfiguration {
  const round = ROUND.has(p.kind);
  const baseType: ChartType = p.kind === 'area' ? 'line' : p.kind === 'polar' ? 'polarArea' : p.kind;
  const fmt = (v: number, s: number) => (p.format ? p.format(v, s) : v.toLocaleString());
  const col = (s: ChartSeries, i: number) => resolveColor(el, s.color || chartPalette[i % chartPalette.length]!);
  const many = p.series.length > 1;
  const reduce = typeof matchMedia === 'function' && matchMedia('(prefers-reduced-motion: reduce)').matches;
  const hasY2 = p.series.some((s) => s.axis === 'y2');
  // Dar kapta (mobil) pasta/halka legend'i alta iner.
  const narrow = (el.parentElement?.clientWidth ?? 999) < NARROW;

  const datasets = p.series.map((s, i): ChartDataset => {
    const c = col(s, i);
    const kind = s.kind ?? (p.kind === 'area' ? 'area' : p.kind === 'line' ? 'line' : undefined);
    if (round) {
      const slices = (s.data as number[]).map((_, j) => resolveColor(el, (p.colors ?? chartPalette)[j % (p.colors ?? chartPalette).length]!));
      return {
        label: s.label,
        data: s.data as number[],
        backgroundColor: p.kind === 'polar' ? slices.map((x) => alphaColor(x, 0.7)) : slices,
        borderColor: th.surface,
        borderWidth: 2,
        hoverOffset: 6,
      } as ChartDataset;
    }
    if (kind === 'line' || kind === 'area' || p.kind === 'radar') {
      return {
        type: p.kind === 'radar' ? undefined : 'line',
        label: s.label,
        data: s.data,
        borderColor: c,
        backgroundColor: kind === 'area' || p.kind === 'radar' ? alphaColor(c, 0.18) : c,
        fill: s.dashed ? false : kind === 'area' || p.kind === 'radar' ? (p.stacked && i > 0 ? '-1' : 'origin') : false,
        tension: 0.3,
        borderWidth: s.dashed ? 1.5 : 2,
        borderDash: s.dashed ? [6, 4] : undefined,
        pointRadius: s.dashed || p.points === false || (p.points !== true && (s.data as unknown[]).length > 40) ? 0 : 2.5,
        pointHoverRadius: 5,
        pointBackgroundColor: c,
        stack: s.dashed ? '_' + i : s.stack,
        yAxisID: p.kind === 'radar' ? undefined : s.axis === 'y2' ? 'y2' : 'y',
        hidden: s.hidden,
        spanGaps: true,
        // Karma grafikte çizgi çubukların üstünde çizilir.
        order: p.kind === 'bar' ? 0 : undefined,
      } as ChartDataset;
    }
    if (p.kind === 'scatter' || p.kind === 'bubble') {
      return { label: s.label, data: s.data, backgroundColor: alphaColor(c, 0.6), borderColor: c, hidden: s.hidden } as ChartDataset;
    }
    return {
      type: 'bar',
      label: s.label,
      data: s.data,
      backgroundColor: c,
      hoverBackgroundColor: alphaColor(c, 0.8),
      borderRadius: p.stacked ? 2 : 4,
      borderSkipped: p.stacked ? false : 'start',
      maxBarThickness: 36,
      order: 1,
      stack: s.stack ?? (p.stacked ? 'all' : undefined),
      yAxisID: p.horizontal ? undefined : s.axis === 'y2' ? 'y2' : 'y',
      hidden: s.hidden,
    } as ChartDataset;
  });

  const tick = { color: th.muted, font: { size: 11, family: th.font } };
  const catTick = {
    ...tick,
    autoSkip: true,
    maxRotation: 0,
    callback(this: { getLabelForValue(v: number): string }, v: string | number, i: number) {
      const l = this.getLabelForValue(Number(v));
      return p.tickLabel ? p.tickLabel(l, i) : p.formatLabel ? p.formatLabel(l, i) : l;
    },
  };
  const valTick = { ...tick, maxTicksLimit: 6, callback: (v: string | number) => (p.formatTick ? p.formatTick(Number(v)) : fmt(Number(v), 0)) };
  const cartesian = !round && p.kind !== 'radar';
  const num = p.kind === 'scatter' || p.kind === 'bubble';
  const stacked = !!p.stacked;
  const cat = p.horizontal ? 'y' : 'x';
  const val = p.horizontal ? 'x' : 'y';

  const scales: Record<string, unknown> = {};
  if (cartesian) {
    scales[cat] = {
      type: num ? 'linear' : 'category',
      stacked,
      grid: { display: num, color: th.grid, drawTicks: false },
      border: { color: th.border },
      ticks: num ? valTick : catTick,
    };
    scales[val] = {
      type: 'linear',
      stacked,
      beginAtZero: p.zero !== false,
      ...(p.max !== undefined ? { suggestedMax: p.max } : {}),
      grid: { color: th.grid, drawTicks: false },
      border: { display: false },
      ticks: valTick,
    };
    if (hasY2 && !p.horizontal) {
      scales.y2 = {
        type: 'linear',
        position: 'right',
        beginAtZero: p.zero !== false,
        grid: { drawOnChartArea: false },
        border: { display: false },
        ticks: { ...tick, maxTicksLimit: 6, callback: (v: string | number) => (p.format2 ?? ((x: number) => fmt(x, 1)))(Number(v)) },
      };
    }
  } else if (p.kind === 'radar' || p.kind === 'polar') {
    scales.r = {
      beginAtZero: true,
      grid: { color: th.grid },
      angleLines: { color: th.grid },
      pointLabels: { color: th.muted, font: { size: 11, family: th.font } },
      ticks: { ...tick, backdropColor: 'transparent', maxTicksLimit: 5, callback: (v: string | number) => fmt(Number(v), 0) },
    };
  }

  return {
    type: baseType,
    data: { labels: p.labels, datasets },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: reduce ? false : { duration: 350 },
      indexAxis: p.horizontal ? 'y' : 'x',
      cutout: p.kind === 'doughnut' ? '62%' : undefined,
      interaction: round || num ? { mode: 'nearest', intersect: true } : { mode: 'index', intersect: false },
      layout: { padding: { top: 4 } },
      onResize: (c, size) => {
        if (!round) return;
        const want = size.width < NARROW ? 'bottom' : 'right';
        const lg = c.options.plugins?.legend;
        if (lg && lg.position !== want) {
          lg.position = want;
          c.update('none');
        }
      },
      onHover: (e, els) => {
        const t = e.native?.target as HTMLElement | undefined;
        if (t && p.onPoint) t.style.cursor = els.length ? 'pointer' : 'default';
      },
      onClick: (_e, els) => {
        const h = els[0];
        if (h && p.onPoint) p.onPoint(h.index, h.datasetIndex);
      },
      scales,
      plugins: {
        legend: {
          display: p.legend ?? (many || round),
          position: round ? (narrow ? 'bottom' : 'right') : 'top',
          align: round ? 'center' : 'end',
          labels: { sort: (a, b) => (a.datasetIndex ?? 0) - (b.datasetIndex ?? 0), color: th.text, boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'rectRounded', padding: 12, font: { size: 12, family: th.font } },
        },
        tooltip: {
          backgroundColor: th.surface,
          titleColor: th.text,
          bodyColor: th.text,
          footerColor: th.muted,
          borderColor: th.border,
          borderWidth: 1,
          padding: 10,
          cornerRadius: 8,
          boxPadding: 4,
          usePointStyle: true,
          itemSort: (a, b) => a.datasetIndex - b.datasetIndex,
          titleFont: { family: th.font, weight: 600 },
          bodyFont: { family: th.font },
          footerFont: { family: th.font, weight: 500 },
          callbacks: {
            title: (items) => {
              const it = items[0];
              if (!it || num) return '';
              const l = String(it.label ?? '');
              return round ? '' : p.formatLabel ? p.formatLabel(l, it.dataIndex) : l;
            },
            label: (it) => {
              const raw = it.raw as number | ChartPoint;
              const v = typeof raw === 'number' ? raw : (raw?.y ?? 0);
              const name = round ? String(it.label ?? '') : it.dataset.label;
              const f = p.series[it.datasetIndex]?.axis === 'y2' && p.format2 ? p.format2(v) : fmt(v, it.datasetIndex);
              if (round) {
                const sum = (it.dataset.data as number[]).reduce((a, b) => a + (Number(b) || 0), 0);
                return ` ${name}: ${f}${sum ? ` (%${Math.round((v / sum) * 100)})` : ''}`;
              }
              return ` ${name ? name + ': ' : ''}${f}`;
            },
            footer: (items) => {
              const parts = items.filter((it) => !p.series[it.datasetIndex]?.dashed);
              if (!stacked || parts.length < 2) return '';
              const sum = parts.reduce((a, it) => a + (Number(it.raw) || 0), 0);
              return fmt(sum, -1);
            },
          },
        },
      },
    },
  } as ChartConfiguration;
}

/** Tema değişince (data-theme, inline --flux-* stili, sınıf, sistem modu) yeniden renklendirir. */
function useThemeWatch(ref: { current: HTMLElement | null }, cb: () => void) {
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const obs = new MutationObserver(cb);
    const opts = { attributes: true, attributeFilter: ['style', 'class', 'data-theme'] };
    obs.observe(document.documentElement, opts);
    if (document.body) obs.observe(document.body, opts);
    const root = el.closest('.flux-root') ?? el.closest('[data-theme]');
    if (root) obs.observe(root, opts);
    const mq = typeof matchMedia === 'function' ? matchMedia('(prefers-color-scheme: dark)') : null;
    mq?.addEventListener?.('change', cb);
    return () => {
      obs.disconnect();
      mq?.removeEventListener?.('change', cb);
    };
  }, [ref, cb]);
}

export function Chart(props: ChartProps) {
  const box = useRef<HTMLDivElement | null>(null);
  const canvas = useRef<HTMLCanvasElement | null>(null);
  const chart = useRef<ChartJS | null>(null);
  const last = useRef(props);
  last.current = props;
  const height = props.height ?? 220;

  // Veri değişince yeniden çiz (yeni nesne her render'da gelir; içerik anahtarıyla karşılaştır).
  const key = useMemo(
    () => JSON.stringify([props.kind, props.labels, props.series, props.stacked, props.horizontal, props.legend, props.colors, props.zero, props.max, props.points]),
    [props.kind, props.labels, props.series, props.stacked, props.horizontal, props.legend, props.colors, props.zero, props.max, props.points],
  );

  const draw = useMemo(
    () => () => {
      const c = canvas.current;
      if (!c) return;
      const cfg = build(c, last.current, readTheme(c));
      const cur = chart.current;
      if (cur && (cur.config as { type?: string }).type === (cfg as { type?: string }).type) {
        // Gizli seriler korunur (legend'dan kapatılan seri yeniden açılmasın).
        const hidden = cur.data.datasets.map((_, i) => !cur.isDatasetVisible(i));
        cur.data = cfg.data;
        cur.options = cfg.options!;
        hidden.forEach((h, i) => h && i < cfg.data.datasets.length && cur.setDatasetVisibility(i, false));
        cur.update();
        return;
      }
      cur?.destroy();
      chart.current = new ChartJS(c, cfg);
    },
    [],
  );

  useEffect(() => {
    draw();
  }, [key, draw]);

  useEffect(
    () => () => {
      chart.current?.destroy();
      chart.current = null;
    },
    [],
  );

  const retheme = useMemo(
    () => () => {
      const c = canvas.current;
      const cur = chart.current;
      if (!c || !cur) return;
      const cfg = build(c, last.current, readTheme(c));
      cur.data.datasets.forEach((d, i) => {
        const n = cfg.data.datasets[i] as unknown as Record<string, unknown> | undefined;
        if (!n) return;
        for (const k of ['backgroundColor', 'borderColor', 'hoverBackgroundColor', 'pointBackgroundColor']) if (k in n) (d as unknown as Record<string, unknown>)[k] = n[k];
      });
      cur.options = cfg.options!;
      // 'none' modu nokta renklerini önbellekten okur; normal güncelleme renkleri yumuşakça geçirir.
      cur.update();
    },
    [],
  );
  useThemeWatch(box, retheme);

  return (
    <div ref={box} className={'flx-chart' + (props.className ? ' ' + props.className : '')} style={{ height }}>
      <canvas ref={canvas} role="img" aria-label={props.label ?? props.series.map((s) => s.label).join(', ')} />
    </div>
  );
}
