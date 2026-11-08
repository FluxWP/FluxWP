<?php
defined( 'ABSPATH' ) || exit;

/**
 * Kuruma özel e-kart tasarımları hazır şablon olarak.
 * Görseller ön ayar eklentisinden gelir (flxs_bundled_dirs filtresi, e-kart-{cid}.jpg); görsel yoksa bu bölüm kapalıdır: "ÖRNEK" filigranı ve örnek isimler
 * temizlendi, isim alanları örnek isimlerin yerine, aynı boyut ve renkte konur.
 * Her tasarım config.source = "fonzip:{cid}" ile işaretlenir. Kurulum/güncellemede eksikler pasif şablon olarak eklenir;
 * aktif yapılınca bağış sayfasında seçilebilir. "İçe aktar" silinenleri yeniden ekler.
 */

add_action( 'admin_post_flxs_ecards_import', 'flxs_handle_ecards_import' );
add_action( 'admin_post_flxs_ecards_zip', 'flxs_handle_ecards_zip' );

/** Tasarımlar: cid => başlık (sıra sitedekiyle aynı). */
function flxs_fonzip_ecards() {
	return array(
		3777 => __( 'Father\'s Day Certificate', 'flx-certificates' ),
		3962 => __( 'Just Because Certificate', 'flx-certificates' ),
		3850 => __( 'Birthday Gift', 'flx-certificates' ),
		3961 => __( 'Get Well Soon', 'flx-certificates' ),
		3780 => __( 'First Day at Work', 'flx-certificates' ),
		3966 => __( 'Condolence Certificate', 'flx-certificates' ),
		3711 => __( 'Mother\'s Day Certificate', 'flx-certificates' ),
		6544 => __( 'Wedding Sapling Donation', 'flx-certificates' ),
		3965 => __( 'Teachers\' Day Certificate', 'flx-certificates' ),
		3967 => __( 'Thank You Certificate', 'flx-certificates' ),
		3874 => __( 'Welcome Baby Certificate', 'flx-certificates' ),
		3872 => __( 'Wedding Congratulations Certificate', 'flx-certificates' ),
		3873 => __( 'Wedding Anniversary Certificate', 'flx-certificates' ),
		3717 => __( 'Sapling Donation Certificate (one name)', 'flx-certificates' ),
		6431 => __( 'Sapling Donation (two names, sapling count)', 'flx-certificates' ),
		3718 => __( 'Sapling Donation Certificate (two names)', 'flx-certificates' ),
	);
}

/**
 * Sayfa boyutu (görselin pikseli) ve alanlar. ad_soyad = alıcı (Flux Donate kendisi doldurur),
 * gonderen = bağışlayan, anilan = taziyede anılan kişi, fidan_sayisi = rozetteki sayı (Flux Donate doldurur).
 */
function flxs_ecard_defs() {
	return array(
		3777 => array(
			'w'      => 960,
			'h'      => 678,
			'fields' => array(
				array( 'id' => 'f0', 'key' => 'ad_soyad', 'label' => __( 'Recipient name', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Ayşe Yılmaz', 'x' => 149.0, 'y' => 224.3, 'w' => 276.0, 'size' => 31.1, 'font' => 'playfair-bold', 'color' => '#393a08', 'align' => 'left' ),
				array( 'id' => 'f1', 'key' => 'gonderen', 'label' => __( 'Donor (your name)', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Mehmet Demir', 'x' => 167.0, 'y' => 293.5, 'w' => 294.4, 'size' => 22.6, 'font' => 'playfair-bold', 'color' => '#3a3a05', 'align' => 'left' ),
			),
		),
		3962 => array(
			'w'      => 960,
			'h'      => 678,
			'fields' => array(
				array( 'id' => 'f0', 'key' => 'ad_soyad', 'label' => __( 'Recipient name', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Ayşe Yılmaz', 'x' => 136.0, 'y' => 197.3, 'w' => 270.0, 'size' => 33.9, 'font' => 'playfair-bold', 'color' => '#217d0a', 'align' => 'left' ),
				array( 'id' => 'f1', 'key' => 'gonderen', 'label' => __( 'Donor (your name)', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Mehmet Demir', 'x' => 109.0, 'y' => 298.3, 'w' => 345.0, 'size' => 31.1, 'font' => 'playfair-bold', 'color' => '#267d05', 'align' => 'left' ),
			),
		),
		3850 => array(
			'w'      => 960,
			'h'      => 678,
			'fields' => array(
				array( 'id' => 'f0', 'key' => 'ad_soyad', 'label' => __( 'Recipient name', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Ayşe Yılmaz', 'x' => 215.0, 'y' => 239.3, 'w' => 273.0, 'size' => 33.9, 'font' => 'playfair-bold', 'color' => '#3b8824', 'align' => 'left' ),
				array( 'id' => 'f1', 'key' => 'gonderen', 'label' => __( 'Donor (your name)', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Mehmet Demir', 'x' => 107.7, 'y' => 304.0, 'w' => 423.7, 'size' => 26.8, 'font' => 'playfair-bold', 'color' => '#3c8923', 'align' => 'center' ),
			),
		),
		3961 => array(
			'w'      => 960,
			'h'      => 678,
			'fields' => array(
				array( 'id' => 'f0', 'key' => 'ad_soyad', 'label' => __( 'Recipient name', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Ayşe Yılmaz', 'x' => 236.0, 'y' => 249.8, 'w' => 247.0, 'size' => 32.5, 'font' => 'playfair-bold', 'color' => '#cfefd5', 'align' => 'left' ),
				array( 'id' => 'f1', 'key' => 'gonderen', 'label' => __( 'Donor (your name)', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Mehmet Demir', 'x' => 181.0, 'y' => 353.4, 'w' => 323.0, 'size' => 28.2, 'font' => 'playfair-bold', 'color' => '#c7f1d7', 'align' => 'left' ),
			),
		),
		3780 => array(
			'w'      => 960,
			'h'      => 678,
			'fields' => array(
				array( 'id' => 'f0', 'key' => 'ad_soyad', 'label' => __( 'Recipient name', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Ayşe Yılmaz', 'x' => 170.0, 'y' => 249.8, 'w' => 273.0, 'size' => 32.5, 'font' => 'playfair-bold', 'color' => '#477e00', 'align' => 'left' ),
				array( 'id' => 'f1', 'key' => 'gonderen', 'label' => __( 'Donor (your name)', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Mehmet Demir', 'x' => 158.0, 'y' => 337.8, 'w' => 305.6, 'size' => 29.7, 'font' => 'playfair-bold', 'color' => '#4e7e00', 'align' => 'left' ),
			),
		),
		3966 => array(
			'w'      => 960,
			'h'      => 678,
			'fields' => array(
				array( 'id' => 'f0', 'key' => 'ad_soyad', 'label' => __( 'Recipient name', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Ayşe Yılmaz', 'x' => 112.5, 'y' => 216.0, 'w' => 300.0, 'size' => 26.8, 'font' => 'playfair-bold', 'color' => '#e0f0dc', 'align' => 'center' ),
				array( 'id' => 'f1', 'key' => 'anilan', 'label' => __( 'Person remembered', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Ali Kaya', 'x' => 10.0, 'y' => 279.0, 'w' => 505.0, 'size' => 24.0, 'font' => 'playfair-bold', 'color' => '#dcf1da', 'align' => 'center' ),
				array( 'id' => 'f2', 'key' => 'gonderen', 'label' => __( 'Donor (your name)', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Mehmet Demir', 'x' => 100.3, 'y' => 326.5, 'w' => 353.4, 'size' => 22.6, 'font' => 'playfair-bold', 'color' => '#daf1d9', 'align' => 'center' ),
			),
		),
		3711 => array(
			'w'      => 960,
			'h'      => 680,
			'fields' => array(
				array( 'id' => 'f0', 'key' => 'ad_soyad', 'label' => __( 'Recipient name', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Ayşe Yılmaz', 'x' => 129.0, 'y' => 252.5, 'w' => 253.0, 'size' => 22.6, 'font' => 'playfair-bold', 'color' => '#1e170f', 'align' => 'left' ),
				array( 'id' => 'f1', 'key' => 'gonderen', 'label' => __( 'Donor (your name)', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Mehmet Demir', 'x' => 123.0, 'y' => 302.5, 'w' => 267.2, 'size' => 19.8, 'font' => 'playfair-bold', 'color' => '#1d1716', 'align' => 'left' ),
			),
		),
		6544 => array(
			'w'      => 960,
			'h'      => 1356,
			'fields' => array(
				array( 'id' => 'f0', 'key' => 'ad_soyad', 'label' => __( 'Recipient name', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Ayşe Yılmaz', 'x' => 322.0, 'y' => 526.9, 'w' => 332.0, 'size' => 60.0, 'font' => 'montserrat-semibold', 'color' => '#214e20', 'align' => 'left' ),
				array( 'id' => 'f1', 'key' => 'gonderen', 'label' => __( 'Donor (your name)', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Mehmet Demir', 'x' => 274.0, 'y' => 665.7, 'w' => 470.4, 'size' => 52.9, 'font' => 'montserrat-semibold', 'color' => '#224e21', 'align' => 'left' ),
				array( 'id' => 'fidan', 'key' => 'fidan_sayisi', 'label' => __( 'Number of saplings', 'flx-certificates' ), 'type' => 'number', 'required' => false, 'placeholder' => '1', 'default' => '1', 'x' => 665.5, 'y' => 297.6, 'w' => 155.6, 'size' => 60.3, 'font' => 'montserrat-bold', 'color' => '#ffffff', 'align' => 'center' ),
			),
		),
		3965 => array(
			'w'      => 960,
			'h'      => 679,
			'fields' => array(
				array( 'id' => 'f0', 'key' => 'ad_soyad', 'label' => __( 'Recipient name', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Ayşe Yılmaz', 'x' => 618.0, 'y' => 271.3, 'w' => 276.0, 'size' => 33.9, 'font' => 'playfair-bold', 'color' => '#dff6ea', 'align' => 'left' ),
				array( 'id' => 'f1', 'key' => 'gonderen', 'label' => __( 'Donor (your name)', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Mehmet Demir', 'x' => 564.0, 'y' => 338.5, 'w' => 325.0, 'size' => 19.8, 'font' => 'playfair-bold', 'color' => '#d7f7ea', 'align' => 'left' ),
			),
		),
		3967 => array(
			'w'      => 960,
			'h'      => 678,
			'fields' => array(
				array( 'id' => 'f0', 'key' => 'ad_soyad', 'label' => __( 'Recipient name', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Ayşe Yılmaz', 'x' => 230.0, 'y' => 258.0, 'w' => 280.0, 'size' => 26.8, 'font' => 'playfair-bold', 'color' => '#e9ede0', 'align' => 'left' ),
			),
		),
		3874 => array(
			'w'      => 960,
			'h'      => 678,
			'fields' => array(
				array( 'id' => 'f0', 'key' => 'ad_soyad', 'label' => __( 'Recipient name', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Ayşe Yılmaz', 'x' => 110.0, 'y' => 286.8, 'w' => 300.0, 'size' => 29.7, 'font' => 'playfair-bold', 'color' => '#204b00', 'align' => 'center' ),
				array( 'id' => 'f1', 'key' => 'gonderen', 'label' => __( 'Donor (your name)', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Mehmet Demir', 'x' => 100.2, 'y' => 390.4, 'w' => 311.6, 'size' => 28.2, 'font' => 'playfair-bold', 'color' => '#99d4ad', 'align' => 'center' ),
			),
		),
		3872 => array(
			'w'      => 960,
			'h'      => 678,
			'fields' => array(
				array( 'id' => 'f0', 'key' => 'ad_soyad', 'label' => __( 'Recipient name', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Ayşe Yılmaz', 'x' => 388.0, 'y' => 291.3, 'w' => 234.0, 'size' => 31.1, 'font' => 'playfair-bold', 'color' => '#dfebec', 'align' => 'left' ),
			),
		),
		3873 => array(
			'w'      => 960,
			'h'      => 678,
			'fields' => array(
				array( 'id' => 'f0', 'key' => 'ad_soyad', 'label' => __( 'Recipient name', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Ayşe Yılmaz', 'x' => 206.0, 'y' => 236.8, 'w' => 262.0, 'size' => 29.7, 'font' => 'playfair-bold', 'color' => '#e1f8e0', 'align' => 'left' ),
			),
		),
		3717 => array(
			'w'      => 960,
			'h'      => 1357,
			'fields' => array(
				array( 'id' => 'f0', 'key' => 'ad_soyad', 'label' => __( 'Recipient name', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Ayşe Yılmaz', 'x' => 250.3, 'y' => 354.4, 'w' => 467.4, 'size' => 52.3, 'font' => 'playfair-bold', 'color' => '#225410', 'align' => 'center' ),
			),
		),
		6431 => array(
			'w'      => 960,
			'h'      => 1358,
			'fields' => array(
				array( 'id' => 'f0', 'key' => 'ad_soyad', 'label' => __( 'Recipient name', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Ayşe Yılmaz', 'x' => 316.0, 'y' => 523.2, 'w' => 334.0, 'size' => 58.6, 'font' => 'montserrat-semibold', 'color' => '#214e20', 'align' => 'left' ),
				array( 'id' => 'f1', 'key' => 'gonderen', 'label' => __( 'Donor (your name)', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Mehmet Demir', 'x' => 267.0, 'y' => 616.6, 'w' => 467.2, 'size' => 50.0, 'font' => 'montserrat-semibold', 'color' => '#254c25', 'align' => 'left' ),
				array( 'id' => 'fidan', 'key' => 'fidan_sayisi', 'label' => __( 'Number of saplings', 'flx-certificates' ), 'type' => 'number', 'required' => false, 'placeholder' => '1', 'default' => '1', 'x' => 672.8, 'y' => 292.6, 'w' => 141.3, 'size' => 54.8, 'font' => 'montserrat-bold', 'color' => '#ffffff', 'align' => 'center' ),
			),
		),
		3718 => array(
			'w'      => 960,
			'h'      => 1357,
			'fields' => array(
				array( 'id' => 'f0', 'key' => 'ad_soyad', 'label' => __( 'Recipient name', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Ayşe Yılmaz', 'x' => 261.3, 'y' => 203.4, 'w' => 429.4, 'size' => 52.3, 'font' => 'playfair-bold', 'color' => '#115b2a', 'align' => 'center' ),
				array( 'id' => 'f1', 'key' => 'gonderen', 'label' => __( 'Donor (your name)', 'flx-certificates' ), 'type' => 'text', 'required' => true, 'placeholder' => 'Mehmet Demir', 'x' => 118.4, 'y' => 479.1, 'w' => 699.2, 'size' => 42.4, 'font' => 'playfair-bold', 'color' => '#145a2c', 'align' => 'center' ),
			),
		),
	);
}

function flxs_ecard_file( $cid ) {
	return 'e-kart-' . (int) $cid . '.jpg';
}

/** E-kart görselleri kurulu mu (ön ayar eklentisi veriyor mu)? */
function flxs_ecards_available() {
	foreach ( array_keys( flxs_fonzip_ecards() ) as $cid ) {
		if ( '' === flxs_bundled_path( flxs_ecard_file( $cid ) ) ) {
			return false;
		}
	}
	return true;
}

/** Tasarımın şablon ayarı. */
function flxs_ecard_config( $cid, $sort = 0 ) {
	$defs   = flxs_ecard_defs();
	$titles = flxs_fonzip_ecards();
	$d      = $defs[ $cid ];
	return array(
		'title'      => $titles[ $cid ],
		'source'     => 'fonzip:' . (int) $cid,
		'page'       => array( 'w' => $d['w'], 'h' => $d['h'] ),
		'background' => array( 'type' => 'image', 'source' => 'bundled', 'file' => flxs_ecard_file( $cid ) ),
		'fields'     => $d['fields'],
		'donor'      => array( 'selectable' => true, 'group' => '', 'sort' => (int) $sort ),
	);
}

/** Eklenmiş e-kart şablonları: cid => şablon. */
function flxs_fonzip_imported() {
	$out = array();
	foreach ( flxs_list_templates() as $t ) {
		if ( preg_match( '/^fonzip:(\d+)$/', (string) ( $t['config']['source'] ?? '' ), $m ) ) {
			$out[ (int) $m[1] ] = $t;
		}
	}
	return $out;
}

/**
 * Eksik tasarımları ekler; $fix_old ile 1.4.1'in indirdiği (filigranlı, tek alanlı) şablonları
 * temiz görsel ve doğru alanlarla günceller. Döner: [eklenen, güncellenen].
 */
function flxs_ecards_sync( $add_missing = true, $fix_old = true ) {
	global $wpdb;
	if ( ! flxs_ecards_available() ) {
		return array( 0, 0 );
	}
	$have  = flxs_fonzip_imported();
	$added = 0;
	$fixed = 0;
	$sort  = 0;
	foreach ( flxs_fonzip_ecards() as $cid => $title ) {
		$sort += 10;
		$t = $have[ $cid ] ?? null;
		if ( $t ) {
			if ( ! $fix_old || 'attachment' !== ( $t['config']['background']['source'] ?? '' ) ) {
				continue;
			}
			$c          = flxs_ecard_config( $cid, $sort );
			$c['donor'] = $t['config']['donor'];
			foreach ( array( 'messages', 'share', 'date_format' ) as $keep ) {
				if ( isset( $t['config'][ $keep ] ) ) {
					$c[ $keep ] = $t['config'][ $keep ];
				}
			}
			flxs_save_template( $t['id'], $t['name'], $c, $t['status'] );
			$old = (int) ( $t['config']['background']['id'] ?? 0 );
			if ( $old && 0 === strpos( (string) get_post_field( 'post_name', $old ), 'e-kart-' ) ) {
				wp_delete_attachment( $old, true );
			}
			if ( $t['thumb'] ) {
				wp_delete_file( flxs_upload_dir()['dir'] . '/' . $t['thumb'] );
				$wpdb->update( flxs_table( 'templates' ), array( 'thumb' => '' ), array( 'id' => $t['id'] ) );
			}
			flxs_install_bundled_thumb( $t['id'], flxs_ecard_file( $cid ) );
			++$fixed;
			continue;
		}
		if ( ! $add_missing ) {
			continue;
		}
		$id = flxs_save_template( 0, $title, flxs_ecard_config( $cid, $sort ), 'inactive', $sort );
		if ( $id && ! is_wp_error( $id ) ) {
			flxs_install_bundled_thumb( $id, flxs_ecard_file( $cid ) );
			++$added;
		}
	}
	return array( $added, $fixed );
}

function flxs_handle_ecards_import() {
	check_admin_referer( 'flxs_ecards_import' );
	if ( ! flxs_can_manage() ) {
		wp_die( esc_html__( 'Not allowed', 'flx-certificates' ) );
	}
	list( $added, $fixed ) = flxs_ecards_sync();
	$args = array();
	if ( $added || $fixed ) {
		/* translators: %d: number of templates */
		$args['flxs_msg'] = sprintf( __( '%d e-card designs were added as inactive templates. Designs you activate can be picked on the donation page.', 'flx-certificates' ), $added + $fixed );
	} else {
		$args['flxs_msg'] = __( 'All e-card designs are already added.', 'flx-certificates' );
	}
	flxs_redirect( 'flxs-sablonlar', $args );
}

/** E-kart görsellerini tek zip olarak indirir (tasarımı başka yerde incelemek için). */
function flxs_handle_ecards_zip() {
	check_admin_referer( 'flxs_ecards_zip' );
	if ( ! flxs_can_manage() ) {
		wp_die( esc_html__( 'Not allowed', 'flx-certificates' ) );
	}
	if ( ! class_exists( 'ZipArchive' ) ) {
		wp_die( esc_html__( 'The server has no ZipArchive (PHP zip extension).', 'flx-certificates' ) );
	}
	$file   = wp_tempnam( 'e-kartlar.zip' );
	$zip    = new ZipArchive();
	$titles = flxs_fonzip_ecards();
	$zip->open( $file, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	foreach ( $titles as $cid => $title ) {
		$path = flxs_bundled_path( flxs_ecard_file( $cid ) );
		if ( '' !== $path ) {
			$zip->addFile( $path, $cid . '-' . sanitize_title( $title ) . '.jpg' );
		}
	}
	$zip->close();
	nocache_headers();
	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="e-kartlar.zip"' );
	header( 'Content-Length: ' . filesize( $file ) );
	readfile( $file ); // phpcs:ignore
	wp_delete_file( $file );
	exit;
}

/** Şablonlar sayfasındaki kart. */
function flxs_ecards_box() {
	if ( ! flxs_ecards_available() ) {
		return;
	}
	$have  = flxs_fonzip_imported();
	$total = count( flxs_fonzip_ecards() );
	echo '<div class="flxs-card flxs-import"><strong>' . esc_html__( 'Ready-made e-cards', 'flx-certificates' ) . '</strong> <span class="flxs-muted">';
	/* translators: 1: imported count, 2: total */
	echo esc_html( sprintf( __( '(%1$d/%2$d added)', 'flx-certificates' ), count( $have ), $total ) ) . '</span> ';
	if ( count( $have ) < $total ) {
		echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=flxs_ecards_import' ), 'flxs_ecards_import' ) ) . '" data-confirm="' . esc_attr__( 'Add the missing e-card designs as inactive templates?', 'flx-certificates' ) . '">' . esc_html__( 'Import', 'flx-certificates' ) . '</a> ';
	}
	echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=flxs_ecards_zip' ), 'flxs_ecards_zip' ) ) . '">' . esc_html__( 'Download images as zip', 'flx-certificates' ) . '</a>';
	echo '</div>';
}
