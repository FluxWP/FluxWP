// The browser PDF renderer already shipped with the plugin (assets/js/renderer.js).
export interface FlxsRenderApi {
  ready: () => Promise<unknown>;
  renderPdf: (config: unknown, values: Record<string, string>, meta: Record<string, string>, opts?: { preview?: boolean }) => Promise<Uint8Array>;
  renderToCanvas: (bytes: Uint8Array, canvas: HTMLCanvasElement, width?: number) => Promise<unknown>;
  toJpeg: (bytes: Uint8Array, width: number, quality: number) => Promise<{ blob: Blob; w: number; h: number }>;
  toShareImage: (bytes: Uint8Array, info: { title: string; name: string; org?: string }) => Promise<{ blob: Blob }>;
}

declare global {
  interface Window {
    FLXS_RENDER?: Record<string, unknown>;
    FLXSRender?: FlxsRenderApi;
    PDFLib?: { PDFDocument: { create: () => Promise<PdfDoc>; load: (b: Uint8Array) => Promise<PdfDoc> } };
  }
}

interface PdfDoc {
  getPageIndices: () => number[];
  copyPages: (src: PdfDoc, idx: number[]) => Promise<unknown[]>;
  addPage: (page: unknown) => void;
  save: () => Promise<Uint8Array>;
}

const loaded = new Set<string>();

function loadScript(src: string): Promise<void> {
  if (loaded.has(src)) return Promise.resolve();
  return new Promise((resolve, reject) => {
    const s = document.createElement('script');
    s.src = src;
    s.async = false;
    s.onload = () => {
      loaded.add(src);
      resolve();
    };
    s.onerror = () => reject(new Error(src));
    document.head.appendChild(s);
  });
}

/** Loads the renderer scripts in order and publishes FLXS_RENDER. */
export async function ensureRenderer(scripts: string[], configJson: string): Promise<FlxsRenderApi> {
  try {
    window.FLXS_RENDER = JSON.parse(configJson) as Record<string, unknown>;
  } catch {
    window.FLXS_RENDER = {};
  }
  for (const src of scripts) await loadScript(src);
  if (!window.FLXSRender) throw new Error('renderer');
  await window.FLXSRender.ready();
  return window.FLXSRender;
}

export async function mergePdfs(parts: Uint8Array[]): Promise<Blob> {
  const PDFLib = window.PDFLib;
  if (!PDFLib) throw new Error('pdf');
  const out = await PDFLib.PDFDocument.create();
  for (const bytes of parts) {
    const src = await PDFLib.PDFDocument.load(bytes);
    const pages = await out.copyPages(src, src.getPageIndices());
    pages.forEach((page) => out.addPage(page));
  }
  const saved = await out.save();
  return bytesToBlob(saved, 'application/pdf');
}

/** pdf-lib's Uint8Array is not a DOM BlobPart under the current TypeScript DOM lib. */
export function bytesToBlob(bytes: Uint8Array, type: string): Blob {
  const copy = new Uint8Array(bytes.byteLength);
  copy.set(bytes);
  return new Blob([copy.buffer], { type });
}
