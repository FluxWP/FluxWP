<?php
/**
 * Flux Donate: preselect an option and a quantity on a Flux Forms form linked to a donation type.
 *
 *  - URL: ?flxb_opt=<option key>&flxb_qty=<n> on the page that shows the form.
 *  - JS:  window.FLXB.preselect( { form: 12, option: 'hisse', qty: 2, beneficiaries: [ … ] } ) → Promise<boolean>
 *         (waits for the form when it is not on the page yet). Events on document: `flxb:ready`
 *         (detail.form) and `flxb:change` (detail: form, option, qty, total).
 *
 * Only the choice is filled in; the price is never taken from the URL or the browser — the server
 * prices every payment from the type again.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Preselect {

	private static $done = array();

	private static $css_done = false;

	/** The summary shown in place of a locked (preselected) choice. */
	private static function lock_css() {
		return '.flxb-locked{display:flex;flex-wrap:wrap;align-items:center;gap:6px 12px;margin:6px 0 12px;padding:12px 14px;border:1px solid var(--flxf-line,#DCE9E0);border-radius:var(--flxf-radius,12px);background:var(--flxf-soft,#F5FAF6);color:var(--flxf-ink,#14324A)}'
			. '.flxb-locked__text{flex:1 1 auto;min-width:0}.flxb-locked__text strong{font-weight:700}'
			. '.flxb-locked__change{appearance:none;border:0;background:none;padding:2px 0;color:var(--flxf-primary,#0E7A56);font:inherit;font-weight:700;text-decoration:underline;cursor:pointer}'
			. '.flxb-locked__change:focus-visible{outline:2px solid var(--flxf-primary,#0E7A56);outline-offset:2px;border-radius:4px}';
	}

	public static function init() {
		add_filter( 'do_shortcode_tag', array( __CLASS__, 'on_form' ), 10, 3 );
	}

	/** Data the browser needs to turn an option key into the form's choice element. */
	public static function form_data( $form_id ) {
		$t = FLXB_Types::for_form_type( (int) $form_id, '', false );
		if ( ! $t ) {
			return null;
		}
		$m = $t['form_map'][ (int) $form_id ] ?? ( $t['form_map'][ (string) (int) $form_id ] ?? null );
		if ( ! is_array( $m ) || empty( $m['opts'] ) ) {
			return null;
		}
		$opts   = array();
		$prices = array();
		$off    = array();
		foreach ( (array) $m['opts'] as $el => $x ) {
			if ( '' === (string) ( $x['opt'] ?? '' ) ) {
				continue; // not mapped: the form's own price
			}
			$o = FLXB_Types::option( $t, (string) $x['opt'] );
			if ( ! $o || ! FLXB_Types::option_on_sale( $o ) ) {
				$off[] = (string) $el; // inactive, price not announced or removed: the visitor cannot pick it
			}
			if ( ! $o ) {
				continue;
			}
			$opts[ $o['key'] ][] = array( 'el' => (string) $el, 'units' => max( 1, (int) $x['units'] ) );
			$prices[ $o['key'] ] = FLXB_Types::option_on_sale( $o ) ? (float) $o['price'] : null;
		}
		return array(
			'form'          => (int) $form_id,
			'type'          => (int) $t['id'],
			'slug'          => (string) $t['slug'],
			'currency'      => (string) $t['currency'],
			'opts'          => $opts,
			'prices'        => $prices,
			'qty'           => $t['allow_quantity'] ? (string) ( $m['qty'] ?? '' ) : '',
			'maxQty'        => (int) $t['max_quantity'],
			'beneficiaries' => (string) ( $m['beneficiaries'] ?? '' ),
			'off'           => $off,
			'lock'          => ! empty( $t['meta']['lock_preselect'] ),
		);
	}

	/** Hides the choices that cannot be paid before any script runs (Flux Forms marks each choice with data-formid / data-id). */
	private static function off_css( array $data ) {
		$fid = (int) $data['form'];
		$sel = array();
		foreach ( $data['off'] as $el ) {
			$el    = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $el );
			$sel[] = 'body .body_efb div.efb.form-check[data-formid="' . $fid . '"][data-id="' . $el . '"]';
			$sel[] = 'body .body_efb option[data-formid="' . $fid . '"][data-op="' . $el . '"]';
		}
		return $sel ? implode( ',', $sel ) . '{display:none!important}' : '';
	}

	/** Element ids of the mapped fields that change the server's answer (proxy names and consent). */
	private static function quote_fields( $form_id ) {
		$t = FLXB_Types::for_form_type( (int) $form_id, '', false );
		if ( ! $t || ! FLXB_Types::requires_proxy( $t ) ) {
			return array();
		}
		$m = (array) ( $t['form_map'][ (int) $form_id ] ?? ( $t['form_map'][ (string) (int) $form_id ] ?? array() ) );
		return array_values( array_filter( array( (string) ( $m['beneficiaries'] ?? '' ), (string) ( $m['consent'] ?? '' ) ), 'strlen' ) );
	}

	public static function on_form( $output, $tag, $attr ) {
		if ( 'EMS_Form_Builder' !== $tag || is_admin() ) {
			return $output;
		}
		$fid = isset( $attr['id'] ) ? absint( $attr['id'] ) : 0;
		if ( ! $fid || isset( self::$done[ $fid ] ) ) {
			return $output;
		}
		$data = self::form_data( $fid );
		if ( ! $data ) {
			return $output;
		}
		self::$done[ $fid ] = true;
		$f = FLXB_DIR . 'assets/preselect.js';
		if ( ! wp_script_is( 'flxb-preselect', 'registered' ) ) {
			wp_register_script( 'flxb-preselect', FLXB_URL . 'assets/preselect.js', array(), is_readable( $f ) ? (string) filemtime( $f ) : FLXB_VERSION, true );
		}
		$js = 'window.flxbForms=window.flxbForms||{};window.flxbForms[' . (int) $fid . ']=' . wp_json_encode( $data ) . ';';
		/* Fields the server price depends on besides the priced choices (Flux Forms re-quotes when they change). */
		$extra = self::quote_fields( $fid );
		if ( $extra ) {
			$js .= 'window.flxfPayQuoteFields=window.flxfPayQuoteFields||{};window.flxfPayQuoteFields[' . (int) $fid . ']=' . wp_json_encode( $extra ) . ';';
		}
		static $i18n = false;
		if ( ! $i18n ) {
			$i18n = true;
			$js  .= 'window.flxbI18n=' . wp_json_encode(
				array(
					'choice' => __( 'Your choice', 'flx-donate' ),
					'change' => __( 'Change', 'flx-donate' ),
				)
			) . ';';
		}
		wp_add_inline_script( 'flxb-preselect', $js, 'before' );
		wp_enqueue_script( 'flxb-preselect' );
		$css = self::off_css( $data );
		$css .= self::$css_done ? '' : self::lock_css();
		self::$css_done = true;
		return '' !== $css ? $output . '<style id="flxb-form-' . (int) $fid . '-css">' . $css . '</style>' : $output;
	}
}
