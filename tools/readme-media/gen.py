# Tanıtım README üretici: python3 gen.py <pkg.py>  -> README.md + README.tr.md
import sys, importlib.util, os
spec=importlib.util.spec_from_file_location('pkg',sys.argv[1]); P=importlib.util.module_from_spec(spec); spec.loader.exec_module(P)
REL='https://github.com/FluxWP/fluxwp/releases'
T={
 'en':dict(other='[Türkçe](README.tr.md)',me='**English**',logo='logo',feat='Features',shots='Screenshots',inst='Installation',mcp='Use it with AI assistants (MCP)',dev='For developers',road='Roadmap',lic='License',why='Why',
   badge_lic='license',zip='**From a zip**',zip1=f'Download `{{slug}}.zip` from the latest `{{slug}}-v*` release on the [Releases]({REL}) page.',zip2='**Plugins › Add New › Upload Plugin**, choose the zip, then **Activate**.',zip2t='**Appearance › Themes › Add New › Upload Theme**, choose the zip, then **Activate**.',
   upd='**With Flux Setup** (recommended on our sites)',upd1='**Setup › Packages** lists every FluxWP package; click **Install** next to *{name}*. Updates arrive the same way.',first='**First steps**',req='Requirements',
   licbody='Released under the [GNU General Public License v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).',made='Made by FluxWP.',docs='Full setup notes (Turkish)',api='API reference',chg='Release notes'),
 'tr':dict(other='[English](README.md)',me='**Türkçe**',logo='logosu',feat='Özellikler',shots='Ekran görüntüleri',inst='Kurulum',mcp='Yapay zekâ asistanlarıyla kullanım (MCP)',dev='Geliştiriciler için',road='Yol haritası',lic='Lisans',why='Neden',
   badge_lic='lisans',zip='**Zip ile**',zip1=f'[Releases]({REL}) sayfasında en son `{{slug}}-v*` sürümünden `{{slug}}.zip`\'i indirin.',zip2='**Eklentiler › Yeni ekle › Eklenti yükle**, zip\'i seçin, sonra **Etkinleştir**.',zip2t='**Görünüm › Temalar › Yeni ekle › Tema yükle**, zip\'i seçin, sonra **Etkinleştir**.',
   upd='**Flux Setup ile** (sitelerimizde önerilen)',upd1='**Setup › Paketler** tüm FluxWP paketlerini listeler; *{name}* satırındaki **Kur**\'a basın. Güncellemeler de buradan gelir.',first='**İlk adımlar**',req='Gereksinimler',
   licbody='[GNU Genel Kamu Lisansı v2 veya üzeri](https://www.gnu.org/licenses/gpl-2.0.html) ile yayınlanır.',made='FluxWP tarafından geliştirilmiştir.',docs='Ayrıntılı kurulum notları',api='API başvurusu',chg='Sürüm notları'),
}
import re
def anchor(h): return '#'+re.sub(r'[^\w\- ]','',h.lower(),flags=re.U).strip().replace(' ','-')
for lang in ('en','tr'):
  t=T[lang]; c=P.C[lang]; name=c['name']; slug=P.SLUG; theme=getattr(P,'THEME',False)
  o=[]
  sw=f"{t['me']} · {t['other']}" if lang=='en' else f"{t['other']} · {t['me']}"
  o+=['<div align="center">','',sw,'',f'<img src="docs/logo.svg" alt="{name} {t["logo"]}" width="120">','',f'# {name}','',f"**{c['tagline']}**<br>",c['desc'],'']
  b=[f'[![WordPress {P.WP}+](https://img.shields.io/badge/WordPress-{P.WP}%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org)',
     f'[![PHP {P.PHP}+](https://img.shields.io/badge/PHP-{P.PHP}%2B-777bb4?logo=php&logoColor=white)](https://www.php.net)',
     f'[![GPL v2+](https://img.shields.io/badge/{t["badge_lic"]}-GPL--2.0--or--later-blue)](https://www.gnu.org/licenses/gpl-2.0.html)']
  b+=c.get('badges',[])
  b.append('![UI](https://img.shields.io/badge/UI-English%20%7C%20Türkçe-e30a17)' if lang=='en' else '![Arayüz](https://img.shields.io/badge/arayüz-Türkçe%20%7C%20English-e30a17)')
  o+=b+['']
  nav=[t['feat'],t['shots'],t['inst']]+([t['mcp']] if c.get('mcp') else [])+([t['dev']] if c.get('dev') else [])+[t['road']]
  o+=[' · '.join(f'[{n}]({anchor(n)})' for n in nav),'']
  gif='docs/demo.gif' if lang=='en' else 'docs/demo-tr.gif'
  o+=[f'<img src="{gif}" alt="{c["gifalt"]}" width="900">','','</div>','','---','']
  o+=[f"## {t['why']} {name}?",'',c['why'].strip(),'']
  o+=[f"## {t['feat']}",'',c['features'].strip(),'']
  o+=[f"## {t['shots']}",'']
  rows=[]
  for p in c['shots']:
    if isinstance(p[0],list):
      # Birden çok görsel her zaman ayrı hücreye: "Mobil: panel ve form" -> "Mobil: panel", "Mobil: form"
      ims=p[0]; lab=p[1]; labs=p[3] if len(p)>3 else None
      if not labs and ':' in lab:
        pre,rest=lab.split(':',1); parts=[x.strip() for x in __import__('re').split(r',| and | ve ',rest) if x.strip()]
        if len(parts)==len(ims): labs=[f'{pre}: {x}' for x in parts]
      labs=labs or [lab]*len(ims)
      rows+=[(im,lb,True) for im,lb in zip(ims,labs)]
    else: rows.append(p)
  out=[]
  for i in range(0,len(rows),2):
    pair=rows[i:i+2]
    hdr=[(f'**{p[1]}**' if i else p[1]) for p in pair]
    def cell(p):
      imgs=p[0] if isinstance(p[0],list) else [p[0]]
      w=260 if (len(p)>2 and p[2]) or str(imgs[0]).startswith('mobile') else 440
      return ' '.join(f'<img src="docs/images/{im}-{lang}.jpg" alt="{p[1]}" width="{w}">' for im in imgs)
    if len(pair)==1: hdr.append(' '); 
    if i==0: out+=['| '+' | '.join(hdr)+' |','|---|---|']
    else: out+=['| '+' | '.join(hdr)+' |']
    cells=[cell(p) for p in pair]+([' '] if len(pair)==1 else [])
    out+=['| '+' | '.join(cells)+' |']
  o+=out+['']
  o+=[f"## {t['inst']}",'',f"{t['req']}: WordPress {P.WP}+, PHP {P.PHP}+."+(' '+c['req'] if c.get('req') else ''),'',t['zip'],'',f"1. {t['zip1'].format(slug=slug)}",f"2. {t['zip2t'] if theme else t['zip2']}",'',t['upd'],'',t['upd1'].format(name=name),'']
  if c.get('first'): o+=[t['first'],'',c['first'].strip(),'']
  if c.get('note'): o+=[c['note'].strip(),'']
  if getattr(P,'DOCS',None): o+=[f"{t['docs']}: [KURULUM.md](../../docs/{P.DOCS}/KURULUM.md)"+(f" · {t['api']}: [API.md](../../docs/{P.DOCS}/API.md)" if getattr(P,'API',False) else '')+(f" · {t['chg']}: [CHANGELOG.md](CHANGELOG.md)" if os.path.exists(os.path.join(P.DIR,'CHANGELOG.md')) else ''),'']
  if c.get('mcp'): o+=[f"## {t['mcp']}",'',c['mcp'].strip(),'']
  if c.get('dev'): o+=[f"## {t['dev']}",'',c['dev'].strip(),'']
  o+=[f"## {t['road']}",'','| | |','|---|---|']+[f'| {"✅" if d else "⬜"} | {x} |' for d,x in c['roadmap']]+['']
  o+=[f"## {t['lic']}",'',f"{name} {t['licbody']}" if lang=='en' else f"{name}, {t['licbody']}",'' ]+([c['licextra'],''] if c.get('licextra') else [])+[t['made'],'']
  fn=os.path.join(P.DIR,'README.md' if lang=='en' else 'README.tr.md'); open(fn,'w').write('\n'.join(o)); print(fn)
