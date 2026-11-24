<?php
/**
 * Tracking page (tracking module) for the admin screen (GraphQL part b7): the list of tracked items with its
 * filters and bulk steps, and one item's page (flow, details, files, history).
 *
 * The wp-admin screen (packages/modules/src/donate/Track.tsx) calls this class. Rights are the classic ones:
 * reading needs FLXB_Track::can_view(); every write needs FLXB_Track::can(). A file upload stays a form post
 * (admin-post flxb_track_file_upload): the screen posts the file with the nonce it gets here. The extra
 * detail tabs (FLXB_Detail_Tabs) are printed with the page as an island.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

final class FLXB_Admin_Track_Service {

	const PER = 50;

	/** @var list<array{id: string, html: string}>|null Islands of the page being printed; null = none wanted. */
	private static $islands = null;

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

	public static function can_view(): bool {
		return (bool) FLXB_Track::can_view();
	}

	public static function can(): bool {
		return (bool) FLXB_Track::can();
	}

	private static function invalid( string $msg ): WP_Error {
		return new WP_Error( 'flxb_track', $msg, array( 'graphql_code' => 'INVALID_INPUT' ) );
	}

	/**
	 * @param int $tid Donation type id.
	 * @return array<string,string>
	 */
	private static function labels( int $tid ): array {
		$out = array();
		foreach ( self::arr( FLXB_Track::labels( $tid ) ) as $k => $v ) {
			$out[ (string) $k ] = self::s( $v );
		}
		return $out;
	}

	/** Colour of an item status. */
	private static function tone( string $status ): string {
		$map = array(
			'pending'   => 'pending',
			'approved'  => 'recurring',
			'sent'      => 'recurring',
			'done'      => 'ok',
			'delivered' => 'ok',
			'canceled'  => 'failed',
		);
		return $map[ $status ] ?? 'muted';
	}

	/**
	 * @param array<string,string> $pairs value => label.
	 * @return list<array{value: string, label: string}>
	 */
	private static function choices( array $pairs ): array {
		$out = array();
		foreach ( $pairs as $k => $v ) {
			$out[] = array(
				'value' => (string) $k,
				'label' => $v,
			);
		}
		return $out;
	}

	/**
	 * Partners an item of the type can go to.
	 *
	 * @return list<array{value: string, label: string}>
	 */
	private static function places( int $tid ): array {
		$out = array();
		foreach ( self::arr( FLXB_Track::places( $tid ) ) as $k => $v ) {
			$out[ (string) $k ] = self::s( $v );
		}
		return self::choices( $out );
	}

	/** Stored numbers may be digits only; show them as +90 5xx… */
	private static function phone( string $p ): string {
		return '' === $p ? '' : self::s( flxb_format_phone( ctype_digit( $p ) ? '+' . $p : $p ) );
	}

	/**
	 * Target date with where it stands.
	 *
	 * @param array<string,mixed> $it Item.
	 * @return array{date: string, state: string, label: string}
	 */
	private static function target( array $it ): array {
		$d = self::s( $it['target_date'] ?? '' );
		if ( '' === $d ) {
			return array(
				'date'  => '',
				'state' => '',
				'label' => '',
			);
		}
		$st  = self::s( FLXB_Track::target_state( $it ) );
		$map = array(
			'window' => __( 'due', 'flx-donate' ),
			'late'   => __( 'late', 'flx-donate' ),
		);
		$ts  = strtotime( $d );
		return array(
			'date'  => self::s( flx_date( false === $ts ? 0 : $ts, 'j M Y' ) ),
			'state' => $st,
			'label' => $map[ $st ] ?? '',
		);
	}

	/**
	 * Payment status of a donation as label and colour.
	 *
	 * @return array{label: string, tone: string}|null
	 */
	private static function pay( string $status ): ?array {
		if ( '' === $status ) {
			return null;
		}
		$s = self::arr( flxb_status_label( $status ) );
		return array(
			'label' => self::s( $s[1] ?? $status ),
			'tone'  => self::s( $s[0] ?? 'muted' ),
		);
	}

	/**
	 * Steps of the bulk bar and of an item's buttons.
	 *
	 * @return array<string,string>
	 */
	private static function steps( int $tid ): array {
		$l = self::labels( $tid );
		return array(
			'approve'   => __( 'Approve', 'flx-donate' ),
			'assign'    => $l['do_assign'] ?? '',
			'revoke'    => $l['do_revoke'] ?? '',
			'done'      => $l['do_done'] ?? '',
			'delivered' => __( 'Mark delivered to owner', 'flx-donate' ),
			'cancel'    => __( 'Cancel', 'flx-donate' ),
			'reset'     => __( 'Back to waiting', 'flx-donate' ),
		);
	}

	/**
	 * Extra columns of other plugins: key => label.
	 *
	 * @return array<string,string>
	 */
	private static function columns(): array {
		$cols = (array) apply_filters( 'flxb_kurban_columns', array() ); // before 2.11
		$out  = array();
		foreach ( (array) apply_filters( 'flxb_track_columns', $cols ) as $k => $v ) {
			$out[ (string) $k ] = self::s( $v );
		}
		return $out;
	}

	/**
	 * The list filter of the screen (and of the classic address).
	 *
	 * @param array<string,mixed> $f status, place, typeId, paid ('', '1', '0'), due, search.
	 * @return array{status: string, place: string, type_id: int, paid: string, due: int, q: string}
	 */
	private static function filter( array $f ): array {
		$st   = sanitize_key( self::s( $f['status'] ?? '' ) );
		$paid = self::s( $f['paid'] ?? '' );
		return array(
			'status'  => isset( self::arr( FLXB_Track::statuses() )[ $st ] ) ? $st : '',
			'place'   => sanitize_text_field( self::s( $f['place'] ?? '' ) ),
			'type_id' => max( 0, self::i( $f['typeId'] ?? 0 ) ),
			'paid'    => in_array( $paid, array( '0', '1' ), true ) ? $paid : '',
			'due'     => empty( $f['due'] ) ? 0 : 1,
			'q'       => sanitize_text_field( self::s( $f['search'] ?? '' ) ),
		);
	}

	/**
	 * The list: tabs with counts, the filter choices, a page of items.
	 *
	 * @param array<string,mixed> $in Filter (see filter()).
	 * @return array<string,mixed>
	 */
	public static function list( array $in, int $page ): array {
		$f      = self::filter( $in );
		$tid    = $f['type_id'];
		$l      = self::labels( $tid );
		$page   = max( 1, $page );
		$res    = self::arr( FLXB_Track::query( $f + array( 'per_page' => self::PER, 'page' => $page, 'order' => $f['due'] ? 'target' : '' ) ) );
		$counts = self::arr( FLXB_Track::counts( $f ) );
		$tabs   = array(
			array(
				'value' => '',
				'label' => __( 'All', 'flx-donate' ),
				'count' => self::s( number_format_i18n( (float) array_sum( array_map( array( __CLASS__, 'i' ), $counts ) ) ) ),
			),
		);
		foreach ( self::arr( FLXB_Track::statuses( $tid ) ) as $k => $label ) {
			$tabs[] = array(
				'value' => (string) $k,
				'label' => self::s( $label ),
				'count' => self::s( number_format_i18n( self::i( $counts[ $k ] ?? 0 ) ) ),
			);
		}
		$types = array();
		foreach ( self::arr( FLXB_Track::tracked_types() ) as $t ) {
			$t                                = self::map( $t );
			$types[ self::s( $t['id'] ?? '' ) ] = self::s( $t['title'] ?? '' );
		}
		$cols  = self::columns();
		$items = array();
		foreach ( self::arr( $res['items'] ?? array() ) as $row ) {
			$it    = self::map( $row );
			$fil   = self::arr( FLXB_Track::files( $it ) );
			$sent  = 0;
			foreach ( $fil as $v ) {
				$sent += count( self::arr( self::map( $v )['sent'] ?? array() ) ) ? 1 : 0;
			}
			$cells = array();
			foreach ( array_keys( $cols ) as $key ) {
				$cell    = self::s( apply_filters( 'flxb_kurban_cell', '', $key, $it ) ); // before 2.11
				$cells[] = wp_kses_post( self::s( apply_filters( 'flxb_track_cell', $cell, $key, $it ) ) );
			}
			$st      = self::s( $it['status'] ?? '' );
			$sts     = self::arr( FLXB_Track::statuses( self::i( $it['type_id'] ?? 0 ) ) );
			$phone   = self::s( $it['beneficiary_phone'] ?? '' );
			$items[] = array(
				'databaseId'  => self::i( $it['id'] ?? 0 ),
				'code'        => self::s( $it['code'] ?? '' ),
				'option'      => self::s( $it['option_label'] ?? '' ),
				'beneficiary' => self::s( $it['beneficiary_name'] ?? '' ),
				'phone'       => '' !== $phone ? self::s( flxb_format_phone( $phone ) ) : '',
				'donationId'  => self::i( $it['donation_id'] ?? 0 ),
				'donationUrl' => self::s( FLXB_Admin_Url::donation( self::i( $it['donation_id'] ?? 0 ) ) ),
				'donor'       => self::s( $it['donor_name'] ?? '' ),
				'pay'         => self::pay( self::s( $it['pay_status'] ?? '' ) ),
				'target'      => self::target( $it ),
				'place'       => self::s( $it['place_name'] ?? '' ),
				'status'      => self::s( $sts[ $st ] ?? $st ),
				'tone'        => self::tone( $st ),
				/* translators: 1: files, 2: sent */
				'files'       => $fil ? sprintf( __( '%1$d · %2$d sent', 'flx-donate' ), count( $fil ), $sent ) : '',
				'cells'       => $cells,
			);
		}
		$acts = array();
		if ( FLXB_Refs::can_create() ) {
			$acts[] = array( 'label' => __( 'New record', 'flx-donate' ), 'url' => FLXB_Admin_Url::page( 'flxb-add' ), 'primary' => true, 'icon' => 'plus' );
		}
		$acts    = (array) apply_filters( 'flxb_kurban_page_actions', $acts ); // before 2.11
		$actions = array();
		foreach ( (array) apply_filters( 'flxb_track_page_actions', $acts ) as $a ) {
			$a = self::map( $a );
			if ( '' !== self::s( $a['url'] ?? '' ) ) {
				$actions[] = array(
					'label'   => self::s( $a['label'] ?? '' ),
					'url'     => self::s( $a['url'] ),
					'primary' => ! empty( $a['primary'] ),
				);
			}
		}
		$total = self::i( $res['total'] ?? 0 );
		return array(
			'title'        => $l['title'] ?? '',
			'partnerLabel' => $l['partner'] ?? '',
			'filesLabel'   => $l['files'] ?? '',
			'canEdit'      => self::can(),
			'actions'      => $actions,
			'tabs'         => $tabs,
			'types'        => count( $types ) > 1 ? self::choices( $types ) : array(),
			'places'       => self::places( $tid ),
			'steps'        => self::choices( self::steps( $tid ) ),
			'columns'      => array_values( $cols ),
			'items'        => $items,
			'total'        => $total,
			/* translators: %s: number of items */
			'totalLabel'   => sprintf( _n( '%s item', '%s items', $total, 'flx-donate' ), number_format_i18n( $total ) ),
			'page'         => $page,
			'perPage'      => self::PER,
		);
	}

	/**
	 * Result of FLXB_Track::apply() as a notice.
	 *
	 * @param mixed $r Result.
	 * @return array{message: string, warning: bool}|WP_Error
	 */
	private static function result( $r ) {
		$r = self::map( $r );
		if ( '' !== self::s( $r['error'] ?? '' ) ) {
			return self::invalid( self::s( $r['error'] ) );
		}
		$done = count( self::arr( $r['done'] ?? array() ) );
		/* translators: %d: number of items */
		$msg  = sprintf( _n( '%d item updated.', '%d items updated.', $done, 'flx-donate' ), $done );
		$skip = self::arr( $r['skipped'] ?? array() );
		if ( $skip ) {
			$why = array();
			foreach ( $skip as $id => $reason ) {
				$it    = FLXB_Track::get( (int) $id );
				$why[] = ( is_array( $it ) ? self::s( $it['code'] ?? '' ) : '#' . (int) $id ) . ': ' . self::s( $reason );
			}
			/* translators: %s: list of codes with the reason */
			$msg .= ' ' . sprintf( __( 'Skipped: %s', 'flx-donate' ), implode( ', ', array_slice( $why, 0, 8 ) ) . ( count( $why ) > 8 ? '…' : '' ) );
		}
		return array(
			'message' => $msg,
			'warning' => (bool) $skip,
		);
	}

	/**
	 * A step on items (approve, assign, revoke, done, delivered, cancel, reset).
	 *
	 * @param list<int> $ids Items.
	 * @return array{message: string, warning: bool}|WP_Error
	 */
	public static function apply( array $ids, string $step, string $place, string $note ) {
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( ! $ids ) {
			return self::invalid( __( 'Select at least one item.', 'flx-donate' ) );
		}
		if ( ! isset( self::steps( 0 )[ $step ] ) ) {
			return self::invalid( __( 'The action could not be completed.', 'flx-donate' ) );
		}
		return self::result(
			FLXB_Track::apply(
				$ids,
				$step,
				array(
					'place' => sanitize_text_field( $place ),
					'note'  => sanitize_text_field( $note ),
				)
			)
		);
	}

	/**
	 * One item's page; null when it does not exist.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function item( int $id ): ?array {
		$row = FLXB_Track::get( $id );
		if ( ! is_array( $row ) || '' === self::s( $row['code'] ?? '' ) ) {
			return null;
		}
		$it    = self::map( $row );
		$tid   = self::i( $it['type_id'] ?? 0 );
		$l     = self::labels( $tid );
		$st    = self::s( $it['status'] ?? '' );
		$sts   = self::arr( FLXB_Track::statuses( $tid ) );
		$can   = self::can();
		$dr    = FLXB_Donations::get( self::i( $it['donation_id'] ?? 0 ) );
		$d     = is_array( $dr ) ? self::map( $dr ) : null;
		$donor = $d && self::i( $d['donor_id'] ?? 0 ) ? FLXB_Donors::get( self::i( $d['donor_id'] ) ) : null;

		/* Flow strip */
		$order = array( 'pending', 'approved', 'sent', 'done', 'delivered' );
		$at    = array_search( $st, $order, true );
		$when  = array(
			'pending'   => $it['created_at'] ?? '',
			'approved'  => $it['approved_at'] ?? '',
			'sent'      => $it['sent_at'] ?? '',
			'done'      => $it['done_at'] ?? '',
			'delivered' => $it['notified_at'] ?? '',
		);
		$flow  = array();
		foreach ( $order as $i => $s ) {
			$w      = self::s( $when[ $s ] );
			$flow[] = array(
				'label' => self::s( $sts[ $s ] ?? $s ),
				'state' => false !== $at && $i < $at ? 'done' : ( false !== $at && $i === $at ? 'on' : '' ),
				'date'  => false !== $at && $i <= $at && '' !== $w ? self::s( flxb_date( $w ) ) : '',
			);
		}

		/* Details */
		$rows = array(
			array( __( 'Code', 'flx-donate' ), esc_html( self::s( $it['code'] ?? '' ) ) ),
			array( __( 'Option', 'flx-donate' ), esc_html( self::s( $it['option_label'] ?? '' ) . ' · ' . self::s( flxb_money( $it['unit_price'] ?? 0, self::s( $it['currency'] ?? '' ) ) ) ) ),
		);
		$int = self::s( $it['intention'] ?? '' );
		if ( '' !== $int ) {
			$ints   = self::arr( FLXB_Types::intentions() );
			$x      = $ints[ $int ] ?? $int;
			$rows[] = array( __( 'Intention', 'flx-donate' ), esc_html( is_array( $x ) ? self::s( $x['label'] ?? $int ) : self::s( $x ) ) );
		}
		$tg     = self::target( $it );
		$rows[] = array( __( 'Target date', 'flx-donate' ), '' !== $tg['date'] ? esc_html( $tg['date'] ) . ( '' !== $tg['label'] ? ' <span class="flxdn-pill flxdn-pill--' . ( 'late' === $tg['state'] ? 'failed' : 'pending' ) . '">' . esc_html( $tg['label'] ) . '</span>' : '' ) : '—' );
		$place  = self::s( $it['place_name'] ?? '' );
		$cost   = (float) self::s( $it['cost'] ?? 0 );
		$rows[] = array( $l['partner'] ?? '', esc_html( ( '' !== $place ? $place : '—' ) . ( $cost > 0 && flxb_can_view() ? ' · ' . self::s( flxb_money( $cost, self::s( $it['cost_currency'] ?? '' ) ) ) : '' ) ) );
		if ( self::i( $it['source_id'] ?? 0 ) ) {
			$rows[] = array( __( 'Came through', 'flx-donate' ), esc_html( self::s( FLXB_Partners::name( self::i( $it['source_id'] ) ) ) ) );
		}
		$extra = (array) apply_filters( 'flxb_kurban_detail_rows', array(), $it ); // before 2.11
		foreach ( (array) apply_filters( 'flxb_track_detail_rows', $extra, $it ) as $label => $html ) {
			$rows[] = array( (string) $label, wp_kses_post( self::s( $html ) ) );
		}
		$details = array_map(
			static fn( array $r ): array => array(
				'label' => self::s( $r[0] ),
				'html'  => self::s( $r[1] ),
			),
			$rows
		);

		/* Donation and payment */
		$money = array();
		if ( $d ) {
			$did     = self::i( $d['id'] ?? 0 );
			$money[] = array( __( 'Donation', 'flx-donate' ), '<a href="' . esc_url( self::s( FLXB_Admin_Url::donation( $did ) ) ) . '">#' . $did . '</a> · ' . esc_html( self::s( flxb_money( $d['amount'] ?? 0, self::s( $d['currency'] ?? '' ) ) ) . ' · ' . self::s( flxb_date( self::s( $d['created_at'] ?? '' ) ) ) ) );
			$dn      = is_array( $donor ) ? $donor : null;
			$money[] = array( __( 'Donor', 'flx-donate' ), esc_html( ( $dn ? self::s( FLXB_Admin::donor_name( $dn, '' ) ) : '—' ) . ( $dn && '' !== self::s( $dn['phone'] ?? '' ) ? ' · ' . self::s( flxb_format_phone( self::s( $dn['phone'] ) ) ) : '' ) ) );
			if ( self::i( $d['ref_user_id'] ?? 0 ) ) {
				$ru      = get_userdata( self::i( $d['ref_user_id'] ) );
				$money[] = array( __( 'Referral', 'flx-donate' ), esc_html( $ru ? $ru->display_name : '#' . self::i( $d['ref_user_id'] ) ) );
			}
			$order_obj = self::i( $d['wc_order_id'] ?? 0 ) && function_exists( 'wc_get_order' ) ? wc_get_order( self::i( $d['wc_order_id'] ) ) : null;
			if ( $order_obj instanceof WC_Order ) {
				$money[] = array( __( 'Payment method', 'flx-donate' ), esc_html( $order_obj->get_payment_method_title() ) );
				if ( class_exists( 'FLXB_WC_Receipts' ) ) {
					$links = array();
					foreach ( self::arr( FLXB_WC_Receipts::of( $order_obj ) ) as $i => $r ) {
						$links[] = '<a href="' . esc_url( self::s( FLXB_WC_Receipts::url( $order_obj, $i, true ) ) ) . '" target="_blank" rel="noopener">' . esc_html( self::s( self::map( $r )['name'] ?? '' ) ) . '</a>';
					}
					$money[] = array( __( 'Receipt', 'flx-donate' ), $links ? implode( '<br>', $links ) : '<span class="flxdn-muted">' . esc_html__( 'No receipt uploaded yet.', 'flx-donate' ) . '</span>' );
				}
			}
			$sibs = self::arr( self::arr( FLXB_Track::query( array( 'donation_id' => $did, 'per_page' => 100 ) ) )['items'] ?? array() );
			if ( count( $sibs ) > 1 ) {
				$links = array();
				foreach ( $sibs as $sr ) {
					$sm      = self::map( $sr );
					$links[] = self::i( $sm['id'] ?? 0 ) === $id ? '<strong class="flxdn-mono">' . esc_html( self::s( $sm['code'] ?? '' ) ) . '</strong>' : '<a class="flxdn-mono" href="' . esc_url( self::s( FLXB_Page_Track::url( self::i( $sm['id'] ?? 0 ) ) ) ) . '">' . esc_html( self::s( $sm['code'] ?? '' ) ) . '</a>';
				}
				$money[] = array( __( 'Others in this donation', 'flx-donate' ), implode( ' · ', $links ) );
			}
		}
		$payment = array_map(
			static fn( array $r ): array => array(
				'label' => self::s( $r[0] ),
				'html'  => self::s( $r[1] ),
			),
			$money
		);

		/* Buttons of the status card */
		$btn   = array(
			'pending'   => array( 'approve', 'cancel' ),
			'approved'  => array( 'reset', 'cancel' ),
			'sent'      => array( 'revoke', 'done' ),
			'done'      => array( 'delivered' ),
			'canceled'  => array( 'reset' ),
			'delivered' => array(),
		);
		$conf  = array(
			/* translators: %s: button text, e.g. "Take back from the partner" */
			'revoke' => sprintf( __( '%s? It goes back to approved and can be sent to another partner.', 'flx-donate' ), $l['do_revoke'] ?? '' ),
			'cancel' => __( 'Cancel this item?', 'flx-donate' ),
		);
		$steps = self::steps( $tid );
		$acts  = array();
		foreach ( $btn[ $st ] ?? array() as $s ) {
			$acts[] = array(
				'step'    => $s,
				'label'   => $steps[ $s ],
				'tone'    => in_array( $s, array( 'approve', 'done', 'delivered' ), true ) ? 'primary' : ( in_array( $s, array( 'cancel', 'revoke' ), true ) ? 'danger' : '' ),
				'confirm' => $conf[ $s ] ?? '',
			);
		}

		/* Files */
		$phone = self::s( FLXB_Track::owner_phone( $it ) );
		$files = array();
		foreach ( self::arr( FLXB_Track::files( $it ) ) as $fv ) {
			$v    = self::map( $fv );
			$att  = self::i( $v['att'] ?? 0 );
			$by   = get_userdata( self::i( $v['by'] ?? 0 ) );
			$sent = array();
			foreach ( self::arr( $v['sent'] ?? array() ) as $sv ) {
				$sv = self::map( $sv );
				/* translators: 1: phone, 2: date */
				$sent[] = sprintf( __( 'Sent to %1$s · %2$s', 'flx-donate' ), self::phone( self::s( $sv['to'] ?? '' ) ), self::s( flxb_date( self::s( $sv['at'] ?? '' ) ) ) );
			}
			$mime    = self::s( $v['mime'] ?? '' );
			$files[] = array(
				'att'  => $att,
				'url'  => self::s( wp_get_attachment_url( $att ) ),
				'kind' => 0 === strpos( $mime, 'video/' ) ? 'video' : ( 0 === strpos( $mime, 'image/' ) ? 'image' : ( 0 === strpos( $mime, 'audio/' ) ? 'audio' : 'file' ) ),
				'name' => basename( self::s( get_attached_file( $att ) ) ),
				'meta' => self::s( flxb_date( self::s( $v['at'] ?? '' ) ) ) . ( $by ? ' · ' . $by->display_name : '' ),
				'sent' => $sent,
			);
		}

		/* History */
		$ev      = self::arr( FLXB_Track::events( $tid ) );
		$history = array();
		foreach ( self::arr( FLXB_Track::history( $id ) ) as $hr ) {
			$h     = self::map( $hr );
			$data  = json_decode( self::s( $h['data'] ?? '' ), true );
			$data  = self::map( $data );
			$u     = self::i( $h['user_id'] ?? 0 ) ? get_userdata( self::i( $h['user_id'] ) ) : null;
			$event = self::s( $h['event'] ?? '' );
			$more  = self::s( $data['place_name'] ?? '' );
			if ( 'assigned' === $event && '' !== self::s( $data['from'] ?? '' ) ) {
				$from = self::s( FLXB_Track::place_name( self::s( $data['from'] ) ) );
				/* translators: 1: new partner, 2: previous partner */
				$more = sprintf( __( '%1$s (moved from %2$s)', 'flx-donate' ), self::s( $data['place_name'] ?? '' ), '' !== $from ? $from : self::s( $data['from'] ) );
			}
			if ( in_array( $event, array( 'file_sent', 'video_sent' ), true ) && '' !== self::s( $data['to'] ?? '' ) ) {
				$more = self::phone( self::s( $data['to'] ) );
			}
			if ( 'edited' === $event && is_array( $data['to'] ?? null ) ) {
				$bits = array();
				$was  = self::map( $data['from'] ?? array() );
				foreach ( self::map( $data['to'] ) as $k => $v ) {
					$bits[] = self::s( $was[ $k ] ?? '' ) . ' → ' . self::s( $v );
				}
				$more = implode( ', ', $bits );
			}
			$history[] = array(
				'date'  => self::s( flxb_date( self::s( $h['created_at'] ?? '' ) ) ),
				'event' => $event,
				'label' => self::s( $ev[ $event ] ?? $event ),
				'more'  => $more,
				'note'  => self::s( $h['note'] ?? '' ),
				'user'  => $u ? $u->display_name : __( 'System', 'flx-donate' ),
			);
		}

		$paid = $d && in_array( self::s( $d['status'] ?? '' ), FLXB_Donations::PAID, true );
		if ( null !== self::$islands ) {
			$tabs = self::s( FLXB_Detail_Tabs::render( 'item', $row, $can ) );
			if ( '' !== $tabs ) {
				self::$islands[] = array(
					'id'   => 'xtabs',
					'html' => $tabs,
				);
			}
		}
		return array(
			'databaseId'   => $id,
			'code'         => self::s( $it['code'] ?? '' ),
			'title'        => ( $l['item'] ?? '' ) . ' ' . self::s( $it['code'] ?? '' ),
			'subtitle'     => self::s( $it['option_label'] ?? '' ),
			'listTitle'    => $l['title'] ?? '',
			'partnerLabel' => $l['partner'] ?? '',
			'filesLabel'   => $l['files'] ?? '',
			'itemLabel'    => $l['item'] ?? '',
			'assignLabel'  => $l['do_assign'] ?? '',
			'status'       => self::s( $sts[ $st ] ?? $st ),
			'statusKey'    => $st,
			'tone'         => self::tone( $st ),
			'canceled'     => 'canceled' === $st,
			'late'         => 'late' === $tg['state'],
			'canEdit'      => $can,
			'canAssign'    => $can && in_array( $st, array( 'approved', 'sent' ), true ),
			'flow'         => $flow,
			'details'      => $details,
			'beneficiary'  => self::s( $it['beneficiary_name'] ?? '' ),
			'phone'        => self::s( $it['beneficiary_phone'] ?? '' ),
			'targetDate'   => self::s( $it['target_date'] ?? '' ),
			'donationTone' => $d ? self::s( self::arr( flxb_status_label( self::s( $d['status'] ?? '' ) ) )[0] ?? 'muted' ) : '',
			'donationStatus' => $d ? self::s( self::arr( flxb_status_label( self::s( $d['status'] ?? '' ) ) )[1] ?? '' ) : '',
			'payment'      => $payment,
			'unpaid'       => $d && ! $paid,
			'places'       => self::places( $tid ),
			'actions'      => $can ? $acts : array(),
			'files'        => $files,
			'filesHint'    => FLXB_Track::file_completes( $it ) ? __( 'Files stay on the item. The first file marks an item that is with a partner as completed.', 'flx-donate' ) : __( 'Files stay on the item.', 'flx-donate' ),
			'canSend'      => $can && FLXB_Track::can_send() && '' !== $phone,
			'sendHint'     => ! $can ? '' : ( ! FLXB_Track::can_send() ? __( 'Install Flux Chat to send files over WhatsApp.', 'flx-donate' ) : ( '' === $phone ? __( 'There is no phone number for the owner. Add one above to send the file.', 'flx-donate' ) : '' ) ),
			'ownerPhone'   => self::phone( $phone ),
			'uploadUrl'    => $can ? admin_url( 'admin-post.php' ) : '',
			'uploadNonce'  => $can ? wp_create_nonce( 'flxb_track_file_upload_' . $id ) : '',
			'islands'      => null !== self::$islands ? array_column( self::$islands, 'id' ) : array(),
			'history'      => $history,
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
		echo '<div id="flxdn-track-islands" hidden>';
		foreach ( self::$islands as $b ) {
			echo '<div class="flxb flxdn-island" data-flxdn-island="' . esc_attr( $b['id'] ) . '">' . $b['html'] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where it is built
		}
		echo '</div>';
	}

	/** @return array{message: string}|WP_Error */
	public static function edit( int $id, string $name, string $phone, string $target ) {
		if ( ! FLXB_Track::get( $id ) ) {
			return self::invalid( __( 'Item not found.', 'flx-donate' ) );
		}
		FLXB_Track::edit( $id, sanitize_text_field( $name ), sanitize_text_field( $phone ) );
		FLXB_Track::set_target( $id, sanitize_text_field( $target ) );
		return array( 'message' => __( 'Saved.', 'flx-donate' ) );
	}

	/** @return array{message: string}|WP_Error */
	public static function note( int $id, string $note ) {
		$note = sanitize_text_field( $note );
		if ( ! FLXB_Track::get( $id ) ) {
			return self::invalid( __( 'Item not found.', 'flx-donate' ) );
		}
		if ( '' === $note ) {
			return self::invalid( __( 'The action could not be completed.', 'flx-donate' ) );
		}
		FLXB_Track::log( $id, 'note', $note );
		return array( 'message' => __( 'Saved.', 'flx-donate' ) );
	}

	/** @return array{message: string}|WP_Error */
	public static function file_send( int $id, int $att ) {
		$r = FLXB_Track::send_file( $id, $att );
		if ( is_wp_error( $r ) ) {
			return self::invalid( $r->get_error_message() );
		}
		return array( 'message' => __( 'The file was sent to the owner over WhatsApp.', 'flx-donate' ) );
	}

	/** @return array{message: string}|WP_Error */
	public static function file_remove( int $id, int $att ) {
		if ( ! FLXB_Track::remove_file( $id, $att ) ) {
			return self::invalid( __( 'Item not found.', 'flx-donate' ) );
		}
		return array( 'message' => __( 'Saved.', 'flx-donate' ) );
	}
}
