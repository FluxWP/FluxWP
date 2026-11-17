# @fluxwp/tokens

[![npm](https://img.shields.io/npm/v/@fluxwp/tokens)](https://www.npmjs.com/package/@fluxwp/tokens)
[![license](https://img.shields.io/npm/l/@fluxwp/tokens)](#license)

**Design tokens of FluxWP**: colour palette (light and dark), brand colours, spacing, radius and type, as
TypeScript values, CSS variables and React Native themes. Zero dependencies.

## Install

```sh
npm i @fluxwp/tokens
```

## Usage

```ts
import { toCssVars, themeColors, space, radius } from '@fluxwp/tokens';

const brand = { primary: '#2f3437', primaryContrast: '#ffffff', accent: '#c8a96a' };

// CSS variables: --flux-primary, --flux-bg, --flux-space-4 …
Object.assign(document.documentElement.style, toCssVars('dark', brand));

const c = themeColors('light', brand); // typed colour map
const gap = space[4];                   // 16
```

`nativeTheme(mode, brand)` returns the same values as one object (`colors`, `space`, `radius`, `font`) for
React Native.
