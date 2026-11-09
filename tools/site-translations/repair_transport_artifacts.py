#!/usr/bin/env python3
"""Repair legacy Python response envelopes in package gettext catalogs, offline.

Only strings with the known batch framing are normalized. Runtime code, code
examples and unframed backslashes are left alone. Run without --apply to audit.
"""
import argparse, ast, json, re, subprocess, warnings
from pathlib import Path
from package_translations import LANGS, mo, po, phpstr

PREFIX = re.compile(r'^\[\[[\'\"]')
SUFFIX = re.compile(r'''[\'\"],\s*[\'\"](?:tr|en|de|es|fr|pt|ja|zh-CN|ar|ru)[\'\"]\]\]$''')

def clean_form(text):
    original = text
    framed = text.startswith('\\n') or text.endswith('\\n') or bool(PREFIX.match(text) or SUFFIX.search(text))
    if not framed:
        return text
    # A complete response list can be decoded without executing any code.
    try:
        parsed = ast.literal_eval(text)
        if isinstance(parsed, list) and parsed and isinstance(parsed[0], list) and parsed[0] and isinstance(parsed[0][0], str):
            text = parsed[0][0]
            return text.strip('\n\r') or original
    except (SyntaxError, ValueError, SyntaxWarning):
        pass
    text = PREFIX.sub('', text, count=1)
    text = SUFFIX.sub('', text, count=1)
    # Undo one repr layer only when the entire framed body is a valid literal.
    # Keep literal \\x00 aliases intact; those are not valid plural gettext IDs.
    if '\\x00' not in text:
        for quote in ("'", '"'):
            try:
                with warnings.catch_warnings():
                    warnings.simplefilter('error', SyntaxWarning)
                    decoded = ast.literal_eval(quote + text + quote)
                if isinstance(decoded, str):
                    text = decoded
                    break
            except (SyntaxError, ValueError, SyntaxWarning):
                pass
    while text.startswith('\\n'):
        text = text[2:]
    while text.endswith('\\n'):
        text = text[:-2]
    return text.strip('\n\r') or original

def clean(text):
    # Preserve gettext contexts and the exact number of plural slots.
    return '\0'.join(clean_form(form) for form in text.split('\0'))

def clean_key(key):
    if '\x04' in key:
        context, body = key.split('\x04', 1)
        return context + '\x04' + clean(body)
    return clean(key)

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--repo', type=Path, action='append', required=True)
    parser.add_argument('--apply', action='store_true')
    parser.add_argument('--image', default='wordpress:7.1.4-php8.3-apache')
    args = parser.parse_args()
    repos = [p.resolve() for p in args.repo]
    packs = [p for repo in repos for area in ('plugins', 'themes') for p in (repo / area).glob('*/languages/messages.en.json')]
    tool = Path(__file__).with_name('extract_gettext.php').resolve()
    mounts = sum((['-v', f'{repo}:{repo}' + ('' if args.apply else ':ro')] for repo in repos), [])
    docker = ['docker', 'run', '--rm', '-i', '--network', 'none', *mounts, '--entrypoint', 'php', args.image, '-d', 'memory_limit=768M']
    roots = [str(p.parent.parent) for p in packs]
    inventory = json.loads(subprocess.check_output(docker + [str(tool)], input=json.dumps(roots).encode()))
    changes = {}
    for path in packs:
        root = str(path.parent.parent); domain = path.parent.parent.name
        aliases = {key: clean_key(key) for key in inventory.get(root, {}).get(domain, {}) if clean_key(key) != key}
        if aliases:
            changes[root] = {'domain': domain, 'aliases': aliases}
    print('Source keys:', sum(len(p['aliases']) for p in changes.values()), 'packages:', len(changes), flush=True)
    if not args.apply:
        return
    subprocess.run(docker + [str(tool)], input=json.dumps({'packages': changes}).encode(), check=True, stdout=subprocess.DEVNULL)
    # Export trusted project-generated dictionaries in one PHP process.
    files = [str(f) for p in packs for f in p.parent.glob('*.l10n.php')]
    php = '$o=[];foreach(json_decode(file_get_contents("php://stdin"),true) as $f){$p=require $f;$o[$f]=$p;}echo json_encode($o);'
    native_packs = json.loads(subprocess.check_output(docker + ['-r', php], input=json.dumps(files).encode()))
    locales = [('en_US','en',2,'(n != 1)'), ('tr_TR','tr',2,'(n != 1)')] + list(LANGS.values())
    for path in packs:
        folder=path.parent; domain=folder.parent.name; updates=0
        for locale, _, n, plural in locales:
            jp=folder/('messages.' + ('en' if locale=='en_US' else locale) + '.json')
            prefix='' if (folder/f'{locale}.l10n.php').exists() else domain+'-'
            lf=folder/f'{prefix}{locale}.l10n.php'
            if not jp.exists() or str(lf) not in native_packs:
                continue
            data=json.loads(jp.read_text()); old=data['messages']; canonical={}
            # Prefer already clean keys when normalization collapses duplicate entries.
            for key,value in sorted(old.items(),key=lambda kv: clean_key(kv[0]) != kv[0]):
                target=clean_key(key)
                canonical.setdefault(target,clean(value))
            base=native_packs[str(lf)]; native={key:clean(value) for key,value in base['messages'].items()}
            # Keep old aliases for external integrations and JS built against old IDs.
            for key,value in list(native.items()):
                native.setdefault(clean_key(key),value)
            for key,value in canonical.items():
                native[key]=value
            if canonical==old and native==base['messages']:
                continue
            updates+=1;data['messages']=canonical
            jp.write_text(json.dumps(data,ensure_ascii=False,indent=2)+'\n')
            base['messages']=native
            def php_value(value):
                if isinstance(value,dict):return 'array('+','.join(phpstr(str(k))+'=>'+php_value(v) for k,v in value.items())+')'
                if isinstance(value,list):return 'array('+','.join(php_value(v) for v in value)+')'
                if isinstance(value,str):return phpstr(value)
                if isinstance(value,bool):return 'true' if value else 'false'
                if value is None:return 'null'
                return str(value)
            # Keep native metadata, including WordPress plural rules.
            text='<?php\nreturn array(\n'
            for key,value in base.items():
                if key=='messages':text+=phpstr(key)+'=>array(\n'+''.join(phpstr(k)+' => '+phpstr(v)+',\n' for k,v in native.items())+'),\n'
                else:text+=phpstr(key)+'=>'+php_value(value)+',\n'
            lf.write_text(text+');\n')
            (folder/f'{prefix}{locale}.mo').write_bytes(mo(native,locale,n,plural))
            (folder/f'{prefix}{locale}.po').write_text(po(canonical,locale,n,plural))
            for jf in folder.glob(f'{domain}-{locale}-*.json'):
                jed=json.loads(jf.read_text());entries=jed.get('locale_data',{}).get(domain)
                if isinstance(entries,dict):
                    for key,value in native.items():entries[key.split('\0')[0]]=value.split('\0')
                    jf.write_text(json.dumps(jed,ensure_ascii=False,indent=2)+'\n')
        if updates:
            en=json.loads(path.read_text())['messages']
            (folder/f'{domain}.pot').write_text(po(en,'en_US',2,'(n != 1)',True))
            print('Repaired',domain,updates,'locales',flush=True)

if __name__=='__main__':main()
