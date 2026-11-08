#!/usr/bin/env python3
"""Translate shared WordPress embed resources using the same English/native package cache."""
import argparse,concurrent.futures,json
from pathlib import Path
from package_translations import LANGS,read,write,translate

def leaves(tree):
 if isinstance(tree,dict):return [s for k,v in tree.items() if not (k=='languages' and isinstance(v,dict)) for s in leaves(v)]
 if isinstance(tree,list):return [s for v in tree for s in leaves(v)]
 return [tree] if isinstance(tree,str) and tree.strip() else []
def fill(tree,cache):
 if isinstance(tree,dict):return {k:(v if k=='languages' and isinstance(v,dict) else fill(v,cache)) for k,v in tree.items()}
 if isinstance(tree,list):return [fill(v,cache) for v in tree]
 return cache.get(tree,tree) if isinstance(tree,str) else tree

def main():
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('--repo',required=True,type=Path);p.add_argument('--cache',required=True,type=Path);p.add_argument('--translate',action='store_true',help='Allow network translation for missing strings');a=p.parse_args();root=a.repo.resolve()
 files=[root/'packages/core/src/i18n/locales/en.json',root/'packages/runtime/src/i18n/en.json',root/'packages/modules/src/tasks/en.json'];sources=[read(f) for f in files]
 strings=sorted(set(s for tree in sources for s in leaves(tree)));jobs={}
 # Reuse native caches. Run generation commands sequentially for one cache directory.
 for lang in LANGS:
  cache_file=a.cache/'cache'/f'{lang}.json'
  if not cache_file.exists():raise ValueError('Generate native package caches first')
 if a.translate:
  with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:
   for future in [pool.submit(translate,lang,strings,a.cache,None) for lang in LANGS]:
    lang,cache=future.result();jobs[lang]=cache
 else:jobs={lang:read(a.cache/'cache'/f'{lang}.json') for lang in LANGS}
 for lang,cache in jobs.items():
  for file,source in zip(files,sources):
   if any(s not in cache for s in leaves(source)):raise ValueError('Missing React translation: '+lang)
   write(file.with_name(lang+'.json'),fill(source,cache))
 print('Shared React resources translated',len(strings),'unique strings into eight languages')
if __name__=='__main__':main()
