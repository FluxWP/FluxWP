/// <reference types="vite/client" />
// Menü ikonu, WP'deki Flux Admin menüsü gibi: FluxWP paketlerinde paketin renkli logosu, diğerlerinde
// renkli zeminli yuvarlak kare içinde ikon. Sunucu icon_url verirse o kullanılır.
import { Icon } from './Icon';

const raw = import.meta.glob('./menu-icons/*.svg', { eager: true, query: '?raw', import: 'default' }) as Record<string, string>;
const logos: Record<string, string> = {};
for (const [path, svg] of Object.entries(raw)) {
  logos[path.replace(/^.*\/(.+)\.svg$/, '$1')] = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
}

/** Menü slug'ı → paket logosu. */
const pkg: [RegExp, string][] = [
  [/^flx-chat/, 'flx-chat'], [/^flx-tasks/, 'flx-tasks'], [/^flxb|^flx-donate/, 'flx-donate'], [/^flxs$|^flx-certificates/, 'flx-certificates'],
  [/^emsfb$|^flx-forms/i, 'flx-forms'], [/^flxi(-|$)|^flx-mail/, 'flx-mail'], [/^flx-setup/, 'flx-setup'], [/^flx-backup/, 'flx-backup'],
  [/^flx-speed/, 'flx-speed'], [/^flx-analytics/, 'flx-analytics'], [/^flx-social/, 'flx-social'], [/^flx-ai/, 'flx-ai'], [/^flx-studio/, 'flx-studio'],
  [/^flxseo|^flx-seo/, 'flx-seo'], [/^flx-qr/, 'flx-qr-menu'], [/^flx-tv-remote/, 'flx-tv-remote'], [/^flx-tv-board/, 'flx-tv-board'],
  [/^flxapp|^flx-app/, 'flx-app'], [/^flx-lang/, 'flx-lang'], [/^flx-roles|^flxr-/, 'flx-roles'], [/^flxa-|^flx-admin/, 'flx-admin'],
];

/** Çekirdek / diğer menüler: zemin tonu (ikon rengi). */
const tones: [RegExp, string][] = [
  [/^index\.php/, '#16A34A'], [/post_type=proje/, '#16A34A'], [/post_type=etkinlik/, '#EA580C'], [/^upload\.php/, '#0891B2'],
  [/post_type=product|^woocommerce$/, '#9333EA'], [/marketing/, '#F97316'], [/^wc-admin/, '#7C3AED'], [/litespeed/, '#F97316'],
];

export function MenuIcon({ slug = '', icon, iconUrl, size = 30 }: { slug?: string; icon?: string; iconUrl?: string; size?: number }) {
  const src = iconUrl || (/^(https?:|data:image\/)/.test(icon ?? '') ? icon : '') || logos[pkg.find(([re]) => re.test(slug))?.[1] ?? ''];
  if (src) return <img className="menu-ic img" src={src} width={size} height={size} alt="" aria-hidden draggable={false} />;
  const tone = tones.find(([re]) => re.test(slug))?.[1];
  return (
    <span className={'menu-ic' + (tone ? ' toned' : '')} style={{ width: size, height: size, ...(tone ? { ['--tone' as string]: tone } : {}) }} aria-hidden>
      <Icon name={icon} slug={slug} size={Math.round(size * 0.55)} />
    </span>
  );
}
