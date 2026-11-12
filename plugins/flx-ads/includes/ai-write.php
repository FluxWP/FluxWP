<?php
/**
 * Flux Ads: değişiklik yapan AI/MCP araçları. Gizli anahtarlar hiçbir araçla okunmaz ve yazılmaz.
 *
 * - Flux AI sohbeti: salt okunur olmayan her araç Flux AI'ın onay kartında bekler; kart onaydır.
 * - MCP (Claude vb.): ayar değiştirme ve harcama silme ilk çağrıda yalnız önizleme + `confirm_token` döner;
 *   kullanıcı onaylayınca aynı çağrı token ile tekrarlanır (CRM ile aynı desen).
 * Her yazma `flxads_tool_log` seçeneğine (son 100) kaydedilir.
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

const FLXADS_TOKEN_TTL = 15 * MINUTE_IN_SECONDS;

/** Araç tanımları: kind read | write | destructive. */
function flxads_write_tools() {
	$platforms = array_keys( flxads_platforms() );
	$channels  = array_values( array_filter( array_keys( flxads_channel_labels() ) ) );
	return array(
		'flxads_settings_get'    => array(
			'kind'        => 'read',
			'title'       => __( 'Ad tracking settings', 'flx-ads' ),
			'description' => 'Current Flux Ads settings without secrets: platform on/off, pixel IDs, Meta ad account, test codes, consent mode, e-mail/phone policy, browser pixels, attribution window. For secrets only whether each one is set.',
			'props'       => array(),
			'run'         => static function () {
				$s   = flxads_settings();
				$out = array_intersect_key( $s, flxads_clean_settings( array() ) );
				foreach ( flxads_secret_keys() as $k ) {
					$out[ $k . '_set' ] = '' !== flxads_secret( $k );
				}
				$out['ga4_id_effective'] = flxads_ga4_id();
				return $out;
			},
		),
		'flxads_settings_update' => array(
			'kind'        => 'destructive',
			'title'       => __( 'Change ad tracking settings', 'flx-ads' ),
			'description' => 'Change Flux Ads settings. Send only the fields to change. Secrets (access tokens, API secret) cannot be set here: the user enters them in Flux Ads › Settings. consent: auto (wait for cookie banner, recommended) | always. pii: consent | never | always. Empty the test codes after testing.',
			'props'       => array(
				'meta_on'      => array( 'type' => 'boolean' ),
				'meta_pixel'   => array( 'type' => 'string', 'description' => 'Meta pixel (dataset) ID, digits.' ),
				'meta_test'    => array( 'type' => 'string', 'description' => 'Meta test event code; empty to clear.' ),
				'meta_account' => array( 'type' => 'string', 'description' => 'Meta ad account ID for spend, digits (act_ optional).' ),
				'tiktok_on'    => array( 'type' => 'boolean' ),
				'tiktok_pixel' => array( 'type' => 'string' ),
				'tiktok_test'  => array( 'type' => 'string' ),
				'ga4_on'       => array( 'type' => 'boolean' ),
				'ga4_id'       => array( 'type' => 'string', 'description' => 'G-XXXX; empty = theme setting.' ),
				'consent'      => array( 'type' => 'string', 'enum' => array( 'auto', 'always' ) ),
				'pii'          => array( 'type' => 'string', 'enum' => array( 'consent', 'never', 'always' ) ),
				'browser'      => array( 'type' => 'boolean' ),
				'window_days'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 365 ),
			),
			'preview'     => static function ( $a ) {
				$new = flxads_clean_settings( $a, true );
				$cur = flxads_settings();
				$chg = array();
				foreach ( $new as $k => $v ) {
					if ( (string) $cur[ $k ] !== (string) $v ) {
						$chg[ $k ] = array( 'from' => $cur[ $k ], 'to' => $v );
					}
				}
				return array( 'changes' => $chg );
			},
			'run'         => static function ( $a ) {
				$new = flxads_clean_settings( $a, true );
				if ( ! $new ) {
					throw new Exception( 'No known setting given.' );
				}
				flxads_store_settings( $new );
				return array( 'saved' => array_keys( $new ) );
			},
		),
		'flxads_test_connection' => array(
			'kind'        => 'write',
			'title'       => __( 'Test ad platform connection', 'flx-ads' ),
			'description' => 'Test one platform with the saved keys. Meta/TikTok send a test event only when a test event code is set (otherwise Meta only looks up the pixel); GA4 uses the validation endpoint. No real conversion is recorded.',
			'props'       => array( 'platform' => array( 'type' => 'string', 'enum' => $platforms ) ),
			'required'    => array( 'platform' ),
			'run'         => static function ( $a ) {
				$p = (string) ( $a['platform'] ?? '' );
				if ( ! isset( flxads_platforms()[ $p ] ) ) {
					throw new Exception( 'Unknown platform.' );
				}
				list( $ok, $msg ) = FLXADS_Send::test( $p );
				return array( 'ok' => (bool) $ok, 'message' => (string) $msg );
			},
		),
		'flxads_send_log'        => array(
			'kind'        => 'read',
			'title'       => __( 'Ad conversion send log', 'flx-ads' ),
			'description' => 'Latest server conversion sends: id, platform, order_id, value, currency, status (pending | sent | failed | skipped), last_error, attempts, dates. Filter by status.',
			'props'       => array(
				'status' => array( 'type' => 'string', 'enum' => array( 'pending', 'sent', 'failed', 'skipped' ) ),
				'limit'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ),
			),
			'run'         => static function ( $a ) {
				global $wpdb;
				$w = '1=1';
				if ( ! empty( $a['status'] ) ) {
					$w = $wpdb->prepare( 'status = %s', (string) $a['status'] );
				}
				return $wpdb->get_results( $wpdb->prepare( 'SELECT id, platform, order_id, value, currency, status, last_error, attempts, created_at, sent_at FROM ' . flxads_table( 'conversions' ) . " WHERE $w ORDER BY id DESC LIMIT %d", max( 1, min( 100, (int) ( $a['limit'] ?? 20 ) ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB
			},
		),
		'flxads_resend'          => array(
			'kind'        => 'write',
			'title'       => __( 'Send a conversion again', 'flx-ads' ),
			'description' => 'Send failed (or skipped for a reason other than missing consent) conversions again. ids from flxads_send_log. A visitor without cookie consent is never sent.',
			'props'       => array( 'ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'maxItems' => 50 ) ),
			'required'    => array( 'ids' ),
			'run'         => static function ( $a ) {
				$out = array();
				foreach ( array_slice( array_map( 'absint', (array) ( $a['ids'] ?? array() ) ), 0, 50 ) as $id ) {
					$r          = FLXADS_Send::retry( $id );
					$row        = FLXADS_Send::get( $id );
					$out[ $id ] = $row ? array( 'status' => $row['status'], 'error' => $row['last_error'] ) : array( 'status' => 'missing' );
					unset( $r );
				}
				return $out;
			},
		),
		'flxads_spend_list'      => array(
			'kind'        => 'read',
			'title'       => __( 'Ad spend', 'flx-ads' ),
			'description' => 'Ad spend in a period by campaign and channel (Meta synced + manual), total in the base currency, Meta sync state, and the manual entries with their ids.',
			'props'       => array(
				'from' => array( 'type' => 'string', 'description' => 'Y-m-d, default 29 days ago.' ),
				'to'   => array( 'type' => 'string', 'description' => 'Y-m-d, default today.' ),
			),
			'run'         => static function ( $a ) {
				$from = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $a['from'] ?? '' ) ) ? $a['from'] : wp_date( 'Y-m-d', time() - 29 * DAY_IN_SECONDS );
				$to   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $a['to'] ?? '' ) ) ? $a['to'] : wp_date( 'Y-m-d' );
				$t    = FLXADS_Spend::totals( $from, $to );
				$c    = array();
				foreach ( $t['by_campaign'] as $v ) {
					$c[] = array( 'campaign' => $v[2], 'channel' => $v[1], 'spend' => round( $v[0], 2 ) );
				}
				$st = FLXADS_Spend::status( 'meta' );
				return array(
					'from'      => $from,
					'to'        => $to,
					'currency'  => FLXADS_Report::base_currency(),
					'total'     => round( $t['total'], 2 ),
					'channels'  => array_map( static function ( $v ) { return round( $v, 2 ); }, $t['by_channel'] ),
					'campaigns' => $c,
					'meta_sync' => array( 'ready' => FLXADS_Spend::meta_ready(), 'at' => $st['at'] ?? '', 'ok' => ! empty( $st['ok'] ), 'error' => empty( $st['ok'] ) ? (string) ( $st['message'] ?? '' ) : '' ),
					'manual'    => array_map(
						static function ( $r ) {
							return array_intersect_key( $r, array_flip( array( 'id', 'platform', 'day', 'campaign', 'ad', 'spend', 'currency' ) ) );
						},
						FLXADS_Spend::manual_rows( 50 )
					),
				);
			},
		),
		'flxads_spend_add'       => array(
			'kind'        => 'write',
			'title'       => __( 'Add ad spend', 'flx-ads' ),
			'description' => 'Add spend by hand for a channel that is not read automatically (Google Ads, TikTok, posters...). Use the same campaign name as utm_campaign so the report can match it.',
			'props'       => array(
				'channel'  => array( 'type' => 'string', 'enum' => $channels ),
				'day'      => array( 'type' => 'string', 'description' => 'Y-m-d' ),
				'campaign' => array( 'type' => 'string' ),
				'ad'       => array( 'type' => 'string', 'description' => 'Optional, utm_content.' ),
				'spend'    => array( 'type' => 'number', 'exclusiveMinimum' => 0 ),
				'currency' => array( 'type' => 'string', 'description' => 'ISO code, default shop currency.' ),
			),
			'required'    => array( 'channel', 'day', 'campaign', 'spend' ),
			'run'         => static function ( $a ) {
				$a['spend'] = (string) ( $a['spend'] ?? '' );
				if ( ! FLXADS_Spend::add_manual( $a ) ) {
					throw new Exception( 'Spend must be greater than zero.' );
				}
				return array( 'added' => true );
			},
		),
		'flxads_spend_delete'    => array(
			'kind'        => 'destructive',
			'title'       => __( 'Delete manual ad spend', 'flx-ads' ),
			'description' => 'Delete a manual spend entry (id from flxads_spend_list). Spend read from Meta cannot be deleted.',
			'props'       => array( 'id' => array( 'type' => 'integer' ) ),
			'required'    => array( 'id' ),
			'preview'     => static function ( $a ) {
				foreach ( FLXADS_Spend::manual_rows( 500 ) as $r ) {
					if ( (int) $r['id'] === (int) ( $a['id'] ?? 0 ) ) {
						return array( 'delete' => array_intersect_key( $r, array_flip( array( 'id', 'platform', 'day', 'campaign', 'ad', 'spend', 'currency' ) ) ) );
					}
				}
				throw new Exception( 'No manual spend entry with this id.' );
			},
			'run'         => static function ( $a ) {
				return array( 'deleted' => FLXADS_Spend::delete_manual( (int) ( $a['id'] ?? 0 ) ) );
			},
		),
		'flxads_spend_sync'      => array(
			'kind'        => 'write',
			'title'       => __( 'Update Meta ad spend now', 'flx-ads' ),
			'description' => 'Read Meta ad spend now (normally every 6 hours). days: how many days back, default 7 (30 on the first read), max 90.',
			'props'       => array( 'days' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 90 ) ),
			'run'         => static function ( $a ) {
				$r  = FLXADS_Spend::sync_meta( max( 0, min( 90, (int) ( $a['days'] ?? 0 ) ) ) );
				$st = FLXADS_Spend::status( 'meta' );
				return true === $r ? array( 'ok' => true, 'rows' => (int) ( $st['rows'] ?? 0 ), 'since' => $st['since'] ?? '', 'until' => $st['until'] ?? '' ) : array( 'ok' => false, 'error' => (string) $r );
			},
		),
	);
}

function flxads_tool_schema( array $t, $token ) {
	$s = array( 'type' => 'object', 'properties' => $t['props'] ? $t['props'] : new stdClass() );
	if ( ! empty( $t['required'] ) ) {
		$s['required'] = $t['required'];
	}
	if ( $token ) {
		if ( ! is_array( $s['properties'] ) ) {
			$s['properties'] = array();
		}
		$s['properties']['confirm_token'] = array( 'type' => 'string', 'description' => 'From the preview of the first call. Send only after the user approved.' );
	}
	return $s;
}

function flxads_token_hash( $name, array $args ) {
	ksort( $args );
	return hash( 'sha256', $name . '|' . get_current_user_id() . '|' . wp_json_encode( $args ) );
}

function flxads_confirm_token( $name, array $args ) {
	$ts = time();
	return $ts . '.' . substr( hash_hmac( 'sha256', $ts . '|' . flxads_token_hash( $name, $args ), wp_salt( 'nonce' ) ), 0, 32 );
}

/** Token doğru, süresi geçmemiş, kullanılmamış mı? Doğruysa kullanılmış sayılır. */
function flxads_check_token( $token, $name, array $args ) {
	$p = explode( '.', (string) $token );
	if ( 2 !== count( $p ) || ! ctype_digit( $p[0] ) || time() - (int) $p[0] > FLXADS_TOKEN_TTL ) {
		return false;
	}
	$want = substr( hash_hmac( 'sha256', $p[0] . '|' . flxads_token_hash( $name, $args ), wp_salt( 'nonce' ) ), 0, 32 );
	if ( ! hash_equals( $want, $p[1] ) ) {
		return false;
	}
	$used = 'flxads_tok_' . md5( $token );
	if ( get_transient( $used ) ) {
		return false;
	}
	set_transient( $used, 1, FLXADS_TOKEN_TTL + 60 );
	return true;
}

/**
 * Aracı çalıştır. $confirmed: onay kanal tarafından alındı (Flux AI onay kartı).
 *
 * @throws Exception Yetki, doğrulama, token.
 */
function flxads_run_tool( $name, $args, $channel, $confirmed = false ) {
	$tools = flxads_write_tools();
	if ( ! isset( $tools[ $name ] ) ) {
		throw new Exception( 'Unknown tool.' );
	}
	$t    = $tools[ $name ];
	$args = is_array( $args ) ? $args : (array) $args;
	if ( ! flxads_can( 'read' !== $t['kind'] ) ) {
		throw new Exception( 'Not allowed.' );
	}
	$token = (string) ( $args['confirm_token'] ?? '' );
	unset( $args['confirm_token'] );
	if ( 'destructive' === $t['kind'] && ! $confirmed ) {
		if ( '' === $token ) {
			return array(
				'confirmation_required' => true,
				'preview'               => isset( $t['preview'] ) ? call_user_func( $t['preview'], $args ) : $args,
				'confirm_token'         => flxads_confirm_token( $name, $args ),
				'next'                  => 'Nothing was changed. Show this preview to the user in plain words and ask for approval. Only after the user agrees, call the same tool again with the same arguments plus confirm_token.',
			);
		}
		if ( ! flxads_check_token( $token, $name, $args ) ) {
			throw new Exception( 'confirm_token is invalid, expired, already used or the arguments changed. Call again without confirm_token for a new preview.' );
		}
	}
	$out = call_user_func( $t['run'], $args );
	if ( 'read' !== $t['kind'] ) {
		$log = (array) get_option( 'flxads_tool_log', array() );
		array_unshift( $log, array( 'tool' => $name, 'channel' => $channel, 'user' => get_current_user_id(), 'at' => flxads_now(), 'args' => $args ) );
		update_option( 'flxads_tool_log', array_slice( $log, 0, 100 ), false );
	}
	return $out;
}

/* MCP: yıkıcı araçlar onay anahtarı ister. */
add_filter(
	'flx_mcp_tools',
	static function ( $tools ) {
		foreach ( flxads_write_tools() as $name => $t ) {
			$destr          = 'destructive' === $t['kind'];
			$tools[ $name ] = array(
				'title'       => $t['title'],
				'description' => $t['description'] . ( $destr ? ' [Needs approval: the first call changes nothing and returns a preview with confirm_token; show it to the user, then repeat the call with confirm_token.]' : '' ),
				'readonly'    => 'read' === $t['kind'],
				'cap'         => 'read' === $t['kind'] ? 'flxads_view' : 'flxads_manage',
				'source'      => 'flx-ads',
				'log'         => 'read' !== $t['kind'],
				'schema'      => flxads_tool_schema( $t, $destr ),
				'run'         => static function ( $args ) use ( $name ) {
					return flxads_run_tool( $name, $args, 'mcp' );
				},
			);
		}
		return $tools;
	}
);

/* Flux AI sohbeti: değişiklik yapan araç onay kartında bekler; kart onaydır, token istenmez. */
add_filter(
	'flxai_tools',
	static function ( $tools ) {
		foreach ( flxads_write_tools() as $name => $t ) {
			$tools[ $name ] = array(
				'title'       => $t['title'],
				'description' => $t['description'],
				'schema'      => flxads_tool_schema( $t, false ),
				'readonly'    => 'read' === $t['kind'],
				'confirm'     => 'read' !== $t['kind'],
				'cap'         => 'read' === $t['kind'] ? 'flxads_view' : 'flxads_manage',
				'source'      => 'flx-ads',
				'run'         => static function ( $args ) use ( $name, $t ) {
					return flxads_run_tool( $name, $args, 'ai', 'read' !== $t['kind'] );
				},
			);
		}
		return $tools;
	}
);

/* Sohbet talimatı. */
add_filter(
	'flxai_chat_system',
	static function ( $text ) {
		if ( ! flxads_can() ) {
			return $text;
		}
		return $text . "\n\nFlux Ads: use flxads_report for which ad brought which donation (with spend and ROAS when known). Never ask the user to paste access tokens or API secrets into the chat; they enter them in Flux Ads › Settings. Before changing settings call flxads_settings_get. Add manual spend with the same campaign name as utm_campaign.";
	}
);
