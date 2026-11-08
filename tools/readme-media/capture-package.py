#!/usr/bin/env python3
"""Capture current UI and refresh package media without regenerating README prose."""
import argparse,json,os,shutil,subprocess
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2];TOOLS=Path(__file__).resolve().parent
def main():
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('config',type=Path);p.add_argument('--env',type=Path,required=True);p.add_argument('--repo',type=Path,default=ROOT);p.add_argument('--area',choices=['plugins','themes'],default='plugins');p.add_argument('--language',choices=['en','tr'],action='append');p.add_argument('--no-shots',action='store_true');p.add_argument('--no-tour',action='store_true');a=p.parse_args()
 env={**os.environ,**json.loads(a.env.read_text())};cfg=json.loads(a.config.read_text());w=Path(env['WPENV']);slug=cfg['slug'];out=w/'out'/slug;package=a.repo/a.area/slug;docs=package/'docs';images=docs/'images'
 if not package.is_dir():p.error('Package folder does not exist')
 if not __import__('re').fullmatch(r'[a-z0-9][a-z0-9-]*',slug):p.error('Invalid package slug')
 images.mkdir(parents=True,exist_ok=True)
 for lang in a.language or ['en','tr']:
  if not a.no_shots:subprocess.run(['bun',str(TOOLS/'shoot.js'),str(a.config.resolve()),lang,str(out)],env=env,check=True)
  expected=[out/f'{s["name"]}-{lang}.jpg' for group in ['shots','mobile','dark'] for s in cfg.get(group,[])]
  if not expected or not all(x.exists() for x in expected):raise FileNotFoundError('A required fresh screenshot is missing; refusing to replace package media')
  for source in expected:shutil.copy2(source,images/source.name)
  # Preserve older documented entry points for packages introduced with a single wizard screenshot.
  if (out/f'wizard-{lang}.jpg').exists():shutil.copy2(out/f'wizard-{lang}.jpg',docs/f'wizard-{lang}.jpg')
  if slug=='fluxwp':shutil.copy2(out/f'home-{lang}.jpg',package/'screenshot.jpg' if lang=='en' else docs/'wizard.jpg')
  if not a.no_tour:
   result=subprocess.run(['bun',str(TOOLS/'tour.js'),str(a.config.resolve()),lang],env=env,capture_output=True,text=True,check=True);video=Path(result.stdout.strip().splitlines()[-1]);gif=docs/('demo.gif' if lang=='en' else 'demo-tr.gif')
   subprocess.run(['ffmpeg','-v','error','-y','-ss','1','-i',str(video),'-vf','fps=7,scale=800:-1:flags=lanczos,split[a][b];[a]palettegen=max_colors=80:stats_mode=diff[p];[b][p]paletteuse=dither=none:diff_mode=rectangle',str(gif)],check=True)
   if lang=='en':subprocess.run(['ffmpeg','-v','error','-y','-i',str(gif),'-vf','fps=5,scale=320:-1:flags=lanczos,split[a][b];[a]palettegen=max_colors=64[p];[b][p]paletteuse=dither=bayer:bayer_scale=4',str(docs/'thumb.gif')],check=True)
 print(f'Refreshed {slug}: captures verified; README content preserved')
if __name__=='__main__':main()
