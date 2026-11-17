import { draftMode } from 'next/headers';
import { redirect } from 'next/navigation';

export async function GET(): Promise<Response> {
  (await draftMode()).disable();
  redirect('/');
}
