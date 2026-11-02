<?php
/**
 * Görünüm ayarları (Ayarlar › Yönetim teması › Görünüm): renkler (açık + koyu), yazı tipi, yazı boyutu,
 * köşe yuvarlaklığı, yoğunluk. Hepsi CSS değişkeni olarak <head>'e yazılır; admin.css bu değişkenleri kullanır.
 * Ayrıca hızlı geçiş: menü bağlantılarına önden yükleme (Speculation Rules) ve yumuşak sayfa geçişi.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_footer', 'flxa_fast_nav', 99 );

function flxa_theme_defaults() {
	return array(
		'radius'      => 'medium',  // none | small | medium | large
		'font'        => 'figtree', // figtree | system | custom
		'font_custom' => '',
		'font_size'   => 13,        // 13 | 14 | 15
		'density'     => 'comfortable', // comfortable | compact
		'light'       => array( 'bg' => '', 'side' => '', 'surface' => '', 'text' => '' ),
		'dark'        => array( 'bg' => '', 'side' => '', 'surface' => '', 'text' => '' ),
	);
}

/** Renk alanlarının varsayılanları (ayar ekranında gösterilir; boş = varsayılan). */
function flxa_theme_color_defaults() {
	return array(
		'light' => array( 'bg' => '#F3F5F1', 'side' => '#FFFFFF', 'surface' => '#FFFFFF', 'text' => '#14201A' ),
		'dark'  => array( 'bg' => '#0D1310', 'side' => '#111814', 'surface' => '#151D18', 'text' => '#E6EEE8' ),
	);
}

function flxa_theme() {
	$t = flxa_setting( 'theme' );
	$t = is_array( $t ) ? $t : array();
	$d = flxa_theme_defaults();
	$o = wp_parse_args( $t, $d );
	foreach ( array( 'light', 'dark' ) as $m ) {
		$o[ $m ] = wp_parse_args( is_array( $o[ $m ] ) ? $o[ $m ] : array(), $d[ $m ] );
	}
	return $o;
}

function flxa_theme_sanitize( array $in ) {
	$d = flxa_theme_defaults();
	$o = array(
		'radius'      => in_array( $in['radius'] ?? '', array( 'none', 'small', 'medium', 'large' ), true ) ? $in['radius'] : $d['radius'],
		'font'        => in_array( $in['font'] ?? '', array( 'figtree', 'system', 'custom' ), true ) ? $in['font'] : $d['font'],
		'font_custom' => trim( preg_replace( '/[^A-Za-z0-9 ,"\'\-]/', '', (string) ( $in['font_custom'] ?? '' ) ) ),
		'font_size'   => in_array( (int) ( $in['font_size'] ?? 0 ), array( 13, 14, 15 ), true ) ? (int) $in['font_size'] : $d['font_size'],
		'density'     => in_array( $in['density'] ?? '', array( 'comfortable', 'compact' ), true ) ? $in['density'] : $d['density'],
	);
	foreach ( array( 'light', 'dark' ) as $m ) {
		$o[ $m ] = array();
		foreach ( array_keys( $d[ $m ] ) as $k ) {
			$c            = sanitize_hex_color( (string) ( $in[ $m ][ $k ] ?? '' ) );
			$def          = flxa_theme_color_defaults()[ $m ][ $k ];
			$o[ $m ][ $k ] = $c && strtolower( $c ) !== strtolower( $def ) ? $c : '';
		}
	}
	$o['font_custom'] = mb_substr( $o['font_custom'], 0, 120 );
	return $o;
}

/** <head>'deki değişkenler. */
function flxa_tokens_css() {
	$t     = flxa_theme();
	$radii = array(
		'none'   => array( 4, 4, 3 ),
		'small'  => array( 8, 6, 5 ),
		'medium' => array( 14, 10, 8 ),
		'large'  => array( 20, 14, 10 ),
	);
	$r     = $radii[ $t['radius'] ] ?? $radii['medium'];
	$font  = '';
	if ( 'system' === $t['font'] ) {
		$font = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';
	} elseif ( 'custom' === $t['font'] && '' !== $t['font_custom'] ) {
		$font = $t['font_custom'] . ', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
	}
	$vars = array(
		'--flxa-radius'  => $r[0] . 'px',
		'--flxa-r'       => $r[1] . 'px',
		'--flxa-r-sm'    => $r[2] . 'px',
		'--flxa-fs'      => (int) $t['font_size'] . 'px',
		'--flxa-item-h'  => 'compact' === $t['density'] ? '32px' : '38px',
		'--flxa-item-py' => 'compact' === $t['density'] ? '5px' : '8px',
	);
	if ( '' !== $font ) {
		$vars['--flxa-font'] = $font;
	}
	$map = array(
		'bg'      => '--flxa-bg',
		'side'    => '--flxa-side',
		'surface' => '--flxa-surface',
		'text'    => '--flxa-text',
	);
	$css = 'html.flxa-html{';
	foreach ( $vars as $k => $v ) {
		$css .= $k . ':' . $v . ';';
	}
	foreach ( $map as $k => $var ) {
		if ( '' !== $t['light'][ $k ] ) {
			$css .= $var . ':' . $t['light'][ $k ] . ';';
		}
	}
	$css .= '}';
	$dark = '';
	foreach ( $map as $k => $var ) {
		if ( '' !== $t['dark'][ $k ] ) {
			$dark .= $var . ':' . $t['dark'][ $k ] . ';';
		}
	}
	if ( '' !== $dark ) {
		$css .= 'html.flxa-html.flxa-dark{' . $dark . '}';
	}
	return wp_strip_all_tags( $css );
}

/**
 * Hızlı geçiş: menüdeki bir bağlantıya basılınca (pointerdown) sayfa önden hazırlanır; Chrome/Edge'de
 * bırakınca anında açılır. "conservative" seviye: yalnız tıklanan bağlantı, Hostinger'a ek yük yok denecek kadar az.
 * İşlem bağlantıları (nonce, action, silme, çıkış) hariç.
 */
function flxa_fast_nav() {
	if ( ! flxa_shell_on() || ! flxa_setting( 'fast_nav' ) ) {
		return;
	}
	$admin = wp_parse_url( admin_url(), PHP_URL_PATH );
	$rules = array(
		'prerender' => array(
			array(
				'where'     => array(
					'and' => array(
						array( 'href_matches' => $admin . '*' ),
						array( 'not' => array( 'href_matches' => '*admin-ajax.php*' ) ),
						array( 'not' => array( 'href_matches' => '*admin-post.php*' ) ),
						array( 'not' => array( 'href_matches' => '*_wpnonce=*' ) ),
						array( 'not' => array( 'href_matches' => '*action=*' ) ),
						array( 'not' => array( 'href_matches' => '*logout*' ) ),
						array( 'not' => array( 'href_matches' => '*download*' ) ),
						array( 'not' => array( 'selector_matches' => '[target], [download], .submitdelete, .delete a, .trash a' ) ),
					),
				),
				'eagerness' => 'conservative',
			),
		),
	);
	echo '<script type="speculationrules">' . wp_json_encode( $rules ) . '</script>';
}

/** Ayarlar › Görünüm sekmesi. */
function flxa_settings_look( array $s ) {
	$t    = flxa_theme();
	$cd   = flxa_theme_color_defaults();
	$cols = array(
		'bg'      => __( 'Arka plan', 'flx-admin' ),
		'side'    => __( 'Sol menü', 'flx-admin' ),
		'surface' => __( 'Kartlar', 'flx-admin' ),
		'text'    => __( 'Yazı', 'flx-admin' ),
	);
	?>
	<div class="flxa-card">
		<h2><?php esc_html_e( 'Tema', 'flx-admin' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><?php esc_html_e( 'Varsayılan tema', 'flx-admin' ); ?></th>
				<td><fieldset>
					<?php foreach ( array( 'auto' => __( 'Otomatik (cihazın ayarı)', 'flx-admin' ), 'light' => __( 'Açık', 'flx-admin' ), 'dark' => __( 'Koyu', 'flx-admin' ) ) as $k => $l ) : ?>
						<label class="flxa-radio"><input type="radio" name="s[mode]" value="<?php echo esc_attr( $k ); ?>" <?php checked( $s['mode'], $k ); ?>> <?php echo esc_html( $l ); ?></label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Herkes kendi tercihini sol alttaki ay/güneş düğmesinden değiştirebilir.', 'flx-admin' ); ?></p>
				</fieldset></td></tr>
			<tr><th scope="row"><label for="flxa-accent"><?php esc_html_e( 'Vurgu rengi', 'flx-admin' ); ?></label></th>
				<td><input type="color" id="flxa-accent" name="s[accent]" value="<?php echo esc_attr( flxa_accent() ); ?>"> <code><?php echo esc_html( flxa_accent() ); ?></code></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Renkler', 'flx-admin' ); ?></th>
				<td><table class="flxa-colors"><thead><tr><th></th><th><?php esc_html_e( 'Açık', 'flx-admin' ); ?></th><th><?php esc_html_e( 'Koyu', 'flx-admin' ); ?></th></tr></thead><tbody>
				<?php foreach ( $cols as $k => $l ) : ?>
					<tr><th scope="row"><?php echo esc_html( $l ); ?></th>
					<?php foreach ( array( 'light', 'dark' ) as $m ) : ?>
						<td><input type="color" name="s[theme][<?php echo esc_attr( $m ); ?>][<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $t[ $m ][ $k ] ? $t[ $m ][ $k ] : $cd[ $m ][ $k ] ); ?>" data-def="<?php echo esc_attr( $cd[ $m ][ $k ] ); ?>"></td>
					<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
				</tbody></table>
				<button type="button" class="button-link" onclick="document.querySelectorAll('.flxa-colors input[data-def]').forEach(function(i){i.value=i.dataset.def;})"><?php esc_html_e( 'Renkleri varsayılana döndür', 'flx-admin' ); ?></button></td></tr>
			<tr><th scope="row"><label for="flxa-font"><?php esc_html_e( 'Yazı tipi', 'flx-admin' ); ?></label></th>
				<td><select id="flxa-font" name="s[theme][font]">
					<option value="figtree" <?php selected( $t['font'], 'figtree' ); ?>>Figtree</option>
					<option value="system" <?php selected( $t['font'], 'system' ); ?>><?php esc_html_e( 'Sistem yazı tipi', 'flx-admin' ); ?></option>
					<option value="custom" <?php selected( $t['font'], 'custom' ); ?>><?php esc_html_e( 'Özel…', 'flx-admin' ); ?></option>
				</select>
				<input type="text" class="regular-text" name="s[theme][font_custom]" value="<?php echo esc_attr( $t['font_custom'] ); ?>" placeholder="Inter, &quot;Open Sans&quot;">
				<p class="description"><?php esc_html_e( 'Özel: bilgisayarda kurulu ya da sitede yüklü bir yazı tipinin adı.', 'flx-admin' ); ?></p></td></tr>
			<tr><th scope="row"><label for="flxa-fs"><?php esc_html_e( 'Yazı boyutu', 'flx-admin' ); ?></label></th>
				<td><select id="flxa-fs" name="s[theme][font_size]">
					<?php foreach ( array( 13 => __( 'Normal', 'flx-admin' ), 14 => __( 'Büyük', 'flx-admin' ), 15 => __( 'Daha büyük', 'flx-admin' ) ) as $n => $l ) : ?>
						<option value="<?php echo (int) $n; ?>" <?php selected( (int) $t['font_size'], $n ); ?>><?php echo esc_html( $l . ' (' . $n . 'px)' ); ?></option>
					<?php endforeach; ?>
				</select></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Köşeler', 'flx-admin' ); ?></th>
				<td><fieldset class="flxa-radius-pick">
					<?php foreach ( array( 'none' => __( 'Keskin', 'flx-admin' ), 'small' => __( 'Az yuvarlak', 'flx-admin' ), 'medium' => __( 'Yuvarlak', 'flx-admin' ), 'large' => __( 'Çok yuvarlak', 'flx-admin' ) ) as $k => $l ) : ?>
						<label class="flxa-radio"><input type="radio" name="s[theme][radius]" value="<?php echo esc_attr( $k ); ?>" <?php checked( $t['radius'], $k ); ?>> <i class="flxa-rp flxa-rp-<?php echo esc_attr( $k ); ?>"></i> <?php echo esc_html( $l ); ?></label>
					<?php endforeach; ?>
				</fieldset></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Yoğunluk', 'flx-admin' ); ?></th>
				<td><fieldset>
					<label class="flxa-radio"><input type="radio" name="s[theme][density]" value="comfortable" <?php checked( $t['density'], 'comfortable' ); ?>> <?php esc_html_e( 'Rahat', 'flx-admin' ); ?></label>
					<label class="flxa-radio"><input type="radio" name="s[theme][density]" value="compact" <?php checked( $t['density'], 'compact' ); ?>> <?php esc_html_e( 'Sıkı (menüde daha çok satır)', 'flx-admin' ); ?></label>
				</fieldset></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Simgeler', 'flx-admin' ); ?></th>
				<td><label><input type="checkbox" name="s[line_icons]" value="1" <?php checked( $s['line_icons'] ); ?>> <?php esc_html_e( 'WordPress menülerinde çizgi simgeler (FluxWP logoları renkli kalır)', 'flx-admin' ); ?></label></td></tr>
			<tr><th scope="row"><label for="flxa-dark-ex"><?php esc_html_e( 'Koyu tema kapalı ekranlar', 'flx-admin' ); ?></label></th>
				<td><textarea id="flxa-dark-ex" name="s[dark_exclude]" rows="3" class="large-text code"><?php echo esc_textarea( $s['dark_exclude'] ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Satır başına bir ekran kimliği veya gövde sınıfı (sonunda * olabilir). Kendi koyu teması olmayan karmaşık ekranlar burada açık kalır.', 'flx-admin' ); ?></p></td></tr>
		</table>
	</div>
	<?php
}
