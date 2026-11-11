import { Window } from 'happy-dom';
import { beforeAll, describe, expect, it } from 'vitest';
import { sanitizeHtml } from './sanitize';

beforeAll(() => {
  const w = new Window();
  globalThis.DOMParser = w.DOMParser as unknown as typeof DOMParser;
  globalThis.Node = w.Node as unknown as typeof Node;
  globalThis.Element = w.Element as unknown as typeof Element;
});

describe('sanitizeHtml', () => {
  it('keeps rich text and checklist images, and forces link rel', () => {
    const html = sanitizeHtml('<p>Hi <strong>there</strong></p><ul data-checklist="1"><li data-checked="0">A</li></ul><img data-file="12" alt="pic"><a href="https://x.org/a">x</a>');
    expect(html).toContain('<strong>there</strong>');
    expect(html).toContain('data-checklist="1"');
    expect(html).toContain('data-checked="0"');
    expect(html).toContain('data-file="12"');
    expect(html).toContain('rel="noopener noreferrer"');
    expect(html).toContain('target="_blank"');
  });

  it('drops scripts, handlers, forms and unsafe URLs', () => {
    const html = sanitizeHtml('<p onclick="alert(1)">x</p><script>alert(1)</script><iframe src="https://evil.test"></iframe><form action="https://evil.test"><input name="t"></form><a href="javascript:alert(1)">j</a><img src="data:image/svg+xml,<svg>"><img src="data:image/png;base64,aaaa" alt="ok"><style>body{background:url(https://evil.test)}</style>');
    expect(html.toLowerCase()).not.toContain('script');
    expect(html.toLowerCase()).not.toContain('onclick');
    expect(html.toLowerCase()).not.toContain('iframe');
    expect(html.toLowerCase()).not.toContain('form');
    expect(html.toLowerCase()).not.toContain('javascript');
    expect(html.toLowerCase()).not.toContain('svg');
    expect(html.toLowerCase()).not.toContain('style');
    expect(html).toContain('data:image/png;base64,aaaa');
    expect(html).toContain('>x<');
    expect(html).toContain('>j<');
  });
});
