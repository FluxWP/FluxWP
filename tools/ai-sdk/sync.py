#!/usr/bin/env python3
"""Copies the Flux AI SDK (flx-ai-sdk/) into the packages that use it.

Usage:
    python3 tools/ai-sdk/sync.py [ROOT ...]            refresh every package that already has the SDK
    python3 tools/ai-sdk/sync.py --add plugins/flx-x   add it to a package (copy + require line)

ROOT is a repo with plugins/ and themes/ (default: this repo). Example, both repos:
    python3 tools/ai-sdk/sync.py . ../wp-extras

A package uses the SDK when <pkg>/includes|inc/flx-ai-sdk/ exists. The folder is overwritten and the main
file (plugin header file or functions.php) gets one require line after the ABSPATH guard (once).
"""
import os, sys, glob, shutil

SRC = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'flx-ai-sdk')
FILES = ('loader.php', 'core.php')
MARK = 'flx-ai-sdk/loader.php'


def main_file(pkg):
    slug = os.path.basename(os.path.normpath(pkg))
    f = os.path.join(pkg, slug + '.php')
    return f if os.path.exists(f) else os.path.join(pkg, 'functions.php')


def rel_dir(pkg):
    return 'includes' if os.path.isdir(os.path.join(pkg, 'includes')) else 'inc'


def add_require(path, rel):
    src = open(path, encoding='utf-8').read()
    if MARK in src:
        return False
    lines = src.split('\n')
    at = next(i for i, l in enumerate(lines) if 'ABSPATH' in l)
    if '{' in lines[at] and '}' not in lines[at]:
        at = next(i for i in range(at + 1, len(lines)) if lines[i].strip().startswith('}'))
    # After the flx-brand line when there is one, so both shared modules sit together.
    for i in range(at + 1, min(at + 4, len(lines))):
        if 'flx-brand/loader.php' in lines[i]:
            at = i
            break
    req = "require_once __DIR__ . '/" + rel + "/flx-ai-sdk/loader.php'; // Flux AI SDK (tools/ai-sdk)."
    lines[at + 1:at + 1] = ([] if 'flx-brand' in lines[at] else ['']) + [req]
    open(path, 'w', encoding='utf-8').write('\n'.join(lines))
    return True


def install(pkg):
    rel = rel_dir(pkg)
    dst = os.path.join(pkg, rel, 'flx-ai-sdk')
    os.makedirs(dst, exist_ok=True)
    for f in FILES:
        shutil.copyfile(os.path.join(SRC, f), os.path.join(dst, f))
    main = main_file(pkg)
    done = any(MARK in open(f, encoding='utf-8').read() for f in glob.glob(os.path.join(pkg, '*.php')) if f != main)
    added = not done and add_require(main, rel)
    print(('+ ' if added else '= ') + pkg)


def sync(root):
    for pkg in sorted(glob.glob(os.path.join(root, 'plugins', '*')) + glob.glob(os.path.join(root, 'themes', '*'))):
        if os.path.isdir(os.path.join(pkg, rel_dir(pkg), 'flx-ai-sdk')):
            install(pkg)


if __name__ == '__main__':
    args = sys.argv[1:]
    if args[:1] == ['--add']:
        for p in args[1:]:
            install(p.rstrip('/'))
    else:
        for r in (args or [os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..')]):
            sync(r)
