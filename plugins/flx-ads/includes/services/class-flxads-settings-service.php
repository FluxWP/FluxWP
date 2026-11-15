<?php
/**
 * Flux Ads: settings, connection tests and the screen context. Secrets are write-only: they are never
 * returned, only whether one is set and its last four characters.
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type FlxadsSecret array{set: bool, mask: string, fromConfig: bool}
 * @phpstan-type FlxadsTest array{ok: bool, message: string, at: string|null, ago: string}
 * @phpstan-type FlxadsPlatform array{key: string, label: string, on: bool, ready: bool, canTest: bool, test: FlxadsTest|null}
 * @phpstan-type FlxadsSettings array{metaOn: bool, metaPixel: string, metaToken: FlxadsSecret, metaTest: string, metaAccount: string, metaPage: string, metaAdsToken: FlxadsSecret, tiktokOn: bool, tiktokPixel: string, tiktokToken: FlxadsSecret, tiktokTest: string, ga4On: bool, ga4Id: string, ga4ThemeId: string, ga4Secret: FlxadsSecret, consent: string, pii: string, browser: bool, windowDays: int, optRoas: float, optMinSpend: float, platforms: list<FlxadsPlatform>}
 * @phpstan-type FlxadsContext array{canManage: bool, metaReady: bool, aiReady: bool, crmReady: bool, openSuggestions: int, baseCurrency: string, version: string, tabs: list<string>}
 */
final class FLXADS_Settings_Service {

	public const CACHE_TAG = 'flx-ads:settings';

	/**
	 * Settings form keys → settings option keys.
	 *
	 * @var array<string,string>
	 */
	private const MAP = array(
		'metaOn'      => 'meta_on',
		'metaPixel'   => 'meta_pixel',
		'metaTest'    => 'meta_test',
		'metaAccount' => 'meta_account',
		'metaPage'    => 'meta_page',
		'tiktokOn'    => 'tiktok_on',
		'tiktokPixel' => 'tiktok_pixel',
		'tiktokTest'  => 'tiktok_test',
		'ga4On'       => 'ga4_on',
		'ga4Id'       => 'ga4_id',
		'consent'     => 'consent',
		'pii'         => 'pii',
		'browser'     => 'browser',
		'windowDays'  => 'window_days',
		'optRoas'     => 'opt_roas',
		'optMinSpend' => 'opt_min_spend',
	);

	/**
	 * Secret input keys → settings option keys.
	 *
	 * @var array<string,string>
	 */
	private const SECRETS = array(
		'metaToken'    => 'meta_token',
		'metaAdsToken' => 'meta_ads_token',
		'tiktokToken'  => 'tiktok_token',
		'ga4Secret'    => 'ga4_secret',
	);

	/**
	 * What the screen needs before it draws anything.
	 *
	 * @return FlxadsContext
	 */
	public static function context(): array {
		FLXADS_V::tag( self::CACHE_TAG );
		$manage = flxads_can( true );
		$tabs   = array( 'report', 'campaigns', 'suggestions', 'studio', 'planner', 'experiments', 'value', 'spend', 'audiences', 'log', 'links' );
		if ( $manage ) {
			$tabs[] = 'settings';
		}
		return array(
			'canManage'       => $manage,
			'metaReady'       => (bool) FLXADS_Spend::meta_ready(),
			'aiReady'         => function_exists( 'flxai_ready' ) && (bool) flxai_ready( 'flx-ads' ),
			'crmReady'        => (bool) FLXADS_Audiences::crm_ready(),
			'openSuggestions' => FLXADS_Meta_Service::open_count(),
			'baseCurrency'    => FLXADS_V::s( FLXADS_Report::base_currency() ),
			'version'         => defined( 'FLXADS_VERSION' ) ? (string) FLXADS_VERSION : '',
			'tabs'            => $tabs,
		);
	}

	/**
	 * Read side effects the classic page runs on load (queue flush, spend and suggestion refresh) so the
	 * React screen can trigger them on a timer. Nothing here writes to an ad account.
	 *
	 * @return array{ok: bool, openSuggestions: int}
	 */
	public static function heartbeat(): array {
		if ( (int) get_option( 'flxads_heartbeat' ) < time() - 10 * MINUTE_IN_SECONDS ) {
			FLXADS_Send::run_queue( 10 );
		}
		FLXADS_Spend::maybe_sync_later();
		return array(
			'ok'              => true,
			'openSuggestions' => FLXADS_Meta_Service::open_count(),
		);
	}

	/**
	 * @param string $key Settings key.
	 * @return FlxadsSecret
	 */
	private static function secret( string $key ): array {
		$const  = 'FLXADS_' . strtoupper( $key );
		$cfg    = defined( $const ) && '' !== (string) constant( $const );
		$plain  = flxads_secret( $key );
		$stored = '' !== $plain;
		return array(
			'set'        => $stored,
			'mask'       => $stored ? FLXADS_V::s( flxads_mask( $plain ) ) : '',
			'fromConfig' => $cfg,
		);
	}

	/**
	 * @param string $p Platform key.
	 * @return FlxadsTest|null
	 */
	private static function last_test( string $p ): ?array {
		$all = FLXADS_V::a( get_option( 'flxads_tests', array() ) );
		if ( ! isset( $all[ $p ] ) ) {
			return null;
		}
		$t  = FLXADS_V::a( $all[ $p ] );
		$at = FLXADS_V::sn( $t['at'] ?? '' );
		return array(
			'ok'      => FLXADS_V::b( $t['ok'] ?? false ),
			'message' => FLXADS_V::s( $t['message'] ?? '' ),
			'at'      => $at,
			'ago'     => null === $at ? '' : FLXADS_V::s( flxads_ago( $at ) ),
		);
	}

	/**
	 * @return list<FlxadsPlatform>
	 */
	private static function platforms(): array {
		$out = array();
		foreach ( flxads_platforms() as $key => $label ) {
			$key   = (string) $key;
			$out[] = array(
				'key'     => $key,
				'label'   => (string) $label,
				'on'      => (bool) flxads_setting( $key . '_on' ),
				'ready'   => (bool) flxads_platform_on( $key ),
				'canTest' => (bool) flxads_platform_on( $key, true ),
				'test'    => self::last_test( $key ),
			);
		}
		return $out;
	}

	/**
	 * @return FlxadsSettings
	 */
	public static function get(): array {
		FLXADS_V::tag( self::CACHE_TAG );
		$s = flxads_settings();
		return array(
			'metaOn'       => FLXADS_V::b( $s['meta_on'] ?? 0 ),
			'metaPixel'    => FLXADS_V::s( $s['meta_pixel'] ?? '' ),
			'metaToken'    => self::secret( 'meta_token' ),
			'metaTest'     => FLXADS_V::s( $s['meta_test'] ?? '' ),
			'metaAccount'  => FLXADS_V::s( $s['meta_account'] ?? '' ),
			'metaPage'     => FLXADS_V::s( $s['meta_page'] ?? '' ),
			'metaAdsToken' => self::secret( 'meta_ads_token' ),
			'tiktokOn'     => FLXADS_V::b( $s['tiktok_on'] ?? 0 ),
			'tiktokPixel'  => FLXADS_V::s( $s['tiktok_pixel'] ?? '' ),
			'tiktokToken'  => self::secret( 'tiktok_token' ),
			'tiktokTest'   => FLXADS_V::s( $s['tiktok_test'] ?? '' ),
			'ga4On'        => FLXADS_V::b( $s['ga4_on'] ?? 0 ),
			'ga4Id'        => FLXADS_V::s( $s['ga4_id'] ?? '' ),
			'ga4ThemeId'   => function_exists( 'flx_ayar' ) ? FLXADS_V::s( flx_ayar( 'ga4_id' ) ) : '',
			'ga4Secret'    => self::secret( 'ga4_secret' ),
			'consent'      => FLXADS_V::s( $s['consent'] ?? 'auto', 'auto' ),
			'pii'          => FLXADS_V::s( $s['pii'] ?? 'consent', 'consent' ),
			'browser'      => FLXADS_V::b( $s['browser'] ?? 0 ),
			'windowDays'   => FLXADS_V::i( $s['window_days'] ?? 90, 90 ),
			'optRoas'      => FLXADS_V::f( $s['opt_roas'] ?? 2, 2.0 ),
			'optMinSpend'  => FLXADS_V::f( $s['opt_min_spend'] ?? 0 ),
			'platforms'    => self::platforms(),
		);
	}

	/**
	 * Validates and stores the form. Secrets: empty keeps the old one, "__clear__" removes it.
	 *
	 * @param array<string,mixed> $in Input (camelCase keys, as the screen sends them).
	 */
	public static function save( array $in ): bool {
		$raw = array();
		foreach ( self::MAP as $camel => $key ) {
			if ( array_key_exists( $camel, $in ) ) {
				$raw[ $key ] = is_bool( $in[ $camel ] ) ? ( $in[ $camel ] ? 1 : 0 ) : $in[ $camel ];
			}
		}
		$v = flxads_clean_settings( $raw, true );
		foreach ( self::SECRETS as $camel => $key ) {
			$v[ $key ] = trim( FLXADS_V::s( $in[ $camel ] ?? '' ) );
		}
		flxads_store_settings( $v );
		FLXADS_V::purge( self::CACHE_TAG );
		FLXADS_V::purge( FLXADS_Reports_Service::CACHE_TAG );
		FLXADS_V::purge( FLXADS_Meta_Service::CACHE_TAG );
		return true;
	}

	/**
	 * Saves the form values first (as the classic Test button does), then tests one platform. Test events
	 * only: Meta/TikTok use the test event code, GA4 the validation endpoint.
	 *
	 * @param string                   $platform meta | tiktok | ga4.
	 * @param array<string,mixed>|null $in       Form values to save first.
	 * @return FlxadsTest|WP_Error
	 */
	public static function test( string $platform, ?array $in = null ) {
		if ( ! isset( flxads_platforms()[ $platform ] ) ) {
			return FLXADS_V::error( 'flxads_platform', __( 'Unknown platform.', 'flx-ads' ), 'platform' );
		}
		if ( null !== $in ) {
			self::save( $in );
		}
		FLXADS_Send::test( $platform );
		FLXADS_V::purge( self::CACHE_TAG );
		$t = self::last_test( $platform );
		if ( null === $t ) {
			return FLXADS_V::error( 'flxads_platform', __( 'Fill in the ID and the key first.', 'flx-ads' ), 'platform' );
		}
		return $t;
	}
}
