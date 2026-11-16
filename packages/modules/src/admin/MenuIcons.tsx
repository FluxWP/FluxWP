// The server sends each menu item's icon as the inner markup of a 24×24 line icon. It is never injected
// as HTML: only known shape tags and attributes are rebuilt as React elements.
import { createElement, useMemo, type ReactNode } from 'react';

const SHAPES = new Set(['path', 'circle', 'rect', 'line', 'polyline', 'ellipse', 'polygon']);
const NUMERIC = new Set(['d', 'cx', 'cy', 'r', 'x', 'y', 'width', 'height', 'rx', 'ry', 'x1', 'x2', 'y1', 'y2', 'points']);
const SAFE_VALUE = /^[\w\s.,+-]*$/;

function build(el: Element, key: number): ReactNode {
  const tag = el.tagName.toLowerCase();
  if (!SHAPES.has(tag)) return null;
  const props: Record<string, string> = { key: String(key) };
  for (const a of Array.from(el.attributes)) {
    if (NUMERIC.has(a.name) && SAFE_VALUE.test(a.value)) props[a.name] = a.value;
    else if (a.name === 'fill' && (a.value === 'currentColor' || a.value === 'none')) props.fill = a.value;
  }
  return createElement(tag, props);
}

export function MenuSvg({ markup, size = 16 }: { markup: string; size?: number }) {
  const shapes = useMemo(() => {
    const doc = new DOMParser().parseFromString('<svg xmlns="http://www.w3.org/2000/svg">' + markup + '</svg>', 'image/svg+xml');
    return Array.from(doc.documentElement.children).map(build);
  }, [markup]);
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      {shapes}
    </svg>
  );
}
