#!/usr/bin/env python3
"""Restore spaces (and a few words) that the batch translation step removed around placeholders, offline.

The English source IDs of some packages lost the spaces next to printf/{name} placeholders
("of%sgoal", "Thank you,{ad}"). This tool finds them in three ways and repairs source literals,
every native catalog (JSON, PHP, PO, MO, POT) and the space-separated machine translations:

1. A git revision from before the batch step (--base): an older msgid that differs only in
   whitespace and is longer is the original.
2. Same file and line in --base and --batch (the batch commit itself): a whitespace-only or a
   listed word change (--overrides) is restored.
3. Remaining IDs where a placeholder touches a letter get one space, except wrapper
   placeholders ("the %1$sprivacy policy%2$s and") which keep hugging their words.

Run without --apply to print the planned changes.

    python3 tools/site-translations/repair_spacing.py --package plugins/flx-donate \
        --base f7df140^ --batch f7df140 --overrides overrides.json --apply
"""
import argparse, json, re, subprocess, sys, tempfile
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from package_translations import LANGS, mo, po, phpstr  # noqa: E402
from repair_transport_artifacts import clean_key  # noqa: E402

TOOL = Path(__file__).with_name('extract_gettext.php').resolve()
PH = r'(?:%(?:\d\$)?[sd]|\{[a-z_]+\})'
# Letters of space-separated scripts; Arabic tatweel (U+0640) joins a prefix to the next word, keep it.
L = r'[^\W\d_ـ]'
TOUCH = re.compile(L + r'[,:;.)]?' + PH + r'(?!\})|' + PH + L + r'|' + PH + r'\(|' + PH + r'[·→×]|[·→×]' + PH, re.U)
NO_SPACE = {'ja', 'zh_CN'}
LOCALES = [('en_US', 'en', 2, '(n != 1)'), ('tr_TR', 'tr', 2, '(n != 1)')] + list(LANGS.values())


def inventory(root):
    out = subprocess.check_output(['php', '-d', 'memory_limit=768M', str(TOOL)], input=json.dumps([str(root)]).encode())
    return json.loads(out).get(str(root), {})


def archive(rev, rel, dst):
    dst.mkdir(parents=True, exist_ok=True)
    data = subprocess.check_output(['git', 'archive', rev, rel])
    subprocess.run(['tar', '-x', '-C', str(dst)], input=data, check=True)
    return dst / rel


def bare(s):
    return re.sub(r'\s+', '', s)


def auto(s):
    """One space between a placeholder and a touching letter or (·→× separator)."""
    s = re.sub(r'(' + L + r'|[,:;.)])(' + PH + ')', r'\1 \2', s)
    s = re.sub(r'(' + PH + r')(' + L + r'|\()', r'\1 \2', s)
    return re.sub(r'\s*([·→×])\s*', r' \1 ', s)


def wrappers(english):
    """Placeholders that hug a word in the fixed English: opening (word after) and closing (word before)."""
    opening = {m.group(1) for m in re.finditer('(' + PH + ')' + L, english)}
    closing = {m.group(1) for m in re.finditer(L + '(' + PH + ')', english)}
    return opening, closing


def space_translation(value, english):
    opening, closing = wrappers(english)

    def before(m):
        return m.group(0) if m.group(2) in closing else m.group(1) + ' ' + m.group(2)

    def after(m):
        return m.group(0) if m.group(1) in opening else m.group(1) + ' ' + m.group(2)

    value = re.sub(r'(' + L + r'|[,:;،])(' + PH + ')', before, value)
    value = re.sub(r'(' + PH + r')(' + L + ')', after, value)
    if re.search(r' [·→×] ', english):
        value = re.sub(r'(?<=\S)([·→×])(?=\S)', r' \1 ', value)
    return value


def fix_form(form, base_fix):
    if form in base_fix:
        return base_fix[form]
    return auto(form) if TOUCH.search(form) else form


def split_key(key):
    ctx, _, body = key.rpartition('\x04')
    return (ctx + '\x04' if ctx else ''), body.split('\x00')


def plan(pkg, base, batch, overrides):
    domain = pkg.name
    now = inventory(pkg).get(domain, {})
    with tempfile.TemporaryDirectory() as tmp:
        tmp = Path(tmp)
        rel = str(pkg.resolve().relative_to(Path.cwd().resolve()))
        old_root = archive(base, rel, tmp / 'base')
        old = inventory(old_root).get(domain, {})
        pot = old_root / 'languages' / f'{domain}.pot'
        old_ids = set(old)
        if pot.exists():
            text = pot.read_text(encoding='utf-8')
            for m in re.finditer(r'^msgid(?:_plural)? ("(?:[^"\\]|\\.)*")((?:\n"(?:[^"\\]|\\.)*")*)', text, re.M):
                old_ids.add(json.loads(m.group(1)) + ''.join(json.loads(x) for x in re.findall(r'\n("(?:[^"\\]|\\.)*")', m.group(2))))
        old_forms = {f for k in old_ids for f in split_key(k)[1]}
        by_bare = {}
        for f in old_forms:
            by_bare.setdefault(bare(f), set()).add(f)
        located = {}
        if batch:
            mid = inventory(archive(batch, rel, tmp / 'batch')).get(domain, {})
            at = {}
            for k, v in old.items():
                at.setdefault((v['file'], v['line']), []).append(k)
            for k, v in mid.items():
                hit = at.get((v['file'], v['line']))
                if hit and len(hit) == 1 and k not in old:
                    located[clean_key(k)] = hit[0]
    aliases = {}
    for key in now:
        if key in overrides:
            aliases[key] = overrides[key]
            continue
        prefix, forms = split_key(key)
        src = located.get(key)
        if src and bare(src) == bare(key) and len(src) > len(key) and src not in now:
            aliases[key] = src
            continue
        base_fix = {f: f for f in forms if f in old_forms}  # unchanged since --base: already right
        for f in forms:
            if f in base_fix:
                continue
            cands = {c for c in by_bare.get(bare(f), ()) if len(c) > len(f)}
            if len(cands) == 1:
                base_fix[f] = cands.pop()
        new = prefix + '\x00'.join(fix_form(f, base_fix) for f in forms)
        if new != key:
            aliases[key] = new
    return now, aliases


def php_export(files):
    code = '$o=[];foreach(json_decode(file_get_contents("php://stdin"),true) as $f){$o[$f]=require $f;}echo json_encode($o,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);'
    return json.loads(subprocess.check_output(['php', '-r', code], input=json.dumps(files).encode()))


def php_value(value):
    if isinstance(value, dict):
        return 'array(' + ','.join(phpstr(str(k)) + '=>' + php_value(v) for k, v in value.items()) + ')'
    if isinstance(value, list):
        return 'array(' + ','.join(php_value(v) for v in value) + ')'
    if isinstance(value, bool):
        return 'true' if value else 'false'
    if value is None:
        return 'null'
    return phpstr(value) if isinstance(value, str) else str(value)


def rekey(messages, aliases, source, locale, translations):
    """Rename broken keys and repair the values of source keys; other (legacy alias) keys stay as they are."""
    out = {}
    for key, value in messages.items():
        target = aliases.get(key, key)
        if target not in source:
            prefix, forms = split_key(key)
            if prefix + '\x00'.join(fix_form(f, {}) for f in forms) in source and key not in source:
                continue  # stale broken copy of a key that the source already has fixed
            out.setdefault(key, value)
            continue
        if target in out and target != key:
            continue
        english = target.split('\x04')[-1]
        if target in translations.get(locale, {}):
            value = translations[locale][target]
        elif locale == 'en_US':
            value = english
        elif locale != 'tr_TR' and locale not in NO_SPACE:
            eforms = english.split('\x00')
            value = '\x00'.join(space_translation(v, eforms[min(i, len(eforms) - 1)]) for i, v in enumerate(value.split('\x00')))
        out[target] = value
    return out


def apply(pkg, now, aliases, translations):
    domain = pkg.name
    root = str(pkg.resolve())
    subprocess.run(['php', '-d', 'memory_limit=768M', str(TOOL)], input=json.dumps({'packages': {root: {'domain': domain, 'aliases': aliases}}}).encode(), check=True, stdout=subprocess.DEVNULL)
    source = set(aliases.get(k, k) for k in now)
    folder = pkg / 'languages'
    natives = php_export([str(f.resolve()) for f in folder.glob('*.l10n.php')])
    for locale, _, n, plural in LOCALES:
        jp = folder / ('messages.' + ('en' if locale == 'en_US' else locale) + '.json')
        lf = (folder / f'{domain}-{locale}.l10n.php').resolve()
        if not jp.exists() or str(lf) not in natives:
            continue
        data = json.loads(jp.read_text(encoding='utf-8'))
        data['messages'] = rekey(data['messages'], aliases, source, locale, translations)
        jp.write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
        base = natives[str(lf)]
        native = rekey(base['messages'], aliases, source, locale, translations)
        for key, value in data['messages'].items():
            if key in source:
                native[key] = value
        base['messages'] = native
        text = '<?php\nreturn array(\n'
        for key, value in base.items():
            if key == 'messages':
                text += ' ' + phpstr(key) + ' => array(\n' + ''.join('  ' + phpstr(k) + ' => ' + phpstr(v) + ',\n' for k, v in native.items()) + '\n ),\n'
            else:
                text += ' ' + phpstr(key) + ' => ' + php_value(value) + ',\n'
        lf.write_text(text + ');\n', encoding='utf-8')
        (folder / f'{domain}-{locale}.mo').write_bytes(mo(native, locale, n, plural))
        (folder / f'{domain}-{locale}.po').write_text(po(data['messages'], locale, n, plural), encoding='utf-8')
        if locale == 'en_US':
            (folder / f'{domain}.pot').write_text(po(data['messages'], 'en_US', 2, '(n != 1)', True), encoding='utf-8')
        print('Repaired', domain, locale, flush=True)


def main():
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument('--package', type=Path, required=True, help='plugins/<slug> or themes/<slug>, relative to the repo root (run from the repo root)')
    p.add_argument('--base', required=True, help='git revision from before the batch translation step')
    p.add_argument('--batch', help='git revision of the batch translation step (for same-line word changes)')
    p.add_argument('--overrides', type=Path, help='JSON: {"source": {broken key: fixed key}, "translations": {locale: {fixed key: value}}}')
    p.add_argument('--apply', action='store_true')
    a = p.parse_args()
    ov = json.loads(a.overrides.read_text(encoding='utf-8')) if a.overrides else {}
    now, aliases = plan(a.package, a.base, a.batch, ov.get('source', {}))
    for k, v in aliases.items():
        print(repr(k), '\n   ->', repr(v))
    print('Source keys:', len(aliases))
    if a.apply:
        apply(a.package, now, aliases, ov.get('translations', {}))


if __name__ == '__main__':
    main()
