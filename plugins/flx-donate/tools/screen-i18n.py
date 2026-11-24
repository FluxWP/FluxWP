#!/usr/bin/env python3
"""Donations React screen dictionaries (packages/modules/src/donate/i18n/<lang>.json).

Keys are the English source strings of t()/tn() calls in packages/modules/src/donate/*.tsx (plus the
`label: '…'` constants those files pass to t()). Every language comes from the plugin's language packs
(languages/flx-donate-<locale>.mo); a string the packs do not have yet comes from tools/tr.json (Turkish)
or falls back to English.

Usage: python3 plugins/flx-donate/tools/screen-i18n.py [--check]   (--check: exit 1 when Turkish is missing)
"""
import gettext, json, os, re, sys

HERE = os.path.dirname(os.path.abspath(__file__))
PKG = os.path.dirname(HERE)
REPO = os.path.dirname(os.path.dirname(PKG))
LANG = os.path.join(PKG, 'languages')
SCREEN = os.path.join(REPO, 'packages/modules/src/donate')
JS_STR = r"""(?:'((?:[^'\\]|\\.)*)'|"((?:[^"\\]|\\.)*)")"""
JS_T = re.compile(r"\bt\(\s*" + JS_STR)
JS_TN = re.compile(r"\btn\(\s*" + JS_STR + r"\s*,\s*" + JS_STR)
JS_LABEL = re.compile(r"\blabel: '((?:[^'\\]|\\.)*)'")
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
        for m in JS_LABEL.finditer(src):
            out.append(m.group(1).replace("\\'", "'"))
    return list(dict.fromkeys(out))


def pack(locale):
    p = os.path.join(LANG, 'flx-donate-%s.mo' % locale)
    if not os.path.exists(p):
        return {}
    cat = gettext.GNUTranslations(open(p, 'rb'))._catalog
    out = {}
    for k, v in cat.items():
        if isinstance(k, tuple):
            out.setdefault(('n', k[0]), {})[k[1]] = v
        elif k:
            out[k] = v
    return out


def lookup(d, key):
    if '\0' in key:
        single = key.split('\0')[0]
        forms = d.get(('n', single))
        if forms:
            return forms.get(0, '') + '\0' + forms.get(1, forms.get(0, ''))
        return None
    return d.get(key) or None


def main():
    check = '--check' in sys.argv
    screen = screen_strings()
    extra_p = os.path.join(HERE, 'tr.json')
    tr_new = json.load(open(extra_p, encoding='utf-8')) if os.path.exists(extra_p) else {}
    tr_old = pack('tr_TR')
    tr_of = lambda k: lookup(tr_old, k) or tr_new.get(k)
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
        if lang == 'en':
            continue
        other = pack(loc)
        out = {}
        for k in screen:
            v = tr_of(k) if lang == 'tr' else lookup(other, k)
            if v and v != k:
                out[k] = v
        with open(os.path.join(SCREEN, 'i18n', lang + '.json'), 'w', encoding='utf-8') as f:
            json.dump(out, f, ensure_ascii=False, indent=1)
            f.write('\n')
    print(f'{len(screen)} screen strings')


main()
