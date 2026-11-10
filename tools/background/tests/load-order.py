#!/usr/bin/env python3
"""Check standalone bundles, every plugin order and additive APIs after a legacy guard."""
import argparse
import itertools
import json
from pathlib import Path
import subprocess

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--php', default='php')
args = parser.parse_args()
root = Path(__file__).resolve().parents[3]
packages = json.loads((root / 'tools/background/consumers.json').read_text())
bundles = [str(root / p / 'includes/flx-background.php') for p in packages]
assert bundles, 'No consumers found'
bootstrap = "define('ABSPATH', '/'); $hooks=[]; function add_action($hook,$callback,$priority=10){global $hooks;$hooks[]=$hook;}"
legacy = "function flx_background_worker_active(){return false;} add_action('init','flx_background_worker_heartbeat',99);"
assertions = 0
# Pairwise ordering remains bounded as consumers are added; permutations exercise
# all current load orders without introducing factorial CI growth for large sets.
orders = [(p,) for p in bundles]
orders += list(itertools.permutations(bundles)) if len(bundles) <= 4 else list(itertools.permutations(bundles, 2))
for order in orders:
    for old_first in (False, True):
        code = bootstrap + (legacy if old_first else '')
        code += ''.join('require ' + json.dumps(path) + ';' for path in order)
        code += "echo json_encode([function_exists('flx_background_run'),count($hooks),function_exists('flx_background_scheduler_version')]);"
        result = subprocess.run([args.php, '-r', code], check=True, capture_output=True, text=True)
        assert json.loads(result.stdout) == [True, 2, not old_first], (order, old_first, result.stdout)
        assertions += 1
print(f'{assertions} independent/mixed-order library loads passed')
