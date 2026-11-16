// Browser-side CSV download (UTF-8 with BOM so Excel opens Turkish characters correctly).
const cell = (v: string) => {
  // Spreadsheet formulas: neutralise a leading = + - @ so imported text never runs.
  const safe = /^[=+\-@\t\r]/.test(v) ? "'" + v : v;
  return /[",\n\r;]/.test(safe) ? '"' + safe.replace(/"/g, '""') + '"' : safe;
};

export function csvDownload(name: string, head: string[], rows: string[][]): void {
  const body = [head, ...rows].map((r) => r.map(cell).join(',')).join('\r\n');
  const url = URL.createObjectURL(new Blob(['﻿' + body], { type: 'text/csv;charset=utf-8' }));
  const a = document.createElement('a');
  a.href = url;
  a.download = name;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 2000);
}
