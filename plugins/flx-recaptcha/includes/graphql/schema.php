<?php
/**
 * Flux reCAPTCHA GraphQL schema (Flux GraphQL kit). Loaded on flx_gql_register, i.e. only inside a
 * GraphQL request. Resolvers call the services; the reCAPTCHA screen (wp-admin and FluxWP app) reads
 * and writes only through these fields.
 *
 * The secret key never leaves the server: reads return whether one is saved and its masked tail,
 * writes accept a new one (empty keeps the saved one). `flxCaptcha` is public and returns only what a
 * client needs to protect a form (the site key is public by design).
 *
 * @package FLX_Recaptcha
 */

defined( 'ABSPATH' ) || exit;

/**
 * Service result → value, or the WP_Error as a typed GraphQL error.
 *
 * @template T
 * @param T|WP_Error $r Result.
 * @return T
 * @throws FLXGQL_Error On WP_Error.
 */
function flxrc_gql_ok( $r ) {
	if ( $r instanceof WP_Error ) {
		throw flx_gql_from_wp_error( $r );
	}
	return $r;
}

/**
 * Typed parent value of a field.
 *
 * @template T of object
 * @param mixed           $source Parent.
 * @param class-string<T> $class  Expected class.
 * @return T
 * @throws FLXGQL_Error When the parent has another type.
 */
function flxrc_gql_src( $source, string $class ) {
	if ( ! $source instanceof $class ) {
		throw flx_gql_error( 'INTERNAL' );
	}
	return $source;
}

/** Marks the read for the cache: the public configuration changes with the settings. */
function flxrc_gql_tag(): void {
	flx_gql_cache_tag( FLXRC_Settings_Service::CACHE_TAG );
}

/* ------------------------------------------------------------------ */
/* Enums                                                               */
/* ------------------------------------------------------------------ */

flx_gql_enum(
	'FlxRecaptchaVersion',
	array(
		'description' => __( 'reCAPTCHA type.', 'flx-recaptcha' ),
		'values'      => array(
			'V3' => array( 'value' => 'v3', 'description' => __( 'Invisible; Google returns a score from 0 to 1.', 'flx-recaptcha' ) ),
			'V2' => array( 'value' => 'v2', 'description' => __( 'The "I\'m not a robot" checkbox.', 'flx-recaptcha' ) ),
		),
	)
);

flx_gql_enum(
	'FlxRecaptchaDomain',
	array(
		'description' => __( 'Google host the script and the verification use.', 'flx-recaptcha' ),
		'values'      => array(
			'GOOGLE'    => array( 'value' => 'google', 'description' => __( 'www.google.com', 'flx-recaptcha' ) ),
			'RECAPTCHA' => array( 'value' => 'recaptcha', 'description' => __( 'www.recaptcha.net, for networks where google.com is blocked.', 'flx-recaptcha' ) ),
		),
	)
);

flx_gql_enum(
	'FlxRecaptchaTheme',
	array(
		'description' => __( 'Look of the v2 checkbox.', 'flx-recaptcha' ),
		'values'      => array(
			'AUTO'  => array( 'value' => 'auto', 'description' => __( 'Follows the site\'s dark mode.', 'flx-recaptcha' ) ),
			'LIGHT' => array( 'value' => 'light', 'description' => __( 'Light.', 'flx-recaptcha' ) ),
			'DARK'  => array( 'value' => 'dark', 'description' => __( 'Dark.', 'flx-recaptcha' ) ),
		),
	)
);

flx_gql_enum(
	'FlxRecaptchaSize',
	array(
		'description' => __( 'Size of the v2 checkbox.', 'flx-recaptcha' ),
		'values'      => array(
			'NORMAL'  => array( 'value' => 'normal', 'description' => __( 'Normal.', 'flx-recaptcha' ) ),
			'COMPACT' => array( 'value' => 'compact', 'description' => __( 'Compact.', 'flx-recaptcha' ) ),
		),
	)
);

flx_gql_enum(
	'FlxRecaptchaFormGroup',
	array(
		'description' => __( 'Group a protectable form belongs to.', 'flx-recaptcha' ),
		'values'      => array(
			'WORDPRESS'   => array( 'value' => 'wordpress', 'description' => __( 'WordPress forms.', 'flx-recaptcha' ) ),
			'WOOCOMMERCE' => array( 'value' => 'woocommerce', 'description' => __( 'WooCommerce forms.', 'flx-recaptcha' ) ),
			'FLUXWP'      => array( 'value' => 'fluxwp', 'description' => __( 'FluxWP forms.', 'flx-recaptcha' ) ),
			'OTHER'       => array( 'value' => 'other', 'description' => __( 'Forms of other plugins.', 'flx-recaptcha' ) ),
		),
	)
);

flx_gql_enum(
	'FlxRecaptchaState',
	array(
		'description' => __( 'Whether protection is running.', 'flx-recaptcha' ),
		'values'      => array(
			'ON'            => array( 'value' => 'on', 'description' => __( 'Keys tested; the enabled forms are protected.', 'flx-recaptcha' ) ),
			'OFF'           => array( 'value' => 'off', 'description' => __( 'Turned off by the FLXRC_DISABLE recovery constant.', 'flx-recaptcha' ) ),
			'AWAITING_TEST' => array( 'value' => 'awaiting_test', 'description' => __( 'The keys are not tested yet; no form is checked.', 'flx-recaptcha' ) ),
		),
	)
);

flx_gql_enum(
	'FlxRecaptchaErrorKind',
	array(
		'description' => __( 'What went wrong when Google was asked.', 'flx-recaptcha' ),
		'values'      => array(
			'CONFIG'  => array( 'value' => 'config', 'description' => __( 'Google rejected the secret key.', 'flx-recaptcha' ) ),
			'NETWORK' => array( 'value' => 'network', 'description' => __( 'Google could not be reached.', 'flx-recaptcha' ) ),
		),
	)
);

/* ------------------------------------------------------------------ */
/* Types                                                               */
/* ------------------------------------------------------------------ */

flx_gql_type(
	'FlxRecaptchaSettings',
	array(
		'description' => __( 'Keys and options of the reCAPTCHA page. The secret key is never returned.', 'flx-recaptcha' ),
		'fields'      => array(
			'version'          => array(
				'type'        => 'FlxRecaptchaVersion!',
				'description' => __( 'reCAPTCHA type.', 'flx-recaptcha' ),
				'resolve'     => static fn( $s ): string => flxrc_gql_src( $s, FLXRC_Settings::class )->version,
			),
			'siteKey'          => array(
				'type'        => 'String!',
				'description' => __( 'Site key in use (public). Empty when none is set.', 'flx-recaptcha' ),
				'resolve'     => static fn( $s ): string => flxrc_gql_src( $s, FLXRC_Settings::class )->site_key,
			),
			'siteKeyLocked'    => array(
				'type'        => 'Boolean!',
				'description' => __( 'The site key comes from the FLXRC_SITE_KEY constant in wp-config.php; the panel value is not used.', 'flx-recaptcha' ),
				'resolve'     => static fn( $s ): bool => flxrc_gql_src( $s, FLXRC_Settings::class )->site_key_locked,
			),
			'secretSaved'      => array(
				'type'        => 'Boolean!',
				'description' => __( 'A secret key is saved.', 'flx-recaptcha' ),
				'resolve'     => static fn( $s ): bool => flxrc_gql_src( $s, FLXRC_Settings::class )->secret_saved,
			),
			'secretHint'       => array(
				'type'        => 'String!',
				'description' => __( 'Masked last four characters of the saved secret key; empty when none. The key itself is never returned.', 'flx-recaptcha' ),
				'resolve'     => static fn( $s ): string => flxrc_gql_src( $s, FLXRC_Settings::class )->secret_hint,
			),
			'secretLocked'     => array(
				'type'        => 'Boolean!',
				'description' => __( 'The secret key comes from the FLXRC_SECRET_KEY constant in wp-config.php; the panel value is not used.', 'flx-recaptcha' ),
				'resolve'     => static fn( $s ): bool => flxrc_gql_src( $s, FLXRC_Settings::class )->secret_locked,
			),
			'threshold'        => array(
				'type'        => 'Float!',
				'description' => __( 'v3 score below which a submission is blocked (0.1–0.9).', 'flx-recaptcha' ),
				'resolve'     => static fn( $s ): float => flxrc_gql_src( $s, FLXRC_Settings::class )->threshold,
			),
			'domain'           => array(
				'type'        => 'FlxRecaptchaDomain!',
				'description' => __( 'Google host.', 'flx-recaptcha' ),
				'resolve'     => static fn( $s ): string => flxrc_gql_src( $s, FLXRC_Settings::class )->domain,
			),
			'v2Theme'          => array(
				'type'        => 'FlxRecaptchaTheme!',
				'description' => __( 'v2 checkbox theme.', 'flx-recaptcha' ),
				'resolve'     => static fn( $s ): string => flxrc_gql_src( $s, FLXRC_Settings::class )->v2_theme,
			),
			'v2Size'           => array(
				'type'        => 'FlxRecaptchaSize!',
				'description' => __( 'v2 checkbox size.', 'flx-recaptcha' ),
				'resolve'     => static fn( $s ): string => flxrc_gql_src( $s, FLXRC_Settings::class )->v2_size,
			),
			'hideBadge'        => array(
				'type'        => 'Boolean!',
				'description' => __( 'The v3 badge is hidden; the notice Google requires is added under protected forms.', 'flx-recaptcha' ),
				'resolve'     => static fn( $s ): bool => flxrc_gql_src( $s, FLXRC_Settings::class )->hide_badge,
			),
			'skipLoggedIn'     => array(
				'type'        => 'Boolean!',
				'description' => __( 'Signed-in users are not checked on comments, checkout, donations and forms.', 'flx-recaptcha' ),
				'resolve'     => static fn( $s ): bool => flxrc_gql_src( $s, FLXRC_Settings::class )->skip_logged_in,
			),
			'failOpen'         => array(
				'type'        => 'Boolean!',
				'description' => __( 'Submissions are let through when Google is unreachable.', 'flx-recaptcha' ),
				'resolve'     => static fn( $s ): bool => flxrc_gql_src( $s, FLXRC_Settings::class )->fail_open,
			),
			'allowIps'         => array(
				'type'        => '[String!]!',
				'description' => __( 'IP addresses and ranges (CIDR) that skip the check.', 'flx-recaptcha' ),
				'resolve'     => static fn( $s ): array => flxrc_gql_src( $s, FLXRC_Settings::class )->allow_ips,
			),
			'currentIp'        => array(
				'type'        => 'String',
				'description' => __( 'The caller\'s IP address; empty when unknown.', 'flx-recaptcha' ),
				'resolve'     => static function ( $s ): ?string {
					$ip = flxrc_gql_src( $s, FLXRC_Settings::class )->current_ip;
					return '' === $ip ? null : $ip;
				},
			),
			'keysVerified'     => array(
				'type'        => 'Boolean!',
				'description' => __( 'The keys were tested for the current type; protection runs only then.', 'flx-recaptcha' ),
				'resolve'     => static fn( $s ): bool => flxrc_gql_src( $s, FLXRC_Settings::class )->keys_verified,
			),
			'disabledByConstant' => array(
				'type'        => 'Boolean!',
				'description' => __( 'Protection is turned off by the FLXRC_DISABLE recovery constant.', 'flx-recaptcha' ),
				'resolve'     => static fn( $s ): bool => flxrc_gql_src( $s, FLXRC_Settings::class )->disabled_by_const,
			),
			'formsOverlap'     => array(
				'type'        => 'Boolean!',
				'description' => __( 'Flux Forms is installed and its form protection is on here; its own reCAPTCHA option must be off.', 'flx-recaptcha' ),
				'resolve'     => static fn( $s ): bool => flxrc_gql_src( $s, FLXRC_Settings::class )->forms_overlap,
			),
		),
	)
);

flx_gql_type(
	'FlxRecaptchaForm',
	array(
		'description' => __( 'A form the panel can protect.', 'flx-recaptcha' ),
		'fields'      => array(
			'key'         => array(
				'type'        => 'String!',
				'description' => __( 'Form (context) key, e.g. login or checkout.', 'flx-recaptcha' ),
				'resolve'     => static fn( $f ): string => flxrc_gql_src( $f, FLXRC_Form::class )->key,
			),
			'label'       => array(
				'type'        => 'String!',
				'description' => __( 'Form name in the user\'s language.', 'flx-recaptcha' ),
				'resolve'     => static fn( $f ): string => flxrc_gql_src( $f, FLXRC_Form::class )->label,
			),
			'description' => array(
				'type'        => 'String!',
				'description' => __( 'What is protected.', 'flx-recaptcha' ),
				'resolve'     => static fn( $f ): string => flxrc_gql_src( $f, FLXRC_Form::class )->description,
			),
			'group'       => array(
				'type'        => 'FlxRecaptchaFormGroup!',
				'description' => __( 'Group of the form.', 'flx-recaptcha' ),
				'resolve'     => static fn( $f ): string => flxrc_gql_src( $f, FLXRC_Form::class )->group,
			),
			'groupLabel'  => array(
				'type'        => 'String!',
				'description' => __( 'Group name in the user\'s language.', 'flx-recaptcha' ),
				'resolve'     => static fn( $f ): string => flxrc_gql_src( $f, FLXRC_Form::class )->group_label,
			),
			'available'   => array(
				'type'        => 'Boolean!',
				'description' => __( 'The plugin that owns the form is installed.', 'flx-recaptcha' ),
				'resolve'     => static fn( $f ): bool => flxrc_gql_src( $f, FLXRC_Form::class )->available,
			),
			'enabled'     => array(
				'type'        => 'Boolean!',
				'description' => __( 'Protection is on for the form in the panel.', 'flx-recaptcha' ),
				'resolve'     => static fn( $f ): bool => flxrc_gql_src( $f, FLXRC_Form::class )->enabled,
			),
			'passed'      => array(
				'type'        => 'Int!',
				'description' => __( 'Submissions that passed in the last 30 days.', 'flx-recaptcha' ),
				'resolve'     => static fn( $f ): int => flxrc_gql_src( $f, FLXRC_Form::class )->passed,
			),
			'blocked'     => array(
				'type'        => 'Int!',
				'description' => __( 'Submissions blocked in the last 30 days.', 'flx-recaptcha' ),
				'resolve'     => static fn( $f ): int => flxrc_gql_src( $f, FLXRC_Form::class )->blocked,
			),
		),
	)
);

flx_gql_type(
	'FlxRecaptchaTestLog',
	array(
		'description' => __( 'The last key test.', 'flx-recaptcha' ),
		'fields'      => array(
			'time'    => array(
				'type'        => 'FlxDateTime!',
				'description' => __( 'When the test ran.', 'flx-recaptcha' ),
				'resolve'     => static fn( $l ): ?array => flx_gql_datetime( flxrc_gql_src( $l, FLXRC_Test_Log::class )->time ),
			),
			'ok'      => array(
				'type'        => 'Boolean!',
				'description' => __( 'The keys worked.', 'flx-recaptcha' ),
				'resolve'     => static fn( $l ): bool => flxrc_gql_src( $l, FLXRC_Test_Log::class )->ok,
			),
			'message' => array(
				'type'        => 'String!',
				'description' => __( 'Result of the test.', 'flx-recaptcha' ),
				'resolve'     => static fn( $l ): string => flxrc_gql_src( $l, FLXRC_Test_Log::class )->message,
			),
		),
	)
);

flx_gql_type(
	'FlxRecaptchaErrorLog',
	array(
		'description' => __( 'The last time Google could not be reached or rejected the secret key (kept for a day).', 'flx-recaptcha' ),
		'fields'      => array(
			'time'    => array(
				'type'        => 'FlxDateTime!',
				'description' => __( 'When it happened.', 'flx-recaptcha' ),
				'resolve'     => static fn( $l ): ?array => flx_gql_datetime( flxrc_gql_src( $l, FLXRC_Error_Log::class )->time ),
			),
			'kind'    => array(
				'type'        => 'FlxRecaptchaErrorKind!',
				'description' => __( 'What went wrong.', 'flx-recaptcha' ),
				'resolve'     => static fn( $l ): string => flxrc_gql_src( $l, FLXRC_Error_Log::class )->kind,
			),
			'reason'  => array(
				'type'        => 'String!',
				'description' => __( 'Google\'s error codes or the connection error (short).', 'flx-recaptcha' ),
				'resolve'     => static fn( $l ): string => flxrc_gql_src( $l, FLXRC_Error_Log::class )->reason,
			),
			'letPass' => array(
				'type'        => 'Boolean!',
				'description' => __( 'Submissions were let through without a check at that time (otherwise they were blocked).', 'flx-recaptcha' ),
				'resolve'     => static fn( $l ): bool => flxrc_gql_src( $l, FLXRC_Error_Log::class )->let_pass,
			),
		),
	)
);

flx_gql_type(
	'FlxRecaptchaStatus',
	array(
		'description' => __( 'State of the protection and the last 30 days.', 'flx-recaptcha' ),
		'fields'      => array(
			'state'          => array(
				'type'        => 'FlxRecaptchaState!',
				'description' => __( 'Whether protection is running.', 'flx-recaptcha' ),
				'resolve'     => static fn( $s ): string => flxrc_gql_src( $s, FLXRC_Status::class )->state,
			),
			'version'        => array(
				'type'        => 'FlxRecaptchaVersion!',
				'description' => __( 'reCAPTCHA type in use.', 'flx-recaptcha' ),
				'resolve'     => static fn( $s ): string => flxrc_gql_src( $s, FLXRC_Status::class )->version,
			),
			'formsOn'        => array(
				'type'        => 'Int!',
				'description' => __( 'Available forms with protection on.', 'flx-recaptcha' ),
				'resolve'     => static fn( $s ): int => flxrc_gql_src( $s, FLXRC_Status::class )->forms_on,
			),
			'formsAvailable' => array(
				'type'        => 'Int!',
				'description' => __( 'Forms of installed plugins.', 'flx-recaptcha' ),
				'resolve'     => static fn( $s ): int => flxrc_gql_src( $s, FLXRC_Status::class )->forms_available,
			),
			'passed'         => array(
				'type'        => 'Int!',
				'description' => __( 'Submissions that passed in the last 30 days.', 'flx-recaptcha' ),
				'resolve'     => static fn( $s ): int => flxrc_gql_src( $s, FLXRC_Status::class )->passed,
			),
			'blocked'        => array(
				'type'        => 'Int!',
				'description' => __( 'Submissions blocked in the last 30 days.', 'flx-recaptcha' ),
				'resolve'     => static fn( $s ): int => flxrc_gql_src( $s, FLXRC_Status::class )->blocked,
			),
			'lastTest'       => array(
				'type'        => 'FlxRecaptchaTestLog',
				'description' => __( 'The last key test; empty when none ran yet.', 'flx-recaptcha' ),
				'resolve'     => static fn( $s ): ?FLXRC_Test_Log => flxrc_gql_src( $s, FLXRC_Status::class )->last_test,
			),
			'lastError'      => array(
				'type'        => 'FlxRecaptchaErrorLog',
				'description' => __( 'A Google error of the last day while protection is on; empty otherwise.', 'flx-recaptcha' ),
				'resolve'     => static fn( $s ): ?FLXRC_Error_Log => flxrc_gql_src( $s, FLXRC_Status::class )->last_error,
			),
		),
	)
);

flx_gql_type(
	'FlxRecaptchaTestResult',
	array(
		'description' => __( 'Result of testing keys with Google.', 'flx-recaptcha' ),
		'fields'      => array(
			'ok'      => array(
				'type'        => 'Boolean!',
				'description' => __( 'The keys work and were saved.', 'flx-recaptcha' ),
				'resolve'     => static fn( $r ): bool => flxrc_gql_src( $r, FLXRC_Test_Result::class )->ok,
			),
			'message' => array(
				'type'        => 'String!',
				'description' => __( 'What happened, in the user\'s language.', 'flx-recaptcha' ),
				'resolve'     => static fn( $r ): string => flxrc_gql_src( $r, FLXRC_Test_Result::class )->message,
			),
			'score'   => array(
				'type'        => 'Float',
				'description' => __( 'v3 score of the test token; empty for v2 or a failed test.', 'flx-recaptcha' ),
				'resolve'     => static fn( $r ): ?float => flxrc_gql_src( $r, FLXRC_Test_Result::class )->score,
			),
		),
	)
);

flx_gql_type(
	'FlxCaptchaConfig',
	array(
		'description' => __( 'What a client needs to protect one form with reCAPTCHA. Send the token with the mutation (its captchaToken input) or in the X-Flx-Captcha header.', 'flx-recaptcha' ),
		'fields'      => array(
			'context'     => array(
				'type'        => 'String!',
				'description' => __( 'Form (context) key.', 'flx-recaptcha' ),
				'resolve'     => static fn( $c ): string => flxrc_gql_src( $c, FLXRC_Public_Config::class )->context,
			),
			'enabled'     => array(
				'type'        => 'Boolean!',
				'description' => __( 'The form is protected for visitors. Signed-in users and allowed IP addresses may still be skipped by the server.', 'flx-recaptcha' ),
				'resolve'     => static fn( $c ): bool => flxrc_gql_src( $c, FLXRC_Public_Config::class )->enabled,
			),
			'version'     => array(
				'type'        => 'FlxRecaptchaVersion!',
				'description' => __( 'reCAPTCHA type.', 'flx-recaptcha' ),
				'resolve'     => static fn( $c ): string => flxrc_gql_src( $c, FLXRC_Public_Config::class )->version,
			),
			'siteKey'     => array(
				'type'        => 'String',
				'description' => __( 'Site key (public); empty when the form is not protected.', 'flx-recaptcha' ),
				'resolve'     => static fn( $c ): ?string => flxrc_gql_src( $c, FLXRC_Public_Config::class )->site_key,
			),
			'action'      => array(
				'type'        => 'String',
				'description' => __( 'v3 action name to execute; empty when the form is not protected.', 'flx-recaptcha' ),
				'resolve'     => static fn( $c ): ?string => flxrc_gql_src( $c, FLXRC_Public_Config::class )->action,
			),
			'scriptUrl'   => array(
				'type'        => 'String',
				'description' => __( 'Google script address to load; empty when the form is not protected.', 'flx-recaptcha' ),
				'resolve'     => static fn( $c ): ?string => flxrc_gql_src( $c, FLXRC_Public_Config::class )->script_url,
			),
			'theme'       => array(
				'type'        => 'FlxRecaptchaTheme!',
				'description' => __( 'v2 checkbox theme.', 'flx-recaptcha' ),
				'resolve'     => static fn( $c ): string => flxrc_gql_src( $c, FLXRC_Public_Config::class )->theme,
			),
			'size'        => array(
				'type'        => 'FlxRecaptchaSize!',
				'description' => __( 'v2 checkbox size.', 'flx-recaptcha' ),
				'resolve'     => static fn( $c ): string => flxrc_gql_src( $c, FLXRC_Public_Config::class )->size,
			),
			'hideBadge'   => array(
				'type'        => 'Boolean!',
				'description' => __( 'The v3 badge is hidden; show Google\'s notice under the form.', 'flx-recaptcha' ),
				'resolve'     => static fn( $c ): bool => flxrc_gql_src( $c, FLXRC_Public_Config::class )->hide_badge,
			),
			'tokenField'  => array(
				'type'        => 'String!',
				'description' => __( 'POST field name that carries the token (forms that post).', 'flx-recaptcha' ),
				'resolve'     => static fn( $c ): string => flxrc_gql_src( $c, FLXRC_Public_Config::class )->token_field,
			),
			'tokenHeader' => array(
				'type'        => 'String!',
				'description' => __( 'Request header name that carries the token (REST and GraphQL).', 'flx-recaptcha' ),
				'resolve'     => static fn( $c ): string => flxrc_gql_src( $c, FLXRC_Public_Config::class )->token_header,
			),
		),
	)
);

/* ------------------------------------------------------------------ */
/* Queries                                                             */
/* ------------------------------------------------------------------ */

flx_gql_query(
	'flxRecaptchaSettings',
	array(
		'type'        => 'FlxRecaptchaSettings!',
		'description' => __( 'Keys and options of the reCAPTCHA page. The secret key is never returned.', 'flx-recaptcha' ),
		'cap'         => 'manage_options',
		'resolve'     => static function (): FLXRC_Settings {
			flxrc_gql_tag();
			return FLXRC_Settings_Service::settings();
		},
	)
);

flx_gql_query(
	'flxRecaptchaForms',
	array(
		'type'        => '[FlxRecaptchaForm!]!',
		'description' => __( 'Every form the panel can protect, with its switch and the last 30 days.', 'flx-recaptcha' ),
		'cap'         => 'manage_options',
		'resolve'     => static function (): array {
			flxrc_gql_tag();
			return FLXRC_Settings_Service::forms();
		},
	)
);

flx_gql_query(
	'flxRecaptchaStatus',
	array(
		'type'        => 'FlxRecaptchaStatus!',
		'description' => __( 'State of the protection, the last 30 days, the last key test and the last Google error.', 'flx-recaptcha' ),
		'cap'         => 'manage_options',
		'resolve'     => static function (): FLXRC_Status {
			flxrc_gql_tag();
			return FLXRC_Settings_Service::status();
		},
	)
);

/*
 * Public by design: it holds the site key, which every protected page prints. It does not depend on the
 * IP allow list or on who is signed in, so a visitor's response can be cached (tag flx-recaptcha:config).
 */
flx_gql_query(
	'flxCaptcha',
	array(
		'type'        => 'FlxCaptchaConfig',
		'description' => __( 'What a client needs to protect one form with reCAPTCHA; empty when the form key is not registered.', 'flx-recaptcha' ),
		'cap'         => 'public',
		'args'        => array(
			'context' => array(
				'type'        => 'String!',
				'description' => __( 'Form (context) key, e.g. login, checkout or one a plugin registered with flx_captcha_contexts.', 'flx-recaptcha' ),
			),
		),
		'resolve'     => static function ( $root, array $args ): ?FLXRC_Public_Config {
			flxrc_gql_tag();
			return FLXRC_Captcha_Service::config( flx_gql_str( $args['context'] ?? '' ) );
		},
	)
);

/* ------------------------------------------------------------------ */
/* Mutations                                                           */
/* ------------------------------------------------------------------ */

flx_gql_input(
	'FlxRecaptchaFormSwitchInput',
	array(
		'description' => __( 'Turns protection on or off for one form.', 'flx-recaptcha' ),
		'fields'      => array(
			'key'     => array(
				'type'        => 'String!',
				'description' => __( 'Form (context) key.', 'flx-recaptcha' ),
			),
			'enabled' => array(
				'type'        => 'Boolean!',
				'description' => __( 'Protection on.', 'flx-recaptcha' ),
			),
		),
	)
);

/**
 * Typed update of flxRecaptchaSettingsUpdate.
 *
 * @param array<string,mixed> $input Mutation input.
 * @return array{version?: string, site_key?: string, secret?: string, threshold?: float, domain?: string, v2_theme?: string, v2_size?: string, hide_badge?: bool, skip_logged_in?: bool, fail_open?: bool, allow_ips?: list<string>, forms?: list<array{key: string, enabled: bool}>}
 */
function flxrc_gql_update_input( array $input ): array {
	$out = array();
	if ( isset( $input['version'] ) ) {
		$out['version'] = flx_gql_str( $input['version'] );
	}
	if ( isset( $input['siteKey'] ) ) {
		$out['site_key'] = flx_gql_str( $input['siteKey'] );
	}
	if ( isset( $input['secret'] ) ) {
		$out['secret'] = flx_gql_str( $input['secret'] );
	}
	if ( isset( $input['threshold'] ) && is_numeric( $input['threshold'] ) ) {
		$out['threshold'] = (float) $input['threshold'];
	}
	foreach ( array( 'domain' => 'domain', 'v2Theme' => 'v2_theme', 'v2Size' => 'v2_size' ) as $gql => $key ) {
		if ( isset( $input[ $gql ] ) ) {
			$out[ $key ] = flx_gql_str( $input[ $gql ] );
		}
	}
	foreach ( array( 'hideBadge' => 'hide_badge', 'skipLoggedIn' => 'skip_logged_in', 'failOpen' => 'fail_open' ) as $gql => $key ) {
		if ( isset( $input[ $gql ] ) ) {
			$out[ $key ] = (bool) $input[ $gql ];
		}
	}
	if ( isset( $input['allowIps'] ) && is_array( $input['allowIps'] ) ) {
		$out['allow_ips'] = array_values( array_map( 'flx_gql_str', $input['allowIps'] ) );
	}
	if ( isset( $input['forms'] ) && is_array( $input['forms'] ) ) {
		$forms = array();
		foreach ( $input['forms'] as $f ) {
			$f       = flx_gql_arr( $f );
			$forms[] = array( 'key' => flx_gql_str( $f['key'] ?? '' ), 'enabled' => (bool) ( $f['enabled'] ?? false ) );
		}
		$out['forms'] = $forms;
	}
	return $out;
}

/** The settings, forms and status after a write (the screen refreshes from the payload). */
$flxrc_payload_fields = array(
	'settings' => array(
		'type'        => 'FlxRecaptchaSettings!',
		'description' => __( 'Settings after the change.', 'flx-recaptcha' ),
		'resolve'     => static fn(): FLXRC_Settings => FLXRC_Settings_Service::settings(),
	),
	'forms'    => array(
		'type'        => '[FlxRecaptchaForm!]!',
		'description' => __( 'Forms after the change.', 'flx-recaptcha' ),
		'resolve'     => static fn(): array => FLXRC_Settings_Service::forms(),
	),
	'status'   => array(
		'type'        => 'FlxRecaptchaStatus!',
		'description' => __( 'Protection state after the change.', 'flx-recaptcha' ),
		'resolve'     => static fn(): FLXRC_Status => FLXRC_Settings_Service::status(),
	),
);

flx_gql_mutation(
	'flxRecaptchaSettingsUpdate',
	array(
		'description' => __( 'Changes the keys, the options and the form switches in one go. Fields you leave out stay as they are; nothing is written when any part is invalid. A changed key or type turns protection off until the keys are tested again.', 'flx-recaptcha' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'version'      => array(
				'type'        => 'FlxRecaptchaVersion',
				'description' => __( 'reCAPTCHA type.', 'flx-recaptcha' ),
			),
			'siteKey'      => array(
				'type'        => 'String',
				'description' => __( 'Site key. Ignored when FLXRC_SITE_KEY is set.', 'flx-recaptcha' ),
			),
			'secret'       => array(
				'type'        => 'String',
				'description' => __( 'Secret key; write-only, stored sealed. Empty keeps the saved one.', 'flx-recaptcha' ),
			),
			'threshold'    => array(
				'type'        => 'Float',
				'description' => __( 'v3 bot threshold (0.1–0.9).', 'flx-recaptcha' ),
			),
			'domain'       => array(
				'type'        => 'FlxRecaptchaDomain',
				'description' => __( 'Google host.', 'flx-recaptcha' ),
			),
			'v2Theme'      => array(
				'type'        => 'FlxRecaptchaTheme',
				'description' => __( 'v2 checkbox theme.', 'flx-recaptcha' ),
			),
			'v2Size'       => array(
				'type'        => 'FlxRecaptchaSize',
				'description' => __( 'v2 checkbox size.', 'flx-recaptcha' ),
			),
			'hideBadge'    => array(
				'type'        => 'Boolean',
				'description' => __( 'Hide the v3 badge.', 'flx-recaptcha' ),
			),
			'skipLoggedIn' => array(
				'type'        => 'Boolean',
				'description' => __( 'Do not check signed-in users on comments, checkout, donations and forms.', 'flx-recaptcha' ),
			),
			'failOpen'     => array(
				'type'        => 'Boolean',
				'description' => __( 'Let submissions through when Google is unreachable.', 'flx-recaptcha' ),
			),
			'allowIps'     => array(
				'type'        => '[String!]',
				'description' => __( 'IP addresses and ranges (CIDR) that skip the check; replaces the list.', 'flx-recaptcha' ),
			),
			'forms'        => array(
				'type'        => '[FlxRecaptchaFormSwitchInput!]',
				'description' => __( 'Form switches to change; the other forms stay as they are.', 'flx-recaptcha' ),
			),
		),
		'output'      => $flxrc_payload_fields,
		'resolve'     => static function ( array $input ) {
			/** @var array<string,mixed> $input */
			$r = FLXRC_Settings_Service::update( flxrc_gql_update_input( $input ) );
			return $r instanceof WP_Error ? $r : array();
		},
	)
);

flx_gql_mutation(
	'flxRecaptchaKeysTest',
	array(
		'description' => __( 'Tests the keys with the token the browser got from Google. When the test passes the keys are saved and protection turns on; when it fails nothing changes and the result says why.', 'flx-recaptcha' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'version' => array(
				'type'        => 'FlxRecaptchaVersion!',
				'description' => __( 'reCAPTCHA type of the keys.', 'flx-recaptcha' ),
			),
			'siteKey' => array(
				'type'        => 'String',
				'description' => __( 'Site key the token was made with. Ignored when FLXRC_SITE_KEY is set.', 'flx-recaptcha' ),
			),
			'secret'  => array(
				'type'        => 'String',
				'description' => __( 'Secret key to test; empty uses the saved one.', 'flx-recaptcha' ),
			),
			'token'   => array(
				'type'        => 'String!',
				'description' => __( 'Token Google returned in the browser (grecaptcha.execute or the v2 checkbox).', 'flx-recaptcha' ),
			),
		),
		'output'      => array_merge(
			array(
				'result' => array(
					'type'        => 'FlxRecaptchaTestResult',
					'description' => __( 'Result of the test.', 'flx-recaptcha' ),
				),
			),
			$flxrc_payload_fields
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXRC_Settings_Service::test_keys(
				'v2' === flx_gql_str( $input['version'] ?? '' ) ? 'v2' : 'v3',
				flx_gql_str( $input['siteKey'] ?? '' ),
				flx_gql_str( $input['secret'] ?? '' ),
				flx_gql_str( $input['token'] ?? '' )
			);
			return $r instanceof WP_Error ? $r : array( 'result' => $r );
		},
	)
);

unset( $flxrc_payload_fields );
