// Flux App (flxapp/v1) cevap tipleri. Kaynak: docs/flx-app/API.md

export interface Role { id: string; name: string }

export interface MeUser {
  id: number;
  login: string;
  name: string;
  email: string;
  avatar: string;
  roles: Role[];
  locale: string;
  is_admin: boolean;
  profile_url: string;
}

export interface MeSite {
  name: string;
  url: string;
  admin_url: string;
  rest_url: string;
  icon: string;
  locale: string;
  timezone: string;
}

export interface MenuSubItem { slug: string; title: string; url: string; badge?: number | string }

export interface MenuItem {
  id: string;
  slug: string;
  title: string;
  url: string;
  icon: string;
  /** Paketin renkli logosu (Flux App gönderirse). */
  icon_url?: string;
  badge?: number | string;
  items: MenuSubItem[];
}

/** WP'deki Flux Admin menü düzeni (kişinin sabitledikleri + gruplar). Flux Admin yoksa gelmez. */
export interface MenuLayout {
  pins: string[];
  groups: { id: string; label: string; collapsed?: boolean; items: string[] }[];
}

export interface ModuleInfo {
  title: string;
  cap?: string;
  menu?: string;
  api?: string;
  icon?: string;
  version?: string;
  order?: number;
}

export interface Me {
  user: MeUser;
  site: MeSite;
  auth: { kind: string; client?: string; scope?: string; expires?: number };
  menu: MenuItem[];
  modules: Record<string, ModuleInfo>;
  counts: Record<string, number>;
  bottom: string[];
  layout?: MenuLayout;
  web_session: boolean;
  versions: Record<string, string>;
}

export interface DashboardCard {
  id: string;
  title: string;
  value: string;
  sub?: string;
  icon?: string;
  tone?: '' | 'accent' | 'warn' | 'danger' | 'ok';
  url?: string;
  open?: string;
  order?: number;
}

export interface DashboardList {
  id: string;
  title: string;
  icon?: string;
  url?: string;
  order?: number;
  items: { title: string; sub?: string; meta?: string; url?: string }[];
}

export interface Dashboard { cards: DashboardCard[]; lists: DashboardList[] }

export interface TokenResponse {
  access_token: string;
  token_type: string;
  expires_in: number;
  refresh_token?: string;
  scope?: string;
}
