#!/usr/bin/env python3
"""FluxWP Site Translations - Automated Pipeline.

Translates canonical en.json into all target languages:
de, es, fr, pt-br, ja, zh, ar, ru
Preserves 100% of JSON schema, immutable flags, protected tokens,
HTML skeletons, WordPress Gutenberg comments, and Markdown structures.
"""
import argparse
import json
import re
import sys
import time
import unicodedata
import urllib.parse
import urllib.request
import zipfile
from collections import Counter
from pathlib import Path
from validator import protected, validate

# Paths
BASE_DIR = Path(__file__).resolve().parent
WORKSPACE = BASE_DIR / 'fluxwp-site-translations'
SCRATCH = BASE_DIR / '.translation_cache'


TARGETS = {
    'de': {'code': 'de', 'name': 'Deutsch'},
    'es': {'code': 'es', 'name': 'Español'},
    'fr': {'code': 'fr', 'name': 'Français'},
    'pt-br': {'code': 'pt', 'name': 'Português do Brasil'},
    'ja': {'code': 'ja', 'name': '日本語'},
    'zh': {'code': 'zh-CN', 'name': '简体中文'},
    'ar': {'code': 'ar', 'name': 'العربية'},
    'ru': {'code': 'ru', 'name': 'Русский'},
}

DELIM = "\n<<<FLX_SPLIT>>>\n"

CURLY_KEYS = [
    '{address}', '{data_location}', '{field}', '{verbis}', '{kvkk_url}',
    '{tax}', '{kep}', '{name}', '{mersis}', '{legal_name}', '{code}',
    '{link}', '{email}', '{privacy_url}'
]

def call_translation_api(text, tl):
    endpoints = [
        f'https://clients5.google.com/translate_a/t?client=dict-chrome-ex&sl=en&tl={tl}',
        f'https://translate.googleapis.com/translate_a/single?client=gtx&sl=en&tl={tl}&dt=t'
    ]
    
    for attempt in range(8):
        url = endpoints[attempt % len(endpoints)]
        try:
            data = urllib.parse.urlencode({'q': text}).encode('utf-8')
            req = urllib.request.Request(url, data=data, headers={
                'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            })
            with urllib.request.urlopen(req, timeout=25) as res:
                body = res.read().decode('utf-8')
                parsed = json.loads(body)
                if isinstance(parsed, list):
                    if len(parsed) > 0 and isinstance(parsed[0], list) and len(parsed[0]) > 0 and isinstance(parsed[0][0], list):
                        return ''.join([part[0] for part in parsed[0] if part and part[0]])
                    elif len(parsed) > 0 and isinstance(parsed[0], str):
                        return parsed[0]
                elif isinstance(parsed, str):
                    return parsed
                return str(parsed)
        except Exception:
            if attempt == 7:
                raise
            time.sleep(1.0 + attempt * 1.5)

def protect_string(text):
    phs = []
    def add_ph(matched_str):
        idx = len(phs)
        phs.append(matched_str)
        return f' FLXTOK{idx}END '

    # 1. Fenced code
    s = re.sub(r'```[\s\S]*?```', lambda m: add_ph(m.group(0)), text)
    # 2. WordPress code block pre
    s = re.sub(r'<pre class="wp-block-code">[\s\S]*?</pre>', lambda m: add_ph(m.group(0)), s)
    # 3. Inline code
    s = re.sub(r'`[^`\n]+`', lambda m: add_ph(m.group(0)), s)
    # 4. HTML entities like &lt;...&gt;
    s = re.sub(r'&lt;[^&]*?&gt;', lambda m: add_ph(m.group(0)), s)
    # 5. HTML comments
    s = re.sub(r'<!--[\s\S]*?-->', lambda m: add_ph(m.group(0)), s)
    # 6. HTML tags
    s = re.sub(r'<[^>]+>', lambda m: add_ph(m.group(0)), s)
    # 7. Markdown links
    def md_link_sub(m):
        label = m.group(1)
        url = m.group(2)
        idx = len(phs)
        phs.append(url)
        return f'[{label}](FLXTOK{idx}END)'
    s = re.sub(r'!?\[([^\]]*)\]\(([^)]+)\)', md_link_sub, s)
    # 8. Standalone URLs
    s = re.sub(r'https?://[^\s<>"\'\)\]]+', lambda m: add_ph(m.group(0)), s)
    # 9. Curly template variables
    s = re.sub(r'\{(?:[A-Za-z_][A-Za-z0-9_.:-]*|url:@[^}]+|id:@[^}]+)\}', lambda m: add_ph(m.group(0)), s)
    # 10. Printf specifiers
    s = re.sub(r'%(?:\d+\$)?[-+0-9.]*[sdf]', lambda m: add_ph(m.group(0)), s)

    return s, phs

def restore_string(text, phs):
    res = unicodedata.normalize('NFKC', text)
    for idx, orig in reversed(list(enumerate(phs))):
        res = re.sub(rf'\]\(\s*FLX\s*TOK\s*{idx}\s*END\s*\)', lambda match: '](' + orig + ')', res, flags=re.IGNORECASE)
        pattern = re.compile(rf'\s*FLX\s*TOK\s*{idx}\s*END\s*', re.IGNORECASE)
        m = pattern.search(res)
        if m:
            start, end = m.span()
            repl = orig
            if orig.startswith('http'):
                if end < len(res) and (res[end].isalpha() or ord(res[end]) > 127):
                    repl = orig + ' '
                if start > 0 and (res[start - 1].isalpha() or ord(res[start - 1]) > 127):
                    repl = ' ' + repl
            elif orig.startswith('`') and orig.endswith('`'):
                repl = f' {orig} '
            res = res[:start] + repl + res[end:]
        else:
            res = re.sub(rf'FLX\s*TOK\s*{idx}\s*END', lambda match: orig, res, flags=re.IGNORECASE)
    return res

def repair(src, dst):
    if protected(src) == protected(dst):
        return dst
    
    res = dst

    # Widget code space & double backticks
    if '`<code>widget / </code>`' in src and '`<code>widget /</code>`' in res:
        res = res.replace('`<code>widget /</code>`', '`<code>widget / </code>`')
    if '``<code>widget / </code>``' in src:
        res = re.sub(r'`\s*`<code>widget / </code>`\s*`', '``<code>widget / </code>``', res)
        res = res.replace('` `<code>widget / </code>` `', '``<code>widget / </code>``')
        res = res.replace('` `<code>widget / </code>`', '``<code>widget / </code>``')
    if '``widget / ``' in src:
        res = re.sub(r'`\s*`widget /\s*`\s*`', '``widget / ``', res)
        res = res.replace('` `widget / ` `', '``widget / ``')
        res = res.replace('` `widget / `', '``widget / ``')

    # Updater server period: `https://updater.fluxwp.com`.
    if '`https://updater.fluxwp.com`.' in src:
        res = re.sub(r'`https://updater\.fluxwp\.com`(?!\.)', '`https://updater.fluxwp.com`.', res)

    # Shortcodes &#91; ... &#93;
    if '&#91;flx_analiz' in src:
        res = re.sub(r'\[(flx_analiz_populer|flx_analiz_sayac)\]\(([^)]+)\)', r'&#91;\1&#93; (\2)', res)
        res = re.sub(r'\[(flx_analiz_populer|flx_analiz_sayac)\]', r'&#91;\1&#93;', res)

    ps = protected(src)
    pd = protected(res)
    for tag_tuple in pd:
        if isinstance(tag_tuple, tuple) and tag_tuple[0] == 'open':
            tag_name = tag_tuple[1]
            if tag_tuple not in ps:
                res = re.sub(rf'<{re.escape(tag_name)}>', f'&lt;{tag_name}&gt;', res)
                res = re.sub(rf'<{re.escape(tag_name)}([^>]*)>', f'&lt;{tag_name}\\1&gt;', res)

    res = re.sub(r'\[([^\]]+)\]\s+\((https?://[^\)]+)\)', r'[\1](\2)', res)
    res = re.sub(r'\]\(\s*([^\s\)]+)\s*\)', r'](\1)', res)
    res = re.sub(r'\[([^\]]+)\]\s+([^\(\s]+)\s+\((https?://[^\)]+)\)', r'[\1](\3) \2', res)
    res = re.sub(r'\]\s+\(', '](', res)

    ps = protected(src)
    pd = protected(res)
    for src_tok in ps:
        if isinstance(src_tok, str) and src_tok.startswith('http'):
            if src_tok not in pd:
                res = re.sub(rf'({re.escape(src_tok)})([A-Za-zÀ-ÿ\u0400-\u04FF]+)', r'\1 \2', res)

    ps = protected(src)
    pd = protected(res)
    for src_tok in ps:
        if isinstance(src_tok, str) and src_tok.startswith('`') and src_tok.endswith('`'):
            if src_tok not in pd:
                inner = src_tok[1:-1].strip()
                res = re.sub(rf'`{re.escape(inner)}`', src_tok, res)

    ps = protected(src)
    pd = protected(res)
    for k in CURLY_KEYS:
        if k in ps and k not in pd:
            foreign_curlies = re.findall(r'\{[^\}]+\}', res)
            for fc in foreign_curlies:
                if fc not in ps:
                    res = res.replace(fc, k, 1)
                    break

    if '```' in res:
        res = re.sub(r'([^\n])```', r'\1\n\n```', res)
        res = re.sub(r'```([^\n])', r'```\n\n\1', res)

    return res

def translate_html_exact(html_str, tl):
    comments = []
    def comm_sub(m):
        idx = len(comments)
        comments.append(m.group(0))
        return f'<!--FLXCOMM{idx}END-->'
    s_clean = re.sub(r'<!--[\s\S]*?-->', comm_sub, html_str)

    chunks = []
    def collector(m):
        txt = m.group(1)
        if txt.strip():
            chunks.append(txt)
        return m.group(0)

    re.sub(r'>([^<]+)<', collector, s_clean)
    
    unique_chunks = list(dict.fromkeys(chunks))
    chunk_map = {}
    batch_size = 20
    for i in range(0, len(unique_chunks), batch_size):
        b = unique_chunks[i:i + batch_size]
        b_prot = []
        b_phs = []
        for s in b:
            p, phs = protect_string(s)
            b_prot.append(p)
            b_phs.append(phs)
        combined = DELIM.join(b_prot)
        trans_comb = call_translation_api(combined, tl)
        trans_comb = unicodedata.normalize('NFKC', trans_comb)
        parts = re.split(r'<<<FLX_SPLIT>>>', trans_comb)
        if len(parts) == len(b):
            for orig, p, phs in zip(b, parts, b_phs):
                chunk_map[orig] = restore_string(p.strip(), phs).strip()
        else:
            for orig, phs in zip(b, b_phs):
                p_s, _ = protect_string(orig)
                tr = call_translation_api(p_s, tl)
                chunk_map[orig] = restore_string(tr, phs).strip()
                
    def replacer(m):
        txt = m.group(1)
        if txt in chunk_map:
            leading = re.match(r'^\s*', txt).group(0)
            trailing = re.search(r'\s*$', txt).group(0)
            return f'>{leading}{chunk_map[txt]}{trailing}<'
        return m.group(0)

    res = re.sub(r'>([^<]+)<', replacer, s_clean)
    for idx, c in enumerate(comments):
        res = res.replace(f'<!--FLXCOMM{idx}END-->', c)
        
    return repair(html_str, res)

def translate_md_exact(md_str, tl):
    blocks = re.split(r'(```[\s\S]*?```)', md_str)
    out = []
    for b in blocks:
        if b.startswith('```'):
            out.append('\n\n' + b.strip() + '\n\n')
            continue
        
        phs = []
        def add_ph(s):
            idx = len(phs)
            phs.append(s)
            return f' FLXTOK{idx}END '
        
        def link_sub(m):
            lbl = m.group(1)
            url = m.group(2)
            idx = len(phs)
            phs.append(url)
            return f'[{lbl}](FLXTOK{idx}END)'
        
        s = re.sub(r'!?\[([^\]]*)\]\(([^)]+)\)', link_sub, b)
        s = re.sub(r'`[^`\n]+`', lambda m: add_ph(m.group(0)), s)
        s = re.sub(r'https?://[^\s<>"\'\)\]]+', lambda m: add_ph(m.group(0)), s)
        s = re.sub(r'\{(?:[A-Za-z_][A-Za-z0-9_.:-]*|url:@[^}]+|id:@[^}]+)\}', lambda m: add_ph(m.group(0)), s)
        s = re.sub(r'%(?:\d+\$)?[-+0-9.]*[sdf]', lambda m: add_ph(m.group(0)), s)
        
        if not s.strip():
            out.append(s)
            continue
            
        trans = call_translation_api(s, tl)
        trans = unicodedata.normalize('NFKC', trans)
        
        for idx, orig in reversed(list(enumerate(phs))):
            pattern = re.compile(rf'\s*FLX\s*TOK\s*{idx}\s*END\s*', re.IGNORECASE)
            trans = re.sub(rf'\]\(\s*FLX\s*TOK\s*{idx}\s*END\s*\)', f']({orig})', trans, flags=re.IGNORECASE)
            trans = pattern.sub(f' {orig} ', trans)
            
        trans = re.sub(r'\[([^\]]+)\]\s+\((https?://[^\)]+)\)', r'[\1](\2)', trans)
        trans = re.sub(r'\]\s+\(', '](', trans)
        trans = re.sub(r'\]\(\s*([^\s\)]+)\s*\)', r'](\1)', trans)
        
        out.append(trans.strip())
        
    res = '\n\n'.join([part.strip() for part in out if part.strip()]) + '\n'
    return repair(md_str, res)

def translate_single(text, tl):
    if not text.strip():
        return ''
    
    if len(text) > 400 and ('<p>' in text or '<table>' in text or '<!-- wp:' in text or '<h2>' in text):
        cand = translate_html_exact(text, tl)
    elif len(text) > 400 and ('```' in text or '\n## ' in text or '\n1. ' in text):
        cand = translate_md_exact(text, tl)
    else:
        p_str, phs = protect_string(text)
        trans = call_translation_api(p_str, tl)
        trans = unicodedata.normalize('NFKC', trans)
        cand = restore_string(trans, phs)
        cand = repair(text, cand)

    return cand

def translate_batch(batch_strings, tl):
    if any(len(s) > 400 or '<p>' in s or '```' in s for s in batch_strings):
        return [translate_single(s, tl) for s in batch_strings]

    protected_batch = []
    all_phs = []
    for s in batch_strings:
        p_str, phs = protect_string(s)
        protected_batch.append(p_str)
        all_phs.append(phs)

    combined = DELIM.join(protected_batch)
    try:
        trans_combined = call_translation_api(combined, tl)
        trans_combined = unicodedata.normalize('NFKC', trans_combined)
        parts = re.split(r'<<<FLX_SPLIT>>>', trans_combined)
        if len(parts) == len(batch_strings):
            results = []
            for orig, part, phs in zip(batch_strings, parts, all_phs):
                restored = restore_string(part.strip(), phs)
                repaired = repair(orig, restored)
                if protected(orig) == protected(repaired):
                    results.append(repaired)
                else:
                    results.append(translate_single(orig, tl))
            return results
    except Exception as e:
        print(f"  [Notice] Batch fallback: {e}")

    return [translate_single(s, tl) for s in batch_strings]

def get_leaf_strings(obj):
    strings = []
    if isinstance(obj, dict):
        for v in obj.values():
            strings.extend(get_leaf_strings(v))
    elif isinstance(obj, list):
        for v in obj:
            strings.extend(get_leaf_strings(v))
    elif isinstance(obj, str):
        if obj.strip():
            strings.append(obj)
    return strings

def fill_tree(canonical_obj, trans_map):
    if isinstance(canonical_obj, dict):
        return {k: fill_tree(v, trans_map) for k, v in canonical_obj.items()}
    elif isinstance(canonical_obj, list):
        return [fill_tree(v, trans_map) for v in canonical_obj]
    elif isinstance(canonical_obj, str):
        if not canonical_obj.strip():
            return ''
        return trans_map.get(canonical_obj, canonical_obj)
    else:
        return canonical_obj

def run(target_langs=None):
    canonical = json.loads((WORKSPACE / 'en.json').read_text(encoding='utf-8'))
    all_strings = get_leaf_strings(canonical['text'])
    unique_strings = list(dict.fromkeys(all_strings))
    print(f"Canonical source strings: {len(all_strings)} (unique: {len(unique_strings)})")

    langs_to_process = target_langs if target_langs else list(TARGETS.keys())

    for lang in langs_to_process:
        config = TARGETS[lang]
        code = config['code']
        name = config['name']
        print(f"\n==========================================")
        print(f"Processing {lang.upper()} ({name}, code={code})...")
        print(f"==========================================")

        cache_file = SCRATCH / f"cache_{lang}.json"
        cache = {}
        if cache_file.exists():
            try:
                cache = json.loads(cache_file.read_text(encoding='utf-8'))
                for k, v in list(cache.items()):
                    cache[k] = repair(k, v)
                print(f"Loaded and repaired {len(cache)} cached items for {lang}")
            except Exception:
                cache = {}

        missing = [s for s in unique_strings if s not in cache or protected(s) != protected(cache[s])]
        print(f"Strings to translate/re-verify for {lang}: {len(missing)}")

        batch_size = 15
        for i in range(0, len(missing), batch_size):
            batch = missing[i:i + batch_size]
            results = translate_batch(batch, code)
            for orig, trans in zip(batch, results):
                cache[orig] = trans
            
            if (i // batch_size) % 10 == 0 or (i + batch_size) >= len(missing):
                cache_file.write_text(json.dumps(cache, ensure_ascii=False, indent=2), encoding='utf-8')
                progress = min(len(missing), i + len(batch))
                pct = int((len(unique_strings) - (len(missing) - progress)) / len(unique_strings) * 100)
                print(f"  [{lang}] Progress: {progress}/{len(missing)} ({pct}% total done)")
            time.sleep(0.08)

        for k in unique_strings:
            if k in cache:
                cache[k] = repair(k, cache[k])
                if protected(k) != protected(cache[k]):
                    single_fix = translate_single(k, code)
                    cache[k] = single_fix

        cache_file.write_text(json.dumps(cache, ensure_ascii=False, indent=2), encoding='utf-8')

        target_path = WORKSPACE / f"{lang}.json"
        target_orig = json.loads(target_path.read_text(encoding='utf-8'))
        
        new_text = fill_tree(canonical['text'], cache)
        candidate = {
            '_meta': target_orig['_meta'],
            'text': new_text
        }

        print(f"Validating {lang}.json...")
        report = validate(candidate, canonical, allow_blank=False)
        print(f"VALIDATION SUCCESS for {lang}: total={report['total']}, empty={report['empty']}, changed={report['changed']}")
        
        target_path.write_text(json.dumps(candidate, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
        print(f"[SAVED] {target_path}")

    zip_path = WORKSPACE.parent / 'fluxwp-site-translations.zip'
    if zip_path.exists():
        print("\nUpdating fluxwp-site-translations.zip...")
        with zipfile.ZipFile(zip_path, 'w', compression=zipfile.ZIP_DEFLATED) as z:
            for name in ['en'] + list(TARGETS.keys()):
                file_to_pack = WORKSPACE / f"{name}.json"
                if file_to_pack.exists():
                    z.write(file_to_pack, f"{name}.json")
        print(f"[SUCCESS] Updated {zip_path}")

    print("\nALL TARGET LANGUAGES PROCESSED AND VALIDATED SUCCESSFULLY!")

def main():
    global WORKSPACE, SCRATCH
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('languages', nargs='*', choices=list(TARGETS))
    parser.add_argument('--workspace', type=Path, required=True, help='Directory containing en.json and target JSON files')
    parser.add_argument('--cache', type=Path, help='Translation cache directory')
    parser.add_argument('--translate', action='store_true', help='Send source strings to Google Translate; otherwise validate offline only')
    args = parser.parse_args()
    WORKSPACE = args.workspace.resolve()
    SCRATCH = args.cache or WORKSPACE.parent / '.translation_cache'
    langs = args.languages or list(TARGETS)
    if args.translate:
        SCRATCH.mkdir(parents=True, exist_ok=True)
        run(langs)
    else:
        canonical = json.loads((WORKSPACE / 'en.json').read_text(encoding='utf-8'))
        for lang in langs:
            candidate = json.loads((WORKSPACE / f'{lang}.json').read_text(encoding='utf-8'))
            print(json.dumps(validate(candidate, canonical, allow_blank=False)))

if __name__ == '__main__':
    main()
