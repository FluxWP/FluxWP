#!/usr/bin/env python3
"""Çeviri dizelerini toplar: JS t('...') ve PHP __('...', 'flx-speed').
Çıktı: includes/js-strings.php, languages/flx-speed.pot, tools/strings.json (eksik EN çevirileri listesi).
EN çeviriler tools/en.json'dan okunur -> languages/flx-speed-en_US.l10n.php + .po"""
import json, os, re, sys
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
JS_RE = re.compile(r"""\bt\(\s*'((?:[^'\\]|\\.)*)'""")
PHP_RE = re.compile(r"""\b(?:__|_e|esc_html__|esc_attr__|esc_html_e|esc_attr_e)\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'flx-speed'""")
PHP_N = re.compile(r"""\b_n\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'((?:[^'\\]|\\.)*)'""")
def unq(s): return re.sub(r"\\(.)", r"\1", s)
def php_q(s): return "'" + s.replace("\\", "\\\\").replace("'", "\\'") + "'"
js, php = [], []
for d, _, fs in os.walk(ROOT):
    if '/tools' in d: continue
    for f in sorted(fs):
        if not (f.endswith('.js') or f.endswith('.php')): continue
        p = os.path.join(d, f); src = open(p, encoding='utf-8').read()
        if f.endswith('.js'):
            for m in JS_RE.finditer(src):
                s = unq(m.group(1))
                if s not in js: js.append(s)
        elif f.endswith('.php') and f != 'js-strings.php' and not f.endswith('.l10n.php'):
            for m in PHP_RE.finditer(src):
                s = unq(m.group(1))
                if s not in php: php.append(s)
            for m in PHP_N.finditer(src):
                for g in (1, 2):
                    s = unq(m.group(g))
                    if s not in php: php.append(s)
out = ["<?php", "/** Otomatik üretildi: tools/genstr.py — JS arayüz dizeleri (çeviri için). */", "defined( 'ABSPATH' ) || exit;", "", "function flxp_js_strings() {", "\treturn array("]
for s in js: out.append("\t\t%s => __( %s, 'flx-speed' )," % (php_q(s), php_q(s)))
out += ["\t);", "}", ""]
open(os.path.join(ROOT, 'includes/js-strings.php'), 'w', encoding='utf-8').write("\n".join(out))
allk = php + [s for s in js if s not in php]
def po_q(s): return '"' + s.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n') + '"'
os.makedirs(os.path.join(ROOT, 'languages'), exist_ok=True)
head = 'msgid ""\nmsgstr ""\n"Content-Type: text/plain; charset=UTF-8\\n"\n"Project-Id-Version: Flux Speed\\n"\n'
pot = head + "".join("\nmsgid %s\nmsgstr \"\"\n" % po_q(s) for s in allk)
open(os.path.join(ROOT, 'languages/flx-speed.pot'), 'w', encoding='utf-8').write(pot)
enp = os.path.join(ROOT, 'tools/en.json')
en = json.load(open(enp, encoding='utf-8')) if os.path.exists(enp) else {}
missing = [s for s in allk if s not in en]
json.dump(missing, open(os.path.join(ROOT, 'tools/missing.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=0)
msgs = {s: en[s] for s in allk if s in en}
l10n = ["<?php", "return array(", "\t'language' => 'en_US',", "\t'plural-forms' => 'nplurals=2; plural=(n != 1);',", "\t'messages' => array("]
for k, v in msgs.items(): l10n.append("\t\t%s => %s," % (php_q(k), php_q(v)))
l10n += ["\t),", ");", ""]
open(os.path.join(ROOT, 'languages/flx-speed-en_US.l10n.php'), 'w', encoding='utf-8').write("\n".join(l10n))
po = head.replace('Flux Speed\\n"', 'Flux Speed\\n"\n"Language: en_US\\n"') + "".join("\nmsgid %s\nmsgstr %s\n" % (po_q(k), po_q(v)) for k, v in msgs.items())
open(os.path.join(ROOT, 'languages/flx-speed-en_US.po'), 'w', encoding='utf-8').write(po)
print("js=%d php=%d total=%d missing=%d" % (len(js), len(php), len(allk), len(missing)))

# .mo (msgfmt yoksa): GNU gettext ikili biçimi.
import struct
def write_mo(path, msgs):
    keys = sorted(msgs.keys())
    ids = b''; strs = b''; offs = []
    for k in keys:
        kb = k.encode('utf-8'); vb = msgs[k].encode('utf-8')
        offs.append((len(ids), len(kb), len(strs), len(vb)))
        ids += kb + b'\0'; strs += vb + b'\0'
    n = len(keys); ko = 7 * 4; vo = ko + n * 8; idso = vo + n * 8; stro = idso + len(ids)
    out = struct.pack('Iiiiiii', 0x950412de, 0, n, ko, vo, 0, 0)
    out += b''.join(struct.pack('ii', l, idso + o) for o, l, _, _ in offs)
    out += b''.join(struct.pack('ii', l, stro + o) for _, _, o, l in offs)
    out += ids + strs
    open(path, 'wb').write(out)
hdr = {'': 'Content-Type: text/plain; charset=UTF-8\nLanguage: en_US\nPlural-Forms: nplurals=2; plural=(n != 1);\n'}
write_mo(os.path.join(ROOT, 'languages/flx-speed-en_US.mo'), dict(hdr, **msgs))
