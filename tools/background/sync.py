#!/usr/bin/env python3
"""Sync opted-in plugin/theme bundles; --add registers a consumer, --check detects drift."""
import argparse
import json
from pathlib import Path

root = Path(__file__).resolve().parents[2]
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--check', action='store_true')
parser.add_argument('--add', action='append', default=[], metavar='PACKAGE')
args = parser.parse_args()
sources = {'flx-background.php': (root / 'tools/background/runner.php').read_bytes(), 'flx-background-parallel.php': (root / 'tools/background/flx-background-parallel.php').read_bytes()}
manifest = root / 'tools/background/consumers.json'
packages = set(json.loads(manifest.read_text())) | set(args.add)
targets = set()
for package in sorted(packages):
    path = (root / package).resolve()
    if path.parent not in (root / 'plugins', root / 'themes') or not path.is_dir():
        parser.error('--add requires an existing direct plugin/theme package in this repository')
    for name in sources:
        targets.add(path / 'includes' / name)
if args.add and not args.check:
    manifest.write_text(json.dumps(sorted(packages), indent=2) + '\n')
failed = []
for target in sorted(targets):
    source = sources[target.name]
    if args.check:
        if not target.exists() or target.read_bytes() != source:
            failed.append(str(target.relative_to(root)))
    else:
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(source)
if failed:
    raise SystemExit('Background adapter differs: ' + ', '.join(failed))
