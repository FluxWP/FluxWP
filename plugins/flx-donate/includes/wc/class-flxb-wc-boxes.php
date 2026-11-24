<?php
/**
 * 2.34.0: the WooCommerce order's boxes on the donation page.
 *
 * Boxes that other plugins add to the WooCommerce order screen (LiteSpeed, Google for WooCommerce,
 * order attribution …) open on the donation page as Flux cards, so the order screen is not needed.
 * WooCommerce's own boxes (order data, items, actions, downloads, notes, custom fields) are left out:
 * the donation page has its own. The notes get a native box here.
 *
 * Saving: one "Save" posts the fields of every such box together, like the order screen's single
 * form, and runs woocommerce_process_shop_order_meta (the hook these plugins save on) without
 * WooCommerce's own handlers, so nothing else of the order changes. A plugin that saves only on
 * save_post (posts storage) is not covered; its box offers the order screen link.
 *
 * Filters: flxb_wc_box_skip ( string[] $ids ) — box ids not to show; flxb_wc_boxes ( array $boxes, WC_Order ).
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

final class FLXB_WC_Boxes {

	/** WooCommerce's own boxes (the donation page shows these its own way) and ours. */
	const SKIP = array( 'woocommerce-order-data', 'woocommerce-order-items', 'woocommerce-order-notes', 'woocommerce-order-actions', 'woocommerce-order-downloads', 'order_custom', 'postcustom', 'submitdiv', 'slugdiv', 'commentsdiv', 'commentstatusdiv', 'flxwb-box' );

	public static function init() {
		add_action( 'admin_post_flxb_wc_boxes_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_flxb_wc_note_add', array( __CLASS__, 'note_add' ) );
		add_action( 'admin_post_flxb_wc_note_delete', array( __CLASS__, 'note_delete' ) );
	}

	public static function can( $order ) {
		return $order instanceof WC_Order && ( current_user_can( 'edit_shop_orders' ) || current_user_can( 'manage_woocommerce' ) ) && flxb_can_manage() && ! FLXB_Refs::own_only();
	}

	private static function hpos() {
		return class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * Boxes of the order screen, other than WooCommerce's own.
	 *
	 * @return array[] Boxes for FLXB_Boxes (id wc:<box id>).
	 */
	public static function collect( WC_Order $order ) {
		global $wp_meta_boxes, $post, $theorder;
		if ( ! function_exists( 'add_meta_box' ) ) {
			require_once ABSPATH . 'wp-admin/includes/template.php';
		}
		$hpos   = self::hpos();
		$screen = $hpos && function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		$obj    = $hpos ? $order : get_post( $order->get_id() );
		if ( ! $obj ) {
			return array();
		}
		$keep_boxes = $wp_meta_boxes;
		$keep_post  = $post;
		$keep_order = $theorder;
		$theorder   = $order; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WooCommerce's own global, restored below
		if ( ! $hpos ) {
			$post = $obj; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below
			setup_postdata( $post );
		}
		$out  = array();
		$seen = array();
		$skip = array_flip( (array) apply_filters( 'flxb_wc_box_skip', self::SKIP ) );
		try {
			$wp_meta_boxes[ $screen ] = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			do_action( 'add_meta_boxes', $screen, $obj );
			do_action( 'add_meta_boxes_' . $screen, $obj );
			foreach ( array( 'normal', 'advanced', 'side' ) as $context ) {
				foreach ( array( 'high', 'core', 'default', 'low' ) as $prio ) {
					foreach ( (array) ( $wp_meta_boxes[ $screen ][ $context ][ $prio ] ?? array() ) as $id => $box ) {
						if ( ! $box || ! is_array( $box ) || isset( $skip[ $id ] ) || empty( $box['callback'] ) || ! is_callable( $box['callback'] ) ) {
							continue;
						}
						ob_start();
						try {
							call_user_func( $box['callback'], $obj, $box );
						} catch ( Throwable $e ) {
							echo '<p class="flxb-notice flxb-notice--error">' . esc_html__( 'This box could not be shown.', 'flx-donate' ) . '</p>';
						}
						$body = trim( (string) ob_get_clean() );
						if ( '' === $body ) {
							continue;
						}
						$seen[ $id ] = true;
						$fields      = (bool) preg_match( '/<(input|select|textarea)\b(?![^>]*type=["\']?(hidden|button|submit)\b)/i', $body );
						$out[]  = array(
							'id'     => 'wc:' . $id,
							'title'  => trim( wp_strip_all_tags( (string) $box['title'] ) ),
							'col'    => 'side' === $context ? 'side' : 'main',
							'class'  => 'flxb-xbox--wc' . ( $fields ? ' flxb-xbox--form' : '' ),
							'body'   => '<div class="flxb-xbox__wc" data-flxb-wcbox="' . esc_attr( $id ) . '">' . $body . '</div>' . ( $fields ? self::save_row() : '' ),
						);
					}
				}
			}
		} catch ( Throwable $e ) {
			$out = array();
		}
		// Scripts the order screen loads for these boxes (late enqueue: printed in the footer).
		if ( isset( $seen['woocommerce-order-source-data'] ) && defined( 'WC_PLUGIN_FILE' ) && defined( 'WC_VERSION' ) ) {
			wp_enqueue_script( 'woocommerce-order-attribution-admin-js', plugins_url( 'assets/js/admin/order-attribution-admin.min.js', WC_PLUGIN_FILE ), array( 'jquery' ), WC_VERSION, true );
		}
		/**
		 * A plugin whose box needs its order-screen script enqueues it here (the donation page is not the order screen).
		 *
		 * @param WC_Order $order Order.
		 * @param string[] $ids   Box ids shown.
		 */
		do_action( 'flxb_wc_boxes_enqueue', $order, array_keys( $seen ) );
		$wp_meta_boxes = $keep_boxes; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$theorder      = $keep_order; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		if ( ! $hpos ) {
			$post = $keep_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			wp_reset_postdata();
		}
		return (array) apply_filters( 'flxb_wc_boxes', $out, $order );
	}

	private static function save_row() {
		return '<div class="flxb-xbox__save"><button type="button" class="flxb-btn flxb-btn--sm flxb-btn--primary" data-flxb-xsave>' . esc_html__( 'Save', 'flx-donate' ) . '</button><span class="flxb-muted">' . esc_html__( 'Saves the fields of every order box on this page.', 'flx-donate' ) . '</span></div>';
	}

	/** Hidden form the "Save" buttons fill (admin.js copies the boxes' fields into it). */
	public static function save_form( WC_Order $order, $back ) {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" id="flxb-xform" hidden>'
			. wp_nonce_field( 'flxb_wc_boxes_' . $order->get_id(), '_flxb_nonce', true, false )
			. wp_nonce_field( 'woocommerce_save_data', 'woocommerce_meta_nonce', false, false )
			. '<input type="hidden" name="action" value="flxb_wc_boxes_save"><input type="hidden" name="flxb_order" value="' . (int) $order->get_id() . '"><input type="hidden" name="post_ID" value="' . (int) $order->get_id() . '"><input type="hidden" name="flxb_back" value="' . esc_url( $back ) . '"></form>';
	}

	private static function back( $fallback = '' ) {
		$back = isset( $_POST['flxb_back'] ) ? esc_url_raw( wp_unslash( $_POST['flxb_back'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked by the caller
		return wp_validate_redirect( $back, $fallback ? $fallback : FLXB_Admin_Url::page( 'flxb-donations' ) );
	}

	private static function done( $back, $ok, $msg ) {
		wp_safe_redirect( add_query_arg( array( 'flxb_msg' => $ok ? 'saved' : 'warn', 'flxb_err' => rawurlencode( $msg ) ), $back ) );
		exit;
	}

	/** Save the boxes' fields through the plugins' own order-save hook. */
	public static function save() {
		$oid = isset( $_POST['flxb_order'] ) ? absint( $_POST['flxb_order'] ) : 0;
		check_admin_referer( 'flxb_wc_boxes_' . $oid, '_flxb_nonce' );
		$order = $oid ? wc_get_order( $oid ) : null;
		if ( ! self::can( $order ) ) {
			FLXB_Admin::deny();
		}
		$back = self::back();
		// WooCommerce's own handlers read the whole order form; that form is not posted here.
		foreach ( array( 'WC_Meta_Box_Order_Items' => 10, 'WC_Meta_Box_Order_Downloads' => 30, 'WC_Meta_Box_Order_Data' => 40, 'WC_Meta_Box_Order_Actions' => 50 ) as $cls => $prio ) {
			remove_action( 'woocommerce_process_shop_order_meta', $cls . '::save', $prio );
		}
		try {
			do_action( 'woocommerce_process_shop_order_meta', $order->get_id(), $order );
			$order->save();
		} catch ( Throwable $e ) {
			self::done( $back, false, __( 'The order boxes could not be saved.', 'flx-donate' ) );
		}
		self::done( $back, true, __( 'Saved.', 'flx-donate' ) );
	}

	/** Native notes box (order notes, like the order screen). */
	public static function notes_box( WC_Order $order, $back ) {
		$notes = function_exists( 'wc_get_order_notes' ) ? wc_get_order_notes( array( 'order_id' => $order->get_id() ) ) : array();
		$post  = esc_url( admin_url( 'admin-post.php' ) );
		$html  = '<ul class="flxb-notes">';
		if ( ! $notes ) {
			$html .= '<li class="flxb-muted">' . esc_html__( 'No notes yet.', 'flx-donate' ) . '</li>';
		}
		foreach ( $notes as $n ) {
			$cls   = $n->customer_note ? ' is-customer' : ( 'system' === $n->added_by ? ' is-system' : '' );
			$by    = 'system' === $n->added_by ? __( 'System', 'flx-donate' ) : (string) $n->added_by;
			$when  = $n->date_created ? flxb_date( gmdate( 'Y-m-d H:i:s', $n->date_created->getTimestamp() ) ) : '';
			$html .= '<li class="flxb-note' . $cls . '"><div class="flxb-note__text">' . wpautop( wptexturize( wp_kses_post( (string) $n->content ) ) ) . '</div><div class="flxb-note__meta"><span>' . esc_html( trim( $when . ' · ' . $by, ' ·' ) ) . ( $n->customer_note ? ' · ' . esc_html__( 'to the donor', 'flx-donate' ) : '' ) . '</span>'
				. '<form method="post" action="' . $post . '" class="flxb-inline-form" data-flx-confirm="' . esc_attr__( 'Delete this note?', 'flx-donate' ) . '" data-flx-confirm-danger="1">' . wp_nonce_field( 'flxb_wc_note_' . $order->get_id(), '_wpnonce', true, false ) . '<input type="hidden" name="action" value="flxb_wc_note_delete"><input type="hidden" name="flxb_order" value="' . (int) $order->get_id() . '"><input type="hidden" name="note" value="' . (int) $n->id . '"><input type="hidden" name="flxb_back" value="' . esc_url( $back ) . '"><button type="submit" class="flxb-linkbtn flxb-linkbtn--danger">' . esc_html__( 'Delete', 'flx-donate' ) . '</button></form></div></li>';
		}
		$html .= '</ul>';
		$html .= '<form method="post" action="' . $post . '" class="flxb-noteform">' . wp_nonce_field( 'flxb_wc_note_' . $order->get_id(), '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="flxb_wc_note_add"><input type="hidden" name="flxb_order" value="' . (int) $order->get_id() . '"><input type="hidden" name="flxb_back" value="' . esc_url( $back ) . '">'
			. '<label class="screen-reader-text" for="flxb-note-text">' . esc_html__( 'Add note', 'flx-donate' ) . '</label><textarea id="flxb-note-text" name="note" rows="3" required placeholder="' . esc_attr__( 'Add a note…', 'flx-donate' ) . '"></textarea>'
			. '<div class="flxb-noteform__row"><label class="screen-reader-text" for="flxb-note-type">' . esc_html__( 'Note type', 'flx-donate' ) . '</label><select id="flxb-note-type" name="type"><option value="private">' . esc_html__( 'Private note', 'flx-donate' ) . '</option><option value="customer">' . esc_html__( 'Note to the donor (sent by e-mail)', 'flx-donate' ) . '</option></select>'
			. '<button type="submit" class="flxb-btn flxb-btn--sm">' . esc_html__( 'Add', 'flx-donate' ) . '</button></div></form>';
		return $html;
	}

	public static function note_add() {
		$oid = isset( $_POST['flxb_order'] ) ? absint( $_POST['flxb_order'] ) : 0;
		check_admin_referer( 'flxb_wc_note_' . $oid );
		$order = $oid ? wc_get_order( $oid ) : null;
		if ( ! self::can( $order ) ) {
			FLXB_Admin::deny();
		}
		$text = isset( $_POST['note'] ) ? wp_kses_post( trim( (string) wp_unslash( $_POST['note'] ) ) ) : '';
		$back = self::back();
		if ( '' === $text ) {
			self::done( $back, false, __( 'Write the note first.', 'flx-donate' ) );
		}
		$customer = isset( $_POST['type'] ) && 'customer' === $_POST['type'];
		$order->add_order_note( $text, $customer ? 1 : 0, true );
		self::done( $back, true, __( 'Note added.', 'flx-donate' ) );
	}

	public static function note_delete() {
		$oid = isset( $_POST['flxb_order'] ) ? absint( $_POST['flxb_order'] ) : 0;
		check_admin_referer( 'flxb_wc_note_' . $oid );
		$order = $oid ? wc_get_order( $oid ) : null;
		if ( ! self::can( $order ) ) {
			FLXB_Admin::deny();
		}
		$nid  = isset( $_POST['note'] ) ? absint( $_POST['note'] ) : 0;
		$note = $nid ? wc_get_order_note( $nid ) : null;
		$back = self::back();
		if ( ! $note || (int) get_comment( $nid )->comment_post_ID !== (int) $order->get_id() ) {
			self::done( $back, false, __( 'The note was not found.', 'flx-donate' ) );
		}
		wc_delete_order_note( $nid );
		self::done( $back, true, __( 'Note deleted.', 'flx-donate' ) );
	}
}
