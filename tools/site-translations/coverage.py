#!/usr/bin/env python3
"""Report translation coverage against English and refresh each repository's README blocks offline."""
import argparse,json,tempfile
from pathlib import Path
from package_translations import LANGS,export
from validator import protected
START='<!-- translation-coverage:start -->';END='<!-- translation-coverage:end -->'
LOCALES={'tr':('tr_TR',2),**{key:(value[0],value[2]) for key,value in LANGS.items()}}
def read(path):return json.loads(path.read_text(encoding='utf-8'))
def percent(good,total):return 100.0 if not total or good==total else min(99.9,round(100*good/total,1))
def valid(source,target,code):
 if not isinstance(target,str) or not target.strip():return False
 src=source.split('\0');dst=target.split('\0');slots=LOCALES[code][1] if len(src)>1 else 1
 if len(dst)!=slots:return False
 for index,value in enumerate(dst):
  one=1 if code=='ar' else 0
  reference=src[0] if len(src)==1 or (slots>1 and index==one) else src[-1]
  if protected(reference)!=protected(value):return False
 return True

def measure(folder,source=None):
 if source is None:source=read(folder/'languages/messages.en.json')['messages']
 row={'package':folder.name,'source':len(source),'languages':{}}
 for code,(locale,_) in LOCALES.items():
  file=folder/f'languages/messages.{locale}.json';messages=read(file).get('messages',{}) if file.exists() else {}
  good=sum(valid(value,messages.get(key),code) for key,value in source.items())
  missing=[key for key in source if not messages.get(key)]
  invalid=[key for key,value in source.items() if messages.get(key) and not valid(value,messages[key],code)]
  row['languages'][code]={'percent':percent(good,len(source)),'valid':good,'missing':len(missing),'invalid':len(invalid),'unchanged':sum(messages.get(k)==v for k,v in source.items()),'obsolete':len(set(messages)-set(source)),'missing_keys':missing,'invalid_keys':invalid}
 return row

def block(rows,turkish=False,local=True):
 title='## Dil dosyalarının kapsamı' if turkish else '## Translation coverage'
 note=('İngilizce kaynakla karşılaştırılan, boş olmayan ve biçimi geçerli kayıt yüzdesidir; dil kalitesi onayı değildir. Kaynakla aynı kalan değerler (ör. marka adları) geçerli sayılır ve JSON raporunda ayrıca gösterilir. Yeni metin eklendiğinde komutu yeniden çalıştırın.' if turkish else 'Percentage of English source entries with a nonempty, structurally valid translation. This measures file coverage, not linguistic quality. Source-identical values (such as brand names) count as valid and are listed separately in the JSON report. Rerun after adding messages.')
 lines=[START,title,'',note,'','| Package | EN strings | '+' | '.join(code.upper() for code in LOCALES)+' |','|---|---:|'+'---:|'*len(LOCALES)]
 for row in rows:lines.append('| '+row['package']+' | '+str(row['source'])+' | '+' | '.join(f"{row['languages'][code]['percent']:g}%" for code in LOCALES)+' |')
 command='python3 '+('tools/site-translations/coverage.py' if local else '../FluxWP/tools/site-translations/coverage.py')+' --repo . --scan-source --write-readme'
 guide=('tools/site-translations/' if local else 'https://github.com/FluxWP/FluxWP/blob/main/tools/site-translations/')
 lines+=['','```sh',command,'```','','[Language guide]('+guide+'LANGUAGES.md)' if not turkish else '[Dil ekleme rehberi]('+guide+'LANGUAGES.tr.md)',END]
 return '\n'.join(lines)

def main():
 parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--repo',action='append',required=True,type=Path);parser.add_argument('--scan-source',action='store_true',help='Also inventory new gettext source calls with local Docker/PHP; no network');parser.add_argument('--write-readme',action='store_true');parser.add_argument('--report',type=Path);parser.add_argument('--check',action='store_true',help='Fail for missing/invalid entries or stale README blocks')
 args=parser.parse_args();repos=[r.resolve() for r in args.repo];inventory={}
 if args.scan_source:
  with tempfile.TemporaryDirectory() as folder:inventory=export(repos,Path(folder))
 report={};failed=False
 for repo in repos:
  roots=sorted(p.parent.parent for area in ['plugins','themes'] for p in (repo/area).glob('*/languages/messages.en.json'))
  rows=[measure(root,inventory.get(str(root),{}).get('messages')) for root in roots];report[str(repo)]=rows
  if any(item['missing'] or item['invalid'] for row in rows for item in row['languages'].values()):failed=True
  for name,turkish in [('README.md',False),('README.tr.md',True)]:
   path=repo/name
   if not path.exists():continue
   current=path.read_text();rendered=block(rows,turkish,(repo/'tools/site-translations/coverage.py').exists())
   if START in current and END in current:
    left=current.index(START);right=current.index(END)+len(END);updated=current[:left]+rendered+current[right:]
   else:updated=current.rstrip()+'\n\n'+rendered+'\n'
   if args.check and updated!=current:failed=True
   if args.write_readme:path.write_text(updated)
  total=sum(row['source'] for row in rows)
  summary={code:percent(sum(row['languages'][code]['valid'] for row in rows),total) for code in LOCALES}
  print(repo.name,json.dumps({'packages':len(rows),'source_entries':total,'coverage':summary},ensure_ascii=False))
 if args.report:args.report.parent.mkdir(parents=True,exist_ok=True);args.report.write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
 if args.check and failed:raise SystemExit(1)
if __name__=='__main__':main()
