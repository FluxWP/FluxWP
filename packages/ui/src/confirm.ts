// flx-brand's dialog (wp-admin has no native confirm/alert in FluxWP); the browser's one only outside wp-admin.
declare global {
  interface Window {
    flxDialog?: { confirm: (o: { message: string; danger?: boolean }) => Promise<boolean>; alert?: (message: string) => Promise<void> };
  }
}

/** Asks yes/no in the shared dialog, as the classic pages did (window.flxDialog.confirm). */
export function confirmDialog(message: string, danger = false): Promise<boolean> {
  if (typeof window === 'undefined') return Promise.resolve(false);
  return window.flxDialog ? window.flxDialog.confirm({ message, danger }) : Promise.resolve(window.confirm(message));
}
