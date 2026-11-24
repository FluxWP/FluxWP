<?php
/**
 * Donation types list and the type editor for the admin screen (GraphQL part b4).
 *
 * The wp-admin screen (packages/modules/src/donate/Types.tsx, TypeForm.tsx) calls this class. Rights are the
 * classic ones: the list needs flx_bagis_view; the editor, status, order and sample types need
 * flxb_can_manage(). The form mapping, translations and other plugins' cards (`flxb_type_fields`) are printed
 * by PHP as islands with their classic field names; the screen sends their fields back as `extra`.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

final class FLXB_Admin_Types_Service {

	/**
	 * @param mixed $v Value.
	 * @return array<mixed>
	 */
	private static function arr( $v ): array {
		return is_array( $v ) ? $v : array();
	}

	/**
	 * @param mixed $v Value.
	 */
	private static function s( $v ): string {
		return is_scalar( $v ) ? (string) $v : '';
	}

	/**
	 * @param mixed $v Value.
	 */
	private static function i( $v ): int {
		return is_numeric( $v ) ? (int) $v : 0;
	}

	/**
	 * @param mixed $v Value.
	 */
	private static function f( $v ): float {
		return is_numeric( $v ) ? (float) $v : 0.0;
	}

	public static function can_list(): bool {
		return (bool) flxb_can_view();
	}

	public static function can_manage(): bool {
		return (bool) flxb_can_manage();
	}

	private static function day( string $ymd ): string {
		return '' !== $ymd ? self::s( flx_date( (int) strtotime( $ymd . ' 12:00' ) ) ) : '';
	}

	/**
	 * Life-cycle pill of a type: [ tone, label ].
	 *
	 * @param array<string,mixed> $t Type.
	 * @return array{0: string, 1: string}
	 */
	private static function state( array $t ): array {
		switch ( self::s( FLXB_Types::state( $t ) ) ) {
			case 'open':
				return array( 'ok', __( 'Open', 'flx-donate' ) );
			case 'upcoming':
				/* translators: %s: start date */
				return array( 'pending', sprintf( __( 'Upcoming (%s)', 'flx-donate' ), self::day( self::s( $t['start_date'] ?? '' ) ) ) );
			case 'ended':
				return array( 'muted', __( 'Ended', 'flx-donate' ) );
			case 'archived':
				return array( 'muted', __( 'Archived', 'flx-donate' ) );
			default:
				return array( 'pending', __( 'Closed', 'flx-donate' ) );
		}
	}

	/**
	 * The list: open and closed types, or the archive.
	 *
	 * @return array<string,mixed>
	 */
	public static function list( bool $archived ): array {
		$cats   = self::arr( flxb_categories() );
		$manage = self::can_manage();
		$items  = array();
		foreach ( self::arr( FLXB_Types::all( $archived ? 'archived' : '' ) ) as $t ) {
			if ( ! is_array( $t ) ) {
				continue;
			}
			/** @var array<string,mixed> $t */
			$id       = self::i( $t['id'] ?? 0 );
			$currency = self::s( $t['currency'] ?? '' );
			$p        = self::arr( FLXB_Types::progress( $t ) );
			$st       = self::state( $t );
			$options  = self::arr( $t['options'] ?? null );
			$prices   = array();
			foreach ( array_slice( $options, 0, 4 ) as $o ) {
				$o        = self::arr( $o );
				$price    = self::f( $o['price'] ?? 0 );
				$prices[] = self::s( $o['label'] ?? '' ) . ' ' . ( empty( $o['active'] ) ? __( '(inactive)', 'flx-donate' ) : ( $price > 0 ? self::s( flxb_money( $price, $currency ) ) : __( '(price not set)', 'flx-donate' ) ) );
			}
			if ( count( $options ) > 4 ) {
				$prices[] = '…';
			}
			if ( ! empty( $t['allow_free'] ) ) {
				$min      = self::f( $t['min_amount'] ?? 0 );
				/* translators: %s: minimum */
				$prices[] = $min > 0 ? sprintf( __( 'free amount (min. %s)', 'flx-donate' ), self::s( flxb_money( $min, $currency ) ) ) : __( 'free amount', 'flx-donate' );
			}
			$flags = array();
			if ( ! empty( $t['allow_quantity'] ) ) {
				$flags[] = __( 'quantity', 'flx-donate' );
			}
			if ( ! empty( $t['allow_recurring'] ) ) {
				$flags[] = __( 'recurring', 'flx-donate' );
			}
			$goal   = self::f( $p['goal'] ?? 0 );
			$raised = self::f( $p['raised'] ?? 0 );
			$donors = self::i( $p['donors'] ?? 0 );
			$pct    = self::f( $p['percent'] ?? 0 );
			$start  = self::s( $t['start_date'] ?? '' );
			$end    = self::s( $t['end_date'] ?? '' );
			$forms  = array();
			foreach ( self::arr( $t['form_ids'] ?? null ) as $fid ) {
				$forms[] = self::s( flxb_form_title( self::i( $fid ) ) );
			}
			$status  = self::s( $t['status'] ?? '' );
			$items[] = array(
				'databaseId'   => $id,
				'title'        => self::s( $t['title'] ?? '' ),
				'stateLabel'   => $st[1],
				'tone'         => $st[0],
				'category'     => self::s( $cats[ self::s( $t['category'] ?? '' ) ] ?? __( 'No category', 'flx-donate' ) ),
				'shortcode'    => '[flx_bagis_kampanya id=' . $id . ']',
				'prices'       => implode( ' · ', $prices ),
				'flags'        => implode( ', ', $flags ),
				'hasGoal'      => $goal > 0,
				'percent'      => $pct,
				/* translators: 1: raised, 2: goal, 3: percent */
				'progress'     => $goal > 0 ? sprintf( __( '%1$s of %2$s (%3$s%%)', 'flx-donate' ), self::s( flxb_money( $raised, $currency ) ), self::s( flxb_money( $goal, $currency ) ), number_format_i18n( $pct, 0 ) ) : self::s( flxb_money( $raised, $currency ) ),
				/* translators: %s: number of donors */
				'donors'       => sprintf( _n( '%s donor', '%s donors', $donors, 'flx-donate' ), number_format_i18n( $donors ) ),
				'dates'        => '' !== $start || '' !== $end ? ( '' !== $start ? self::day( $start ) : '…' ) . ' – ' . ( '' !== $end ? self::day( $end ) : '…' ) : __( 'Open-ended', 'flx-donate' ),
				'forms'        => $forms ? implode( ', ', $forms ) : '',
				'status'       => in_array( $status, array( 'active', 'closed', 'archived' ), true ) ? $status : 'closed',
				'listUrl'      => self::s( FLXB_Admin_Url::page( 'flxb-donations', array( 'dtype' => $id ) ) ),
				'editUrl'      => $manage ? self::s( FLXB_Admin_Url::page( 'flxb-types', array( 'edit' => $id ) ) ) : '',
			);
		}
		$samples = array();
		if ( $manage && ! $archived ) {
			foreach ( self::arr( FLXB_Types::samples() ) as $k => $s ) {
				$samples[] = array(
					'value' => self::s( $k ),
					'label' => self::s( self::arr( $s )['label'] ?? $k ),
				);
			}
		}
		return array(
			'items'     => $items,
			'canManage' => $manage,
			'newUrl'    => $manage ? self::s( FLXB_Admin_Url::page( 'flxb-types', array( 'edit' => 'new' ) ) ) : '',
			'samples'   => $samples,
		);
	}

	private static function err( string $code, string $msg ): WP_Error {
		return new WP_Error( 'flxb_types', $msg, array( 'graphql_code' => $code ) );
	}

	/**
	 * Open, close or archive a type. Opening is refused when one of its forms already collects for
	 * another open type (FLXB_Types::$notices says which).
	 *
	 * @return array{message: string}|WP_Error
	 */
	public static function set_status( int $id, string $status ) {
		if ( ! FLXB_Types::get( $id ) ) {
			return self::err( 'INVALID_INPUT', __( 'Donation type not found.', 'flx-donate' ) );
		}
		if ( ! FLXB_Types::set_status( $id, $status ) ) {
			$why = implode( ' ', array_map( array( __CLASS__, 's' ), self::arr( FLXB_Types::$notices ) ) );
			return self::err( 'CONFLICT', '' !== $why ? $why : __( 'The action could not be completed.', 'flx-donate' ) );
		}
		return array( 'message' => __( 'Saved.', 'flx-donate' ) );
	}

	/**
	 * Move a type one place up (-1) or down (+1).
	 *
	 * @return array{message: string}|WP_Error
	 */
	public static function move( int $id, int $dir ) {
		if ( ! FLXB_Types::get( $id ) ) {
			return self::err( 'INVALID_INPUT', __( 'Donation type not found.', 'flx-donate' ) );
		}
		FLXB_Types::move( $id, $dir < 0 ? -1 : 1 );
		return array( 'message' => '' );
	}

	/**
	 * Load a sample set of types and categories.
	 *
	 * @return array{message: string}|WP_Error
	 */
	public static function samples( string $key ) {
		if ( ! array_key_exists( $key, self::arr( FLXB_Types::samples() ) ) ) {
			return self::err( 'INVALID_INPUT', __( 'The action could not be completed.', 'flx-donate' ) );
		}
		FLXB_Types::load_sample( $key );
		return array( 'message' => __( 'Saved.', 'flx-donate' ) );
	}

	/* ------------------------------------------------------------------
	 * Editor
	 * ------------------------------------------------------------------ */

	/** The classic card builders (FLXB_Page_Types) are loaded on admin pages only. */
	private static function load_page(): void {
		if ( ! class_exists( 'FLXB_Page_Types' ) ) {
			require_once dirname( __DIR__ ) . '/admin/page-types.php';
		}
	}

	/** @var list<array{id: string, title: string, col: string, html: string}>|null Islands of the page being printed. */
	private static ?array $islands = null;

	/**
	 * @param array<int|string,mixed> $map key => label (or [ singular, plural ]).
	 * @return list<array{value: string, label: string}>
	 */
	private static function choices( array $map ): array {
		$out = array();
		foreach ( $map as $k => $l ) {
			$out[] = array(
				'value' => self::s( $k ),
				'label' => self::s( is_array( $l ) ? ( $l[0] ?? $k ) : $l ),
			);
		}
		return $out;
	}

	/**
	 * @param mixed $m code => amount.
	 * @return list<array{currency: string, amount: float}>
	 */
	private static function amounts( $m ): array {
		$out = array();
		foreach ( self::arr( $m ) as $code => $v ) {
			$out[] = array(
				'currency' => self::s( $code ),
				'amount'   => self::f( $v ),
			);
		}
		return $out;
	}

	/** @return array<string,string> */
	private static function currencies( string $cur ): array {
		$c = array(
			'TRY' => __( 'Turkish lira (₺)', 'flx-donate' ),
			'EUR' => __( 'Euro (€)', 'flx-donate' ),
			'USD' => __( 'US dollar ($)', 'flx-donate' ),
			'GBP' => __( 'Pound sterling (£)', 'flx-donate' ),
		);
		if ( '' !== $cur && ! isset( $c[ $cur ] ) ) {
			$c[ $cur ] = $cur;
		}
		return $c;
	}

	/** @return array<string,mixed> A new type's fields. */
	private static function blank(): array {
		return array(
			'id'              => 0,
			'title'           => '',
			'slug'            => '',
			'category'        => '',
			'description'     => '',
			'image_id'        => 0,
			'goal_amount'     => 0,
			'currency'        => self::s( flxb_default_currency() ),
			'start_date'      => '',
			'end_date'        => '',
			'status'          => 'active',
			'options'         => array(),
			'allow_free'      => 0,
			'min_amount'      => 0,
			'allow_quantity'  => 0,
			'max_quantity'    => 0,
			'allow_recurring' => 0,
			'form_ids'        => array(),
			'form_map'        => array(),
			'meta'            => array(),
		);
	}

	/**
	 * The editor of a type (0 = a new one). Null when the type does not exist.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function form( int $id ): ?array {
		$t = $id ? FLXB_Types::get( $id ) : self::blank();
		if ( ! is_array( $t ) ) {
			return null;
		}
		/** @var array<string,mixed> $t */
		self::load_page();
		$m       = self::arr( $t['meta'] ?? null );
		$cur     = self::s( $t['currency'] ?? '' );
		$extra   = array_map( array( __CLASS__, 's' ), self::arr( FLXB_Page_Types::extra_currencies( $t ) ) );
		$options = array();
		foreach ( self::arr( $t['options'] ?? null ) as $o ) {
			$o         = self::arr( $o );
			$options[] = array(
				'key'      => self::s( $o['key'] ?? '' ),
				'label'    => self::s( $o['label'] ?? '' ),
				'price'    => self::f( $o['price'] ?? 0 ),
				'unit'     => self::s( $o['unit'] ?? '' ),
				'unitsPer' => max( 1, self::i( $o['units_per'] ?? 1 ) ),
				'badge'    => self::s( $o['badge'] ?? '' ),
				'active'   => ! array_key_exists( 'active', $o ) || ! empty( $o['active'] ),
				'prices'   => self::amounts( $o['prices'] ?? null ),
			);
		}
		$units = self::arr( flxb_units() );
		$bads  = class_exists( 'FLXB_WC' ) ? self::arr( FLXB_WC::badges() ) : array( '' => '—' );
		foreach ( $options as $o ) {
			if ( '' !== $o['unit'] && ! isset( $units[ $o['unit'] ] ) ) {
				$units[ $o['unit'] ] = $o['unit']; // a custom unit stored earlier
			}
			if ( '' !== $o['badge'] && ! isset( $bads[ $o['badge'] ] ) ) {
				$bads[ $o['badge'] ] = $o['badge'];
			}
		}
		$forms  = array();
		$others = array();
		foreach ( self::arr( FLXB_Types::all( 'active' ) ) as $o ) {
			$o = self::arr( $o );
			if ( self::i( $o['id'] ?? 0 ) !== $id ) {
				foreach ( self::arr( $o['form_ids'] ?? null ) as $ofid ) {
					$others[ self::i( $ofid ) ][] = self::s( $o['title'] ?? '' );
				}
			}
		}
		foreach ( self::arr( flxb_forms() ) as $fid => $f ) {
			$f = self::arr( $f );
			if ( 'payment' !== ( $f['type'] ?? '' ) ) {
				continue;
			}
			$forms[] = array(
				'databaseId' => self::i( $fid ),
				'title'      => self::s( $f['title'] ?? '' ),
				'others'     => implode( ', ', $others[ self::i( $fid ) ] ?? array() ),
			);
		}
		$track  = (bool) flxb_track_enabled();
		$labels = array();
		if ( $track ) {
			$lb  = self::arr( $m['labels'] ?? null );
			$def = array_merge( self::arr( FLXB_Track::default_labels() ), array_filter( self::arr( get_option( 'flxb_track_labels', array() ) ), static fn( $v ): bool => '' !== self::s( $v ) ) );
			foreach ( self::arr( FLXB_Track::label_fields() ) as $k => $l ) {
				$labels[] = array(
					'key'         => self::s( $k ),
					'label'       => self::s( $l ),
					'value'       => self::s( $lb[ $k ] ?? '' ),
					'placeholder' => self::s( $def[ $k ] ?? '' ),
				);
			}
		}
		$img   = self::i( $t['image_id'] ?? 0 );
		$gifts = 0;
		if ( $id ) {
			global $wpdb;
			/** @var wpdb $wpdb */
			$gifts = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE type_id = %d', flxb_table( 'donations' ), $id ) );
		}
		$status = self::s( $t['status'] ?? 'active' );
		return array(
			'databaseId'      => $id,
			'title'           => self::s( $t['title'] ?? '' ),
			'slug'            => self::s( $t['slug'] ?? '' ),
			'category'        => self::s( $t['category'] ?? '' ),
			'currency'        => $cur,
			'description'     => self::s( $t['description'] ?? '' ),
			'subtitle'        => self::s( $m['subtitle'] ?? '' ),
			'icon'            => self::s( $m['icon'] ?? '' ),
			'imageId'         => $img,
			'imageUrl'        => $img ? self::s( wp_get_attachment_image_url( $img, 'medium' ) ) : '',
			'options'         => $options,
			'allowFree'       => ! empty( $t['allow_free'] ),
			'minAmount'       => self::f( $t['min_amount'] ?? 0 ),
			'minByCurrency'   => self::amounts( $m['min_by_currency'] ?? null ),
			'allowQuantity'   => ! empty( $t['allow_quantity'] ),
			'maxQuantity'     => self::i( $t['max_quantity'] ?? 0 ),
			'allowRecurring'  => ! empty( $t['allow_recurring'] ),
			'formIds'         => array_values( array_map( array( __CLASS__, 'i' ), self::arr( $t['form_ids'] ?? null ) ) ),
			'lockPreselect'   => ! empty( $m['lock_preselect'] ),
			'trackOn'         => $track,
			'trackItems'      => ! empty( $m['track_items'] ) || ! empty( $m['requires_proxy'] ),
			'fileCompletes'   => false !== ( $m['file_completes'] ?? true ),
			'trackLabels'     => $labels,
			'kurbanOn'        => (bool) flxb_kurban_enabled(),
			'requiresProxy'   => ! empty( $m['requires_proxy'] ),
			'intention'       => self::s( $m['intention'] ?? '' ),
			'grouping'        => self::s( $m['grouping'] ?? 'none' ),
			'meatToNeedy'     => ! empty( $m['meat_to_needy'] ),
			'status'          => in_array( $status, array( 'active', 'closed', 'archived' ), true ) ? $status : 'active',
			'startDate'       => self::s( $t['start_date'] ?? '' ),
			'endDate'         => self::s( $t['end_date'] ?? '' ),
			'goal'            => self::f( $t['goal_amount'] ?? 0 ),
			'shortcode'       => $id ? '[flx_bagis_kampanya id=' . $id . ']' : '',
			'hasGifts'        => $gifts > 0,
			'categories'      => self::choices( self::arr( flxb_categories() ) ),
			'categoriesUrl'   => self::s( FLXB_Admin_Url::page( 'flxb-settings', array( 'tab' => 'categories' ) ) ),
			'currencies'      => self::choices( self::currencies( $cur ) ),
			'extraCurrencies' => $extra,
			'icons'           => self::choices(
				array(
					''       => __( 'Automatic', 'flx-donate' ),
					'heart'  => __( 'Heart', 'flx-donate' ),
					'gift'   => __( 'Gift', 'flx-donate' ),
					'sprout' => __( 'Sapling', 'flx-donate' ),
					'sheep'  => __( 'Sheep', 'flx-donate' ),
					'cow'    => __( 'Cattle', 'flx-donate' ),
					'camel'  => __( 'Camel', 'flx-donate' ),
				)
			),
			'units'           => self::choices( $units ),
			'badges'          => self::choices( $bads ),
			'intentions'      => self::choices( self::arr( FLXB_Types::intentions() ) ),
			'forms'           => $forms,
			'listUrl'         => self::s( FLXB_Admin_Url::page( 'flxb-types' ) ),
			'islands'         => self::island_list( $t ),
		);
	}

	/**
	 * Cards printed by PHP: the form mapping, translations, other plugins' cards and the preview.
	 *
	 * @param array<string,mixed> $t Type.
	 * @return list<array{id: string, title: string, col: string, html: string}>
	 */
	public static function islands( array $t ): array {
		self::load_page();
		$id  = self::i( $t['id'] ?? 0 );
		$out = array();
		$add = static function ( string $key, string $col, string $html ) use ( &$out ): void {
			$html = trim( $html );
			if ( '' !== $html ) {
				$out[] = array(
					'id'    => $key,
					'title' => '',
					'col'   => $col,
					'html'  => $html,
				);
			}
		};
		ob_start();
		foreach ( self::arr( $t['form_ids'] ?? null ) as $fid ) {
			FLXB_Page_Types::mapping( $t, self::i( $fid ) );
		}
		$add( 'map', 'main', self::s( ob_get_clean() ) );
		ob_start();
		FLXB_Page_Types::i18n_card( $t );
		$add( 'i18n', 'main', self::s( ob_get_clean() ) );
		ob_start();
		do_action( 'flxb_type_fields', $t ); // Add-ons print their own cards (save their keys through `flxb_type_meta_clean`).
		$add( 'addons', 'main', self::s( ob_get_clean() ) );
		if ( $id ) {
			$add( 'preview', 'side', self::s( do_shortcode( '[flx_bagis_kampanya id=' . $id . ']' ) ) );
		}
		return $out;
	}

	/**
	 * Island ids for the screen; the HTML is printed with the page (print_islands()). Over GraphQL
	 * outside the page (the app) there are none: they need the page.
	 *
	 * @param array<string,mixed> $t Type.
	 * @return list<array{id: string, title: string, col: string}>
	 */
	private static function island_list( array $t ): array {
		if ( null === self::$islands ) {
			return array();
		}
		self::$islands = self::islands( $t );
		return array_map(
			static fn( array $b ): array => array(
				'id'    => $b['id'],
				'title' => $b['title'],
				'col'   => $b['col'],
			),
			self::$islands
		);
	}

	/** The page that is being printed wants islands (called before the preload). */
	public static function want_islands(): void {
		self::$islands = array();
	}

	/** Print the islands collected while the preload ran, hidden; the screen moves them into place. */
	public static function print_islands(): void {
		if ( ! self::$islands ) {
			return;
		}
		echo '<div id="flxdn-type-islands" hidden>';
		foreach ( self::$islands as $b ) {
			echo '<div class="flxb flxdn-island" data-flxdn-island="' . esc_attr( $b['id'] ) . '">' . $b['html'] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the cards are escaped where they are built
		}
		echo '</div>';
	}

	/**
	 * Save a type (0 = a new one). $in holds the screen's fields; $extra the islands' fields as a
	 * form body (meta[…], form_map[…]), the classic names.
	 *
	 * @param array<string,mixed> $in Fields.
	 * @return array{message: string, id: int, warning: string}|WP_Error
	 */
	public static function save( int $id, array $in, string $extra ) {
		if ( $id && ! FLXB_Types::get( $id ) ) {
			return self::err( 'INVALID_INPUT', __( 'Donation type not found.', 'flx-donate' ) );
		}
		$x = array();
		if ( '' !== $extra ) {
			parse_str( $extra, $x );
		}
		$meta = self::arr( $x['meta'] ?? null );
		$own  = self::arr( $in['meta'] ?? null );
		foreach ( array( 'subtitle', 'icon', 'lock_preselect', 'min_by_currency', 'track_items', 'file_completes', 'labels', 'requires_proxy', 'intention', 'grouping', 'meat_to_needy' ) as $k ) {
			if ( array_key_exists( $k, $own ) ) {
				$meta[ $k ] = $own[ $k ];
			}
		}
		$d         = $in;
		$d['meta'] = $meta;
		if ( array_key_exists( 'form_map', $x ) ) {
			$d['form_map'] = self::arr( $x['form_map'] );
		}
		$res = FLXB_Types::save( $d, $id );
		if ( is_wp_error( $res ) ) {
			return self::err( 'INVALID_INPUT', $res->get_error_message() );
		}
		$new = self::i( $res );
		self::relink( $new );
		return array(
			'message' => __( 'Saved.', 'flx-donate' ),
			'id'      => $new,
			'warning' => implode( ' ', array_map( array( __CLASS__, 's' ), self::arr( FLXB_Types::$notices ) ) ),
		);
	}

	/** Gifts already received through the linked forms that have no type yet join this one. */
	public static function relink( int $id ): void {
		$t = FLXB_Types::get( $id );
		if ( ! is_array( $t ) || ! $t['form_ids'] ) {
			return;
		}
		global $wpdb;
		/** @var wpdb $wpdb */
		$dn = self::s( flxb_table( 'donations' ) );
		foreach ( self::arr( $t['form_ids'] ) as $fid ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, created_at FROM %i WHERE type_id = 0 AND form_id = %d', $dn, self::i( $fid ) ), ARRAY_A );
			foreach ( self::arr( $rows ) as $r ) {
				$r   = self::arr( $r );
				$tid = self::i( FLXB_Types::for_form( self::i( $fid ), self::s( $r['created_at'] ?? '' ) ) );
				if ( $tid ) {
					$wpdb->update( $dn, array( 'type_id' => $tid ), array( 'id' => self::i( $r['id'] ?? 0 ) ) );
				}
			}
		}
	}

	/**
	 * Delete a type that has no gifts (one with gifts can only be archived).
	 *
	 * @return array{message: string}|WP_Error
	 */
	public static function delete( int $id ) {
		if ( ! FLXB_Types::get( $id ) ) {
			return self::err( 'INVALID_INPUT', __( 'Donation type not found.', 'flx-donate' ) );
		}
		global $wpdb;
		/** @var wpdb $wpdb */
		if ( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE type_id = %d', flxb_table( 'donations' ), $id ) ) ) {
			return self::err( 'CONFLICT', __( 'This type has gifts, so it can be archived but not deleted.', 'flx-donate' ) );
		}
		FLXB_Types::delete( $id );
		return array( 'message' => __( 'Deleted.', 'flx-donate' ) );
	}
}
