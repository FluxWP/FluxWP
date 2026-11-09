#!/usr/bin/env python3
"""Refresh existing site image/video derivatives while preserving every translated page text."""
import argparse,json,subprocess
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
def main():
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('preset',type=Path);p.add_argument('--package',help='Refresh only the package source folder slug');a=p.parse_args();preset=a.preset.resolve();count=0
 for page in sorted((preset/'data/pages').glob('*.json')):
  data=json.loads(page.read_text());source=ROOT/data['source'];
  if a.package and source.name!=a.package:continue
  images=source/'docs/images';dest=preset/'assets/shots'/data['slug'];dest.mkdir(parents=True,exist_ok=True)
  for name in data.get('shots',[])+([data['phone']] if data.get('phone') else []):
   from PIL import Image
   for lang in ['en','tr']:
    src=images/f'{name}-{lang}.jpg'
    if not src.exists():raise FileNotFoundError(f'{src}: recapture before refreshing site media')
    with Image.open(src) as image:
     if lang=='en':data.setdefault('dims',{})[name]=list(image.size)
     for width in ([360,720] if name==data.get('phone') else [640,1280]):
      out=dest/f'{name}-{lang}-{width}.webp';size=(width,round(image.height*width/image.width));image.resize(size,Image.Resampling.LANCZOS).save(out,quality=83,method=6);count+=1
  for lang,filename in [('en','demo.gif'),('tr','demo-tr.gif')]:
   src=source/'docs'/filename
   if src.exists():
    out=dest/f'tour-{lang}.mp4';subprocess.run(['ffmpeg','-v','error','-y','-i',str(src),'-vf','scale=trunc(iw/2)*2:trunc(ih/2)*2','-c:v','libx264','-pix_fmt','yuv420p','-crf','25','-movflags','+faststart','-an',str(out)],check=True)
    with Image.open(src) as image:data.setdefault('tour',{})[lang]=[image.width-image.width%2,image.height-image.height%2]
  page.write_text(json.dumps(data,ensure_ascii=False,indent='\t')+'\n')
 print(f'Refreshed {count} site image derivatives; page translations preserved')
if __name__=='__main__':main()
