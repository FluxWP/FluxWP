<?php
/**
 * Flux Donate: Partners (tracking module). List and edit partners, their priced agreements and
 * payments; Settlement tab: per period what was sent to each partner (or came in through it), what
 * it costs, what was paid, and the balance. CSV download per period.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Page_Partners {

	const SLUG = 'flxb-partners';

	public static function init() {
		foreach ( array( 'save', 'price_save', 'price_delete', 'pay_save', 'pay_delete', 'settle_csv', 'reprice' ) as $a ) {
			add_action( 'admin_post_flxb_partner_' . $a, array( __CLASS__, $a ) );
		}
	}

	private static function url( array $args = array() ) {
		return FLXB_Admin_Url::page( self::SLUG, $args );
	}

	private static function form( $action, $extra = '' ) {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxb-form"' . $extra . '><input type="hidden" name="action" value="flxb_partner_' . esc_attr( $action ) . '">' . wp_nonce_field( 'flxb_partner_' . $action, '_wpnonce', true, false );
	}

	private static function money_list( array $by_cur ) {
		if ( ! $by_cur ) {
			return '—';
		}
		$out = array();
		foreach ( $by_cur as $c => $v ) {
			$out[] = flxb_money( $v, $c );
		}
		return implode( ' + ', $out );
	}

	private static function currencies() {
		$c = array( flxb_default_currency(), 'TRY', 'USD', 'EUR', 'GBP' );
		return array_values( array_unique( (array) apply_filters( 'flxb_partner_currencies', $c ) ) );
	}

	private static function cur_select( $name, $cur ) {
		$h = '<select name="' . esc_attr( $name ) . '">';
		foreach ( self::currencies() as $c ) {
			$h .= '<option value="' . esc_attr( $c ) . '"' . selected( $cur, $c, false ) . '>' . esc_html( $c ) . '</option>';
		}
		return $h . '</select>';
	}

	public static function render() {
		if ( ! flxb_can_view() ) {
			FLXB_Admin::deny();
		}
		$tab = sanitize_key( FLXB_Admin::q( 'tab', 'list' ) );
		$id  = absint( FLXB_Admin::q( 'id' ) );
		if ( 'edit' === $tab || $id ) {
			self::edit( $id );
			return;
		}
		$partner = FLXB_Track::label( 'partner' );
		FLXB_Admin::header(
			__( 'Partners', 'flx-donate' ),
			/* translators: %s: what a partner is called on this site */
			sprintf( __( 'Who brings gifts in and who carries the work out (%s), their agreed prices and payments.', 'flx-donate' ), $partner ),
			flxb_can_manage() ? array( array( 'label' => __( 'New partner', 'flx-donate' ), 'url' => self::url( array( 'tab' => 'edit' ) ), 'primary' => true, 'icon' => 'plus' ) ) : array()
		);
		echo '<nav class="flxb-tabs">';
		foreach ( array( 'list' => __( 'Partners', 'flx-donate' ), 'settle' => __( 'Settlement', 'flx-donate' ) ) as $k => $label ) {
			echo '<a class="flxb-tab' . ( $k === $tab ? ' is-active' : '' ) . '" href="' . esc_url( self::url( array( 'tab' => $k ) ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
		if ( 'settle' === $tab ) {
			self::settle();
		} else {
			self::table();
		}
		FLXB_Admin::footer();
	}

	private static function table() {
		$all = FLXB_Partners::all();
		echo '<div class="flxb-panel">';
		if ( ! $all ) {
			echo '<div class="flxb-emptystate">' . FLXB_Admin::icon( 'users', 32 ) . '<h2>' . esc_html__( 'No partners yet', 'flx-donate' ) . '</h2><p>' . esc_html__( 'Add the places or organisations that carry the work out, and the offices that bring gifts in.', 'flx-donate' ) . '</p></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		$types = array();
		foreach ( FLXB_Types::all() as $t ) {
			$types[ (int) $t['id'] ] = $t['title'];
		}
		echo '<div class="flxb-tablewrap"><table class="flxb-table"><thead><tr><th scope="col">' . esc_html__( 'Name', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Role', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Donation types', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Agreement', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Contact', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Status', 'flx-donate' ) . '</th></tr></thead><tbody>';
		foreach ( $all as $id => $p ) {
			$roles = array();
			if ( (int) $p['is_service'] ) {
				$roles[] = FLXB_Track::label( 'partner' );
			}
			if ( (int) $p['is_source'] ) {
				$roles[] = __( 'Brings gifts in', 'flx-donate' );
			}
			$tn = array();
			foreach ( FLXB_Partners::type_ids( $p ) as $tid ) {
				$tn[] = $types[ $tid ] ?? '#' . $tid;
			}
			$ps  = FLXB_Partners::price_state( $id );
			$psl = array( 'none' => '—', 'on' => __( 'Active', 'flx-donate' ), 'off' => __( 'All off', 'flx-donate' ) );
			echo '<tr><td class="flxb-col-main"><a href="' . esc_url( self::url( array( 'id' => $id ) ) ) . '">' . esc_html( $p['name'] ) . '</a>' . ( '' !== (string) $p['group_name'] ? '<span class="flxb-sub2">' . esc_html( $p['group_name'] ) . '</span>' : '' ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Role', 'flx-donate' ) . '"><span>' . esc_html( implode( ', ', $roles ) ) . '</span></td>';
			echo '<td data-label="' . esc_attr__( 'Donation types', 'flx-donate' ) . '"><span>' . esc_html( $tn ? implode( ', ', $tn ) : __( 'All', 'flx-donate' ) ) . '</span></td>';
			echo '<td data-label="' . esc_attr__( 'Agreement', 'flx-donate' ) . '"><span>' . esc_html( $psl[ $ps ] ) . '</span></td>';
			echo '<td data-label="' . esc_attr__( 'Contact', 'flx-donate' ) . '"><span>' . esc_html( trim( $p['contact_name'] . ' ' . ( '' !== (string) $p['phone'] ? flxb_format_phone( $p['phone'] ) : '' ) ) ) . '</span></td>';
			echo '<td data-label="' . esc_attr__( 'Status', 'flx-donate' ) . '"><span>' . ( (int) $p['active'] ? '<span class="flxb-pill flxb-pill--ok">' . esc_html__( 'Active', 'flx-donate' ) . '</span>' : '<span class="flxb-pill flxb-pill--muted">' . esc_html__( 'Passive', 'flx-donate' ) . '</span>' ) . '</span></td></tr>';
		}
		echo '</tbody></table></div></div>';
	}

	private static function edit( $id ) {
		$p      = $id ? FLXB_Partners::get( $id ) : null;
		$can    = flxb_can_manage();
		$p      = $p ? $p : array( 'id' => 0, 'name' => '', 'pkey' => '', 'group_name' => '', 'is_source' => 0, 'is_service' => 1, 'type_ids' => '', 'contact_name' => '', 'phone' => '', 'email' => '', 'currency' => flxb_default_currency(), 'active' => 1, 'sort_order' => 0, 'note' => '' );
		$types  = FLXB_Types::all();
		$chosen = FLXB_Partners::type_ids( $p );
		FLXB_Admin::header( $id ? (string) $p['name'] : __( 'New partner', 'flx-donate' ), '', array(), array( __( 'Partners', 'flx-donate' ), self::url() ) );

		echo '<div class="flxb-grid flxb-grid--1-1"><section class="flxb-card"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'Partner', 'flx-donate' ) . '</h2></div>';
		echo self::form( 'save' ) . '<input type="hidden" name="id" value="' . (int) $id . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="flxb-fields-2"><div class="flxb-field"><label for="flxb-pn">' . esc_html__( 'Name', 'flx-donate' ) . '</label><input id="flxb-pn" type="text" name="name" maxlength="190" required value="' . esc_attr( $p['name'] ) . '"></div>';
		echo '<div class="flxb-field"><label for="flxb-pg">' . esc_html__( 'Group', 'flx-donate' ) . '</label><input id="flxb-pg" type="text" name="group_name" maxlength="190" value="' . esc_attr( $p['group_name'] ) . '"><p class="flxb-help">' . esc_html__( 'Optional, for reports (e.g. country or region).', 'flx-donate' ) . '</p></div></div>';
		echo '<div class="flxb-field"><span class="flxb-label">' . esc_html__( 'Role', 'flx-donate' ) . '</span>';
		echo '<input type="hidden" name="is_service" value="0"><label class="flxb-switch"><input type="checkbox" name="is_service" value="1"' . checked( (int) $p['is_service'], 1, false ) . '><span class="flxb-switch__ui" aria-hidden="true"></span>' . esc_html( sprintf( /* translators: %s: what a partner is called */ __( 'Carries the work out (%s)', 'flx-donate' ), FLXB_Track::label( 'partner' ) ) ) . '</label>';
		echo '<input type="hidden" name="is_source" value="0"><label class="flxb-switch"><input type="checkbox" name="is_source" value="1"' . checked( (int) $p['is_source'], 1, false ) . '><span class="flxb-switch__ui" aria-hidden="true"></span>' . esc_html__( 'Brings gifts in (office, organization)', 'flx-donate' ) . '</label></div>';
		echo '<div class="flxb-field"><span class="flxb-label">' . esc_html__( 'Donation types', 'flx-donate' ) . '</span><div class="flxb-checks">';
		foreach ( $types as $t ) {
			echo '<label class="flxb-check"><input type="checkbox" name="type_ids[]" value="' . (int) $t['id'] . '"' . checked( in_array( (int) $t['id'], $chosen, true ), true, false ) . '> ' . esc_html( $t['title'] ) . '</label>';
		}
		echo '</div><p class="flxb-help">' . esc_html__( 'None ticked = all types.', 'flx-donate' ) . '</p></div>';
		echo '<div class="flxb-fields-2"><div class="flxb-field"><label for="flxb-pc">' . esc_html__( 'Contact person', 'flx-donate' ) . '</label><input id="flxb-pc" type="text" name="contact_name" maxlength="190" value="' . esc_attr( $p['contact_name'] ) . '"></div>';
		echo '<div class="flxb-field"><label for="flxb-pp">' . esc_html__( 'Phone (WhatsApp)', 'flx-donate' ) . '</label><input id="flxb-pp" type="tel" name="phone" maxlength="32" value="' . esc_attr( $p['phone'] ) . '"></div></div>';
		echo '<div class="flxb-fields-amount"><div class="flxb-field"><label for="flxb-pe">' . esc_html__( 'E-mail', 'flx-donate' ) . '</label><input id="flxb-pe" type="email" name="email" maxlength="190" value="' . esc_attr( $p['email'] ) . '"></div>';
		echo '<div class="flxb-field"><label>' . esc_html__( 'Currency', 'flx-donate' ) . self::cur_select( 'currency', $p['currency'] ) . '</label></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="flxb-field"><label for="flxb-pnote">' . esc_html__( 'Note', 'flx-donate' ) . '</label><textarea id="flxb-pnote" name="note" rows="2">' . esc_textarea( (string) $p['note'] ) . '</textarea></div>';
		echo '<div class="flxb-fields-2"><div class="flxb-field"><label for="flxb-pk">' . esc_html__( 'Key', 'flx-donate' ) . '</label><input id="flxb-pk" type="text" name="pkey" maxlength="64" value="' . esc_attr( $p['pkey'] ) . '"><p class="flxb-help">' . esc_html__( 'Optional. Presets and imports find the partner by it.', 'flx-donate' ) . '</p></div>';
		echo '<div class="flxb-field"><input type="hidden" name="active" value="0"><label class="flxb-switch" style="margin-top:28px"><input type="checkbox" name="active" value="1"' . checked( (int) $p['active'], 1, false ) . '><span class="flxb-switch__ui" aria-hidden="true"></span>' . esc_html__( 'Active', 'flx-donate' ) . '</label></div></div>';
		if ( $can ) {
			echo '<div class="flxb-formactions"><button type="submit" class="flxb-btn flxb-btn--primary">' . esc_html__( 'Save', 'flx-donate' ) . '</button></div>';
		}
		echo '</form></section>';

		if ( ! $id ) {
			echo '<div></div></div>';
			FLXB_Admin::footer();
			return;
		}

		/* Payments */
		$pays = FLXB_Partners::payments( $id );
		echo '<section class="flxb-card"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'Payments', 'flx-donate' ) . '</h2><p class="flxb-card__hint">' . esc_html__( 'Money paid to the partner, or received from it. The settlement subtracts it.', 'flx-donate' ) . '</p></div></div>';
		if ( $pays ) {
			echo '<div class="flxb-tablewrap flxb-tablewrap--mid"><table class="flxb-table flxb-table--compact"><thead><tr><th scope="col">' . esc_html__( 'Date', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Direction', 'flx-donate' ) . '</th><th scope="col" class="num">' . esc_html__( 'Amount', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Note', 'flx-donate' ) . '</th><th></th></tr></thead><tbody>';
			foreach ( $pays as $py ) {
				echo '<tr><td>' . esc_html( flx_date( strtotime( $py['paid_at'] ), 'j M Y' ) ) . '</td><td>' . esc_html( 'in' === $py['direction'] ? __( 'Received', 'flx-donate' ) : __( 'Paid', 'flx-donate' ) ) . '</td><td class="num">' . esc_html( flxb_money( $py['amount'], $py['currency'] ) ) . '</td><td>' . esc_html( $py['note'] ) . '</td><td>';
				if ( $can ) {
					echo self::form( 'pay_delete', ' onsubmit="return confirm(' . esc_attr( wp_json_encode( __( 'Delete this payment?', 'flx-donate' ) ) ) . ')"' ) . '<input type="hidden" name="id" value="' . (int) $py['id'] . '"><input type="hidden" name="partner_id" value="' . (int) $id . '"><button class="flxb-linkbtn flxb-linkbtn--danger" type="submit">' . esc_html__( 'Delete', 'flx-donate' ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				echo '</td></tr>';
			}
			echo '</tbody></table></div>';
		}
		if ( $can ) {
			echo self::form( 'pay_save' ) . '<input type="hidden" name="partner_id" value="' . (int) $id . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<div class="flxb-fields-2"><div class="flxb-field"><label>' . esc_html__( 'Direction', 'flx-donate' ) . '<select name="direction"><option value="out">' . esc_html__( 'We paid', 'flx-donate' ) . '</option><option value="in">' . esc_html__( 'We received', 'flx-donate' ) . '</option></select></label></div><div class="flxb-field"><label>' . esc_html__( 'Date', 'flx-donate' ) . '<input type="date" name="paid_at" value="' . esc_attr( wp_date( 'Y-m-d' ) ) . '"></label></div></div>';
			echo '<div class="flxb-fields-amount"><div class="flxb-field"><label>' . esc_html__( 'Amount', 'flx-donate' ) . '<input type="text" inputmode="decimal" name="amount" required></label></div><div class="flxb-field"><label>' . esc_html__( 'Currency', 'flx-donate' ) . self::cur_select( 'currency', $p['currency'] ) . '</label></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<div class="flxb-field"><label>' . esc_html__( 'Note', 'flx-donate' ) . '<input type="text" name="note" maxlength="190"></label></div><div class="flxb-formactions"><button type="submit" class="flxb-btn">' . esc_html__( 'Add payment', 'flx-donate' ) . '</button></div></form>';
		}
		echo '</section></div>';

		/* Agreements */
		$tmap = array();
		foreach ( $types as $t ) {
			$tmap[ (int) $t['id'] ] = $t;
		}
		echo '<section class="flxb-card flxb-card--gap"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'Agreements', 'flx-donate' ) . '</h2><p class="flxb-card__hint">' . esc_html__( 'Agreed cost per type and option. The price valid on the day an item is sent is kept on the item. A partner whose agreements for a type are all switched off gets no new work for it.', 'flx-donate' ) . '</p></div></div>';
		$prices = FLXB_Partners::prices( $id );
		if ( $prices ) {
			echo '<div class="flxb-tablewrap flxb-tablewrap--mid"><table class="flxb-table flxb-table--compact"><thead><tr><th scope="col">' . esc_html__( 'Donation type', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Option', 'flx-donate' ) . '</th><th scope="col" class="num">' . esc_html__( 'Price', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Valid from', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Status', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Note', 'flx-donate' ) . '</th><th></th></tr></thead><tbody>';
			foreach ( $prices as $a ) {
				$t   = $tmap[ (int) $a['type_id'] ] ?? null;
				$opt = $t && '' !== (string) $a['option_key'] ? FLXB_Types::option( $t, (string) $a['option_key'] ) : null;
				echo '<tr><td>' . esc_html( $t ? $t['title'] : __( 'All', 'flx-donate' ) ) . '</td><td>' . esc_html( $opt ? $opt['label'] : ( '' !== (string) $a['option_key'] ? $a['option_key'] : __( 'All', 'flx-donate' ) ) ) . '</td><td class="num">' . esc_html( flxb_money( $a['price'], $a['currency'] ) ) . '</td><td>' . esc_html( flx_date( strtotime( $a['valid_from'] ), 'j M Y' ) ) . '</td><td>';
				if ( $can ) {
					echo self::form( 'price_save', ' style="display:inline"' ) . '<input type="hidden" name="id" value="' . (int) $a['id'] . '"><input type="hidden" name="toggle" value="1"><button class="flxb-linkbtn" type="submit">' . ( (int) $a['active'] ? '<span class="flxb-pill flxb-pill--ok">' . esc_html__( 'Active', 'flx-donate' ) . '</span>' : '<span class="flxb-pill flxb-pill--muted">' . esc_html__( 'Passive', 'flx-donate' ) . '</span>' ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
					echo (int) $a['active'] ? esc_html__( 'Active', 'flx-donate' ) : esc_html__( 'Passive', 'flx-donate' );
				}
				echo '</td><td>' . esc_html( $a['note'] ) . '</td><td>';
				if ( $can ) {
					echo self::form( 'price_delete', ' onsubmit="return confirm(' . esc_attr( wp_json_encode( __( 'Delete this agreement? Items already sent keep their price.', 'flx-donate' ) ) ) . ')"' ) . '<input type="hidden" name="id" value="' . (int) $a['id'] . '"><input type="hidden" name="partner_id" value="' . (int) $id . '"><button class="flxb-linkbtn flxb-linkbtn--danger" type="submit">' . esc_html__( 'Delete', 'flx-donate' ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				echo '</td></tr>';
			}
			echo '</tbody></table></div>';
		}
		if ( $can ) {
			echo self::form( 'price_save' ) . '<input type="hidden" name="partner_id" value="' . (int) $id . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<div class="flxb-fields-2"><div class="flxb-field"><label>' . esc_html__( 'Donation type and option', 'flx-donate' ) . '<select name="type_opt"><option value="0|">' . esc_html__( 'All types', 'flx-donate' ) . '</option>';
			foreach ( $types as $t ) {
				echo '<option value="' . (int) $t['id'] . '|">' . esc_html( $t['title'] ) . ' — ' . esc_html__( 'all options', 'flx-donate' ) . '</option>';
				foreach ( (array) $t['options'] as $o ) {
					echo '<option value="' . (int) $t['id'] . '|' . esc_attr( $o['key'] ) . '">' . esc_html( $t['title'] . ' — ' . $o['label'] ) . '</option>';
				}
			}
			echo '</select></label></div><div class="flxb-field"><label>' . esc_html__( 'Valid from', 'flx-donate' ) . '<input type="date" name="valid_from" value="' . esc_attr( wp_date( 'Y-m-d' ) ) . '"></label></div></div>';
			echo '<div class="flxb-fields-amount"><div class="flxb-field"><label>' . esc_html__( 'Price', 'flx-donate' ) . '<input type="text" inputmode="decimal" name="price" required></label></div><div class="flxb-field"><label>' . esc_html__( 'Currency', 'flx-donate' ) . self::cur_select( 'currency', $p['currency'] ) . '</label></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<div class="flxb-field"><label>' . esc_html__( 'Note', 'flx-donate' ) . '<input type="text" name="note" maxlength="190"></label></div><div class="flxb-formactions"><button type="submit" class="flxb-btn flxb-btn--primary">' . esc_html__( 'Add agreement', 'flx-donate' ) . '</button></div></form>';
		}
		echo '</section>';
		FLXB_Admin::footer();
	}

	/** Period from the request: from+to → range, month → month, else this month. */
	private static function period_from_get() {
		$from = sanitize_text_field( FLXB_Admin::q( 'from' ) );
		$to   = sanitize_text_field( FLXB_Admin::q( 'to' ) );
		$m    = sanitize_text_field( FLXB_Admin::q( 'month' ) );
		$d    = '/^\d{4}-\d{2}-\d{2}$/';
		if ( preg_match( $d, $from ) && preg_match( $d, $to ) ) {
			return $from . '..' . $to;
		}
		return preg_match( '/^\d{4}-\d{2}$/', $m ) ? $m : wp_date( 'Y-m' );
	}

	private static function settle() {
		$kind   = 'source' === FLXB_Admin::q( 'kind' ) ? 'source' : 'service';
		$period = self::period_from_get();
		$pid    = absint( FLXB_Admin::q( 'partner' ) );
		list( $from, $to ) = FLXB_Partners::period( $period );
		$label  = FLXB_Track::label( 'partner' );
		$base   = array( 'tab' => 'settle', 'kind' => $kind );

		echo '<div class="flxb-seg">';
		/* translators: %s: what a partner is called */
		foreach ( array( 'service' => sprintf( __( '%s (cost)', 'flx-donate' ), $label ), 'source' => __( 'Brought in (gift price)', 'flx-donate' ) ) as $k => $l ) {
			echo '<a class="' . ( $k === $kind ? 'is-on' : '' ) . '" href="' . esc_url( self::url( array( 'tab' => 'settle', 'kind' => $k, 'month' => preg_match( '/^\d{4}-\d{2}$/', $period ) ? $period : '' ) ) ) . '">' . esc_html( $l ) . '</a>';
		}
		echo '</div>';

		/* Month cards */
		echo '<div class="flxb-months">';
		foreach ( array_reverse( FLXB_Partners::month_stats( $kind, 12, $pid ), true ) as $m => $st ) {
			echo '<a class="flxb-month' . ( $m === $period ? ' is-on' : '' ) . '" href="' . esc_url( self::url( $base + array( 'month' => $m, 'partner' => $pid ? $pid : '' ) ) ) . '"><b>' . esc_html( flx_date( strtotime( $m . '-15' ), 'M Y' ) ) . '</b><span>' . esc_html( number_format_i18n( $st['n'] ) ) . '</span><small>' . esc_html( self::money_list( $st['sum'] ) ) . '</small></a>';
		}
		echo '</div>';

		echo '<form class="flxb-filters" method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '"><input type="hidden" name="tab" value="settle"><input type="hidden" name="kind" value="' . esc_attr( $kind ) . '">';
		echo '<label>' . esc_html__( 'From', 'flx-donate' ) . ' <input type="date" name="from" value="' . esc_attr( $from ) . '"></label><label>' . esc_html__( 'To', 'flx-donate' ) . ' <input type="date" name="to" value="' . esc_attr( $to ) . '"></label>';
		echo '<label><select name="partner"><option value="">' . esc_html__( 'All partners', 'flx-donate' ) . '</option>';
		foreach ( FLXB_Partners::all() as $i => $p ) {
			if ( 'service' === $kind ? (int) $p['is_service'] : (int) $p['is_source'] ) {
				echo '<option value="' . (int) $i . '"' . selected( $pid, $i, false ) . '>' . esc_html( $p['name'] ) . '</option>';
			}
		}
		echo '</select></label><div class="flxb-filters__end"><button class="flxb-btn flxb-btn--primary" type="submit">' . esc_html__( 'Show', 'flx-donate' ) . '</button> <a class="flxb-btn" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=flxb_partner_settle_csv&kind=' . $kind . '&period=' . rawurlencode( $period ) . '&partner=' . $pid ), 'flxb_partner_settle_csv' ) ) . '">' . FLXB_Admin::icon( 'download' ) . esc_html__( 'CSV', 'flx-donate' ) . '</a></div></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$res = FLXB_Partners::settlement( $kind, $period, $pid, (bool) $pid );
		echo '<div class="flxb-panel">';
		if ( ! $res ) {
			echo '<div class="flxb-emptystate"><h2>' . esc_html__( 'Nothing in this period', 'flx-donate' ) . '</h2></div></div>';
			return;
		}
		$service = 'service' === $kind;
		echo '<div class="flxb-tablewrap"><table class="flxb-table"><thead><tr><th scope="col">' . esc_html__( 'Partner', 'flx-donate' ) . '</th><th scope="col" class="num">' . esc_html__( 'Records', 'flx-donate' ) . '</th><th scope="col" class="num">' . esc_html( $service ? __( 'Cost', 'flx-donate' ) : __( 'Gift price', 'flx-donate' ) ) . '</th><th scope="col" class="num">' . esc_html( $service ? __( 'Paid', 'flx-donate' ) : __( 'Received', 'flx-donate' ) ) . '</th><th scope="col" class="num">' . esc_html__( 'Open', 'flx-donate' ) . '</th></tr></thead><tbody>';
		foreach ( $res as $id => $r ) {
			$moved = $service ? $r['paid'] : $r['received'];
			$open  = $r['sum'];
			foreach ( $moved as $c => $v ) {
				$open[ $c ] = ( $open[ $c ] ?? 0 ) - $v;
			}
			$open = array_filter(
				$open,
				function ( $v ) {
					return abs( $v ) > 0.004;
				}
			);
			echo '<tr><td class="flxb-col-main"><a href="' . esc_url( self::url( $base + array( 'partner' => $id, 'from' => $from, 'to' => $to ) ) ) . '">' . esc_html( $r['name'] ) . '</a>' . ( $r['uncosted'] ? '<span class="flxb-sub2">' . esc_html( sprintf( /* translators: %d: items without a price */ _n( '%d without a price', '%d without a price', $r['uncosted'], 'flx-donate' ), $r['uncosted'] ) ) . '</span>' : '' ) . '</td>';
			echo '<td class="num">' . esc_html( number_format_i18n( $r['n'] ) ) . '</td><td class="num">' . esc_html( self::money_list( $r['sum'] ) ) . '</td><td class="num">' . esc_html( self::money_list( $moved ) ) . '</td><td class="num"><b>' . esc_html( self::money_list( $open ) ) . '</b></td></tr>';
		}
		echo '</tbody></table></div></div>';

		if ( $pid && isset( $res[ $pid ] ) && $res[ $pid ]['rows'] ) {
			echo '<section class="flxb-card flxb-card--gap"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html( $res[ $pid ]['name'] ) . '</h2>';
			if ( $service && $res[ $pid ]['uncosted'] && flxb_can_manage() ) {
				echo self::form( 'reprice' ) . '<input type="hidden" name="partner_id" value="' . (int) $pid . '"><input type="hidden" name="period" value="' . esc_attr( $period ) . '"><button class="flxb-btn flxb-btn--sm" type="submit">' . esc_html__( 'Re-read prices from the agreements', 'flx-donate' ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div><div class="flxb-tablewrap"><table class="flxb-table flxb-table--compact"><thead><tr><th scope="col">' . esc_html__( 'Date', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Code', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'On behalf of', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Option', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Status', 'flx-donate' ) . '</th><th scope="col" class="num">' . esc_html__( 'Price', 'flx-donate' ) . '</th></tr></thead><tbody>';
			foreach ( $res[ $pid ]['rows'] as $r ) {
				echo '<tr><td>' . esc_html( flxb_date( $r['day'] ) ) . '</td><td><a class="flxb-mono" href="' . esc_url( FLXB_Page_Track::url( (int) $r['id'] ) ) . '">' . esc_html( $r['code'] ) . '</a></td><td>' . esc_html( $r['beneficiary_name'] ) . '</td><td>' . esc_html( $r['option_label'] ) . '</td><td>' . FLXB_Track::status_pill( $r['status'], (int) $r['type_id'] ) . '</td><td class="num">' . esc_html( '' !== (string) $r['cur'] && (float) $r['price'] > 0 ? flxb_money( $r['price'], $r['cur'] ) : '—' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</tbody></table></div></section>';
		}
	}

	/* ---------- Handlers ---------- */

	private static function guard( $action ) {
		if ( ! flxb_can_manage() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_partner_' . $action );
	}

	private static function p( $k ) {
		return isset( $_POST[ $k ] ) ? wp_unslash( $_POST[ $k ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
	}

	public static function save() {
		self::guard( 'save' );
		$id = absint( self::p( 'id' ) );
		$d  = array();
		foreach ( array( 'name', 'pkey', 'group_name', 'is_source', 'is_service', 'contact_name', 'phone', 'email', 'currency', 'active', 'note' ) as $k ) {
			$d[ $k ] = self::p( $k );
		}
		$d['type_ids'] = (array) self::p( 'type_ids' );
		$r             = FLXB_Partners::save( $d, $id );
		if ( is_wp_error( $r ) ) {
			FLXB_Admin::redirect( self::SLUG, array( 'tab' => 'edit', 'id' => $id, 'flxb_msg' => 'error', 'flxb_err' => $r->get_error_message() ) );
		}
		FLXB_Admin::redirect( self::SLUG, array( 'id' => $r, 'flxb_msg' => 'saved' ) );
	}

	public static function price_save() {
		self::guard( 'price_save' );
		$id = absint( self::p( 'id' ) );
		if ( $id && self::p( 'toggle' ) ) {
			global $wpdb;
			$t = flxb_table( 'partner_prices' );
			$a = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $a ) {
				$wpdb->update( $t, array( 'active' => (int) $a['active'] ? 0 : 1 ), array( 'id' => $id ) );
				FLXB_Admin::redirect( self::SLUG, array( 'id' => (int) $a['partner_id'], 'flxb_msg' => 'saved' ) );
			}
			FLXB_Admin::redirect( self::SLUG );
		}
		list( $tid, $opt ) = array_pad( explode( '|', (string) self::p( 'type_opt' ), 2 ), 2, '' );
		$r = FLXB_Partners::save_price(
			array(
				'partner_id' => self::p( 'partner_id' ),
				'type_id'    => $tid,
				'option_key' => $opt,
				'price'      => self::p( 'price' ),
				'currency'   => self::p( 'currency' ),
				'valid_from' => self::p( 'valid_from' ),
				'note'       => self::p( 'note' ),
			)
		);
		FLXB_Admin::redirect( self::SLUG, array( 'id' => absint( self::p( 'partner_id' ) ), 'flxb_msg' => is_wp_error( $r ) ? 'error' : 'saved' ) );
	}

	public static function price_delete() {
		self::guard( 'price_delete' );
		FLXB_Partners::delete_price( absint( self::p( 'id' ) ) );
		FLXB_Admin::redirect( self::SLUG, array( 'id' => absint( self::p( 'partner_id' ) ), 'flxb_msg' => 'deleted' ) );
	}

	public static function pay_save() {
		self::guard( 'pay_save' );
		$r = FLXB_Partners::add_payment(
			array(
				'partner_id' => self::p( 'partner_id' ),
				'direction'  => self::p( 'direction' ),
				'amount'     => self::p( 'amount' ),
				'currency'   => self::p( 'currency' ),
				'paid_at'    => self::p( 'paid_at' ),
				'note'       => self::p( 'note' ),
			)
		);
		FLXB_Admin::redirect( self::SLUG, array( 'id' => absint( self::p( 'partner_id' ) ), 'flxb_msg' => is_wp_error( $r ) ? 'error' : 'saved', 'flxb_err' => is_wp_error( $r ) ? $r->get_error_message() : '' ) );
	}

	public static function pay_delete() {
		self::guard( 'pay_delete' );
		FLXB_Partners::delete_payment( absint( self::p( 'id' ) ) );
		FLXB_Admin::redirect( self::SLUG, array( 'id' => absint( self::p( 'partner_id' ) ), 'flxb_msg' => 'deleted' ) );
	}

	public static function reprice() {
		self::guard( 'reprice' );
		$pid = absint( self::p( 'partner_id' ) );
		$res = FLXB_Partners::settlement( 'service', sanitize_text_field( (string) self::p( 'period' ) ), $pid, true );
		$ids = isset( $res[ $pid ] ) ? wp_list_pluck( $res[ $pid ]['rows'], 'id' ) : array();
		FLXB_Partners::reprice( $ids );
		list( $from, $to ) = FLXB_Partners::period( sanitize_text_field( (string) self::p( 'period' ) ) );
		FLXB_Admin::redirect( self::SLUG, array( 'tab' => 'settle', 'kind' => 'service', 'partner' => $pid, 'from' => $from, 'to' => $to, 'flxb_msg' => 'saved' ) );
	}

	public static function settle_csv() {
		if ( ! flxb_can_view() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_partner_settle_csv' );
		$kind   = 'source' === FLXB_Admin::q( 'kind' ) ? 'source' : 'service';
		$period = sanitize_text_field( FLXB_Admin::q( 'period' ) );
		$res    = FLXB_Partners::settlement( $kind, $period, absint( FLXB_Admin::q( 'partner' ) ), true );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( 'settlement-' . $kind . '-' . str_replace( '..', '_', $period ) . '.csv' ) . '"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array_map( 'flxb_csv_cell', array( __( 'Partner', 'flx-donate' ), __( 'Date', 'flx-donate' ), __( 'Code', 'flx-donate' ), __( 'On behalf of', 'flx-donate' ), __( 'Option', 'flx-donate' ), __( 'Status', 'flx-donate' ), __( 'Price', 'flx-donate' ), __( 'Currency', 'flx-donate' ) ) ) );
		foreach ( $res as $r ) {
			foreach ( $r['rows'] as $x ) {
				$sts = FLXB_Track::statuses( (int) $x['type_id'] );
				fputcsv( $out, array_map( 'flxb_csv_cell', array( $r['name'], $x['day'], $x['code'], $x['beneficiary_name'], $x['option_label'], $sts[ $x['status'] ] ?? $x['status'], $x['price'], $x['cur'] ) ) );
			}
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
