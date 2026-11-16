#!/usr/bin/env python3
"""Builds the dictionaries of the new React screen (packages/modules/src/studio/i18n/*.json).

Keys are the English strings used in `t('...')` calls of packages/modules/src/studio. Translations come from the
plugin's language packs (languages/*.po: the classic screen uses the same wording) and from
tools/i18n-react-extra.json for strings only the new screen has: { "English": { "tr": "...", "de": "..." } }.
Run from the repository root:  python3 plugins/flx-studio/tools/i18n-react.py [--missing]
"""
import json
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parents[3]
SRC = ROOT / 'packages/modules/src/studio'
LANGS = ROOT / 'plugins/flx-studio/languages'
EXTRA = pathlib.Path(__file__).with_name('i18n-react-extra.json')
MAP = {'tr': 'tr_TR', 'de': 'de_DE', 'es': 'es_ES', 'fr': 'fr_FR', 'pt-br': 'pt_BR', 'ja': 'ja', 'zh': 'zh_CN', 'ar': 'ar', 'ru': 'ru_RU'}


def unq(s: str) -> str:
    return re.sub(r'\\(.)', lambda m: {'n': '\n', 't': '\t'}.get(m.group(1), m.group(1)), s)


def sources() -> list[str]:
    found: list[str] = []
    for f in sorted(SRC.glob('*.ts*')):
        for m in re.finditer(r"\bt\(\s*'((?:[^'\\]|\\.)*)'", f.read_text(encoding='utf-8')):
            s = unq(m.group(1))
            if s not in found:
                found.append(s)
    return found


def po(path: pathlib.Path) -> dict[str, str]:
    out: dict[str, str] = {}
    mid = mstr = None
    cur = None
    for line in path.read_text(encoding='utf-8').splitlines() + ['']:
        if line.startswith('msgid '):
            if mid and mstr:
                out[mid] = mstr
            mid, mstr, cur = unq(line[6:].strip()[1:-1]), '', 'id'
        elif line.startswith('msgstr '):
            mstr, cur = unq(line[7:].strip()[1:-1]), 'str'
        elif line.startswith('"') and cur:
            part = unq(line.strip()[1:-1])
            if cur == 'id':
                mid = (mid or '') + part
            else:
                mstr = (mstr or '') + part
        elif not line.strip():
            if mid and mstr:
                out[mid] = mstr
            mid = mstr = cur = None
    return out


def main() -> None:
    keys = sources()
    extra = json.loads(EXTRA.read_text(encoding='utf-8')) if EXTRA.exists() else {}
    missing: dict[str, list[str]] = {}
    for lang, loc in MAP.items():
        packs = po(LANGS / f'flx-studio-{loc}.po')
        d: dict[str, str] = {}
        for k in keys:
            v = extra.get(k, {}).get(lang) or packs.get(k)
            if v:
                d[k] = v
            else:
                missing.setdefault(k, []).append(lang)
        (SRC / 'i18n' / f'{lang}.json').write_text(json.dumps(d, ensure_ascii=False, indent=1, sort_keys=True) + '\n', encoding='utf-8')
    print(f'{len(keys)} strings; {len(missing)} without a translation in some language')
    if '--missing' in sys.argv:
        for k, ls in missing.items():
            print(f'  {k!r}: {",".join(ls)}')


main()
