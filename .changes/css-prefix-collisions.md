---
"@fluxwp/modules": patch
---
Speed and App settings screens use their own CSS class prefixes (`flxpf-`, `flxas-`) instead of the `flxs-` prefix shared with SEO; SEO's colour helper classes are scoped to the SEO screen. Fixes misaligned Performance tables when several screens' styles are loaded together (FluxApp).
