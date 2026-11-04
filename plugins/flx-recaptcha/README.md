# Flux reCAPTCHA

Protects login, registration, lost password, comments, WooCommerce account and checkout, Flux Donate donation form and
Flux Forms submissions from bots with Google reCAPTCHA v3 (invisible) or v2 (checkbox). Pick the forms to protect in the panel.

- Keys are tested before protection turns on, so a wrong key can never lock you out. The secret key is stored encrypted.
- Works with the Flux Speed custom login address (/fluxadmin).
- Google's script loads only when a visitor touches a protected form.
- Last 30 days passed / blocked per form, skip signed-in users, IP allow list, recaptcha.net option.
- Recovery: `define( 'FLXRC_DISABLE', true );` in wp-config.php.
- Shared API for other plugins: `flx_captcha_contexts`, `flx_captcha_field`, `flx_captcha_verify`, JS `flxCaptcha.token()`.
  See [docs/flx-recaptcha/API.md](../../docs/flx-recaptcha/API.md).

Türkçe: [README.tr.md](README.tr.md)
