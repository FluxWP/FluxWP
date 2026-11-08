#!/usr/bin/env python3
"""Build independent WordPress package language packs from English-keyed JSON catalogs."""
import argparse, concurrent.futures, hashlib, json, os, re, struct, subprocess, threading, time
from pathlib import Path
from translate_all import translate_batch, translate_numeric_markers
from validator import protected

LANGS={'de':('de_DE','de',2,'(n != 1)'), 'es':('es_ES','es',2,'(n != 1)'), 'fr':('fr_FR','fr',2,'(n > 1)'), 'pt-br':('pt_BR','pt',2,'(n > 1)'), 'ja':('ja','ja',1,'0'), 'zh':('zh_CN','zh-CN',1,'0'), 'ar':('ar','ar',6,'(n==0?0:n==1?1:n==2?2:n%100>=3&&n%100<=10?3:n%100>=11&&n%100<=99?4:5)'), 'ru':('ru_RU','ru',3,'(n%10==1&&n%100!=11?0:n%10>=2&&n%10<=4&&(n%100<12||n%100>14)?1:2)')}
LOCK=threading.Lock()
def read(p):return json.loads(Path(p).read_text())
def write(p,d):
 p=Path(p);p.parent.mkdir(parents=True,exist_ok=True);tmp=p.with_suffix(p.suffix+'.tmp');tmp.write_text(json.dumps(d,ensure_ascii=False,indent=2)+'\n');tmp.replace(p)
def export(repos,out):
 # Only include executable PHP catalogs already tracked by the developer's repositories.
 files=[]
 for repo in repos:
  for area in ('plugins','themes'):
   files.extend(str(p.resolve()) for p in (repo/area).glob('*/languages/*en_US.l10n.php'))
 php='$o=[];foreach(json_decode(file_get_contents("php://stdin"),true) as $f){$o[$f]=require $f;}echo json_encode($o,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);'
 mounts=sum((['-v',f'{r.resolve()}:{r.resolve()}:ro'] for r in repos),[])
 tool_dir=Path(__file__).resolve().parent;mounts+=['-v',f'{tool_dir}:{tool_dir}:ro']
 result=subprocess.run(['docker','run','--rm','-i','--network','none',*mounts,'--entrypoint','php','php:8.3-cli','-r',php],input=json.dumps(files),capture_output=True,text=True,check=True)
 scan=subprocess.run(['docker','run','--rm','-i','--network','none',*mounts,'--entrypoint','php','php:8.3-cli',str(Path(__file__).with_name('extract_gettext.php').resolve())],input=json.dumps([str(Path(f).parent.parent) for f in files]),capture_output=True,text=True,check=True)
 inventory=json.loads(scan.stdout)
 packages={}
 for file,pack in json.loads(result.stdout).items():
  folder=Path(file).parent.parent;domain=folder.name;messages={};aliases={}
  domain='fluxwp' if domain=='fluxwp' else domain
  existing=pack.get('messages',{}).copy()
  normalized=read(out/'english-normalization.json') if (out/'english-normalization.json').exists() else {}
  for key in inventory.get(str(folder),{}).get(domain,{}):
   existing.setdefault(key,normalized.get(key.split('\x04')[-1],key.split('\x04')[-1]))
  for main in folder.glob('*.php'):
   header=main.read_text(errors='replace')[:6000]
   if 'Plugin Name:' not in header:continue
   match=re.search(r'^\s*\*\s*Description:\s*(.*)$',header,re.M)
   if match:existing.setdefault(match[1],normalized.get(match[1],match[1]))
  # Static literal JS gettext calls supplement PHP's token inventory.
  quoted=r"(\"(?:\\.|[^\"\\])*\"|'(?:\\.|[^'\\])*')"
  for script in folder.rglob('*.js'):
   if any(x in script.parts for x in ('vendor','node_modules')) or script.name.endswith('.min.js'):continue
   code=script.read_text(errors='replace')
   for match in re.finditer(r"\b__\(\s*"+quoted+r"\s*,\s*(['\"])"+re.escape(domain)+r"\2\s*\)",code):
    raw=match[1]
    try:value=json.loads(raw) if raw.startswith('"') else raw[1:-1].replace("\\'", "'").replace('\\\\','\\')
    except ValueError:continue
    existing.setdefault(value,normalized.get(value,value))
  pack['messages']=existing
  for old,value in pack.get('messages',{}).items():
   if not value:continue
   context=old.split('\x04')[0] if '\x04' in old else ''
   if context and not re.fullmatch(r'context_[0-9a-f]{12}',context):context='context_'+hashlib.sha256(context.encode()).hexdigest()[:12]
   key=(context+'\x04' if context else '')+value
   messages[key]=value;aliases[old]=key
  packages[str(folder)]={'domain':domain,'filename':Path(file).name.replace('en_US.l10n.php','{locale}'),'messages':messages,'aliases':aliases}
 write(out/'packages.json',packages)
 # English translator keys, grouped by package; no Turkish display keys in the public contract.
 write(out/'en.json',{'_meta':{'format':'fluxwp-package-translations/v1','language':'en'},'text':{p['domain']:p['messages'] for p in packages.values()}})
 print('EXPORTED',len(packages),'packages',sum(len(p['messages']) for p in packages.values()),'messages',flush=True)
 return packages

def seed(cache,source,target):
 if isinstance(source,dict) and isinstance(target,dict):
  for k,v in source.items():
   if k in target:seed(cache,v,target[k])
 elif isinstance(source,list) and isinstance(target,list):
  for a,b in zip(source,target):seed(cache,a,b)
 elif isinstance(source,str) and isinstance(target,str) and source.strip() and target.strip() and protected(source)==protected(target):cache.setdefault(source,target)

def translate(lang,strings,out,site):
 locale,api,_,_=LANGS[lang];file=out/'cache'/f'{lang}.json';cache=read(file) if file.exists() else {}
 if site and (site/'en.json').exists() and (site/f'{lang}.json').exists():seed(cache,read(site/'en.json')['text'],read(site/f'{lang}.json')['text'])
 missing=[s for s in strings if not cache.get(s) or protected(s)!=protected(cache[s])]
 print(lang,'PENDING',len(missing),'REUSED',len(strings)-len(missing),flush=True)
 batches=[];batch=[];size=0
 for s in missing:
  if len(s)>400 or '<p>' in s or '```' in s:
   if batch:batches.append(batch);batch=[];size=0
   batches.append([s]);continue
  if batch and (size+len(s)>3500 or len(batch)>=35):batches.append(batch);batch=[];size=0
  batch.append(s);size+=len(s)
 if batch:batches.append(batch)
 errors={}
 for i,batch in enumerate(batches):
  try:
   values=translate_batch(batch,api)
   for src,dst in zip(batch,values):
    if dst.strip() and protected(src)==protected(dst):cache[src]=dst
    else:
     try:cache[src]=translate_numeric_markers(src,api)
     except Exception as retry_error:errors[src]=str(retry_error)
  except Exception as e:
   for src in batch:errors[src]=str(e)
  write(file,cache)
  if i%10==0 or i+1==len(batches):print(lang,'BATCH',i+1,'/',len(batches),'ERRORS',len(errors),flush=True)
  time.sleep(.15)
 write(out/'errors'/f'{lang}.json',errors)
 return lang,cache

def phpstr(v):return "'"+v.replace('\\','\\\\').replace("'","\\'").replace('\x00',"' . chr(0) . '").replace('\n',"' . chr(10) . '").replace('\r',"' . chr(13) . '")+"'"
def mo(messages,language,nplurals,plural):
 messages={'':f'Content-Type: text/plain; charset=UTF-8\nLanguage: {language}\nPlural-Forms: nplurals={nplurals}; plural={plural};\n',**messages};items=sorted(messages.items());n=len(items);offset=28+16*n;keys=b'';vals=b'';kt=[];vt=[]
 for key,value in items:
  a=key.encode();b=value.encode();kt.append((len(a),offset+len(keys)));vt.append((len(b),len(vals)));keys+=a+b'\0';vals+=b+b'\0'
 base=offset+len(keys)
 return struct.pack('<7I',0x950412de,0,n,28,28+8*n,0,0)+b''.join(struct.pack('<2I',*x) for x in kt)+b''.join(struct.pack('<2I',size,base+pos) for size,pos in vt)+keys+vals

def po(messages,language,nplurals,plural,template=False):
 q=lambda s:json.dumps(s,ensure_ascii=False)
 header=f'Content-Type: text/plain; charset=UTF-8\nLanguage: {language}\nPlural-Forms: nplurals={nplurals}; plural={plural};\n'
 lines=['msgid ""','msgstr '+q(header),'']
 for key,value in messages.items():
  if '\x04' in key:
   context,key=key.split('\x04',1);lines.append('msgctxt '+q(context))
  source=key.split('\0');target=value.split('\0')
  lines.append('msgid '+q(source[0]))
  if len(source)>1:
   lines.append('msgid_plural '+q(source[-1]))
   for i in range(nplurals):lines.append(f'msgstr[{i}] '+q('' if template else target[min(i,len(target)-1)]))
  else:lines.append('msgstr '+q('' if template else value))
  lines.append('')
 return '\n'.join(lines)

def build_base(packages):
 for folder,p in packages.items():
  root=Path(folder)/'languages';source=p['messages'];tr_file=root/'messages.tr_TR.json'
  tr=read(tr_file)['messages'] if tr_file.exists() else {}
  for old,key in p['aliases'].items():tr.setdefault(key,old.split('\x04')[-1])
  tr={key:tr.get(key,value) for key,value in source.items()}
  for locale,canonical in [('en_US',source),('tr_TR',tr)]:
   native=dict(canonical)
   for old,key in p['aliases'].items():native[old]=canonical[key]
   path=root/p['filename'].format(locale=locale)
   Path(str(path)+'.mo').write_bytes(mo(native,locale,2,'(n != 1)'))
   Path(str(path)+'.po').write_text(po(canonical,locale,2,'(n != 1)'))
   Path(str(path)+'.l10n.php').write_text("<?php\nreturn array('language'=>"+phpstr(locale)+",'plural-forms'=>'nplurals=2; plural=(n != 1);','messages'=>array(\n"+''.join(phpstr(k)+'=>'+phpstr(v)+",\n" for k,v in native.items())+"));\n")
   write(root/('messages.'+('en' if locale=='en_US' else locale)+'.json'),{'_meta':{'format':'fluxwp-package-translations/v1','locale':locale,'status':'source' if locale=='en_US' else 'preserved'},'messages':canonical})
   for handle in {'flx-lang':['flxl-blok'],'flx-forms':['efb-gutenberg-editor']}.get(p['domain'],[]):
    jed={'':{'domain':p['domain'],'lang':locale,'plural-forms':'nplurals=2; plural=(n != 1);'}}
    jed.update({key.split('\0')[0]:value.split('\0') for key,value in native.items()})
    write(root/f'{p["domain"]}-{locale}-{handle}.json',{'domain':p['domain'],'locale_data':{p['domain']:jed}})
  (root/(p['domain']+'.pot')).write_text(po(source,'en_US',2,'(n != 1)',True))
  # Standalone regional fallback: defer to a user/official catalog whenever it exists.
  mapping={'en':'en_US','tr':'tr_TR',**{lang.split('-')[0]:data[0] for lang,data in LANGS.items()}}
  loader="""<?php
defined('ABSPATH') || exit;
add_filter('load_textdomain_mofile', static function($file,$domain){
 if($domain!==__DOMAIN__ || is_readable($file) || is_readable(str_replace('.mo','.l10n.php',$file)))return $file;
 $locale=preg_replace(__PREFIX__, '', basename($file,'.mo'));
 $base=strtolower(explode('_',str_replace('-','_',$locale))[0]);
 $locales=__LOCALES__;
 if(!isset($locales[$base]))return $file;
 $path=__DIR__.'/'.str_replace('{locale}',$locales[$base],__FILENAME__).'.mo';
 return is_readable($path)?$path:$file;
},20,2);
""".replace('__DOMAIN__',phpstr(p['domain'])).replace('__PREFIX__',phpstr('/^'+p['domain']+'-/')).replace('__FILENAME__',phpstr(p['filename'])).replace('__LOCALES__','array('+','.join(phpstr(k)+'=>'+phpstr(v) for k,v in mapping.items())+')')
  (root/'locale-loader.php').write_text(loader)


def validate_import(packages,folder):
 catalogs={}
 for lang,(locale,_,nplurals,_) in LANGS.items():
  candidate=read(folder/f'{lang}.json')
  meta=candidate.get('_meta',{})
  if meta.get('format')!='fluxwp-package-translations/v1' or meta.get('language')!=lang or meta.get('locale')!=locale:raise ValueError('Invalid package catalog metadata: '+lang)
  if set(candidate.get('text',{}))!={p['domain'] for p in packages.values()}:raise ValueError('Package keys changed: '+lang)
  for package in packages.values():
   messages=candidate['text'][package['domain']]
   if set(messages)!=set(package['messages']):raise ValueError('Message keys changed: '+package['domain']+' '+lang)
   for key,source in package['messages'].items():
    value=messages[key]
    if not isinstance(value,str) or not value.strip():raise ValueError('Translation missing: '+key)
    src=source.split('\0');dst=value.split('\0');expected=nplurals if len(src)>1 else 1
    if len(dst)!=expected:raise ValueError('Plural slots changed: '+key)
    for index,form in enumerate(dst):
     one_index=1 if lang=='ar' else 0
     reference=src[0] if len(src)==1 or (nplurals>1 and index==one_index) else src[-1]
     if protected(reference)!=protected(form):raise ValueError('Protected content changed: '+key)
  catalogs[lang]=candidate['text']
 return catalogs

def build(packages,caches,out,from_files=False):
 report={}
 for lang,cache in caches.items():
  locale,_,nplurals,plural=LANGS[lang];catalog={};missing=[]
  for folder,pack in packages.items():
   canonical={};native={}
   if from_files:
    canonical=read(Path(folder)/('languages/messages.'+locale+'.json'))['messages'];native=dict(canonical)
   else:
    for key,value in pack['messages'].items():
     parts=value.split('\0');translated=[]
     for part in parts:
      if part not in cache or protected(part)!=protected(cache[part]):missing.append((pack['domain'],part));break
      translated.append(cache[part])
     else:
      # WordPress requires all plural slots. Machine forms remain reviewable in the English-keyed JSON.
      forms = ([translated[-1]] if nplurals == 1 else ([translated[-1],translated[0]]+[translated[-1]]*4) if lang == 'ar' else [translated[0]]+[translated[-1]]*(nplurals-1)) if len(parts)>1 else translated
      target='\0'.join(forms)
      canonical[key]=target;native[key]=target
   catalog[pack['domain']]=canonical
   write(Path(folder)/'languages/messages.en.json',{'_meta':{'format':'fluxwp-package-translations/v1','language':'en'},'messages':pack['messages']})
   if len(canonical)!=len(pack['messages']):continue # Never ship partially translated package catalogs.
   for old,key in pack['aliases'].items():native[old]=canonical[key]
   write(Path(folder)/('languages/messages.'+locale+'.json'),{'_meta':{'format':'fluxwp-package-translations/v1','language':lang,'locale':locale,'status':'machine-translated'},'messages':canonical})
   path=Path(folder)/'languages'/pack['filename'].format(locale=locale)
   path.with_suffix('.mo').write_bytes(mo(native,locale,nplurals,plural))
   # Keep PHP data catalogs data-only. Runtime uses WordPress's own locale, no Flux Lang dependency.
   php="<?php\nreturn array(\n 'language' => "+phpstr(locale)+",\n 'plural-forms' => "+phpstr(f'nplurals={nplurals}; plural={plural};')+",\n 'messages' => array(\n"+''.join('  '+phpstr(k)+' => '+phpstr(v)+",\n" for k,v in native.items())+" ),\n);\n"
   Path(str(path)+'.l10n.php').write_text(php)
   Path(str(path)+'.po').write_text(po(canonical,locale,nplurals,plural))
   handles={'flx-lang':['flxl-blok'],'flx-forms':['efb-gutenberg-editor']}.get(pack['domain'],[])
   for handle in handles:
    jed={'':{'domain':pack['domain'],'lang':locale,'plural-forms':f'nplurals={nplurals}; plural={plural};'}}
    jed.update({key.split('\0')[0]:value.split('\0') for key,value in native.items()})
    write(Path(folder)/f'languages/{pack["domain"]}-{locale}-{handle}.json',{'domain':pack['domain'],'locale_data':{pack['domain']:jed}})
  write(out/f'{lang}.json',{'_meta':{'format':'fluxwp-package-translations/v1','language':lang,'locale':locale,'status':'machine-translated'},'text':catalog})
  report[lang]={'missing':len(missing),'missing_examples':missing[:10],'packages':len(catalog)}
 write(out/'report.json',report);return report

def main():
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('--repo',action='append',type=Path,required=True);p.add_argument('--output',type=Path,required=True);p.add_argument('--site-catalogs',type=Path);p.add_argument('--translate',action='store_true');p.add_argument('--workers',type=int,default=4);p.add_argument('--build',action='store_true');p.add_argument('--normalize-source',action='store_true',help='Rewrite known legacy gettext literals to their canonical English IDs');p.add_argument('--import-catalogs',type=Path,help='Validate and compile returned JSON offline; no translation API');a=p.parse_args();out=a.output.resolve();out.mkdir(parents=True,exist_ok=True);packages=export(a.repo,out)
 strings=sorted({s for pack in packages.values() for value in pack['messages'].values() for s in value.split('\0') if s.strip()});caches={}
 if a.normalize_source:
  mounts=sum((['-v',f'{r.resolve()}:{r.resolve()}'] for r in a.repo),[])
  tool_dir=Path(__file__).resolve().parent;mounts+=['-v',f'{tool_dir}:{tool_dir}:ro']
  subprocess.run(['docker','run','--rm','-i','--network','none',*mounts,'--entrypoint','php','php:8.3-cli',str(Path(__file__).with_name('extract_gettext.php').resolve())],input=json.dumps({'packages':packages}),text=True,capture_output=True,check=True)
 if a.import_catalogs:
  catalogs=validate_import(packages,a.import_catalogs)
  for lang,(locale,_,_,_) in LANGS.items():
   for folder,package in packages.items():write(Path(folder)/f'languages/messages.{locale}.json',{'_meta':{'format':'fluxwp-package-translations/v1','language':lang,'locale':locale,'status':'imported'},'messages':catalogs[lang][package['domain']]})
  build_base(packages);print(json.dumps(build(packages,{lang:{} for lang in LANGS},out,True)));return
 if a.translate:
  with concurrent.futures.ThreadPoolExecutor(max_workers=a.workers) as pool:
   futures=[pool.submit(translate,l,strings,out,a.site_catalogs) for l in LANGS]
   for future in concurrent.futures.as_completed(futures):lang,cache=future.result();caches[lang]=cache
 else:caches={l:read(out/'cache'/f'{l}.json') for l in LANGS if (out/'cache'/f'{l}.json').exists()}
 if a.build:
  build_base(packages);report=build(packages,caches,out);print(json.dumps(report),flush=True)
  if len(caches)!=len(LANGS) or any(x['missing'] for x in report.values()):raise SystemExit('Some package translations remain incomplete; see report.json')
if __name__=='__main__':main()
