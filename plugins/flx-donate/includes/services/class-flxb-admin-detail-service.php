<?php
/**
 * Donation page of the admin screen (GraphQL part b2): everything the classic detail page shows,
 * as data, and its actions.
 *
 * The wp-admin screen (packages/modules/src/donate/Detail.tsx) reads view() and calls the actions;
 * the texts are made here, the same ones the classic page printed (FLXB_Page_Donations::detail()).
 * Rights are the classic ones:
 * - view: may_see() of the list (flx_bagis_view, or flxb_view_own for the staff's own referrals);
 * - status, trash, names, referral: flxb_can_manage() and not own-only (WooCommerce order: also
 *   edit_shop_orders or manage_woocommerce);
 * - approve / reject a receipt: FLXB_Approval::can();
 * - certificates, refund, order notes: edit_shop_orders;
 * - box layout: the person's own.
 *
 * Boxes of other plugins (the WooCommerce order screen's boxes, flxb_detail_boxes, the payment
 * plugin's money form and the extra tabs) are not data: the page prints them as ready HTML next to
 * the screen and the screen places them in their box (islands()).
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type FlxbFact array{label: string, text: string, url: string, look: string, tone: string, hint: string}
 * @phpstan-type FlxbIsland array{id: string, title: string, col: string, html: string}
 */
final class FLXB_Admin_Detail_Service {

	/** Islands collected for the page that is being printed (screen render). */
	/** @var list<FlxbIsland>|null */
	private static ?array $islands = null;

	private static function boot(): void {
		if ( ! class_exists( 'FLXB_Page_Donations' ) ) {
			require_once dirname( __DIR__ ) . '/admin/class-flxb-admin.php';
		}
	}

	/**
	 * @param mixed $v Value.
	 * @return array<mixed>
	 */
	private static function arr( $v ): array {
		return is_array( $v ) ? $v : array();
	}

	/**
	 * A database row with string keys, or null.
	 *
	 * @param mixed $v Value.
	 * @return array<string,mixed>|null
	 */
	private static function row( $v ): ?array {
		if ( ! is_array( $v ) ) {
			return null;
		}
		$out = array();
		foreach ( $v as $k => $x ) {
			$out[ (string) $k ] = $x;
		}
		return $out;
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
	 * One fact: label and text, optionally a link, a look (text, code, strong, muted, pill, chip,
	 * chip-donor) and a tone for pills.
	 *
	 * @return FlxbFact
	 */
	private static function fact( string $label, string $text, string $look = 'text', string $url = '', string $tone = '', string $hint = '' ): array {
		return array(
			'label' => $label,
			'text'  => $text,
			'url'   => $url,
			'look'  => $look,
			'tone'  => $tone,
			'hint'  => $hint,
		);
	}

	private static function tone( string $tone ): string {
		return in_array( $tone, array( 'ok', 'pending', 'failed', 'muted', 'receipt', 'recurring' ), true ) ? $tone : 'muted';
	}

	private static function money( float $amount, string $currency, int $decimals = 0 ): string {
		return self::s( $decimals ? flxb_money( $amount, $currency, $decimals ) : flxb_money( $amount, $currency ) );
	}

	private static function date( string $mysql ): string {
		return '' !== $mysql ? self::s( flxb_date( $mysql ) ) : '';
	}

	private static function user_name( int $id ): string {
		$u = $id ? get_userdata( $id ) : false;
		return $u ? (string) $u->display_name : ( $id ? '#' . $id : __( 'System', 'flx-donate' ) );
	}

	private static function initial( string $name ): string {
		return mb_strtoupper( mb_substr( $name, 0, 1 ) );
	}

	/**
	 * May the current user open this donation? (FLXB_Page_Donations::may_see())
	 *
	 * @param array<string,mixed> $d Donation.
	 */
	public static function may_see( array $d ): bool {
		if ( ! FLXB_Refs::can_list() ) {
			return false;
		}
		return ! FLXB_Refs::own_only() || self::i( $d['ref_user_id'] ?? 0 ) === get_current_user_id();
	}

	public static function can_manage(): bool {
		return ! FLXB_Refs::own_only() && (bool) flxb_can_manage();
	}

	/**
	 * The donation, when the current user may open it.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function donation( int $id ): ?array {
		self::boot();
		$d = $id ? FLXB_Donations::get( $id ) : null;
		$d = self::row( $d );
		return $d && self::may_see( $d ) ? $d : null;
	}

	/**
	 * @param array<string,mixed> $d Donation.
	 * @return WC_Order|null
	 */
	private static function order( array $d ) {
		$oid = self::i( $d['wc_order_id'] ?? 0 );
		$o   = $oid && function_exists( 'wc_get_order' ) ? wc_get_order( $oid ) : null;
		return $o instanceof WC_Order ? $o : null;
	}

	/**
	 * @param array<string,mixed> $d Donation.
	 */
	private static function method_label( array $d ): string {
		$order = self::order( $d );
		return $order && '' !== $order->get_payment_method_title() ? $order->get_payment_method_title() : self::s( flxb_provider_label( self::s( $d['provider'] ?? '' ) ) );
	}

	/**
	 * @param array<string,mixed> $d Donation.
	 * @return array<string,mixed>|null
	 */
	private static function payinfo( array $d ): ?array {
		$order = class_exists( 'FLXB_WC_Payinfo' ) ? self::order( $d ) : null;
		if ( ! $order ) {
			return null;
		}
		$info = array();
		foreach ( self::arr( FLXB_WC_Payinfo::get( $order ) ) as $k => $v ) {
			$info[ (string) $k ] = $v;
		}
		return FLXB_WC_Payinfo::worth( $info ) ? $info : null;
	}

	/**
	 * Everything the donation page shows.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function view( int $id ): ?array {
		$d = self::donation( $id );
		if ( ! $d ) {
			return null;
		}
		$id     = self::i( $d['id'] );
		$donor  = self::i( $d['donor_id'] ?? 0 ) ? FLXB_Donors::get( self::i( $d['donor_id'] ) ) : null;
		$donor  = self::row( $donor );
		$camp   = self::i( $d['type_id'] ?? 0 ) ? FLXB_Types::get( self::i( $d['type_id'] ) ) : null;
		$camp   = self::row( $camp );
		$sibs   = array_values( array_filter( array_map( array( __CLASS__, 'row' ), self::arr( FLXB_Donations::siblings( $d ) ) ) ) );
		$multi  = count( $sibs ) > 1;
		$cur    = self::s( $d['currency'] ?? '' );
		$amount = $multi ? array_sum( array_map( static fn( $s ): float => self::f( $s['amount'] ?? 0 ), $sibs ) ) : self::f( $d['amount'] ?? 0 );
		$state  = self::arr( flxb_donation_state( $d ) );
		$paid   = in_array( $d['status'], FLXB_Donations::PAID, true );
		$order  = self::order( $d );
		return array(
			'databaseId'  => $id,
			/* translators: %s: amount */
			'title'       => sprintf( __( 'Donation of %s', 'flx-donate' ), self::money( $amount, $cur ) ),
			'date'        => self::date( self::s( $d['created_at'] ?? '' ) ),
			'statusLabel' => self::s( $state[1] ?? '' ),
			'tone'        => self::tone( self::s( $state[0] ?? '' ) ),
			'kind'        => 'recurring' === ( $d['type'] ?? '' ) ? 'recurring' : 'one_time',
			'kindLabel'   => self::s( flxb_type_label( self::s( $d['type'] ?? '' ) ) ),
			'multi'       => $multi,
			'trashed'     => 'trash' === ( $d['status'] ?? '' ),
			'receiptUrl'  => $paid ? add_query_arg( array( 'action' => 'flxb_receipt', 'id' => $id, '_wpnonce' => wp_create_nonce( 'flxb_receipt_' . $id ) ), admin_url( 'admin-post.php' ) ) : '',
			'listUrl'     => FLXB_Admin_Url::page( 'flxb-donations' ),
			'hero'        => self::hero( $d, $donor, $camp ),
			'approval'    => self::approval( $d, $sibs, $donor ),
			'lines'       => self::lines( $d, $sibs, $camp, $order ),
			'payment'     => self::payment( $d, $order ),
			'contacts'    => self::contacts( $d ),
			'receipts'    => self::receipts( $d ),
			'history'     => self::history( $d ),
			'donor'       => self::donor( $donor ),
			'referral'    => self::referral( $d ),
			'manage'      => self::manage( $d, $sibs ),
			'notes'       => self::notes( $d, $order ),
			'islands'     => self::island_list( $d, $order ),
			'layout'      => self::layout(),
		);
	}

	/**
	 * @param array<string,mixed> $d Donation.
	 * @param array<string,mixed>|null $donor Donor.
	 * @param array<string,mixed>|null $camp Donation type.
	 * @return list<FlxbFact>
	 */
	private static function hero( array $d, ?array $donor, ?array $camp ): array {
		$ts    = strtotime( self::s( $d['created_at'] ?? '' ) . ' UTC' );
		$ref   = self::i( $d['ref_user_id'] ?? 0 );
		$dref  = ! $ref && $donor ? self::i( FLXB_Refs::donor_ref( self::i( $donor['id'] ) ) ) : 0;
		$name  = $donor ? self::s( FLXB_Admin::donor_name( $donor, '' ) ) : __( 'Anonymous', 'flx-donate' );
		$pi    = self::payinfo( $d );
		$state = self::arr( flxb_donation_state( $d ) );
		$out   = array(
			self::fact( __( 'Status', 'flx-donate' ), self::s( $state[1] ?? '' ), 'pill', '', self::tone( self::s( $state[0] ?? '' ) ) ),
			self::fact( __( 'Donor', 'flx-donate' ), $name, 'text', $donor && FLXB_Refs::can_donors() ? self::s( FLXB_Admin_Url::donor( self::i( $donor['id'] ) ) ) : '' ),
		);
		if ( $ref ) {
			$out[] = self::fact( __( 'Referral', 'flx-donate' ), self::s( FLXB_Refs::name( $ref ) ), 'chip' );
		} elseif ( $dref ) {
			/* translators: %s: referral name */
			$out[] = self::fact( __( 'Referral', 'flx-donate' ), self::s( FLXB_Refs::name( $dref ) ), 'chip-donor', '', '', sprintf( __( 'The donor\'s earlier gifts came through %s.', 'flx-donate' ), self::s( FLXB_Refs::name( $dref ) ) ) );
		} else {
			$out[] = self::fact( __( 'Referral', 'flx-donate' ), __( 'None', 'flx-donate' ), 'muted' );
		}
		$out[] = self::fact( __( 'Payment', 'flx-donate' ), $pi ? self::s( FLXB_WC_Payinfo::summary( $pi ) ) : self::method_label( $d ) );
		$out[] = self::fact( __( 'Donation type', 'flx-donate' ), $camp ? self::s( $camp['title'] ?? '' ) : self::s( flxb_type_label( self::s( $d['type'] ?? '' ) ) ) );
		$out[] = self::fact( __( 'Received', 'flx-donate' ), $ts ? self::s( flx_time_ago( $ts ) ) : '—' );
		return $out;
	}

	/**
	 * Receipt / payment awaiting approval (FLXB_Approval::card()).
	 *
	 * @param array<string,mixed> $d Donation.
	 * @param list<array<string,mixed>> $sibs Gifts of the same order.
	 * @param array<string,mixed>|null $donor Donor.
	 * @return array<string,mixed>|null
	 */
	private static function approval( array $d, array $sibs, ?array $donor ): ?array {
		$p = FLXB_Approval::pending( $d );
		if ( ! $p ) {
			return null;
		}
		$p     = self::arr( $p );
		$order = $p['order'] ?? null;
		$order = $order instanceof WC_Order ? $order : null;
		$total = $order ? (float) $order->get_total() : array_sum( array_map( static fn( $s ): float => self::f( $s['amount'] ?? 0 ), $sibs ) );
		$cur   = $order ? $order->get_currency() : self::s( $d['currency'] ?? '' );
		$bits  = array( self::money( $total, $cur, 2 ), $order ? $order->get_payment_method_title() : self::s( flxb_provider_label( self::s( $d['provider'] ?? '' ) ) ) );
		if ( $order ) {
			/* translators: %s: order number */
			$bits[] = sprintf( __( 'order #%s', 'flx-donate' ), $order->get_order_number() );
		}
		$files = array();
		foreach ( self::arr( $p['receipts'] ?? array() ) as $r ) {
			$r       = self::arr( $r );
			$files[] = array(
				'url'  => self::s( $r['url'] ?? '' ),
				'kind' => in_array( $r['kind'] ?? '', array( 'pdf', 'image' ), true ) ? self::s( $r['kind'] ) : 'file',
				'name' => self::s( $r['name'] ?? '' ),
				'meta' => self::s( $r['meta'] ?? '' ),
				'warn' => self::s( $r['warn'] ?? '' ),
			);
		}
		$to = array_values(
			array_filter(
				array(
					$donor && '' !== self::s( $donor['phone'] ?? '' ) ? 'WhatsApp' : '',
					$donor && is_email( self::s( $donor['email'] ?? '' ) ) ? __( 'e-mail', 'flx-donate' ) : '',
				)
			)
		);
		return array(
			'title'    => $files ? __( 'Receipt awaiting approval', 'flx-donate' ) : __( 'Awaiting payment', 'flx-donate' ),
			'hint'     => implode( ' · ', array_filter( $bits ) ),
			'canAct'   => (bool) FLXB_Approval::can( $d ),
			'receipts' => $files,
			'notifyTo' => implode( ', ', $to ),
		);
	}

	/**
	 * Items table, totals and the names on whose behalf (FLXB_Page_Donations::lines_card()).
	 *
	 * @param array<string,mixed> $d Donation.
	 * @param list<array<string,mixed>> $sibs Gifts of the same order.
	 * @param array<string,mixed>|null $camp Donation type.
	 * @param WC_Order|null $order Order.
	 * @return array<string,mixed>
	 */
	private static function lines( array $d, array $sibs, ?array $camp, $order ): array {
		$multi   = count( $sibs ) > 1;
		$cur     = self::s( $d['currency'] ?? '' );
		$rows    = array();
		$wc_bits = static function ( array $s ) use ( $order ): array {
			$item = $order && class_exists( 'FLXB_WC_Ledger' ) ? $order->get_item( self::i( FLXB_WC_Ledger::item_of( $s ) ) ) : null;
			$bits = array();
			if ( $item ) {
				foreach ( $item->get_formatted_meta_data() as $m ) {
					if ( ! is_object( $m ) ) {
						continue;
					}
					$bits[] = self::fact( rtrim( trim( wp_strip_all_tags( self::s( $m->display_key ?? '' ) ) ), ':' ), trim( wp_strip_all_tags( self::s( $m->display_value ?? '' ) ) ) );
				}
			}
			return array( $item ?: null, $bits );
		};
		$images  = array();
		$image   = static function ( int $type_id ) use ( &$images ): string {
			if ( ! isset( $images[ $type_id ] ) ) {
				$t                  = $type_id ? FLXB_Types::get( $type_id ) : null;
				$images[ $type_id ] = is_array( $t ) && self::i( $t['image_id'] ?? 0 ) ? self::s( wp_get_attachment_image_url( self::i( $t['image_id'] ), 'thumbnail' ) ) : '';
			}
			return $images[ $type_id ];
		};
		$type_url = static function ( int $type_id ): string {
			return $type_id && flxb_can_view() ? self::s( FLXB_Admin_Url::page( 'flxb-types', array( 'edit' => $type_id ) ) ) : '';
		};
		$row      = static function ( array $r ) use ( $cur ): array {
			$c = '' !== self::s( $r['cur'] ?? '' ) ? self::s( $r['cur'] ) : $cur;
			return $r + array(
				'unitLabel'  => self::money( self::f( $r['unit'] ?? 0 ), $c, 2 ),
				'qtyLabel'   => '× ' . number_format_i18n( self::i( $r['qty'] ?? 0 ) ),
				'totalLabel' => self::money( self::f( $r['total'] ?? 0 ), $c, 2 ),
			);
		};
		if ( $multi ) {
			foreach ( $sibs as $s ) {
				list( $item, $bits ) = $wc_bits( $s );
				$t      = self::i( $s['type_id'] ?? 0 ) ? FLXB_Types::get( self::i( $s['type_id'] ) ) : null;
				$name   = $item ? $item->get_name() : ( is_array( $t ) ? self::s( $t['title'] ?? '' ) : ( '' !== self::s( $s['option_label'] ?? '' ) ? self::s( $s['option_label'] ) : __( 'Donation', 'flx-donate' ) ) );
				$here   = self::i( $s['id'] ) === self::i( $d['id'] );
				$qty    = max( 1, self::i( $s['quantity'] ?? 1 ) );
				$st     = self::arr( flxb_status_label( self::s( $s['status'] ?? '' ) ) );
				$rows[] = $row(
					array(
						'image'       => $image( self::i( $s['type_id'] ?? 0 ) ),
						'name'        => $name,
						'url'         => $here ? '' : FLXB_Admin_Url::donation( self::i( $s['id'] ) ),
						'current'     => $here,
						'bits'        => $bits,
						'statusLabel' => self::s( $st[1] ?? '' ),
						'tone'        => self::tone( self::s( $st[0] ?? '' ) ),
						'share'       => null,
						'unit'        => self::f( $s['unit_price'] ?? 0 ) > 0 ? self::f( $s['unit_price'] ) : self::f( $s['amount'] ?? 0 ) / $qty,
						'qty'         => $qty,
						'total'       => self::f( $s['amount'] ?? 0 ),
						'cur'         => self::s( $s['currency'] ?? '' ),
					)
				);
			}
		} else {
			$items              = array_values( array_filter( self::arr( FLXB_Items::for_donation( self::i( $d['id'] ) ) ), 'is_array' ) );
			list( $item, $bits ) = $wc_bits( $d );
			$title              = $camp ? self::s( $camp['title'] ?? '' ) : '';
			if ( ! $items ) {
				$qty   = max( 1, self::i( $d['quantity'] ?? 1 ) );
				$items = array(
					array(
						'id'               => 0,
						'option_label'     => self::s( $d['option_label'] ?? '' ),
						'unit'             => '',
						'quantity'         => $qty,
						'unit_price'       => self::f( $d['unit_price'] ?? 0 ) > 0 ? self::f( $d['unit_price'] ) : self::f( $d['amount'] ?? 0 ) / $qty,
						'amount'           => self::f( $d['amount'] ?? 0 ),
						'currency'         => $cur,
						'beneficiary_name' => '',
						'code'             => '',
						'status'           => '',
						'place_name'       => '',
					),
				);
			}
			$one = 1 === count( $items );
			foreach ( $items as $it ) {
				$opt  = '' !== self::s( $it['option_label'] ?? '' ) ? self::s( $it['option_label'] ) : __( 'Donation', 'flx-donate' );
				$name = $one && $item ? $item->get_name() : ( '' !== $title && $title !== $opt ? $title . ' · ' . $opt : $opt );
				$b    = $one && $bits ? $bits : array();
				if ( ! $b ) {
					if ( '' !== $title && $one && ! $item && $title !== $opt ) {
						$b[] = self::fact( __( 'Option', 'flx-donate' ), $opt );
					}
					if ( '' !== self::s( $it['unit'] ?? '' ) ) {
						$b[] = self::fact( __( 'Quantity', 'flx-donate' ), number_format_i18n( self::i( $it['quantity'] ?? 0 ) ) . ' ' . self::s( flxb_unit_label( self::s( $it['unit'] ), self::i( $it['quantity'] ?? 0 ) ) ) );
					}
					if ( '' !== self::s( $it['beneficiary_name'] ?? '' ) ) {
						$b[] = self::fact( __( 'On behalf of', 'flx-donate' ), self::s( $it['beneficiary_name'] ) );
					}
				}
				$share = null;
				if ( flxb_kurban_enabled() && '' !== self::s( $it['code'] ?? '' ) ) {
					$ist   = self::s( $it['status'] ?? '' );
					$all   = self::arr( FLXB_Track::statuses( self::i( $d['type_id'] ?? 0 ) ) );
					$tones = array( 'pending' => 'pending', 'approved' => 'recurring', 'sent' => 'recurring', 'done' => 'ok', 'delivered' => 'ok', 'canceled' => 'failed' );
					$share = array(
						'code'        => self::s( $it['code'] ),
						'url'         => FLXB_Admin_Url::page( FLXB_Page_Track::SLUG, array( 'item' => self::i( $it['id'] ?? 0 ) ) ),
						'statusLabel' => self::s( $all[ $ist ] ?? $ist ),
						'tone'        => $tones[ $ist ] ?? 'muted',
						'place'       => self::s( $it['place_name'] ?? '' ),
					);
				}
				$qty    = max( 1, self::i( $it['quantity'] ?? 1 ) );
				$rows[] = $row(
					array(
						'image'       => $image( self::i( $d['type_id'] ?? 0 ) ),
						'name'        => $name,
						'url'         => $type_url( self::i( $d['type_id'] ?? 0 ) ),
						'current'     => false,
						'bits'        => $b,
						'statusLabel' => '',
						'tone'        => 'muted',
						'share'       => $share,
						'unit'        => self::f( $it['unit_price'] ?? 0 ) > 0 ? self::f( $it['unit_price'] ) : self::f( $it['amount'] ?? 0 ) / $qty,
						'qty'         => $qty,
						'total'       => self::f( $it['amount'] ?? 0 ),
						'cur'         => '' !== self::s( $it['currency'] ?? '' ) ? self::s( $it['currency'] ) : $cur,
					)
				);
			}
		}
		$sum = array_sum( array_map( static fn( array $r ): float => self::f( $r['total'] ?? 0 ), $rows ) );
		foreach ( $rows as &$r ) {
			unset( $r['cur'] );
		}
		unset( $r );
		return array(
			'hint'   => $multi ? __( 'One basket: every item in it is paid and changes together.', 'flx-donate' ) : '',
			'rows'   => $rows,
			'totals' => self::totals( $d, $sibs, $order, $sum ),
			'names'  => self::names( $d, $multi ),
		);
	}

	/**
	 * Totals under the items (FLXB_Page_Donations::totals()): look is '', total, paid, none, wait or refund.
	 *
	 * @param array<string,mixed> $d Donation.
	 * @param list<array<string,mixed>> $sibs Gifts of the same order.
	 * @param WC_Order|null $order Order.
	 * @return array{lines: list<array{label: string, value: string, look: string}>, paid: list<array{label: string, value: string, look: string}>, via: string}
	 */
	private static function totals( array $d, array $sibs, $order, float $sum ): array {
		$cur   = self::s( $d['currency'] ?? '' );
		$multi = count( $sibs ) > 1;
		$line  = static fn( string $l, string $v, string $look = '' ): array => array( 'label' => $l, 'value' => $v, 'look' => $look );
		$lines = array( $line( __( 'Items subtotal', 'flx-donate' ), self::money( $sum, $cur, 2 ) ) );
		$total = $sum;
		if ( $order && ( $multi || 1 === count( $order->get_items() ) ) ) {
			foreach ( $order->get_fees() as $fee ) {
				$lines[] = $line( $fee->get_name(), self::money( (float) $fee->get_total(), $cur, 2 ) );
			}
			$total = (float) $order->get_total();
		}
		if ( abs( $total - $sum ) > 0.004 || count( $lines ) > 1 ) {
			$lines[] = $line( __( 'Total', 'flx-donate' ), self::money( $total, $cur, 2 ), 'total' );
		} else {
			$lines[0] = $line( __( 'Total', 'flx-donate' ), self::money( $total, $cur, 2 ), 'total' );
		}
		$states = $multi ? array_map( static fn( $s ): string => self::s( $s['status'] ?? '' ), $sibs ) : array( self::s( $d['status'] ?? '' ) );
		$paid   = (bool) array_intersect( $states, array_merge( FLXB_Donations::PAID, array( 'refunded' ) ) );
		$when   = '';
		if ( $order && $order->get_date_paid() ) {
			$when = self::date( gmdate( 'Y-m-d H:i:s', $order->get_date_paid()->getTimestamp() ) );
		} elseif ( $paid ) {
			$when = self::date( self::s( $d['created_at'] ?? '' ) );
		}
		$how = self::method_label( $d );
		$out = array();
		$via = $how;
		if ( $paid ) {
			$out[] = $line( __( 'Paid', 'flx-donate' ), self::money( $total, $cur, 2 ), 'paid' );
			/* translators: 1: payment method, 2: date */
			$via = '' !== $when ? sprintf( __( 'via %1$s · %2$s', 'flx-donate' ), $how, $when ) : $how;
		} elseif ( in_array( $d['status'], array( 'canceled', 'failed', 'trash' ), true ) ) {
			$out[] = $line( __( 'Paid', 'flx-donate' ), self::money( 0, $cur, 2 ), 'none' );
			$via   = '';
		} else {
			$out[] = $line( __( 'Awaiting payment', 'flx-donate' ), self::money( $total, $cur, 2 ), 'wait' );
		}
		$refunded = $order ? (float) $order->get_total_refunded() : 0.0;
		if ( $refunded > 0 ) {
			$out[] = $line( __( 'Refunded', 'flx-donate' ), '−' . self::money( $refunded, $cur, 2 ), 'refund' );
			$out[] = $line( __( 'Net', 'flx-donate' ), self::money( $total - $refunded, $cur, 2 ), 'total' );
		}
		return array(
			'lines' => $lines,
			'paid'  => $out,
			'via'   => $via,
		);
	}

	/**
	 * Names on whose behalf, editable (FLXB_Page_Donations::names_form()); null when not offered.
	 *
	 * @param array<string,mixed> $d Donation.
	 * @return list<array{itemId: int, label: string, value: string}>|null
	 */
	private static function names( array $d, bool $multi ): ?array {
		if ( $multi || ! self::can_manage() ) {
			return null;
		}
		$items = array_values( array_filter( self::arr( FLXB_Items::for_donation( self::i( $d['id'] ) ) ), 'is_array' ) );
		if ( ! $items ) {
			return null;
		}
		$named = (bool) array_filter( array_map( static fn( $it ): string => self::s( $it['beneficiary_name'] ?? '' ), $items ), static fn( string $v ): bool => '' !== $v );
		if ( ! $named && self::i( $d['type_id'] ?? 0 ) ) {
			$t     = FLXB_Types::get( self::i( $d['type_id'] ) );
			$named = is_array( $t ) && FLXB_Types::requires_proxy( $t );
		}
		if ( ! $named ) {
			return null;
		}
		$out = array();
		foreach ( $items as $i => $it ) {
			$out[] = array(
				'itemId' => self::i( $it['id'] ?? 0 ),
				/* translators: 1: number, 2: option */
				'label'  => sprintf( __( '%1$d. %2$s', 'flx-donate' ), $i + 1, '' !== self::s( $it['option_label'] ?? '' ) ? self::s( $it['option_label'] ) : __( 'Donation', 'flx-donate' ) ),
				'value'  => self::s( $it['beneficiary_name'] ?? '' ),
			);
		}
		return $out;
	}

	/**
	 * Payment card: facts, certificates, note and the "⋯" menu (FLXB_Page_Donations::detail(), more_menu()).
	 *
	 * @param array<string,mixed> $d Donation.
	 * @param WC_Order|null $order Order.
	 * @return array<string,mixed>
	 */
	private static function payment( array $d, $order ): array {
		$multi = count( self::arr( FLXB_Donations::siblings( $d ) ) ) > 1;
		$cur   = self::s( $d['currency'] ?? '' );
		$sub   = self::i( $d['subscription_id'] ?? 0 ) ? FLXB_Subscriptions::get( self::i( $d['subscription_id'] ) ) : null;
		$sub   = is_array( $sub ) ? $sub : null;
		$meta  = self::arr( FLXB_Donations::meta( $d ) );
		$facts = array();
		if ( $multi ) {
			$facts[] = self::fact( __( 'Amount', 'flx-donate' ), self::money( self::f( $d['amount'] ?? 0 ), $cur, 2 ) );
		}
		$facts[] = self::fact( __( 'Date', 'flx-donate' ), self::date( self::s( $d['created_at'] ?? '' ) ) );
		$pinfo   = self::payinfo( $d );
		if ( $pinfo ) {
			$p   = $pinfo;
			$add = static function ( string $label, string $text, string $look = 'text' ) use ( &$facts ): void {
				if ( '' !== $text ) {
					$facts[] = self::fact( $label, $text, $look );
				}
			};
			$add( __( 'Payment method', 'flx-donate' ), self::s( $p['method'] ?? '' ) );
			$add( __( 'Payment provider', 'flx-donate' ), self::s( $p['provider'] ?? '' ) );
			$add( __( 'Card', 'flx-donate' ), self::s( $p['card'] ?? '' ), 'code' );
			$add( __( 'Card holder', 'flx-donate' ), self::s( $p['owner'] ?? '' ) );
			$add( __( 'Installments', 'flx-donate' ), self::s( FLXB_WC_Payinfo::installment_label( $p['installment'] ?? 0 ) ) );
			$add( __( 'Amount charged', 'flx-donate' ), self::s( FLXB_WC::money_text( self::f( $p['amount'] ?? 0 ), self::s( $p['currency'] ?? '' ) ) ) );
			$add( __( 'Bank reference', 'flx-donate' ), self::s( $p['bank_ref'] ?? '' ), 'code' );
			$add( __( 'Authorization code', 'flx-donate' ), self::s( $p['auth_code'] ?? '' ), 'code' );
			$add( __( 'Transaction ID', 'flx-donate' ), self::s( $p['txn'] ?? '' ), 'code' );
			if ( null !== ( $p['test'] ?? null ) ) {
				$facts[] = $p['test'] ? self::fact( __( 'Mode', 'flx-donate' ), __( 'Test', 'flx-donate' ), 'strong' ) : self::fact( __( 'Mode', 'flx-donate' ), __( 'Live', 'flx-donate' ) );
			}
		} else {
			$facts[] = self::fact( __( 'Payment provider', 'flx-donate' ), self::method_label( $d ) );
		}
		if ( '' !== self::s( $d['provider_ref'] ?? '' ) && ! self::i( $d['wc_order_id'] ?? 0 ) ) {
			$facts[] = self::fact( __( 'Provider reference', 'flx-donate' ), self::s( $d['provider_ref'] ), 'code' );
		}
		if ( '' !== self::s( $d['track'] ?? '' ) ) {
			$facts[] = self::fact( __( 'Confirmation code', 'flx-donate' ), self::s( $d['track'] ), 'code' );
		}
		if ( self::i( $d['form_id'] ?? 0 ) ) {
			$facts[] = self::fact( __( 'Form', 'flx-donate' ), self::s( flxb_form_title( self::i( $d['form_id'] ) ) ) );
		}
		$source = self::s( FLXB_Page_Donations::source_label( self::s( $d['source'] ?? '' ) ) );
		if ( ! empty( $meta['source_detail'] ) ) {
			$sd     = array(
				'flx-chat-bot' => __( 'Call center · WhatsApp assistant', 'flx-donate' ),
				'flx-chat'     => __( 'Call center · agent', 'flx-donate' ),
			);
			$source = $sd[ self::s( $meta['source_detail'] ) ] ?? $source . ' · ' . self::s( $meta['source_detail'] );
		}
		$facts[] = self::fact( __( 'Recorded from', 'flx-donate' ), $source );
		if ( $sub ) {
			$st      = self::arr( flxb_status_label( self::s( $sub['status'] ?? '' ) ) );
			$facts[] = self::fact(
				__( 'Recurring donation', 'flx-donate' ),
				sprintf( '%1$s · %2$s · %3$s', self::money( self::f( $sub['amount'] ?? 0 ), self::s( $sub['currency'] ?? '' ) ), self::s( flxb_interval_label( self::s( $sub['interval_unit'] ?? '' ) ) ), self::s( $st[1] ?? '' ) ),
				'text',
				flxb_can_view() ? self::s( FLXB_Admin_Url::page( 'flxb-subscriptions', array( 's' => self::s( $sub['provider_ref'] ?? '' ) ) ) ) : ''
			);
		}
		if ( ! empty( $meta['account'] ) ) {
			$facts[] = self::fact( __( 'Account', 'flx-donate' ), self::s( $meta['account'] ) );
		}
		$po = self::arr( $meta['price_override'] ?? null );
		if ( $po ) {
			/* translators: 1: type price, 2: reason */
			$facts[] = self::fact( __( 'Different price', 'flx-donate' ), sprintf( __( 'type price %1$s · %2$s', 'flx-donate' ), self::money( self::f( $po['type_price'] ?? 0 ), $cur ), self::s( $po['reason'] ?? '' ) ) );
		}
		$pc = self::arr( $meta['proxy_consent'] ?? null );
		if ( ! empty( $pc['version'] ) ) {
			/* translators: 1: version, 2: date */
			$facts[] = self::fact( __( 'Proxy consent', 'flx-donate' ), sprintf( __( 'version %1$s · %2$s', 'flx-donate' ), number_format_i18n( self::i( $pc['version'] ) ), self::date( self::s( $pc['accepted_at'] ?? '' ) ) ) );
		}
		if ( ! empty( $meta['created_by'] ) ) {
			$facts[] = self::fact( __( 'Entered by', 'flx-donate' ), self::user_name( self::i( $meta['created_by'] ) ) );
		}
		if ( ! empty( $meta['reference'] ) && is_scalar( $meta['reference'] ) ) {
			$facts[] = self::fact( __( 'Payment reference', 'flx-donate' ), self::s( $meta['reference'] ) );
		}
		$iz = self::arr( $meta['iyzico'] ?? null );
		if ( ! empty( $iz['paymentId'] ) ) {
			$facts[] = self::fact( __( 'Card payment', 'flx-donate' ), 'iyzico ' . self::s( $iz['paymentId'] ) . ( ! empty( $iz['card'] ) ? ' · ' . self::s( $iz['card'] ) : '' ), 'code' );
			if ( ! empty( $iz['refunds'] ) ) {
				$sum     = array_sum( array_map( static fn( $r ): float => self::f( self::arr( $r )['amount'] ?? 0 ), self::arr( $iz['refunds'] ) ) );
				$facts[] = self::fact( __( 'Refunded', 'flx-donate' ), self::money( $sum, $cur, 2 ) );
			}
			if ( ! empty( $iz['canceled_at'] ) ) {
				$facts[] = self::fact( __( 'Canceled', 'flx-donate' ), self::date( self::s( $iz['canceled_at'] ) ) );
			}
		}
		$certs = array();
		if ( '' !== self::s( $d['certificate_code'] ?? '' ) ) {
			$codes = array_values( array_unique( array_filter( array_map( array( __CLASS__, 's' ), self::arr( $meta['certificates'] ?? array() ) ) ) ) );
			$codes = $codes ? $codes : array( self::s( $d['certificate_code'] ) );
			foreach ( $codes as $code ) {
				$c       = flxb_certificates_available() ? flxs_get_certificate_by_code( $code ) : null;
				$c       = is_array( $c ) ? $c : null;
				$certs[] = array(
					'label'   => $c ? ( '' !== self::s( $c['number'] ?? '' ) ? self::s( $c['number'] ) : self::s( $c['code'] ?? '' ) ) . ' · ' . self::s( self::arr( self::arr( $c['data'] ?? null )['values'] ?? null )['ad_soyad'] ?? '' ) : $code,
					'url'     => $c ? self::s( flxs_certificate_url( $c ) ) : '',
					'revoked' => $c && 'revoked' === ( $c['status'] ?? '' ),
				);
			}
		}
		$st   = self::arr( flxb_donation_state( $d ) );
		$menu = null;
		$wcm  = class_exists( 'FLXB_WC_Admin' ) && FLXB_WC::ready() ? FLXB_WC_Admin::donation_menu( $d ) : null;
		if ( is_array( $wcm ) ) {
			$r    = is_array( $wcm['refund'] ?? null ) ? $wcm['refund'] : null;
			$menu = array(
				'orderLabel' => self::s( $wcm['order_label'] ?? '' ),
				'orderUrl'   => self::s( $wcm['order_url'] ?? '' ),
				'refund'     => $r ? array(
					'label' => self::s( $r['label'] ?? '' ),
					'help'  => self::s( $r['help'] ?? '' ),
					'field' => self::s( $r['field'] ?? '' ),
					'max'   => self::f( $r['max'] ?? 0 ),
					'auto'  => __( 'Refund', 'flx-donate' ) === self::s( $r['label'] ?? '' ),
				) : null,
			);
		}
		return array(
			'title'            => $multi ? __( 'This item', 'flx-donate' ) : __( 'Payment', 'flx-donate' ),
			'kindLabel'        => self::s( flxb_type_label( self::s( $d['type'] ?? '' ) ) ),
			'recurring'        => 'recurring' === ( $d['type'] ?? '' ),
			'statusLabel'      => self::s( $st[1] ?? '' ),
			'tone'             => self::tone( self::s( $st[0] ?? '' ) ),
			'facts'            => $facts,
			'certificates'     => $certs,
			'canCertificates'  => (bool) ( $certs && self::i( $d['wc_order_id'] ?? 0 ) && current_user_can( 'edit_shop_orders' ) && class_exists( 'FLXB_WC_Certificates' ) && in_array( $d['status'], FLXB_Donations::PAID, true ) ),
			'note'             => self::s( $meta['note'] ?? '' ),
			'menu'             => $menu,
		);
	}

	/**
	 * @param array<string,mixed> $d Donation.
	 * @return list<array{name: string, phone: string, phoneUrl: string, email: string}>
	 */
	private static function contacts( array $d ): array {
		$m   = self::arr( FLXB_Donations::meta( $d ) );
		$out = array();
		foreach ( self::arr( $m['beneficiary_contacts'] ?? array() ) as $c ) {
			if ( ! is_array( $c ) ) {
				continue;
			}
			$phone = self::s( $c['phone'] ?? '' );
			$email = self::s( $c['email'] ?? '' );
			$out[] = array(
				'name'     => '' !== self::s( $c['name'] ?? '' ) ? self::s( $c['name'] ) : '—',
				'phone'    => '' !== $phone ? self::s( flxb_format_phone( $phone ) ) : '',
				'phoneUrl' => '' !== $phone ? 'tel:' . preg_replace( '/[^0-9+]/', '', $phone ) : '',
				'email'    => is_email( $email ) ? $email : '',
			);
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $d Donation.
	 * @return list<array{name: string, url: string, meta: string}>
	 */
	private static function receipts( array $d ): array {
		if ( FLXB_Approval::pending( $d ) ) {
			return array(); // in the approval card while the gift awaits approval
		}
		$m   = self::arr( FLXB_Donations::meta( $d ) );
		$out = array();
		foreach ( self::arr( $m['receipts'] ?? array() ) as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$url   = self::s( $r['url'] ?? '' );
			$bits  = array_filter(
				array(
					! empty( $r['at'] ) ? self::date( self::s( $r['at'] ) ) : '',
					isset( $r['user'] ) ? self::user_name( self::i( $r['user'] ) ) : '',
					! empty( $r['source'] ) ? ( 'whatsapp' === $r['source'] ? 'WhatsApp' : self::s( $r['source'] ) ) : '',
				)
			);
			$out[] = array(
				'name' => '' !== self::s( $r['file'] ?? '' ) ? self::s( $r['file'] ) : __( 'Receipt', 'flx-donate' ),
				'url'  => preg_match( '#^https?://#i', $url ) ? $url : '',
				'meta' => implode( ' · ', $bits ),
			);
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $d Donation.
	 * @return list<array{date: string, text: string, user: string}>
	 */
	private static function history( array $d ): array {
		$m   = self::arr( FLXB_Donations::meta( $d ) );
		$out = array();
		foreach ( array_reverse( self::arr( $m['audit'] ?? array() ) ) as $a ) {
			if ( ! is_array( $a ) ) {
				continue;
			}
			$out[] = array(
				'date' => self::date( self::s( $a['at'] ?? '' ) ),
				'text' => self::s( FLXB_Page_Donations::audit_text( $a ) ),
				'user' => self::user_name( self::i( $a['user'] ?? 0 ) ),
			);
		}
		return $out;
	}

	/**
	 * @param array<string,mixed>|null $donor Donor.
	 * @return array<string,mixed>|null
	 */
	private static function donor( ?array $donor ): ?array {
		if ( ! $donor ) {
			return null;
		}
		global $wpdb;
		/** @var wpdb $wpdb */
		$name  = self::s( FLXB_Admin::donor_name( $donor, '' ) );
		$open  = self::i( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT ' . FLXB_Donations::GROUP_KEY . ") FROM %i x WHERE x.donor_id = %d AND x.status = 'pending'", self::s( flxb_table( 'donations' ) ), self::i( $donor['id'] ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$phone = preg_replace( '/\D+/', '', self::s( $donor['phone'] ?? '' ) );
		return array(
			'name'     => $name,
			'initial'  => self::initial( $name ),
			'contact'  => array_values( array_filter( array( '' !== self::s( $donor['phone'] ?? '' ) ? self::s( flxb_format_phone( self::s( $donor['phone'] ) ) ) : '', self::s( $donor['email'] ?? '' ) ) ) ),
			'paid'     => number_format_i18n( self::i( $donor['donation_count'] ?? 0 ) ),
			'total'    => self::s( flxb_money( self::f( $donor['total_amount'] ?? 0 ) ) ),
			'waiting'  => number_format_i18n( $open ),
			'cardUrl'  => FLXB_Refs::can_donors() ? FLXB_Admin_Url::donor( self::i( $donor['id'] ) ) : '',
			'chatUrl'  => '' !== (string) $phone && function_exists( 'flxc_can_use' ) && flxc_can_use() ? admin_url( 'admin.php?page=flx-chat&phone=' . $phone ) : '',
		);
	}

	/**
	 * @param array<string,mixed> $d Donation.
	 * @return array<string,mixed>|null
	 */
	private static function referral( array $d ): ?array {
		if ( FLXB_Refs::own_only() ) {
			return null;
		}
		$ref    = self::i( $d['ref_user_id'] ?? 0 );
		$person = null;
		$hint   = '';
		if ( $ref ) {
			$u      = get_userdata( $ref );
			$name   = self::s( FLXB_Refs::name( $ref ) );
			$ph     = $u ? self::s( get_user_meta( $ref, 'billing_phone', true ) ) : '';
			$person = array(
				'name'    => $name,
				'initial' => self::initial( $name ),
				'contact' => array_values( array_filter( array( '' !== $ph ? self::s( flxb_format_phone( $ph ) ) : '', $u ? (string) $u->user_email : '' ) ) ),
			);
		} else {
			$prev = self::i( $d['donor_id'] ?? 0 ) ? self::i( FLXB_Refs::donor_ref( self::i( $d['donor_id'] ) ) ) : 0;
			/* translators: %s: referral name */
			$hint = $prev ? sprintf( __( 'The donor\'s earlier gifts came through %s.', 'flx-donate' ), self::s( FLXB_Refs::name( $prev ) ) ) : '';
		}
		$choices = array();
		if ( self::can_manage() ) {
			$users = self::arr( FLXB_Refs::users() );
			if ( $ref && ! isset( $users[ $ref ] ) ) {
				$users[ $ref ] = FLXB_Refs::name( $ref );
			}
			foreach ( $users as $uid => $label ) {
				$choices[] = array( 'value' => (string) self::i( $uid ), 'label' => self::s( $label ) );
			}
		}
		return array(
			'person'  => $person,
			'hint'    => $hint,
			'current' => $ref,
			'canEdit' => self::can_manage(),
			'choices' => $choices,
		);
	}

	/** Payment record of Flux Donate's payment layer (loaded only when it owns payments). */
	private static function payment_record( int $id ): mixed {
		$cb = array( 'FLXB_Payment_Records', 'get' );
		return is_callable( $cb ) ? call_user_func( $cb, $id ) : null;
	}

	/**
	 * Statuses an admin may set by hand (FLXB_Page_Donations::manual_statuses()).
	 *
	 * @return array<string,string>
	 */
	private static function manual_statuses(): array {
		$out = array();
		foreach ( self::arr( FLXB_Page_Donations::manual_statuses() ) as $k => $v ) {
			$out[ (string) $k ] = self::s( $v );
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $d Donation.
	 * @param list<array<string,mixed>> $sibs Gifts of the same order.
	 * @return array<string,mixed>|null
	 */
	private static function manage( array $d, array $sibs ): ?array {
		if ( ! self::can_manage() ) {
			return null;
		}
		$trash = 'trash' === ( $d['status'] ?? '' );
		$oid   = self::i( $d['wc_order_id'] ?? 0 );
		$opts  = array();
		if ( ! $trash && ! in_array( $d['status'], array( 'refunded', 'partially_refunded' ), true ) ) {
			foreach ( self::manual_statuses() as $k => $l ) {
				if ( $k !== $d['status'] ) {
					$opts[] = array( 'value' => self::s( $k ), 'label' => self::s( $l ) );
				}
			}
		}
		return array(
			'multi'         => count( $sibs ) > 1,
			'statuses'      => $opts,
			'statusConfirm' => $oid
				? __( 'Change the status of this gift? Its WooCommerce order follows; when you mark it paid, the certificate is made and sent to the donor.', 'flx-donate' )
				: __( 'Change the status of this gift?', 'flx-donate' ),
			'trashed'       => $trash,
			'trashConfirm'  => $oid ? __( 'Move this gift and its WooCommerce order to the trash? You can restore it later.', 'flx-donate' ) : __( 'Move this gift to the trash? You can restore it later.', 'flx-donate' ),
			'canDelete'     => current_user_can( 'manage_options' ),
		);
	}

	/**
	 * WooCommerce order notes (FLXB_WC_Boxes::notes_box()); null without the right or an order.
	 *
	 * @param array<string,mixed> $d Donation.
	 * @param WC_Order|null $order Order.
	 * @return array<string,mixed>|null
	 */
	private static function notes( array $d, $order ): ?array {
		if ( ! $order || ! class_exists( 'FLXB_WC_Boxes' ) || ! FLXB_WC_Boxes::can( $order ) ) {
			return null;
		}
		unset( $d );
		$out = array();
		foreach ( function_exists( 'wc_get_order_notes' ) ? (array) wc_get_order_notes( array( 'order_id' => $order->get_id() ) ) : array() as $n ) {
			if ( ! is_object( $n ) ) {
				continue;
			}
			$by    = 'system' === ( $n->added_by ?? '' ) ? __( 'System', 'flx-donate' ) : self::s( $n->added_by ?? '' );
			$when  = isset( $n->date_created ) && $n->date_created instanceof WC_DateTime ? self::date( gmdate( 'Y-m-d H:i:s', $n->date_created->getTimestamp() ) ) : '';
			$out[] = array(
				'id'       => self::i( $n->id ?? 0 ),
				'html'     => wpautop( wptexturize( wp_kses_post( self::s( $n->content ?? '' ) ) ) ),
				'meta'     => trim( $when . ' · ' . $by, ' ·' ),
				'customer' => ! empty( $n->customer_note ),
				'system'   => 'system' === ( $n->added_by ?? '' ),
			);
		}
		return array( 'items' => $out );
	}

	/**
	 * Boxes of other plugins as ready HTML: id, title, column and the HTML. Printed on the page
	 * (print_islands()); the screen moves each one into its box.
	 *
	 * @param array<string,mixed> $d Donation.
	 * @param WC_Order|null $order Order.
	 * @return list<FlxbIsland>
	 */
	public static function islands( array $d, $order = null ): array {
		$order = $order ?? self::order( $d );
		$id    = self::i( $d['id'] );
		$back  = FLXB_Admin_Url::donation( $id );
		$out   = array();
		if ( $order && class_exists( 'FLXB_WC_Boxes' ) && FLXB_WC_Boxes::can( $order ) ) {
			$wc   = self::arr( FLXB_WC_Boxes::collect( $order ) );
			$form = $wc ? self::s( FLXB_WC_Boxes::save_form( $order, $back ) ) : '';
			foreach ( $wc as $b ) {
				$b = self::arr( $b );
				if ( empty( $b['id'] ) ) {
					continue;
				}
				$out[] = array(
					'id'    => self::s( FLXB_Boxes::key( self::s( $b['id'] ) ) ),
					'title' => wp_strip_all_tags( self::s( $b['title'] ?? '' ) ),
					'col'   => 'side' === ( $b['col'] ?? '' ) ? 'side' : 'main',
					'html'  => isset( $b['html'] ) ? self::s( $b['html'] ) : self::s( FLXB_Boxes::card( $b ) ),
				);
			}
			if ( '' !== $form ) {
				$out[] = array( 'id' => 'wc-save', 'title' => '', 'col' => 'main', 'html' => $form );
			}
		}
		foreach ( (array) apply_filters( 'flxb_detail_boxes', array(), $d ) as $b ) {
			if ( ! is_array( $b ) || empty( $b['id'] ) ) {
				continue;
			}
			$b['donation'] = $d;
			unset( $b['html'] );
			$out[] = array(
				'id'    => self::s( FLXB_Boxes::key( 'x:' . self::s( $b['id'] ) ) ),
				'title' => wp_strip_all_tags( self::s( $b['title'] ?? '' ) ),
				'col'   => 'side' === ( $b['col'] ?? '' ) ? 'side' : 'main',
				'html'  => self::s( FLXB_Boxes::card( $b ) ),
			);
		}
		// The payment plugin's refund / void form (Flux POS money form) when there is no WooCommerce menu.
		$wcm = class_exists( 'FLXB_WC_Admin' ) && FLXB_WC::ready() ? FLXB_WC_Admin::donation_menu( $d ) : null;
		if ( ! $wcm && function_exists( 'flxb_owns_payments' ) && flxb_owns_payments() && class_exists( 'FLXB_Page_Payments' ) ) {
			$m   = self::arr( FLXB_Donations::meta( $d ) );
			$row = self::i( $d['pay_id'] ?? 0 ) && empty( $m['renewal'] ) ? self::payment_record( self::i( $d['pay_id'] ) ) : null;
			ob_start();
			FLXB_Page_Payments::money_form( $row, $d, $back );
			$html = trim( (string) ob_get_clean() );
			if ( '' !== $html ) {
				$out[] = array( 'id' => 'money', 'title' => __( 'Refund', 'flx-donate' ), 'col' => 'side', 'html' => '<section class="flxb-card flxb-xbox">' . $html . '</section>' );
			}
		}
		$tabs = self::s( FLXB_Detail_Tabs::render( 'donation', $d, self::can_manage() ) );
		if ( '' !== $tabs ) {
			$out[] = array( 'id' => 'xtabs', 'title' => __( 'Extra details', 'flx-donate' ), 'col' => 'main', 'html' => $tabs );
		}
		return $out;
	}

	/**
	 * Island ids, titles and columns for the screen; the HTML is printed on the page. Over GraphQL
	 * outside the page (the app) the islands are left out: they need the page's scripts.
	 *
	 * @param array<string,mixed> $d Donation.
	 * @param WC_Order|null $order Order.
	 * @return list<array{id: string, title: string, col: string}>
	 */
	private static function island_list( array $d, $order ): array {
		if ( null === self::$islands ) {
			return array();
		}
		$list = self::islands( $d, $order );
		self::$islands = $list;
		return array_values(
			array_map(
				static fn( array $b ): array => array(
					'id'    => $b['id'],
					'title' => $b['title'],
					'col'   => $b['col'],
				),
				$list
			)
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
		echo '<div id="flxdn-islands" hidden>';
		foreach ( self::$islands as $b ) {
			echo '<div class="flxb flxdn-island" data-flxdn-island="' . esc_attr( $b['id'] ) . '">' . $b['html'] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the cards are escaped where they are built
		}
		echo '</div>';
	}

	/** @return array{main: list<string>, side: list<string>, closed: list<string>} */
	private static function layout(): array {
		$p = self::arr( FLXB_Boxes::prefs( 'donation' ) );
		$l = static fn( $v ): array => array_values( array_map( array( __CLASS__, 's' ), self::arr( $v ) ) );
		return array(
			'main'   => $l( $p['main'] ?? array() ),
			'side'   => $l( $p['side'] ?? array() ),
			'closed' => $l( $p['closed'] ?? array() ),
		);
	}

	/* ---------- Actions ---------- */

	private static function err( string $code, string $msg ): WP_Error {
		$map = array(
			'flx_forbidden'     => 'FORBIDDEN',
			'flx_not_found'     => 'NOT_FOUND',
			'flx_invalid_input' => 'INVALID_INPUT',
		);
		return new WP_Error( $code, $msg, array( 'graphql_code' => $map[ $code ] ?? 'INTERNAL' ) );
	}

	/**
	 * The donation for an action, or an error.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	private static function target( int $id ) {
		$d = self::donation( $id );
		return $d ? $d : self::err( 'flx_not_found', __( 'Donation not found', 'flx-donate' ) );
	}

	/** @return array{message: string}|WP_Error */
	public static function set_status( int $id, string $status ) {
		$d = self::target( $id );
		if ( is_wp_error( $d ) ) {
			return $d;
		}
		if ( ! self::can_manage() ) {
			return self::err( 'flx_forbidden', __( 'You are not allowed to do this.', 'flx-donate' ) );
		}
		if ( ! isset( self::manual_statuses()[ $status ] ) ) {
			return self::err( 'flx_invalid_input', __( 'The status was not changed.', 'flx-donate' ) );
		}
		if ( self::i( $d['wc_order_id'] ?? 0 ) && ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			return self::err( 'flx_forbidden', __( 'You are not allowed to do this.', 'flx-donate' ) );
		}
		$done = 0;
		foreach ( self::arr( FLXB_Donations::siblings( $d ) ) as $s ) {
			$done += is_array( $s ) && FLXB_Donations::set_status( self::i( $s['id'] ), $status ) ? 1 : 0;
		}
		$label = self::s( self::arr( flxb_status_label( $status ) )[1] ?? $status );
		/* translators: %s: new status */
		return array( 'message' => $done ? sprintf( __( 'Status changed to "%s".', 'flx-donate' ), $label ) : __( 'The status was not changed.', 'flx-donate' ) );
	}

	/** @return array{message: string}|WP_Error */
	public static function set_referral( int $id, int $ref ) {
		$d = self::target( $id );
		if ( is_wp_error( $d ) ) {
			return $d;
		}
		if ( ! self::can_manage() ) {
			return self::err( 'flx_forbidden', __( 'You are not allowed to do this.', 'flx-donate' ) );
		}
		if ( $ref && ! FLXB_Refs::valid( $ref ) ) {
			return self::err( 'flx_invalid_input', __( 'This person cannot be a referral.', 'flx-donate' ) );
		}
		FLXB_Refs::set( self::i( $d['id'] ), $ref );
		return array( 'message' => __( 'Saved.', 'flx-donate' ) );
	}

	/**
	 * @param array<int,string> $names Item id => name.
	 * @return array{message: string}|WP_Error
	 */
	public static function save_names( int $id, array $names ) {
		$d = self::target( $id );
		if ( is_wp_error( $d ) ) {
			return $d;
		}
		if ( ! self::can_manage() ) {
			return self::err( 'flx_forbidden', __( 'You are not allowed to do this.', 'flx-donate' ) );
		}
		$clean = array();
		foreach ( $names as $item => $name ) {
			$clean[ (int) $item ] = sanitize_text_field( $name );
		}
		FLXB_Items::update_names( self::i( $d['id'] ), $clean );
		return array( 'message' => __( 'Saved.', 'flx-donate' ) );
	}

	/** @return array{message: string}|WP_Error */
	public static function approve( int $id ) {
		$d = self::target( $id );
		if ( is_wp_error( $d ) ) {
			return $d;
		}
		if ( ! FLXB_Approval::can( $d ) ) {
			return self::err( 'flx_forbidden', __( 'You are not allowed to do this.', 'flx-donate' ) );
		}
		if ( ! FLXB_Approval::pending( $d ) ) {
			return self::err( 'flx_invalid_input', __( 'This donation is no longer awaiting approval.', 'flx-donate' ) );
		}
		FLXB_Approval::do_approve( $d );
		return array( 'message' => __( 'Payment approved. The certificate is made and sent to the donor.', 'flx-donate' ) );
	}

	/** @return array{message: string}|WP_Error */
	public static function reject( int $id, string $reason, bool $notify ) {
		$d = self::target( $id );
		if ( is_wp_error( $d ) ) {
			return $d;
		}
		if ( ! FLXB_Approval::can( $d ) ) {
			return self::err( 'flx_forbidden', __( 'You are not allowed to do this.', 'flx-donate' ) );
		}
		if ( ! FLXB_Approval::pending( $d ) ) {
			return self::err( 'flx_invalid_input', __( 'This donation is no longer awaiting approval.', 'flx-donate' ) );
		}
		$reason = trim( sanitize_textarea_field( $reason ) );
		if ( '' === $reason ) {
			return self::err( 'flx_invalid_input', __( 'Please write a reason.', 'flx-donate' ) );
		}
		$sent = array_map( array( __CLASS__, 's' ), self::arr( FLXB_Approval::do_reject( $d, $reason, $notify ) ) );
		return array(
			'message' => $sent
				/* translators: %s: channels */
				? sprintf( __( 'Payment rejected; the donor was told by %s.', 'flx-donate' ), implode( ', ', $sent ) )
				: __( 'Payment rejected.', 'flx-donate' ),
		);
	}

	/** @return array{message: string}|WP_Error */
	public static function certificates( int $id, string $action ) {
		$d = self::target( $id );
		if ( is_wp_error( $d ) ) {
			return $d;
		}
		$order = self::order( $d );
		if ( ! $order || ! current_user_can( 'edit_shop_orders' ) || ! class_exists( 'FLXB_WC_Certificates' ) ) {
			return self::err( 'flx_forbidden', __( 'You are not allowed to do this.', 'flx-donate' ) );
		}
		if ( 'send' === $action ) {
			$sum = self::s( FLXB_WC_Certificates::send_all( $order ) );
			/* translators: %s: summary */
			return array( 'message' => '' !== $sum ? sprintf( __( 'Certificates sent: %s', 'flx-donate' ), $sum ) : __( 'There is no certificate to send.', 'flx-donate' ) );
		}
		$made = self::arr( FLXB_WC_Certificates::remake( $order ) );
		return array(
			'message' => $made
				/* translators: %d: number of certificates */
				? sprintf( _n( '%d certificate made. Check it, then press "Send the certificates".', '%d certificates made. Check them, then press "Send the certificates".', count( $made ), 'flx-donate' ), count( $made ) )
				: __( 'No certificate could be made; see the order notes.', 'flx-donate' ),
		);
	}

	/** @return array{message: string}|WP_Error */
	public static function refund( int $id, float $amount ) {
		$d = self::target( $id );
		if ( is_wp_error( $d ) ) {
			return $d;
		}
		if ( ! flxb_can_manage() || ! current_user_can( 'edit_shop_orders' ) || ! class_exists( 'FLXB_WC_Admin' ) ) {
			return self::err( 'flx_forbidden', __( 'You are not allowed to do this.', 'flx-donate' ) );
		}
		$oid     = self::i( FLXB_WC_Ledger::order_of( $d ) );
		$order   = $oid ? wc_get_order( $oid ) : null;
		$item_id = self::i( FLXB_WC_Ledger::item_of( $d ) );
		if ( ! $order instanceof WC_Order || ! $item_id ) {
			return self::err( 'flx_not_found', __( 'Order not found.', 'flx-donate' ) );
		}
		$res = FLXB_WC_Admin::refund_line( $order, $item_id, $amount );
		if ( is_wp_error( $res ) ) {
			return self::err( 'flx_invalid_input', $res->get_error_message() );
		}
		return array( 'message' => __( 'The refund was sent to the payment provider.', 'flx-donate' ) );
	}

	/** @return array{message: string}|WP_Error */
	public static function note_add( int $id, string $text, bool $customer ) {
		$d = self::target( $id );
		if ( is_wp_error( $d ) ) {
			return $d;
		}
		$order = self::order( $d );
		if ( ! $order || ! class_exists( 'FLXB_WC_Boxes' ) || ! FLXB_WC_Boxes::can( $order ) ) {
			return self::err( 'flx_forbidden', __( 'You are not allowed to do this.', 'flx-donate' ) );
		}
		$text = wp_kses_post( trim( $text ) );
		if ( '' === $text ) {
			return self::err( 'flx_invalid_input', __( 'Write the note first.', 'flx-donate' ) );
		}
		$order->add_order_note( $text, $customer ? 1 : 0, true );
		return array( 'message' => __( 'Note added.', 'flx-donate' ) );
	}

	/** @return array{message: string}|WP_Error */
	public static function note_delete( int $id, int $note ) {
		$d = self::target( $id );
		if ( is_wp_error( $d ) ) {
			return $d;
		}
		$order = self::order( $d );
		if ( ! $order || ! class_exists( 'FLXB_WC_Boxes' ) || ! FLXB_WC_Boxes::can( $order ) ) {
			return self::err( 'flx_forbidden', __( 'You are not allowed to do this.', 'flx-donate' ) );
		}
		$c = $note ? get_comment( $note ) : null;
		if ( ! $c instanceof WP_Comment || (int) $c->comment_post_ID !== (int) $order->get_id() ) {
			return self::err( 'flx_not_found', __( 'The note was not found.', 'flx-donate' ) );
		}
		wc_delete_order_note( $note );
		return array( 'message' => __( 'Note deleted.', 'flx-donate' ) );
	}

	/**
	 * Save the person's box layout of the donation page.
	 *
	 * @param list<string> $main   Main column.
	 * @param list<string> $side   Side column.
	 * @param list<string> $closed Folded boxes.
	 */
	public static function save_layout( array $main, array $side, array $closed ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$clean = static fn( array $l ): array => array_slice( array_values( array_unique( array_filter( array_map( static fn( $v ): string => self::s( FLXB_Boxes::key( $v ) ), $l ) ) ) ), 0, 60 );
		$all   = get_user_meta( get_current_user_id(), FLXB_Boxes::META, true );
		$all   = is_array( $all ) ? $all : array();
		$all['donation'] = array(
			'main'   => $clean( $main ),
			'side'   => $clean( $side ),
			'closed' => $clean( $closed ),
		);
		update_user_meta( get_current_user_id(), FLXB_Boxes::META, array_slice( $all, -12, null, true ) );
		return true;
	}
}
