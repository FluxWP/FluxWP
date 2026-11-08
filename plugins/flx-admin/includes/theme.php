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
		'light' => array( 'bg' => '#F3F2EF', 'side' => '#FFFFFF', 'surface' => '#FFFFFF', 'text' => '#16181D' ),
		'dark'  => array( 'bg' => '#101114', 'side' => '#14161A', 'surface' => '#191B20', 'text' => '#E8E8EA' ),
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
		'bg'      => __( 'Background', 'flx-admin' ),
		'side'    => __( 'Side menu', 'flx-admin' ),
		'surface' => __( 'Cards', 'flx-admin' ),
		'text'    => __( 'Text', 'flx-admin' ),
	);
	?>
	<div class="flxa-card">
		<h2><?php esc_html_e( 'Theme', 'flx-admin' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><?php esc_html_e( 'Default theme', 'flx-admin' ); ?></th>
				<td><fieldset>
					<?php foreach ( array( 'auto' => __( 'Automatic (device setting)', 'flx-admin' ), 'light' => __( 'Light', 'flx-admin' ), 'dark' => __( 'Dark', 'flx-admin' ) ) as $k => $l ) : ?>
						<label class="flxa-radio"><input type="radio" name="s[mode]" value="<?php echo esc_attr( $k ); ?>" <?php checked( $s['mode'], $k ); ?>> <?php echo esc_html( $l ); ?></label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Everyone can change their own preference with the moon/sun button at the bottom left.', 'flx-admin' ); ?></p>
				</fieldset></td></tr>
			<tr><th scope="row"><label for="flxa-accent"><?php esc_html_e( 'Accent color', 'flx-admin' ); ?></label></th>
				<td><input type="color" id="flxa-accent" name="s[accent]" value="<?php echo esc_attr( flxa_accent() ); ?>"> <code><?php echo esc_html( flxa_accent() ); ?></code></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Colors', 'flx-admin' ); ?></th>
				<td><table class="flxa-colors"><thead><tr><th></th><th><?php esc_html_e( 'Light', 'flx-admin' ); ?></th><th><?php esc_html_e( 'Dark', 'flx-admin' ); ?></th></tr></thead><tbody>
				<?php foreach ( $cols as $k => $l ) : ?>
					<tr><th scope="row"><?php echo esc_html( $l ); ?></th>
					<?php foreach ( array( 'light', 'dark' ) as $m ) : ?>
						<td><input type="color" name="s[theme][<?php echo esc_attr( $m ); ?>][<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $t[ $m ][ $k ] ? $t[ $m ][ $k ] : $cd[ $m ][ $k ] ); ?>" data-def="<?php echo esc_attr( $cd[ $m ][ $k ] ); ?>"></td>
					<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
				</tbody></table>
				<button type="button" class="button-link" onclick="document.querySelectorAll('.flxa-colors input[data-def]').forEach(function(i){i.value=i.dataset.def;})"><?php esc_html_e( 'Reset colors to default', 'flx-admin' ); ?></button></td></tr>
			<tr><th scope="row"><label for="flxa-font"><?php esc_html_e( 'Font', 'flx-admin' ); ?></label></th>
				<td><select id="flxa-font" name="s[theme][font]">
					<option value="figtree" <?php selected( $t['font'], 'figtree' ); ?>>Figtree</option>
					<option value="system" <?php selected( $t['font'], 'system' ); ?>><?php esc_html_e( 'System font', 'flx-admin' ); ?></option>
					<option value="custom" <?php selected( $t['font'], 'custom' ); ?>><?php esc_html_e( 'Custom…', 'flx-admin' ); ?></option>
				</select>
				<input type="text" class="regular-text" name="s[theme][font_custom]" value="<?php echo esc_attr( $t['font_custom'] ); ?>" placeholder="Inter, &quot;Open Sans&quot;">
				<p class="description"><?php esc_html_e( 'Custom: the name of a font installed on the computer or loaded on the site.', 'flx-admin' ); ?></p></td></tr>
			<tr><th scope="row"><label for="flxa-fs"><?php esc_html_e( 'Font size', 'flx-admin' ); ?></label></th>
				<td><select id="flxa-fs" name="s[theme][font_size]">
					<?php foreach ( array( 13 => __( 'Normal', 'flx-admin' ), 14 => __( 'Large', 'flx-admin' ), 15 => __( 'Larger', 'flx-admin' ) ) as $n => $l ) : ?>
						<option value="<?php echo (int) $n; ?>" <?php selected( (int) $t['font_size'], $n ); ?>><?php echo esc_html( $l . ' (' . $n . 'px)' ); ?></option>
					<?php endforeach; ?>
				</select></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Corners', 'flx-admin' ); ?></th>
				<td><fieldset class="flxa-radius-pick">
					<?php foreach ( array( 'none' => __( 'Sharp', 'flx-admin' ), 'small' => __( 'Slightly rounded', 'flx-admin' ), 'medium' => __( 'Rounded', 'flx-admin' ), 'large' => __( 'Very rounded', 'flx-admin' ) ) as $k => $l ) : ?>
						<label class="flxa-radio"><input type="radio" name="s[theme][radius]" value="<?php echo esc_attr( $k ); ?>" <?php checked( $t['radius'], $k ); ?>> <i class="flxa-rp flxa-rp-<?php echo esc_attr( $k ); ?>"></i> <?php echo esc_html( $l ); ?></label>
					<?php endforeach; ?>
				</fieldset></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Density', 'flx-admin' ); ?></th>
				<td><fieldset>
					<label class="flxa-radio"><input type="radio" name="s[theme][density]" value="comfortable" <?php checked( $t['density'], 'comfortable' ); ?>> <?php esc_html_e( 'Comfortable', 'flx-admin' ); ?></label>
					<label class="flxa-radio"><input type="radio" name="s[theme][density]" value="compact" <?php checked( $t['density'], 'compact' ); ?>> <?php esc_html_e( 'Compact (more rows in the menu)', 'flx-admin' ); ?></label>
				</fieldset></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Icons', 'flx-admin' ); ?></th>
				<td><label><input type="checkbox" name="s[line_icons]" value="1" <?php checked( $s['line_icons'] ); ?>> <?php esc_html_e( 'Line icons in WordPress menus (FluxWP logos stay in color)', 'flx-admin' ); ?></label></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Package logos', 'flx-admin' ); ?></th>
				<td><fieldset class="flxa-logo-styles"><style>.flxa-logo-styles .flxa-radio{display:inline-flex;align-items:center;gap:4px;margin-right:20px}.flxa-logo-styles img{border-radius:6px}</style>
					<?php foreach ( array( 'gradient' => __( 'Gradient (default)', 'flx-admin' ), 'pastel' => __( 'Pastel', 'flx-admin' ) ) as $k => $l ) : ?>
						<label class="flxa-radio"><input type="radio" name="s[logo_style]" value="<?php echo esc_attr( $k ); ?>" <?php checked( $s['logo_style'], $k ); ?>>
							<img src="<?php echo esc_url( FLXA_URL . 'assets/logos/' . ( 'pastel' === $k ? 'pastel/' : '' ) . 'flx-donate.svg' ); ?>" alt="" width="24" height="24"><img src="<?php echo esc_url( FLXA_URL . 'assets/logos/' . ( 'pastel' === $k ? 'pastel/' : '' ) . 'flx-chat.svg' ); ?>" alt="" width="24" height="24"><img src="<?php echo esc_url( FLXA_URL . 'assets/logos/' . ( 'pastel' === $k ? 'pastel/' : '' ) . 'flx-seo.svg' ); ?>" alt="" width="24" height="24">
							<?php echo esc_html( $l ); ?></label>
					<?php endforeach; ?>
				</fieldset>
				<p class="description"><?php esc_html_e( 'Used in the side menu, the FluxWP Panel package list and FluxWP App. Colours show the package group: platform, maintenance, communication, content, commerce.', 'flx-admin' ); ?></p></td></tr>
			<tr><th scope="row"><label for="flxa-dark-ex"><?php esc_html_e( 'Screens without dark theme', 'flx-admin' ); ?></label></th>
				<td><textarea id="flxa-dark-ex" name="s[dark_exclude]" rows="3" class="large-text code"><?php echo esc_textarea( $s['dark_exclude'] ); ?></textarea>
				<p class="description"><?php esc_html_e( 'One screen ID or body class per line (may end with *). Complex screens without their own dark theme stay light here.', 'flx-admin' ); ?></p></td></tr>
		</table>
	</div>
	<?php
}
