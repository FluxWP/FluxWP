<?php
/**
 * Flux Donate: donation types and campaigns. The list and the editor are the React screen
 * (FLXB_Admin_Types_Service); the form mapping and the translations card are printed here as islands
 * of the editor.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Page_Types {

	public static function render() {
		if ( ! flxb_can_view() ) {
			FLXB_Admin::deny();
		}
		FLXB_Admin::screen(); // 2.40.0 / 2.41.0: the list and the editor are the React screen (packages/modules/src/donate).
	}

	/** 2.15: currencies on offer other than the type's own, plus any that already have a price. */
	public static function extra_currencies( array $t ) {
		$codes = class_exists( 'FLXB_WC_Currency' ) && class_exists( 'FLXB_WC' ) && FLXB_WC::ready() ? FLXB_WC_Currency::codes() : array();
		foreach ( (array) ( $t['options'] ?? array() ) as $o ) {
			$codes = array_merge( $codes, array_keys( (array) ( $o['prices'] ?? array() ) ) );
		}
		$codes = array_merge( $codes, array_keys( (array) FLXB_Types::meta( $t, 'min_by_currency', array() ) ) );
		return array_values( array_diff( array_unique( $codes ), array( strtoupper( (string) ( $t['currency'] ?? '' ) ) ) ) );
	}

	/** 2.15: title, description and option names in the other Flux Lang languages. */
	public static function i18n_card( array $t ) {
		if ( ! function_exists( 'flxl_languages' ) || ! function_exists( 'flxl_default_language' ) ) {
			return;
		}
		$def   = (string) flxl_default_language();
		$langs = array_diff_key( (array) flxl_languages(), array( $def => 1 ) );
		if ( ! $langs ) {
			return;
		}
		$all = (array) FLXB_Types::meta( $t, 'i18n', array() );
		echo '<section class="flxb-card"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'Translations', 'flx-donate' ) . '</h2><p class="flxb-card__hint">' . esc_html__( 'Shown on the donation page, the cart and the checkout in that language (Flux Lang). Empty fields use the texts above. Records keep the texts above. Save once after adding options to translate their names.', 'flx-donate' ) . '</p></div></div><div class="flxb-form">';
		foreach ( $langs as $code => $l ) {
			$tr  = (array) ( $all[ $code ] ?? array() );
			$pre = 'meta[i18n][' . esc_attr( $code ) . ']';
			$id  = 'flxb-i18n-' . esc_attr( $code );
			echo '<fieldset class="flxb-field flxb-i18n"><legend class="flxb-label">' . esc_html( trim( ( $l['flag'] ?? '' ) . ' ' . ( $l['name'] ?? $code ) ) ) . '</legend>';
			echo '<div class="flxb-field"><label for="' . $id . '-title">' . esc_html__( 'Title', 'flx-donate' ) . '</label><input type="text" id="' . $id . '-title" name="' . $pre . '[title]" maxlength="191" value="' . esc_attr( (string) ( $tr['title'] ?? '' ) ) . '" placeholder="' . esc_attr( $t['title'] ) . '"></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<div class="flxb-field"><label for="' . $id . '-desc">' . esc_html__( 'Short description', 'flx-donate' ) . '</label><textarea id="' . $id . '-desc" name="' . $pre . '[description]" rows="2" placeholder="' . esc_attr( (string) $t['description'] ) . '">' . esc_textarea( (string) ( $tr['description'] ?? '' ) ) . '</textarea></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( '' !== (string) ( $t['meta']['subtitle'] ?? '' ) ) {
				echo '<div class="flxb-field"><label for="' . $id . '-sub">' . esc_html__( 'Card subtitle', 'flx-donate' ) . '</label><input type="text" id="' . $id . '-sub" name="' . $pre . '[subtitle]" maxlength="80" value="' . esc_attr( (string) ( $tr['subtitle'] ?? '' ) ) . '" placeholder="' . esc_attr( (string) $t['meta']['subtitle'] ) . '"></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $id, $pre escaped above
			}
			if ( $t['options'] ) {
				echo '<div class="flxb-fields-2">';
				foreach ( $t['options'] as $o ) {
					if ( '' === (string) $o['key'] ) {
						continue;
					}
					/* translators: %s: option name in the default language */
					echo '<div class="flxb-field"><label for="' . $id . '-o-' . esc_attr( $o['key'] ) . '">' . esc_html( sprintf( __( 'Option: %s', 'flx-donate' ), $o['label'] ) ) . '</label><input type="text" id="' . $id . '-o-' . esc_attr( $o['key'] ) . '" name="' . $pre . '[options][' . esc_attr( $o['key'] ) . ']" maxlength="120" value="' . esc_attr( (string) ( $tr['options'][ $o['key'] ] ?? '' ) ) . '" placeholder="' . esc_attr( $o['label'] ) . '"></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				echo '</div>';
			}
			echo '</fieldset>';
		}
		echo '</div></section>';
	}

	/** The mapping of a linked form's choices (an island of the editor, classic field names form_map[…]). */
	public static function mapping( array $t, $fid ) {
		$ch  = FLXB_Types::form_choices( $fid );
		$map = $t['form_map'][ $fid ] ?? ( $t['form_map'][ (string) $fid ] ?? array() );
		echo '<fieldset class="flxb-map"><legend class="flxb-label">' . esc_html( sprintf( /* translators: %s: form title */ __( 'Mapping for “%s”', 'flx-donate' ), flxb_form_title( $fid ) ) ) . '</legend>';
		$fs  = FLXB_FLXForm_Bridge::form_structure( $fid );
		$fc  = strtoupper( (string) ( $fs[0]['currency'] ?? 'USD' ) );
		if ( $fc !== strtoupper( (string) $t['currency'] ) ) {
			/* translators: 1: form currency, 2: type currency */
			echo '<div class="flxb-notice flxb-notice--warn">' . esc_html( sprintf( __( 'This form charges in %1$s but the donation type is in %2$s: its payments are stopped until both use the same currency.', 'flx-donate' ), $fc, $t['currency'] ) ) . '</div>';
		}
		if ( ! $ch['options'] && ! $ch['prices'] ) {
			echo '<p class="flxb-muted">' . esc_html__( 'This form has no priced choices to map.', 'flx-donate' ) . '</p></fieldset>';
			return;
		}
		if ( $ch['options'] ) {
			echo '<div class="flxb-tablewrap"><table class="flxb-table flxb-table--compact"><thead><tr><th scope="col">' . esc_html__( 'Form choice', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Option', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Units', 'flx-donate' ) . '</th></tr></thead><tbody>';
			foreach ( $ch['options'] as $el => $c ) {
				$cur = $map['opts'][ $el ] ?? array( 'opt' => '', 'units' => 1 );
				echo '<tr><td class="flxb-col-main">' . esc_html( $c['label'] ) . '<span class="flxb-sub2">' . esc_html( sprintf( /* translators: %s: price set in the form */ __( 'form price: %s', 'flx-donate' ), flxb_money( $c['price'], $t['currency'] ) ) ) . '</span></td>';
				echo '<td data-label="' . esc_attr__( 'Option', 'flx-donate' ) . '"><select name="form_map[' . (int) $fid . '][opts][' . esc_attr( $el ) . '][opt]"><option value="">' . esc_html__( 'Not mapped (form price)', 'flx-donate' ) . '</option>';
				foreach ( $t['options'] as $o ) {
					echo '<option value="' . esc_attr( $o['key'] ) . '"' . selected( $cur['opt'], $o['key'], false ) . '>' . esc_html( $o['label'] . ' — ' . flxb_money( $o['price'], $t['currency'] ) ) . '</option>';
				}
				echo '</select></td><td data-label="' . esc_attr__( 'Units', 'flx-donate' ) . '"><input type="number" min="1" step="1" class="flxb-input" style="width:84px" name="form_map[' . (int) $fid . '][opts][' . esc_attr( $el ) . '][units]" value="' . (int) $cur['units'] . '"></td></tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '<div class="flxb-fields-2">';
		echo '<div class="flxb-field"><label>' . esc_html__( 'Quantity field', 'flx-donate' ) . '<select name="form_map[' . (int) $fid . '][qty]"><option value="">' . esc_html__( 'None', 'flx-donate' ) . '</option>';
		foreach ( $ch['numbers'] as $el => $label ) {
			echo '<option value="' . esc_attr( $el ) . '"' . selected( $map['qty'] ?? '', $el, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label></div>';
		echo '<div class="flxb-field"><label>' . esc_html__( 'Free amount field', 'flx-donate' ) . '<select name="form_map[' . (int) $fid . '][free]"><option value="">' . esc_html__( 'None', 'flx-donate' ) . '</option>';
		foreach ( $ch['prices'] as $el => $label ) {
			echo '<option value="' . esc_attr( $el ) . '"' . selected( $map['free'] ?? '', $el, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label></div></div>';
		if ( flxb_kurban_enabled() ) {
			echo '<div class="flxb-fields-2">';
			echo '<div class="flxb-field"><label>' . esc_html__( 'On behalf of (names field)', 'flx-donate' ) . '<select name="form_map[' . (int) $fid . '][beneficiaries]"><option value="">' . esc_html__( 'None', 'flx-donate' ) . '</option>';
			foreach ( $ch['texts'] as $el => $label ) {
				echo '<option value="' . esc_attr( $el ) . '"' . selected( $map['beneficiaries'] ?? '', $el, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select></label><p class="flxb-help">' . esc_html__( 'One name per line. Units without a name are given on behalf of the donor.', 'flx-donate' ) . '</p></div>';
			echo '<div class="flxb-field"><label>' . esc_html__( 'Proxy consent field', 'flx-donate' ) . '<select name="form_map[' . (int) $fid . '][consent]"><option value="">' . esc_html__( 'None', 'flx-donate' ) . '</option>';
			foreach ( $ch['checks'] as $el => $label ) {
				echo '<option value="' . esc_attr( $el ) . '"' . selected( $map['consent'] ?? '', $el, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select></label></div></div>';
		}
		echo '</fieldset>';
	}
}
