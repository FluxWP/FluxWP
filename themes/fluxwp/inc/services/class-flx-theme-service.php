<?php
/**
 * Theme service: the one place that reads and changes Theme Settings, payment logos, the System Check
 * and the "migrate from previous theme" action for GraphQL (and the Theme Settings / System Check screens).
 * The classic pages keep their own handlers; both write the same options through the same sanitizers.
 * Permission checks stay with the caller (manage_options; switch_themes for the migration).
 *
 * Errors are WP_Error with `graphql_code` INVALID_INPUT / NOT_FOUND and the input `field`.
 *
 * @package fluxwp
 */

defined( 'ABSPATH' ) || exit;

final class FLX_Theme_Service {

	public const CACHE_TAG = 'flx-theme:settings';

	/** Most donation types / logos a list returns. */
	public const LIST_MAX = 100;

	/**
	 * Service key => [ stored key, kind ]. Kinds: str, text (multi-line), html, int, bool (1/0), onoff, enum.
	 *
	 * @var array<string,array{0:string,1:string}>
	 */
	private const FIELDS = array(
		'coming_soon_title' => array( 'coming_soon_title', 'str' ),
		'coming_soon_text'  => array( 'coming_soon_text', 'text' ),
		'store_title'       => array( 'coming_soon_store_title', 'str' ),
		'store_text'        => array( 'coming_soon_store_text', 'text' ),
		'contact_email'     => array( 'coming_soon_email', 'str' ),
		'site_type'         => array( 'site_mod', 'enum' ),
		'pattern'           => array( 'decoration_pattern', 'enum' ),
		'table_head'        => array( 'table_head', 'str' ),
		'news_intro'        => array( 'news_intro', 'str' ),
		'dark_mode'         => array( 'dark_mode', 'enum' ),
		'animations'        => array( 'animasyon', 'onoff' ),
		'pagination'        => array( 'sayfalama', 'enum' ),
		'active_card'       => array( 'aktif_kart', 'onoff' ),
		'active_campaign'   => array( 'aktif_kampanya', 'int' ),
		'cookie_enabled'    => array( 'cookie_enabled', 'bool' ),
		'cookie_title'      => array( 'cookie_title', 'str' ),
		'cookie_text'       => array( 'cookie_text', 'html' ),
		'cookie_accept'     => array( 'cookie_accept', 'str' ),
		'cookie_reject'     => array( 'cookie_reject', 'str' ),
		'cookie_more'       => array( 'cookie_more', 'str' ),
		'cookie_page'       => array( 'cookie_page', 'int' ),
		'cookie_position'   => array( 'cookie_position', 'enum' ),
		'ga4_id'            => array( 'ga4_id', 'str' ),
		'ga_mode'           => array( 'ga_mode', 'enum' ),
		'bank_holder'       => array( 'bank_holder', 'str' ),
		'bank_iban'         => array( 'bank_iban', 'str' ),
	);

	/** Allowed values of the enum fields (service values). */
	private const CHOICES = array(
		'visibility'      => array( 'live', 'coming-soon', 'store' ),
		'site_type'       => array( 'shop', 'donation' ),
		'pattern'         => array( 'dots', 'leaves', 'none' ),
		'dark_mode'       => array( 'auto', 'off' ),
		'pagination'      => array( 'sonsuz', 'numara' ),
		'cookie_position' => array( 'card', 'bar' ),
		'ga_mode'         => array( 'consent', 'after' ),
	);

	/* ---------- Settings ---------- */

	/**
	 * Stored options with the defaults filled in.
	 *
	 * @return array<string,mixed>
	 */
	private static function stored(): array {
		/** @var array<string,mixed> $defaults */
		$defaults = flx_ayar_defaults();
		/** @var array<string,mixed> $saved */
		$saved = (array) get_option( 'flx_ayarlar', array() );
		return wp_parse_args( $saved, $defaults );
	}

	private static function str( mixed $v ): string {
		return is_scalar( $v ) ? (string) $v : '';
	}

	private static function int( mixed $v ): int {
		return is_scalar( $v ) ? absint( $v ) : 0;
	}

	/**
	 * Rows of a legacy list (array of arrays); anything else is dropped.
	 *
	 * @return list<array<string,mixed>>
	 */
	private static function rows( mixed $v ): array {
		$out = array();
		if ( is_array( $v ) ) {
			foreach ( $v as $row ) {
				if ( is_array( $row ) ) {
					$out[] = $row;
				}
			}
		}
		return $out;
	}

	/**
	 * Theme Settings and the context the screen needs.
	 */
	public static function settings(): FLX_Theme_Settings {
		$o        = self::stored();
		$can_js   = current_user_can( 'unfiltered_html' );
		/** @var array{title:string,text:string} $defaults */
		$defaults = flx_coming_soon_store_texts( false );
		$own_iban = self::str( $o['bank_iban'] );
		$use      = function_exists( 'flxb_bank_account' ) ? flxb_bank_account() : null;
		$in_use   = '';
		if ( is_array( $use ) && flx_iban_temizle( self::str( $use['iban'] ) ) !== flx_iban_temizle( $own_iban ) ) {
			$in_use = self::str( $use['iban_display'] ) . ( '' !== self::str( $use['holder'] ) ? ' · ' . self::str( $use['holder'] ) : '' );
		}
		$campaigns = array();
		if ( class_exists( 'FLXB_Types' ) ) {
			foreach ( array_slice( (array) FLXB_Types::all(), 0, self::LIST_MAX ) as $t ) {
				if ( is_array( $t ) && isset( $t['id'] ) ) {
					$campaigns[] = new FLX_Theme_Option( self::int( $t['id'] ), self::str( $t['title'] ?? '' ) );
				}
			}
		}
		return new FLX_Theme_Settings(
			visibility: self::str( flx_visibility_mode() ),
			coming_soon_title: self::str( $o['coming_soon_title'] ),
			coming_soon_text: self::str( $o['coming_soon_text'] ),
			store_title: self::str( $o['coming_soon_store_title'] ),
			store_text: self::str( $o['coming_soon_store_text'] ),
			store_title_default: $defaults['title'],
			store_text_default: $defaults['text'],
			contact_email: self::str( $o['coming_soon_email'] ),
			site_type: 'donation' === $o['site_mod'] ? 'donation' : 'shop',
			pattern: in_array( $o['decoration_pattern'], self::CHOICES['pattern'], true ) ? self::str( $o['decoration_pattern'] ) : 'dots',
			table_head: self::str( $o['table_head'] ),
			news_intro: self::str( $o['news_intro'] ),
			dark_mode: 'off' === $o['dark_mode'] ? 'off' : 'auto',
			animations: 'off' !== $o['animasyon'],
			pagination: 'numara' === $o['sayfalama'] ? 'numara' : 'sonsuz',
			active_card: 'off' !== $o['aktif_kart'],
			active_campaign: self::int( $o['aktif_kampanya'] ),
			cookie_enabled: (bool) $o['cookie_enabled'],
			cookie_title: self::str( $o['cookie_title'] ),
			cookie_text: self::str( $o['cookie_text'] ),
			cookie_accept: self::str( $o['cookie_accept'] ),
			cookie_reject: self::str( $o['cookie_reject'] ),
			cookie_more: self::str( $o['cookie_more'] ),
			cookie_page: self::int( $o['cookie_page'] ),
			cookie_position: 'bar' === $o['cookie_position'] ? 'bar' : 'card',
			cookie_revision: max( 1, self::int( $o['cookie_rev'] ) ),
			ga4_id: self::str( $o['ga4_id'] ),
			ga_mode: 'after' === $o['ga_mode'] ? 'after' : 'consent',
			consent_scripts: $can_js ? self::str( $o['consent_scripts'] ) : null,
			bank_holder: self::str( $o['bank_holder'] ),
			bank_iban: $own_iban,
			bank_in_use: $in_use,
			woocommerce: class_exists( 'WooCommerce' ),
			active_card_available: function_exists( 'flx_aktif_kart_html' ),
			can_edit_scripts: $can_js,
			presets_url: function_exists( 'flxk_render_panel' ) ? admin_url( 'themes.php?page=flx-ayarlar&bolum=onayarlar' ) : '',
			campaigns: $campaigns,
		);
	}

	/**
	 * Saves the fields that are given (null / missing = unchanged). Nothing is written when any field is invalid.
	 *
	 * @param array<string,mixed> $in Changes by service key (see FIELDS, plus visibility, cookie_reset, consent_scripts).
	 * @return FLX_Theme_Settings|WP_Error The settings after the change, or the field errors.
	 */
	public static function save( array $in ): FLX_Theme_Settings|WP_Error {
		$errors = new WP_Error();
		$fail   = static function ( string $field, string $message ) use ( $errors ): void {
			$errors->add( $field, $message, array( 'field' => $field, 'status' => 400 ) );
		};
		$store  = self::stored();
		$given  = static fn( string $key ): bool => array_key_exists( $key, $in ) && null !== $in[ $key ];

		foreach ( self::CHOICES as $key => $choices ) {
			if ( $given( $key ) && ! in_array( $in[ $key ] ?? null, $choices, true ) ) {
				$fail( $key, __( 'This choice is not available.', 'fluxwp' ) );
			}
		}
		$visibility = $given( 'visibility' ) ? self::str( $in['visibility'] ?? '' ) : self::str( flx_visibility_mode() );
		if ( 'store' === $visibility && ! class_exists( 'WooCommerce' ) ) {
			$fail( 'visibility', __( 'Requires WooCommerce.', 'fluxwp' ) );
		}
		$store['site_visibility'] = 'live' === $visibility ? 'auto' : $visibility;

		if ( $given( 'bank_iban' ) && '' !== trim( self::str( $in['bank_iban'] ?? '' ) ) && '' === flx_iban_temizle( self::str( $in['bank_iban'] ?? '' ) ) ) {
			$fail( 'bank_iban', __( 'The IBAN must be 26 characters starting with “TR”; it was not saved.', 'fluxwp' ) );
		}
		if ( $given( 'ga4_id' ) && '' !== trim( self::str( $in['ga4_id'] ?? '' ) ) && ! preg_match( '/^G-[A-Z0-9]{4,}$/', strtoupper( trim( self::str( $in['ga4_id'] ?? '' ) ) ) ) ) {
			$fail( 'ga4_id', __( 'The GA4 measurement ID must look like “G-XXXXXXX”; it was not saved.', 'fluxwp' ) );
		}
		if ( $given( 'table_head' ) && '' !== trim( self::str( $in['table_head'] ?? '' ) ) && '' === (string) sanitize_hex_color( trim( self::str( $in['table_head'] ?? '' ) ) ) ) {
			$fail( 'table_head', __( 'Enter a colour like #F1F2F4.', 'fluxwp' ) );
		}
		if ( $given( 'contact_email' ) && '' !== trim( self::str( $in['contact_email'] ?? '' ) ) && ! is_email( trim( self::str( $in['contact_email'] ?? '' ) ) ) ) {
			$fail( 'contact_email', __( 'Enter a valid email address.', 'fluxwp' ) );
		}
		if ( array_key_exists( 'consent_scripts', $in ) && null !== $in['consent_scripts'] ) {
			if ( ! current_user_can( 'unfiltered_html' ) ) {
				$fail( 'consent_scripts', __( 'Only users who may add raw code can change this.', 'fluxwp' ) );
			} else {
				$store['consent_scripts'] = trim( self::str( $in['consent_scripts'] ) );
			}
		}
		if ( $errors->has_errors() ) {
			return $errors;
		}

		foreach ( self::FIELDS as $key => $f ) {
			if ( ! $given( $key ) ) {
				continue;
			}
			$v = $in[ $key ] ?? null;
			switch ( $f[1] ) {
				case 'bool':
					$store[ $f[0] ] = $v ? 1 : 0;
					break;
				case 'onoff':
					$store[ $f[0] ] = $v ? 'on' : 'off';
					break;
				case 'int':
					$store[ $f[0] ] = self::int( $v );
					break;
				default:
					$store[ $f[0] ] = self::str( $v );
			}
		}
		if ( $given( 'cookie_reset' ) && ! empty( $in['cookie_reset'] ) ) {
			$store['cookie_reset'] = 1;
		}
		// Same as the classic form post: every field is sent, so the sanitizer takes the values as they are.
		$store['_form'] = 1;
		foreach ( array( 'animasyon', 'aktif_kart' ) as $on ) {
			$store[ $on ] = 'off' === $store[ $on ] ? 0 : 1;
		}
		update_option( 'flx_ayarlar', $store );
		self::purge();
		return self::settings();
	}

	/* ---------- Global styles ---------- */

	/**
	 * Merges the origins of a theme.json list (Site Editor changes > theme.json > WordPress defaults).
	 *
	 * @param mixed $raw `wp_get_global_settings()` value: [ origin => list ] or a plain list.
	 * @return list<array<mixed,mixed>>
	 */
	private static function merge_origins( mixed $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$groups = array_is_list( $raw ) ? array( $raw ) : array( $raw['custom'] ?? null, $raw['theme'] ?? null );
		if ( ! array_is_list( $raw ) && ! array_filter( $groups ) ) {
			$groups = array( $raw['default'] ?? null );
		}
		$out  = array();
		$seen = array();
		foreach ( $groups as $list ) {
			foreach ( is_array( $list ) ? $list : array() as $item ) {
				if ( is_array( $item ) && isset( $item['slug'] ) && is_string( $item['slug'] ) && ! isset( $seen[ $item['slug'] ] ) ) {
					$seen[ $item['slug'] ] = true;
					$out[]                 = $item;
				}
			}
		}
		return $out;
	}

	/**
	 * Colours, fonts and the dark-mode switch of the site.
	 */
	public static function global_styles(): FLX_Theme_Styles {
		$palette = array();
		foreach ( self::merge_origins( wp_get_global_settings( array( 'color', 'palette' ) ) ) as $c ) {
			$palette[] = new FLX_Theme_Color( self::str( $c['slug'] ?? '' ), self::str( $c['name'] ?? '' ), self::str( $c['color'] ?? '' ) );
		}
		$fonts = array();
		foreach ( self::merge_origins( wp_get_global_settings( array( 'typography', 'fontFamilies' ) ) ) as $f ) {
			$fonts[] = new FLX_Theme_Font( self::str( $f['slug'] ?? '' ), self::str( $f['name'] ?? '' ), self::str( $f['fontFamily'] ?? '' ) );
		}
		$o = self::stored();
		return new FLX_Theme_Styles(
			palette: $palette,
			fonts: $fonts,
			dark_mode: 'off' !== $o['dark_mode'],
			table_head: (string) sanitize_hex_color( self::str( $o['table_head'] ) ),
			pattern: in_array( $o['decoration_pattern'], self::CHOICES['pattern'], true ) ? self::str( $o['decoration_pattern'] ) : 'dots',
		);
	}

	/* ---------- Payment logos ---------- */

	/**
	 * @param array<string,mixed> $l A logo row of flx_pay_logos().
	 */
	private static function logo( array $l ): FLX_Theme_Pay_Logo {
		return new FLX_Theme_Pay_Logo( self::str( $l['key'] ?? '' ), self::str( $l['label'] ?? '' ), self::str( $l['url'] ?? '' ), ! empty( $l['chip'] ) );
	}

	public static function pay_logos(): FLX_Theme_Pay_Logos {
		$current = array();
		foreach ( self::rows( flx_pay_logos() ) as $l ) {
			$current[] = self::logo( $l );
		}
		$builtin = array();
		$auto    = array();
		$defs = flx_pay_logos_builtin();
		foreach ( is_array( $defs ) ? $defs : array() as $key => $b ) {
			if ( ! is_array( $b ) ) {
				continue;
			}
			$logo      = self::logo( array( 'key' => self::str( $key ) ) + $b );
			$builtin[] = $logo;
			if ( ! empty( $b['default'] ) ) {
				$auto[] = $logo;
			}
		}
		return new FLX_Theme_Pay_Logos( null === flx_pay_logos_saved(), $current, $builtin, $auto );
	}

	/**
	 * Hand-picks the footer logos ("iyzico", "a:<attachment id>", plugin keys) or returns to the automatic list.
	 *
	 * @param list<string>|null $keys      Logo keys in order; null with $automatic.
	 * @param bool              $automatic Back to automatic.
	 * @return FLX_Theme_Pay_Logos|WP_Error
	 */
	public static function save_pay_logos( ?array $keys, bool $automatic ): FLX_Theme_Pay_Logos|WP_Error {
		if ( $automatic ) {
			update_option( 'flx_pay_logos', array( 'reset' => 1 ) );
		} else {
			$keys = array_values( array_unique( array_map( 'strval', (array) $keys ) ) );
			if ( count( $keys ) > self::LIST_MAX ) {
				return new WP_Error( 'keys', __( 'Too many logos.', 'fluxwp' ), array( 'field' => 'keys', 'status' => 400 ) );
			}
			update_option( 'flx_pay_logos', array( 'list' => $keys, 'mode' => 'custom' ) );
		}
		self::purge();
		return self::pay_logos();
	}

	/* ---------- System Check ---------- */

	/**
	 * Runs every check (cheap: options, constants, files) and the recent MCP fixes.
	 */
	public static function system(): FLX_Theme_System {
		$order  = array( 'error' => 0, 'warning' => 1, 'info' => 2, 'ok' => 3 );
		$groups = array();
		foreach ( self::rows( flx_sk_collect() ) as $c ) {
			$source               = self::str( $c['source'] ?? '' );
			$group                = self::str( $c['group'] ?? '' );
			$groups[ $group ][] = new FLX_Theme_Check(
				self::str( $c['id'] ?? '' ),
				$source,
				'' !== $source && 'theme' !== $source ? self::str( flx_sk_source_label( $source ) ) : '',
				$group,
				self::str( $c['label'] ?? '' ),
				self::str( $c['status'] ?? '' ),
				self::str( $c['message'] ?? '' ),
				self::str( $c['action_url'] ?? '' ),
				self::str( $c['action_label'] ?? '' ),
			);
		}
		$checks = array();
		foreach ( $groups as $list ) {
			usort( $list, static fn( FLX_Theme_Check $a, FLX_Theme_Check $b ): int => ( $order[ $a->status ] ?? 2 ) <=> ( $order[ $b->status ] ?? 2 ) );
			$checks = array_merge( $checks, $list );
		}
		$log = array();
		if ( function_exists( 'flxseo_system_fix_log' ) ) {
			foreach ( self::rows( flxseo_system_fix_log( 20 ) ) as $r ) {
				$u     = get_userdata( self::int( $r['u'] ?? 0 ) );
				$log[] = new FLX_Theme_Fix_Entry(
					self::int( $r['t'] ?? 0 ),
					$u ? $u->user_login : '?',
					self::str( $r['label'] ?? '' ),
					self::str( $r['value'] ?? '' ),
					self::str( $r['before'] ?? '' ),
					self::str( $r['after'] ?? '' ),
					! empty( $r['ok'] ),
					self::str( $r['msg'] ?? '' ),
				);
			}
		}
		return new FLX_Theme_System( time(), $checks, function_exists( 'flxseo_system_fix_log' ), $log );
	}

	/* ---------- Migrate from the previous theme ---------- */

	/**
	 * Previous themes whose settings, templates and styles can be copied.
	 *
	 * @return list<FLX_Theme_Migration_Source>
	 */
	public static function migration_sources(): array {
		$done = (array) get_option( 'flx_tasi_done', array() );
		$out  = array();
		$sources = flx_tasi_sources();
		foreach ( is_array( $sources ) ? $sources : array() as $src ) {
			$src = self::str( $src );
			if ( get_stylesheet() !== $src && false !== get_option( 'theme_mods_' . $src ) ) {
				$out[] = new FLX_Theme_Migration_Source( $src, empty( $done[ $src ] ) ? null : self::int( $done[ $src ] ) );
			}
		}
		return $out;
	}

	/**
	 * Copies the previous theme's data (safe to repeat).
	 *
	 * @param string $source Previous theme folder name.
	 * @return list<string>|WP_Error What was done.
	 */
	public static function migrate( string $source ): array|WP_Error {
		$source = sanitize_key( $source );
		$known  = array_map( static fn( FLX_Theme_Migration_Source $s ): string => $s->stylesheet, self::migration_sources() );
		if ( ! in_array( $source, $known, true ) ) {
			return new WP_Error( 'source', __( 'This theme cannot be migrated.', 'fluxwp' ), array( 'field' => 'source', 'graphql_code' => 'NOT_FOUND' ) );
		}
		$done = (array) get_option( 'flx_tasi_done', array() );
		unset( $done[ $source ] );
		update_option( 'flx_tasi_done', $done, false );
		$log = array_map( 'strval', (array) flx_tasi_run( $source ) );
		self::purge();
		return $log ? array_values( $log ) : array( __( 'There was nothing new to migrate.', 'fluxwp' ) );
	}

	/**
	 * Drops the cached GraphQL reads of the theme.
	 */
	public static function purge(): void {
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( array( self::CACHE_TAG ) );
		}
	}
}
