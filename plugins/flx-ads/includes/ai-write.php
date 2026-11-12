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
				'opt_roas'      => array( 'type' => 'number', 'description' => 'Suggestions: target return on ad spend (e.g. 2 = 2x).' ),
				'opt_min_spend' => array( 'type' => 'number', 'description' => 'Suggestions: spend (ad account currency, 7 days) before a campaign is judged; 0 = automatic (2x average cost per payment).' ),
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
		'flxads_value_report'      => array(
			'kind'        => 'read',
			'title'       => __( 'Donor value by campaign (LTV, CAC)', 'flx-ads' ),
			'description' => 'People whose first payment was in the period, grouped by the campaign or channel of that first payment, with everything they paid so far (also later payments without an ad). Per row: new, first, ltv30/90/180, ltv (so far), avg (per person), rate (% who paid again), spend, cac (spend / new), ratio (avg / cac; >3 good, <1 not paid back), payback (days until the cohort paid back the spend). Base currency. cohorts=true adds the monthly cohort table (cumulative value per person, M0..M11).',
			'props'       => array(
				'from'    => array( 'type' => 'string', 'description' => 'Y-m-d, first-payment window start, default 89 days ago.' ),
				'to'      => array( 'type' => 'string', 'description' => 'Y-m-d, default today.' ),
				'by'      => array( 'type' => 'string', 'enum' => array( 'campaign', 'channel' ) ),
				'kind'    => array( 'type' => 'string', 'enum' => array( 'all', 'donations', 'orders' ) ),
				'cohorts' => array( 'type' => 'boolean' ),
			),
			'run'         => static function ( $a ) {
				$f = array( 'kind' => in_array( $a['kind'] ?? '', array( 'donations', 'orders' ), true ) ? $a['kind'] : 'all' );
				foreach ( array( 'from', 'to' ) as $k ) {
					if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $a[ $k ] ?? '' ) ) ) {
						$f[ $k ] = $a[ $k ];
					}
				}
				$r     = FLXADS_Cohort::value( 'channel' === ( $a['by'] ?? '' ) ? 'channel' : 'campaign', $f );
				$round = static function ( $v ) {
					return is_float( $v ) ? round( $v, 2 ) : $v;
				};
				$r['totals'] = array_map( $round, $r['totals'] );
				foreach ( $r['rows'] as &$row ) {
					$row = array_map( $round, $row );
				}
				unset( $row );
				if ( ! empty( $a['cohorts'] ) ) {
					$r['cohorts'] = FLXADS_Cohort::matrix( array( 'kind' => $f['kind'] ) );
				}
				return $r;
			},
		),
		'flxads_audiences_list'    => array(
			'kind'        => 'read',
			'title'       => __( 'Meta audiences', 'flx-ads' ),
			'description' => 'Meta custom and lookalike audiences managed by Flux Ads: id, name, source (all | donors | rfm:<segment> | seg:<CRM segment id> | lookalike:<audience id>), purpose (target | exclude), meta_id, people count, last sync, last error. sources lists the groups that can be used for a new audience with their consented people count. Only people with ad consent are ever sent.',
			'props'       => array(),
			'run'         => static function () {
				$src = array();
				foreach ( FLXADS_Audiences::sources() as $k => $l ) {
					$src[] = array( 'source' => $k, 'label' => $l, 'people' => count( FLXADS_Audiences::member_ids( $k ) ) );
				}
				return array(
					'ready'     => FLXADS_Audiences::ready(),
					'crm'       => FLXADS_Audiences::crm_ready(),
					'audiences' => array_values( FLXADS_Audiences::all() ),
					'sources'   => FLXADS_Audiences::crm_ready() ? $src : array(),
				);
			},
		),
		'flxads_audience_create'   => array(
			'kind'        => 'destructive',
			'title'       => __( 'Create a Meta audience', 'flx-ads' ),
			'description' => 'Create a custom audience in the Meta ad account from a CRM group and upload its consented people (hashed). purpose: target (show ads to them) | exclude (keep them out of an ad set, e.g. recent donors). lookalike=true also creates a lookalike (country ISO-2, ratio 1-10 percent). Updated daily afterwards. Needs Flux CRM and an ads_management key.',
			'props'       => array(
				'name'      => array( 'type' => 'string', 'maxLength' => 100 ),
				'source'    => array( 'type' => 'string', 'description' => 'From flxads_audiences_list sources.' ),
				'purpose'   => array( 'type' => 'string', 'enum' => array( 'target', 'exclude' ) ),
				'lookalike' => array( 'type' => 'boolean' ),
				'country'   => array( 'type' => 'string', 'description' => 'ISO-2, default TR.' ),
				'ratio'     => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 10 ),
			),
			'required'    => array( 'name', 'source' ),
			'preview'     => static function ( $a ) {
				$src = FLXADS_Audiences::sources();
				if ( ! isset( $src[ (string) ( $a['source'] ?? '' ) ] ) ) {
					throw new Exception( 'Unknown source. Use one from flxads_audiences_list.' );
				}
				return array(
					'create'    => array( 'name' => (string) $a['name'], 'group' => $src[ $a['source'] ], 'purpose' => (string) ( $a['purpose'] ?? 'target' ) ),
					'people'    => count( FLXADS_Audiences::member_ids( (string) $a['source'] ) ),
					'lookalike' => ! empty( $a['lookalike'] ) ? array( 'country' => strtoupper( (string) ( $a['country'] ?? 'TR' ) ), 'ratio' => (int) ( $a['ratio'] ?? 1 ) ) : false,
				);
			},
			'run'         => static function ( $a ) {
				$r = FLXADS_Audiences::create( $a );
				if ( is_wp_error( $r ) ) {
					throw new Exception( $r->get_error_message() );
				}
				return $r;
			},
		),
		'flxads_audience_lookalike' => array(
			'kind'        => 'destructive',
			'title'       => __( 'Create a lookalike audience', 'flx-ads' ),
			'description' => 'Create a Meta lookalike audience from one of the custom audiences (id from flxads_audiences_list). country ISO-2, ratio 1-10 percent (1 = closest). Meta needs at least 100 matched people in the source.',
			'props'       => array(
				'id'      => array( 'type' => 'string' ),
				'country' => array( 'type' => 'string' ),
				'ratio'   => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 10 ),
			),
			'required'    => array( 'id' ),
			'preview'     => static function ( $a ) {
				$o = FLXADS_Audiences::get( (string) ( $a['id'] ?? '' ) );
				if ( ! $o || 'custom' !== $o['kind'] ) {
					throw new Exception( 'No custom audience with this id.' );
				}
				return array( 'source' => $o['name'], 'source_people' => (int) $o['count'], 'country' => strtoupper( (string) ( $a['country'] ?? 'TR' ) ), 'ratio' => (int) ( $a['ratio'] ?? 1 ) );
			},
			'run'         => static function ( $a ) {
				$r = FLXADS_Audiences::lookalike( (string) $a['id'], (string) ( $a['country'] ?? 'TR' ), (float) ( $a['ratio'] ?? 1 ) );
				if ( is_wp_error( $r ) ) {
					throw new Exception( $r->get_error_message() );
				}
				return $r;
			},
		),
		'flxads_audience_sync'     => array(
			'kind'        => 'write',
			'title'       => __( 'Update a Meta audience now', 'flx-ads' ),
			'description' => 'Sync one custom audience now (normally daily): adds new consented people of the group, removes people who left it or withdrew consent. Returns added, removed, count.',
			'props'       => array( 'id' => array( 'type' => 'string' ) ),
			'required'    => array( 'id' ),
			'run'         => static function ( $a ) {
				$r = FLXADS_Audiences::sync( (string) ( $a['id'] ?? '' ) );
				if ( is_wp_error( $r ) ) {
					throw new Exception( $r->get_error_message() );
				}
				return $r;
			},
		),
		'flxads_audience_delete'   => array(
			'kind'        => 'destructive',
			'title'       => __( 'Delete a Meta audience', 'flx-ads' ),
			'description' => 'Delete an audience in Meta and in Flux Ads. Ad sets using it stop reaching these people.',
			'props'       => array( 'id' => array( 'type' => 'string' ) ),
			'required'    => array( 'id' ),
			'preview'     => static function ( $a ) {
				$o = FLXADS_Audiences::get( (string) ( $a['id'] ?? '' ) );
				if ( ! $o ) {
					throw new Exception( 'No audience with this id.' );
				}
				return array( 'delete' => array_intersect_key( $o, array_flip( array( 'id', 'name', 'meta_id', 'kind', 'count' ) ) ) );
			},
			'run'         => static function ( $a ) {
				$r = FLXADS_Audiences::delete( (string) ( $a['id'] ?? '' ) );
				if ( is_wp_error( $r ) ) {
					throw new Exception( $r->get_error_message() );
				}
				return array( 'deleted' => true );
			},
		),
		'flxads_campaigns'          => array(
			'kind'        => 'read',
			'title'       => __( 'Meta campaigns', 'flx-ads' ),
			'description' => 'Meta ad account campaigns with status, objective, daily/lifetime budget (account currency), spend, impressions, link clicks, CTR for the period, and what they brought on this site (income in base currency, payments, new people, ROAS, cost per payment; matched by utm_campaign name or ID). range: 7 | 30 | 90 days.',
			'props'       => array( 'range' => array( 'type' => 'string', 'enum' => array( '7', '30', '90' ) ) ),
			'run'         => static function ( $a ) {
				$r = FLXADS_Manager::campaigns( (string) ( $a['range'] ?? '7' ) );
				if ( is_wp_error( $r ) ) {
					throw new Exception( $r->get_error_message() );
				}
				return $r;
			},
		),
		'flxads_campaign_detail'    => array(
			'kind'        => 'read',
			'title'       => __( 'Meta campaign details', 'flx-ads' ),
			'description' => 'One Meta campaign: its ad sets (status, budget, targeting summary: ages, gender, places, audiences) with their ads (status, creative title/body) and metrics, plus spend breakdowns by age+gender and by placement for the period.',
			'props'       => array(
				'id'    => array( 'type' => 'string', 'description' => 'Campaign ID from flxads_campaigns.' ),
				'range' => array( 'type' => 'string', 'enum' => array( '7', '30', '90' ) ),
			),
			'required'    => array( 'id' ),
			'run'         => static function ( $a ) {
				$r = FLXADS_Manager::campaign( (string) ( $a['id'] ?? '' ), (string) ( $a['range'] ?? '7' ) );
				if ( is_wp_error( $r ) ) {
					throw new Exception( $r->get_error_message() );
				}
				return $r;
			},
		),
		'flxads_ad_status'          => array(
			'kind'        => 'destructive',
			'title'       => __( 'Pause or turn on in Meta', 'flx-ads' ),
			'description' => 'Pause or turn on a Meta campaign, ad set or ad (id from flxads_campaigns / flxads_campaign_detail). status: PAUSED | ACTIVE. Takes effect in Meta at once and is written to the change history. Never turn something on without the user asking for it.',
			'props'       => array(
				'id'     => array( 'type' => 'string' ),
				'status' => array( 'type' => 'string', 'enum' => array( 'PAUSED', 'ACTIVE' ) ),
			),
			'required'    => array( 'id', 'status' ),
			'preview'     => static function ( $a ) {
				$o = FLXADS_Manager::object( (string) ( $a['id'] ?? '' ) );
				if ( is_wp_error( $o ) ) {
					throw new Exception( $o->get_error_message() );
				}
				return array( 'item' => $o['name'], 'id' => $o['id'], 'from' => $o['status'], 'to' => strtoupper( (string) ( $a['status'] ?? '' ) ) );
			},
			'run'         => static function ( $a, $channel = 'mcp' ) {
				$r = FLXADS_Manager::set_status( (string) ( $a['id'] ?? '' ), (string) ( $a['status'] ?? '' ), $channel );
				if ( is_wp_error( $r ) ) {
					throw new Exception( $r->get_error_message() );
				}
				return $r;
			},
		),
		'flxads_ad_budget'          => array(
			'kind'        => 'destructive',
			'title'       => __( 'Change daily budget in Meta', 'flx-ads' ),
			'description' => 'Set the daily budget of a Meta campaign or ad set that has a daily budget, in whole units of the ad account currency (e.g. 750 = 750 TRY a day). Takes effect in Meta at once and is written to the change history. Large jumps reset Meta\'s learning; prefer steps of at most 20-30%.',
			'props'       => array(
				'id'     => array( 'type' => 'string' ),
				'amount' => array( 'type' => 'number', 'minimum' => 1 ),
			),
			'required'    => array( 'id', 'amount' ),
			'preview'     => static function ( $a ) {
				$o = FLXADS_Manager::object( (string) ( $a['id'] ?? '' ) );
				if ( is_wp_error( $o ) ) {
					throw new Exception( $o->get_error_message() );
				}
				if ( null === $o['daily'] ) {
					throw new Exception( 'This item has no daily budget (lifetime budget, or the budget is set on the campaign / ad sets).' );
				}
				$to  = round( (float) ( $a['amount'] ?? 0 ), 2 );
				$pct = FLXADS_Manager::change_pct( $o['daily'], $to );
				return array( 'item' => $o['name'], 'id' => $o['id'], 'daily_budget_from' => $o['daily'], 'daily_budget_to' => $to, 'currency' => FLXADS_Manager::currency(), 'change_percent' => $pct, 'warning' => null !== $pct && abs( $pct ) > 30 ? 'Change above 30%: Meta may restart the learning phase.' : '' );
			},
			'run'         => static function ( $a, $channel = 'mcp' ) {
				$r = FLXADS_Manager::set_budget( (string) ( $a['id'] ?? '' ), (float) ( $a['amount'] ?? 0 ), $channel );
				if ( is_wp_error( $r ) ) {
					throw new Exception( $r->get_error_message() );
				}
				return $r;
			},
		),
		'flxads_suggestions'        => array(
			'kind'        => 'read',
			'title'       => __( 'Ad suggestions', 'flx-ads' ),
			'description' => 'Suggestions for active Meta campaigns from the last 7 days: pause a campaign that spends without payments, lower or raise a daily budget by 20% by ROAS against the target, pause an ad with a much lower click rate than its ad set, new text when frequency is high. Each has id, type, reason, the exact action and status (open|applied|dismissed|gone). Nothing is applied automatically. refresh=true checks Meta again first.',
			'props'       => array(
				'status'  => array( 'type' => 'string', 'enum' => array( 'open', 'all' ) ),
				'refresh' => array( 'type' => 'boolean' ),
			),
			'run'         => static function ( $a ) {
				if ( ! empty( $a['refresh'] ) ) {
					$r = FLXADS_Optimizer::refresh();
					if ( is_wp_error( $r ) ) {
						throw new Exception( $r->get_error_message() );
					}
				}
				$list = 'all' === ( $a['status'] ?? 'open' ) ? FLXADS_Optimizer::all() : FLXADS_Optimizer::open();
				return array(
					'checked'     => FLXADS_Optimizer::last_run(),
					'suggestions' => array_map( array( 'FLXADS_Optimizer', 'export' ), array_slice( $list, 0, 50 ) ),
				);
			},
		),
		'flxads_suggestion_apply'   => array(
			'kind'        => 'destructive',
			'title'       => __( 'Apply an ad suggestion', 'flx-ads' ),
			'description' => 'Apply one open suggestion from flxads_suggestions: the pause or budget change goes to Meta at once and is written to the change history. Only when the user asks for this suggestion.',
			'props'       => array( 'id' => array( 'type' => 'string' ) ),
			'required'    => array( 'id' ),
			'preview'     => static function ( $a ) {
				$s = FLXADS_Optimizer::get( (string) ( $a['id'] ?? '' ) );
				if ( ! $s || 'open' !== $s['status'] ) {
					throw new Exception( 'Suggestion not found or already closed.' );
				}
				return FLXADS_Optimizer::export( $s );
			},
			'run'         => static function ( $a, $channel = 'mcp' ) {
				$r = FLXADS_Optimizer::apply( (string) ( $a['id'] ?? '' ), $channel );
				if ( is_wp_error( $r ) ) {
					throw new Exception( $r->get_error_message() );
				}
				return $r;
			},
		),
		'flxads_suggestion_dismiss' => array(
			'kind'        => 'write',
			'title'       => __( 'Dismiss an ad suggestion', 'flx-ads' ),
			'description' => 'Dismiss an open suggestion (nothing changes in Meta). It does not come back for 14 days. Optional short note why.',
			'props'       => array(
				'id'   => array( 'type' => 'string' ),
				'note' => array( 'type' => 'string' ),
			),
			'required'    => array( 'id' ),
			'run'         => static function ( $a, $channel = 'mcp' ) {
				$r = FLXADS_Optimizer::dismiss( (string) ( $a['id'] ?? '' ), $channel, (string) ( $a['note'] ?? '' ) );
				if ( is_wp_error( $r ) ) {
					throw new Exception( $r->get_error_message() );
				}
				return array( 'id' => (string) $a['id'], 'dismissed' => true );
			},
		),
		'flxads_season_plan'        => array(
			'kind'        => 'read',
			'title'       => __( 'Season plan', 'flx-ads' ),
			'description' => 'Giving seasons in the next N days (Ramadan, Qurban, 11 November tree day, Giving Tuesday, year end, the site\'s own dates...): dates, state now|prep|later, days left, preparation days, and what the same period brought last year (payments, income, new donors, ad spend). Use it to plan campaigns; write the draft with flxads_draft_save. Hijri dates are approximate.',
			'props'       => array( 'days' => array( 'type' => 'integer', 'minimum' => 7, 'maximum' => 365 ) ),
			'run'         => static function ( $a ) {
				return array( 'today' => wp_date( 'Y-m-d' ), 'seasons' => array_map( static function ( $e ) { return $e + array( 'brief' => FLXADS_Planner::brief( $e ) ); }, FLXADS_Planner::upcoming( (int) ( $a['days'] ?? 180 ) ?: 180 ) ) );
			},
		),
		'flxads_season_add'         => array(
			'kind'        => 'write',
			'title'       => __( 'Add a season date', 'flx-ads' ),
			'description' => 'Add the site\'s own campaign date to the season plan (anniversary, event, planting day). Local only.',
			'props'       => array(
				'name'  => array( 'type' => 'string' ),
				'start' => array( 'type' => 'string', 'description' => 'Y-m-d' ),
				'end'   => array( 'type' => 'string', 'description' => 'Y-m-d, optional' ),
				'prep'  => array( 'type' => 'integer', 'description' => 'Days of preparation before start (3-60, default 14).' ),
			),
			'required'    => array( 'name', 'start' ),
			'run'         => static function ( $a ) {
				$r = FLXADS_Planner::add_custom( $a );
				if ( is_wp_error( $r ) ) {
					throw new Exception( $r->get_error_message() );
				}
				return $r;
			},
		),
		'flxads_season_delete'      => array(
			'kind'        => 'write',
			'title'       => __( 'Delete a season date', 'flx-ads' ),
			'description' => 'Delete one of the site\'s own dates (id from flxads_season_plan, custom ones only).',
			'props'       => array( 'id' => array( 'type' => 'string' ) ),
			'required'    => array( 'id' ),
			'run'         => static function ( $a ) {
				$r = FLXADS_Planner::delete_custom( (string) ( $a['id'] ?? '' ) );
				if ( is_wp_error( $r ) ) {
					throw new Exception( $r->get_error_message() );
				}
				return array( 'deleted' => true );
			},
		),
		'flxads_tests'              => array(
			'kind'        => 'read',
			'title'       => __( 'Ad tests', 'flx-ads' ),
			'description' => 'A/B tests (two Meta ads of one campaign, payments per click on the site, or click rate while payments are few) and holdout tests (share of an audience kept out of Meta; lift in giving rate, extra donors and income). Each with result, confidence (95% needed) and a plain verdict.',
			'props'       => array(),
			'run'         => static function () {
				return array( 'tests' => array_map( array( 'FLXADS_Experiments', 'export' ), FLXADS_Experiments::all() ) );
			},
		),
		'flxads_test_create'        => array(
			'kind'        => 'destructive',
			'title'       => __( 'Start an ad test', 'flx-ads' ),
			'description' => 'Start a test. type=ab: campaign (Meta campaign id) and two ad ids a, b of that campaign (from flxads_campaign_detail); nothing changes in Meta. type=holdout: audience (Flux Ads audience id, a target audience from flxads_audiences_list) and pct 5-50; the held-out people are removed from the Meta audience at once and put back when it ends. start/end Y-m-d (holdout: at least 4 weeks).',
			'props'       => array(
				'type'     => array( 'type' => 'string', 'enum' => array( 'ab', 'holdout' ) ),
				'name'     => array( 'type' => 'string' ),
				'campaign' => array( 'type' => 'string' ),
				'a'        => array( 'type' => 'string' ),
				'b'        => array( 'type' => 'string' ),
				'audience' => array( 'type' => 'string' ),
				'pct'      => array( 'type' => 'integer', 'minimum' => 5, 'maximum' => 50 ),
				'start'    => array( 'type' => 'string' ),
				'end'      => array( 'type' => 'string' ),
			),
			'required'    => array( 'type' ),
			'preview'     => static function ( $a ) {
				if ( 'holdout' === ( $a['type'] ?? '' ) ) {
					$aud = FLXADS_Audiences::get( (string) ( $a['audience'] ?? '' ) );
					if ( ! $aud ) {
						throw new Exception( 'Audience not found.' );
					}
					return array( 'type' => 'holdout', 'audience' => $aud['name'], 'people' => (int) $aud['count'], 'held_out_percent' => (int) ( $a['pct'] ?? 10 ), 'effect' => 'About ' . (int) round( (int) $aud['count'] * (int) ( $a['pct'] ?? 10 ) / 100 ) . ' people are removed from the Meta audience until the test ends.' );
				}
				return array( 'type' => 'ab', 'campaign' => (string) ( $a['campaign'] ?? '' ), 'a' => (string) ( $a['a'] ?? '' ), 'b' => (string) ( $a['b'] ?? '' ), 'effect' => 'Nothing changes in Meta; results are read from Meta and this site.' );
			},
			'run'         => static function ( $a, $channel = 'mcp' ) {
				$r = FLXADS_Experiments::create( $a, $channel );
				if ( is_wp_error( $r ) ) {
					throw new Exception( $r->get_error_message() );
				}
				return FLXADS_Experiments::export( $r );
			},
		),
		'flxads_test_end'           => array(
			'kind'        => 'destructive',
			'title'       => __( 'End an ad test', 'flx-ads' ),
			'description' => 'End a running test and keep its result. For a holdout test the held-out people go back into the Meta audience.',
			'props'       => array( 'id' => array( 'type' => 'string' ) ),
			'required'    => array( 'id' ),
			'preview'     => static function ( $a ) {
				$e = FLXADS_Experiments::get( (string) ( $a['id'] ?? '' ) );
				if ( ! $e ) {
					throw new Exception( 'Test not found.' );
				}
				return FLXADS_Experiments::export( $e );
			},
			'run'         => static function ( $a ) {
				$r = FLXADS_Experiments::finish( (string) ( $a['id'] ?? '' ) );
				if ( is_wp_error( $r ) ) {
					throw new Exception( $r->get_error_message() );
				}
				return FLXADS_Experiments::export( $r );
			},
		),
		'flxads_test_delete'        => array(
			'kind'        => 'destructive',
			'title'       => __( 'Delete an ad test', 'flx-ads' ),
			'description' => 'Delete a test and its result (a running holdout is ended first).',
			'props'       => array( 'id' => array( 'type' => 'string' ) ),
			'required'    => array( 'id' ),
			'preview'     => static function ( $a ) {
				$e = FLXADS_Experiments::get( (string) ( $a['id'] ?? '' ) );
				if ( ! $e ) {
					throw new Exception( 'Test not found.' );
				}
				return array( 'delete' => $e['name'], 'type' => $e['type'], 'status' => $e['status'] );
			},
			'run'         => static function ( $a ) {
				$r = FLXADS_Experiments::delete( (string) ( $a['id'] ?? '' ) );
				if ( is_wp_error( $r ) ) {
					throw new Exception( $r->get_error_message() );
				}
				return array( 'deleted' => true );
			},
		),
		'flxads_studio_context'     => array(
			'kind'        => 'read',
			'title'       => __( 'Campaign planning context', 'flx-ads' ),
			'description' => 'What a campaign draft should be based on: last 90 days campaigns from this site (payments, income, new donors, spend, ROAS, cost per payment), Meta audiences (ids to use in ad sets), Flux CRM size and value groups, account currency, allowed objectives and buttons. No personal data.',
			'props'       => array(),
			'run'         => static function () {
				return FLXADS_Studio::context() + array( 'missing_for_send' => FLXADS_Studio::missing() );
			},
		),
		'flxads_drafts'             => array(
			'kind'        => 'read',
			'title'       => __( 'Campaign drafts', 'flx-ads' ),
			'description' => 'List Studio campaign drafts (id, name, status draft|sent|error, objective, daily budget, ad sets, ads, notes, Meta ids when sent). Give id for one draft only.',
			'props'       => array( 'id' => array( 'type' => 'string' ) ),
			'run'         => static function ( $a ) {
				if ( ! empty( $a['id'] ) ) {
					$d = FLXADS_Studio::get( (string) $a['id'] );
					if ( ! $d ) {
						throw new Exception( 'Draft not found.' );
					}
					return $d;
				}
				return array( 'drafts' => array_values( FLXADS_Studio::all() ) );
			},
		),
		'flxads_draft_save'         => array(
			'kind'        => 'write',
			'title'       => __( 'Save a campaign draft', 'flx-ads' ),
			'description' => 'Create or update a campaign DRAFT in Flux Ads › Studio. Nothing goes to Meta: the user reads and edits it, then sends it (always paused). Call flxads_studio_context first. objective: OUTCOME_SALES | OUTCOME_TRAFFIC | OUTCOME_AWARENESS. daily_budget in the ad account currency. 1-3 ad sets {name, age_min, age_max 18-65, gender all|male|female, countries [ISO-2], audiences [audience ids], exclude [audience ids], share percent}. 2-4 ads {name, headline <=40 chars, text <=300 chars, description, cta DONATE_NOW|LEARN_MORE|SHOP_NOW|SIGN_UP, image url optional}; each ad runs in every ad set. notes: why this plan, in the site language. Write texts in the site language, honest, within Meta ad policy.',
			'props'       => array(
				'id'           => array( 'type' => 'string', 'description' => 'Existing draft to update; omit to create.' ),
				'name'         => array( 'type' => 'string' ),
				'objective'    => array( 'type' => 'string', 'enum' => array_keys( FLXADS_Studio::objectives() ) ),
				'link'         => array( 'type' => 'string' ),
				'daily_budget' => array( 'type' => 'number' ),
				'start'        => array( 'type' => 'string', 'description' => 'YYYY-MM-DD or empty' ),
				'end'          => array( 'type' => 'string', 'description' => 'YYYY-MM-DD or empty' ),
				'adsets'       => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				'ads'          => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				'notes'        => array( 'type' => 'string' ),
			),
			'required'    => array( 'name', 'link', 'daily_budget', 'adsets', 'ads' ),
			'run'         => static function ( $a, $channel = 'mcp' ) {
				$r = FLXADS_Studio::save( $a, (string) ( $a['id'] ?? '' ), $channel );
				if ( is_wp_error( $r ) ) {
					throw new Exception( $r->get_error_message() );
				}
				return array( 'id' => $r, 'status' => 'draft', 'url' => flxads_admin_url( 'studio', array( 'edit' => $r ) ), 'next' => 'Tell the user the draft is ready to review in Flux Ads › Studio. Do not send it to Meta unless they ask.' );
			},
		),
		'flxads_draft_send'         => array(
			'kind'        => 'destructive',
			'title'       => __( 'Create draft in Meta (paused)', 'flx-ads' ),
			'description' => 'Create a saved Studio draft in Meta: campaign, ad sets, creatives and ads, all PAUSED (nothing is shown or spent). The user turns it on later under Campaigns. Only when the user asks.',
			'props'       => array( 'id' => array( 'type' => 'string' ) ),
			'required'    => array( 'id' ),
			'preview'     => static function ( $a ) {
				$d = FLXADS_Studio::get( (string) ( $a['id'] ?? '' ) );
				if ( ! $d ) {
					throw new Exception( 'Draft not found.' );
				}
				return array( 'create_paused' => $d['name'], 'objective' => $d['objective'], 'daily_budget' => $d['daily_budget'], 'adsets' => count( $d['adsets'] ), 'ads' => count( $d['adsets'] ) * count( $d['ads'] ), 'missing' => FLXADS_Studio::missing() );
			},
			'run'         => static function ( $a, $channel = 'mcp' ) {
				$r = FLXADS_Studio::send( (string) ( $a['id'] ?? '' ), $channel );
				if ( is_wp_error( $r ) ) {
					throw new Exception( $r->get_error_message() );
				}
				return $r;
			},
		),
		'flxads_draft_delete'       => array(
			'kind'        => 'destructive',
			'title'       => __( 'Delete a campaign draft', 'flx-ads' ),
			'description' => 'Delete a Studio draft from the site. A campaign already created in Meta stays there (paused or as the user left it).',
			'props'       => array( 'id' => array( 'type' => 'string' ) ),
			'required'    => array( 'id' ),
			'preview'     => static function ( $a ) {
				$d = FLXADS_Studio::get( (string) ( $a['id'] ?? '' ) );
				if ( ! $d ) {
					throw new Exception( 'Draft not found.' );
				}
				return array( 'delete_draft' => $d['name'], 'status' => $d['status'] );
			},
			'run'         => static function ( $a ) {
				return array( 'deleted' => FLXADS_Studio::delete( (string) ( $a['id'] ?? '' ) ) );
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
	$out = call_user_func( $t['run'], $args, $channel );
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
		return $text . "\n\nFlux Ads: use flxads_report for which ad brought which donation (with spend and ROAS when known). Never ask the user to paste access tokens or API secrets into the chat; they enter them in Flux Ads › Settings. Before changing settings call flxads_settings_get. Add manual spend with the same campaign name as utm_campaign. For which campaign brings donors who keep giving (LTV, CAC, payback) use flxads_value_report. Meta audiences only ever contain people with ad consent; list sources with flxads_audiences_list before creating one. For Meta campaigns, ad sets and ads use flxads_campaigns and flxads_campaign_detail; pausing, turning on and budget changes (flxads_ad_status, flxads_ad_budget) only when the user asks, one item at a time, budget steps of at most 20-30%. To plan a new campaign: flxads_studio_context, then flxads_draft_save (a draft only); the user reviews it in Flux Ads › Studio. Send it to Meta (paused) with flxads_draft_send only when the user asks. For what to change in running campaigns use flxads_suggestions; apply one (flxads_suggestion_apply) only when the user picks it. For upcoming giving seasons use flxads_season_plan and draft early. To learn what works, flxads_tests shows A/B and holdout results; start or end a test only when the user asks.";
	}
);
