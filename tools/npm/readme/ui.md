# @fluxwp/ui

[![npm](https://img.shields.io/npm/v/@fluxwp/ui)](https://www.npmjs.com/package/@fluxwp/ui)
[![license](https://img.shields.io/npm/l/@fluxwp/ui)](#license)

**The React UI kit of FluxWP screens.** Accessible, theme-aware components that look the same in
wp-admin, the FluxWP desktop app and the web, and match the PHP side (`flx_check()`, the date picker of
`flx-brand`), so classic and React screens of a plugin look alike.

| Component | |
|---|---|
| `Check` | Checkbox, radio and switch in one (`switch`, `radio`, `indeterminate`, `note`, `error`) |
| `DatePicker`, `DateCalendar` | Date picker with quick ranges and an Apply button, a sheet on phones; `formatDate`, `parseDate` |
| `Modal`, `useOverlay` | Dialogs and overlays |
| `Icon`, `MenuIcon` | Icons (Lucide) and the FluxWP plugin menu icons |
| `Avatar`, `Empty`, `Loading` | Small building blocks |
| `sanitizeHtml`, `openSanitizedLink` | Safe HTML from the server |

## Install

```sh
npm i @fluxwp/ui react
```

```ts
import '@fluxwp/ui/styles.css'; // not needed inside wp-admin with Flux GraphQL: the styles are on the page
```

## Usage

```tsx
import { Check, DatePicker, Modal } from '@fluxwp/ui';

<Check checked={on} onChange={setOn} switch>Send e-mail</Check>
<DatePicker value={date} onChange={setDate} />
```

Colours, spacing and dark mode come from [`@fluxwp/tokens`](https://www.npmjs.com/package/@fluxwp/tokens)
CSS variables (`--flux-*`).
