// Classic email preview box (.flxi-preview): desktop / phone / dark switch, "Download PDF", and a frame that grows with the email.
import { useRef, useState, type ReactNode } from 'react';
import { useFlxMutation } from '@fluxwp/graphql/react';
import { FlxMailPreviewPdf } from '@fluxwp/graphql/operations';
import type { FlxMailPreviewPdfInput } from '@fluxwp/graphql/types';
import { useMail } from './common';

export type Device = 'desktop' | 'phone' | 'dark';

/** The dark preview turns the email's dark-mode rules on for every viewer. */
export const darkHtml = (html: string): string => html.replace(/@media\s*\(\s*prefers-color-scheme\s*:\s*dark\s*\)/gi, '@media all');

export function Preview({
  head,
  html,
  device,
  onDevice,
  pdf,
  note,
  busy,
}: {
  head: ReactNode;
  html: string;
  device: Device;
  onDevice: (d: Device) => void;
  pdf: () => FlxMailPreviewPdfInput;
  note?: string;
  busy?: boolean;
}) {
  const { t } = useMail();
  const frame = useRef<HTMLIFrameElement>(null);
  const [wait, setWait] = useState(false);
  const toPdf = useFlxMutation(FlxMailPreviewPdf);
  const shown = device === 'dark' ? darkHtml(html) : html;

  const fit = () => {
    const f = frame.current;
    const doc = f?.contentDocument;
    if (!f || !doc) return;
    f.style.height = Math.max(420, doc.documentElement.scrollHeight) + 'px';
    doc.querySelectorAll('img').forEach((im) => {
      if (!im.complete) im.addEventListener('load', () => f && (f.style.height = Math.max(420, doc.documentElement.scrollHeight) + 'px'), { once: true });
    });
  };

  const download = () => {
    if (!html || wait) return;
    setWait(true);
    void toPdf
      .mutateAsync({ input: { ...pdf(), dark: device === 'dark' } })
      .then((r) => {
        const file = r.flxMailPreviewPdf?.pdf;
        if (!file?.data) throw new Error('pdf');
        const bin = atob(file.data);
        const bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        const url = URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' }));
        const a = document.createElement('a');
        a.href = url;
        a.download = file.name || 'e-posta.pdf';
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 4000);
      })
      .catch(() => printHtml(shown))
      .finally(() => setWait(false));
  };

  return (
    <div className={'flxi-preview' + (busy ? ' is-loading' : '')}>
      <div className="flxi-preview__bar">
        {head}
        <div className="flxi-seg" role="group">
          {(['desktop', 'phone', 'dark'] as const).map((d) => (
            <button key={d} type="button" className={device === d ? 'is-active' : ''} aria-pressed={device === d} onClick={() => onDevice(d)}>
              <span className={'dashicons dashicons-' + (d === 'desktop' ? 'desktop' : d === 'phone' ? 'smartphone' : 'admin-appearance')} />
              <span className="screen-reader-text">{t(d === 'desktop' ? 'Desktop' : d === 'phone' ? 'Phone' : 'Dark mode')}</span>
            </button>
          ))}
        </div>
        <button type="button" className="button flxi-pdf-btn" disabled={wait} onClick={download}>
          <span className="dashicons dashicons-pdf" aria-hidden="true" />
          {wait ? t('Preparing PDF…') : t('Download PDF')}
        </button>
      </div>
      <div className={'flxi-preview__frame' + (device === 'phone' ? ' is-mobile' : device === 'dark' ? ' is-dark' : '')}>
        <iframe ref={frame} title={t('Email preview')} sandbox="allow-same-origin" srcDoc={shown} onLoad={fit} />
      </div>
      {note ? <p className="flxi-muted flxi-preview__note">{note}</p> : null}
    </div>
  );
}

/** When the server cannot make a PDF: print the email from a hidden frame ("Save as PDF" in the browser). */
function printHtml(html: string) {
  const f = document.createElement('iframe');
  f.setAttribute('aria-hidden', 'true');
  f.style.cssText = 'position:fixed;right:0;bottom:0;width:820px;height:10px;border:0;opacity:0;pointer-events:none';
  document.body.appendChild(f);
  const d = f.contentDocument;
  if (!d) return;
  const head = '<style>@page{margin:0}html,body{-webkit-print-color-adjust:exact;print-color-adjust:exact}</style>';
  d.open();
  d.write(/<head[^>]*>/i.test(html) ? html.replace(/<head([^>]*)>/i, '<head$1>' + head) : head + html);
  d.close();
  setTimeout(() => {
    f.contentWindow?.focus();
    f.contentWindow?.print();
    setTimeout(() => f.remove(), 60000);
  }, 300);
}
