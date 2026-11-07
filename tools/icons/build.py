#!/usr/bin/env python3
"""FluxWP package icons (gradient = default, pastel = optional).

Glyphs come from Lucide (ISC, lucide-static). Run from the repo root:
  cd tools/icons && bun install && cd ../.. && python3 tools/icons/build.py
Writes every package's assets/icon.svg (+ icon-pastel.svg), docs/logo.svg,
flx-setup/assets/icons/{,pastel/}<slug>.svg and flx-admin/assets/logos/{,pastel/}<slug>.svg.
Pass --extras <wp-extras checkout> to also write the wp-extras packages, --skip a,b to leave
those packages' own copies alone.
"""
import os, re, sys, json

HERE = os.path.dirname(os.path.abspath(__file__))
ICONS = os.path.join(HERE, 'node_modules', 'lucide-static', 'icons')
ROOT = os.path.abspath(os.path.join(HERE, '..', '..'))
EXTRAS = sys.argv[sys.argv.index('--extras') + 1] if '--extras' in sys.argv else ''

GROUPS = {
  # key: (label_tr, label_en, light, base, dark, tint, ink)
  'platform': ('Yönetim & platform', 'Platform', '#818CF8', '#4F46E5', '#312E81', '#EEF2FF', '#4338CA'),
  'system':   ('Bakım & altyapı', 'Maintenance', '#2DD4BF', '#0D9488', '#134E4A', '#F0FDFA', '#0F766E'),
  'comms':    ('İletişim', 'Communication', '#38BDF8', '#0284C7', '#0C4A6E', '#F0F9FF', '#0369A1'),
  'growth':   ('İçerik & büyüme', 'Content & growth', '#F472B6', '#DB2777', '#831843', '#FDF2F8', '#BE185D'),
  'commerce': ('Ticaret & bağış', 'Commerce & giving', '#FDBA74', '#EA580C', '#7C2D12', '#FFF7ED', '#C2410C'),
}

PKGS = [
  # slug, name, group, lucide glyph
  ('flx-admin', 'Flux Admin', 'platform', 'layout-dashboard'),
  ('flx-setup', 'FluxWP Panel', 'platform', 'blocks'),
  ('flx-roles', 'Flux Roles', 'platform', 'user-round-cog'),
  ('flx-app', 'Flux App', 'platform', 'monitor-smartphone'),
  ('flx-ai', 'Flux AI', 'platform', 'sparkles'),
  ('flx-tasks', 'Flux Tasks', 'platform', 'list-checks'),
  ('fluxwp', 'FluxWP (tema)', 'platform', '@mark'),
  ('flx-backup', 'Flux Backup', 'system', 'database-backup'),
  ('flx-speed', 'Flux Speed', 'system', 'gauge'),
  ('flx-migrate', 'Flux Migrate', 'system', 'arrow-right-left'),
  ('flx-recaptcha', 'Flux reCAPTCHA', 'system', 'shield-check'),
  ('flx-chat', 'Flux Chat', 'comms', 'message-circle-more'),
  ('flx-mail', 'Flux Mail', 'comms', 'mail'),
  ('flx-social', 'Flux Social', 'comms', 'share-2'),
  ('flx-forms', 'Flux Forms', 'comms', 'clipboard-list'),
  ('flx-seo', 'Flux SEO', 'growth', 'search-check'),
  ('flx-analytics', 'Flux Analytics', 'growth', 'chart-column'),
  ('flx-lang', 'Flux Lang', 'growth', 'languages'),
  ('flx-studio', 'Flux Studio', 'growth', 'clapperboard'),
  ('flx-crm', 'Flux CRM', 'growth', 'contact-round'),
  ('flx-wiki', 'Flux Wiki', 'growth', 'book-open-text'),
  ('flx-donate', 'Flux Donate', 'commerce', 'hand-heart'),
  ('flx-commerce', 'Flux Commerce', 'commerce', 'shopping-bag'),
  ('flx-certificates', 'Flux Certificates', 'commerce', 'award'),
  ('flx-qr-menu', 'Flux QR Menu', 'commerce', 'qr-code'),
  ('flx-menu-theme', 'Flux Menu Theme', 'commerce', 'utensils-crossed'),
  # wp-extras
  ('flx-qurban', 'Flux Qurban', 'commerce', 'beef'),
  ('flx-zakat', 'Flux Zakat', 'commerce', 'hand-coins'),
  ('flx-staff', 'Flux Staff', 'platform', 'users-round'),
  ('flx-tv-board', 'Flux TV Board', 'comms', 'tv-minimal'),
  ('flx-tv-remote', 'Flux TV Remote', 'comms', 'cast'),
  ('flx-preset-yesilturkiye', 'Yeşil Türkiye Preset', 'system', 'trees'),
  ('flx-preset-hayirkapisi', 'Hayır Kapısı Preset', 'commerce', 'door-open'),
  ('flx-preset-fluxwp', 'FluxWP Site Preset', 'platform', 'layout-template'),
]

def glyph_inner(name):
    s = open(os.path.join(ICONS, name + '.svg')).read()
    s = re.sub(r'<!--.*?-->', '', s, flags=re.S)
    return re.search(r'<svg[^>]*>(.*)</svg>', s, re.S).group(1).strip()

def mark(blocks, accent, s=1.0):
    # FluxWP "B · Modül" mark on a 64 grid
    r = [(9, 9), (25.5, 9), (9, 25.5), (25.5, 25.5), (9, 42)]
    out = ''.join(f'<rect x="{x}" y="{y}" width="13" height="13" rx="3.5" fill="{blocks}"/>' for x, y in r)
    return out + f'<rect x="44.2" y="6.8" width="13" height="13" rx="3.5" fill="{accent}"/>'

def glyph(name, color, size=132, sw=1.75):
    off = (256 - size) / 2
    k = size / 24
    if name == '@mark':
        k2 = size * 1.06 / 64
        o = (256 - 64 * k2) / 2
        return f'<g transform="translate({o:.1f} {o + 4:.1f}) scale({k2:.3f})">{mark(color, "#F59E0B")}</g>'
    return (f'<g transform="translate({off:.1f} {off:.1f}) scale({k:.3f})" fill="none" stroke="{color}" '
            f'stroke-width="{sw}" stroke-linecap="round" stroke-linejoin="round">{glyph_inner(name)}</g>')

SQ = 'M128 0C35 0 0 35 0 128s35 128 128 128 128-35 128-128S221 0 128 0Z'  # squircle

def style_a(slug, g, name):  # Degrade: group gradient, white glyph
    _, _, L, B, D, T, I = GROUPS[g]
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" role="img" aria-label="{slug}"><defs>'
            f'<linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{L}"/><stop offset=".5" stop-color="{B}"/><stop offset="1" stop-color="{D}"/></linearGradient>'
            f'<radialGradient id="h" cx=".2" cy=".1" r=".8"><stop offset="0" stop-color="#fff" stop-opacity=".28"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient></defs>'
            f'<path d="{SQ}" fill="url(#g)"/><path d="{SQ}" fill="url(#h)"/>'
            + (glyph(name, '#fff', 128, 1.9) if name != '@mark' else glyph(name, '#fff', 128)) + '</svg>')

def style_b(slug, g, name):  # Modül: night tile, group-coloured glyph + corner module
    _, _, L, B, D, T, I = GROUPS[g]
    body = glyph(name, L, 116, 1.9) if name != '@mark' else glyph(name, '#A5B4FC', 124)
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" role="img" aria-label="{slug}"><defs>'
            f'<linearGradient id="g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#262457"/><stop offset="1" stop-color="#15133A"/></linearGradient></defs>'
            f'<path d="{SQ}" fill="url(#g)"/>'
            + ('' if name == '@mark' else f'<rect x="186" y="30" width="40" height="40" rx="11" fill="{B}"/>')
            + body + '</svg>')

def style_c(slug, g, name):  # Pastel: light tint tile, coloured glyph
    _, _, L, B, D, T, I = GROUPS[g]
    body = glyph(name, I, 128, 1.75)
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" role="img" aria-label="{slug}">'
            f'<path d="{SQ}" fill="{T}"/><path d="{SQ}" fill="none" stroke="{L}" stroke-opacity=".55" stroke-width="5"/>'
            + body + '</svg>')


# Packages whose own copy is not rewritten (e.g. while they are being moved between repos): --skip a,b
SKIP = set(sys.argv[sys.argv.index('--skip') + 1].split(',')) if '--skip' in sys.argv else set()
STYLES = {'': style_a, 'pastel': style_c}

def put(path, data):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, 'w') as f:
        f.write(data + '\n')

for slug, nm, g, gl in PKGS:
    a, c = style_a(slug, g, gl), style_c(slug, g, gl)
    # central copies: updater (package list, Updates screen) and admin (menu, FluxApp)
    for base in ('plugins/flx-setup/assets/icons', 'plugins/flx-admin/assets/logos'):
        put(os.path.join(ROOT, base, slug + '.svg'), a)
        put(os.path.join(ROOT, base, 'pastel', slug + '.svg'), c)
    home = ''
    for root in (ROOT, EXTRAS):
        for kind in ('plugins', 'themes'):
            p = os.path.join(root, kind, slug) if root else ''
            if p and os.path.isdir(p):
                home = p
    if slug in SKIP:
        home = ''
    if not home or not os.path.isdir(home):
        continue
    if slug != 'fluxwp':  # the theme's README uses the FluxWP brand mark
        put(os.path.join(home, 'docs', 'logo.svg'), a)
    if os.path.isdir(os.path.join(home, 'assets')) and not slug.startswith('flx-preset-') and slug not in ('fluxwp', 'flx-menu-theme'):
        put(os.path.join(home, 'assets', 'icon.svg'), a)
        put(os.path.join(home, 'assets', 'icon-pastel.svg'), c)
