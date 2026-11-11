# Changelog

## 0.3.3
- A chunk build mounts once from `window.FluxModulesOptions` whether the entry runs before or after DOMContentLoaded. `window.FluxModules.mount` remains the single-file entry point.

## 0.3.2
- The default build stays one IIFE file. `FLUX_EMBED_CHUNKS=1` emits ES chunks named `flux-<module>-<hash>.js` beside the script.

## 0.3.1
- Support all ten shared locales and isolate Arabic embed direction.
