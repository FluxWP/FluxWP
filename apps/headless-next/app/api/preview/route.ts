// The editor's Preview button opens /api/preview?id=&type=&exp=&sig= (FluxWP › Headless › Preview path).
// sig = HMAC-SHA256(secret, "preview|<id>|<exp>"), valid until exp.
import { draftMode } from 'next/headers';
import { redirect } from 'next/navigation';
import { RENDER, SECRET, WP_URL } from '@/lib/env';
import { hmacHex, sameHex } from '@/lib/verify';

export async function GET(req: Request): Promise<Response> {
  const q = new URL(req.url).searchParams;
  const id = Number(q.get('id'));
  const exp = Number(q.get('exp'));
  const secret = SECRET;
  const ok = secret && id > 0 && exp > Date.now() / 1000 && sameHex(await hmacHex(secret, `preview|${id}|${exp}`), q.get('sig') ?? '');
  if (!ok) return new Response('Invalid or expired preview link', { status: 401 });
  // Whole-page mode: WordPress shows its own preview to the signed-in editor.
  if (RENDER === 'wordpress') redirect(`${WP_URL}/?p=${id}&preview=true`);
  (await draftMode()).enable();
  redirect(`/__preview/${id}/`);
}
