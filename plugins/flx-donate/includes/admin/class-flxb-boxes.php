<?php
/**
 * 2.35.0: movable boxes on detail pages (like the WooCommerce order screen, in the Flux look).
 *
 * A page passes its boxes to FLXB_Boxes::render( $screen, $boxes ). Each box is one card in the
 * main or the side column; the person can move it up or down (across the columns too), fold it,
 * and on a wide screen drag it by its handle. The order and the folded boxes are kept per user
 * (user meta flxb_boxes, keyed by screen) and the page prints them in that order from PHP, so
 * nothing jumps after load. On a narrow screen the columns stack: main first, then side.
 *
 * Box: array(
 *   'id'     => 'lines',                   // [a-z0-9:_-]
 *   'title'  => 'Items',                   // for the buttons' labels (and the card when 'body' is used)
 *   'col'    => 'main' | 'side',           // default column
 *   'html'   => '<section class="flxb-card">…</section>', // a ready card, or:
 *   'body'   => '…',                       // inner HTML; the card and its title are added here
 *   'tools'  => '…',                       // optional HTML next to the title (with 'body')
 *   'class'  => '…',                       // optional extra card class (with 'body')
 * )
 *
 * Other plugins add boxes to the donation page with the filter flxb_detail_boxes
 * ( array $boxes, array $donation ); 'render' => callable( $donation ) printing the body
 * may be given instead of 'body'.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

final class FLXB_Boxes {

	const META = 'flxb_boxes';

	public static function init() {
		add_action( 'wp_ajax_flxb_boxes', array( __CLASS__, 'ajax_save' ) );
	}

	/** Clean box id. */
	public static function key( $id ) {
		return substr( (string) preg_replace( '/[^a-z0-9:_-]/', '', strtolower( (string) $id ) ), 0, 80 );
	}

	/**
	 * Saved layout of a screen for the current user.
	 *
	 * @return array{main:string[],side:string[],closed:string[]}
	 */
	public static function prefs( $screen ) {
		$all = get_user_meta( get_current_user_id(), self::META, true );
		$p   = is_array( $all ) && isset( $all[ $screen ] ) && is_array( $all[ $screen ] ) ? $all[ $screen ] : array();
		$out = array();
		foreach ( array( 'main', 'side', 'closed' ) as $k ) {
			$out[ $k ] = array_values( array_filter( array_map( array( __CLASS__, 'key' ), (array) ( $p[ $k ] ?? array() ) ) ) );
		}
		return $out;
	}

	/**
	 * Boxes in the person's order.
	 *
	 * @return array{main:array[],side:array[]}
	 */
	public static function arrange( $screen, array $boxes ) {
		$byid = array();
		foreach ( $boxes as $b ) {
			$id = self::key( $b['id'] ?? '' );
			if ( '' === $id || isset( $byid[ $id ] ) ) {
				continue;
			}
			$b['id']     = $id;
			$b['col']    = 'side' === ( $b['col'] ?? '' ) ? 'side' : 'main';
			$byid[ $id ] = $b;
		}
		$p    = self::prefs( $screen );
		$cols = array( 'main' => array(), 'side' => array() );
		$used = array();
		foreach ( array( 'main', 'side' ) as $col ) {
			foreach ( $p[ $col ] as $id ) {
				if ( isset( $byid[ $id ] ) && ! isset( $used[ $id ] ) ) {
					$cols[ $col ][] = $byid[ $id ];
					$used[ $id ]    = true;
				}
			}
		}
		// Boxes the person has not placed yet (new, or shown only sometimes) keep their default column.
		foreach ( $byid as $id => $b ) {
			if ( ! isset( $used[ $id ] ) ) {
				$cols[ $b['col'] ][] = $b;
			}
		}
		return $cols;
	}

	/** Card markup of a box that gives its body only. */
	public static function card( array $b ) {
		$body = (string) ( $b['body'] ?? '' );
		if ( '' === $body && isset( $b['render'] ) && is_callable( $b['render'] ) ) {
			ob_start();
			try {
				call_user_func( $b['render'], $b['donation'] ?? null );
			} catch ( Throwable $e ) {
				echo '<p class="flxb-notice flxb-notice--error">' . esc_html__( 'This box could not be shown.', 'flx-donate' ) . '</p>';
			}
			$body = (string) ob_get_clean();
		}
		$cls = trim( 'flxb-card flxb-xbox ' . (string) ( $b['class'] ?? '' ) );
		return '<section class="' . esc_attr( $cls ) . '"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html( (string) ( $b['title'] ?? '' ) ) . '</h2>' . ( ! empty( $b['tools'] ) ? '<div class="flxb-xbox__tools">' . $b['tools'] . '</div>' : '' ) . '</div><div class="flxb-xbox__body">' . $body . '</div></section>';
	}

	private static function svg( $path ) {
		return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
	}

	/** The box's buttons: drag handle (wide screens), up, down, fold. */
	private static function tools( array $b, $closed ) {
		/* translators: %s: box title */
		$name = sprintf( __( 'Box: %s', 'flx-donate' ), wp_strip_all_tags( (string) ( $b['title'] ?? $b['id'] ) ) );
		return '<div class="flxb-box__tools" role="group" aria-label="' . esc_attr( $name ) . '">'
			. '<button type="button" class="flxb-box__btn flxb-box__grip" data-box-grip aria-hidden="true" tabindex="-1" title="' . esc_attr__( 'Drag to move', 'flx-donate' ) . '">' . self::svg( '<circle cx="9" cy="6" r="1"/><circle cx="15" cy="6" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="9" cy="18" r="1"/><circle cx="15" cy="18" r="1"/>' ) . '</button>'
			. '<button type="button" class="flxb-box__btn" data-box-move="up" aria-label="' . esc_attr__( 'Move up', 'flx-donate' ) . '" title="' . esc_attr__( 'Move up', 'flx-donate' ) . '">' . self::svg( '<path d="m18 15-6-6-6 6"/>' ) . '</button>'
			. '<button type="button" class="flxb-box__btn" data-box-move="down" aria-label="' . esc_attr__( 'Move down', 'flx-donate' ) . '" title="' . esc_attr__( 'Move down', 'flx-donate' ) . '">' . self::svg( '<path d="m6 9 6 6 6-6"/>' ) . '</button>'
			. '<button type="button" class="flxb-box__btn flxb-box__fold" data-box-fold aria-expanded="' . ( $closed ? 'false' : 'true' ) . '" aria-label="' . esc_attr__( 'Fold or unfold', 'flx-donate' ) . '" title="' . esc_attr__( 'Fold or unfold', 'flx-donate' ) . '">' . self::svg( '<path d="M5 12h14"/><path class="flxb-box__v" d="M12 5v14"/>' ) . '</button>'
			. '</div>';
	}

	/**
	 * Print the two columns.
	 *
	 * @param string  $screen Layout key (one per page kind, e.g. "donation").
	 * @param array[] $boxes  Boxes in their default order.
	 */
	public static function render( $screen, array $boxes ) {
		$screen = self::key( $screen );
		$cols   = self::arrange( $screen, $boxes );
		$closed = array_flip( self::prefs( $screen )['closed'] );
		echo '<div class="flxb-detail flxb-boxes" data-flxb-boxes="' . esc_attr( $screen ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'flxb_boxes' ) ) . '">';
		foreach ( $cols as $col => $list ) {
			echo '<div class="flxb-detail__' . esc_attr( $col ) . ' flxb-boxes__col" data-col="' . esc_attr( $col ) . '">';
			foreach ( $list as $b ) {
				$html = isset( $b['html'] ) ? (string) $b['html'] : self::card( $b );
				if ( '' === trim( $html ) ) {
					continue;
				}
				$is_closed = isset( $closed[ $b['id'] ] );
				echo '<div class="flxb-box' . ( $is_closed ? ' is-closed' : '' ) . '" data-box="' . esc_attr( $b['id'] ) . '">';
				echo self::tools( $b, $is_closed ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
				echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cards are escaped where they are built
				echo '</div>';
			}
			echo '</div>';
		}
		echo '</div>';
	}

	/** Save the order and the folded boxes (admin-ajax, flxb_boxes). */
	public static function ajax_save() {
		check_ajax_referer( 'flxb_boxes', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}
		$screen = self::key( wp_unslash( $_POST['screen'] ?? '' ) );
		if ( '' === $screen ) {
			wp_send_json_error( null, 400 );
		}
		$list = function ( $k ) {
			$raw = isset( $_POST[ $k ] ) ? explode( ',', (string) wp_unslash( $_POST[ $k ] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above
			return array_slice( array_values( array_unique( array_filter( array_map( array( __CLASS__, 'key' ), $raw ) ) ) ), 0, 60 );
		};
		$all = get_user_meta( get_current_user_id(), self::META, true );
		$all = is_array( $all ) ? $all : array();
		$all[ $screen ] = array(
			'main'   => $list( 'main' ),
			'side'   => $list( 'side' ),
			'closed' => $list( 'closed' ),
		);
		$all = array_slice( $all, -12, null, true ); // a few page kinds at most
		update_user_meta( get_current_user_id(), self::META, $all );
		wp_send_json_success();
	}
}
