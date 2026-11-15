#!/usr/bin/env python3
"""Flux App translations. Source strings are English (PHP __()).

Writes:
  languages/flx-app.pot                       every PHP string
  languages/flx-app-tr_TR.{po,mo,l10n.php}    Turkish: existing pack + tools/tr.json for new strings
  languages/messages.tr_TR.json                 same, FluxWP package JSON
Other language packs are left as they are; new strings show in English there until translated.

Usage: python3 plugins/flx-app/tools/i18n.py [--check]   (--check: exit 1 when Turkish is missing)
"""
import json, os, re, struct, sys

HERE = os.path.dirname(os.path.abspath(__file__))
PKG = os.path.dirname(HERE)
REPO = os.path.dirname(os.path.dirname(PKG))
LANG = os.path.join(PKG, 'languages')
DOMAIN = 'flx-app'
STR = r"'((?:[^'\\]|\\.)*)'"
SINGLE = re.compile(r"\b(?:__|esc_html__|esc_attr__|_e|esc_html_e|esc_attr_e)\(\s*" + STR + r"\s*,\s*'" + DOMAIN + "'")
PLURAL = re.compile(r"\b_n\(\s*" + STR + r"\s*,\s*" + STR + r"\s*,[^;]*?'" + DOMAIN + "'", re.S)
LOCALES = {'en': 'en_US', 'tr': 'tr_TR', 'de': 'de_DE', 'es': 'es_ES', 'fr': 'fr_FR', 'pt-br': 'pt_BR', 'ja': 'ja', 'zh': 'zh_CN', 'ar': 'ar', 'ru': 'ru_RU'}


def php_unq(s):
    return s.replace("\\'", "'").replace('\\\\', '\\')


def php_strings():
    out = {}
    for d, dirs, files in os.walk(PKG):
        dirs[:] = [x for x in dirs if x not in ('tools', 'languages', 'flx-brand', 'assets', 'docs')]
        for f in sorted(files):
            if not f.endswith('.php'):
                continue
            src = open(os.path.join(d, f), encoding='utf-8').read()
            for m in PLURAL.finditer(src):
                out[php_unq(m.group(1)) + '\0' + php_unq(m.group(2))] = os.path.relpath(os.path.join(d, f), PKG)
            for m in SINGLE.finditer(src):
                out.setdefault(php_unq(m.group(1)), os.path.relpath(os.path.join(d, f), PKG))
    return out


def messages(locale):
    p = os.path.join(LANG, 'messages.%s.json' % locale)
    return json.load(open(p, encoding='utf-8')) if os.path.exists(p) else {'_meta': {}, 'messages': {}}


def po_q(s):
    return '"' + s.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n') + '"'


def php_q(s):
    parts = s.split('\0')
    return " . chr(0) . ".join("'" + p.replace('\\', '\\\\').replace("'", "\\'") + "'" for p in parts)


def write_mo(path, pairs, header):
    items = [('', header)] + sorted(pairs.items())
    ids, strs, offs = b'', b'', []
    for k, v in items:
        kb, vb = k.encode(), v.encode()
        offs.append((len(ids), len(kb), len(strs), len(vb)))
        ids += kb + b'\0'
        strs += vb + b'\0'
    n = len(items)
    ks = 7 * 4 + 16 * n
    vs = ks + len(ids)
    out = struct.pack('Iiiiiii', 0x950412de, 0, n, 28, 28 + n * 8, 0, 0)
    for o in offs:
        out += struct.pack('ii', o[1], o[0] + ks)
    for o in offs:
        out += struct.pack('ii', o[3], o[2] + vs)
    open(path, 'wb').write(out + ids + strs)


def main():
    check = '--check' in sys.argv
    php = php_strings()
    screen = []
    tr_new = json.load(open(os.path.join(HERE, 'tr.json'), encoding='utf-8'))
    tr_pack = messages('tr_TR')
    tr_old = tr_pack.get('messages', {})

    def tr_of(k):
        return tr_new.get(k) or tr_old.get(k)

    missing = [k for k in list(php) + screen if not tr_of(k)]
    if missing:
        print('Missing Turkish (add to tools/tr.json):', *[m.replace('\0', ' | ') for m in missing], sep='\n  ')
        if check:
            sys.exit(1)
    if check:
        print(f'{len(php)} PHP strings, Turkish complete')
        return

    header = 'Content-Type: text/plain; charset=UTF-8\\nLanguage: {loc}\\nPlural-Forms: nplurals=2; plural=(n != 1);\\n'
    with open(os.path.join(LANG, DOMAIN + '.pot'), 'w', encoding='utf-8') as f:
        f.write('msgid ""\nmsgstr "' + header.format(loc='en_US') + '"\n')
        for k in php:
            if '\0' in k:
                a, b = k.split('\0')
                f.write(f'\nmsgid {po_q(a)}\nmsgid_plural {po_q(b)}\nmsgstr[0] ""\nmsgstr[1] ""\n')
            else:
                f.write(f'\nmsgid {po_q(k)}\nmsgstr ""\n')

    tr = {k: tr_of(k) for k in php if tr_of(k)}
    with open(os.path.join(LANG, DOMAIN + '-tr_TR.po'), 'w', encoding='utf-8') as f:
        f.write('msgid ""\nmsgstr "' + header.format(loc='tr_TR') + '"\n')
        for k, v in tr.items():
            if '\0' in k:
                a, b = k.split('\0')
                va = v.split('\0')
                f.write(f'\nmsgid {po_q(a)}\nmsgid_plural {po_q(b)}\nmsgstr[0] {po_q(va[0])}\nmsgstr[1] {po_q(va[-1])}\n')
            else:
                f.write(f'\nmsgid {po_q(k)}\nmsgstr {po_q(v)}\n')
    write_mo(os.path.join(LANG, DOMAIN + '-tr_TR.mo'), tr, header.format(loc='tr_TR').replace('\\n', '\n'))
    with open(os.path.join(LANG, DOMAIN + '-tr_TR.l10n.php'), 'w', encoding='utf-8') as f:
        f.write("<?php\nreturn array(\n 'language' => 'tr_TR',\n 'plural-forms' => 'nplurals=2; plural=(n != 1);',\n 'messages' => array(\n")
        for k, v in tr.items():
            f.write(f'  {php_q(k)} => {php_q(v)},\n')
        f.write(' ),\n);\n')
    tr_pack['messages'] = tr
    json.dump(tr_pack, open(os.path.join(LANG, 'messages.tr_TR.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=2)

    print(f'{len(php)} PHP strings, {len(tr)} Turkish')


main()
