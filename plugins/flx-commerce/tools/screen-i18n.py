#!/usr/bin/env python3
"""Store React screen dictionaries (packages/modules/src/commerce/i18n/<lang>.json).

Keys are the English source strings of t() calls in packages/modules/src/commerce/*.tsx. Every language comes
from the plugin's .po files first, so the screen says exactly what the classic screens said; Turkish strings
only the React screen has come from tools/screen-tr.json. A missing string falls back to English.

Usage: python3 plugins/flx-commerce/tools/screen-i18n.py [--check]   (--check: exit 1 when Turkish is missing)
"""
import json, os, re, sys

HERE = os.path.dirname(os.path.abspath(__file__))
PKG = os.path.dirname(HERE)
REPO = os.path.dirname(os.path.dirname(PKG))
LANG = os.path.join(PKG, 'languages')
SCREEN = os.path.join(REPO, 'packages/modules/src/commerce')
JS_STR = r"""(?:'((?:[^'\\]|\\.)*)'|"((?:[^"\\]|\\.)*)")"""
JS_T = re.compile(r"\bt\(\s*" + JS_STR)
LOCALES = {'en': 'en_US', 'tr': 'tr_TR', 'de': 'de_DE', 'es': 'es_ES', 'fr': 'fr_FR', 'pt-br': 'pt_BR', 'ja': 'ja', 'zh': 'zh_CN', 'ar': 'ar', 'ru': 'ru_RU'}


def unq(m, i):
    single, double = m.group(i), m.group(i + 1)
    return single.replace("\\'", "'").replace('\\\\', '\\') if single is not None else json.loads('"' + double + '"')


def screen_strings():
    out = []
    for f in sorted(os.listdir(SCREEN)):
        if f.endswith('.tsx') or f.endswith('.ts'):
            src = open(os.path.join(SCREEN, f), encoding='utf-8').read()
            out += [unq(m, 1) for m in JS_T.finditer(src)]
    return list(dict.fromkeys(out))


def po(locale):
    p = os.path.join(LANG, 'flx-commerce-%s.po' % locale)
    if not os.path.exists(p):
        return {}
    out, cur, field = {}, {}, None
    def flush():
        k, v = cur.get('msgid', ''), cur.get('msgstr', '') or cur.get('msgstr[0]', '')
        if k and v:
            out[k] = v
    for line in open(p, encoding='utf-8'):
        line = line.strip()
        m = re.match(r'^(msgid|msgid_plural|msgstr(?:\[\d\])?) (".*")$', line)
        if m:
            if m.group(1) == 'msgid':
                flush()
                cur = {}
            field = m.group(1)
            cur[field] = json.loads(m.group(2))
        elif line.startswith('"') and field:
            cur[field] += json.loads(line)
        elif not line:
            field = None
    flush()
    return out


def main():
    check = '--check' in sys.argv
    screen = screen_strings()
    extra = json.load(open(os.path.join(HERE, 'screen-tr.json'), encoding='utf-8'))
    tr_po = po('tr_TR')
    tr_of = lambda k: tr_po.get(k) or extra.get(k)
    missing = [k for k in screen if not tr_of(k)]
    if missing:
        print('Missing Turkish (add to tools/screen-tr.json):', *missing, sep='\n  ')
        if check:
            sys.exit(1)
    if check:
        print(f'{len(screen)} screen strings, Turkish complete')
        return
    for lang, loc in LOCALES.items():
        if lang == 'en':
            out = {k: k for k in screen}
        elif lang == 'tr':
            out = {k: v for k in screen if (v := tr_of(k))}
        else:
            d = po(loc)
            out = {k: v for k in screen if (v := d.get(k))}
        with open(os.path.join(SCREEN, 'i18n', lang + '.json'), 'w', encoding='utf-8') as f:
            json.dump(out, f, ensure_ascii=False, indent=2)
            f.write('\n')
    print(f'{len(screen)} screen strings')


main()
