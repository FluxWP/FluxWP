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

/** GET flxapp/v1/app/bootstrap (public). */
export interface Bootstrap {
  site: { name: string; url: string; icon?: string; locale?: string };
  auth: { methods: ('oauth' | 'otp' | 'apikey' | 'apppass')[]; clients?: { mobile?: string }; prompt_login?: boolean };
  theme?: { light?: ThemeColorsInput; dark?: ThemeColorsInput };
  min_app?: string;
  flx_app?: string;
}
