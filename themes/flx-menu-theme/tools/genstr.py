#!/usr/bin/env python3
"""Tema çeviri dosyalarını üretir: languages/flx-menu-theme.pot, en_US.po, en_US.mo, en_US.l10n.php.
PHP __('...', 'flx-menu-theme') çağrıları + style.css Description toplanır; İngilizceler tools/en.json'dan."""
import json, os, re, struct
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
RE = re.compile(r"""\b(?:__|_e|esc_html__|esc_attr__|esc_html_e|esc_attr_e)\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'flx-menu-theme'""")
keys = []
for d, _, fs in os.walk(ROOT):
    if '/tools' in d: continue
    for f in sorted(fs):
        if f.endswith('.php') and not f.endswith('.l10n.php'):
            for m in RE.finditer(open(os.path.join(d, f), encoding='utf-8').read()):
                s = re.sub(r"\\(.)", r"\1", m.group(1))
                if s not in keys: keys.append(s)
desc = re.search(r"^Description:\s*(.+)$", open(os.path.join(ROOT, 'style.css'), encoding='utf-8').read(), re.M).group(1).strip()
keys += [k for k in ('Flux Menu Theme', desc) if k not in keys]
en = json.load(open(os.path.join(ROOT, 'tools/en.json'), encoding='utf-8'))
missing = [k for k in keys if k not in en]
if missing: raise SystemExit('Eksik çeviri: %r' % missing)
def q(s): return '"' + s.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n') + '"'
def pq(s): return "'" + s.replace('\\', '\\\\').replace("'", "\\'") + "'"
head = 'msgid ""\nmsgstr ""\n"Content-Type: text/plain; charset=UTF-8\\n"\n"Project-Id-Version: Yesil Fun\\n"\n'
L = os.path.join(ROOT, 'languages')
open(os.path.join(L, 'flx-menu-theme.pot'), 'w', encoding='utf-8').write(head + ''.join('\nmsgid %s\nmsgstr ""\n' % q(k) for k in keys))
open(os.path.join(L, 'en_US.po'), 'w', encoding='utf-8').write(head + '"Language: en_US\\n"\n' + ''.join('\nmsgid %s\nmsgstr %s\n' % (q(k), q(en[k])) for k in keys))
open(os.path.join(L, 'en_US.l10n.php'), 'w', encoding='utf-8').write("<?php\nreturn array(\n\t'language' => 'en_US',\n\t'messages' => array(\n" + ''.join("\t\t%s => %s,\n" % (pq(k), pq(en[k])) for k in keys) + "\t),\n);\n")
e = {k: en[k] for k in keys}
e[''] = 'Content-Type: text/plain; charset=UTF-8\nLanguage: en_US\n'
ks = sorted(e); ids = b''.join(k.encode() + b'\0' for k in ks); strs = b''.join(e[k].encode() + b'\0' for k in ks)
n = len(ks); ko = 28 + 16 * n; vo = ko + len(ids); kt, vt, a, b = [], [], 0, 0
for k in ks:
    kb, vb = k.encode(), e[k].encode(); kt += [len(kb), ko + a]; vt += [len(vb), vo + b]; a += len(kb) + 1; b += len(vb) + 1
open(os.path.join(L, 'en_US.mo'), 'wb').write(struct.pack('Iiiiiii', 0x950412de, 0, n, 28, 28 + n * 8, 0, 0) + struct.pack('%di' % len(kt), *kt) + struct.pack('%di' % len(vt), *vt) + ids + strs)
print('%d dize' % len(keys))
