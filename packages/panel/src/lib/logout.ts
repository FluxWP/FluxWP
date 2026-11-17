// Bir hesaptan çıkış: sunucu oturumunu kapat, token'ı iptal et, hesabı kaldır.
import { accountsStore, clientFor, dropClient, loadTokens, revokeToken, flxapp, type Account } from '@fluxwp/core';
import { dropAccountCache } from '@fluxwp/runtime';
import { accountCacheStore } from '../boot/cacheStore';

export async function logoutAccount(a: Account): Promise<void> {
  try {
    if (a.authKind === 'oauth') {
      await flxapp(clientFor(a)).logout().catch(() => undefined);
      const tk = await loadTokens(a.id);
      if (tk?.refresh) await revokeToken(a.siteUrl, tk.refresh);
    }
    await clientFor(a)('flxapp/v1/session/end', { method: 'POST' }).catch(() => undefined);
  } finally {
    dropClient(a.id);
    dropAccountCache(a.id);
    void accountCacheStore(a.id).removeItem('query-cache');
    await accountsStore.getState().remove(a.id);
  }
}
