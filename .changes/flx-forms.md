---
"@fluxwp/modules": patch
"@fluxwp/graphql": minor
---
Forms: Payments, Automations (list, flow editor, run log), System check and Add-ons tabs on the new `flxFormsPayments`, `flxFormsSystem`, `flxFormsAddons`, `flxFormsAutomation*` and `flxFormsStripeWebhookSecret` operations.

Forms 2.9.0: payment forms are paid on the WooCommerce checkout (no gateway of their own). `flxFormsPayments` now returns `checkout { active installed configured currency gateways url }` instead of the provider list, and `flxFormsStripeWebhookSecret` is removed.
