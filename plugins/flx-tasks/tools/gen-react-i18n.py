#!/usr/bin/env python3
"""React görev ekranının sözlüklerini klasik çevirilerden üretir.

- packages/modules/src/tasks/<dil>.json : İngilizce anahtar -> dil (React kaynağındaki tt() + mevcut anahtarlar)
- packages/modules/src/tasks/aliases.json : Türkçe kaynak -> İngilizce (js-strings.php)
- packages/modules/src/tasks/legacy/<dil>.json : core.js + docs.js için Türkçe kaynak -> dil
Kaynak: includes/js-strings.php (TR -> EN) + languages/messages.<locale>.json (EN -> dil).
Çalıştırma: python3 plugins/flx-tasks/tools/gen-react-i18n.py
"""
import json, os, re

HERE = os.path.dirname(os.path.abspath(__file__))
PLUG = os.path.dirname(HERE)
REPO = os.path.dirname(os.path.dirname(PLUG))
SRC = os.path.join(REPO, 'packages/modules/src/tasks')
LANGS = {'en': 'en', 'tr': 'tr_TR', 'de': 'de_DE', 'es': 'es_ES', 'fr': 'fr_FR', 'pt-br': 'pt_BR', 'ja': 'ja', 'zh': 'zh_CN', 'ar': 'ar', 'ru': 'ru_RU'}


def unq(s):
    return s.replace("\\'", "'").replace('\\\\', '\\')


php = open(os.path.join(PLUG, 'includes/js-strings.php'), encoding='utf-8').read()
pairs = {}
for m in re.finditer(r"^\t\t'((?:[^'\\]|\\.)*)' => __\( '((?:[^'\\]|\\.)*)', 'flx-tasks' \),$", php, re.M):
    pairs[unq(m.group(1))] = unq(m.group(2))

TT = re.compile(r"""\btt\(\s*(?:"((?:[^"\\]|\\.)*)"|'((?:[^'\\]|\\.)*)')""")
used = set()
for root in (SRC, os.path.join(REPO, 'packages/runtime/src/modules/tasks')):
    for f in os.listdir(root):
        if f.endswith(('.ts', '.tsx')):
            for m in TT.finditer(open(os.path.join(root, f), encoding='utf-8').read()):
                used.add(json.loads('"' + m.group(1) + '"') if m.group(1) is not None else unq(m.group(2)))

old = {k: json.load(open(os.path.join(SRC, k + '.json'), encoding='utf-8')) for k in LANGS}
aliases = json.load(open(os.path.join(SRC, 'aliases.json'), encoding='utf-8'))
aliases.update(pairs)
used = {aliases.get(k, k) for k in used} | set(old['en'])
msgs = {}
for k, loc in LANGS.items():
    p = os.path.join(PLUG, 'languages/messages.%s.json' % loc)
    msgs[k] = json.load(open(p, encoding='utf-8')).get('messages', {}) if os.path.exists(p) else {}

missing = []
for k in LANGS:
    out = {}
    for key in sorted(used):
        v = old[k].get(key) or msgs[k].get(key) or (key if k == 'en' else '')
        if not v and k == 'tr':
            v = next((t for t, e in pairs.items() if e == key), '')
        if v:
            out[key] = v
        elif k != 'en':
            missing.append((k, key))
    json.dump(out, open(os.path.join(SRC, k + '.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
json.dump(dict(sorted(aliases.items())), open(os.path.join(SRC, 'aliases.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=2)

# Klasik çekirdek (core.js + docs.js + doc-comments.js): Türkçe kaynak -> dil.
js = ''.join(open(os.path.join(PLUG, 'assets/js', f), encoding='utf-8').read() for f in ('core.js', 'docs.js', 'doc-comments.js'))
legacy = [t for t in pairs if ("'" + t.replace("'", "\\'") + "'") in js]
os.makedirs(os.path.join(SRC, 'legacy'), exist_ok=True)
for k in LANGS:
    if k == 'tr':
        continue
    out = {}
    for t in legacy:
        e = pairs[t]
        v = e if k == 'en' else (msgs[k].get(e) or old[k].get(e) or e)
        out[t] = v
    json.dump(out, open(os.path.join(SRC, 'legacy', k + '.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
print('react keys', len(used), 'legacy', len(legacy), 'missing', len(missing))
for x in missing[:40]:
    print(' ', x)
