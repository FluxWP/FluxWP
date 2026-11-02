<?php
/**
 * Certificate designs the donor picks on the donation form (2.0.7).
 *
 * The list comes from Flux Certificates (1.4.0+, flxs_donor_templates()): active templates marked
 * "the donor can pick this design". The chosen template and the donor's field values travel on the
 * cart line ('tpl', 'tpl_values') and are used when the certificate is made after payment.
 * No list, or nothing picked: the donation type's own template, as before. The price never changes.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

class FLXB_WC_Designs {

	/** Keys Flux Donate fills itself (name on the certificate, number of saplings). */
	const AUTO_KEYS = array( 'ad_soyad', 'fidan_sayisi' );

	/** 2.0.8: the sender on e-card designs; pre-filled with the donor's name, else the checkout name is used. */
	const SENDER_KEY = 'gonderen';

	public static function init() {
		add_filter( 'flxb_wc_line', array( __CLASS__, 'line' ), 10, 3 );
	}

	/** Designs the donor can pick, keyed by template id. */
	public static function all() {
		static $list = null;
		if ( null === $list ) {
			$list = array();
			if ( function_exists( 'flxs_donor_templates' ) ) {
				foreach ( (array) flxs_donor_templates() as $d ) {
					if ( ! empty( $d['id'] ) ) {
						$list[ (int) $d['id'] ] = $d;
					}
				}
			}
		}
		return $list;
	}

	public static function get( $id ) {
		$all = self::all();
		return $all[ (int) $id ] ?? null;
	}

	/** Fields the donor fills on the form (the automatic ones are left out). */
	public static function donor_fields( array $d ) {
		$out = array();
		foreach ( (array) ( $d['fields'] ?? array() ) as $f ) {
			if ( empty( $f['key'] ) || in_array( $f['key'], self::AUTO_KEYS, true ) ) {
				continue;
			}
			$out[] = $f;
		}
		return $out;
	}

	public static function options( array $f ) {
		$o = $f['options'] ?? array();
		$o = is_array( $o ) ? $o : preg_split( '/\r\n|\r|\n/', (string) $o );
		return array_values( array_filter( array_map( 'trim', $o ), 'strlen' ) );
	}

	/** Cart line: check the picked design and its values (filter flxb_wc_line). */
	public static function line( $line, $in, $t ) {
		if ( is_wp_error( $line ) || empty( $line['cert'] ) ) {
			return $line;
		}
		$id = absint( $in['tpl'] ?? 0 );
		if ( ! $id ) {
			return $line;
		}
		$d = self::get( $id );
		if ( ! $d ) {
			return new WP_Error( 'flxb_design', __( 'This certificate design cannot be chosen any more. Please pick another one.', 'flx-donate' ) );
		}
		$raw  = is_array( $in['tpl_values'] ?? null ) ? $in['tpl_values'] : array();
		$vals = array();
		foreach ( self::donor_fields( $d ) as $f ) {
			$k = (string) $f['key'];
			$v = isset( $raw[ $k ] ) && is_scalar( $raw[ $k ] ) ? (string) $raw[ $k ] : '';
			$v = 'textarea' === $f['type'] ? sanitize_textarea_field( $v ) : sanitize_text_field( $v );
			$v = trim( mb_substr( $v, 0, 'textarea' === $f['type'] ? 600 : 120 ) );
			if ( '' !== $v && 'number' === $f['type'] && ! is_numeric( $v ) ) {
				$v = '';
			}
			if ( '' !== $v && 'date' === $f['type'] && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ) {
				$v = '';
			}
			if ( '' !== $v && 'select' === $f['type'] && ! in_array( $v, self::options( $f ), true ) ) {
				$v = '';
			}
			if ( '' === $v && ! empty( $f['required'] ) && 'date' !== $f['type'] && self::SENDER_KEY !== $k ) {
				/* translators: %s: field label */
				return new WP_Error( 'flxb_design_field', sprintf( __( 'Please fill in "%s" for the certificate.', 'flx-donate' ), (string) $f['label'] ) );
			}
			if ( '' !== $v ) {
				$vals[ $k ] = $v;
			}
		}
		$line['tpl']        = $id;
		$line['tpl_name']   = (string) $d['name'];
		$line['tpl_values'] = $vals;
		return $line;
	}

	/** Values for the certificate: the donor's words, the sender falling back to the checkout name. */
	public static function values( array $line, $billing_name ) {
		$vals = is_array( $line['tpl_values'] ?? null ) ? $line['tpl_values'] : array();
		$d    = self::get( (int) ( $line['tpl'] ?? 0 ) );
		if ( $d && empty( $vals[ self::SENDER_KEY ] ) && '' !== trim( (string) $billing_name ) ) {
			foreach ( (array) $d['fields'] as $f ) {
				if ( self::SENDER_KEY === ( $f['key'] ?? '' ) ) {
					$vals[ self::SENDER_KEY ] = trim( (string) $billing_name );
				}
			}
		}
		return $vals;
	}

	/** Template for a paid line: the picked design while it is still offered, else the type's own. */
	public static function template_for( array $line, $type ) {
		$id = (int) ( $line['tpl'] ?? 0 );
		if ( $id && self::get( $id ) ) {
			return $id;
		}
		return FLXB_WC::template_id( $type );
	}

	/** Donation form section: design cards and the fields of the picked one. */
	public static function render( $type_tpl, array $old ) {
		$all = self::all();
		if ( ! $all ) {
			return '';
		}
		$picked = absint( $old['tpl'] ?? 0 );
		$picked = isset( $all[ $picked ] ) ? $picked : 0;
		$group  = (string) reset( $all )['group'];
		ob_start();
		?>
		<fieldset class="flxwb-fs flxwb-designs" data-flxwb-designs>
			<legend class="flxwb-legend"><?php echo esc_html( '' !== $group ? $group : __( 'Certificates', 'flx-donate' ) ); ?></legend>
			<p class="flxwb-field__help"><?php esc_html_e( 'Pick the design of your certificate. The donation amount does not change.', 'flx-donate' ); ?></p>
			<div class="flxwb-dgrid" role="radiogroup">
				<?php if ( ! isset( $all[ (int) $type_tpl ] ) ) : ?>
				<label class="flxwb-dcard2">
					<input type="radio" name="tpl" value="0" <?php checked( 0, $picked ); ?>>
					<span class="flxwb-dcard2__img flxwb-dcard2__img--std"><?php echo FLXB_WC::icon( 'sprout' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="flxwb-dcard2__name"><?php esc_html_e( 'Standard', 'flx-donate' ); ?></span>
				</label>
				<?php endif; ?>
				<?php foreach ( $all as $id => $d ) : ?>
				<label class="flxwb-dcard2">
					<input type="radio" name="tpl" value="<?php echo esc_attr( $id ); ?>" <?php checked( $picked ? $picked : ( isset( $all[ (int) $type_tpl ] ) ? (int) $type_tpl : -1 ), $id ); ?>>
					<span class="flxwb-dcard2__img">
						<?php if ( '' !== (string) $d['thumb'] ) : ?>
						<img src="<?php echo esc_url( $d['thumb'] ); ?>" alt="" loading="lazy" decoding="async">
						<?php else : ?>
							<?php echo FLXB_WC::icon( 'sprout' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php endif; ?>
					</span>
					<span class="flxwb-dcard2__name"><?php echo esc_html( $d['name'] ); ?></span>
				</label>
				<?php endforeach; ?>
			</div>
			<?php
			foreach ( $all as $id => $d ) :
				$fields = self::donor_fields( $d );
				if ( ! $fields ) {
					continue;
				}
				?>
			<div class="flxwb-dfields" data-flxwb-dfields="<?php echo esc_attr( $id ); ?>" hidden>
				<?php
				foreach ( $fields as $f ) :
					$name = 'tf[' . $id . '][' . $f['key'] . ']';
					$def  = self::SENDER_KEY === $f['key'] ? (string) FLXB_WC_Form::me()['name'] : '';
					$val  = (string) ( $old['tf'][ $id ][ $f['key'] ] ?? ( '' !== $def ? $def : ( $f['default'] ?? '' ) ) );
					$req  = ! empty( $f['required'] ) && 'date' !== $f['type'] && self::SENDER_KEY !== $f['key'];
					?>
				<label class="flxwb-field">
					<span class="flxwb-field__label"><?php echo esc_html( $f['label'] ); ?><?php echo $req ? ' *' : ''; ?></span>
					<?php if ( 'textarea' === $f['type'] ) : ?>
					<textarea name="<?php echo esc_attr( $name ); ?>" rows="3" maxlength="600" placeholder="<?php echo esc_attr( (string) ( $f['placeholder'] ?? '' ) ); ?>"<?php echo $req ? ' data-flxwb-req' : ''; ?>><?php echo esc_textarea( $val ); ?></textarea>
					<?php elseif ( 'select' === $f['type'] ) : ?>
					<select name="<?php echo esc_attr( $name ); ?>"<?php echo $req ? ' data-flxwb-req' : ''; ?>>
						<option value=""><?php esc_html_e( '— Choose —', 'flx-donate' ); ?></option>
						<?php foreach ( self::options( $f ) as $o ) : ?>
						<option value="<?php echo esc_attr( $o ); ?>" <?php selected( $val, $o ); ?>><?php echo esc_html( $o ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php else : ?>
					<input type="<?php echo esc_attr( in_array( $f['type'], array( 'date', 'number' ), true ) ? $f['type'] : 'text' ); ?>" name="<?php echo esc_attr( $name ); ?>" maxlength="120" value="<?php echo esc_attr( $val ); ?>" placeholder="<?php echo esc_attr( (string) ( $f['placeholder'] ?? '' ) ); ?>"<?php echo $req ? ' data-flxwb-req' : ''; ?>>
					<?php endif; ?>
				</label>
				<?php endforeach; ?>
			</div>
			<?php endforeach; ?>
		</fieldset>
		<?php
		return ob_get_clean();
	}
}
