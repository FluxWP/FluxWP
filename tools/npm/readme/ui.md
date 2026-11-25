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
| `Chart` (`@fluxwp/ui/chart`) | Interactive charts on Chart.js 4: bar (stacked, horizontal), line, area, doughnut, pie, radar, polar, scatter, bubble, bar + line on two axes. Hover tooltips, legend toggles, `onPoint` clicks, `--flux-*` colours, live dark/light switch |
| `FluxForm` (`@fluxwp/ui/forms`) | Flux Forms forms in React for headless sites: draws the `flxForm` query result (every field type, steps, widths, totals), checks it in the browser and gives you the `flxFormSubmit` input; server errors land on their fields. EN + TR texts, light and dark |
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

```tsx
import { Chart } from '@fluxwp/ui/chart'; // own entry: Chart.js loads only with the screens that draw charts

<Chart
  kind="bar"
  stacked
  labels={days}
  series={[
    { label: 'One-time', data: oneTime },
    { label: 'Recurring', data: recurring, color: 'var(--flux-info)' },
  ]}
  format={(v) => money(v)}
/>
```

```tsx
'use client';
import { FluxForm } from '@fluxwp/ui/forms';
import '@fluxwp/ui/forms.css';

// form = data.flxForm from the FlxForm query (Flux Forms persisted query)
<FluxForm
  form={form}
  lang="tr"
  onSubmit={async (input) => (await gql(FlxFormSubmit, { input })).flxFormSubmit}
  onUpload={async (file) => uploadToFluxForms(file)} // POST /wp-json/Emsfb/v1/forms/file/upload → URL
/>
```

Each field type also has its own component (`TextField`, `ChoicesField`, `ImageChoiceField`, `RatingField`, …);
pass `components={{ choices: MyChoices }}` to replace one. Field types the GraphQL submit does not take
(signature, map, card payment) show a short "fill it on the website" note.

Colours, spacing and dark mode come from [`@fluxwp/tokens`](https://www.npmjs.com/package/@fluxwp/tokens)
CSS variables (`--flux-*`).
