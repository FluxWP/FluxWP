<?php
/**
 * Flux AI › Bağlı sistemler › <eklenti>: SDK ile tanımlanan AI ayarları (flx_ai_settings / flx_ai_feature),
 * eklentiye özel model, aç/kapa ve eklentinin araçları tek ekranda.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** SDK ile ayar alanı tanımlamış mı? */
function flxai_system_has_settings( $slug ) {
	return function_exists( 'flx_ai_registry' ) && ( ! empty( flx_ai_registry()['settings'][ $slug ] ) || isset( flx_ai_registry()['plugins'][ $slug ] ) );
}

add_action(
	'admin_init',
	function () {
		if ( empty( $_POST['flxai_system_nonce'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'flxai_system', 'flxai_system_nonce' );
		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidationSanitization
		$slug = sanitize_key( $post['system'] ?? '' );
		if ( '' === $slug ) {
			return;
		}
		// Aç/kapa (Bağlı sistemler listesindeki anahtarla aynı ayar).
		$off = array_diff( (array) flxai_setting( 'sources_off', array() ), array( $slug ) );
		if ( empty( $post['ai_on'] ) ) {
			$off[] = $slug;
		}
		$models = (array) flxai_setting( 'source_models', array() );
		$m      = sanitize_text_field( (string) ( $post['source_model'] ?? '' ) );
		if ( '' === $m ) {
			unset( $models[ $slug ] );
		} else {
			$models[ $slug ] = $m;
		}
		flxai_update_settings(
			array(
				'sources_off'   => array_values( array_unique( $off ) ),
				'source_models' => $models,
			)
		);
		if ( function_exists( 'flx_ai_set' ) ) {
			$fields = flx_ai_registry()['settings'][ $slug ] ?? array();
			$vals   = (array) ( $post['f'] ?? array() );
			foreach ( $fields as $key => $f ) {
				if ( 'toggle' === $f['type'] ) {
					$vals[ $key ] = ! empty( $vals[ $key ] );
				}
			}
			flx_ai_set( $slug, $vals );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=flx-ai-systems&system=' . rawurlencode( $slug ) . '&saved=1' ) );
		exit;
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( false !== strpos( (string) $hook, 'flx-ai-systems' ) && ! empty( $_GET['system'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			flxai_enqueue_model_picker();
		}
	},
	20
);

function flxai_page_system_settings( $slug ) {
	$reg    = function_exists( 'flx_ai_registry' ) ? flx_ai_registry() : array(
		'plugins'  => array(),
		'settings' => array(),
	);
	$plugin = $reg['plugins'][ $slug ] ?? array();
	$fields = $reg['settings'][ $slug ] ?? array();
	$info   = flxai_source_info( $slug );
	$back   = admin_url( 'admin.php?page=flx-ai-systems' );

	flxai_page_head( $info['label'], '' !== (string) ( $plugin['description'] ?? '' ) ? $plugin['description'] : __( 'Bu eklentinin yapay zekâ ayarları. Değerler Flux AI\'da saklanır, eklenti kendisi okur.', 'flx-ai' ) );
	echo '<p><a href="' . esc_url( $back ) . '">← ' . esc_html__( 'Bağlı sistemler', 'flx-ai' ) . '</a></p>';
	if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Kaydedildi.', 'flx-ai' ) . '</p></div>';
	}

	$tools = array_filter( flxai_tools_rows(), fn( $r ) => $r['source'] === $slug && ! $r['dupe'] );
	$n_on  = count( array_filter( $tools, fn( $r ) => ! $r['off'] ) );

	echo '<form method="post" class="flxai-card flxai-sysform">';
	wp_nonce_field( 'flxai_system', 'flxai_system_nonce' );
	echo '<input type="hidden" name="system" value="' . esc_attr( $slug ) . '">';
	echo '<table class="form-table" role="presentation"><tbody>';

	echo '<tr><th scope="row">' . esc_html__( 'Yapay zekâ', 'flx-ai' ) . '</th><td><label class="flxai-check"><input type="checkbox" name="ai_on" value="1"' . checked( flxai_source_enabled( $slug ), true, false ) . '> ' . esc_html__( 'Bu eklenti yapay zekâ kullanabilir', 'flx-ai' ) . '</label><p class="description">' . esc_html__( 'Kapalıyken eklentinin model istekleri reddedilir; araçları ayrıca Araçlar ekranından kapatılır.', 'flx-ai' ) . '</p></td></tr>';

	$sm = (string) ( ( (array) flxai_setting( 'source_models', array() ) )[ $slug ] ?? '' );
	echo '<tr><th scope="row">' . esc_html__( 'Model', 'flx-ai' ) . '</th><td>';
	flxai_model_field( 'source_model', $sm, flxai_model_for( '' ) );
	echo '<p class="description">' . esc_html__( 'Boşsa varsayılan model kullanılır.', 'flx-ai' ) . '</p></td></tr>';

	foreach ( $fields as $key => $f ) {
		$name = 'f[' . $key . ']';
		$val  = function_exists( 'flx_ai_get' ) ? flx_ai_get( $slug, $key ) : $f['default'];
		$id   = 'flxai-f-' . $key;
		echo '<tr><th scope="row">' . ( 'toggle' === $f['type'] ? esc_html( $f['label'] ) : '<label for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . '</label>' ) . '</th><td>';
		switch ( $f['type'] ) {
			case 'toggle':
				printf( '<label class="flxai-check"><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s> %4$s</label>', esc_attr( $id ), esc_attr( $name ), checked( (bool) $val, true, false ), esc_html__( 'Açık', 'flx-ai' ) );
				break;
			case 'textarea':
				printf( '<textarea id="%1$s" name="%2$s" rows="4" class="large-text">%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( (string) $val ) );
				break;
			case 'number':
				printf( '<input type="number" id="%1$s" name="%2$s" value="%3$s" class="small-text"%4$s%5$s step="any">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $val ), isset( $f['min'] ) ? ' min="' . esc_attr( $f['min'] ) . '"' : '', isset( $f['max'] ) ? ' max="' . esc_attr( $f['max'] ) . '"' : '' );
				break;
			case 'select':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
				foreach ( (array) $f['options'] as $ov => $ol ) {
					printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $ov ), selected( (string) $val, (string) $ov, false ), esc_html( $ol ) );
				}
				echo '</select>';
				break;
			case 'model':
				flxai_model_field( $name, (string) $val, $sm ? $sm : flxai_model_for( '' ) );
				break;
			default:
				printf( '<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $val ) );
		}
		if ( '' !== (string) $f['help'] ) {
			echo '<p class="description">' . esc_html( $f['help'] ) . '</p>';
		}
		echo '</td></tr>';
	}

	echo '<tr><th scope="row">' . esc_html__( 'Araçlar', 'flx-ai' ) . '</th><td>';
	if ( $tools ) {
		/* translators: 1: open tools, 2: all tools */
		echo esc_html( sprintf( __( '%1$s / %2$s araç açık', 'flx-ai' ), number_format_i18n( $n_on ), number_format_i18n( count( $tools ) ) ) );
		echo ' · <a href="' . esc_url( admin_url( 'admin.php?page=flx-ai-tools#src=' . rawurlencode( $slug ) ) ) . '">' . esc_html__( 'Araçları yönet', 'flx-ai' ) . '</a>';
	} else {
		echo esc_html__( 'Bu eklentinin aracı yok.', 'flx-ai' );
	}
	echo '</td></tr>';
	echo '</tbody></table>';
	if ( ! $fields && ! $plugin ) {
		echo '<p class="flxai-note">' . esc_html__( 'Bu eklenti Flux AI SDK ile ayar tanımlamamış; yalnız aç/kapa ve model seçilebilir.', 'flx-ai' ) . '</p>';
	}
	if ( defined( 'FLX_AI_SDK_VERSION' ) ) {
		/* translators: %s: SDK version */
		echo '<p class="flxai-note">' . esc_html( sprintf( __( 'Flux AI SDK %s yüklü.', 'flx-ai' ), FLX_AI_SDK_VERSION ) ) . '</p>';
	}
	echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Kaydet', 'flx-ai' ) . '</button></p>';
	echo '</form></div>';
}
