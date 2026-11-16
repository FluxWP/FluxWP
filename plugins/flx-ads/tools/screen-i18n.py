#!/usr/bin/env python3
"""Ad conversions React screen dictionaries (packages/modules/src/ads/i18n/<lang>.json).

Keys are the English source strings of t()/tn() calls in packages/modules/src/ads/*.tsx (plus the named string
constants those files pass to t()). Turkish comes from tools/tr.json and the Turkish pack; other languages
from the plugin's language packs when they exist (a missing string falls back to English).

Usage: python3 plugins/flx-ads/tools/screen-i18n.py [--check]   (--check: exit 1 when Turkish is missing)
"""
import json, os, re, sys

HERE = os.path.dirname(os.path.abspath(__file__))
PKG = os.path.dirname(HERE)
REPO = os.path.dirname(os.path.dirname(PKG))
LANG = os.path.join(PKG, 'languages')
SCREEN = os.path.join(REPO, 'packages/modules/src/ads')
JS_STR = r"""(?:'((?:[^'\\]|\\.)*)'|"((?:[^"\\]|\\.)*)")"""
JS_T = re.compile(r"\bt\(\s*" + JS_STR)
JS_TN = re.compile(r"\btn\(\s*" + JS_STR + r"\s*,\s*" + JS_STR)
# Named constants handed to t(): `const SETUP = '…';` and `studio:\n '…',` entries.
JS_CONST = re.compile(r"(?:\bconst [A-Z_]+ =|^\s+\w+:)\s*'((?:[^'\\]|\\.)*)'\s*[,;]", re.M)
LOCALES = {'en': 'en_US', 'tr': 'tr_TR', 'de': 'de_DE', 'es': 'es_ES', 'fr': 'fr_FR', 'pt-br': 'pt_BR', 'ja': 'ja', 'zh': 'zh_CN', 'ar': 'ar', 'ru': 'ru_RU'}


def unq(m, i):
    single, double = m.group(i), m.group(i + 1)
    return single.replace("\\'", "'").replace('\\\\', '\\') if single is not None else json.loads('"' + double + '"')


def screen_strings():
    out = []
    for f in sorted(os.listdir(SCREEN)):
        if not f.endswith('.tsx'):
            continue
        src = open(os.path.join(SCREEN, f), encoding='utf-8').read()
        for m in JS_TN.finditer(src):
            out.append(unq(m, 1) + '\0' + unq(m, 3))
        for m in JS_T.finditer(src):
            out.append(unq(m, 1))
        for m in JS_CONST.finditer(src):
            s = m.group(1).replace("\\'", "'").replace('\\\\', '\\')
            if ' ' in s and len(s) > 40:
                out.append(s)
    return list(dict.fromkeys(out))


def pack(locale):
    p = os.path.join(LANG, 'messages.%s.json' % locale)
    return json.load(open(p, encoding='utf-8')).get('messages', {}) if os.path.exists(p) else {}


def main():
    check = '--check' in sys.argv
    screen = screen_strings()
    tr_new = json.load(open(os.path.join(HERE, 'tr.json'), encoding='utf-8'))
    tr_old = pack('tr_TR')
    tr_of = lambda k: tr_new.get(k) or tr_old.get(k)
    missing = [k for k in screen if not tr_of(k)]
    if missing:
        print('Missing Turkish (add to tools/tr.json):', *[m.replace('\0', ' | ') for m in missing], sep='\n  ')
        if check:
            sys.exit(1)
    if check:
        print(f'{len(screen)} screen strings, Turkish complete')
        return
    os.makedirs(os.path.join(SCREEN, 'i18n'), exist_ok=True)
    for lang, loc in LOCALES.items():
        other = {} if lang == 'en' else pack(loc)
        out = {k: v for k in screen if (v := tr_of(k) if lang == 'tr' else other.get(k))}
        with open(os.path.join(SCREEN, 'i18n', lang + '.json'), 'w', encoding='utf-8') as f:
            json.dump(out, f, ensure_ascii=False, indent=1)
            f.write('\n')
    print(f'{len(screen)} screen strings')


main()
