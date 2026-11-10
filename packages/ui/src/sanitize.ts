// Sunucudan gelen HTML'i (görev açıklaması vb.) güvenli alt kümeye indirger.
import { platform } from '@fluxwp/core';

const ALLOWED = new Set(['P', 'BR', 'B', 'STRONG', 'I', 'EM', 'U', 'S', 'STRIKE', 'DEL', 'UL', 'OL', 'LI', 'A', 'CODE', 'PRE', 'BLOCKQUOTE', 'H2', 'H3', 'H4', 'SPAN', 'IMG', 'DIV', 'HR']);
const DROP = new Set(['SCRIPT', 'STYLE', 'IFRAME', 'FRAME', 'FRAMESET', 'OBJECT', 'EMBED', 'APPLET', 'LINK', 'META', 'BASE', 'NOSCRIPT', 'TEMPLATE', 'SVG', 'MATH']);
const CONTROLS = /[\u0000-\u001F\u007F-\u009F]/;

function safeUrl(v: string): boolean {
  if (CONTROLS.test(v) || v.includes('\\')) return false;
  const u = v.replace(/[\u0000-\u0020]+/g, '').toLowerCase();
  const m = /^([a-z][a-z0-9+.-]*):/.exec(u);
  return !m || m[1] === 'http' || m[1] === 'https' || m[1] === 'mailto' || m[1] === 'tel';
}

function safeImageSrc(v: string): boolean {
  if (CONTROLS.test(v) || v.includes('\\')) return false;
  const u = v.replace(/[\u0000-\u0020]+/g, '');
  if (/^data:/i.test(u)) return /^data:image\/(?:png|jpeg|gif|webp);base64,[a-z0-9+/]+=*$/i.test(u);
  return safeUrl(u);
}

function keepAttr(tag: string, name: string, value: string): boolean {
  if (tag === 'A' && name === 'href') return safeUrl(value);
  if (tag === 'IMG' && name === 'src') return safeImageSrc(value);
  if (tag === 'IMG' && name === 'alt') return !CONTROLS.test(value);
  if (tag === 'IMG' && name === 'data-file') return /^\d{1,12}$/.test(value);
  if (tag === 'UL' && name === 'data-checklist') return value === '1';
  if (tag === 'LI' && name === 'data-checked') return value === '0' || value === '1';
  if (tag === 'SPAN' && name === 'class') {
    const parts = value.split(/\s+/).filter(Boolean);
    return parts.length > 0 && parts.every((p) => p === 'flxg-mention' || p === 'is-me');
  }
  return false;
}

function stripComments(el: Element) {
  for (const n of Array.from(el.childNodes)) {
    if (n.nodeType === 8) n.remove();
    else if (n.nodeType === 1) stripComments(n as Element);
  }
}

function walk(el: Element) {
  for (const child of Array.from(el.children)) {
    if (DROP.has(child.tagName)) {
      child.remove();
      continue;
    }
    walk(child);
    if (!ALLOWED.has(child.tagName)) {
      child.replaceWith(...Array.from(child.childNodes));
      continue;
    }
    for (const attr of Array.from(child.attributes)) {
      if (!keepAttr(child.tagName, attr.name, attr.value)) child.removeAttribute(attr.name);
    }
    if (child.tagName === 'A' && child.hasAttribute('href')) {
      child.setAttribute('target', '_blank');
      child.setAttribute('rel', 'noopener noreferrer');
    }
  }
}

function once(html: string): string {
  const doc = new DOMParser().parseFromString(html, 'text/html');
  stripComments(doc.body);
  walk(doc.body);
  return doc.body.innerHTML;
}

export function sanitizeHtml(html: string): string {
  if (!html || typeof DOMParser === 'undefined') return '';
  return once(once(html));
}

/** A click on a sanitised link leaves the app (system browser or a new tab). */
export function openSanitizedLink(event: { target: EventTarget | null; preventDefault(): void }): void {
  const node = event.target;
  const el = node instanceof Element ? node : node instanceof Node ? node.parentElement : null;
  if (!el) return;
  const a = el.closest('a');
  if (!a) return;
  const href = a.getAttribute('href');
  if (!href || !safeUrl(href)) return;
  event.preventDefault();
  try {
    void platform().openExternal(href);
  } catch {
    /* host has no opener */
  }
}
