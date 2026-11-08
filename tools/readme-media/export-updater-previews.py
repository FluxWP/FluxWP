#!/usr/bin/env python3
"""Publish package descriptions, icon references and fresh preview references as GitHub-owned catalogs.
The updater reads catalog/packages.json from each configured repository and caches immutable GitHub blobs.
"""
import argparse,hashlib,io,json,re,sys
from pathlib import Path
from PIL import Image
ROOT=Path(__file__).resolve().parents[2]
LANGUAGES=['en','tr','de','es','fr','pt-br','ja','zh','ar','ru']
def intro(path):
 if not path.exists():return ''
 text=path.read_text();match=re.search(r'^\*\*(.+?)\*\*<br>',text,re.M)
 value=match[1] if match else next((p for p in re.split(r'\n\s*\n',text) if p.strip() and not p.startswith(('#','<','[','!','-','|','**'))),'')
 value=re.sub(r'\[([^\]]+)\]\([^)]+\)',r'\1',value);value=' '.join(re.sub(r'<[^>]+>|[`*]','',value).split())
 return value if len(value)<=420 else value[:417].rsplit(' ',1)[0]+'…'
def reference(repo,path):
 data=path.read_bytes()
 if len(data)>8*1024*1024:raise ValueError(f'Preview asset exceeds 8 MB: {path}')
 return {'path':path.relative_to(repo).as_posix(),'sha':hashlib.sha1(b'blob '+str(len(data)).encode()+b'\0'+data).hexdigest(),'size':len(data)}
def main():
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('--repo',type=Path,default=ROOT);p.add_argument('--private-root',type=Path);p.add_argument('--site-preset',type=Path);p.add_argument('--seed-copy',type=Path,help='One-time import of the previous public introduction copy');p.add_argument('--translate',action='store_true');a=p.parse_args()
 pages={f.stem:json.loads(f.read_text()).get('page') for f in ((a.site_preset/'data/pages').glob('*.json') if a.site_preset else [])}
 seed=json.loads(a.seed_copy.read_text()) if a.seed_copy else {}
 if a.translate:
  sys.path.insert(0,str(ROOT/'tools/site-translations'));from translate_all import translate_batch
 def collect(repo,private):
  target=repo/'catalog/packages.json';old=json.loads(target.read_text()).get('packages',{}) if target.exists() else {};records={};copy_file=repo/'tools/readme-media/descriptions.json';curated=json.loads(copy_file.read_text()) if copy_file.exists() else {}
  for area in ['plugins','themes']:
   for folder in sorted((repo/area).iterdir()):
    if not folder.is_dir() or not (folder/'README.md').exists() or folder.name=='flx-migrate':continue
    slug=folder.name;prev=old.get(slug,{});initial=seed.get(slug,{}) if not private else {};desc=prev.get('desc',initial.get('description',{})).copy()
    if not prev:desc.update(curated.get(slug,{}))
    if not desc.get('en'):desc['en']=intro(folder/'README.md')
    if not desc.get('tr'):desc['tr']=intro(folder/'README.tr.md')
    if not desc.get('en'):raise ValueError(f'{slug}: missing English introduction')
    if a.translate:
     for lang in LANGUAGES:
      if not desc.get(lang):desc[lang]=translate_batch([desc['en']], 'zh-CN' if lang=='zh' else lang)[0]
    website=prev.get('website',{})
    if not website and not private:
     page=pages.get(slug);path='/plugins/'+page+'/' if page else '/plugins/'
     website={lang:'https://fluxwp.com'+('' if lang=='en' else '/'+lang)+path for lang in LANGUAGES}
    cfg_path=repo/'tools/readme-media/cfg'/f'{slug}.json'
    if not cfg_path.exists():cfg_path=ROOT/'tools/readme-media/cfg'/f'{slug}.json'
    cfg=json.loads(cfg_path.read_text());names=[s['name'] for s in cfg.get('shots',[])][:4];gallery={};previews=folder/'docs/previews';previews.mkdir(exist_ok=True)
    for lang in ['en','tr']:
     files=[folder/'docs/images'/f'{name}-{lang}.jpg' for name in names]
     if not files or not all(f.exists() for f in files):raise FileNotFoundError(f'{slug}/{lang}: capture and install all configured screenshots first')
     gallery[lang]=[]
     for source in files:
      dest=previews/(source.stem+'.webp')
      with Image.open(source) as im:
       im=im.convert('RGB');im.thumbnail((1440,1800),Image.Resampling.LANCZOS);im.save(dest,format='WEBP',quality=85,method=6)
      gallery[lang].append(reference(repo,dest))
    icons={};previous_icons=prev.get('icons',{})
    for kind in ['svg','pastel']:
     candidates=[repo/previous_icons[kind]['path']] if kind in previous_icons else []
     candidates += ([folder/'assets/icon-pastel.svg',folder/'docs/icon-pastel.svg'] if kind=='pastel' else [])+[folder/'assets/icon.svg',folder/'docs/icon.svg',folder/'docs/logo.svg',folder/'assets/images/logo/ikon.svg']
     icon=next((x for x in candidates if x.is_file() and x.resolve().is_relative_to(folder.resolve())),None)
     if icon:icons[kind]=reference(repo,icon)
    if not icons:raise FileNotFoundError(f'{slug}: package icon is missing')
    records[slug]={'desc':desc,'website':website,'icons':icons,'gallery':gallery}
  target.parent.mkdir(exist_ok=True);target.write_text(json.dumps({'schema':1,'packages':records},ensure_ascii=False,indent=2)+'\n');print(repo.name,len(records),'GitHub-owned package records:',target,flush=True)
 collect(a.repo.resolve(),False)
 if a.private_root:collect(a.private_root.resolve(),True)
if __name__=='__main__':main()
