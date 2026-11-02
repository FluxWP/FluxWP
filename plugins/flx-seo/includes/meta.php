<?php
/**
 * Yazı/sayfa SEO alanları (editör yan panelinde "SEO") ve kategori SEO alanları.
 *
 * @package flx-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function flxseo_meta_keys() {
	return array(
		'_flxseo_title'   => 'string',
		'_flxseo_desc'    => 'string',
		'_flxseo_focus'   => 'string',
		'_flxseo_noindex' => 'boolean',
		'_flxseo_image'   => 'string',
	);
}

add_action(
	'init',
	function () {
		foreach ( flxseo_post_types() as $type ) {
			add_post_type_support( $type, 'custom-fields' );
			foreach ( flxseo_meta_keys() as $key => $t ) {
				register_post_meta(
					$type,
					$key,
					array(
						'type'          => $t,
						'single'        => true,
						'show_in_rest'  => true,
						'default'       => 'boolean' === $t ? false : '',
						'auth_callback' => function ( $allowed, $meta_key, $post_id ) {
							return current_user_can( 'edit_post', $post_id );
						},
					)
				);
			}
		}
	},
	20
);

function flxseo_get( $post_id, $key ) {
	return get_post_meta( $post_id, '_flxseo_' . $key, true );
}

/**
 * Editör: "SEO" paneli (Google önizlemesi, karakter sayacı, noindex).
 */
add_action(
	'enqueue_block_editor_assets',
	function () {
		$screen = get_current_screen();
		if ( ! $screen || empty( $screen->post_type ) || ! in_array( $screen->post_type, flxseo_post_types(), true ) ) {
			return;
		}
		wp_enqueue_script( 'flxseo-editor', FLXSEO_URL . 'assets/js/editor.js', array( 'wp-plugins', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data' ), FLXSEO_VERSION, true );
		wp_localize_script(
			'flxseo-editor',
			'flxseoEditor',
			array(
				'sep'      => flxseo_opt( 'sep' ),
				'siteName' => flxseo_opt( 'alt_name' ),
				'home'     => home_url( '/' ),
			)
		);
	}
);

/**
 * Kategori ve etiketler: SEO başlığı ve açıklaması.
 */
function flxseo_term_taxonomies() {
	return array_values( array_diff( get_taxonomies( array( 'public' => true ) ), array( 'post_format' ) ) );
}

add_action(
	'admin_init',
	function () {
		foreach ( flxseo_term_taxonomies() as $tax ) {
			add_action( "{$tax}_edit_form_fields", 'flxseo_term_fields', 20 );
			add_action( "edited_{$tax}", 'flxseo_term_save' );
		}
	}
);

function flxseo_term_fields( $term ) {
	wp_nonce_field( 'flxseo_term', 'flxseo_term_nonce' );
	?>
<tr class="form-field"><th scope="row"><label for="flxseo-tt">SEO başlığı</label></th><td><input id="flxseo-tt" name="flxseo_title" value="<?php echo esc_attr( get_term_meta( $term->term_id, '_flxseo_title', true ) ); ?>" maxlength="70"></td></tr>
<tr class="form-field"><th scope="row"><label for="flxseo-td">SEO açıklaması</label></th><td><textarea id="flxseo-td" name="flxseo_desc" rows="3" maxlength="320"><?php echo esc_textarea( get_term_meta( $term->term_id, '_flxseo_desc', true ) ); ?></textarea><p class="description">Boşsa kategori açıklaması kullanılır.</p></td></tr>
	<?php
}

function flxseo_term_save( $term_id ) {
	if ( ! isset( $_POST['flxseo_term_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['flxseo_term_nonce'] ), 'flxseo_term' ) || ! current_user_can( 'edit_term', $term_id ) ) {
		return;
	}
	update_term_meta( $term_id, '_flxseo_title', sanitize_text_field( wp_unslash( $_POST['flxseo_title'] ?? '' ) ) );
	update_term_meta( $term_id, '_flxseo_desc', sanitize_textarea_field( wp_unslash( $_POST['flxseo_desc'] ?? '' ) ) );
}

/**
 * Yazı listesinde SEO durumu sütunu.
 */
add_action(
	'admin_init',
	function () {
		foreach ( flxseo_post_types() as $type ) {
			add_filter(
				"manage_{$type}_posts_columns",
				function ( $cols ) {
					$cols['flxseo'] = 'SEO';
					return $cols;
				}
			);
			add_action(
				"manage_{$type}_posts_custom_column",
				function ( $col, $post_id ) {
					if ( 'flxseo' !== $col ) {
						return;
					}
					$issues = flxseo_audit_post( $post_id );
					$bad    = count( array_filter( $issues, fn( $i ) => 'error' === $i['level'] ) );
					$warn   = count( $issues ) - $bad;
					$color  = $bad ? '#d63638' : ( $warn ? '#dba617' : '#00a32a' );
					$title  = $issues ? implode( "\n", wp_list_pluck( $issues, 'msg' ) ) : 'Sorun yok';
					printf( '<span title="%s" style="display:inline-block;width:10px;height:10px;border-radius:50%%;background:%s;margin-right:4px"></span>%s', esc_attr( $title ), esc_attr( $color ), $issues ? esc_html( count( $issues ) . ' öneri' ) : 'İyi' );
				},
				10,
				2
			);
		}
	}
);
