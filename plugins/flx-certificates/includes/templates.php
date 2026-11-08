<?php
defined( 'ABSPATH' ) || exit;

/** Eklentiyle gelen fontlar (Türkçe karakter destekli TTF). */
function flxs_builtin_fonts() {
	return array(
		'nunito'              => array( 'Nunito', 'Nunito-Regular.ttf' ),
		'nunito-italic'       => array( 'Nunito İtalik', 'Nunito-Italic.ttf' ),
		'nunito-bold'         => array( 'Nunito Kalın', 'Nunito-Bold.ttf' ),
		'nunito-extrabold'    => array( 'Nunito Ekstra Kalın', 'Nunito-ExtraBold.ttf' ),
		'montserrat'          => array( 'Montserrat', 'Montserrat-Regular.ttf' ),
		'montserrat-semibold' => array( 'Montserrat Yarı Kalın', 'Montserrat-SemiBold.ttf' ),
		'montserrat-bold'     => array( 'Montserrat Kalın', 'Montserrat-Bold.ttf' ),
		'playfair'            => array( 'Playfair Display', 'PlayfairDisplay-Regular.ttf' ),
		'playfair-italic'     => array( 'Playfair Display İtalik', 'PlayfairDisplay-Italic.ttf' ),
		'playfair-bold'       => array( 'Playfair Display Kalın', 'PlayfairDisplay-Bold.ttf' ),
		'cinzel'              => array( 'Cinzel', 'Cinzel-Regular.ttf' ),
		'cinzel-bold'         => array( 'Cinzel Kalın', 'Cinzel-Bold.ttf' ),
		'garamond'            => array( 'EB Garamond', 'EBGaramond-Regular.ttf', false ),
		'garamond-italic'     => array( 'EB Garamond İtalik', 'EBGaramond-Italic.ttf', false ),
		'greatvibes'          => array( 'Great Vibes (el yazısı)', 'GreatVibes-Regular.ttf', false ),
		'dancing'             => array( 'Dancing Script (el yazısı)', 'DancingScript-Bold.ttf' ),
	);
}

/**
 * Tüm fontlar: id => [label, url, subset]. Panelden yüklenen TTF/OTF'ler de dahil.
 * subset=false: pdf-lib bu fontları alt kümeye çevirirken bozuyor, tam gömülür.
 */
function flxs_fonts() {
	$out = array();
	foreach ( flxs_builtin_fonts() as $id => $f ) {
		$out[ $id ] = array( 'label' => $f[0], 'url' => FLXS_URL . 'assets/fonts/' . $f[1], 'subset' => $f[2] ?? true );
	}
	foreach ( (array) get_option( 'flxs_custom_fonts', array() ) as $f ) {
		$url = wp_get_attachment_url( (int) $f['attachment_id'] );
		if ( $url ) {
			$out[ 'custom-' . (int) $f['attachment_id'] ] = array( 'label' => $f['label'] . ' (yüklenen)', 'url' => $url, 'subset' => false );
		}
	}
	return $out;
}

function flxs_field_types() {
	return array(
		'text'     => 'Metin',
		'textarea' => 'Çok satırlı metin',
		'date'     => 'Tarih',
		'number'   => 'Sayı',
		'select'   => 'Seçim listesi',
		'static'   => 'Sabit metin (yer tutuculu)',
		'number_auto' => 'Sertifika no (otomatik)',
		'qr'       => 'QR kod (sertifika linki)',
	);
}

function flxs_get_template( $id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxs_table( 'templates' ) . ' WHERE id = %d', $id ), ARRAY_A );
	if ( $row ) {
		$row['config'] = flxs_normalize_template_config( flxs_decode( $row['config'] ) );
	}
	return $row;
}

function flxs_list_templates( $only_active = false ) {
	global $wpdb;
	$where = $only_active ? "WHERE status = 'active'" : '';
	$rows  = $wpdb->get_results( 'SELECT * FROM ' . flxs_table( 'templates' ) . " $where ORDER BY sort ASC, id ASC", ARRAY_A );
	foreach ( $rows as &$r ) {
		$r['config'] = flxs_normalize_template_config( flxs_decode( $r['config'] ) );
	}
	return $rows;
}

function flxs_normalize_template_config( $c ) {
	$c = wp_parse_args(
		is_array( $c ) ? $c : array(),
		array(
			'title'       => 'Sertifika',
			'page'        => array( 'w' => 841.89, 'h' => 595.28 ),
			'background'  => array( 'type' => 'none', 'source' => '', 'file' => '', 'id' => 0, 'color' => '#ffffff' ),
			'fields'      => array(),
			'share'       => array(),
			'messages'    => array(),
			'date_format' => 'd.m.Y',
		)
	);
	$c['share']    = wp_parse_args( (array) $c['share'], array( 'og_title' => '{ad_soyad} | {sertifika}', 'og_description' => '{site} tarafından {tarih} tarihinde düzenlendi.' ) );
	$c['messages'] = wp_parse_args( (array) $c['messages'], array( 'sms' => '', 'whatsapp' => '', 'email_subject' => '', 'email_body' => '' ) );
	$c['background'] = wp_parse_args( (array) $c['background'], array( 'type' => 'none', 'source' => '', 'file' => '', 'id' => 0, 'color' => '#ffffff' ) );
	$d          = wp_parse_args( (array) ( $c['donor'] ?? array() ), array( 'selectable' => false, 'group' => '', 'sort' => 0 ) );
	$c['donor'] = array(
		'selectable' => ! empty( $d['selectable'] ),
		'group'      => sanitize_text_field( $d['group'] ),
		'sort'       => (int) $d['sort'],
	);
	$c['background']['url'] = flxs_background_url( $c['background'] );
	$fields = array();
	foreach ( (array) $c['fields'] as $f ) {
		$fields[] = flxs_sanitize_field( $f );
	}
	$c['fields'] = $fields;
	return $c;
}

/**
 * Hazır arka plan klasörleri (yol => adres). Kuruma özel tasarımlar (sertifika arka planları, e-kartlar)
 * ön ayar eklentisinden gelir: add_filter( 'flxs_bundled_dirs', fn( $d ) => $d + array( $yol => $adres ) ).
 */
function flxs_bundled_dirs() {
	return (array) apply_filters( 'flxs_bundled_dirs', array( FLXS_DIR . 'assets/backgrounds/' => FLXS_URL . 'assets/backgrounds/' ) );
}

/** Hazır dosyanın yolu ($sub: 'raster/'); yoksa ''. */
function flxs_bundled_path( $file, $sub = '' ) {
	$name = $sub . basename( (string) $file );
	foreach ( flxs_bundled_dirs() as $dir => $url ) {
		if ( '' !== $name && file_exists( trailingslashit( $dir ) . $name ) ) {
			return trailingslashit( $dir ) . $name;
		}
	}
	return '';
}

/** Hazır dosyanın adresi; yoksa ''. */
function flxs_bundled_url( $file ) {
	$name = basename( (string) $file );
	foreach ( flxs_bundled_dirs() as $dir => $url ) {
		if ( '' !== $name && file_exists( trailingslashit( $dir ) . $name ) ) {
			return trailingslashit( $url ) . $name;
		}
	}
	return '';
}

function flxs_background_url( $bg ) {
	if ( 'bundled' === ( $bg['source'] ?? '' ) && ! empty( $bg['file'] ) ) {
		return flxs_bundled_url( $bg['file'] );
	}
	if ( 'attachment' === ( $bg['source'] ?? '' ) && ! empty( $bg['id'] ) ) {
		$u = wp_get_attachment_url( (int) $bg['id'] );
		return $u ? $u : '';
	}
	return '';
}

function flxs_sanitize_field( $f ) {
	$f     = is_array( $f ) ? $f : array();
	$types = flxs_field_types();
	$type  = isset( $types[ $f['type'] ?? '' ] ) ? $f['type'] : 'text';
	$key   = sanitize_key( $f['key'] ?? '' );
	if ( '' === $key ) {
		$key = 'alan_' . wp_rand( 100, 999 );
	}
	$align = in_array( $f['align'] ?? '', array( 'left', 'center', 'right' ), true ) ? $f['align'] : 'center';
	$fonts = flxs_fonts();
	$font  = isset( $fonts[ $f['font'] ?? '' ] ) ? $f['font'] : 'nunito';
	$color = sanitize_hex_color( $f['color'] ?? '' );
	return array(
		'id'          => sanitize_key( $f['id'] ?? '' ) ? sanitize_key( $f['id'] ) : 'f' . wp_rand( 10000, 99999 ),
		'key'         => $key,
		'label'       => sanitize_text_field( $f['label'] ?? $key ),
		'type'        => $type,
		'default'     => sanitize_textarea_field( $f['default'] ?? '' ),
		'placeholder' => sanitize_text_field( $f['placeholder'] ?? '' ),
		'options'     => sanitize_textarea_field( $f['options'] ?? '' ),
		'required'    => ! empty( $f['required'] ),
		'draw'        => ! isset( $f['draw'] ) || ! empty( $f['draw'] ),
		'x'           => round( (float) ( $f['x'] ?? 100 ), 2 ),
		'y'           => round( (float) ( $f['y'] ?? 100 ), 2 ),
		'w'           => max( 10, round( (float) ( $f['w'] ?? 300 ), 2 ) ),
		'size'        => max( 4, min( 200, (float) ( $f['size'] ?? 18 ) ) ),
		'font'        => $font,
		'color'       => $color ? $color : '#111827',
		'align'       => $align,
		'lines'       => max( 1, min( 20, (int) ( $f['lines'] ?? 1 ) ) ),
		'lineHeight'  => max( 0.8, min( 3, (float) ( $f['lineHeight'] ?? 1.25 ) ) ),
		'fit'         => ! isset( $f['fit'] ) || ! empty( $f['fit'] ),
		'upper'       => ! empty( $f['upper'] ),
		'letter'      => max( -5, min( 20, (float) ( $f['letter'] ?? 0 ) ) ),
		'page'        => max( 0, (int) ( $f['page'] ?? 0 ) ),
	);
}

function flxs_save_template( $id, $name, $config, $status = 'active', $sort = null ) {
	global $wpdb;
	$config = flxs_normalize_template_config( $config );
	unset( $config['background']['url'] );
	$data = array(
		'name'       => sanitize_text_field( $name ),
		'status'     => 'inactive' === $status ? 'inactive' : 'active',
		'config'     => flxs_json( $config ),
		'updated_at' => flxs_now(),
	);
	if ( null !== $sort ) {
		$data['sort'] = (int) $sort;
	}
	if ( $id ) {
		$wpdb->update( flxs_table( 'templates' ), $data, array( 'id' => $id ) );
	} else {
		$data['created_at'] = flxs_now();
		$data['created_by'] = get_current_user_id();
		$wpdb->insert( flxs_table( 'templates' ), $data );
		$id = (int) $wpdb->insert_id;
	}
	flxs_log( 'template_saved', 'template', $id, $data['name'], array( 'fields' => count( $config['fields'] ), 'status' => $data['status'] ) );
	return $id;
}

function flxs_delete_template( $id ) {
	global $wpdb;
	$used = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxs_table( 'certificates' ) . ' WHERE template_id = %d', $id ) );
	if ( $used ) {
		return new WP_Error( 'flxs_used', 'Bu şablonla oluşturulmuş ' . $used . ' sertifika var. Silmek yerine pasif yapın.' );
	}
	$t = flxs_get_template( $id );
	$wpdb->delete( flxs_table( 'templates' ), array( 'id' => $id ) );
	flxs_log( 'template_deleted', 'template', $id, $t ? $t['name'] : '' );
	return true;
}

/** Şablonun JS'e gidecek hali */
function flxs_template_for_js( $t ) {
	return array(
		'id'     => (int) $t['id'],
		'name'   => $t['name'],
		'status' => $t['status'],
		'thumb'  => $t['thumb'] ? flxs_upload_dir()['url'] . '/' . $t['thumb'] : '',
		'config' => $t['config'],
	);
}

/**
 * Bağışçının bağış sayfasında seçebileceği şablonlar (şablon editöründe "Bağış sayfasında seçilebilir").
 * Flux Donate gibi eklentiler kullanır. Her öğe: id, name (sertifika başlığı), group (boşsa "Sertifikalar"),
 * thumb (önizleme görseli; şablon bir kez kaydedilince oluşur), fields (bağışçıdan istenecek alanlar:
 * key, label, type, required, placeholder, options, default).
 *
 * @return array[]
 */
function flxs_donor_templates() {
	$out = array();
	foreach ( flxs_list_templates( true ) as $t ) {
		if ( empty( $t['config']['donor']['selectable'] ) ) {
			continue;
		}
		$fields = array();
		foreach ( $t['config']['fields'] as $f ) {
			if ( in_array( $f['type'], array( 'text', 'textarea', 'date', 'number', 'select' ), true ) ) {
				$fields[] = array_intersect_key( $f, array_flip( array( 'key', 'label', 'type', 'required', 'placeholder', 'options', 'default' ) ) );
			}
		}
		$out[] = array(
			'id'     => (int) $t['id'],
			'name'   => $t['config']['title'] ? $t['config']['title'] : $t['name'],
			'group'  => '' !== $t['config']['donor']['group'] ? $t['config']['donor']['group'] : __( 'Certificates', 'flx-certificates' ),
			'sort'   => (int) $t['config']['donor']['sort'],
			'thumb'  => $t['thumb'] ? flxs_upload_dir()['url'] . '/' . $t['thumb'] : ( 'image' === $t['config']['background']['type'] ? $t['config']['background']['url'] : '' ),
			'fields' => $fields,
		);
	}
	usort(
		$out,
		function ( $a, $b ) {
			return $a['sort'] <=> $b['sort'] ?: $a['id'] <=> $b['id'];
		}
	);
	return apply_filters( 'flxs_donor_templates', $out );
}

/** Etkinleştirmede gelen iki hazır şablon. Arka planlar assets/backgrounds içinde (vektör PDF). */
function flxs_default_templates() {
	$base = array(
		'size'   => 14,
		'font'   => 'nunito',
		'color'  => '#4B5563',
		'align'  => 'center',
		'lines'  => 1,
		'fit'    => true,
		'page'   => 0,
	);
	$f = function ( $a ) use ( $base ) {
		return array_merge( $base, $a );
	};

	return array(
		// Derneğin kullandığı asıl tasarım (Canva PDF'inden; isim ve metin değişken alan olarak çıkarıldı)
		array(
			'name'   => 'Fidan Bağış Sertifikası',
			'config' => array(
				'title'      => 'Fidan Bağış Sertifikası',
				'page'       => array( 'w' => 1060.5, 'h' => 1500 ),
				'background' => array( 'type' => 'pdf', 'source' => 'bundled', 'file' => 'fidan-bagis-sertifikasi.pdf' ),
				'share'      => array(
					'og_title'       => '{ad_soyad} adına {fidan_sayisi} fidan bağışlandı 🌱',
					'og_description' => 'Fidan Bağış Sertifikası · Örnek Vakfı',
				),
				'messages'   => flxs_fidan_bagis_messages(),
				'fields'     => array(
					$f( array( 'id' => 'ad', 'key' => 'ad_soyad', 'label' => 'Ad Soyad', 'type' => 'text', 'required' => true, 'placeholder' => 'Seyfullah Doğan', 'x' => 50, 'y' => 394.5, 'w' => 960.5, 'size' => 80, 'font' => 'montserrat-bold', 'color' => '#247833', 'lines' => 2, 'lineHeight' => 1.4, 'upper' => true ) ),
					$f( array( 'id' => 'adet', 'key' => 'fidan_sayisi', 'label' => 'Fidan sayısı', 'type' => 'number', 'default' => '1', 'required' => true, 'draw' => false, 'x' => 40, 'y' => 40, 'w' => 80 ) ),
					$f( array( 'id' => 'metin', 'key' => 'metin', 'label' => 'Metin', 'type' => 'static', 'default' => "Daha Örnek Vakfı ve Daha Yeşil Dünya için\nçaba sarf ederken sizlerin destekleri bizi daha\nçok çalışmaya teşvik etmiştir.\n\nYeşil bir Türkiye için yapmış olduğunuz {fidan_sayisi}\nadet fidan bağışınızdan dolayı teşekkür\nederiz.", 'x' => 77, 'y' => 633, 'w' => 960, 'size' => 38, 'font' => 'montserrat-bold', 'color' => '#247833', 'lines' => 7, 'lineHeight' => 1.54 ) ),
					$f( array( 'id' => 'qr', 'key' => 'qr', 'label' => 'QR kod (doğrulama)', 'type' => 'qr', 'x' => 401, 'y' => 1245, 'w' => 180, 'color' => '#247833' ) ),
				),
			),
		),
		array(
			'name'   => 'Fidan Sertifikası (yatay)',
			'config' => array(
				'title'      => 'Fidan Bağış Sertifikası',
				'page'       => array( 'w' => 841.89, 'h' => 595.28 ),
				'background' => array( 'type' => 'pdf', 'source' => 'bundled', 'file' => 'fidan-sertifikasi.pdf' ),
				'share'      => array(
					'og_title'       => '{ad_soyad} adına {fidan_sayisi} fidan dikildi 🌱',
					'og_description' => '{sertifika} · {site} · {tarih}',
				),
				'fields'     => array(
					$f( array( 'id' => 'baslik', 'key' => 'baslik', 'label' => 'Başlık', 'type' => 'static', 'default' => 'FİDAN BAĞIŞ SERTİFİKASI', 'x' => 120.9, 'y' => 150, 'w' => 600, 'size' => 30, 'font' => 'cinzel-bold', 'color' => '#111827', 'letter' => 2 ) ),
					$f( array( 'id' => 'giris', 'key' => 'giris', 'label' => 'Giriş', 'type' => 'static', 'default' => 'Bu sertifika', 'x' => 220.9, 'y' => 202, 'w' => 400, 'size' => 15, 'font' => 'nunito-italic' ) ),
					$f( array( 'id' => 'ad', 'key' => 'ad_soyad', 'label' => 'Ad Soyad', 'type' => 'text', 'required' => true, 'placeholder' => 'Ayşe Yılmaz', 'x' => 120.9, 'y' => 222, 'w' => 600, 'size' => 50, 'font' => 'greatvibes', 'color' => '#1F2937' ) ),
					$f( array( 'id' => 'adet', 'key' => 'fidan_sayisi', 'label' => 'Fidan sayısı', 'type' => 'number', 'default' => '1', 'draw' => false, 'x' => 40, 'y' => 40, 'w' => 80 ) ),
					$f( array( 'id' => 'metin', 'key' => 'metin', 'label' => 'Açıklama', 'type' => 'static', 'default' => "adına {fidan_sayisi} adet fidan dikildiğini belgeler.\nDoğaya kattığınız nefes için teşekkür ederiz.", 'x' => 170.9, 'y' => 300, 'w' => 500, 'size' => 15, 'lines' => 3, 'lineHeight' => 1.45 ) ),
					$f( array( 'id' => 'tarih', 'key' => 'tarih', 'label' => 'Tarih', 'type' => 'date', 'x' => 150, 'y' => 448, 'w' => 170, 'size' => 14, 'font' => 'nunito-bold', 'color' => '#111827' ) ),
					$f( array( 'id' => 'no', 'key' => 'sertifika_no', 'label' => 'Sertifika No', 'type' => 'number_auto', 'x' => 521.89, 'y' => 448, 'w' => 170, 'size' => 14, 'font' => 'nunito-bold', 'color' => '#111827' ) ),
					$f( array( 'id' => 'qr', 'key' => 'qr', 'label' => 'QR kod', 'type' => 'qr', 'x' => 393.95, 'y' => 470, 'w' => 54, 'color' => '#111827' ) ),
					$f( array( 'id' => 'et1', 'key' => 'etiket_tarih', 'label' => 'Etiket: Tarih', 'type' => 'static', 'default' => 'TARİH', 'x' => 150, 'y' => 477, 'w' => 170, 'size' => 8, 'color' => '#6b7c85', 'letter' => 1.5 ) ),
					$f( array( 'id' => 'et2', 'key' => 'etiket_no', 'label' => 'Etiket: Sertifika No', 'type' => 'static', 'default' => 'SERTİFİKA NO', 'x' => 521.89, 'y' => 477, 'w' => 170, 'size' => 8, 'color' => '#6b7c85', 'letter' => 1.5 ) ),
					$f( array( 'id' => 'et3', 'key' => 'qr_not', 'label' => 'QR notu', 'type' => 'static', 'default' => 'Doğrulamak için okutun', 'x' => 343.95, 'y' => 528, 'w' => 154, 'size' => 7, 'color' => '#6b7c85' ) ),
				),
			),
		),
		array(
			'name'   => 'Katılım Belgesi',
			'config' => array(
				'title'      => 'Katılım Belgesi',
				'page'       => array( 'w' => 841.89, 'h' => 595.28 ),
				'background' => array( 'type' => 'pdf', 'source' => 'bundled', 'file' => 'katilim-belgesi.pdf' ),
				'share'      => array(
					'og_title'       => '{ad_soyad} · {etkinlik} katılım belgesi',
					'og_description' => '{site} · {tarih}',
				),
				'fields'     => array(
					$f( array( 'id' => 'baslik', 'key' => 'baslik', 'label' => 'Başlık', 'type' => 'static', 'default' => 'KATILIM BELGESİ', 'x' => 290, 'y' => 120, 'w' => 500, 'size' => 34, 'font' => 'montserrat-bold', 'color' => '#1F2937', 'align' => 'left', 'letter' => 3 ) ),
					$f( array( 'id' => 'ad', 'key' => 'ad_soyad', 'label' => 'Ad Soyad', 'type' => 'text', 'required' => true, 'placeholder' => 'Mehmet Demir', 'x' => 290, 'y' => 205, 'w' => 500, 'size' => 40, 'font' => 'playfair-bold', 'color' => '#111827', 'align' => 'left' ) ),
					$f( array( 'id' => 'etkinlik', 'key' => 'etkinlik', 'label' => 'Etkinlik adı', 'type' => 'text', 'default' => 'Fidan Dikim Şenliği', 'draw' => false, 'x' => 40, 'y' => 40, 'w' => 200 ) ),
					$f( array( 'id' => 'metin', 'key' => 'metin', 'label' => 'Açıklama', 'type' => 'static', 'default' => '{tarih} tarihinde düzenlenen {etkinlik} etkinliğine katılarak doğanın yeşil geleceğine katkı sağladığınız için teşekkür ederiz.', 'x' => 290, 'y' => 275, 'w' => 470, 'size' => 15, 'align' => 'left', 'lines' => 4, 'lineHeight' => 1.5 ) ),
					$f( array( 'id' => 'tarih', 'key' => 'tarih', 'label' => 'Tarih', 'type' => 'date', 'x' => 290, 'y' => 448, 'w' => 180, 'size' => 14, 'font' => 'nunito-bold', 'color' => '#111827', 'align' => 'left' ) ),
					$f( array( 'id' => 'no', 'key' => 'sertifika_no', 'label' => 'Belge No', 'type' => 'number_auto', 'x' => 500, 'y' => 448, 'w' => 180, 'size' => 14, 'font' => 'nunito-bold', 'color' => '#111827', 'align' => 'left' ) ),
					$f( array( 'id' => 'qr', 'key' => 'qr', 'label' => 'QR kod', 'type' => 'qr', 'x' => 706, 'y' => 430, 'w' => 66, 'color' => '#111827' ) ),
					$f( array( 'id' => 'et1', 'key' => 'etiket_tarih', 'label' => 'Etiket: Tarih', 'type' => 'static', 'default' => 'TARİH', 'x' => 290, 'y' => 477, 'w' => 180, 'size' => 8, 'color' => '#6b7c85', 'align' => 'left', 'letter' => 1.5 ) ),
					$f( array( 'id' => 'et2', 'key' => 'etiket_no', 'label' => 'Etiket: Belge No', 'type' => 'static', 'default' => 'BELGE NO', 'x' => 500, 'y' => 477, 'w' => 180, 'size' => 8, 'color' => '#6b7c85', 'align' => 'left', 'letter' => 1.5 ) ),
				),
			),
		),
	);
}

/** Fidan Bağış Sertifikası'na özel mesaj metinleri */
function flxs_fidan_bagis_messages() {
	return array(
		'whatsapp'      => "Merhaba {ad} 🌱\n\n*Fidan Bağışı Sertifikan* hazırlandı. Görüntülemek ve PDF olarak indirmek için:\n{link}\n\nTeşekkür ederiz,\nÖrnek Vakfı",
		'sms'           => "Merhaba {ad}, Fidan Bağışı Sertifikan hazırlandı. Görüntülemek ve indirmek için: {link}\nÖrnek Vakfı",
		'email_subject' => 'Fidan Bağışı Sertifikan hazırlandı 🌱',
		'email_body'    => "Merhaba {ad},\n\nFidan Bağışı Sertifikan hazırlandı. Aşağıdaki bağlantıdan görüntüleyebilir, PDF olarak indirebilir ve paylaşabilirsin.\n\n{buton}\n\nTeşekkür ederiz,\n{site}",
	);
}
