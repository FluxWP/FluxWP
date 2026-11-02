#!/usr/bin/env python3
"""flx-app çeviri dosyalarını üretir: PHP'deki __() / esc_html__() / _n() metinlerini toplar,
EN karşılıklarını tools/en.json'dan alır; languages/ altına .pot, en_US .po, .mo ve .l10n.php yazar.
Kullanım: python3 tools/genstr.py  (eksik çeviri varsa listeler, çıkış kodu 1)"""
import json, os, re, struct, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DOMAIN = 'flx-app'
S = r"'((?:[^'\\]|\\.)*)'"
SINGLE = re.compile(r"\b(?:__|esc_html__|esc_attr__|_e|esc_html_e|esc_attr_e)\(\s*" + S + r"\s*,\s*'" + DOMAIN + "'")
PLURAL = re.compile(r"\b_n\(\s*" + S + r"\s*,\s*" + S + r"\s*,[^;]*?'" + DOMAIN + "'")


def unq(s):
    return s.replace("\\'", "'").replace('\\\\', '\\')


def collect():
    out, plur = [], {}
    for d, _, files in os.walk(ROOT):
        if '/tools' in d or '/languages' in d:
            continue
        for f in sorted(files):
            if f.endswith('.php'):
                src = open(os.path.join(d, f), encoding='utf-8').read()
                for m in PLURAL.finditer(src):
                    k = unq(m.group(1))
                    plur[k] = unq(m.group(2))
                    if k not in out:
                        out.append(k)
                for m in SINGLE.finditer(src):
                    k = unq(m.group(1))
                    if k not in out:
                        out.append(k)
    return out, plur


def po_esc(s):
    return s.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n')


def php_esc(s):
    return s.replace('\\', '\\\\').replace("'", "\\'")


def main():
    keys, plur = collect()
    en = json.load(open(os.path.join(ROOT, 'tools', 'en.json'), encoding='utf-8'))
    missing = [k for k in keys if k not in en]
    lang = os.path.join(ROOT, 'languages')
    head = 'msgid ""\nmsgstr ""\n"Content-Type: text/plain; charset=UTF-8\\n"\n"Project-Id-Version: Flux App\\n"\n'
    pot, po = [head], [head + '"Language: en_US\\n"\n"Plural-Forms: nplurals=2; plural=(n != 1);\\n"\n']
    for k in keys:
        if k in plur:
            t = en.get(k, ['', ''])
            pot.append('msgid "%s"\nmsgid_plural "%s"\nmsgstr[0] ""\nmsgstr[1] ""\n' % (po_esc(k), po_esc(plur[k])))
            po.append('msgid "%s"\nmsgid_plural "%s"\nmsgstr[0] "%s"\nmsgstr[1] "%s"\n' % (po_esc(k), po_esc(plur[k]), po_esc(t[0]), po_esc(t[1])))
        else:
            pot.append('msgid "%s"\nmsgstr ""\n' % po_esc(k))
            po.append('msgid "%s"\nmsgstr "%s"\n' % (po_esc(k), po_esc(en.get(k, ''))))
    open(os.path.join(lang, DOMAIN + '.pot'), 'w', encoding='utf-8').write('\n'.join(pot))
    open(os.path.join(lang, DOMAIN + '-en_US.po'), 'w', encoding='utf-8').write('\n'.join(po))
    # l10n.php
    rows = []
    for k in keys:
        if k not in en:
            continue
        v = en[k]
        if k in plur:
            z = "' . \"\\0\" . '"
            rows.append("'%s'=>'%s'" % (php_esc(k) + z + php_esc(plur[k]), php_esc(v[0]) + z + php_esc(v[1])))
        else:
            rows.append("'%s'=>'%s'" % (php_esc(k), php_esc(v)))
    php = "<?php\nreturn array('domain'=>'%s','plural-forms'=>'nplurals=2; plural=(n != 1);','language'=>'en_US','messages'=>array(%s));\n" % (DOMAIN, ','.join(rows))
    open(os.path.join(lang, DOMAIN + '-en_US.l10n.php'), 'w', encoding='utf-8').write(php)
    # .mo
    entries = {'': 'Content-Type: text/plain; charset=UTF-8\nLanguage: en_US\nPlural-Forms: nplurals=2; plural=(n != 1);\n'}
    for k in keys:
        if k not in en:
            continue
        if k in plur:
            entries[k + '\0' + plur[k]] = en[k][0] + '\0' + en[k][1]
        else:
            entries[k] = en[k]
    ids = sorted(entries)
    kb = [i.encode('utf-8') for i in ids]
    vb = [entries[i].encode('utf-8') for i in ids]
    n = len(ids)
    ko = 7 * 4
    vo = ko + n * 8
    data = 7 * 4 + n * 16
    kt, vt, blob = [], [], b''
    for b in kb:
        kt.append((len(b), data + len(blob)))
        blob += b + b'\0'
    for b in vb:
        vt.append((len(b), data + len(blob)))
        blob += b + b'\0'
    mo = struct.pack('<7I', 0x950412de, 0, n, ko, vo, 0, 0)
    for l, o in kt:
        mo += struct.pack('<2I', l, o)
    for l, o in vt:
        mo += struct.pack('<2I', l, o)
    open(os.path.join(lang, DOMAIN + '-en_US.mo'), 'wb').write(mo + blob)
    print('metin: %d, eksik: %d' % (len(keys), len(missing)))
    for m in missing:
        print('  EKSİK:', m)
    return 1 if missing else 0


if __name__ == '__main__':
    sys.exit(main())
