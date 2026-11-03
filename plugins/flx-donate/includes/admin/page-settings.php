<?php
/**
 * Flux Donate: settings (general, import of earlier payments; other modules add tabs).
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Page_Settings {

	public static function tabs() {
		$tabs = array(
			'general'      => __( 'General', 'flx-donate' ),
			'organisation' => __( 'Organisation', 'flx-donate' ),
			'categories'   => __( 'Categories', 'flx-donate' ),
		);
		if ( flxb_track_enabled() ) {
			$tabs['kurban'] = __( 'Tracking', 'flx-donate' );
		}
		$tabs['tabs']   = __( 'Extra tabs', 'flx-donate' );
		$tabs['import'] = __( 'Import', 'flx-donate' );
		if ( ! class_exists( 'FLXB_System_Checks' ) || ! FLXB_System_Checks::external() ) {
			$tabs['system'] = __( 'System check', 'flx-donate' ); // 2.9.0: with Flux Speed the screen lives there
		}
		return apply_filters( 'flxb_settings_tabs', $tabs );
	}

	public static function render() {
		if ( ! flxb_can_manage() ) {
			FLXB_Admin::deny();
		}
		$tabs = self::tabs();
		$tab  = FLXB_Admin::q( 'tab', 'general' );
		$tab  = isset( $tabs[ $tab ] ) ? $tab : 'general';
		FLXB_Admin::header( __( 'Donation settings', 'flx-donate' ) );
		echo '<nav class="flxb-tabs" aria-label="' . esc_attr__( 'Settings sections', 'flx-donate' ) . '">';
		foreach ( $tabs as $k => $label ) {
			echo '<a class="flxb-tab' . ( $k === $tab ? ' is-active' : '' ) . '" href="' . esc_url( FLXB_Admin_Url::page( 'flxb-settings', array( 'tab' => $k ) ) ) . '"' . ( $k === $tab ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
		if ( 'general' === $tab ) {
			self::general();
		} elseif ( 'organisation' === $tab ) {
			self::organisation();
		} elseif ( 'categories' === $tab ) {
			self::categories();
		} elseif ( 'import' === $tab ) {
			self::import();
		} elseif ( 'kurban' === $tab ) {
			self::kurban();
		} elseif ( 'tabs' === $tab ) {
			self::xtabs();
		} elseif ( 'system' === $tab && class_exists( 'FLXB_System_Checks' ) ) {
			FLXB_System_Checks::render_tab();
		} else {
			do_action( 'flxb_settings_tab_' . $tab );
		}
		FLXB_Admin::footer();
	}

	private static function general() {
		$s     = flxb_settings();
		$forms = array_filter( flxb_forms(), function ( $f ) {
			return 'payment' === $f['type'];
		} );
		echo '<div class="flxb-grid flxb-grid--2-1"><section class="flxb-card"><form class="flxb-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="flxb_settings_save"><input type="hidden" name="tab" value="general">';
		wp_nonce_field( 'flxb_settings_save' );
		echo '<div class="flxb-field"><label for="flxb-currency">' . esc_html__( 'Default currency', 'flx-donate' ) . '</label><select id="flxb-currency" name="currency">';
		foreach ( array( 'TRY' => __( 'Turkish lira (₺)', 'flx-donate' ), 'EUR' => __( 'Euro (€)', 'flx-donate' ), 'USD' => __( 'US dollar ($)', 'flx-donate' ), 'GBP' => __( 'Pound sterling (£)', 'flx-donate' ) ) as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $s['currency'], $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><p class="flxb-help">' . esc_html__( 'Dashboard totals are in this currency; gifts in other currencies are listed but not added to the totals.', 'flx-donate' ) . '</p></div>';

		echo '<div class="flxb-field"><input type="hidden" name="module_track" value="0"><label class="flxb-switch"><input type="checkbox" name="module_track" value="1"' . checked( ! empty( $s['module_track'] ), true, false ) . '><span class="flxb-switch__ui" aria-hidden="true"></span>' . esc_html__( 'Tracking module', 'flx-donate' ) . '</label><p class="flxb-help">' . esc_html__( 'Chosen donation types get one item per unit with a code, sent to partners (with agreed prices and settlement), files and history. Leave it off if you do not need it.', 'flx-donate' ) . '</p></div>';
		echo '<div class="flxb-field"><input type="hidden" name="module_reports" value="0"><label class="flxb-switch"><input type="checkbox" name="module_reports" value="1"' . checked( ! empty( $s['module_reports'] ), true, false ) . '><span class="flxb-switch__ui" aria-hidden="true"></span>' . esc_html__( 'Reports', 'flx-donate' ) . '</label><p class="flxb-help">' . esc_html__( 'A Reports screen with editable JSON reports (always on with the tracking module).', 'flx-donate' ) . '</p></div>';
		echo '<div class="flxb-field"><input type="hidden" name="module_kurban" value="0"><label class="flxb-switch"><input type="checkbox" name="module_kurban" value="1"' . checked( ! empty( $s['module_kurban'] ), true, false ) . '><span class="flxb-switch__ui" aria-hidden="true"></span>' . esc_html__( 'Gifts on behalf of a person', 'flx-donate' ) . '</label><p class="flxb-help">' . esc_html__( 'Adds a proxy (vekâlet) text the donor accepts, intentions and one item per unit with the name of the person it is given for. Turns tracking on too.', 'flx-donate' ) . '</p></div>';

		echo '<fieldset class="flxb-field"><legend class="flxb-label">' . esc_html__( 'Which payments are donations?', 'flx-donate' ) . '</legend>';
		echo '<label class="flxb-check"><input type="radio" name="donation_forms" value="payments"' . checked( $s['donation_forms'], 'payments', false ) . '> ' . esc_html__( 'Every payment taken through a Flux Forms form', 'flx-donate' ) . '</label>';
		echo '<label class="flxb-check"><input type="radio" name="donation_forms" value="list"' . checked( $s['donation_forms'], 'list', false ) . '> ' . esc_html__( 'Only payments through the forms selected below (and forms linked to a donation type)', 'flx-donate' ) . '</label>';
		if ( $forms ) {
			echo '<div class="flxb-checks">';
			foreach ( $forms as $fid => $f ) {
				echo '<label class="flxb-check"><input type="checkbox" name="form_ids[]" value="' . (int) $fid . '"' . checked( in_array( (int) $fid, array_map( 'intval', (array) $s['form_ids'] ), true ), true, false ) . '> ' . esc_html( $f['title'] ) . ' <span class="flxb-muted">#' . (int) $fid . '</span></label>';
			}
			echo '</div>';
		}
		echo '</fieldset>';
		echo '<div class="flxb-formactions"><button type="submit" class="flxb-btn flxb-btn--primary">' . esc_html__( 'Save settings', 'flx-donate' ) . '</button></div></form></section>';

		echo '<aside class="flxb-card"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'How donations are recorded', 'flx-donate' ) . '</h2></div><p>' . esc_html__( 'A donation is stored when a Flux Forms payment is completed: at the final form submission (with name, phone and e-mail from the form), from the payment provider\'s notifications (refunds, renewals of recurring gifts) and by an hourly check that catches payments whose form was not submitted.', 'flx-donate' ) . '</p><p>' . esc_html__( 'Donors are matched on phone number or e-mail address, never on name alone.', 'flx-donate' ) . '</p></aside></div>';
		/* 2.9.0: the iyzico addresses were under System check; with Flux Speed that screen is not ours. */
		if ( class_exists( 'FLXB_System_Checks' ) && FLXB_System_Checks::external() ) {
			FLXB_System_Checks::iyzico_box();
		}
	}

	private static function organisation() {
		$s     = flxb_settings();
		$b     = flxb_brand();
		$logo  = ! empty( $s['logo_id'] ) ? wp_get_attachment_image_url( (int) $s['logo_id'], 'medium' ) : '';
		$theme = flxb_theme_color( 'primary' );
		echo '<div class="flxb-grid flxb-grid--2-1"><section class="flxb-card"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'Organisation', 'flx-donate' ) . '</h2><p class="flxb-card__hint">' . esc_html__( 'Shown on receipts, donation pages and in exports. Empty fields fall back to the site name, the theme logo and the theme colours.', 'flx-donate' ) . '</p></div></div>';
		echo '<form class="flxb-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="flxb_settings_save"><input type="hidden" name="tab" value="organisation">';
		wp_nonce_field( 'flxb_settings_save' );
		echo '<div class="flxb-field"><label for="flxb-org">' . esc_html__( 'Organisation name', 'flx-donate' ) . '</label><input type="text" id="flxb-org" name="org_name" maxlength="190" value="' . esc_attr( (string) $s['org_name'] ) . '" placeholder="' . esc_attr( get_bloginfo( 'name' ) ) . '"></div>';
		echo '<div class="flxb-field"><span class="flxb-label">' . esc_html__( 'Logo', 'flx-donate' ) . '</span><div class="flxb-media" data-flxb-media><input type="hidden" name="logo_id" value="' . (int) $s['logo_id'] . '"><img alt="" src="' . esc_url( (string) $logo ) . '"' . ( $logo ? '' : ' hidden' ) . '><button type="button" class="flxb-btn flxb-btn--sm" data-flxb-media-pick data-title="' . esc_attr__( 'Choose a logo', 'flx-donate' ) . '">' . esc_html__( 'Choose logo', 'flx-donate' ) . '</button><button type="button" class="flxb-linkbtn flxb-linkbtn--danger" data-flxb-media-clear' . ( $logo ? '' : ' hidden' ) . '>' . esc_html__( 'Remove', 'flx-donate' ) . '</button></div></div>';
		echo '<div class="flxb-field"><label for="flxb-color">' . esc_html__( 'Brand colour', 'flx-donate' ) . '</label><input type="text" id="flxb-color" name="primary_color" maxlength="7" pattern="#[0-9a-fA-F]{6}" value="' . esc_attr( (string) $s['primary_color'] ) . '" placeholder="' . esc_attr( '' !== $theme ? $theme : '#1F2937' ) . '"> <span class="flxb-swatch" style="background:' . esc_attr( $b['primary'] ) . '"></span><p class="flxb-help">' . esc_html__( 'Hex colour such as #1F2937. Empty = the theme\'s primary colour.', 'flx-donate' ) . '</p></div>';
		echo '<div class="flxb-field"><label for="flxb-receipt">' . esc_html__( 'Receipt text', 'flx-donate' ) . '</label><textarea id="flxb-receipt" name="receipt_text" rows="3">' . esc_textarea( (string) $s['receipt_text'] ) . '</textarea><p class="flxb-help">' . esc_html__( 'A thank-you note printed on the donation receipt.', 'flx-donate' ) . '</p></div>';
		echo '<div class="flxb-field"><label for="flxb-footer">' . esc_html__( 'Footer text', 'flx-donate' ) . '</label><textarea id="flxb-footer" name="footer_text" rows="3">' . esc_textarea( (string) $s['footer_text'] ) . '</textarea><p class="flxb-help">' . esc_html__( 'Address, registration number, bank details and similar; printed at the bottom of receipts.', 'flx-donate' ) . '</p></div>';
		echo '<div class="flxb-formactions"><button type="submit" class="flxb-btn flxb-btn--primary">' . esc_html__( 'Save settings', 'flx-donate' ) . '</button></div></form></section>';
		echo '<aside class="flxb-card"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'Preview', 'flx-donate' ) . '</h2></div><div class="flxb-person">';
		if ( $b['logo'] ) {
			echo '<img src="' . esc_url( $b['logo'] ) . '" alt="" style="max-width:72px;max-height:72px;border-radius:12px">';
		}
		echo '<div><div class="flxb-person__name">' . esc_html( $b['name'] ) . '</div><div class="flxb-muted"><span class="flxb-swatch" style="background:' . esc_attr( $b['primary'] ) . '"></span> <span class="flxb-swatch" style="background:' . esc_attr( $b['primary_dark'] ) . '"></span></div></div></div></aside></div>';
	}

	private static function categories() {
		$cats = flxb_categories();
		$used = array();
		global $wpdb;
		foreach ( (array) $wpdb->get_results( 'SELECT category, COUNT(*) AS n FROM ' . flxb_table( 'types' ) . ' GROUP BY category', ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
			$used[ (string) $r['category'] ] = (int) $r['n'];
		}
		echo '<section class="flxb-card" style="max-width:760px"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'Donation type categories', 'flx-donate' ) . '</h2><p class="flxb-card__hint">' . esc_html__( 'Group donation types, e.g. Kurban, Ramazan, General. The key is stored on the types; a category that is in use cannot be removed.', 'flx-donate' ) . '</p></div></div>';
		echo '<form class="flxb-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="flxb_settings_save"><input type="hidden" name="tab" value="categories">';
		wp_nonce_field( 'flxb_settings_save' );
		echo '<div class="flxb-repeater" data-flxb-repeater><div class="flxb-optrows" data-rows>';
		$i = 0;
		foreach ( $cats as $k => $label ) {
			self::category_row( (string) $i++, (string) $k, (string) $label, $used[ $k ] ?? 0 );
		}
		echo '</div><template data-template>';
		self::category_row( '__i__', '', '', 0 );
		echo '</template><button type="button" class="flxb-btn flxb-btn--sm" data-add>' . FLXB_Admin::icon( 'plus', 14 ) . esc_html__( 'Add category', 'flx-donate' ) . '</button></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="flxb-formactions"><button type="submit" class="flxb-btn flxb-btn--primary">' . esc_html__( 'Save categories', 'flx-donate' ) . '</button></div></form></section>';
	}

	private static function category_row( $i, $key, $label, $used ) {
		$i = esc_attr( $i );
		echo '<div class="flxb-optrow flxb-catrow" data-row><label class="flxb-field"><span class="flxb-label">' . esc_html__( 'Name', 'flx-donate' ) . '</span><input type="text" name="cats[' . $i . '][label]" maxlength="80" value="' . esc_attr( $label ) . '"></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<label class="flxb-field"><span class="flxb-label">' . esc_html__( 'Key', 'flx-donate' ) . '</span><input type="text" name="cats[' . $i . '][key]" maxlength="40" value="' . esc_attr( $key ) . '"' . ( $used ? ' readonly title="' . esc_attr( sprintf( /* translators: %s: number of types */ _n( 'Used by %s type', 'Used by %s types', $used, 'flx-donate' ), number_format_i18n( $used ) ) ) . '"' : '' ) . '></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( $used ) {
			echo '<input type="hidden" name="cats[' . $i . '][used]" value="1"><span class="flxb-iconbtn is-off" aria-hidden="true"></span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo '<button type="button" class="flxb-iconbtn flxb-iconbtn--danger" data-remove aria-label="' . esc_attr__( 'Remove category', 'flx-donate' ) . '">×</button></div>';
		}
	}

	private static function kurban() {
		$labels = (array) get_option( 'flxb_track_labels', array() );
		$def    = FLXB_Track::default_labels();
		echo '<div class="flxb-grid flxb-grid--2-1"><section class="flxb-card"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'Tracking', 'flx-donate' ) . '</h2><p class="flxb-card__hint">' . esc_html__( 'The words on the tracking screens. A donation type can use its own words (Donation types › Tracking); empty fields use these, and empty ones here the built-in words.', 'flx-donate' ) . '</p></div></div>';
		echo '<form class="flxb-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="flxb_settings_save"><input type="hidden" name="tab" value="kurban">';
		wp_nonce_field( 'flxb_settings_save' );
		echo '<div class="flxb-field"><label for="flxb-early">' . esc_html__( 'Early days', 'flx-donate' ) . '</label><input id="flxb-early" type="number" min="0" max="30" step="1" name="early_days" style="width:90px" value="' . (int) flxb_track_early_days() . '"><p class="flxb-help">' . esc_html__( 'How many days before the target date an item may already be done. After the target day it is still allowed, but shown as late.', 'flx-donate' ) . '</p></div>';
		echo '<div class="flxb-fields-2">';
		foreach ( FLXB_Track::label_fields() as $k => $l ) {
			echo '<div class="flxb-field"><label for="flxb-lb-' . esc_attr( $k ) . '">' . esc_html( $l ) . '</label><input id="flxb-lb-' . esc_attr( $k ) . '" type="text" name="labels[' . esc_attr( $k ) . ']" maxlength="' . ( 'caption' === $k ? 300 : 80 ) . '" value="' . esc_attr( (string) ( $labels[ $k ] ?? '' ) ) . '" placeholder="' . esc_attr( $def[ $k ] ) . '"></div>';
		}
		echo '</div>';
		if ( flxb_kurban_enabled() ) {
			$cur = flxb_proxy_text();
			echo '<div class="flxb-field"><label for="flxb-proxy">' . esc_html__( 'Proxy (vekâlet) text', 'flx-donate' ) . '</label><textarea id="flxb-proxy" name="proxy_text" rows="8">' . esc_textarea( $cur ? (string) $cur['text'] : '' ) . '</textarea><p class="flxb-help">' . esc_html__( 'The text a donor accepts to authorise the organisation to give on their behalf. Every change is saved as a new version; each donation keeps the version that was accepted.', 'flx-donate' ) . '</p></div>';
		}
		echo '<div class="flxb-formactions"><button type="submit" class="flxb-btn flxb-btn--primary">' . esc_html__( 'Save settings', 'flx-donate' ) . '</button></div></form></section>';
		echo '<aside class="flxb-card"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'Partners', 'flx-donate' ) . '</h2></div><p>' . esc_html__( 'Who items are sent to, their agreed prices and payments are kept under Donations › Partners.', 'flx-donate' ) . '</p><p><a class="flxb-btn" href="' . esc_url( FLXB_Admin_Url::page( 'flxb-partners' ) ) . '">' . esc_html__( 'Partners', 'flx-donate' ) . '</a></p>';
		$all = get_option( 'flxb_proxy_texts', array() );
		if ( flxb_kurban_enabled() ) {
			echo '<h3 class="flxb-card__title" style="margin-top:16px">' . esc_html__( 'Proxy text versions', 'flx-donate' ) . '</h3>';
			if ( ! $all ) {
				echo '<p class="flxb-muted">' . esc_html__( 'No text yet. Donations of types that require a proxy cannot be paid until a text is saved.', 'flx-donate' ) . '</p>';
			} else {
				echo '<ul class="flxb-list">';
				foreach ( array_reverse( (array) $all ) as $v ) {
					/* translators: 1: version number, 2: date */
					echo '<li>' . esc_html( sprintf( __( 'Version %1$s · %2$s', 'flx-donate' ), number_format_i18n( (int) $v['version'] ), flxb_date( $v['created_at'] ) ) ) . '</li>';
				}
				echo '</ul>';
			}
		}
		echo '</aside></div>';
	}

	/** 2.11: extra detail tabs (JSON). */
	private static function xtabs() {
		$draft = get_transient( 'flxb_tabs_draft_' . get_current_user_id() );
		if ( false !== $draft ) {
			delete_transient( 'flxb_tabs_draft_' . get_current_user_id() );
		}
		$own  = FLXB_Detail_Tabs::custom();
		$json = false !== $draft ? $draft : ( $own ? wp_json_encode( $own, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : '' );
		$ex   = array(
			array(
				'id'         => 'delivery',
				'title'      => array( 'tr' => 'Teslim', 'en' => 'Delivery' ),
				'on'         => 'item',
				'type_slugs' => array(),
				'fields'     => array(
					array( 'key' => 'person', 'label' => array( 'tr' => 'Teslim alan', 'en' => 'Received by' ), 'type' => 'text' ),
					array( 'key' => 'date', 'label' => array( 'tr' => 'Teslim tarihi', 'en' => 'Date' ), 'type' => 'date' ),
					array( 'key' => 'state', 'label' => array( 'tr' => 'Durum', 'en' => 'State' ), 'type' => 'select', 'options' => array( 'ok' => array( 'tr' => 'Sağlam', 'en' => 'Fine' ), 'damaged' => array( 'tr' => 'Hasarlı', 'en' => 'Damaged' ) ) ),
				),
			),
		);
		echo '<div class="flxb-grid flxb-grid--2-1"><section class="flxb-card"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'Extra tabs', 'flx-donate' ) . '</h2><p class="flxb-card__hint">' . esc_html__( 'Add your own tabs with fields to tracked records ("on": "item") or donations ("on": "donation"), for all or some donation types. Leave it empty if you do not need any.', 'flx-donate' ) . '</p></div></div>';
		echo '<form class="flxb-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="flxb_settings_save"><input type="hidden" name="tab" value="tabs">';
		wp_nonce_field( 'flxb_settings_save' );
		echo '<div class="flxb-field"><label for="flxb-xj">' . esc_html__( 'Tabs (JSON list)', 'flx-donate' ) . '</label><textarea id="flxb-xj" name="json" rows="20" class="flxb-code" spellcheck="false" placeholder="[]">' . esc_textarea( (string) $json ) . '</textarea></div>';
		echo '<div class="flxb-actions"><button class="flxb-btn flxb-btn--primary" type="submit">' . esc_html__( 'Save', 'flx-donate' ) . '</button></div></form></section>';
		echo '<aside class="flxb-card"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'Tabs in use', 'flx-donate' ) . '</h2></div>';
		$all   = FLXB_Detail_Tabs::all();
		$built = FLXB_Detail_Tabs::builtin();
		if ( $all ) {
			echo '<ul class="flxb-list">';
			foreach ( $all as $id => $t ) {
				$where = 'donation' === $t['on'] ? __( 'Donation page', 'flx-donate' ) : __( 'Record page', 'flx-donate' );
				echo '<li><b>' . esc_html( FLXB_Reports::text( $t['title'] ) ) . '</b> <code>' . esc_html( $id ) . '</code> · ' . esc_html( $where ) . ' · ' . esc_html( sprintf( /* translators: %d: number of fields */ _n( '%d field', '%d fields', count( $t['fields'] ), 'flx-donate' ), count( $t['fields'] ) ) ) . ( isset( $built[ $id ] ) ? ' · <span class="flxb-muted">' . esc_html__( 'from a plugin', 'flx-donate' ) . '</span>' : '' ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</ul>';
		} else {
			echo '<p class="flxb-muted">' . esc_html__( 'No extra tabs yet.', 'flx-donate' ) . '</p>';
		}
		echo '<p class="flxb-help"><b>type</b> ' . esc_html( implode( ', ', FLXB_Detail_Tabs::field_types() ) ) . '</p>';
		echo '<details class="flxb-details"><summary>' . esc_html__( 'Example', 'flx-donate' ) . '</summary><pre class="flxb-code">' . esc_html( wp_json_encode( $ex, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) . '</pre></details></aside></div>';
	}

	private static function import() {
		$last = get_option( 'flxb_last_sync', array() );
		echo '<section class="flxb-card" style="max-width:760px"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'Import existing payments', 'flx-donate' ) . '</h2><p class="flxb-card__hint">' . esc_html__( 'Reads completed payments stored by Flux Forms and adds the missing ones as donations. Safe to run more than once: a payment is never imported twice.', 'flx-donate' ) . '</p></div></div>';
		if ( '' === FLXB_FLXForm_Bridge::pay_table() ) {
			echo '<div class="flxb-notice flxb-notice--warn">' . esc_html__( 'Flux Forms\'s payment table was not found. Is Flux Forms installed?', 'flx-donate' ) . '</div>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="flxb_backfill">';
		wp_nonce_field( 'flxb_backfill' );
		echo '<button type="submit" class="flxb-btn flxb-btn--primary" data-flxb-busy="' . esc_attr__( 'Importing…', 'flx-donate' ) . '">' . FLXB_Admin::icon( 'download' ) . esc_html__( 'Import now', 'flx-donate' ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( ! empty( $last['time'] ) ) {
			$r = (array) ( $last['result'] ?? array() );
			/* translators: 1: date, 2: checked, 3: imported */
			echo '<p class="flxb-muted" style="margin-top:12px">' . esc_html( sprintf( __( 'Last run %1$s: %2$s payments checked, %3$s imported.', 'flx-donate' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $last['time'] ), number_format_i18n( (int) ( $r['seen'] ?? 0 ) ), number_format_i18n( (int) ( $r['imported'] ?? 0 ) ) ) ) . '</p>';
		}
		echo '</section>';
	}

	public static function save() {
		if ( ! flxb_can_manage() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_settings_save' );
		$s   = flxb_settings();
		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'general';
		if ( 'organisation' === $tab ) {
			$color              = isset( $_POST['primary_color'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['primary_color'] ) ) ) : '';
			$s['org_name']      = isset( $_POST['org_name'] ) ? sanitize_text_field( wp_unslash( $_POST['org_name'] ) ) : '';
			$s['logo_id']       = isset( $_POST['logo_id'] ) ? absint( $_POST['logo_id'] ) : 0;
			$s['primary_color'] = preg_match( '/^#[0-9a-fA-F]{6}$/', $color ) ? strtoupper( $color ) : '';
			$s['receipt_text']  = isset( $_POST['receipt_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['receipt_text'] ) ) : '';
			$s['footer_text']   = isset( $_POST['footer_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['footer_text'] ) ) : '';
		} elseif ( 'categories' === $tab ) {
			$old  = flxb_categories();
			$cats = array();
			foreach ( (array) ( $_POST['cats'] ?? array() ) as $row ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$row   = is_array( $row ) ? wp_unslash( $row ) : array();
				$label = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
				$key   = sanitize_key( remove_accents( (string) ( $row['key'] ?? '' ) ) );
				if ( '' === $key ) {
					$key = sanitize_key( str_replace( ' ', '-', remove_accents( strtolower( $label ) ) ) );
				}
				if ( '' === $label && '' === $key ) {
					continue;
				}
				$cats[ '' !== $key ? $key : 'cat-' . ( count( $cats ) + 1 ) ] = '' !== $label ? $label : $key;
			}
			/* Categories in use are kept even if their row was removed. */
			global $wpdb;
			foreach ( (array) $wpdb->get_col( 'SELECT DISTINCT category FROM ' . flxb_table( 'types' ) . " WHERE category <> ''" ) as $k ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
				if ( ! isset( $cats[ $k ] ) ) {
					$cats[ $k ] = $old[ $k ] ?? $k;
				}
			}
			update_option( 'flxb_categories', $cats, false );
			FLXB_Admin::redirect( 'flxb-settings', array( 'tab' => 'categories', 'flxb_msg' => 'saved' ) );
		} elseif ( 'tabs' === $tab ) {
			$json = isset( $_POST['json'] ) ? (string) wp_unslash( $_POST['json'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON, checked by FLXB_Detail_Tabs
			$res  = FLXB_Detail_Tabs::save_json( $json );
			if ( is_wp_error( $res ) ) {
				set_transient( 'flxb_tabs_draft_' . get_current_user_id(), $json, 600 );
				FLXB_Admin::redirect( 'flxb-settings', array( 'tab' => 'tabs', 'flxb_msg' => 'error', 'flxb_err' => $res->get_error_message() ) );
			}
			FLXB_Admin::redirect( 'flxb-settings', array( 'tab' => 'tabs', 'flxb_msg' => 'saved' ) );
		} elseif ( 'kurban' === $tab ) {
			$text = isset( $_POST['proxy_text'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['proxy_text'] ) ) ) : '';
			$cur  = flxb_proxy_text();
			if ( '' !== $text && ( ! $cur || $cur['text'] !== $text ) ) {
				$all   = (array) get_option( 'flxb_proxy_texts', array() );
				$all[] = array( 'version' => $cur ? (int) $cur['version'] + 1 : 1, 'text' => $text, 'created_at' => flxb_now(), 'user' => get_current_user_id() );
				update_option( 'flxb_proxy_texts', array_values( $all ), false );
			}
			if ( isset( $_POST['early_days'] ) ) {
				update_option( 'flxb_track_early_days', max( 0, min( 30, absint( $_POST['early_days'] ) ) ), false );
			}
			if ( isset( $_POST['labels'] ) ) {
				update_option( 'flxb_track_labels', FLXB_Track::clean_labels( (array) wp_unslash( $_POST['labels'] ) ), false ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}
			FLXB_Admin::redirect( 'flxb-settings', array( 'tab' => 'kurban', 'flxb_msg' => 'saved' ) );
		} else {
			$cur                 = isset( $_POST['currency'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['currency'] ) ) ) : 'TRY';
			$s['currency']       = in_array( $cur, array( 'TRY', 'EUR', 'USD', 'GBP' ), true ) ? $cur : 'TRY';
			$s['donation_forms'] = isset( $_POST['donation_forms'] ) && 'list' === $_POST['donation_forms'] ? 'list' : 'payments';
			$s['form_ids']       = isset( $_POST['form_ids'] ) ? array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['form_ids'] ) ) ) ) : array();
			$s['module_kurban']  = ! empty( $_POST['module_kurban'] ) ? 1 : 0;
			$s['module_track']   = ! empty( $_POST['module_track'] ) ? 1 : 0;
			$s['module_reports'] = ! empty( $_POST['module_reports'] ) ? 1 : 0;
			$tab                 = 'general';
		}
		update_option( 'flxb_settings', $s, false );
		FLXB_Admin::redirect( 'flxb-settings', array( 'tab' => $tab, 'flxb_msg' => 'saved' ) );
	}

	public static function backfill() {
		if ( ! flxb_can_manage() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_backfill' );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		FLXB_FLXForm_Bridge::import( 0, 'backfill' );
		FLXB_Admin::redirect( 'flxb-settings', array( 'tab' => 'import', 'flxb_msg' => 'imported' ) );
	}
}
