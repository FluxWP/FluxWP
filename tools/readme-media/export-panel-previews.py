#!/usr/bin/env python3
"""Bundle short public-package introductions and existing example-image URLs for FluxWP Panel.
Default is offline. --translate explicitly uses the developer-side translation service.
"""
import argparse,json,re,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
OUTPUT=ROOT/'plugins/flx-setup/assets/package-previews.json'
SITE_PAGES={'flx-setup','flx-admin','flx-roles','flx-app','flx-ai','flx-tasks','flx-backup','flx-speed','flx-chat','flx-mail','flx-forms','flx-social','flx-seo','flx-analytics','flx-lang','flx-studio','flx-wiki','flx-donate','flx-certificates'}
RAW='https://raw.githubusercontent.com/FluxWP/FluxWP/main/'
def intro(path):
 if not path.exists():return ''
 text=path.read_text();m=re.search(r'^\*\*(.+?)\*\*<br>',text,re.M)
 if m:value=m[1]
 else:
  value=''
  for paragraph in re.split(r'\n\s*\n',text):
   if paragraph.startswith(('#','<','[','!','-','|','**')):continue
   if paragraph.strip():value=paragraph;break
 value=re.sub(r'\[([^\]]+)\]\([^)]+\)',r'\1',value)
 value=re.sub(r'<[^>]+>|[`*]','',value);value=' '.join(value.split())
 return value if len(value)<=320 else value[:317].rsplit(' ',1)[0]+'…'
def generate(translate=False):
 old=json.loads(OUTPUT.read_text()) if OUTPUT.exists() else {};out={}
 for folder in sorted((ROOT/'plugins').glob('flx-*')):
  if folder.name=='flx-migrate' or not (folder/'README.md').exists():continue
  cfg=ROOT/'tools/readme-media/cfg'/f'{folder.name}.json';config=json.loads(cfg.read_text()) if cfg.exists() else {}
  desc={'en':intro(folder/'README.md'),'tr':intro(folder/'README.tr.md')};images={}
  for lang in ('en','tr'):
   files=[folder/'docs/images'/f'{shot["name"]}-{lang}.jpg' for shot in config.get('shots',[])]
   if not any(p.exists() for p in files):files=[folder/'docs'/f'wizard-{lang}.jpg']
   images[lang]=[RAW+p.relative_to(ROOT).as_posix() for p in files if p.exists()][:2]
  previous=old.get(folder.name,{}).get('description',{})
  if previous.get('en')==desc['en']:desc={**previous,**desc}
  out[folder.name]={'description':desc,'images':images,'website':'/plugins/'+folder.name.replace('flx-','flux-',1)+'/' if folder.name in SITE_PAGES else '/plugins/'}
 if translate:
  sys.path.insert(0,str(ROOT/'tools/site-translations'));from translate_all import translate_batch,translate_numeric_markers
  from validator import protected
  from concurrent.futures import ThreadPoolExecutor
  def fill(lang):
   for package in out.values():
    src=package['description']['en']
    if not package['description'].get(lang):
     value=translate_batch([src],lang if lang!='zh' else 'zh-CN')[0]
     if protected(src)!=protected(value):value=translate_numeric_markers(src,lang if lang!='zh' else 'zh-CN')
     package['description'][lang]=value
  with ThreadPoolExecutor(max_workers=4) as pool:list(pool.map(fill,['de','es','fr','pt-br','ja','zh','ar','ru']))
 OUTPUT.write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n')
 print('Bundled',len(out),'public package introductions; no private package metadata')
if __name__=='__main__':
 parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--translate',action='store_true');args=parser.parse_args();generate(args.translate)
