// PageSpeed switches, grouped as on the classic tab. The texts are the classic tab's English sources.
import type { Tr } from './ui';

export interface PsRow {
  key: string;
  title: string;
  risk: 'safe' | 'test';
  desc: string;
  /** Extra content under the row: 'css' options or the defer note. */
  extra?: 'css' | 'defer';
}
export interface PsSection {
  title: string;
  rows: PsRow[];
}

export const psSections = (t: Tr): PsSection[] => [
  {
    title: t('Basics'),
    rows: [
      {
        key: 'ps_viewport',
        title: t('Remove the zoom lock'),
        risk: 'safe',
        desc: t('user-scalable=no and maximum-scale are removed from the viewport tag so visitors with low vision can zoom in. Directly affects the Accessibility score.'),
      },
      { key: 'ps_emoji', title: t('Remove the emoji script'), risk: 'safe', desc: t("WordPress's emoji detection script and style are not loaded. Modern browsers render emoji themselves.") },
    ],
  },
  {
    title: 'CSS',
    rows: [
      {
        key: 'ps_css',
        title: t('Remove unused CSS and inline it'),
        risk: 'test',
        desc: t(
          "Each page's class/id inventory is collected (HTML + the page's scripts); rules with no match on that page are dropped from the CSS file and the rest is written into <head>. The render-blocking CSS request goes away and LCP/FCP improve. Pages with the same inventory share one copy; copies are rebuilt when the file changes, and the full file is used if there are too many different pages.",
        ),
        extra: 'css',
      },
      { key: 'ps_min_css', title: t('Minify CSS files'), risk: 'safe', desc: t('For unminified (non-.min) local CSS files, an up-to-date .min sibling or a copy without comments and whitespace is used.') },
    ],
  },
  {
    title: 'JavaScript',
    rows: [
      {
        key: 'ps_min_js',
        title: t('Minify JS files'),
        risk: 'test',
        desc: t(
          'For theme and plugin scripts, the .min sibling is used if it is newer than the source; otherwise a copy without comments and indentation is generated (line breaks are kept). Bundles that load chunks relative to their own location and scripts with translations are left alone.',
        ),
      },
      { key: 'ps_defer', title: t('Defer head scripts'), risk: 'test', desc: t('Scripts loaded in the page <head> that hold up rendering get defer (e.g. language suggestion, analytics scripts).'), extra: 'defer' },
      {
        key: 'ps_defer_jquery',
        title: t('Defer jQuery too'),
        risk: 'test',
        desc: t(
          'jQuery and jQuery Migrate get defer as well. WordPress keeps them blocking when a blocking script depends on them, and Flux Speed keeps them blocking on pages with an inline script that calls jQuery or $ directly.',
        ),
      },
    ],
  },
  {
    title: t('Fonts'),
    rows: [
      {
        key: 'ps_fonts',
        title: t('Remove unused fonts'),
        risk: 'safe',
        desc: t("Font families defined in theme.json but not referenced in the page's CSS (@font-face) are not printed. If usage cannot be determined, nothing is removed."),
      },
      {
        key: 'ps_preload',
        title: t('Preload the above-the-fold font'),
        risk: 'test',
        desc: t(
          'The regular (400) woff2 of up to two families in use is requested with <link rel="preload"> so text paints sooner. Preloading the wrong weight wastes a download; turn this off if PageSpeed reports an unused preload.',
        ),
      },
    ],
  },
];
