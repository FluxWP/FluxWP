#!/usr/bin/env python3
"""Flux CRM React screen dictionaries (packages/modules/src/crm/i18n/<lang>.json).

Keys are the English source strings of t()/tn() calls in packages/modules/src/crm/*.tsx. Values come from the
plugin's language packs (languages/messages.<locale>.json, made from the PHP strings) and, for strings only the
screen has, tools/tr-screen.json (Turkish). A string missing in a pack falls back to English.

Usage: python3 plugins/flx-crm/tools/i18n-screen.py [--check]   (--check: exit 1 when Turkish is missing)
"""
import json, os, re, sys

HERE = os.path.dirname(os.path.abspath(__file__))
PKG = os.path.dirname(HERE)
REPO = os.path.dirname(os.path.dirname(PKG))
LANG = os.path.join(PKG, 'languages')
SCREEN = os.path.join(REPO, 'packages/modules/src/crm')
LOCALES = {'en': 'en_US', 'tr': 'tr_TR', 'de': 'de_DE', 'es': 'es_ES', 'fr': 'fr_FR', 'pt-br': 'pt_BR', 'ja': 'ja', 'zh': 'zh_CN', 'ar': 'ar', 'ru': 'ru_RU'}
STR = r"""(?:'((?:[^'\\]|\\.)*)'|"((?:[^"\\\n]|\\.)*)")"""
T = re.compile(r"(?<![\w.])t\(\s*" + STR)
TN = re.compile(r"\btn\(\s*" + STR + r"\s*,\s*" + STR)


def unq(m, i):
    a, b = m.group(i), m.group(i + 1)
    return a.replace("\\'", "'").replace('\\\\', '\\') if a is not None else json.loads('"' + b + '"')


def screen_strings():
    out = []
    for f in sorted(os.listdir(SCREEN)):
        if not f.endswith('.tsx'):
            continue
        src = open(os.path.join(SCREEN, f), encoding='utf-8').read()
        for m in TN.finditer(src):
            out.append(unq(m, 1) + '\0' + unq(m, 3))
        for m in T.finditer(src):
            out.append(unq(m, 1))
    return list(dict.fromkeys(out))


def pack(locale):
    p = os.path.join(LANG, 'messages.%s.json' % locale)
    return json.load(open(p, encoding='utf-8')).get('messages', {}) if os.path.exists(p) else {}


def main():
    strings = screen_strings()
    extra = json.load(open(os.path.join(HERE, 'tr-screen.json'), encoding='utf-8')) if os.path.exists(os.path.join(HERE, 'tr-screen.json')) else {}
    tr_pack = pack('tr_TR')
    missing = [k for k in strings if not (extra.get(k) or tr_pack.get(k))]
    if missing:
        print('Missing Turkish (add to tools/tr-screen.json):', *[m.replace('\0', ' | ') for m in missing], sep='\n  ')
        if '--check' in sys.argv:
            sys.exit(1)
    os.makedirs(os.path.join(SCREEN, 'i18n'), exist_ok=True)
    for lang, loc in LOCALES.items():
        p = {} if lang == 'en' else pack(loc)
        out = {}
        for k in strings:
            v = (extra.get(k) or tr_pack.get(k)) if lang == 'tr' else p.get(k)
            if v:
                out[k] = v
        with open(os.path.join(SCREEN, 'i18n', lang + '.json'), 'w', encoding='utf-8') as f:
            json.dump(out, f, ensure_ascii=False, indent=1)
            f.write('\n')
    print(f'{len(strings)} screen strings, {len(missing)} without Turkish')


main()
