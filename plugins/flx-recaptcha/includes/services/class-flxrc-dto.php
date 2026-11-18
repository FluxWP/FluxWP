<?php
/**
 * Typed values the reCAPTCHA services return (GraphQL and other packages read the same shapes).
 *
 * @package FLX_Recaptcha
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings of the reCAPTCHA page. The secret key is never part of it: only whether one is saved and its last four characters.
 */
final class FLXRC_Settings {

	/**
	 * @param string       $version             'v3' or 'v2'.
	 * @param string       $site_key            Site key in use (a wp-config.php constant wins over the panel value).
	 * @param bool         $site_key_locked     The site key comes from FLXRC_SITE_KEY.
	 * @param bool         $secret_saved        A secret key is saved (or set by FLXRC_SECRET_KEY).
	 * @param string       $secret_hint         Masked tail of the secret key ("••••ab12"); empty when none.
	 * @param bool         $secret_locked       The secret key comes from FLXRC_SECRET_KEY.
	 * @param float        $threshold           v3 score below which a submission is a bot (0.1–0.9).
	 * @param string       $domain              'google' or 'recaptcha' (www.recaptcha.net).
	 * @param string       $v2_theme            'auto', 'light' or 'dark'.
	 * @param string       $v2_size             'normal' or 'compact'.
	 * @param bool         $hide_badge          Hide the v3 badge.
	 * @param bool         $skip_logged_in      Signed-in users are not checked on comments, checkout, donations and forms.
	 * @param bool         $fail_open           Let submissions through when Google is unreachable.
	 * @param list<string> $allow_ips           IPs and CIDR ranges that skip the check.
	 * @param string       $current_ip          The caller's IP address ('' when unknown).
	 * @param bool         $keys_verified       The keys were tested for this version.
	 * @param bool         $disabled_by_const   Protection is off through FLXRC_DISABLE.
	 * @param bool         $forms_overlap       Flux Forms is installed and its form protection is on here (its own reCAPTCHA option would conflict).
	 */
	public function __construct(
		public readonly string $version,
		public readonly string $site_key,
		public readonly bool $site_key_locked,
		public readonly bool $secret_saved,
		public readonly string $secret_hint,
		public readonly bool $secret_locked,
		public readonly float $threshold,
		public readonly string $domain,
		public readonly string $v2_theme,
		public readonly string $v2_size,
		public readonly bool $hide_badge,
		public readonly bool $skip_logged_in,
		public readonly bool $fail_open,
		public readonly array $allow_ips,
		public readonly string $current_ip,
		public readonly bool $keys_verified,
		public readonly bool $disabled_by_const,
		public readonly bool $forms_overlap,
	) {}
}

/**
 * A form (context) the panel can protect.
 */
final class FLXRC_Form {

	/**
	 * @param string $key         Context key (login, comments, checkout …).
	 * @param string $label       Name.
	 * @param string $description What is protected.
	 * @param string $group       'wordpress', 'woocommerce', 'fluxwp' or 'other'.
	 * @param string $group_label Group name.
	 * @param bool   $available   The plugin that owns the form is installed.
	 * @param bool   $enabled     Protection is on for the form in the panel.
	 * @param int    $passed      Submissions that passed in the last 30 days.
	 * @param int    $blocked     Submissions blocked in the last 30 days.
	 */
	public function __construct(
		public readonly string $key,
		public readonly string $label,
		public readonly string $description,
		public readonly string $group,
		public readonly string $group_label,
		public readonly bool $available,
		public readonly bool $enabled,
		public readonly int $passed,
		public readonly int $blocked,
	) {}
}

/**
 * The result of the last key test.
 */
final class FLXRC_Test_Log {

	/**
	 * @param int    $time    Unix time.
	 * @param bool   $ok      The keys worked.
	 * @param string $message Result in the language of the person who ran it.
	 */
	public function __construct(
		public readonly int $time,
		public readonly bool $ok,
		public readonly string $message,
	) {}
}

/**
 * The last time Google could not be reached or rejected the secret key (shown for a day).
 */
final class FLXRC_Error_Log {

	/**
	 * @param int    $time      Unix time.
	 * @param string $kind      'config' (key rejected) or 'network' (unreachable).
	 * @param string $reason    Google's error codes or the connection error (short).
	 * @param bool   $let_pass  Submissions were let through at that time (fail open).
	 */
	public function __construct(
		public readonly int $time,
		public readonly string $kind,
		public readonly string $reason,
		public readonly bool $let_pass,
	) {}
}

/**
 * State of the protection and the last 30 days.
 */
final class FLXRC_Status {

	/**
	 * @param string              $state           'on', 'off' (recovery constant) or 'awaiting_test'.
	 * @param string              $version         'v3' or 'v2'.
	 * @param int                 $forms_on        Available forms with protection on.
	 * @param int                 $forms_available Forms of installed plugins.
	 * @param int                 $passed          Passed in the last 30 days.
	 * @param int                 $blocked         Blocked in the last 30 days.
	 * @param FLXRC_Test_Log|null $last_test       Last key test.
	 * @param FLXRC_Error_Log|null $last_error     Recent Google error (last day, protection on).
	 */
	public function __construct(
		public readonly string $state,
		public readonly string $version,
		public readonly int $forms_on,
		public readonly int $forms_available,
		public readonly int $passed,
		public readonly int $blocked,
		public readonly ?FLXRC_Test_Log $last_test,
		public readonly ?FLXRC_Error_Log $last_error,
	) {}
}

/**
 * Result of testing keys with Google.
 */
final class FLXRC_Test_Result {

	/**
	 * @param bool        $ok      The keys work (and were saved).
	 * @param string      $message Result.
	 * @param float|null  $score   v3 score of the test token.
	 */
	public function __construct(
		public readonly bool $ok,
		public readonly string $message,
		public readonly ?float $score,
	) {}
}

/**
 * What a client needs to protect one form (public: the site key is public by design).
 */
final class FLXRC_Public_Config {

	/**
	 * @param string      $context     Context key.
	 * @param bool        $enabled     The form is protected for visitors (protection on, form on, plugin installed).
	 * @param string      $version     'v3' or 'v2'.
	 * @param string|null $site_key    Site key; null when the form is not protected.
	 * @param string|null $action      v3 action name to execute; null when not protected.
	 * @param string|null $script_url  Google script address; null when not protected.
	 * @param string      $theme       v2 theme: 'auto', 'light' or 'dark'.
	 * @param string      $size        v2 size: 'normal' or 'compact'.
	 * @param bool        $hide_badge  The v3 badge is hidden (show Google's notice under the form).
	 * @param string      $token_field POST field name that carries the token.
	 * @param string      $token_header Request header name that carries the token.
	 */
	public function __construct(
		public readonly string $context,
		public readonly bool $enabled,
		public readonly string $version,
		public readonly ?string $site_key,
		public readonly ?string $action,
		public readonly ?string $script_url,
		public readonly string $theme,
		public readonly string $size,
		public readonly bool $hide_badge,
		public readonly string $token_field,
		public readonly string $token_header,
	) {}
}
