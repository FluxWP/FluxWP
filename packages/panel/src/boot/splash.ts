// Loading screen outside React. index.html draws it before any script runs (#boot-splash); the panel
// keeps it up while accounts and the site's screens load, and shows it again on a site switch.
const MIN_MS = 450;
const shownAt = { t: typeof performance !== 'undefined' ? performance.now() : 0 };
let hideTimer: ReturnType<typeof setTimeout> | null = null;

function el(): HTMLElement | null {
  return typeof document === 'undefined' ? null : document.getElementById('boot-splash');
}

export interface SplashState {
  /** Site logo/icon URL; empty keeps the app icon. */
  logo?: string;
  title?: string;
  status?: string;
  /** 0..1; undefined = indeterminate bar. */
  progress?: number;
}

export function splashVisible(): boolean {
  const e = el();
  return !!e && !e.classList.contains('gone');
}

export function showSplash(s: SplashState = {}): void {
  const e = el();
  if (!e) return;
  if (hideTimer) clearTimeout(hideTimer), (hideTimer = null);
  if (e.classList.contains('gone')) {
    shownAt.t = performance.now();
    e.classList.remove('gone', 'out');
  }
  e.classList.remove('out');
  updateSplash(s);
}

export function updateSplash(s: SplashState): void {
  const e = el();
  if (!e) return;
  const img = e.querySelector<HTMLImageElement>('.bs-site');
  if (img && s.logo !== undefined) {
    if (s.logo) {
      if (img.getAttribute('src') !== s.logo) img.src = s.logo;
      e.classList.add('has-site');
    } else e.classList.remove('has-site');
  }
  const title = e.querySelector('.bs-title');
  if (title && s.title !== undefined) title.textContent = s.title;
  const status = e.querySelector('.bs-status');
  if (status && s.status !== undefined) status.textContent = s.status;
  const bar = e.querySelector<HTMLElement>('.bs-bar');
  if (bar && 'progress' in s) {
    if (typeof s.progress === 'number') {
      bar.classList.add('det');
      bar.style.setProperty('--p', String(Math.max(0.04, Math.min(1, s.progress))));
    } else bar.classList.remove('det');
  }
}

/** Fades the splash out (kept at least MIN_MS on screen so it does not flash). */
export function hideSplash(): void {
  const e = el();
  if (!e || e.classList.contains('gone')) return;
  const wait = Math.max(0, MIN_MS - (performance.now() - shownAt.t));
  if (hideTimer) clearTimeout(hideTimer);
  hideTimer = setTimeout(() => {
    e.classList.add('out');
    hideTimer = setTimeout(() => {
      e.classList.add('gone');
      hideTimer = null;
    }, 260);
  }, wait);
}
