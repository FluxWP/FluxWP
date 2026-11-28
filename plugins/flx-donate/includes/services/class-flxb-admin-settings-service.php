<?php
/**
 * Donation settings for the admin screen (GraphQL part b10): general, organisation, categories, tracking
 * words, extra tabs and the import of earlier payments. The tabs other plugins and modules add
 * (flxb_settings_tabs + flxb_settings_tab_<id>, the system check) stay their own PHP forms: the page
 * prints the open one hidden ("island") and the screen moves it into place.
 *
 * The wp-admin screen (packages/modules/src/donate/Settings.tsx) calls this class. Everything needs
 * flxb_can_manage().
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

final class FLXB_Admin_Settings_Service {

	/** The tabs the screen draws itself; every other tab is an island. */
	const OWN = array( 'general', 'organisation', 'categories', 'kurban', 'tabs', 'import' );

	/** @var array<int,array{id:string,html:string}>|null */
	private static $islands = null;

	/**
	 * @param mixed $v Value.
	 */
	private static function s( $v ): string {
		return is_scalar( $v ) ? (string) $v : '';
	}

	/**
	 * @param mixed $v Value.
	 * @return array<mixed>
	 */
	private static function arr( $v ): array {
		return is_array( $v ) ? $v : array();
	}

	/**
	 * @param mixed $v Value.
	 * @return array<string,mixed>
	 */
	private static function map( $v ): array {
		$out = array();
		foreach ( is_array( $v ) ? $v : array() as $k => $x ) {
			$out[ (string) $k ] = $x;
		}
		return $out;
	}

	public static function can(): bool {
		return (bool) flxb_can_manage();
	}

	/** @return array{message: string} */
	private static function ok(): array {
		return array( 'message' => __( 'Saved.', 'flx-donate' ) );
	}

	/** @return array<string,string> */
	private static function tabs(): array {
		$out = array();
		foreach ( self::arr( FLXB_Page_Settings::tabs() ) as $k => $l ) {
			$out[ sanitize_key( (string) $k ) ] = self::s( $l );
		}
		return $out;
	}

	/** The tab of the address (?tab=), general when unknown. */
	public static function current_tab(): string {
		$tab = sanitize_key( self::s( FLXB_Admin::q( 'tab', 'general' ) ) );
		return isset( self::tabs()[ $tab ] ) ? $tab : 'general';
	}

	/** The settings page is being printed: collect the island of its tab (before the shell prints). */
	public static function want_islands(): void {
		self::$islands = array();
		$tab           = self::current_tab();
		ob_start();
		if ( 'system' === $tab && class_exists( 'FLXB_System_Checks' ) ) {
			FLXB_System_Checks::render_tab();
		} elseif ( ! in_array( $tab, self::OWN, true ) ) {
			do_action( 'flxb_settings_tab_' . $tab );
		}
		$html = trim( self::s( ob_get_clean() ) );
		if ( '' !== $html ) {
			self::$islands[] = array(
				'id'   => 'settings-' . $tab,
				'html' => $html,
			);
		}
	}

	public static function print_islands(): void {
		if ( ! self::$islands ) {
			return;
		}
		echo '<div id="flxdn-settings-islands" hidden>';
		foreach ( self::$islands as $b ) {
			echo '<div class="flxb flxdn-island" data-flxdn-island="' . esc_attr( $b['id'] ) . '">' . $b['html'] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the tab's own output
		}
		echo '</div>';
	}

	/** Is the open settings tab one that PHP prints (the classic styles are needed for it)? */
	public static function island_tab(): bool {
		$tab = self::current_tab();
		return ! in_array( $tab, self::OWN, true );
	}

	/** @return array<string,mixed> */
	public static function settings(): array {
		$s    = self::map( flxb_settings() );
		$tabs = array();
		foreach ( self::tabs() as $k => $l ) {
			$tabs[] = array(
				'id'     => $k,
				'label'  => $l,
				'island' => ! in_array( $k, self::OWN, true ),
				'url'    => FLXB_Admin_Url::page( 'flxb-settings', array( 'tab' => $k ) ),
			);
		}
		$forms = array();
		foreach ( self::arr( flxb_forms() ) as $fid => $f ) {
			$f = self::arr( $f );
			if ( 'payment' === ( $f['type'] ?? '' ) ) {
				$forms[] = array(
					'value' => (string) (int) $fid,
					'label' => self::s( $f['title'] ?? '' ) . ' #' . (int) $fid,
				);
			}
		}
		$b    = flxb_brand();
		$logo = ! empty( $s['logo_id'] ) ? wp_get_attachment_image_url( (int) self::s( $s['logo_id'] ), 'medium' ) : '';

		/** @var wpdb $wpdb */
		global $wpdb;
		$used = array();
		foreach ( self::arr( $wpdb->get_results( 'SELECT category, COUNT(*) AS n FROM ' . self::s( flxb_table( 'types' ) ) . ' GROUP BY category', ARRAY_A ) ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
			$r                                   = self::arr( $r );
			$used[ self::s( $r['category'] ?? '' ) ] = (int) self::s( $r['n'] ?? 0 );
		}
		$cats = array();
		foreach ( self::arr( flxb_categories() ) as $k => $l ) {
			$cats[] = array(
				'key'   => (string) $k,
				'label' => self::s( $l ),
				'used'  => $used[ (string) $k ] ?? 0,
			);
		}

		$labels = self::arr( get_option( 'flxb_track_labels', array() ) );
		$def    = self::arr( FLXB_Track::default_labels() );
		$lf     = array();
		foreach ( self::arr( FLXB_Track::label_fields() ) as $k => $l ) {
			$lf[] = array(
				'key'         => (string) $k,
				'label'       => self::s( $l ),
				'value'       => self::s( $labels[ $k ] ?? '' ),
				'placeholder' => self::s( $def[ $k ] ?? '' ),
				'max'         => 'caption' === $k ? 300 : 80,
			);
		}
		$kurban   = (bool) flxb_kurban_enabled();
		$cur      = $kurban ? flxb_proxy_text( 0, '' ) : null;
		$versions = array();
		foreach ( $kurban ? array_reverse( self::arr( get_option( 'flxb_proxy_texts', array() ) ) ) : array() as $v ) {
			$v = self::arr( $v );
			/* translators: 1: version number, 2: date */
			$versions[] = sprintf( __( 'Version %1$s · %2$s', 'flx-donate' ), number_format_i18n( (int) self::s( $v['version'] ?? 0 ) ), self::s( flxb_date( $v['created_at'] ?? '' ) ) );
		}

		$own   = FLXB_Detail_Tabs::custom();
		$built = self::arr( FLXB_Detail_Tabs::builtin() );
		$inuse = array();
		foreach ( self::arr( FLXB_Detail_Tabs::all() ) as $id => $t ) {
			$t       = self::arr( $t );
			$inuse[] = array(
				'id'       => (string) $id,
				'title'    => self::s( FLXB_Reports::text( $t['title'] ?? '' ) ),
				'donation' => 'donation' === ( $t['on'] ?? '' ),
				'fields'   => count( self::arr( $t['fields'] ?? array() ) ),
				'builtin'  => isset( $built[ $id ] ),
			);
		}
		$ex = array(
			array(
				'id'         => 'delivery',
				'title'      => array(
					'tr' => 'Teslim',
					'en' => 'Delivery',
				),
				'on'         => 'item',
				'type_slugs' => array(),
				'fields'     => array(
					array(
						'key'   => 'person',
						'label' => array(
							'tr' => 'Teslim alan',
							'en' => 'Received by',
						),
						'type'  => 'text',
					),
					array(
						'key'   => 'date',
						'label' => array(
							'tr' => 'Teslim tarihi',
							'en' => 'Date',
						),
						'type'  => 'date',
					),
					array(
						'key'     => 'state',
						'label'   => array(
							'tr' => 'Durum',
							'en' => 'State',
						),
						'type'    => 'select',
						'options' => array(
							'ok'      => array(
								'tr' => 'Sağlam',
								'en' => 'Fine',
							),
							'damaged' => array(
								'tr' => 'Hasarlı',
								'en' => 'Damaged',
							),
						),
					),
				),
			),
		);
		$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
		$last  = self::arr( get_option( 'flxb_last_sync', array() ) );
		$lr    = self::arr( $last['result'] ?? array() );
		$theme = self::s( flxb_theme_color( 'primary' ) );

		return array(
			'tabs'            => $tabs,
			'currency'        => self::s( $s['currency'] ?? '' ),
			'currencies'      => array(
				array(
					'value' => 'TRY',
					'label' => __( 'Turkish lira (₺)', 'flx-donate' ),
				),
				array(
					'value' => 'EUR',
					'label' => __( 'Euro (€)', 'flx-donate' ),
				),
				array(
					'value' => 'USD',
					'label' => __( 'US dollar ($)', 'flx-donate' ),
				),
				array(
					'value' => 'GBP',
					'label' => __( 'Pound sterling (£)', 'flx-donate' ),
				),
			),
			'donationForms'   => 'list' === ( $s['donation_forms'] ?? '' ) ? 'list' : 'payments',
			'formIds'         => array_values( array_map( static fn( $x ): int => (int) self::s( $x ), self::arr( $s['form_ids'] ?? array() ) ) ),
			'forms'           => $forms,
			'moduleTrack'     => ! empty( $s['module_track'] ),
			'moduleReports'   => ! empty( $s['module_reports'] ),
			'moduleKurban'    => ! empty( $s['module_kurban'] ),
			'orgName'         => self::s( $s['org_name'] ?? '' ),
			'orgPlaceholder'  => self::s( get_bloginfo( 'name' ) ),
			'logoId'          => (int) self::s( $s['logo_id'] ?? '' ),
			'logoUrl'         => self::s( $logo ),
			'primaryColor'    => self::s( $s['primary_color'] ?? '' ),
			'colorHolder'     => '' !== $theme ? $theme : '#1F2937',
			'receiptText'     => self::s( $s['receipt_text'] ?? '' ),
			'footerText'      => self::s( $s['footer_text'] ?? '' ),
			'brandName'       => self::s( $b['name'] ?? '' ),
			'brandLogo'       => self::s( $b['logo'] ?? '' ),
			'brandPrimary'    => self::s( $b['primary'] ?? '' ),
			'brandDark'       => self::s( $b['primary_dark'] ?? '' ),
			'categories'      => $cats,
			'earlyDays'       => (int) self::s( flxb_track_early_days() ),
			'labels'          => $lf,
			'kurban'          => $kurban,
			'proxyText'       => is_array( $cur ) ? self::s( $cur['text'] ?? '' ) : '',
			'proxyVersions'   => $versions,
			'tabsJson'        => $own ? self::s( wp_json_encode( $own, $flags ) ) : '',
			'tabsInUse'       => $inuse,
			'fieldTypes'      => implode( ', ', array_map( static fn( $x ): string => self::s( $x ), self::arr( FLXB_Detail_Tabs::field_types() ) ) ),
			'tabsExample'     => self::s( wp_json_encode( $ex, $flags ) ),
			'importReady'     => '' !== self::s( FLXB_FLXForm_Bridge::pay_table() ),
			/* translators: 1: date, 2: checked, 3: imported */
			'importLast'      => ! empty( $last['time'] ) ? sprintf( __( 'Last run %1$s: %2$s payments checked, %3$s imported.', 'flx-donate' ), self::s( flx_date( (int) self::s( $last['time'] ), 'datetime' ) ), number_format_i18n( (int) self::s( $lr['seen'] ?? 0 ) ), number_format_i18n( (int) self::s( $lr['imported'] ?? 0 ) ) ) : '',
		);
	}

	/**
	 * @param array<mixed> $in currency, donationForms, formIds, moduleTrack, moduleReports, moduleKurban.
	 * @return array{message: string}
	 */
	public static function general( array $in ): array {
		$s                   = self::map( flxb_settings() );
		$cur                 = strtoupper( self::s( $in['currency'] ?? '' ) );
		$s['currency']       = in_array( $cur, array( 'TRY', 'EUR', 'USD', 'GBP' ), true ) ? $cur : 'TRY';
		$s['donation_forms'] = 'list' === ( $in['donationForms'] ?? '' ) ? 'list' : 'payments';
		$s['form_ids']       = array_values( array_filter( array_map( static fn( $x ): int => absint( self::s( $x ) ), self::arr( $in['formIds'] ?? array() ) ) ) );
		$s['module_kurban']  = ! empty( $in['moduleKurban'] ) ? 1 : 0;
		$s['module_track']   = ! empty( $in['moduleTrack'] ) ? 1 : 0;
		$s['module_reports'] = ! empty( $in['moduleReports'] ) ? 1 : 0;
		update_option( 'flxb_settings', $s, false );
		return self::ok();
	}

	/**
	 * @param array<mixed> $in orgName, logoId, primaryColor, receiptText, footerText.
	 * @return array{message: string}
	 */
	public static function organisation( array $in ): array {
		$s                  = self::map( flxb_settings() );
		$color              = trim( sanitize_text_field( self::s( $in['primaryColor'] ?? '' ) ) );
		$s['org_name']      = sanitize_text_field( self::s( $in['orgName'] ?? '' ) );
		$s['logo_id']       = absint( self::s( $in['logoId'] ?? 0 ) );
		$s['primary_color'] = preg_match( '/^#[0-9a-fA-F]{6}$/', $color ) ? strtoupper( $color ) : '';
		$s['receipt_text']  = sanitize_textarea_field( self::s( $in['receiptText'] ?? '' ) );
		$s['footer_text']   = sanitize_textarea_field( self::s( $in['footerText'] ?? '' ) );
		update_option( 'flxb_settings', $s, false );
		return self::ok();
	}

	/**
	 * Categories; the ones in use are kept even if their row was removed.
	 *
	 * @param array<mixed> $rows {key, label} rows.
	 * @return array{message: string}
	 */
	public static function categories( array $rows ): array {
		$old  = self::arr( flxb_categories() );
		$cats = array();
		foreach ( $rows as $row ) {
			$row   = self::arr( $row );
			$label = sanitize_text_field( self::s( $row['label'] ?? '' ) );
			$key   = sanitize_key( remove_accents( self::s( $row['key'] ?? '' ) ) );
			if ( '' === $key ) {
				$key = sanitize_key( str_replace( ' ', '-', remove_accents( strtolower( $label ) ) ) );
			}
			if ( '' === $label && '' === $key ) {
				continue;
			}
			$cats[ '' !== $key ? $key : 'cat-' . ( count( $cats ) + 1 ) ] = '' !== $label ? $label : $key;
		}
		/** @var wpdb $wpdb */
		global $wpdb;
		foreach ( self::arr( $wpdb->get_col( 'SELECT DISTINCT category FROM ' . self::s( flxb_table( 'types' ) ) . " WHERE category <> ''" ) ) as $k ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
			$k = self::s( $k );
			if ( ! isset( $cats[ $k ] ) ) {
				$cats[ $k ] = $old[ $k ] ?? $k;
			}
		}
		update_option( 'flxb_categories', $cats, false );
		return self::ok();
	}

	/**
	 * Tracking words, early days and (with gifts on behalf of a person) the proxy text.
	 *
	 * @param array<mixed> $in earlyDays, labels {key, value}, proxyText.
	 * @return array{message: string}
	 */
	public static function track( array $in ): array {
		if ( flxb_kurban_enabled() ) {
			$text = trim( sanitize_textarea_field( self::s( $in['proxyText'] ?? '' ) ) );
			$cur  = flxb_proxy_text( 0, '' );
			if ( '' !== $text && ( ! is_array( $cur ) || self::s( $cur['text'] ?? '' ) !== $text ) ) {
				flxb_proxy_text_save( $text ); // 2.34.0: a changed text drops the old version's languages.
			}
		}
		update_option( 'flxb_track_early_days', max( 0, min( 30, absint( self::s( $in['earlyDays'] ?? 0 ) ) ) ), false );
		$labels = array();
		foreach ( self::arr( $in['labels'] ?? array() ) as $l ) {
			$l = self::arr( $l );
			$labels[ sanitize_key( self::s( $l['key'] ?? '' ) ) ] = self::s( $l['value'] ?? '' );
		}
		update_option( 'flxb_track_labels', FLXB_Track::clean_labels( $labels ), false );
		return self::ok();
	}

	/** @return array{message: string}|WP_Error */
	public static function tabs_json( string $json ) {
		$res = FLXB_Detail_Tabs::save_json( $json );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'flxb_settings', $res->get_error_message(), array( 'graphql_code' => 'INVALID_INPUT' ) );
		}
		return self::ok();
	}

	/**
	 * Reads completed Flux Forms payments and adds the missing ones as donations.
	 *
	 * @return array{message: string}
	 */
	public static function backfill(): array {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		FLXB_FLXForm_Bridge::import( 0, 'backfill' );
		$last = self::arr( get_option( 'flxb_last_sync', array() ) );
		$r    = self::arr( $last['result'] ?? array() );
		/* translators: 1: payments checked, 2: newly imported, 3: already known */
		return array( 'message' => sprintf( __( 'Import finished: %1$s payments checked, %2$s imported, %3$s already known.', 'flx-donate' ), number_format_i18n( (int) self::s( $r['seen'] ?? 0 ) ), number_format_i18n( (int) self::s( $r['imported'] ?? 0 ) ), number_format_i18n( (int) self::s( $r['existing'] ?? 0 ) ) ) );
	}
}
