#!/usr/bin/env python3
"""Copies the FluxWP brand signature module (flx-brand/) into every package and loads it.

Usage: python3 tools/brand/sync.py [ROOT ...]
ROOT is a repo with plugins/ and themes/ (default: this repo). Example, both repos:
    python3 tools/brand/sync.py . ../wp-extras

For each package: <pkg>/includes|inc/flx-brand/{loader.php,core.php} is overwritten and the main file
(plugin header file or functions.php) gets one require line after the ABSPATH guard (once).
"""
import os, sys, glob, shutil

SRC = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'flx-brand')
FILES = ('loader.php', 'core.php')
MARK = 'flx-brand/loader.php'


def main_file(pkg):
    slug = os.path.basename(pkg)
    f = os.path.join(pkg, slug + '.php')
    return f if os.path.exists(f) else os.path.join(pkg, 'functions.php')


def add_require(path, rel):
    src = open(path, encoding='utf-8').read()
    if MARK in src:
        return False
    lines = src.split('\n')
    at = next(i for i, l in enumerate(lines) if 'ABSPATH' in l)
    if '{' in lines[at] and '}' not in lines[at]:
        at = next(i for i in range(at + 1, len(lines)) if lines[i].strip().startswith('}'))
    req = "require_once __DIR__ . '/" + rel + "/flx-brand/loader.php'; // FluxWP signature (tools/brand)."
    lines[at + 1:at + 1] = ['', req]
    open(path, 'w', encoding='utf-8').write('\n'.join(lines))
    return True


def sync(root):
    for pkg in sorted(glob.glob(os.path.join(root, 'plugins', '*')) + glob.glob(os.path.join(root, 'themes', '*'))):
        if not os.path.isdir(pkg):
            continue
        main = main_file(pkg)
        if not os.path.exists(main):
            continue
        rel = 'includes' if os.path.isdir(os.path.join(pkg, 'includes')) else 'inc'
        dst = os.path.join(pkg, rel, 'flx-brand')
        os.makedirs(dst, exist_ok=True)
        for f in FILES:
            shutil.copyfile(os.path.join(SRC, f), os.path.join(dst, f))
        # Already required from another top-level file (e.g. flx-forms-load.php)?
        done = any(MARK in open(f, encoding='utf-8').read() for f in glob.glob(os.path.join(pkg, '*.php')) if f != main)
        print(('+ ' if not done and add_require(main, rel) else '= ') + os.path.relpath(pkg, root))


if __name__ == '__main__':
    for r in (sys.argv[1:] or [os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..')]):
        sync(r)
