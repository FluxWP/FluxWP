// Shapes of the Flux Headless answers this example reads (see docs/flx-headless/README.md).

export type RouteKind = 'FRONT_PAGE' | 'HOME' | 'SINGULAR' | 'TERM' | 'POST_TYPE' | 'AUTHOR' | 'DATE' | 'SEARCH' | 'REDIRECT' | 'NOT_FOUND';

export interface Head {
  title: string;
  description: string;
  canonical: string;
  robots: string[];
  noindex: boolean;
  image: string;
  locale: string;
  meta: { name: string; property: string; content: string }[];
  jsonLd: string[];
}

export interface Image {
  sourceUrl: string;
  altText: string;
  mediaDetails: { width: number | null; height: number | null } | null;
}

export interface RouteNode {
  __typename: string;
  uri: string | null;
  title?: string | null;
  databaseId?: number;
  date?: string | null;
  modified?: string | null;
  contentTypeName?: string;
  content?: string | null;
  excerpt?: string | null;
  featuredImage?: { node: Image } | null;
  author?: { node: { name: string } } | null;
  name?: string | null;
  description?: string | null;
}

export interface Route {
  path: string;
  kind: RouteKind;
  status: number;
  redirect: string | null;
  lang: string | null;
  title: string;
  page: number;
  databaseId: number | null;
  postType: string | null;
  taxonomy: string | null;
  head: Head;
  alternates: { lang: string; hreflang: string; url: string; path: string }[];
  cacheTags: string[];
  frontendId: string | null;
  node: RouteNode | null;
}

export interface MenuItem {
  id: string;
  parentId: string | null;
  label: string;
  url: string;
  path: string | null;
  target: string | null;
  external: boolean;
  order: number;
}

export interface Menu {
  location: string;
  name: string;
  source: string;
  items: MenuItem[];
}

export interface Site {
  name: string;
  description: string;
  url: string;
  cmsUrl: string;
  language: string;
  locale: string;
  languages: { code: string; locale: string; name: string; hreflang: string; path: string; url: string; isDefault: boolean }[];
  logo: { url: string; width: number; height: number; alt: string } | null;
  icon: string | null;
  menus: Menu[];
  palette: { slug: string; name: string; color: string }[];
  contact: { email: string; phone: string; whatsapp: string; address: string };
  legalPages: { kind: string; title: string; path: string; url: string }[];
  social: string[];
  sitemapUrl: string;
  cacheTags: string[];
}

export interface PostCard {
  databaseId: number;
  title: string;
  uri: string;
  date: string;
  excerpt: string | null;
  featuredImage: { node: Image } | null;
}
