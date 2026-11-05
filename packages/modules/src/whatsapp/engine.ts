// Engine lives in @fluxwp/runtime so the phone and the desktop share it.
// The browser adapter (document visibility, blob URLs) is the default, which is what this panel uses.
export { WaEngine, type ChatMsgs, type EngineOptions, type WaState } from '@fluxwp/runtime/src/modules/whatsapp/engine';
