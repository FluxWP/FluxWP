#!/usr/bin/env python3
"""FluxWP theme translations. Source strings are English (PHP __() and the React screen's t()/tn()).

Writes:
  languages/fluxwp.pot                       every PHP string
  languages/tr_TR.{po,mo,l10n.php}    Turkish: existing pack + tools/tr.json for new strings
  languages/messages.tr_TR.json                 same, FluxWP package JSON
  packages/modules/src/theme/i18n/<lang>.json   React screen dictionaries from every language pack
                                                (a string missing in a pack falls back to English)
Other language packs are left as they are; new strings show in English there until translated.

Usage: python3 themes/fluxwp/tools/i18n.py [--check]   (--check: exit 1 when Turkish is missing)
"""
import json, os, re, struct, subprocess, sys

HERE = os.path.dirname(os.path.abspath(__file__))
PKG = os.path.dirname(HERE)
REPO = os.path.dirname(os.path.dirname(PKG))
LANG = os.path.join(PKG, 'languages')
SCREEN = os.path.join(REPO, 'packages/modules/src/theme')
DOMAIN = 'fluxwp'
STR = r"'((?:[^'\\]|\\.)*)'"
SINGLE = re.compile(r"\b(?:__|esc_html__|esc_attr__|_e|esc_html_e|esc_attr_e)\(\s*" + STR + r"\s*,\s*'" + DOMAIN + "'")
PLURAL = re.compile(r"\b_n\(\s*" + STR + r"\s*,\s*" + STR + r"\s*,[^;]*?'" + DOMAIN + "'", re.S)
JS_STR = r"""(?:'((?:[^'\\]|\\.)*)'|"((?:[^"\\]|\\.)*)")"""
JS_T = re.compile(r"\bt\(\s*" + JS_STR)
JS_TN = re.compile(r"\btn\(\s*" + JS_STR + r"\s*,\s*" + JS_STR)
LOCALES = {'en': 'en_US', 'tr': 'tr_TR', 'de': 'de_DE', 'es': 'es_ES', 'fr': 'fr_FR', 'pt-br': 'pt_BR', 'ja': 'ja', 'zh': 'zh_CN', 'ar': 'ar', 'ru': 'ru_RU'}


def php_unq(s):
    return s.replace("\\'", "'").replace('\\\\', '\\')


def js_unq(m, i):
    single, double = m.group(i), m.group(i + 1)
    return php_unq(single) if single is not None else json.loads('"' + double + '"')


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


def screen_strings():
    out = []
    for f in sorted(os.listdir(SCREEN)):
        if not f.endswith('.tsx'):
            continue
        src = open(os.path.join(SCREEN, f), encoding='utf-8').read()
        for m in JS_TN.finditer(src):
            out.append(js_unq(m, 1) + '\0' + js_unq(m, 3))
        for m in JS_T.finditer(src):
            out.append(js_unq(m, 1))
    return list(dict.fromkeys(out))


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
    screen = screen_strings()
    tr_new = json.load(open(os.path.join(HERE, 'tr.json'), encoding='utf-8'))
    tr_pack = messages('tr_TR')
    tr_old = tr_pack.get('messages', {})
    # tr_TR.l10n.php holds more than the JSON pack (patterns, theme.json, templates); keep all of it.
    l10n = subprocess.run(['php', '-r', 'echo json_encode((include $argv[1])["messages"]);', '--', os.path.join(LANG, 'tr_TR.l10n.php')], capture_output=True, text=True, check=True).stdout
    tr_all_old = {**json.loads(l10n), **tr_old}

    def tr_of(k):
        return tr_new.get(k) or tr_old.get(k)

    missing = [k for k in list(php) + screen if not tr_of(k)]
    if missing:
        print('Missing Turkish (add to tools/tr.json):', *[m.replace('\0', ' | ') for m in missing], sep='\n  ')
        if check:
            sys.exit(1)
    if check:
        print(f'{len(php)} PHP + {len(screen)} screen strings, Turkish complete')
        return

    header = 'Content-Type: text/plain; charset=UTF-8\\nLanguage: {loc}\\nPlural-Forms: nplurals=2; plural=(n != 1);\\n'
    with open(os.path.join(LANG, DOMAIN + '.pot'), 'w', encoding='utf-8') as f:
        f.write('msgid ""\nmsgstr "' + header.format(loc='en_US') + '"\n')
        for k in list(dict.fromkeys(list(php) + list(tr_old))):
            if '\0' in k:
                a, b = k.split('\0')
                f.write(f'\nmsgid {po_q(a)}\nmsgid_plural {po_q(b)}\nmsgstr[0] ""\nmsgstr[1] ""\n')
            else:
                f.write(f'\nmsgid {po_q(k)}\nmsgstr ""\n')

    # Keep every existing string (patterns, theme.json, templates are not scanned); add the new PHP ones.
    new = {k: tr_of(k) for k in php if tr_of(k)}
    tr = {**tr_all_old, **new}
    with open(os.path.join(LANG, 'tr_TR.po'), 'w', encoding='utf-8') as f:
        f.write('msgid ""\nmsgstr "' + header.format(loc='tr_TR') + '"\n')
        for k, v in tr.items():
            if '\0' in k:
                a, b = k.split('\0')
                va = v.split('\0')
                f.write(f'\nmsgid {po_q(a)}\nmsgid_plural {po_q(b)}\nmsgstr[0] {po_q(va[0])}\nmsgstr[1] {po_q(va[-1])}\n')
            else:
                f.write(f'\nmsgid {po_q(k)}\nmsgstr {po_q(v)}\n')
    write_mo(os.path.join(LANG, 'tr_TR.mo'), tr, header.format(loc='tr_TR').replace('\\n', '\n'))
    with open(os.path.join(LANG, 'tr_TR.l10n.php'), 'w', encoding='utf-8') as f:
        f.write("<?php\nreturn array(\n 'language' => 'tr_TR',\n 'plural-forms' => 'nplurals=2; plural=(n != 1);',\n 'messages' => array(\n")
        for k, v in tr.items():
            f.write(f'  {php_q(k)} => {php_q(v)},\n')
        f.write(' ),\n);\n')
    tr_pack['messages'] = {**tr_old, **new}
    json.dump(tr_pack, open(os.path.join(LANG, 'messages.tr_TR.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=2)

    os.makedirs(os.path.join(SCREEN, 'i18n'), exist_ok=True)
    for lang, loc in LOCALES.items():
        pack = {} if lang == 'en' else messages(loc).get('messages', {})
        out = {}
        for k in screen:
            v = tr_of(k) if lang == 'tr' else pack.get(k)
            if v:
                out[k] = v
        with open(os.path.join(SCREEN, 'i18n', lang + '.json'), 'w', encoding='utf-8') as f:
            json.dump(out, f, ensure_ascii=False, indent=1)
            f.write('\n')
    print(f'{len(php)} PHP strings, {len(tr)} Turkish; {len(screen)} screen strings')


main()
