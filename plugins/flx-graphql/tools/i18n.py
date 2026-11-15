#!/usr/bin/env python3
"""Builds languages/ for Flux GraphQL: flx-graphql.pot, flx-graphql-tr_TR.{po,mo,l10n.php}.

Source strings are English (in the PHP code). Turkish lives in tools/tr.json ({ "English": "Türkçe" }).
Run after changing strings:  python3 plugins/flx-graphql/tools/i18n.py
--check exits 1 when a string has no Turkish translation.
"""
import json, os, re, struct, sys

HERE = os.path.dirname(os.path.abspath(__file__))
PKG = os.path.dirname(HERE)
DOMAIN = 'flx-graphql'
FUNCS = r'(?:__|esc_html__|esc_attr__|esc_html_e|esc_attr_e|_e|_x|_n)'
STR = r"'((?:[^'\\]|\\.)*)'"


def php_unescape(s):
    return s.replace("\\'", "'").replace('\\\\', '\\')


def collect():
    found = {}
    for dirpath, dirs, files in os.walk(PKG):
        dirs[:] = [d for d in dirs if d not in ('vendor', 'languages', 'tools', 'flx-brand', 'node_modules')]
        for name in sorted(files):
            if not name.endswith('.php'):
                continue
            path = os.path.join(dirpath, name)
            src = open(path, encoding='utf-8').read()
            for m in re.finditer(FUNCS + r'\(\s*' + STR + r'(?:\s*,\s*' + STR + r')?\s*,\s*\'' + DOMAIN + r'\'', src):
                one = php_unescape(m.group(1))
                found.setdefault(one, os.path.relpath(path, PKG))
            # _n( 'single', 'plural', $n, 'domain' )
            for m in re.finditer(r'_n\(\s*' + STR + r'\s*,\s*' + STR + r'\s*,[^;]*?\'' + DOMAIN + r'\'', src, re.S):
                found.setdefault(php_unescape(m.group(1)), os.path.relpath(path, PKG))
    return found


def po_quote(s):
    return '"' + s.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n') + '"'


def php_quote(s):
    return "'" + s.replace('\\', '\\\\').replace("'", "\\'") + "'"


def write_mo(path, pairs):
    pairs = sorted(pairs.items())
    header = 'Content-Type: text/plain; charset=UTF-8\nLanguage: tr_TR\nPlural-Forms: nplurals=2; plural=(n != 1);\n'
    pairs = [('', header)] + pairs
    ids = b''
    strs = b''
    offsets = []
    for k, v in pairs:
        kb, vb = k.encode(), v.encode()
        offsets.append((len(ids), len(kb), len(strs), len(vb)))
        ids += kb + b'\0'
        strs += vb + b'\0'
    n = len(pairs)
    kstart = 7 * 4 + 16 * n
    vstart = kstart + len(ids)
    out = struct.pack('Iiiiiii', 0x950412de, 0, n, 7 * 4, 7 * 4 + n * 8, 0, 0)
    for o in offsets:
        out += struct.pack('ii', o[1], o[0] + kstart)
    for o in offsets:
        out += struct.pack('ii', o[3], o[2] + vstart)
    open(path, 'wb').write(out + ids + strs)


def main():
    check = '--check' in sys.argv
    strings = collect()
    tr = json.load(open(os.path.join(HERE, 'tr.json'), encoding='utf-8'))
    missing = [s for s in strings if s not in tr]
    if missing:
        print('Missing Turkish:', *missing, sep='\n  ')
        if check:
            sys.exit(1)
    if check:
        print(f'{len(strings)} strings, all translated')
        return
    lang = os.path.join(PKG, 'languages')
    os.makedirs(lang, exist_ok=True)
    head = 'msgid ""\nmsgstr ""\n"Project-Id-Version: Flux GraphQL\\n"\n"Content-Type: text/plain; charset=UTF-8\\n"\n'
    with open(os.path.join(lang, DOMAIN + '.pot'), 'w', encoding='utf-8') as f:
        f.write(head + '\n')
        for s, ref in strings.items():
            f.write(f'#: {ref}\nmsgid {po_quote(s)}\nmsgstr ""\n\n')
    pairs = {s: tr[s] for s in strings if s in tr}
    with open(os.path.join(lang, DOMAIN + '-tr_TR.po'), 'w', encoding='utf-8') as f:
        f.write(head + '"Language: tr_TR\\n"\n"Plural-Forms: nplurals=2; plural=(n != 1);\\n"\n\n')
        for s, t in pairs.items():
            f.write(f'msgid {po_quote(s)}\nmsgstr {po_quote(t)}\n\n')
    write_mo(os.path.join(lang, DOMAIN + '-tr_TR.mo'), pairs)
    with open(os.path.join(lang, DOMAIN + '-tr_TR.l10n.php'), 'w', encoding='utf-8') as f:
        f.write("<?php\nreturn array(\n 'language' => 'tr_TR',\n 'plural-forms' => 'nplurals=2; plural=(n != 1);',\n 'messages' => array(\n")
        for s, t in pairs.items():
            f.write(f'  {php_quote(s)} => {php_quote(t)},\n')
        f.write(' ),\n);\n')
    open(os.path.join(lang, 'index.php'), 'w').write('<?php\n// Silence is golden.\n')
    print(f'{len(strings)} strings, {len(pairs)} Turkish')


main()
