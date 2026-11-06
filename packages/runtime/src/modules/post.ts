// Light post editor payload. The block body stays in the web editor (plan §13).
export type PostStatus = 'draft' | 'pending' | 'publish';

export interface PostDraft {
  title: string;
  excerpt: string;
  status: PostStatus;
  /** HTML from the rich editor. Omitted on older callers. */
  content?: string;
}

export interface WpPost {
  id: number;
  status: string;
  link?: string;
  title?: { raw?: string; rendered?: string };
  excerpt?: { raw?: string; rendered?: string };
}

export function postBody(d: PostDraft): { title: string; excerpt: string; status: PostStatus; content: string } {
  return { title: d.title.trim(), excerpt: d.excerpt.trim(), status: d.status, content: (d.content ?? '').trim() };
}

export function postEditPath(id: number): string {
  return `post.php?post=${id}&action=edit`;
}
