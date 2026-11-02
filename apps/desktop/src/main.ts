import { mountApp } from '@fluxwp/panel';
import { desktopPlatform } from './platform';

mountApp(document.getElementById('root')!, desktopPlatform);
