import { mountApp } from '@fluxwp/panel';
import { desktopPlatform } from './platform';

// macOS: başlık çubuğu pencereye gömülü (trafik ışıkları için sol boşluk). Windows: yerel başlık çubuğu, boşluk yok.
document.documentElement.classList.add(/Mac/i.test(navigator.userAgent) ? 'os-macos' : 'os-windows');

mountApp(document.getElementById('root')!, desktopPlatform);
