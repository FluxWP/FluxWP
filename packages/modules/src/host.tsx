// Modül ekranlarının çalıştığı ortam. FluxWP paneli ve WP admin gömmesi (apps/wp-embed) ayrı ayrı sağlar;
// ekran kodu ikisinde de aynıdır.
import { createContext, useContext, useMemo, type ReactNode } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import type { ApiClient } from '@fluxwp/core';

export interface ModuleHost {
  /** 'app': FluxWP (belirteç), 'wp': WordPress admin sayfası (çerez + nonce). */
  mode: 'app' | 'wp';
  client: ApiClient;
  /** Sitenin kök adresi (bağlantı kopyalama, yönetim sayfası). */
  siteUrl?: string;
  /** Site adı (yan menü başlığı). */
  siteName?: string;
  /** Query anahtarı öneki (hesap başına ayrı önbellek). */
  scope: string;
  /** Rozet sayılarını yenile (uygulamada menü rozetleri). */
  refreshCounts?: () => void;
  /** Panel/dış adres aç. */
  openUrl: (url: string) => void;
  /** Medyayı istemciyle blob olarak çek (uygulamada hep; WP'de test ortamı için). */
  blobMedia?: boolean;
  /** Masaüstü bildirimi (uygulama yerel bildirim kullanabilir); yoksa tarayıcı Notification. */
  notify?: (n: { title: string; body: string; icon?: string; onClick?: () => void }) => void;
  /** Sunucu yeni REST nonce'u verdi (WP oturumu yenilendi). */
  setNonce?: (nonce: string) => void;
}

const Ctx = createContext<ModuleHost | null>(null);

export function ModuleHostProvider({ value, children }: { value: ModuleHost; children: ReactNode }) {
  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}

export function useHost(): ModuleHost {
  const h = useContext(Ctx);
  if (!h) throw new Error('ModuleHostProvider missing');
  return h;
}

/** Modül API'si + query anahtarı yardımcıları. */
export function useModuleApi<T>(factory: (api: ApiClient) => T) {
  const host = useHost();
  const api = useMemo(() => factory(host.client), [host.client]); // eslint-disable-line react-hooks/exhaustive-deps
  const qc = useQueryClient();
  const key = (...parts: unknown[]) => [host.scope, ...parts];
  const refreshCounts = () => {
    host.refreshCounts?.();
    return qc.invalidateQueries({ queryKey: [host.scope, 'counts'] });
  };
  return { api, key, qc, refreshCounts, host };
}
