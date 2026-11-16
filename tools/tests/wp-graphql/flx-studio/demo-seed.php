<?php
/**
 * Seeds a test site with demo content for the Studio screen: folders, people, templates and a gallery of
 * generated placeholder images (GD, no AI) and two short clips (ffmpeg).
 *
 *   wp eval-file tools/tests/wp-graphql/flx-studio/demo-seed.php
 */

$up  = wp_upload_dir();
$dir = $up['basedir'] . '/flxst-demo';
$url = $up['baseurl'] . '/flxst-demo';
wp_mkdir_p( $dir );

$palettes = array(
	array( array( 242, 153, 74 ), array( 91, 43, 130 ), array( 255, 220, 140 ) ),
	array( array( 20, 90, 70 ), array( 160, 210, 120 ), array( 240, 250, 200 ) ),
	array( array( 14, 52, 105 ), array( 80, 180, 220 ), array( 235, 250, 255 ) ),
	array( array( 230, 90, 90 ), array( 250, 190, 120 ), array( 255, 245, 220 ) ),
	array( array( 30, 30, 50 ), array( 120, 80, 200 ), array( 250, 170, 220 ) ),
	array( array( 60, 110, 50 ), array( 210, 190, 90 ), array( 255, 255, 230 ) ),
);

/** A soft abstract landscape: gradient sky, sun, hills, blobs. */
function flxst_demo_img( string $file, int $w, int $h, array $pal, int $seed ): void {
	mt_srand( $seed );
	$im = imagecreatetruecolor( $w, $h );
	imagealphablending( $im, true );
	for ( $y = 0; $y < $h; $y++ ) {
		$t = $y / $h;
		$c = imagecolorallocate( $im, (int) ( $pal[0][0] + ( $pal[1][0] - $pal[0][0] ) * $t ), (int) ( $pal[0][1] + ( $pal[1][1] - $pal[0][1] ) * $t ), (int) ( $pal[0][2] + ( $pal[1][2] - $pal[0][2] ) * $t ) );
		imageline( $im, 0, $y, $w, $y, $c );
	}
	$sun = imagecolorallocatealpha( $im, $pal[2][0], $pal[2][1], $pal[2][2], 20 );
	imagefilledellipse( $im, (int) ( $w * ( 0.3 + mt_rand( 0, 40 ) / 100 ) ), (int) ( $h * 0.34 ), (int) ( $w * 0.28 ), (int) ( $w * 0.28 ), $sun );
	for ( $i = 0; $i < 7; $i++ ) {
		$b = imagecolorallocatealpha( $im, mt_rand( 0, 255 ), mt_rand( 0, 255 ), mt_rand( 0, 255 ), mt_rand( 95, 115 ) );
		imagefilledellipse( $im, mt_rand( 0, $w ), mt_rand( 0, $h ), mt_rand( (int) ( $w * 0.2 ), (int) ( $w * 0.6 ) ), mt_rand( (int) ( $h * 0.15 ), (int) ( $h * 0.4 ) ), $b );
	}
	for ( $l = 0; $l < 3; $l++ ) {
		$col = imagecolorallocatealpha( $im, (int) ( $pal[0][0] * ( 0.5 - $l * 0.12 ) ), (int) ( $pal[0][1] * ( 0.55 - $l * 0.12 ) ), (int) ( $pal[0][2] * ( 0.5 - $l * 0.1 ) ), 10 );
		$pts = array( 0, $h );
		$n   = 9;
		for ( $i = 0; $i <= $n; $i++ ) {
			$pts[] = (int) ( $w * $i / $n );
			$pts[] = (int) ( $h * ( 0.62 + $l * 0.12 ) + sin( $i * 1.3 + $seed + $l ) * $h * 0.07 );
		}
		$pts[] = $w;
		$pts[] = $h;
		imagefilledpolygon( $im, $pts, $col );
	}
	imagejpeg( $im, $file, 86 );
	imagedestroy( $im );
}

function flxst_demo_thumb( string $src, string $dst, int $w ): void {
	$im = imagecreatefromjpeg( $src );
	$s  = imagescale( $im, $w );
	imagejpeg( $s, $dst, 80 );
}

$specs = array(
	// title, prompt, w, h, aspect, favorite, folder (0 none, 1 events, 2 social)
	array( '11 Kasım afişi', '11 Kasım fidan dikim etkinliği için afiş, yeşil tonlar, büyük başlık alanı', 1024, 1280, '4:5', 1, 1 ),
	array( 'Orman sabahı', 'Sisli bir ormanda gün doğumu, sıcak ışık, geniş açı', 1536, 1024, '3:2', 0, 0 ),
	array( 'Fidan eller', 'Toprakla dolu ellerde genç bir fidan, sığ alan derinliği', 1024, 1024, '1:1', 1, 2 ),
	array( 'Gönüllüler', 'Tepede fidan diken gülümseyen gönüllüler, altın saat', 1024, 1536, '2:3', 0, 1 ),
	array( 'Nehir kıvrımı', 'Yukarıdan koyu yeşil orman ve kıvrılan bir nehir', 1536, 1024, '3:2', 0, 0 ),
	array( 'Instagram kapağı', 'Yeşil Türkiye Instagram kapak görseli, sade, geometrik', 1024, 1024, '1:1', 0, 2 ),
	array( 'Çocuklar ve doğa', 'Çocuklar küçük bir ağacı birlikte suluyor, sabah ışığı, çizim stili', 1024, 1024, '1:1', 0, 1 ),
	array( 'Bağış kampanyası', 'Bir fidan bir umut: bağış kampanyası görseli', 1280, 1024, '4:5', 1, 2 ),
	array( 'Gün batımı vadisi', 'Gün batımında turuncu ışıkla yıkanmış vadi', 1792, 1024, '16:9', 0, 0 ),
	array( 'Sonbahar yolu', 'Sonbahar renklerinde patika, loş ışık', 1024, 1536, '2:3', 0, 0 ),
	array( 'Yeni dikim alanı', 'Yeni dikilmiş fidanlarla dolu geniş bir arazi, drone çekimi', 1536, 1024, '3:2', 0, 1 ),
	array( 'Mavi saat', 'Gölün üstünde mavi saat, sakin su yansıması', 1024, 1024, '1:1', 0, 0 ),
);

wp_set_current_user( 1 );
global $wpdb;
foreach ( array( 'items', 'folders', 'people', 'refs', 'templates' ) as $t ) {
	$wpdb->query( 'DELETE FROM ' . flxst_table( $t ) ); // phpcs:ignore WordPress.DB
}

$now = time();
$fold = array();
$fold[1] = (int) flxst_folder_create_demo( 'Etkinlik afişleri', 0, 1 );
$fold[2] = (int) flxst_folder_create_demo( 'Sosyal medya', 0, 0 );
flxst_folder_create_demo( 'Fidan günü', $fold[1], 0 );

function flxst_folder_create_demo( string $name, int $parent, int $shared ): int {
	global $wpdb;
	$wpdb->insert( flxst_table( 'folders' ), array( 'user_id' => 1, 'parent_id' => $parent, 'name' => $name, 'shared' => $shared, 'created' => time() ) ); // phpcs:ignore WordPress.DB
	return (int) $wpdb->insert_id;
}

$i = 0;
foreach ( $specs as $s ) {
	list( $title, $prompt, $w, $h, $aspect, $fav, $f ) = $s;
	$base = $dir . '/demo-' . $i . '.jpg';
	flxst_demo_img( $base, $w, $h, $palettes[ $i % count( $palettes ) ], 11 + $i * 7 );
	flxst_demo_thumb( $base, $dir . '/demo-' . $i . '-t.jpg', 520 );
	$day = $i * 2.4; // spread over ~30 days for the dashboard
	flxst_item_insert(
		array(
			'user_id'   => 1,
			'folder_id' => $f ? $fold[ $f ] : 0,
			'kind'      => 'image',
			'state'     => 'done',
			'job'       => 'flxm_demo' . $i,
			'title'     => $title,
			'prompt'    => $prompt,
			'model'     => 0 === $i % 3 ? 'aurora-pro' : 'nova-image',
			'provider'  => 'alpha',
			'params'    => wp_json_encode( array( 'aspect' => $aspect, 'resolution' => '1K', 'quality' => 'high', 'n' => 1, 'refs' => array() ) ),
			'url'       => $url . '/demo-' . $i . '.jpg',
			'path'      => $base,
			'thumb'     => $url . '/demo-' . $i . '-t.jpg',
			'mime'      => 'image/jpeg',
			'width'     => $w,
			'height'    => $h,
			'bytes'     => (int) filesize( $base ),
			'cost'      => 0.04 + ( $i % 3 ) * 0.01,
			'favorite'  => $fav,
			'created'   => (int) ( $now - $day * DAY_IN_SECONDS - $i * 600 ),
		)
	);
	$i++;
}
// New result of a generation started in the UI.
flxst_demo_img( $dir . '/new.jpg', 1024, 1024, $palettes[2], 99 );

// Two short clips.
foreach ( array( 0 => 'Rüzgârda yapraklar', 1 => 'Fidan büyüyor' ) as $v => $title ) {
	$src = $dir . '/demo-' . ( 1 + $v * 3 ) . '.jpg';
	$mp4 = $dir . '/clip-' . $v . '.mp4';
	$jpg = $dir . '/clip-' . $v . '-t.jpg';
	shell_exec( 'ffmpeg -y -loglevel error -loop 1 -i ' . escapeshellarg( $src ) . ' -vf "scale=960:540:force_original_aspect_ratio=increase,crop=960:540,zoompan=z=\'min(zoom+0.0015,1.3)\':d=75:s=960x540" -t 3 -pix_fmt yuv420p -an ' . escapeshellarg( $mp4 ) );
	flxst_demo_thumb( $src, $jpg, 520 );
	flxst_item_insert(
		array(
			'user_id'  => 1,
			'kind'     => 'video',
			'state'    => 'done',
			'job'      => 'flxm_demov' . $v,
			'title'    => $title,
			'prompt'   => 'Kısa bir doğa klibi: ' . $title,
			'model'    => 'kite-video',
			'provider' => 'beta',
			'params'   => wp_json_encode( array( 'aspect' => '16:9', 'resolution' => '720p', 'duration' => 6, 'audio' => true, 'refs' => array() ) ),
			'url'      => $url . '/clip-' . $v . '.mp4',
			'path'     => $mp4,
			'thumb'    => $url . '/clip-' . $v . '-t.jpg',
			'mime'     => 'video/mp4',
			'width'    => 960,
			'height'   => 540,
			'bytes'    => is_file( $mp4 ) ? (int) filesize( $mp4 ) : 0,
			'cost'     => 0.6,
			'created'  => $now - ( 3 + $v * 5 ) * DAY_IN_SECONDS,
		)
	);
}
// Running, queued, failed, cancelled.
foreach ( array(
	array( 'running', 'Ormanda sisli bir sabah, hareketli kamera', 'nova-image', 0.45, '' ),
	array( 'queued', 'Gün batımında dağ evi', 'aurora-pro', 0, '' ),
	array( 'failed', 'Çok uzun ve belirsiz bir istem', 'nova-image', 0, 'The provider refused the prompt (content policy).' ),
	array( 'cancelled', 'Yarım bırakılan üretim', 'aurora-pro', 0, 'Cancelled.' ),
) as $k => $row ) {
	flxst_item_insert(
		array(
			'user_id'  => 1,
			'state'    => $row[0],
			'job'      => in_array( $row[0], array( 'running', 'queued' ), true ) ? 'flxm_demo_p' . $k : '',
			'prompt'   => $row[1],
			'model'    => $row[2],
			'provider' => 'alpha',
			'params'   => wp_json_encode( array( 'aspect' => '1:1', 'resolution' => '1K', 'n' => 1, 'progress' => $row[3], 'refs' => array() ) ),
			'error'    => $row[4],
			'created'  => $now - 60 * ( $k + 1 ),
		)
	);
}

// People with photos.
foreach ( array( 'Ayşe', 'Mehmet', 'Elif' ) as $k => $name ) {
	$wpdb->insert( flxst_table( 'people' ), array( 'user_id' => 1, 'name' => $name, 'shared' => 1, 'created' => $now ) ); // phpcs:ignore WordPress.DB
	$pid = (int) $wpdb->insert_id;
	if ( 2 === $k ) {
		continue; // a person without photos
	}
	for ( $p = 0; $p < 2; $p++ ) {
		$f = $dir . '/person-' . $k . '-' . $p . '.jpg';
		flxst_demo_img( $f, 400, 400, $palettes[ ( $k + $p + 3 ) % count( $palettes ) ], 300 + $k * 10 + $p );
		$wpdb->insert( flxst_table( 'refs' ), array( 'user_id' => 1, 'person_id' => $pid, 'name' => $name, 'role' => 'identity', 'url' => $url . '/person-' . $k . '-' . $p . '.jpg', 'path' => $f, 'thumb' => $url . '/person-' . $k . '-' . $p . '.jpg', 'mime' => 'image/jpeg', 'width' => 400, 'height' => 400, 'saved' => 1, 'created' => $now ) ); // phpcs:ignore WordPress.DB
	}
}
// Templates.
$wpdb->insert( flxst_table( 'templates' ), array( 'user_id' => 1, 'name' => 'Etkinlik afişi 4:5', 'prompt' => 'Etkinlik afişi, yeşil tonlar, büyük başlık alanı', 'params' => wp_json_encode( array( 'kind' => 'image', 'model' => 'nova-image', 'aspect' => '4:5', 'resolution' => '2K', 'quality' => 'high', 'n' => 1 ) ), 'shared' => 1, 'created' => $now ) ); // phpcs:ignore WordPress.DB
$wpdb->insert( flxst_table( 'templates' ), array( 'user_id' => 1, 'name' => 'Kısa klip 16:9', 'prompt' => '', 'params' => wp_json_encode( array( 'kind' => 'video', 'model' => 'kite-video', 'aspect' => '16:9', 'resolution' => '720p', 'duration' => 6 ) ), 'shared' => 0, 'created' => $now ) ); // phpcs:ignore WordPress.DB

echo "Studio demo content ready.\n";
