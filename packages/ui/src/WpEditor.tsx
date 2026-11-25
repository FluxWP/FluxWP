// The classic WordPress editor (TinyMCE + Text tab) for a React screen: the page loads it with wp_enqueue_editor().
// Where WordPress has not loaded it (app, headless, tests) a plain textarea takes its place.
import { useEffect, useRef } from 'react';

type TinyEditor = { getContent: () => string; setContent: (html: string) => void; on: (events: string, fn: () => void) => void };
type WpGlobal = {
  editor?: {
    initialize: (id: string, settings: Record<string, unknown>) => void;
    remove: (id: string) => void;
  };
};
declare global {
  interface Window {
    tinymce?: { get: (id: string) => TinyEditor | null };
  }
}

export interface WpEditorProps {
  id: string;
  value: string;
  onChange: (html: string) => void;
  rows?: number;
  /** TinyMCE toolbar (comma separated). */
  toolbar?: string;
  /** Text-tab buttons (comma separated). */
  quicktags?: string;
  label?: string | undefined;
}

export function WpEditor({ id, value, onChange, rows = 10, toolbar = 'bold,italic,link,unlink,bullist,numlist,blockquote,undo,redo', quicktags = 'strong,em,link,ul,ol,li,close', label }: WpEditorProps) {
  const box = useRef<HTMLDivElement>(null);
  const area = useRef<HTMLTextAreaElement | null>(null);
  const change = useRef(onChange);
  change.current = onChange;
  const last = useRef(value);
  const wp = typeof window !== 'undefined' ? (window as unknown as { wp?: WpGlobal }).wp : undefined;
  const rich = !!wp?.editor?.initialize;

  useEffect(() => {
    if (!rich || !wp?.editor) return;
    const emit = (html: string) => {
      if (html === last.current) return;
      last.current = html;
      change.current(html);
    };
    // WordPress wraps the textarea in its own markup, so React only owns the empty box around it.
    const host = box.current;
    if (!host) return;
    const ta = document.createElement('textarea');
    ta.id = id;
    ta.className = 'wp-editor-area';
    ta.rows = rows;
    ta.value = last.current;
    if (label) ta.setAttribute('aria-label', label);
    host.appendChild(ta);
    area.current = ta;
    const onText = () => emit(ta.value);
    ta.addEventListener('input', onText);
    wp.editor.initialize(id, {
      tinymce: {
        toolbar1: toolbar,
        wpautop: true,
        setup: (ed: TinyEditor) => ed.on('change keyup input undo redo SetContent', () => emit(ed.getContent())),
      },
      quicktags: { buttons: quicktags },
      mediaButtons: false,
    });
    return () => {
      ta.removeEventListener('input', onText);
      wp.editor?.remove(id);
      host.textContent = '';
      area.current = null;
    };
  }, [id, rich]); // eslint-disable-line react-hooks/exhaustive-deps

  // A value set from outside (reset to default, another email loaded) goes into the editor.
  useEffect(() => {
    if (!rich || value === last.current) return;
    last.current = value;
    const ed = window.tinymce?.get(id);
    if (ed) ed.setContent(value);
    if (area.current) area.current.value = value;
  }, [value, id, rich]);

  if (!rich) return <textarea id={id} rows={rows} value={value} aria-label={label} onChange={(e) => onChange(e.target.value)} />;
  return <div ref={box} className="flx-wpeditor" />;
}
