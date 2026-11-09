#!/usr/bin/env python3
"""Create an isolated, local-only screenshot site. Never connects to a production WordPress database."""
import argparse,json,shutil,subprocess,urllib.request,zipfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
def main():
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('--work',type=Path,default=ROOT.parent/'artifacts/readme-media');p.add_argument('--private-root',type=Path);p.add_argument('--port',type=int,default=8888);p.add_argument('--project',default='fluxwp-readme-media');a=p.parse_args()
 w=a.work.resolve();w.mkdir(parents=True,exist_ok=True);base=f'http://127.0.0.1:{a.port}';repos=[ROOT]+([a.private_root.resolve()] if a.private_root else [])
 if not 1024<=a.port<=65535 or not __import__('re').fullmatch(r'[a-z0-9][a-z0-9-]+',a.project):p.error('Invalid local port or Docker project')
 (w/'media.ini').write_text('memory_limit=768M\nmax_execution_time=120\nupload_max_filesize=120M\npost_max_size=128M\n')
 compose={'services':{'db':{'image':'mariadb:11.4','environment':{'MARIADB_ROOT_PASSWORD':'capture-local-only','MARIADB_DATABASE':'wordpress','MARIADB_USER':'wordpress','MARIADB_PASSWORD':'capture-local-only'},'volumes':['db:/var/lib/mysql']},'wordpress':{'image':'wordpress:7.1.4-php8.3-apache','depends_on':['db'],'ports':[f'127.0.0.1:{a.port}:80'],'environment':{'WORDPRESS_DB_HOST':'db','WORDPRESS_DB_USER':'wordpress','WORDPRESS_DB_PASSWORD':'capture-local-only','WORDPRESS_DB_NAME':'wordpress','WORDPRESS_DEBUG':'1','WORDPRESS_CONFIG_EXTRA':f"define('WP_HOME','{base}'); define('WP_SITEURL','{base}'); define('WP_DEBUG_LOG',true); define('WP_DEBUG_DISPLAY',false); define('DISABLE_WP_CRON',true); define('WP_HTTP_BLOCK_EXTERNAL',true);"},'volumes':[f'{w}/site:/var/www/html',f'{w}:{w}',f'{w}/media.ini:/usr/local/etc/php/conf.d/media.ini:ro']+[f'{r}:{r}:ro' for r in repos]}},'volumes':{'db':{}}}
 file=w/'compose.json';file.write_text(json.dumps(compose,indent=2));cmd=['docker','compose','-p',a.project,'-f',str(file)];subprocess.run(cmd+['up','-d'],check=True)
 # The official WP-CLI build is confined to this local fixture environment.
 cli=w/'wp-cli.phar'
 if not cli.exists():urllib.request.urlretrieve('https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar',cli)
 def php(*args,check=True):return subprocess.run(cmd+['exec','-T','-e',f'WPENV={w}','wordpress','php',*map(str,args)],check=check)
 for _ in range(30):
  if (w/'site/wp-config.php').exists():break
  __import__('time').sleep(1)
 if php(cli,'core','is-installed','--allow-root',check=False).returncode:php(cli,'core','install','--allow-root',f'--url={base}','--title=FluxWP Demo','--admin_user=admin','--admin_password=admin12345','--admin_email=demo@example.test','--skip-email')
 site=w/'site';plugins=site/'wp-content/plugins';wc=w/'woocommerce.zip'
 if not (plugins/'woocommerce').exists():
  urllib.request.urlretrieve('https://downloads.wordpress.org/plugin/woocommerce.11.2.2.zip',wc)
  with zipfile.ZipFile(wc) as archive:archive.extractall(plugins)
 for repo in repos:
  for area in ['plugins','themes']:
   for package in (repo/area).iterdir():
    dest=site/'wp-content'/area/package.name
    if package.is_dir() and not dest.exists():dest.symlink_to(package,target_is_directory=True)
 mu=site/'wp-content/mu-plugins';mu.mkdir(exist_ok=True)
 for source in (ROOT/'tools/readme-media/env/mu-plugins').glob('*.php'):shutil.copy2(source,mu/source.name)
 lang=w/'tr.zip'
 if not lang.exists():urllib.request.urlretrieve('https://downloads.wordpress.org/translation/core/7.1.4/tr_TR.zip',lang)
 with zipfile.ZipFile(lang) as archive:archive.extractall(site/'wp-content/languages')
 php(cli,'plugin','activate','woocommerce','--allow-root')
 slugs=[x.name for x in (ROOT/'plugins').iterdir() if x.is_dir() and x.name!='flx-migrate']
 php(ROOT/'tools/readme-media/env/act.php',*slugs);php(cli,'theme','activate','fluxwp','--allow-root')
 # Wrapper keeps the existing PHP fixtures and post-title lookup usable without host PHP.
 import shlex
 wrapper=w/'php';wrapper.write_text('#!/bin/sh\nexec '+' '.join(map(shlex.quote,cmd+['exec','-T','-e',f'WPENV={w}','wordpress','php']))+' "$@"\n');wrapper.chmod(0o755)
 (w/'capture-env.json').write_text(json.dumps({'WPENV':str(w),'MEDIA_BASE_URL':base,'MEDIA_PHP':str(wrapper)},indent=2))
 print(f'Local screenshot site ready: {base}; environment: {w}/capture-env.json')
if __name__=='__main__':main()
