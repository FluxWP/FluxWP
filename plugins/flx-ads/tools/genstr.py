#!/usr/bin/env python3
"""flx-ads çeviri dosyalarını üretir. Kaynak metinler İngilizce (PHP __() / esc_html__() / _n()).
Türkçe karşılıklar tools/tr.json'da. languages/ altına .pot, en_US ve tr_TR için .po, .mo, .l10n.php
ve messages.<dil>.json yazar. Kullanım: python3 tools/genstr.py (eksik TR çeviri varsa listeler, çıkış 1)."""
import json, os, re, struct, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DOMAIN = 'flx-ads'
S = r"'((?:[^'\\]|\\.)*)'"
SINGLE = re.compile(r"\b(?:__|esc_html__|esc_attr__|_e|esc_html_e|esc_attr_e)\(\s*" + S + r"\s*,\s*'" + DOMAIN + "'")
PLURAL = re.compile(r"\b_n\(\s*" + S + r"\s*,\s*" + S + r"\s*,[^;]*?'" + DOMAIN + "'")


def unq(s):
    return s.replace("\\'", "'").replace('\\\\', '\\')


def collect():
    out, plur = [], {}
    for d, _, files in sorted(os.walk(ROOT)):
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


def write_mo(path, entries):
    ids = sorted(entries)
    kb = [i.encode('utf-8') for i in ids]
    vb = [entries[i].encode('utf-8') for i in ids]
    n = len(ids)
    ko, vo, data = 28, 28 + n * 8, 28 + n * 16
    kt, vt, blob = [], [], b''
    for b in kb:
        kt.append((len(b), data + len(blob)))
        blob += b + b'\0'
    for b in vb:
        vt.append((len(b), data + len(blob)))
        blob += b + b'\0'
    mo = struct.pack('<7I', 0x950412de, 0, n, ko, vo, 0, 0)
    for l, o in kt + vt:
        mo += struct.pack('<2I', l, o)
    open(path, 'wb').write(mo + blob)


def build(locale, keys, plur, tr):
    lang = os.path.join(ROOT, 'languages')
    head = 'msgid ""\nmsgstr ""\n"Content-Type: text/plain; charset=UTF-8\\n"\n"Project-Id-Version: Flux Ads\\n"\n'
    po = [head + '"Language: %s\\n"\n"Plural-Forms: nplurals=2; plural=(n != 1);\\n"\n' % locale]
    rows, entries, msgs = [], {'': 'Content-Type: text/plain; charset=UTF-8\nLanguage: %s\nPlural-Forms: nplurals=2; plural=(n != 1);\n' % locale}, {}
    z = "' . \"\\0\" . '"
    for k in keys:
        if k in plur:
            v = [k, plur[k]] if locale == 'en_US' else tr.get(k)
            if not v:
                continue
            po.append('msgid "%s"\nmsgid_plural "%s"\nmsgstr[0] "%s"\nmsgstr[1] "%s"\n' % (po_esc(k), po_esc(plur[k]), po_esc(v[0]), po_esc(v[1])))
            rows.append("'%s'=>'%s'" % (php_esc(k) + z + php_esc(plur[k]), php_esc(v[0]) + z + php_esc(v[1])))
            entries[k + '\0' + plur[k]] = v[0] + '\0' + v[1]
            msgs[k] = v
        else:
            v = k if locale == 'en_US' else tr.get(k)
            if v is None:
                continue
            po.append('msgid "%s"\nmsgstr "%s"\n' % (po_esc(k), po_esc(v)))
            rows.append("'%s'=>'%s'" % (php_esc(k), php_esc(v)))
            entries[k] = v
            msgs[k] = v
    base = os.path.join(lang, '%s-%s' % (DOMAIN, locale))
    open(base + '.po', 'w', encoding='utf-8').write('\n'.join(po))
    open(base + '.l10n.php', 'w', encoding='utf-8').write("<?php\nreturn array('domain'=>'%s','plural-forms'=>'nplurals=2; plural=(n != 1);','language'=>'%s','messages'=>array(%s));\n" % (DOMAIN, locale, ','.join(rows)))
    write_mo(base + '.mo', entries)
    short = 'en' if locale == 'en_US' else locale
    meta = {'format': 'fluxwp-package-translations/v1', ('language' if locale == 'en_US' else 'locale'): short}
    json.dump({'_meta': meta, 'messages': msgs}, open(os.path.join(lang, 'messages.%s.json' % short), 'w', encoding='utf-8'), ensure_ascii=False, indent=2)


def main():
    keys, plur = collect()
    tr = json.load(open(os.path.join(ROOT, 'tools', 'tr.json'), encoding='utf-8'))
    lang = os.path.join(ROOT, 'languages')
    head = 'msgid ""\nmsgstr ""\n"Content-Type: text/plain; charset=UTF-8\\n"\n"Project-Id-Version: Flux Ads\\n"\n'
    pot = [head]
    for k in keys:
        if k in plur:
            pot.append('msgid "%s"\nmsgid_plural "%s"\nmsgstr[0] ""\nmsgstr[1] ""\n' % (po_esc(k), po_esc(plur[k])))
        else:
            pot.append('msgid "%s"\nmsgstr ""\n' % po_esc(k))
    open(os.path.join(lang, DOMAIN + '.pot'), 'w', encoding='utf-8').write('\n'.join(pot))
    build('en_US', keys, plur, tr)
    build('tr_TR', keys, plur, tr)
    missing = [k for k in keys if k not in tr]
    extra = [k for k in tr if k not in keys]
    print('metin: %d, eksik TR: %d, fazla: %d' % (len(keys), len(missing), len(extra)))
    for m in missing:
        print('  EKSİK:', json.dumps(m, ensure_ascii=False))
    return 1 if missing else 0


if __name__ == '__main__':
    sys.exit(main())
