// Renderer contract (plan §2.2): each platform supplies its views and these UI/navigation ports.
// The runtime decides *what* happens (confirm → request → invalidate → toast → back); the renderer draws it.
import type { FormField, ScreenType } from '@fluxwp/manifest';
import type { Route } from './resolve';

export interface ConfirmRequest {
  title: string;
  body?: string;
  ok?: string;
  tone?: 'default' | 'danger';
}

export interface RendererUI {
  confirm(req: ConfirmRequest): Promise<boolean>;
  /** Small form in a sheet; resolves with values or null when dismissed. */
  prompt(req: { title: string; fields: FormField[] }): Promise<Record<string, unknown> | null>;
  toast(message: string, tone?: 'ok' | 'error'): void;
  copy(text: string): Promise<void>;
  share(req: { text?: string; url?: string }): Promise<void>;
  haptic?(kind: 'success' | 'warning' | 'error' | 'light'): void;
}

export interface RendererNav {
  push(route: Route): void;
  replace(route: Route): void;
  back(): void;
  /** Opens an external target (tel:, mailto:, https:) in the in-app browser or the system. */
  openUrl(url: string): void;
}

export interface Renderer {
  ui: RendererUI;
  nav: RendererNav;
  /** Screen types this renderer can draw; others open their web fallback. */
  screens: ReadonlySet<ScreenType>;
  /** Native component ids (`native:<id>`, module ids with a custom screen). */
  native: ReadonlySet<string>;
  /** Text for "$t:" keys and app strings. */
  t(key: string, vars?: Record<string, unknown>): string;
}
