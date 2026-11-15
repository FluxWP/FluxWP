<?php
/**
 * Screen facts, counts, dashboard, settings, network apps, activity log, media and the scheduler.
 * REST, GraphQL and MCP use the same methods. Keys and tokens never leave the server.
 *
 * @package flx-social
 */

defined( 'ABSPATH' ) || exit;

final class FLXSM_Admin_Service {

	public const CACHE_TAG = 'flx-social:settings';

	/**
	 * What the screen needs to start.
	 */
	public static function config(): FLXSM_Config {
		$s = flxsm_settings();
		return new FLXSM_Config(
			get_current_user_id(),
			wp_get_current_user()->display_name,
			flxsm_can_publish(),
			flxsm_can_manage(),
			current_user_can( 'upload_files' ),
			FLXSM_Accounts_Service::networks(),
			(bool) flxsm_ai_ready(),
			function_exists( 'flxst_table' ),
			(bool) $s['approval'],
			FLXSM_Cast::str( $s['signature'] ),
			FLXSM_Cast::str( $s['x_tier'] ),
			wp_timezone_string(),
			(int) wp_timezone()->getOffset( new DateTime( 'now' ) ),
			(int) get_option( 'start_of_week', 1 ),
			FLXSM_VERSION
		);
	}

	/**
	 * Numbers for the sidebar; "due" is how many posts wait for the scheduler.
	 */
	public static function counts(): FLXSM_Counts {
		$c = FLXSM_Cast::arr( flxsm_counts() );
		return new FLXSM_Counts(
			FLXSM_Cast::int( $c['pending'] ?? 0 ),
			FLXSM_Cast::int( $c['scheduled'] ?? 0 ),
			FLXSM_Cast::int( $c['failed'] ?? 0 ),
			(int) flxsm_due_count( 0 )
		);
	}

	/**
	 * Runs the scheduler for a moment (the open panel keeps posts moving).
	 */
	public static function tick(): FLXSM_Tick_Result {
		ignore_user_abort( true );
		$n = (int) flxsm_tick( 20, 'panel' );
		return new FLXSM_Tick_Result( $n, (int) flxsm_due_count() );
	}

	/**
	 * Dashboard for 7, 30 or 90 days.
	 *
	 * @param int $days Period.
	 */
	public static function stats( int $days ): FLXSM_Stats {
		$d = FLXSM_Cast::arr( flxsm_dashboard( $days ) );
		return self::stats_dto( $d );
	}

	/**
	 * Reads the networks' numbers now.
	 *
	 * @return array{updated:int,stats:FLXSM_Stats}
	 */
	public static function refresh_stats(): array {
		$n = (int) flxsm_collect_metrics( 25 );
		return array(
			'updated' => $n,
			'stats'   => self::stats( 30 ),
		);
	}

	/**
	 * Settings of the screen (managers only).
	 */
	public static function settings(): FLXSM_Settings {
		$s    = flxsm_settings();
		$a    = FLXSM_Cast::arr( $s['auto'] );
		$last = FLXSM_Cast::arr( get_option( 'flxsm_last_tick', array() ) );
		$pts  = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
			$pts[] = new FLXSM_Choice( $pt->name, FLXSM_Cast::str( $pt->labels->singular_name ) );
		}
		$tiers = array();
		foreach ( FLXSM_X::tiers() as $id => $label ) {
			$tiers[] = new FLXSM_Choice( (string) $id, $label );
		}
		return new FLXSM_Settings(
			(bool) $s['approval'],
			FLXSM_Cast::str( $s['signature'] ),
			(bool) $s['utm'],
			FLXSM_Cast::str( $s['meta_version'] ),
			FLXSM_Cast::str( $s['x_tier'] ),
			(bool) $s['x_premium'],
			FLXSM_Cast::int( $s['x_month_limit'] ),
			FLXSM_Cast::str( $s['tiktok_mode'] ),
			(bool) $s['pin_sandbox'],
			(bool) $s['li_org'],
			FLXSM_Cast::int( $s['wa_conn'] ),
			new FLXSM_Auto(
				(bool) ( $a['enabled'] ?? false ),
				FLXSM_Cast::strings( $a['post_types'] ?? array() ),
				FLXSM_Cast::ints( $a['accounts'] ?? array() ),
				FLXSM_Cast::str( $a['template'] ?? '' ),
				FLXSM_Cast::str( $a['mode'] ?? 'draft' ),
				FLXSM_Cast::int( $a['delay'] ?? 10 ),
				(bool) ( $a['image'] ?? false )
			),
			$pts,
			$tiers,
			self::wa_choices(),
			(int) flxsm_x_month_count(),
			FLXSM_Cast::int( $last['any'] ?? 0 ),
			self::tick_url( true )
		);
	}

	/**
	 * Validates and saves settings. Keys left out stay as they are.
	 *
	 * @param array<string,mixed> $in approval, utm, x_premium, pin_sandbox, li_org, x_month_limit, wa_conn, signature, meta_version, x_tier, tiktok_mode, auto.
	 */
	public static function save_settings( array $in ): void {
		$patch = array();
		foreach ( array( 'approval', 'utm', 'x_premium', 'pin_sandbox', 'li_org' ) as $k ) {
			if ( isset( $in[ $k ] ) ) {
				$patch[ $k ] = $in[ $k ] ? 1 : 0;
			}
		}
		foreach ( array( 'x_month_limit', 'wa_conn' ) as $k ) {
			if ( isset( $in[ $k ] ) ) {
				$patch[ $k ] = absint( $in[ $k ] );
			}
		}
		if ( isset( $in['signature'] ) ) {
			$patch['signature'] = sanitize_textarea_field( FLXSM_Cast::str( $in['signature'] ) );
		}
		if ( isset( $in['meta_version'] ) && 1 === preg_match( '/^v\d+\.\d+$/', FLXSM_Cast::str( $in['meta_version'] ) ) ) {
			$patch['meta_version'] = FLXSM_Cast::str( $in['meta_version'] );
		}
		if ( isset( $in['x_tier'] ) && isset( FLXSM_X::tiers()[ FLXSM_Cast::str( $in['x_tier'] ) ] ) ) {
			$patch['x_tier'] = FLXSM_Cast::str( $in['x_tier'] );
		}
		if ( isset( $in['tiktok_mode'] ) ) {
			$patch['tiktok_mode'] = 'direct' === $in['tiktok_mode'] ? 'direct' : 'inbox';
		}
		if ( isset( $in['auto'] ) && is_array( $in['auto'] ) ) {
			$a             = $in['auto'];
			$patch['auto'] = array(
				'enabled'    => ! empty( $a['enabled'] ) ? 1 : 0,
				'post_types' => array_values( array_filter( array_map( 'sanitize_key', FLXSM_Cast::strings( $a['post_types'] ?? array() ) ) ) ),
				'accounts'   => array_values( array_filter( array_map( 'absint', FLXSM_Cast::ints( $a['accounts'] ?? array() ) ) ) ),
				'template'   => sanitize_textarea_field( FLXSM_Cast::str( $a['template'] ?? '' ) ),
				'mode'       => 'schedule' === ( $a['mode'] ?? '' ) ? 'schedule' : 'draft',
				'delay'      => min( 1440, absint( $a['delay'] ?? 10 ) ),
				'image'      => ! empty( $a['image'] ) ? 1 : 0,
			);
		}
		flxsm_save_settings( $patch );
	}

	/**
	 * New key for the external scheduler address.
	 */
	public static function regenerate_tick_key(): void {
		update_option( 'flxsm_tick_key', wp_generate_password( 32, false ), false );
	}

	/**
	 * Address an external cron calls. The key is hidden unless asked for.
	 *
	 * @param bool $masked Hide the key.
	 */
	public static function tick_url( bool $masked ): string {
		$key = FLXSM_Cast::str( get_option( 'flxsm_tick_key', '' ) );
		return add_query_arg( 'key', $masked ? '' : $key, rest_url( FLXSM_NS . '/tick' ) );
	}

	/**
	 * Network apps (Meta, Google, X …) with which fields are filled; secret values are never returned.
	 *
	 * @return list<FLXSM_App>
	 */
	public static function apps(): array {
		$out = array();
		foreach ( FLXSM_Cast::rows( flxsm_apps_public() ) as $a ) {
			$fields = array();
			foreach ( FLXSM_Cast::rows( $a['fields'] ?? array() ) as $f ) {
				$fields[] = new FLXSM_App_Field(
					FLXSM_Cast::str( $f['key'] ?? '' ),
					FLXSM_Cast::str( $f['label'] ?? '' ),
					FLXSM_Cast::bool( $f['secret'] ?? false ),
					FLXSM_Cast::str( $f['value'] ?? '' ),
					FLXSM_Cast::bool( $f['set'] ?? false ),
					FLXSM_Cast::str( $f['placeholder'] ?? '' ),
					FLXSM_Cast::bool( $f['optional'] ?? false )
				);
			}
			$connect = array();
			foreach ( FLXSM_Cast::rows( $a['connect'] ?? array() ) as $c ) {
				$connect[] = new FLXSM_App_Connect(
					FLXSM_Cast::str( $c['for'] ?? '' ),
					FLXSM_Cast::str( $c['label'] ?? '' ),
					FLXSM_Cast::str( $c['provider'] ?? '' ),
					FLXSM_Cast::bool( $c['local'] ?? false )
				);
			}
			$out[] = new FLXSM_App(
				FLXSM_Cast::str( $a['id'] ?? '' ),
				FLXSM_Cast::str( $a['label'] ?? '' ),
				FLXSM_Cast::str( $a['console'] ?? '' ),
				$fields,
				FLXSM_Cast::str( $a['redirect'] ?? '' ),
				FLXSM_Cast::str( $a['help'] ?? '' ),
				$connect,
				FLXSM_Cast::bool( $a['ready'] ?? false )
			);
		}
		return $out;
	}

	/**
	 * Saves the fields of one app. An empty secret keeps the old one; "__clear__" removes it.
	 *
	 * @param string               $id     App ID.
	 * @param array<string,mixed> $values Field key → value.
	 * @return true|WP_Error
	 */
	public static function save_app( string $id, array $values ) {
		$res = flxsm_save_app( sanitize_key( $id ), $values );
		return is_wp_error( $res ) ? $res : true;
	}

	/**
	 * Latest activity log rows.
	 *
	 * @param int $limit Rows (at most 200).
	 * @return list<FLXSM_Log_Row>
	 */
	public static function log( int $limit = 200 ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxsm_table( 'log' ) . ' ORDER BY id DESC LIMIT %d', max( 1, min( 200, $limit ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$out  = array();
		foreach ( FLXSM_Cast::rows( $rows ) as $r ) {
			$out[] = new FLXSM_Log_Row(
				FLXSM_Cast::int( $r['id'] ?? 0 ),
				FLXSM_Cast::int( $r['ts'] ?? 0 ),
				FLXSM_Cast::str( $r['level'] ?? 'info' ),
				FLXSM_Cast::int( $r['account_id'] ?? 0 ),
				FLXSM_Cast::str( $r['msg'] ?? '' )
			);
		}
		return $out;
	}

	/**
	 * Images and videos of the media library.
	 *
	 * @param string $q    Search words.
	 * @param int    $page Page (40 per page).
	 */
	public static function media_page( string $q, int $page ): FLXSM_Media_Page {
		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => array( 'image', 'video' ),
				'posts_per_page' => 40,
				'paged'          => max( 1, $page ),
				's'              => $q,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		return new FLXSM_Media_Page( FLXSM_Media::list_from( flxsm_media_public( FLXSM_Cast::ints( $query->posts ) ) ), (int) $query->max_num_pages );
	}

	/**
	 * Media by ID.
	 *
	 * @param list<int> $ids Attachment IDs.
	 * @return list<FLXSM_Media>
	 */
	public static function media_by_ids( array $ids ): array {
		return FLXSM_Media::list_from( flxsm_media_public( $ids ) );
	}

	/**
	 * Adds a file to the media library from an address on the internet.
	 *
	 * @param string $url    File address.
	 * @param int    $poster Cover image attachment (video).
	 * @return FLXSM_Media|WP_Error
	 */
	public static function media_import( string $url, int $poster = 0 ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission to upload files.', 'flx-social' ), array( 'status' => 403 ) );
		}
		self::media_includes();
		$url = esc_url_raw( $url );
		$id  = media_sideload_image( $url, 0, null, 'id' );
		if ( is_wp_error( $id ) ) {
			$tmp = download_url( $url, 120 );
			if ( is_wp_error( $tmp ) ) {
				return new WP_Error( 'flxsm_media', $tmp->get_error_message(), array( 'status' => 400 ) );
			}
			$id = media_handle_sideload(
				array(
					'name'     => basename( FLXSM_Cast::str( wp_parse_url( $url, PHP_URL_PATH ) ) ),
					'tmp_name' => $tmp,
				),
				0
			);
		}
		return self::finish_media( $id, $poster );
	}

	/**
	 * Adds a file that was sent over the upload endpoint (context flx-social/media) to the media library.
	 *
	 * @param string $upload_id Upload ID.
	 * @param int    $poster    Cover image attachment (video).
	 * @return FLXSM_Media|WP_Error
	 */
	public static function media_from_upload( string $upload_id, int $poster = 0 ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission to upload files.', 'flx-social' ), array( 'status' => 403 ) );
		}
		$file = flx_gql_upload_take( $upload_id, 'flx-social/media' );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		self::media_includes();
		$id = media_handle_sideload(
			array(
				'name'     => FLXSM_Cast::str( $file['name'] ?? '' ),
				'tmp_name' => FLXSM_Cast::str( $file['path'] ?? '' ),
			),
			0
		);
		return self::finish_media( $id, $poster );
	}

	/**
	 * Finished items of Flux Studio the user may use.
	 *
	 * @return list<FLXSM_Studio_Item>
	 */
	public static function studio_media(): array {
		global $wpdb;
		if ( ! function_exists( 'flxst_table' ) ) {
			return array();
		}
		$t    = flxst_table( 'items' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, kind, url, thumb, title, prompt, attachment_id, width, height FROM {$t} WHERE state = 'done' AND url <> '' AND (user_id = %d OR %d = 1) ORDER BY id DESC LIMIT 60", get_current_user_id(), current_user_can( 'flxst_manage' ) ? 1 : 0 ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$out  = array();
		foreach ( FLXSM_Cast::rows( $rows ) as $r ) {
			$out[] = new FLXSM_Studio_Item(
				FLXSM_Cast::int( $r['id'] ?? 0 ),
				FLXSM_Cast::str( $r['kind'] ?? '' ),
				FLXSM_Cast::str( $r['url'] ?? '' ),
				FLXSM_Cast::str( $r['thumb'] ?? '' ),
				FLXSM_Cast::str( $r['title'] ?? '' ),
				FLXSM_Cast::str( $r['prompt'] ?? '' ),
				FLXSM_Cast::int( $r['attachment_id'] ?? 0 ),
				FLXSM_Cast::int( $r['width'] ?? 0 ),
				FLXSM_Cast::int( $r['height'] ?? 0 )
			);
		}
		return $out;
	}

	/**
	 * Evolution connections the manager can choose for WhatsApp.
	 *
	 * @return list<FLXSM_Choice>
	 */
	private static function wa_choices(): array {
		$out = array();
		foreach ( FLXSM_Cast::rows( flxsm_wa_choices() ) as $r ) {
			$out[] = new FLXSM_Choice( FLXSM_Cast::str( $r['id'] ?? '' ), FLXSM_Cast::str( $r['name'] ?? '' ) );
		}
		return $out;
	}

	private static function media_includes(): void {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	/**
	 * @param int|WP_Error $id     Attachment ID or error.
	 * @param int          $poster Cover image attachment.
	 * @return FLXSM_Media|WP_Error
	 */
	private static function finish_media( $id, int $poster ) {
		if ( is_wp_error( $id ) ) {
			return new WP_Error( 'flxsm_media', $id->get_error_message(), array( 'status' => 400 ) );
		}
		if ( $poster ) {
			update_post_meta( $id, '_flxsm_poster', $poster );
		}
		$item = flxsm_media_item( $id );
		if ( ! is_array( $item ) ) {
			return new WP_Error( 'flxsm_media', __( 'No file received.', 'flx-social' ), array( 'status' => 400 ) );
		}
		unset( $item['path'] );
		return FLXSM_Media::from_array( $item );
	}

	/**
	 * Legacy dashboard → typed dashboard.
	 *
	 * @param array<mixed> $d flxsm_dashboard() result.
	 */
	private static function stats_dto( array $d ): FLXSM_Stats {
		$t   = FLXSM_Cast::arr( $d['totals'] ?? array() );
		$suc = $t['success'] ?? null;
		$day = array();
		foreach ( FLXSM_Cast::rows( $d['by_day'] ?? array() ) as $pair ) {
			$pair   = array_values( $pair );
			$nets   = array();
			foreach ( FLXSM_Cast::arr( $pair[1] ?? array() ) as $prov => $count ) {
				$nets[] = new FLXSM_Day_Count( (string) $prov, FLXSM_Cast::int( $count ) );
			}
			$day[] = new FLXSM_Day( FLXSM_Cast::str( $pair[0] ?? '' ), $nets );
		}
		$byn = array();
		foreach ( FLXSM_Cast::rows( $d['by_net'] ?? array() ) as $n ) {
			$byn[] = new FLXSM_Net_Stat( FLXSM_Cast::str( $n['provider'] ?? '' ), FLXSM_Cast::int( $n['published'] ?? 0 ), FLXSM_Cast::int( $n['failed'] ?? 0 ), FLXSM_Cast::int( $n['engagement'] ?? 0 ), FLXSM_Cast::int( $n['views'] ?? 0 ) );
		}
		$fol = array();
		foreach ( FLXSM_Cast::rows( $d['followers'] ?? array() ) as $f ) {
			$series = array();
			foreach ( FLXSM_Cast::rows( $f['series'] ?? array() ) as $pt ) {
				$pt       = array_values( $pt );
				$series[] = new FLXSM_Follower_Point( FLXSM_Cast::str( $pt[0] ?? '' ), FLXSM_Cast::int( $pt[1] ?? 0 ) );
			}
			$fol[] = new FLXSM_Followers( FLXSM_Cast::int( $f['account_id'] ?? 0 ), FLXSM_Cast::str( $f['name'] ?? '' ), FLXSM_Cast::str( $f['provider'] ?? '' ), FLXSM_Cast::str( $f['avatar'] ?? '' ), FLXSM_Cast::int( $f['now'] ?? 0 ), FLXSM_Cast::int( $f['delta'] ?? 0 ), $series );
		}
		$top = array();
		foreach ( FLXSM_Cast::rows( $d['top'] ?? array() ) as $r ) {
			$top[] = new FLXSM_Top_Post( FLXSM_Cast::int( $r['target_id'] ?? 0 ), FLXSM_Cast::int( $r['post_id'] ?? 0 ), FLXSM_Cast::str( $r['provider'] ?? '' ), FLXSM_Cast::str( $r['account'] ?? '' ), FLXSM_Cast::str( $r['title'] ?? '' ), FLXSM_Cast::int( $r['engagement'] ?? 0 ), FLXSM_Cast::int( $r['views'] ?? 0 ), FLXSM_Cast::str( $r['url'] ?? '' ), FLXSM_Cast::int( $r['at'] ?? 0 ) );
		}
		$up = array();
		foreach ( FLXSM_Cast::rows( $d['upcoming'] ?? array() ) as $p ) {
			$up[] = FLXSM_Posts_Service::to_dto( $p );
		}
		$err = array();
		foreach ( FLXSM_Cast::rows( $d['errors'] ?? array() ) as $e ) {
			$err[] = new FLXSM_Dash_Error( FLXSM_Cast::int( $e['target_id'] ?? 0 ), FLXSM_Cast::int( $e['post_id'] ?? 0 ), FLXSM_Cast::str( $e['account'] ?? '' ), FLXSM_Cast::str( $e['provider'] ?? '' ), FLXSM_Cast::str( $e['error'] ?? '' ), FLXSM_Cast::int( $e['at'] ?? 0 ) );
		}
		return new FLXSM_Stats(
			FLXSM_Cast::int( $d['days'] ?? 30 ),
			new FLXSM_Totals(
				FLXSM_Cast::int( $t['published'] ?? 0 ),
				FLXSM_Cast::int( $t['failed'] ?? 0 ),
				FLXSM_Cast::int( $t['scheduled'] ?? 0 ),
				FLXSM_Cast::int( $t['pending'] ?? 0 ),
				FLXSM_Cast::int( $t['drafts'] ?? 0 ),
				FLXSM_Cast::int( $t['engagement'] ?? 0 ),
				FLXSM_Cast::int( $t['views'] ?? 0 ),
				FLXSM_Cast::int( $t['followers'] ?? 0 ),
				FLXSM_Cast::int( $t['followers_delta'] ?? 0 ),
				null === $suc ? null : FLXSM_Cast::int( $suc )
			),
			$day,
			$byn,
			$fol,
			$top,
			$up,
			$err,
			FLXSM_Cast::int( $d['last_metrics'] ?? 0 )
		);
	}
}
