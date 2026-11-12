#!/usr/bin/env python3
"""Build the TR language files from tools/i18n/tr_TR.json.

  wp i18n make-pot . languages/flx-blocks.pot --domain=flx-blocks --skip-block-json \
     --exclude=node_modules,build,includes/assets/js,tools,gulp,languages
  python3 tools/i18n/extract.py && python3 tools/i18n/compile.py

Writes languages/flx-blocks-tr_TR.{po,mo,l10n.php} and one JSON per built script
(languages/flx-blocks-tr_TR-<script>.json, picked up by flxbk_script_translation_file()).
Strings missing from tr_TR.json are listed in tools/i18n/missing.txt.
"""
import glob, json, os, struct

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.abspath(os.path.join(HERE, '..', '..'))
LANG = os.path.join(ROOT, 'languages')
LOCALE = 'tr_TR'
PLURAL = 'nplurals=2; plural=(n > 1);'
DOMAIN = 'flx-blocks'

items = json.load(open(os.path.join(HERE, 'strings.json'), encoding='utf-8'))
tr = json.load(open(os.path.join(HERE, LOCALE + '.json'), encoding='utf-8'))

entries = []  # (ctx, id, plural, translation-or-list)
missing = []
for it in items:
    key = (it['ctx'] + '\u0004' if it['ctx'] else '') + it['id']
    t = tr.get(key)
    if t is None:
        missing.append(key)
        continue
    entries.append((it['ctx'], it['id'], it['plural'], t))

with open(os.path.join(HERE, 'missing.txt'), 'w', encoding='utf-8') as f:
    f.write('\n'.join(k.replace('\u0004', ' | ') for k in missing))

# --- .po
def po_str(s):
    return '"' + s.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n').replace('\t', '\\t') + '"'

lines = ['msgid ""', 'msgstr ""', po_str('Project-Id-Version: Flux Block Editor\n'), po_str('Language: %s\n' % LOCALE),
         po_str('MIME-Version: 1.0\n'), po_str('Content-Type: text/plain; charset=UTF-8\n'),
         po_str('Content-Transfer-Encoding: 8bit\n'), po_str('Plural-Forms: %s\n' % PLURAL), po_str('X-Domain: %s\n' % DOMAIN), '']
for ctx, mid, pl, t in entries:
    if ctx:
        lines.append('msgctxt ' + po_str(ctx))
    lines.append('msgid ' + po_str(mid))
    if pl:
        tt = t if isinstance(t, list) else [t, t]
        lines += ['msgid_plural ' + po_str(pl), 'msgstr[0] ' + po_str(tt[0]), 'msgstr[1] ' + po_str(tt[-1])]
    else:
        lines.append('msgstr ' + po_str(t if isinstance(t, str) else t[0]))
    lines.append('')
open(os.path.join(LANG, '%s-%s.po' % (DOMAIN, LOCALE)), 'w', encoding='utf-8').write('\n'.join(lines))

# --- key/value pairs shared by .mo and .l10n.php
pairs = {}
for ctx, mid, pl, t in entries:
    k = (ctx + '\x04' if ctx else '') + mid
    if pl:
        tt = t if isinstance(t, list) else [t, t]
        pairs[k + '\x00' + pl] = tt[0] + '\x00' + tt[-1]
    else:
        pairs[k] = t if isinstance(t, str) else t[0]

# --- .mo
header = 'Content-Type: text/plain; charset=UTF-8\nPlural-Forms: %s\nLanguage: %s\nX-Domain: %s\n' % (PLURAL, LOCALE, DOMAIN)
mo = dict(pairs)
mo[''] = header
keys = sorted(mo.keys())
ids = b''; strs = b''; offs = []
for k in keys:
    kb, vb = k.encode('utf-8'), mo[k].encode('utf-8')
    offs.append((len(ids), len(kb), len(strs), len(vb)))
    ids += kb + b'\0'; strs += vb + b'\0'
n = len(keys)
kstart = 7 * 4 + 16 * n
vstart = kstart + len(ids)
out = struct.pack('Iiiiiii', 0x950412de, 0, n, 7 * 4, 7 * 4 + n * 8, 0, 0)
out += b''.join(struct.pack('ii', l, o + kstart) for o, l, _, _ in offs)
out += b''.join(struct.pack('ii', l, o + vstart) for _, _, o, l in offs)
open(os.path.join(LANG, '%s-%s.mo' % (DOMAIN, LOCALE)), 'wb').write(out + ids + strs)

# --- .l10n.php
def php_str(s):
    return "'" + s.replace('\\', '\\\\').replace("'", "\\'") + "'"

def php_key(s):  # keep NUL / EOT bytes readable
    return '"' + s.replace('\\', '\\\\').replace('"', '\\"').replace('$', '\\$').replace('\x00', '\\0').replace('\x04', '\\4') + '"'

php = ["<?php", "return ['domain'=>'%s','plural-forms'=>'%s','language'=>'%s','messages'=>[" % (DOMAIN, PLURAL, LOCALE)]
for k in sorted(pairs):
    php.append(php_key(k) + '=>' + php_key(pairs[k]) + ',')
php.append(']];')
open(os.path.join(LANG, '%s-%s.l10n.php' % (DOMAIN, LOCALE)), 'w', encoding='utf-8').write('\n'.join(php) + '\n')

# --- JSON per built script (only the strings that script uses)
for old in glob.glob(os.path.join(LANG, '%s-%s-*.json' % (DOMAIN, LOCALE))):
    os.remove(old)
scripts = glob.glob(os.path.join(ROOT, 'build', '*.js')) + glob.glob(os.path.join(ROOT, 'includes', 'assets', 'js', '*.js'))
written = 0
for js in scripts:
    code = open(js, encoding='utf-8', errors='ignore').read()
    if DOMAIN not in code:
        continue
    data = {'': {'domain': 'messages', 'lang': LOCALE, 'plural-forms': PLURAL}}
    for ctx, mid, pl, t in entries:
        if json.dumps(mid, ensure_ascii=False) not in code and json.dumps(mid) not in code and ("'" + mid + "'") not in code:
            continue
        k = (ctx + '\u0004' if ctx else '') + mid
        data[k] = (t if isinstance(t, list) else [t]) if pl else [t if isinstance(t, str) else t[0]]
    if len(data) == 1:
        continue
    name = os.path.splitext(os.path.basename(js))[0]
    doc = {'translation-revision-date': '', 'generator': 'flx-blocks/tools/i18n', 'domain': 'messages',
           'locale_data': {'messages': data}}
    json.dump(doc, open(os.path.join(LANG, '%s-%s-%s.json' % (DOMAIN, LOCALE, name)), 'w', encoding='utf-8'),
              ensure_ascii=False, separators=(',', ':'))
    written += 1

print('entries', len(entries), 'missing', len(missing), 'script json', written)
