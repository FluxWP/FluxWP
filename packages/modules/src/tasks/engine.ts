// Engine lives in @fluxwp/runtime. Group labels keep using this package's dictionary.
import { setTaskLabels } from '@fluxwp/runtime/src/modules/ports';
import { tt } from './i18n';

setTaskLabels(tt);

export { FlxgEngine, useEngineVersion, store, rank, norm, cmpStr, between, parseDay, dayKey, today, addDays, PLAIN_PAGES } from '@fluxwp/runtime/src/modules/tasks/engine';
export type { FlxgDocRef, FlxgPreload, FlxgState, Group, Route, RoutePage, Scope } from '@fluxwp/runtime/src/modules/tasks/engine';
