"""Offline FluxWP translation contract validation. No network or file writes."""
from html.parser import HTMLParser
from collections import Counter
import re
TARGETS = {'de': {'name': 'Deutsch', 'locale': 'de_DE', 'flag': '🇩🇪', 'direction': 'ltr'}, 'es': {'name': 'Español', 'locale': 'es_ES', 'flag': '🇪🇸', 'direction': 'ltr'}, 'fr': {'name': 'Français', 'locale': 'fr_FR', 'flag': '🇫🇷', 'direction': 'ltr'}, 'pt-br': {'name': 'Português do Brasil', 'locale': 'pt_BR', 'flag': '🇧🇷', 'direction': 'ltr'}, 'ja': {'name': '日本語', 'locale': 'ja', 'flag': '🇯🇵', 'direction': 'ltr'}, 'zh': {'name': '简体中文', 'locale': 'zh_CN', 'flag': '🇨🇳', 'direction': 'ltr'}, 'ar': {'name': 'العربية', 'locale': 'ar', 'flag': '🇸🇦', 'direction': 'rtl'}, 'ru': {'name': 'Русский', 'locale': 'ru_RU', 'flag': '🇷🇺', 'direction': 'ltr'}}
FORMAT = "fluxwp-site-translations/v1"

class Markup(HTMLParser):
    def __init__(self): super().__init__(convert_charrefs=False);self.tokens=[]
    def handle_starttag(self,tag,attrs): self.tokens.append(('open',tag,tuple(sorted((k,v) for k,v in attrs if k not in ('alt','title','aria-label')))))
    def handle_endtag(self,tag): self.tokens.append(('close',tag))
    def handle_startendtag(self,tag,attrs): self.handle_starttag(tag,attrs)
    def handle_comment(self,data): self.tokens.append(('comment',data))

def protected(value):
    tokens=[]
    for pattern in (r'%(?:\d+\$)?[-+0-9.]*[sdf]',r'\{(?:[A-Za-z_][A-Za-z0-9_.:-]*|url:@[^}]+|id:@[^}]+)\}',r'https?://[^\s<>"\'\)\]]+',r'```[\s\S]*?```',r'`[^`\n]+`',r'!?\[[^\]]*\]\(([^)]+)\)'):
        tokens+=re.findall(pattern,value)
    parser=Markup();parser.feed(value)
    tokens+=parser.tokens
    return Counter(tokens)

def validate(candidate, canonical, allow_blank=False):
    meta=candidate.get('_meta',{}); targets=TARGETS;lang=meta.get('language')
    if lang not in targets: raise ValueError('Unsupported target language')
    if meta.get('format')!=FORMAT or meta.get('source_hash')!=canonical['_meta']['source_hash']: raise ValueError('Source revision mismatch; use a fresh export')
    for key in ('locale','direction'):
        if meta.get(key)!=targets[lang][key]: raise ValueError('Invalid '+key)
    errors=[]; blank=0;translated=0;total=0
    def visit(src,dst,path):
        nonlocal blank,translated,total
        if type(src)!=type(dst): errors.append(path+': value type changed');return
        if isinstance(src,dict):
            if set(src)!=set(dst): errors.append(path+': keys changed');return
            for k in src: visit(src[k],dst[k],path+'/'+k)
        elif isinstance(src,list):
            if len(src)!=len(dst): errors.append(path+': array length changed');return
            for i,(a,b) in enumerate(zip(src,dst)):visit(a,b,path+'/'+str(i))
        elif isinstance(src,str):
            if not src.strip():
                if dst: errors.append(path+': source is intentionally empty')
                return
            total+=1
            if not dst.strip():
                blank+=1
                if not allow_blank: errors.append(path+': translation missing')
            elif protected(src)!=protected(dst): errors.append(path+': markup, code, URL or placeholder changed')
            elif dst!=src: translated+=1
        elif src!=dst: errors.append(path+': immutable value changed')
    visit(canonical['text'],candidate.get('text'),'text')
    if errors: raise ValueError('\n'.join(errors[:12])+ ('\n... more errors' if len(errors)>12 else ''))
    return {'language':lang,'total':total,'empty':blank,'changed':translated}

