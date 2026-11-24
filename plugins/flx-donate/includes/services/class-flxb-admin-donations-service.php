<?php
/**
 * Donation dashboard and donation list for the admin screen (GraphQL part b1).
 *
 * The wp-admin screen (packages/modules/src/donate) and the classic bulk handler call this class;
 * it holds no screen code. Rights are the classic ones:
 * - list: flx_bagis_view, or flxb_view_own (staff, only the donations they referred);
 * - dashboard: flx_bagis_view or flxb_dashboard (totals without donor contacts);
 * - bulk: flxb_can_manage() and not own-only; permanent delete also manage_options; a donation of a
 *   WooCommerce order also needs delete_shop_orders or manage_woocommerce.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type FlxbListFilter array{search: string, from: string, to: string, status: string, type: string, type_id: string, form_id: string, donor_id: string, ref_user_id: string}
 * @phpstan-type FlxbListRow array<string,mixed>
 * @phpstan-type FlxbChoice array{value: string, label: string}
 */
final class FLXB_Admin_Donations_Service {

	/** Stored statuses plus the two stages of a pending gift. */
	const LIST_STATUSES = array( 'completed', 'pending', 'receipt', 'payment', 'failed', 'canceled', 'refunded', 'partially_refunded', 'trash' );

	/** The approval class (receipt stages) lives with the admin pages; GraphQL requests are not is_admin(). */
	private static function boot(): void {
		if ( ! class_exists( 'FLXB_Approval' ) ) {
			require_once dirname( __DIR__ ) . '/admin/class-flxb-approval.php';
		}
	}

	/**
	 * Any value as an array (helpers of the classic code are untyped).
	 *
	 * @param mixed $v Value.
	 * @return array<mixed>
	 */
	private static function arr( $v ): array {
		return is_array( $v ) ? $v : array();
	}

	/** Label of a stored status (flxb_status_label() gives [ tone, label ]). */
	private static function status_label( string $s ): string {
		$l = flxb_status_label( $s );
		return self::s( self::arr( $l )[1] ?? $s );
	}

	/**
	 * Donation ids of a WooCommerce order.
	 *
	 * @return list<int>
	 */
	private static function order_ids( int $oid ): array {
		$ids = FLXB_Donations::ids_of_order( $oid );
		return array_values( array_map( static fn( $v ): int => self::i( $v ), self::arr( $ids ) ) );
	}

	public static function can_list(): bool {
		return (bool) FLXB_Refs::can_list();
	}

	public static function can_dashboard(): bool {
		return (bool) FLXB_Refs::can_dashboard();
	}

	public static function own_only(): bool {
		return (bool) FLXB_Refs::own_only();
	}

	/** May run the list's bulk actions (trash, restore). */
	public static function can_bulk(): bool {
		return ! self::own_only() && (bool) flxb_can_manage();
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

	/**
	 * GraphQL filter input → the classic list filter (same keys as FLXB_Page_Donations::filters()).
	 *
	 * @param array<string,mixed> $in search, from, to, status, kind, donationTypeId, formId, donorId, referralId.
	 * @return FlxbListFilter
	 */
	public static function filter( array $in ): array {
		$re   = '/^\d{4}-\d{2}-\d{2}$/';
		$from = self::s( $in['from'] ?? '' );
		$to   = self::s( $in['to'] ?? '' );
		$st   = sanitize_key( self::s( $in['status'] ?? '' ) );
		$kind = sanitize_key( self::s( $in['kind'] ?? '' ) );
		$num  = static function ( $v ): string {
			return null === $v || '' === $v || ! is_numeric( $v ) ? '' : (string) absint( $v );
		};
		return array(
			'search'      => sanitize_text_field( self::s( $in['search'] ?? '' ) ),
			'from'        => preg_match( $re, $from ) ? $from : '',
			'to'          => preg_match( $re, $to ) ? $to : '',
			'status'      => in_array( $st, self::LIST_STATUSES, true ) ? $st : '',
			'type'        => in_array( $kind, array( 'one_time', 'recurring' ), true ) ? $kind : '',
			'type_id'     => $num( $in['donationTypeId'] ?? null ),
			'form_id'     => $num( $in['formId'] ?? null ),
			'donor_id'    => $num( $in['donorId'] ?? null ),
			'ref_user_id' => $num( $in['referralId'] ?? null ),
		);
	}

	/**
	 * Query arguments: staff with flxb_view_own see only their referrals; "receipt" and "payment" are
	 * the two stages of a pending gift (as on the classic list).
	 *
	 * @param FlxbListFilter $f Filter.
	 * @return array<string,mixed>
	 */
	private static function query_args( array $f ): array {
		self::boot();
		/** @var array<string,mixed> $a */
		$a = $f;
		if ( self::own_only() ) {
			$a['ref_user_id'] = (string) get_current_user_id();
		}
		if ( in_array( $f['status'], array( 'receipt', 'payment' ), true ) ) {
			$a['stage']  = FLXB_Approval::stage_filter( $f['status'] );
			$a['status'] = '';
		}
		return $a;
	}

	/**
	 * One page of the grouped list (an order's lines are one row), open gifts first.
	 *
	 * @param FlxbListFilter $f      Filter.
	 * @param int            $offset Rows to skip.
	 * @param int            $limit  Rows to return.
	 * @return array{items: list<array<string,mixed>>, total: int}
	 */
	public static function page( array $f, int $offset, int $limit ): array {
		if ( 0 === $offset && self::can_bulk() && class_exists( 'FLXB_WC_Ledger' ) && function_exists( 'wc_get_order' ) ) {
			FLXB_WC_Ledger::reconcile(); // 2.5.0: donations of orders deleted before (as the classic list)
		}
		$res   = FLXB_Donations::query(
			array_merge(
				self::query_args( $f ),
				array(
					'per_page'   => max( 1, $limit ),
					'offset'     => max( 0, $offset ),
					'group'      => true,
					'open_first' => true,
				)
			)
		);
		$res   = self::arr( $res );
		$items = array();
		$rows  = self::arr( $res['items'] ?? null );
		$refs  = self::own_only() ? array() : self::donor_refs( $rows );
		$types = self::type_titles();
		foreach ( $rows as $r ) {
			if ( is_array( $r ) ) {
				$items[] = self::row( $r, $types, $refs );
			}
		}
		return array(
			'items' => $items,
			'total' => self::i( $res['total'] ?? 0 ),
		);
	}

	/**
	 * Received total of the filtered list (completed and partly refunded gifts, default currency).
	 *
	 * @param FlxbListFilter $f Filter.
	 */
	public static function sum( array $f ): float {
		$res = FLXB_Donations::query( array_merge( self::query_args( $f ), array( 'per_page' => 1, 'page' => 1 ) ) );
		return self::f( self::arr( $res )['sum'] ?? 0 );
	}

	/** @return array<int,string> type id → title */
	private static function type_titles(): array {
		$out = array();
		foreach ( self::arr( FLXB_Types::all( 'all' ) ) as $c ) {
			if ( is_array( $c ) ) {
				$out[ self::i( $c['id'] ?? 0 ) ] = self::s( $c['title'] ?? '' );
			}
		}
		return $out;
	}

	/**
	 * 2.20.0: for gifts without a referral, the donor's referral.
	 *
	 * @param array<mixed> $rows Rows.
	 * @return array<int,int>
	 */
	private static function donor_refs( array $rows ): array {
		$ids = array();
		foreach ( $rows as $r ) {
			if ( is_array( $r ) && ! self::i( $r['ref_user_id'] ?? 0 ) ) {
				$ids[] = self::i( $r['donor_id'] ?? 0 );
			}
		}
		$out = array();
		foreach ( self::arr( FLXB_Refs::donor_refs( $ids ) ) as $donor => $ref ) {
			$out[ self::i( $donor ) ] = self::i( $ref );
		}
		return $out;
	}

	/**
	 * Donor name as the classic list shows it: name, else e-mail, else phone, else "Anonymous".
	 *
	 * @param array<mixed> $r Row with donor_name / donor_email / donor_phone.
	 */
	public static function donor_name( array $r ): string {
		foreach ( array( 'donor_name', 'donor_email' ) as $k ) {
			$v = self::s( $r[ $k ] ?? '' );
			if ( '' !== $v ) {
				return $v;
			}
		}
		$phone = self::s( $r['donor_phone'] ?? '' );
		return '' !== $phone ? self::s( flxb_format_phone( $phone ) ) : __( 'Anonymous', 'flx-donate' );
	}

	/**
	 * One list row.
	 *
	 * @param array<mixed>    $r     Grouped donation row.
	 * @param array<int,string> $types Type titles.
	 * @param array<int,int>    $refs  Donor → referral.
	 * @return array<string,mixed>
	 */
	private static function row( array $r, array $types, array $refs ): array {
		$id       = self::i( $r['id'] ?? 0 );
		$donor    = self::i( $r['donor_id'] ?? 0 );
		$name     = $donor ? self::donor_name( $r ) : __( 'Anonymous', 'flx-donate' );
		$email    = self::s( $r['donor_email'] ?? '' );
		$contact  = '' !== $email ? $email : self::s( flxb_format_phone( self::s( $r['donor_phone'] ?? '' ) ) );
		$lines    = max( 1, self::i( $r['group_lines'] ?? 1 ) );
		$currency = self::s( $r['currency'] ?? '' );
		$where    = array();
		$type_id  = self::i( $r['type_id'] ?? 0 );
		if ( $type_id && isset( $types[ $type_id ] ) ) {
			$where[] = $types[ $type_id ];
		}
		$form = self::i( $r['form_id'] ?? 0 );
		if ( $form ) {
			$where[] = self::s( flxb_form_title( $form ) );
		}
		if ( $lines > 1 ) {
			/* translators: %s: number of lines in the order */
			$where = array( sprintf( _n( '%s item', '%s items', $lines, 'flx-donate' ), number_format_i18n( $lines ) ) );
			foreach ( self::arr( FLXB_Donations::siblings( $r ) ) as $sib ) {
				$sid = is_array( $sib ) ? self::i( $sib['type_id'] ?? 0 ) : 0;
				if ( $sid && isset( $types[ $sid ] ) && ! in_array( $types[ $sid ], $where, true ) ) {
					$where[] = $types[ $sid ];
				}
			}
		}
		$state = flxb_donation_state( $r );
		$ref   = self::i( $r['ref_user_id'] ?? 0 );
		$dref  = ! $ref && $donor ? ( $refs[ $donor ] ?? 0 ) : 0;
		$code  = self::s( $r['track'] ?? '' );
		return array(
			'databaseId'   => $id,
			'donorId'      => $donor,
			'donorName'    => $name,
			'contact'      => '' !== $contact && $contact !== $name ? $contact : '',
			'amount'       => flx_gql_money( self::f( $r['group_amount'] ?? ( $r['amount'] ?? 0 ) ), $currency ),
			'amountLabel'  => self::s( flxb_money( self::f( $r['group_amount'] ?? ( $r['amount'] ?? 0 ) ), $currency ) ),
			'kind'         => 'recurring' === self::s( $r['type'] ?? '' ) ? 'recurring' : 'one_time',
			'statusLabel'  => self::s( $state[1] ?? '' ),
			'tone'         => self::tone( self::s( $state[0] ?? '' ) ),
			'date'         => flx_gql_datetime( self::s( $r['created_at'] ?? '' ) ),
			'where'        => $where ? implode( ' · ', $where ) : '',
			'lines'        => $lines,
			'referral'     => $ref ? self::s( FLXB_Refs::name( $ref ) ) : ( $dref ? self::s( FLXB_Refs::name( $dref ) ) : '' ),
			'referralFromDonor' => ! $ref && $dref > 0,
			'code'         => $code,
			'url'          => FLXB_Admin_Url::donation( $id ),
		);
	}

	/**
	 * @param string $tone Classic tone (ok, pending, failed, muted, receipt).
	 */
	private static function tone( string $tone ): string {
		return in_array( $tone, array( 'ok', 'pending', 'failed', 'muted', 'receipt' ), true ) ? $tone : 'muted';
	}

	/**
	 * Filter choices, counters, links and rights of the list screen.
	 *
	 * @param FlxbListFilter $f Filter.
	 * @return array<string,mixed>
	 */
	public static function info( array $f ): array {
		self::boot();
		$own      = self::own_only();
		$manage   = self::can_bulk();
		$statuses = array();
		foreach ( FLXB_Donations::STATUSES as $s ) {
			if ( 'trash' === $s ) {
				continue;
			}
			$statuses[] = array( 'value' => $s, 'label' => self::status_label( $s ) );
			if ( 'pending' === $s ) {
				$statuses[] = array( 'value' => 'receipt', 'label' => '— ' . __( 'Receipt awaiting approval', 'flx-donate' ) );
				$statuses[] = array( 'value' => 'payment', 'label' => '— ' . __( 'Awaiting payment', 'flx-donate' ) );
			}
		}
		$types = array();
		foreach ( self::type_titles() as $id => $title ) {
			$types[] = array( 'value' => (string) $id, 'label' => $title );
		}
		$forms = array();
		foreach ( self::arr( flxb_forms() ) as $fid => $fm ) {
			if ( is_array( $fm ) && 'payment' === self::s( $fm['type'] ?? '' ) ) {
				$forms[] = array( 'value' => (string) self::i( $fid ), 'label' => self::s( $fm['title'] ?? '' ) );
			}
		}
		$refs = array();
		if ( ! $own ) {
			foreach ( self::arr( FLXB_Refs::users() ) as $uid => $label ) {
				$refs[] = array( 'value' => (string) self::i( $uid ), 'label' => self::s( $label ) );
			}
		}
		$awaiting = array();
		if ( 'trash' !== $f['status'] && 'receipt' !== $f['status'] ) {
			$types_t = self::type_titles();
			foreach ( self::arr( FLXB_Approval::awaiting( $own ? get_current_user_id() : 0 ) ) as $r ) {
				if ( ! is_array( $r ) ) {
					continue;
				}
				$lines = max( 1, self::i( $r['group_lines'] ?? 1 ) );
				$tid   = self::i( $r['type_id'] ?? 0 );
				/* translators: %s: number of lines in the order */
				$what       = $lines > 1 ? sprintf( _n( '%s item', '%s items', $lines, 'flx-donate' ), number_format_i18n( $lines ) ) : ( $types_t[ $tid ] ?? '' );
				$awaiting[] = array(
					'databaseId' => self::i( $r['id'] ?? 0 ),
					'donorName'  => self::i( $r['donor_id'] ?? 0 ) ? self::donor_name( $r ) : __( 'Anonymous', 'flx-donate' ),
					'what'       => $what,
					'date'       => flx_gql_datetime( self::s( $r['created_at'] ?? '' ) ),
					'amount'     => flx_gql_money( self::f( $r['group_amount'] ?? ( $r['amount'] ?? 0 ) ), self::s( $r['currency'] ?? '' ) ),
					'amountLabel' => self::s( flxb_money( self::f( $r['group_amount'] ?? ( $r['amount'] ?? 0 ) ), self::s( $r['currency'] ?? '' ) ) ),
					'url'        => FLXB_Admin_Url::donation( self::i( $r['id'] ?? 0 ) ),
				);
			}
		}
		$csv = array_filter(
			array(
				'action'   => 'flxb_csv',
				's'        => $f['search'],
				'from'     => $f['from'],
				'to'       => $f['to'],
				'status'   => $f['status'],
				'type'     => $f['type'],
				'dtype'    => $f['type_id'],
				'form'     => $f['form_id'],
				'donor_id' => $f['donor_id'],
				'ref'      => $own ? '' : $f['ref_user_id'],
			),
			static fn( $v ): bool => '' !== $v
		);
		return array(
			'sum'                  => flx_gql_money( self::sum( $f ), self::s( flxb_default_currency() ) ),
			'sumLabel'             => self::s( flxb_money( self::sum( $f ) ) ),
			'trashCount'           => $manage && 'trash' !== $f['status'] ? self::i( FLXB_Donations::trash_count() ) : 0,
			'statuses'             => $statuses,
			'donationTypes'        => $types,
			'forms'                => $forms,
			'referrals'            => $refs,
			'awaiting'             => $awaiting,
			'ownOnly'              => $own,
			'canManage'            => $manage,
			'canDeletePermanently' => $manage && current_user_can( 'manage_options' ),
			'canAdd'               => (bool) flxb_can_manage(),
			'csvUrl'               => wp_nonce_url( add_query_arg( $csv, admin_url( 'admin-post.php' ) ), 'flxb_csv' ),
			'addUrl'               => FLXB_Admin_Url::page( 'flxb-add' ),
		);
	}

	/**
	 * 2.5.0: trash, restore or delete donations. An order's donations always move together, with the order.
	 *
	 * @param list<int> $ids    Donation ids.
	 * @param string    $action trash | restore | delete.
	 * @return array{done: int, message: string}|WP_Error
	 */
	public static function bulk( array $ids, string $action ) {
		if ( ! self::can_bulk() || ! in_array( $action, array( 'trash', 'restore', 'delete' ), true ) ) {
			return new WP_Error( 'flxb_forbidden', __( 'Sorry, you are not allowed to do that.', 'flx-donate' ), array( 'graphql_code' => 'FORBIDDEN' ) );
		}
		if ( 'delete' === $action && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'flxb_forbidden', __( 'Sorry, you are not allowed to do that.', 'flx-donate' ), array( 'graphql_code' => 'FORBIDDEN' ) );
		}
		$done = 0;
		$seen = array();
		foreach ( array_unique( array_filter( array_map( 'absint', $ids ) ) ) as $id ) {
			$d = self::arr( FLXB_Donations::get( $id ) );
			if ( ! $d ) {
				continue;
			}
			$oid = self::i( $d['wc_order_id'] ?? 0 );
			if ( $oid ) {
				if ( isset( $seen[ $oid ] ) ) {
					continue;
				}
				$seen[ $oid ] = true;
				$order        = function_exists( 'wc_get_order' ) ? wc_get_order( $oid ) : null;
				if ( $order && ! current_user_can( 'delete_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
					continue;
				}
				if ( $order instanceof WC_Order ) {
					if ( 'trash' === $action ) {
						$order->delete( false ); // moves the order to the trash; the ledger follows
					} elseif ( 'delete' === $action ) {
						$order->delete( true );
					} else {
						$store = $order->get_data_store();
						if ( 'trash' === $order->get_status() && is_callable( array( $store, 'untrash_order' ) ) ) {
							call_user_func( array( $store, 'untrash_order' ), $order );
						} else {
							FLXB_Donations::restore_rows( self::order_ids( $oid ) );
						}
					}
					// Anything the hooks missed (e.g. an order not in the trash) is brought in line here.
					$left = self::order_ids( $oid );
					if ( 'delete' === $action && $left ) {
						FLXB_Donations::delete_rows( $left );
					} elseif ( 'trash' === $action ) {
						FLXB_Donations::trash_rows( $left );
					}
					++$done;
					continue;
				}
				$rows = self::order_ids( $oid ); // the order is gone already
			} else {
				$rows = array( $id );
			}
			if ( 'trash' === $action ) {
				FLXB_Donations::trash_rows( $rows );
			} elseif ( 'delete' === $action ) {
				FLXB_Donations::delete_rows( $rows );
			} else {
				FLXB_Donations::restore_rows( $rows );
			}
			++$done;
		}
		$msg = array(
			/* translators: %s: number of gifts */
			'trash'   => _n( '%s donation moved to the trash.', '%s donations moved to the trash.', $done, 'flx-donate' ),
			/* translators: %s: number of gifts */
			'restore' => _n( '%s donation restored.', '%s donations restored.', $done, 'flx-donate' ),
			/* translators: %s: number of gifts */
			'delete'  => _n( '%s donation deleted permanently.', '%s donations deleted permanently.', $done, 'flx-donate' ),
		);
		if ( $done && function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( array( 'flx-donate:types' ) );
		}
		return array(
			'done'    => $done,
			'message' => sprintf( $msg[ $action ], number_format_i18n( $done ) ),
		);
	}

	/**
	 * Dashboard figures for a period (FLXB_Stats::collect, as the classic dashboard).
	 *
	 * @param string $range 7 | 30 | 90 | custom.
	 * @param string $from  Custom start (Y-m-d).
	 * @param string $to    Custom end (Y-m-d).
	 * @return array<string,mixed>
	 */
	public static function dashboard( string $range, string $from, string $to ): array {
		global $wpdb;
		/** @var wpdb $wpdb */
		$range        = in_array( $range, array( '7', '30', '90', 'custom' ), true ) ? $range : '30';
		$period       = self::arr( FLXB_Stats::range( $range, $from, $to ) );
		$from         = self::s( $period[0] ?? '' );
		$to           = self::s( $period[1] ?? '' );
		$d            = self::arr( FLXB_Stats::collect( $from, $to ) );
		$all          = (bool) flxb_can_view();
		$num          = static fn( $v ): float => is_numeric( $v ) ? round( (float) $v, 2 ) : 0.0;
		$totals       = static function ( $t ) use ( $num ): array {
			$t = self::arr( $t );
			return array(
				'total'     => $num( $t['total'] ?? 0 ),
				'gifts'     => is_numeric( $t['gifts'] ?? null ) ? (int) $t['gifts'] : 0,
				'donors'    => is_numeric( $t['donors'] ?? null ) ? (int) $t['donors'] : 0,
				'newDonors' => is_numeric( $t['new_donors'] ?? null ) ? (int) $t['new_donors'] : 0,
				'average'   => $num( $t['average'] ?? 0 ),
			);
		};
		$bars         = static function ( $rows ) use ( $num ): array {
			$out = array();
			foreach ( self::arr( $rows ) as $r ) {
				if ( is_array( $r ) ) {
					$out[] = array(
						'id'     => is_numeric( $r['id'] ?? null ) ? (int) $r['id'] : 0,
						'label'  => is_scalar( $r['label'] ?? null ) ? (string) $r['label'] : '',
						'amount' => $num( $r['amount'] ?? 0 ),
						'gifts'  => is_numeric( $r['gifts'] ?? null ) ? (int) $r['gifts'] : 0,
					);
				}
			}
			return $out;
		};
		$series = array();
		foreach ( self::arr( $d['series'] ?? null ) as $r ) {
			if ( is_array( $r ) ) {
				$series[] = array(
					'day'       => self::s( $r['day'] ?? '' ),
					'label'     => self::s( $r['label'] ?? '' ),
					'oneTime'   => $num( $r['one_time'] ?? 0 ),
					'recurring' => $num( $r['recurring'] ?? 0 ),
					'gifts'     => self::i( $r['gifts'] ?? 0 ),
				);
			}
		}
		$split = self::arr( $d['split'] ?? null );
		$slice = static function ( $v ) use ( $num ): array {
			$v = array_values( self::arr( $v ) );
			return array(
				'amount' => $num( $v[0] ?? 0 ),
				'gifts'  => is_numeric( $v[1] ?? null ) ? (int) $v[1] : 0,
			);
		};
		$top = array();
		foreach ( self::arr( $d['top'] ?? null ) as $r ) {
			if ( is_array( $r ) ) {
				/* flxb_dashboard alone: overall totals, no donor contacts or links into screens they cannot open. */
				$top[] = array(
					'id'      => self::i( $r['id'] ?? 0 ),
					'name'    => self::s( $r['name'] ?? '' ),
					'contact' => $all ? self::s( $r['contact'] ?? '' ) : '',
					'amount'  => $num( $r['amount'] ?? 0 ),
					'gifts'   => self::i( $r['gifts'] ?? 0 ),
					'url'     => $all ? self::s( $r['url'] ?? '' ) : '',
				);
			}
		}
		$subs     = self::arr( $d['subs'] ?? null );
		$currency = self::s( $d['currency'] ?? flxb_default_currency() );
		$has_any  = (bool) $wpdb->get_var( 'SELECT id FROM ' . self::s( flxb_table( 'donations' ) ) . ' LIMIT 1' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$csv      = $all ? wp_nonce_url( add_query_arg( array( 'action' => 'flxb_csv', 'from' => $from, 'to' => $to ), admin_url( 'admin-post.php' ) ), 'flxb_csv' ) : '';
		return array(
			'from'          => $from,
			'to'            => $to,
			'fromLabel'     => self::s( flx_date( (int) strtotime( $from . ' 12:00:00' ) ) ),
			'toLabel'       => self::s( flx_date( (int) strtotime( $to . ' 12:00:00' ) ) ),
			'currency'      => $currency,
			'symbol'        => self::s( flxb_currency_symbol( $currency ) ),
			'hasAny'        => $has_any,
			'allDonors'     => self::i( $d['all_donors'] ?? 0 ),
			'now'           => $totals( $d['now'] ?? null ),
			'prev'          => $totals( $d['prev'] ?? null ),
			'mrr'           => $num( $subs['mrr'] ?? 0 ),
			'activeSubscriptions' => self::i( $subs['active'] ?? 0 ),
			'pastDueSubscriptions' => self::i( $subs['past_due'] ?? 0 ),
			'series'        => $series,
			'oneTime'       => $slice( $split['one_time'] ?? null ),
			'recurring'     => $slice( $split['recurring'] ?? null ),
			'byType'        => $bars( $d['types'] ?? null ),
			'byForm'        => $bars( $d['forms'] ?? null ),
			'topDonors'     => $top,
			'canList'       => $all,
			'csvUrl'        => $csv,
			'listUrl'       => $all ? FLXB_Admin_Url::page( 'flxb-donations' ) : '',
			'donorsUrl'     => $all ? FLXB_Admin_Url::page( 'flxb-donors' ) : '',
			'importUrl'     => FLXB_Admin_Url::page( 'flxb-settings', array( 'tab' => 'import' ) ),
			'ownOnly'       => self::own_only(),
		);
	}
}
