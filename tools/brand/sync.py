#!/usr/bin/env python3
"""Copies the FluxWP brand signature module (flx-brand/) into every package and loads it.

Usage: python3 tools/brand/sync.py [--check] [ROOT ...]
ROOT is a repo with plugins/ and themes/ (default: this repo). Example, both repos:
    python3 tools/brand/sync.py . ../wp-extras

For each package: <pkg>/includes|inc/flx-brand/{loader.php,core.php} is overwritten and the main file
(plugin header file or functions.php) gets one require line after the ABSPATH guard (once).
--check compares those copies and the require line, prints differing paths and exits 1.
"""
import os, sys, glob, shutil

SRC = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'flx-brand')
FILES = ('loader.php', 'core.php', 'people.php', 'events.php')
DIRS = ('phone', 'field')  # 1.2.0: shared phone field assets; 1.5.0: shared form fields
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


def rel_files(base):
    if not os.path.isdir(base):
        return []
    out = []
    for dirpath, _, names in os.walk(base):
        for name in names:
            out.append(os.path.relpath(os.path.join(dirpath, name), base))
    return out


def same(a, b):
    if not (os.path.isfile(a) and os.path.isfile(b)):
        return False
    return open(a, 'rb').read() == open(b, 'rb').read()


def drift(pkg, dst, main):
    bad = []
    for f in FILES:
        dest = os.path.join(dst, f)
        if not same(os.path.join(SRC, f), dest):
            bad.append(os.path.relpath(dest))
    for d in DIRS:
        src_d, dst_d = os.path.join(SRC, d), os.path.join(dst, d)
        src_names, dst_names = set(rel_files(src_d)), set(rel_files(dst_d))
        for name in sorted(src_names | dst_names):
            dest = os.path.join(dst_d, name)
            if name not in src_names or name not in dst_names or not same(os.path.join(src_d, name), dest):
                bad.append(os.path.relpath(dest))
    tops = [f for f in glob.glob(os.path.join(pkg, '*.php')) if os.path.isfile(f)]
    if not any(MARK in open(f, encoding='utf-8').read() for f in tops):
        bad.append(os.path.relpath(main))
    return bad


def sync(root, check=False):
    failed = []
    for pkg in sorted(glob.glob(os.path.join(root, 'plugins', '*')) + glob.glob(os.path.join(root, 'themes', '*'))):
        if not os.path.isdir(pkg):
            continue
        main = main_file(pkg)
        if not os.path.exists(main):
            continue
        rel = 'includes' if os.path.isdir(os.path.join(pkg, 'includes')) else 'inc'
        dst = os.path.join(pkg, rel, 'flx-brand')
        if check:
            failed += drift(pkg, dst, main)
            continue
        os.makedirs(dst, exist_ok=True)
        for f in FILES:
            shutil.copyfile(os.path.join(SRC, f), os.path.join(dst, f))
        for d in DIRS:
            if os.path.isdir(os.path.join(dst, d)):
                shutil.rmtree(os.path.join(dst, d))
            shutil.copytree(os.path.join(SRC, d), os.path.join(dst, d))
        # Already required from another top-level file (e.g. flx-forms-load.php)?
        done = any(MARK in open(f, encoding='utf-8').read() for f in glob.glob(os.path.join(pkg, '*.php')) if f != main)
        print(('+ ' if not done and add_require(main, rel) else '= ') + os.path.relpath(pkg, root))
    return failed


if __name__ == '__main__':
    check = '--check' in sys.argv[1:]
    roots = [a for a in sys.argv[1:] if a != '--check'] or [os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..')]
    failed = []
    for r in roots:
        failed += sync(r, check)
    if failed:
        print('\n'.join(failed))
        raise SystemExit(1)
