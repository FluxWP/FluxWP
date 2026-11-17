// Sol alttaki güncelleme kartı: yeni sürüm bulununca "İndir" sorar (kendiliğinden indirmez),
// indirirken ilerleme, hazır olunca "Yeniden başlat".
import { useTranslation } from 'react-i18next';
import { Download, Loader2, RefreshCw } from 'lucide-react';
import { useUpdate } from '../state/update';

export function UpdateBanner() {
  const { t } = useTranslation();
  const { status, update, progress, download, install } = useUpdate();
  if (!update || !(status === 'ready' || status === 'available' || status === 'downloading' || status === 'installing')) return null;
  const busy = status === 'downloading' || status === 'installing';
  const action =
    status === 'ready' ? 'update.restart'
    : status === 'installing' ? 'update.installing'
    : status === 'downloading' ? 'update.downloadingShort'
    : 'update.download';
  return (
    <button className="update-banner" onClick={() => void (status === 'ready' ? install() : download())} disabled={busy} title={update.notes ? update.notes.replace(/<[^>]+>/g, ' ') : undefined}>
      <span className="update-ic">{busy ? <Loader2 size={16} className="spin" /> : status === 'ready' ? <RefreshCw size={16} /> : <Download size={16} />}</span>
      <span className="account-text">
        <strong>{t('update.newVersion', { version: update.version })}</strong>
        <span>{t(action, { pct: progress > 0 ? Math.round(progress * 100) + '%' : '' })}</span>
      </span>
      {progress > 0 && progress < 1 && <span className="update-bar" style={{ width: `${progress * 100}%` }} />}
    </button>
  );
}
