---
"@fluxwp/modules": patch
"@fluxwp/graphql": minor
---
Forms: Payments, Automations (list, flow editor, run log), System check and Add-ons tabs on the new `flxFormsPayments`, `flxFormsSystem`, `flxFormsAddons`, `flxFormsAutomation*` and `flxFormsStripeWebhookSecret` operations.

Forms 2.9.0: payment forms take payments only through Flux POS. `flxFormsPayments` now returns `pos { active installed configured currencies test url }` instead of the provider list, and `flxFormsStripeWebhookSecret` is removed.
