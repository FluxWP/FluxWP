// flx-app-manifest/1 (docs/mobile-app-plan.md §7). Server output of GET flxapp/v1/manifest.
// Manifests are data only: templates and conditions, never code. Permissions stay on the server.

/** "{{path}}" or "{{path|filter:arg}}" template, or plain text. */
export type Tpl = string;

export type Tone = 'default' | 'accent' | 'ok' | 'warn' | 'danger' | 'muted' | 'info';

export type Op = 'eq' | 'ne' | 'in' | 'nin' | 'gt' | 'gte' | 'lt' | 'lte' | 'filled' | 'empty' | 'truthy' | 'falsy';
export type Condition = { path: string; op: Op; value?: unknown } | { all: Condition[] } | { any: Condition[] } | { not: Condition };

/** §6 target string: `screen:<module>/<screen>`, `module:<id>`, `menu:<slug>`, `web:<path>`, `url:<…>`, `native:<c>`, or a shell name. */
export type TargetString = string;
export interface Navigate {
  target: TargetString;
  params?: Record<string, Tpl | number | boolean>;
}

export interface Paging {
  /** page: body has items/total/pages · wp: bare array + X-WP-Total(Pages) headers · cursor · none */
  mode: 'page' | 'wp' | 'cursor' | 'none';
  param?: string;
  size_param?: string;
  size?: number;
  items?: string;
  total?: string;
  pages?: string;
  /** cursor mode: response field with the next cursor. */
  next?: string;
}

export interface Source {
  /** REST path relative to /wp-json/; may contain templates. Absolute URLs are rejected. */
  path: Tpl;
  method?: 'GET' | 'POST';
  query?: Record<string, Tpl | number | boolean>;
  body?: Record<string, unknown> | null;
  paging?: Paging;
  key?: string;
  cache?: { stale?: number; persist?: boolean };
  /** Seconds; 0 = off, minimum 10. */
  poll?: number;
}

export interface Badge {
  text: Tpl;
  tone?: Tone;
  when?: Condition;
}

export interface ListItem {
  title: Tpl;
  subtitle?: Tpl;
  meta?: Tpl;
  caption?: Tpl;
  avatar?: { image?: Tpl; text?: Tpl };
  badges?: Badge[];
  chips?: { path: string; text: Tpl; color?: Tpl };
  image?: Tpl;
  unread?: Condition | string;
}

export interface ListFilter {
  param: string;
  label: string;
  type: 'select' | 'toggle' | 'segmented';
  options?: { value: string; label: string }[] | { source: string; path: string; value: string; label: string };
}

export interface ScreenActions {
  header?: string[];
  row?: string[];
  bulk?: string[];
  fab?: string;
  footer?: string[];
}

interface ScreenBase {
  title?: Tpl;
  when?: Condition;
}

export interface ListScreen extends ScreenBase {
  type: 'list';
  source: string;
  layout?: 'rows' | 'cards' | 'grid' | 'table';
  columns?: { label: string; value: Tpl; width?: number; align?: 'start' | 'end' }[];
  search?: { param: string; placeholder?: string };
  filters?: ListFilter[];
  sort?: { param: string; order_param?: string; options: { value: string; label: string }[] };
  group_by?: string;
  item: ListItem;
  onPress?: Navigate;
  actions?: ScreenActions;
  empty?: { title?: string; body?: string; icon?: string };
  refresh?: boolean;
}

export type DetailSection =
  | { type: 'fields'; title?: string; when?: Condition; fields: { label: string; value: Tpl; link?: Tpl; copy?: boolean; when?: Condition }[] }
  | { type: 'stats'; title?: string; when?: Condition; items: { label: string; value: Tpl; sub?: Tpl; tone?: Tone; when?: Condition; onPress?: Navigate }[] }
  | { type: 'chips'; title?: string; when?: Condition; path: string; text: Tpl; color?: Tpl; add?: string; remove?: string }
  | { type: 'timeline'; title?: string; when?: Condition; path: string; item: { title: Tpl; body?: Tpl; at?: Tpl; label?: Tpl; link?: Tpl } }
  | { type: 'list'; title?: string; when?: Condition; path: string; item: ListItem; onPress?: Navigate }
  | { type: 'content'; title?: string; when?: Condition; value: Tpl; format?: 'markdown' | 'html' | 'text' }
  | { type: 'actions'; title?: string; when?: Condition; items: string[] }
  | { type: 'notice'; title?: string; when?: Condition; tone?: Tone; text: Tpl }
  /**
   * One point per row at `path` (rows may be plain numbers). The first series picks each point's value:
   * `value` template, or the row field `key` (label from the row field `x`). Without series: field `x`
   * as the label, the row's first number as the value.
   */
  | { type: 'chart'; title?: string; when?: Condition; chart?: 'bar' | 'line'; path: string; x?: string; series?: { label?: Tpl; value?: Tpl; key?: string }[] };

export interface DetailScreen extends ScreenBase {
  type: 'detail';
  source: string;
  header?: { title: Tpl; subtitle?: Tpl; avatar?: { image?: Tpl; text?: Tpl }; chips?: { path: string; text: Tpl }; quick?: string[] };
  sections: DetailSection[];
  actions?: ScreenActions;
}

export type FieldType =
  | 'text' | 'textarea' | 'email' | 'phone' | 'url' | 'number' | 'money' | 'switch' | 'select' | 'multiselect' | 'radio' | 'segmented'
  | 'date' | 'datetime' | 'time' | 'color' | 'image' | 'file' | 'markdown' | 'user' | 'post' | 'term' | 'tags' | 'hidden' | 'readonly'
  | 'secret' | 'repeater';

export interface FormField {
  /** Dot path: "consent.email". */
  name: string;
  type: FieldType;
  label: string;
  placeholder?: string;
  help?: string;
  required?: boolean;
  min?: number;
  max?: number;
  minLength?: number;
  maxLength?: number;
  pattern?: string;
  default?: unknown;
  options?: { value: string; label: string }[] | { source: string; path: string; value: string; label: string };
  when?: Condition;
  width?: 'full' | 'half';
  readonly_when?: Condition;
  creatable?: boolean;
  /** Read path when it differs from `name` (e.g. `title.raw`). */
  load?: string;
  /** Upload endpoint for image/file. Default `wp/v2/media`. */
  upload?: string;
  /** REST base of a term field. Default `categories`. */
  taxonomy?: string;
  multiple?: boolean;
}

export interface After {
  toast?: string;
  back?: boolean;
  navigate?: Navigate;
  refresh?: boolean;
  invalidate?: string[];
  merge?: boolean;
}

export interface RequestSpec {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  path: Tpl;
  body?: Record<string, unknown> | null;
  /** Form screens only: create vs update path, picked by `params.id`. */
  path_create?: Tpl;
  path_update?: Tpl;
}

export interface FormScreen extends ScreenBase {
  type: 'form';
  load?: string;
  fields: FormField[];
  submit: RequestSpec;
  validate?: { any_of?: string[]; message?: string };
  after?: After;
  dirty_guard?: boolean;
}

export interface DashboardScreen extends ScreenBase {
  type: 'dashboard';
  sources?: string[];
  sections: DetailSection[];
}

export interface SettingsScreen extends ScreenBase {
  type: 'settings';
  option: string;
  fields: FormField[];
}

export interface ContentScreen extends ScreenBase {
  type: 'content';
  source: string;
  format?: 'markdown' | 'html';
  path?: string;
}

export interface TabsScreen extends ScreenBase {
  type: 'tabs';
  tabs: { screen: string; title: string }[];
}

export interface NativeScreen extends ScreenBase {
  type: 'native';
  component: string;
  params?: Record<string, Tpl>;
  fallback?: { web: string };
}

export interface WebScreen extends ScreenBase {
  type: 'web';
  path: string;
}

export type Screen = ListScreen | DetailScreen | FormScreen | DashboardScreen | SettingsScreen | ContentScreen | TabsScreen | NativeScreen | WebScreen;
export type ScreenType = Screen['type'];

export interface Action {
  label: string;
  icon?: string;
  tone?: Tone;
  can?: string;
  when?: Condition;
  confirm?: { title: string; body?: string; ok?: string };
  prompt?: { title: string; fields: FormField[] } | null;
  request?: RequestSpec;
  navigate?: Navigate;
  web?: Tpl;
  open?: Tpl;
  share?: { text?: Tpl; url?: Tpl };
  copy?: Tpl;
  native?: { component: string; params?: Record<string, Tpl> };
  refresh?: boolean;
  after?: After;
  optimistic?: { path?: string; value?: unknown; remove?: 'item' } | null;
  haptic?: 'success' | 'warning' | 'error' | 'light';
}

export interface Manifest {
  schema: 'flx-app-manifest/1';
  id: string;
  plugin?: string;
  version?: string;
  min_app?: string;
  title: string;
  icon?: string;
  icon_url?: string;
  menu?: string;
  entry: string;
  tabs?: string[];
  can?: Record<string, boolean>;
  routes?: Record<string, { screen: string; params?: Record<string, string> }>;
  sources: Record<string, Source>;
  screens: Record<string, Screen>;
  actions?: Record<string, Action>;
  fallback?: { web: string };
  search?: { source: string; param: string; item: ListItem; onPress?: Navigate };
  push?: { events: { id: string; title: string; default?: boolean }[] };
}

/** GET flxapp/v1/manifest */
export interface ManifestBundle {
  hash?: string;
  modules: Record<string, Manifest>;
}
