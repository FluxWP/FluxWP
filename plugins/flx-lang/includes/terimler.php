<?php
/**
 * Kategori, etiket ve diğer terimler dillerle ortaktır (aynı kategori her dilde). Adı ve açıklaması
 * dil başına çevrilir: _flxl_name_<dil>, _flxl_desc_<dil> term meta. Adres (slug) ortak kalır.
 *
 * @package flx-lang
 */

defined( 'ABSPATH' ) || exit;

function flxl_term_text( $term_id, $field, $code = '' ) {
	$code = $code ? $code : flxl_current_language();
	return (string) get_term_meta( (int) $term_id, '_flxl_' . $field . '_' . $code, true );
}

function flxl_set_term_translation( $term_id, $code, $name = null, $desc = null ) {
	if ( ! flxl_is_language( $code ) || $code === flxl_default_language() || ! get_term( (int) $term_id ) ) {
		return false;
	}
	foreach ( array( 'name' => $name, 'desc' => $desc ) as $f => $v ) {
		if ( null === $v ) {
			continue;
		}
		$v = 'name' === $f ? sanitize_text_field( $v ) : wp_kses_post( $v );
		if ( '' === $v ) {
			delete_term_meta( (int) $term_id, '_flxl_' . $f . '_' . $code );
		} else {
			update_term_meta( (int) $term_id, '_flxl_' . $f . '_' . $code, $v );
		}
	}
	clean_term_cache( (int) $term_id );
	return true;
}

/**
 * Ön yüzde terim adı/açıklaması istek dilinde.
 */
function flxl_filter_term( $term ) {
	if ( ! $term instanceof WP_Term || FLXL_TAX === $term->taxonomy || flxl_is_backend() || flxl_current_language() === flxl_default_language() ) {
		return $term;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST && ! flxl_is_explicit() ) {
		return $term;
	}
	$n = flxl_term_text( $term->term_id, 'name' );
	$d = flxl_term_text( $term->term_id, 'desc' );
	if ( '' !== $n || '' !== $d ) {
		$term = clone $term;
		if ( '' !== $n ) {
			$term->name = $n;
		}
		if ( '' !== $d ) {
			$term->description = $d;
		}
	}
	return $term;
}
add_filter( 'get_term', 'flxl_filter_term', 20 );
add_filter(
	'get_terms',
	function ( $terms ) {
		if ( is_array( $terms ) && ! flxl_is_backend() && flxl_current_language() !== flxl_default_language() ) {
			foreach ( $terms as $i => $t ) {
				if ( $t instanceof WP_Term ) {
					$terms[ $i ] = flxl_filter_term( $t );
				}
			}
		}
		return $terms;
	},
	20
);
add_filter(
	'get_the_terms',
	function ( $terms ) {
		if ( is_array( $terms ) && ! flxl_is_backend() && flxl_current_language() !== flxl_default_language() ) {
			foreach ( $terms as $i => $t ) {
				$terms[ $i ] = flxl_filter_term( $t );
			}
		}
		return $terms;
	},
	20
);

/**
 * Terim düzenleme ekranında dil alanları.
 */
add_action(
	'admin_init',
	function () {
		foreach ( get_taxonomies( array( 'public' => true ) ) as $tax ) {
			if ( FLXL_TAX === $tax || 'post_format' === $tax ) {
				continue;
			}
			add_action( $tax . '_edit_form_fields', 'flxl_term_fields', 20 );
			add_action( 'edited_' . $tax, 'flxl_term_save' );
		}
	}
);

function flxl_term_fields( $term ) {
	wp_nonce_field( 'flxl_term', 'flxl_term_nonce' );
	foreach ( flxl_languages() as $code => $l ) {
		if ( $code === flxl_default_language() ) {
			continue;
		}
		?>
		<tr class="form-field">
			<th scope="row"><label for="flxl-name-<?php echo esc_attr( $code ); ?>"><?php /* translators: %s: language name */ printf( esc_html__( 'Ad (%s)', 'flx-lang' ), esc_html( $l['name'] ) ); ?></label></th>
			<td><input id="flxl-name-<?php echo esc_attr( $code ); ?>" name="flxl_name[<?php echo esc_attr( $code ); ?>]" value="<?php echo esc_attr( flxl_term_text( $term->term_id, 'name', $code ) ); ?>" lang="<?php echo esc_attr( $code ); ?>"></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="flxl-desc-<?php echo esc_attr( $code ); ?>"><?php /* translators: %s: language name */ printf( esc_html__( 'Açıklama (%s)', 'flx-lang' ), esc_html( $l['name'] ) ); ?></label></th>
			<td><textarea id="flxl-desc-<?php echo esc_attr( $code ); ?>" name="flxl_desc[<?php echo esc_attr( $code ); ?>]" rows="3" lang="<?php echo esc_attr( $code ); ?>"><?php echo esc_textarea( flxl_term_text( $term->term_id, 'desc', $code ) ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Boş bırakılırsa Türkçe ad ve açıklama gösterilir.', 'flx-lang' ); ?></p></td>
		</tr>
		<?php
	}
}

function flxl_term_save( $term_id ) {
	if ( ! isset( $_POST['flxl_term_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['flxl_term_nonce'] ), 'flxl_term' ) || ! current_user_can( 'edit_term', $term_id ) ) {
		return;
	}
	$names = isset( $_POST['flxl_name'] ) ? (array) wp_unslash( $_POST['flxl_name'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$descs = isset( $_POST['flxl_desc'] ) ? (array) wp_unslash( $_POST['flxl_desc'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	foreach ( flxl_languages() as $code => $l ) {
		if ( isset( $names[ $code ] ) || isset( $descs[ $code ] ) ) {
			flxl_set_term_translation( $term_id, $code, (string) ( $names[ $code ] ?? '' ), (string) ( $descs[ $code ] ?? '' ) );
		}
	}
}
