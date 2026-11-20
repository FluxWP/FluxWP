#!/usr/bin/env python3
"""Flux Chat Turkish for the strings added with the GraphQL settings/reports work.

Source strings are English (PHP __() and the React screens' t()). The existing Turkish pack stays as it is
(it also holds legacy keys that are not in the .po), so this only ADDS what is missing:

  tools/tr.json                       new Turkish strings (English source -> Turkish), edited by hand
  languages/flx-chat.pot              missing PHP strings appended
  languages/flx-chat-tr_TR.po         missing PHP strings appended with their Turkish
  languages/flx-chat-tr_TR.mo         rewritten from the existing .mo plus the new strings
  languages/flx-chat-tr_TR.l10n.php   new strings appended
  languages/messages.tr_TR.json       new strings added
  packages/modules/src/whatsapp/i18n-admin/{en,tr}.json   the settings/reports screen dictionaries

Usage: python3 plugins/flx-chat/tools/i18n.py [--check]   (--check: exit 1 when a Turkish string is missing)
"""
import json, os, re, struct, sys

HERE = os.path.dirname(os.path.abspath(__file__))
PKG = os.path.dirname(HERE)
REPO = os.path.dirname(os.path.dirname(PKG))
LANG = os.path.join(PKG, 'languages')
SCREEN = os.path.join(REPO, 'packages/modules/src/whatsapp')
DOMAIN = 'flx-chat'
STR = r"'((?:[^'\\]|\\.)*)'"
SINGLE = re.compile(r"\b(?:__|esc_html__|esc_attr__|_e|esc_html_e|esc_attr_e)\(\s*" + STR + r"\s*,\s*'" + DOMAIN + "'")
JS_STR = r"""(?:'((?:[^'\\]|\\.)*)'|"((?:[^"\\]|\\.)*)")"""
JS_T = re.compile(r"\bt\(\s*" + JS_STR)
SCREEN_FILES = ['SettingsScreen.tsx', 'ReportsScreen.tsx', 'admin-common.tsx']
# PHP files written for this work (their strings must be translated); older files keep their pack.
PHP_FILES = ['includes/graphql/schema-admin.php', 'includes/services/class-flxc-settings-service.php', 'includes/services/class-flxc-report-service.php', 'includes/flux-ui.php']


def php_unq(s):
    return s.replace("\\'", "'").replace('\\\\', '\\')


def php_strings():
    out = {}
    for f in PHP_FILES:
        src = open(os.path.join(PKG, f), encoding='utf-8').read()
        for m in SINGLE.finditer(src):
            out.setdefault(php_unq(m.group(1)), f)
    return out


def screen_strings():
    out = []
    for f in SCREEN_FILES:
        src = open(os.path.join(SCREEN, f), encoding='utf-8').read()
        for m in JS_T.finditer(src):
            s = php_unq(m.group(1)) if m.group(1) is not None else json.loads('"' + m.group(2) + '"')
            out.append(s)
    return list(dict.fromkeys(out))


def read_mo(path):
    d = open(path, 'rb').read()
    _, _, n, o, t = struct.unpack('<IIIII', d[:20])
    out = {}
    for i in range(n):
        l, off = struct.unpack('<II', d[o + 8 * i:o + 8 * i + 8])
        l2, off2 = struct.unpack('<II', d[t + 8 * i:t + 8 * i + 8])
        out[d[off:off + l].decode()] = d[off2:off2 + l2].decode()
    return out


def write_mo(path, pairs):
    items = sorted(pairs.items())
    ids, strs, offs = b'', b'', []
    for k, v in items:
        kb, vb = k.encode(), v.encode()
        offs.append((len(ids), len(kb), len(strs), len(vb)))
        ids += kb + b'\0'
        strs += vb + b'\0'
    n = len(items)
    ks = 7 * 4 + 16 * n
    vs = ks + len(ids)
    out = struct.pack('<IIIIIII', 0x950412de, 0, n, 28, 28 + n * 8, 0, 0)
    for o in offs:
        out += struct.pack('<II', o[1], o[0] + ks)
    for o in offs:
        out += struct.pack('<II', o[3], o[2] + vs)
    open(path, 'wb').write(out + ids + strs)


def po_q(s):
    return '"' + s.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n') + '"'


def php_q(s):
    return "'" + s.replace('\\', '\\\\').replace("'", "\\'") + "'"


def main():
    check = '--check' in sys.argv
    php = php_strings()
    screen = screen_strings()
    new_tr = json.load(open(os.path.join(HERE, 'tr.json'), encoding='utf-8'))
    mo_path = os.path.join(LANG, DOMAIN + '-tr_TR.mo')
    mo = read_mo(mo_path)
    have = lambda k: new_tr.get(k) or mo.get(k)
    missing = [k for k in list(php) + screen if not have(k)]
    if missing:
        print('Missing Turkish (add to tools/tr.json):', *missing, sep='\n  ')
        if check:
            sys.exit(1)
    if check:
        print(f'{len(php)} PHP + {len(screen)} screen strings, Turkish complete')
        return

    add = {k: new_tr[k] for k in php if new_tr.get(k)}  # each file below skips what it already has
    po_path = os.path.join(LANG, DOMAIN + '-tr_TR.po')
    pot_path = os.path.join(LANG, DOMAIN + '.pot')
    po_txt = open(po_path, encoding='utf-8').read()
    pot_txt = open(pot_path, encoding='utf-8').read()
    with open(po_path, 'a', encoding='utf-8') as f:
        for k, v in add.items():
            if 'msgid ' + po_q(k) + '\n' not in po_txt:
                f.write(f'\nmsgid {po_q(k)}\nmsgstr {po_q(v)}\n')
    with open(pot_path, 'a', encoding='utf-8') as f:
        for k in add:
            if 'msgid ' + po_q(k) + '\n' not in pot_txt:
                f.write(f'\nmsgid {po_q(k)}\nmsgstr ""\n')
    merged = dict(mo)
    merged.update({k: v for k, v in add.items() if k not in mo})
    write_mo(mo_path, merged)
    l10n = os.path.join(LANG, DOMAIN + '-tr_TR.l10n.php')
    txt = open(l10n, encoding='utf-8').read()
    body = ''.join(f"  {php_q(k)} => {php_q(v)},\n" for k, v in add.items() if php_q(k) + ' =>' not in txt)
    if body:
        i = txt.rindex(' ),\n);')
        open(l10n, 'w', encoding='utf-8').write(txt[:i] + body + txt[i:])
    msgs = os.path.join(LANG, 'messages.tr_TR.json')
    pack = json.load(open(msgs, encoding='utf-8'))
    for k, v in add.items():
        pack['messages'].setdefault(k, v)
    json.dump(pack, open(msgs, 'w', encoding='utf-8'), ensure_ascii=False, indent=2)

    out_dir = os.path.join(SCREEN, 'i18n-admin')
    os.makedirs(out_dir, exist_ok=True)
    tr = {k: have(k) for k in screen if have(k)}
    for lang, data in (('en', {}), ('tr', tr)):
        with open(os.path.join(out_dir, lang + '.json'), 'w', encoding='utf-8') as f:
            json.dump(data, f, ensure_ascii=False, indent=1)
            f.write('\n')
    print(f'{len(add)} PHP strings added; {len(tr)} of {len(screen)} screen strings translated')


main()
