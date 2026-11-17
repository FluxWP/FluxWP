#!/usr/bin/env python3
"""React görev ekranının stilini (packages/modules/src/tasks/flxg.css) tools/app.css'ten üretir.

Tek kaynak app.css: tasarım değişikliği orada yapılır, sonra bu betik çalıştırılır.
- WP sayfa kabuğu kuralları (html.flxg-lock, #flxg-app, #wpadminbar) çıkarılır; wp-admin'de bunları admin.php verir.
- html.flxg-dark X  -> [data-theme="dark"] X   (React kökü ve katmanları data-theme taşır)
- html.flxg-task-page X -> .flxg.is-task-page X
- Sonuna yalnız React ekranına özgü birkaç kural eklenir.
Çalıştırma: python3 plugins/flx-tasks/tools/gen-react-css.py
"""
import os, re

HERE = os.path.dirname(os.path.abspath(__file__))
PLUG = os.path.dirname(HERE)
REPO = os.path.dirname(os.path.dirname(PLUG))
src = open(os.path.join(PLUG, 'tools/app.css'), encoding='utf-8').read().split('\n')

out, skip = [], False
for line in src:
    s = line.strip()
    if s.startswith('/* ---------------- WP admin içinde tam ekran'):
        skip = True
        continue
    if skip:
        if s.startswith('.flxg *,'):
            skip = False
        else:
            continue
    if '#flxg-app' in s or '#wpadminbar' in s or '#adminmenuwrap' in s:
        continue
    if re.match(r'^html\.flxg-dark body\b', s):
        continue
    line = re.sub(r'^(\s*)html\.flxg-dark ', r'\1[data-theme="dark"] ', line)
    line = re.sub(r'^(\s*)html\.flxg-task-page ', r'\1.flxg.is-task-page ', line)
    out.append(line)

css = '\n'.join(out).rstrip('\n')
head = "/* Otomatik üretildi: plugins/flx-tasks/tools/gen-react-css.py, kaynak plugins/flx-tasks/tools/app.css. Elle değiştirmeyin. */\n"
extra = """

/* ---------------- Yalnız React ekranı (FluxApp + wp-admin) ---------------- */
.flxg.flxg-flux { height: 100%; min-height: 0; position: relative; background: var(--bg); }
.flxg-doc-host { display: contents; }
.flxg-dnd-ghost { pointer-events: none; opacity: .92; box-shadow: var(--shadow-lg, 0 10px 30px rgba(0,0,0,.18)); border-radius: 8px; background: var(--panel); }
.flxg-dnd-ghost .flxg-row { background: var(--panel); }
.is-drop-over { background: color-mix(in srgb, var(--accent) 6%, transparent); }
.is-drag-hidden { opacity: .35; }
"""
open(os.path.join(REPO, 'packages/modules/src/tasks/flxg.css'), 'w', encoding='utf-8').write(head + css + extra)
print('ok', len(out))
