// ```widget (HTML/SVG) and ```mermaid blocks. The code never touches the page DOM: it runs in a sandboxed iframe
// (allow-scripts only, no same-origin) with a CSP; the iframe reports its height by postMessage, accepted only from
// that frame, as a bounded number.
import { useEffect, useRef, useState } from 'react';
import { useAi } from '../common';

const FONT = '-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif';
const MAX_H = 2400;
const CSP = "default-src 'none'; style-src 'unsafe-inline'; img-src data:; font-src data:; script-src 'unsafe-inline'";
const CSS_VARS: Record<string, string> = { bg: '--flux-surface', surface: '--flux-surface2', text: '--flux-text', muted: '--mut', accent: '--ac', border: '--bd', warn: '--warn', danger: '--danger' };

interface Mermaid {
  initialize(o: Record<string, unknown>): void;
  render(id: string, code: string): Promise<{ svg: string }>;
}
let mermaidReq: Promise<Mermaid> | null = null;
let seq = 0;
const svgCache = new Map<string, string>();

function loadMermaid(src: string): Promise<Mermaid> {
  const w = window as unknown as { mermaid?: Mermaid };
  if (w.mermaid) return Promise.resolve(w.mermaid);
  mermaidReq ??= new Promise<Mermaid>((ok, no) => {
    const s = document.createElement('script');
    s.src = src;
    s.async = true;
    s.onload = () => (w.mermaid ? ok(w.mermaid) : no(new Error('mermaid')));
    s.onerror = () => {
      mermaidReq = null;
      no(new Error('mermaid'));
    };
    document.head.appendChild(s);
  });
  return mermaidReq;
}

function frameDoc(key: string, body: string, vars: string, dark: boolean): string {
  const js =
    '(function(){var K=' + JSON.stringify(key) + ",last=0;function s(t,v){parent.postMessage({flxaiw:1,k:K,t:t,v:v},'*')}" +
    "window.addEventListener('error',function(){s('err',0)});" +
    "function h(){var r=document.getElementById('flxaiw-r');var v=Math.ceil(r?r.getBoundingClientRect().height:document.documentElement.scrollHeight);if(v!==last){last=v;s('h',v)}}" +
    "window.addEventListener('load',function(){var r=document.getElementById('flxaiw-r');if(!r||(!r.children.length&&!r.textContent.trim())){s('err',0);return}h();if(window.ResizeObserver)new ResizeObserver(h).observe(r);setTimeout(h,300)})})();";
  return (
    '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="' + CSP + '">' +
    '<style>:root{' + vars + 'color-scheme:' + (dark ? 'dark' : 'light') + ';}*{box-sizing:border-box}html,body{margin:0;padding:0;background:transparent;color:var(--text);font:14px/1.5 ' + FONT + ';overflow:hidden}' +
    '#flxaiw-r{display:flow-root;width:100%}svg{max-width:100%;height:auto}table{border-collapse:collapse}</style>' +
    '<script>' + js + '<\/script></head><body><div id="flxaiw-r">' + body + '</div></body></html>'
  );
}

function hash(type: string, code: string): string {
  let h = 5381;
  for (let i = 0; i < code.length; i++) h = ((h << 5) + h + code.charCodeAt(i)) | 0;
  return type[0] + (h >>> 0).toString(36) + code.length.toString(36);
}

export function Widget({ type, code, mermaid }: { type: 'widget' | 'mermaid'; code: string; mermaid: string }) {
  const { t } = useAi();
  const wrap = useRef<HTMLDivElement>(null);
  const frame = useRef<HTMLIFrameElement>(null);
  const [doc, setDoc] = useState('');
  const [height, setHeight] = useState(0);
  const [failed, setFailed] = useState(false);
  const key = hash(type, code);

  useEffect(() => {
    let live = true;
    setFailed(false);
    const el = wrap.current;
    const root = el?.closest<HTMLElement>('[data-theme]') ?? el;
    const cs = el ? getComputedStyle(root ?? el) : null;
    const dark = root?.dataset.theme === 'dark';
    let vars = '';
    for (const [n, v] of Object.entries(CSS_VARS)) {
      const val = (cs?.getPropertyValue(v) ?? '').trim().replace(/[;{}<>]/g, '');
      if (val) vars += `--${n}:${val};`;
    }
    const build = (body: string) => live && setDoc(frameDoc(key, body, vars, dark));
    if (type === 'widget') build(code);
    else {
      const cached = svgCache.get(key + (dark ? 'd' : 'l'));
      if (cached) build(cached);
      else
        loadMermaid(mermaid)
          .then((m) => {
            m.initialize({ startOnLoad: false, securityLevel: 'strict', theme: dark ? 'dark' : 'neutral', fontFamily: FONT, htmlLabels: false, flowchart: { htmlLabels: false } });
            return m.render('flxaim' + ++seq, code);
          })
          .then((r) => {
            svgCache.set(key + (dark ? 'd' : 'l'), r.svg);
            build(r.svg);
          })
          .catch(() => live && setFailed(true));
    }
    return () => {
      live = false;
    };
  }, [type, code, key, mermaid]);

  useEffect(() => {
    const onMsg = (e: MessageEvent) => {
      const d = e.data as { flxaiw?: number; k?: string; t?: string; v?: unknown } | null;
      if (!d || d.flxaiw !== 1 || d.k !== key || e.source !== frame.current?.contentWindow) return;
      if (d.t === 'err') setFailed(true);
      else if (d.t === 'h' && typeof d.v === 'number' && isFinite(d.v)) setHeight(Math.max(24, Math.min(MAX_H, Math.round(d.v))));
    };
    window.addEventListener('message', onMsg);
    return () => window.removeEventListener('message', onMsg);
  }, [key]);

  useEffect(() => {
    if (!doc || height || failed) return;
    const timer = window.setTimeout(() => setFailed(true), 8000);
    return () => window.clearTimeout(timer);
  }, [doc, height, failed]);

  if (failed)
    return (
      <>
        <div className="flxai-wg-fail">{t('The graphic could not be drawn; its code:')}</div>
        <pre>
          <code>{code}</code>
        </pre>
      </>
    );
  return (
    <div ref={wrap} className={'flxai-wg' + (height ? ' is-ready' : '')}>
      {!height && <div className="flxai-wg-ph">{t('Preparing the graphic…')}</div>}
      {doc && <iframe ref={frame} className="flxai-wg-frame" sandbox="allow-scripts" referrerPolicy="no-referrer" title={t('Graphic')} srcDoc={doc} style={height ? { height } : undefined} />}
    </div>
  );
}
