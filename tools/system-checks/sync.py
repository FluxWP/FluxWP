#!/usr/bin/env python3
"""Sync the shared system-checks file; --check detects drift."""
import argparse
import json
from pathlib import Path

root = Path(__file__).resolve().parents[2]
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--check', action='store_true')
args = parser.parse_args()
source = (root / 'tools/system-checks/flx-system-checks.php').read_bytes()
targets = json.loads((root / 'tools/system-checks/consumers.json').read_text())
failed = []
for rel in targets:
    target = root / rel
    if args.check:
        if not target.is_file() or target.read_bytes() != source:
            failed.append(rel)
    else:
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(source)
if failed:
    raise SystemExit('System checks copy differs: ' + ', '.join(failed))
