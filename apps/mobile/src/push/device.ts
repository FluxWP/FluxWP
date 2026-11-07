// Stable install id (this device) shared by push registration. One id per install, every account.
import { storage } from '@/platform/storage';

export async function installId(): Promise<string> {
  const cur = await storage.get('install-id');
  if (cur) return cur;
  const id = globalThis.crypto.randomUUID();
  await storage.set('install-id', id);
  return id;
}
