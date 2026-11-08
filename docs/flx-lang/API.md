# Flux Lang visitor language selection

Automatic selection is controlled by the existing `flxl_settings` fields: `suggest` (`off`, `banner`, `redirect`), `suggest_to` (an active fallback language, including the default language), and `home_countries` (country codes that retain the site's default language). An empty home-country list lets visitors in every country follow the supported browser language.

Explicit language URLs are preserved. On an unprefixed URL, a saved language preference wins; otherwise a supported browser language is used, including regional variants such as `pt-BR`, `fr-CA` and `zh-Hans-CN`. If none matches, the country hint may select an active language; otherwise `suggest_to` is used. The FluxWP site preset enables redirect mode with English fallback and an empty home-country list. Other sites keep their own configuration.

Page content stays cacheable by URL. The browser makes the redirect decision; bot/admin visits are excluded. The country endpoint returns only the visitor's country code from the existing CDN/server headers and does not call an external IP service or store IP addresses. Missing country information falls back to the configured language. Time zone is only a fallback for configured home countries.

## `flxl_country_languages`

Filters the country-code-to-language-code map. The suggested code is only used if that language has an available alternate on the current page. This is a fallback hint, not a claim that everybody in a country speaks the same language; supported browser preferences take precedence.

```php
add_filter( 'flxl_country_languages', function ( $map ) {
    $map['CA'] = 'fr';
    return $map;
} );
```

## Maintainers

Ahmet Yılmaz ([@sharkpaw](https://github.com/sharkpaw)).
