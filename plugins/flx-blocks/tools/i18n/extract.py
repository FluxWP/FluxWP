#!/usr/bin/env python3
"""Collect every translatable string: languages/flx-blocks.pot + src/blocks/**/block.json.
Writes tools/i18n/strings.json: [{ctx, id, plural}] in stable order."""
import json, os, re, glob
HERE = os.path.dirname(os.path.abspath(__file__)); ROOT = os.path.abspath(os.path.join(HERE, '..', '..'))
def unq(s):
    return json.loads('"' + s.replace('\\\'', "'") + '"') if s else ''
def parse_pot(p):
    out=[]; cur={}; key=None
    for line in open(p, encoding='utf-8'):
        line=line.rstrip('\n')
        m=re.match(r'^(msgctxt|msgid|msgid_plural|msgstr(?:\[\d\])?) "(.*)"$', line)
        if m:
            key=m.group(1); cur[key]=cur.get(key,'')+unq(m.group(2)); continue
        m=re.match(r'^"(.*)"$', line)
        if m and key: cur[key]+=unq(m.group(1)); continue
        if not line.strip():
            if cur.get('msgid'): out.append({'ctx':cur.get('msgctxt',''),'id':cur['msgid'],'plural':cur.get('msgid_plural','')})
            cur={}; key=None
    if cur.get('msgid'): out.append({'ctx':cur.get('msgctxt',''),'id':cur['msgid'],'plural':cur.get('msgid_plural','')})
    return out
items=parse_pot(os.path.join(ROOT,'languages','flx-blocks.pot'))
seen={(i['ctx'],i['id']) for i in items}
def add(ctx,s):
    if s and (ctx,s) not in seen: seen.add((ctx,s)); items.append({'ctx':ctx,'id':s,'plural':''})
for f in sorted(glob.glob(os.path.join(ROOT,'src','blocks','**','block.json'),recursive=True)):
    d=json.load(open(f))
    add('block title',d.get('title','')); add('block description',d.get('description',''))
    for k in d.get('keywords',[]): add('block keyword',k)
    for st in d.get('styles',[]): add('block style label',st.get('label',''))
    for v in d.get('variations',[]):
        add('block variation title',v.get('title','')); add('block variation description',v.get('description',''))
        for k in v.get('keywords',[]): add('block variation keyword',k)
json.dump(items,open(os.path.join(HERE,'strings.json'),'w'),ensure_ascii=False,indent=0)
print(len(items))
