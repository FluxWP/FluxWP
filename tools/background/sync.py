#!/usr/bin/env python3
"""Bundle the dependency-free worker adapter into its three consumers; --check detects drift."""
from pathlib import Path
import sys

root = Path(__file__).resolve().parents[2]
source = (root / 'tools/background/runner.php').read_bytes()
failed = []
for slug in ('flx-mail', 'flx-certificates', 'flx-backup'):
    target = root / 'plugins' / slug / 'includes/flx-background.php'
    if '--check' in sys.argv:
        if not target.exists() or target.read_bytes() != source:
            failed.append(slug)
    else:
        target.write_bytes(source)
if failed:
    raise SystemExit('Background adapter differs: ' + ', '.join(failed))
