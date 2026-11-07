// Engine lives in @fluxwp/runtime. Group labels keep using this package's dictionary.
import { setTaskLabels } from '@fluxwp/runtime/src/modules/ports';
import { tt } from './i18n';

setTaskLabels(tt);

export { FlxgEngine, useEngineVersion, store, rank, norm, cmpStr, between, parseDay, dayKey, today, addDays } from '@fluxwp/runtime/src/modules/tasks/engine';
export type { FlxgState, Group, Route, Scope } from '@fluxwp/runtime/src/modules/tasks/engine';
