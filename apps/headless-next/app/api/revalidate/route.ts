// Revalidate webhook (FluxWP › Headless › Webhook address = https://<this site>/api/revalidate).
// WordPress POSTs {event, tags, paths} signed with X-Flx-Signature; every page cached under one of the
// tags is refreshed on its next visit.
import { revalidatePath, revalidateTag } from 'next/cache';
import { SECRET } from '@/lib/env';
import { verifySignature } from '@/lib/verify';

export async function POST(req: Request): Promise<Response> {
  const body = await req.text(); // The raw body: the signature covers these exact bytes.
  if (!(await verifySignature(body, req.headers.get('x-flx-signature'), SECRET))) {
    return Response.json({ ok: false, error: 'bad signature' }, { status: 401 });
  }
  const { event, tags = [], paths = [] } = JSON.parse(body) as { event: string; tags?: string[]; paths?: string[] };
  if (event === 'all' || tags.includes('flx-headless:all')) {
    revalidateTag('flx-headless:all', { expire: 0 });
    revalidatePath('/', 'layout');
  } else {
    for (const t of tags) revalidateTag(t, { expire: 0 });
    for (const p of paths) revalidatePath(p);
  }
  return Response.json({ ok: true, event, tags: tags.length, paths: paths.length });
}
