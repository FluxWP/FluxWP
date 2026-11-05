// flx-app-config/1 (docs/mobile-app-plan.md §8). Server output of GET flxapp/v1/config: site + role layers
// already merged; the app adds its default, the build brand, the user and the device layers.
import type { TargetString } from './manifest';

export type ThemeMode = 'system' | 'light' | 'dark';

/** Color tokens a config may set (missing ones come from @fluxwp/tokens). */
export interface ThemeColorsInput {
  primary?: string;
  onPrimary?: string;
  accent?: string;
  bg?: string;
  surface?: string;
  surface2?: string;
  border?: string;
  text?: string;
  textMuted?: string;
  danger?: string;
  warning?: string;
  success?: string;
  info?: string;
}

export interface ThemeConfig {
  mode?: ThemeMode;
  light?: ThemeColorsInput;
  dark?: ThemeColorsInput;
  radius?: 'small' | 'medium' | 'large';
  density?: 'compact' | 'comfortable';
  font?: { body?: string; heading?: string; files?: Record<string, string> | null };
}

export interface ShellTab {
  id: string;
  target: TargetString;
  icon?: string;
  label?: string;
  /** flxapp/v1/counts key. */
  badge?: string;
}

export interface DrawerGroup {
  id: string;
  label?: string;
  collapsed?: boolean;
  items: TargetString[];
}

export interface ShellConfig {
  nav: 'tabs' | 'drawer' | 'tabs+drawer';
  home: TargetString;
  tabs: ShellTab[];
  drawer: {
    enabled: boolean;
    header: 'account' | 'brand' | 'none';
    pins?: TargetString[];
    groups: DrawerGroup[];
    footer: TargetString[];
  };
  topbar: {
    style: 'large' | 'compact';
    show_logo: boolean;
    site_switcher: boolean;
    actions: { target: TargetString; icon?: string; label?: string }[];
  };
  /**
   * Desktop shell (`X-FluxWP-Platform: desktop`). Loose on the wire: older configs omit it and the
   * app reuses `drawer`. Not part of the published JSON Schema until the contract PR.
   */
  sidebar?: {
    pins?: TargetString[];
    groups?: DrawerGroup[];
    footer?: TargetString[];
  };
  /** Targets pinned in the desktop command palette. */
  commands?: TargetString[];
}

export interface AppConfig {
  schema: 'flx-app-config/1';
  hash?: string;
  brand?: { name?: string; short_name?: string; logo?: string; logo_dark?: string; icon?: string };
  theme?: ThemeConfig;
  shell: ShellConfig;
  features?: { web_session?: boolean; push?: boolean; otp?: boolean; search?: boolean; biometric_lock?: boolean };
  transport?: { method_override?: boolean; poll_min?: number; token_header?: 'authorization' | 'x-fluxwp-token' };
  webview?: { allow_hosts?: string[]; external?: 'in_app_browser' | 'system' };
  links?: { privacy?: string; support?: string };
  versions?: Record<string, string>;
}

/** Deep-partial layer (brand, user prefs, device). */
export type ConfigLayer = {
  theme?: ThemeConfig;
  shell?: Partial<Omit<ShellConfig, 'drawer' | 'topbar'>> & { drawer?: Partial<ShellConfig['drawer']>; topbar?: Partial<ShellConfig['topbar']> };
  features?: AppConfig['features'];
};

/** GET flxapp/v1/app/bootstrap (public, flx-app 0.4.0+). */
export interface Bootstrap {
  schema?: 'flx-app-bootstrap/1';
  site: { name: string; url: string; description?: string; admin_url?: string; rest_url?: string; icon?: string; locale?: string; timezone?: string; https?: boolean };
  auth: {
    methods: ('oauth' | 'otp' | 'apikey' | 'apppass')[];
    /** Built-in OAuth clients; empty when the site turned the generic apps off. */
    clients?: { mobile?: string; desktop?: string };
    redirects?: { mobile?: string[]; desktop?: string[]; loopback_any_port?: boolean };
    authorize?: string;
    /** Login page (Flux Speed: /fluxadmin/). A webview landing here means the session dropped. */
    login_url?: string;
    token?: string;
    revoke?: string;
    scopes?: string[];
    pkce?: 'S256';
    prompt_login?: boolean;
    otp?: { start?: string; verify?: string };
  };
  apps?: { generic?: boolean; clients?: { id: string; name: string; platform: 'mobile' | 'desktop' }[]; preset?: string | null; store?: Record<string, string> };
  brand?: { name?: string; short_name?: string; logo?: string; logo_dark?: string; icon?: string };
  theme?: { mode?: ThemeMode; light?: ThemeColorsInput; dark?: ThemeColorsInput };
  packages?: { slug: string; name: string; type: 'plugin' | 'theme' }[];
  min_app?: string;
  min_app_desktop?: string;
  flx_app?: string;
  api?: string;
  endpoints?: Record<string, string>;
}
