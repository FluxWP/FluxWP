<?php
/**
 * Demo stubs of Flux AI for screenshots and manual tests of the Studio screen (no real generation, no keys).
 * Copy into wp-content/mu-plugins/ of a test site (tools/tests/wp-graphql/flx-studio/demo.sh does it).
 */

if ( ! function_exists( 'flxai_media_submit' ) ) {
	function flxai_media_providers() {
		$img = array( 'aspects' => array( '1:1', '4:5', '3:2', '2:3', '16:9' ), 'resolutions' => array( '1K', '2K' ), 'max_refs' => 8, 'max_n' => 4, 'mask' => false );
		return array(
			'default_image' => 'nova-image',
			'default_video' => 'kite-video',
			'providers'     => array(
				'alpha' => array( 'label' => 'Alpha Cloud', 'ready' => true ),
				'beta'  => array( 'label' => 'Beta AI', 'ready' => true ),
				'gamma' => array( 'label' => 'Gamma Labs', 'ready' => false ),
			),
			'models'        => array(
				'nova-image'  => array_merge( $img, array( 'label' => 'Nova Image 2', 'kind' => 'image', 'routes' => array( 'alpha', 'beta' ), 'providers' => array( 'alpha', 'beta' ), 'auto' => 'alpha', 'qualities' => array( 'low', 'medium', 'high' ), 'mask' => true, 'price' => array( '1K' => 0.04, '2K' => 0.09 ) ) ),
				'aurora-pro'  => array_merge( $img, array( 'label' => 'Aurora Pro', 'kind' => 'image', 'routes' => array( 'alpha', 'gamma' ), 'providers' => array( 'alpha' ), 'auto' => 'alpha', 'qualities' => array(), 'price' => array( '1K' => 0.05, '2K' => 0.1 ) ) ),
				'kite-video'  => array( 'label' => 'Kite Video', 'kind' => 'video', 'routes' => array( 'beta' ), 'providers' => array( 'beta' ), 'auto' => 'beta', 'aspects' => array( '16:9', '9:16', '1:1' ), 'resolutions' => array( '720p', '1080p' ), 'qualities' => array(), 'duration' => array( 4, 10, 6 ), 'max_refs' => 2, 'max_n' => 1, 'mask' => false, 'price' => array( '720p' => 0.1, '1080p' => 0.18 ) ),
			),
		);
	}
	function flxai_media_submit( $req ) {
		return array( 'job' => 'flxm_' . dechex( wp_rand( 0x100000, 0xffffff ) ), 'state' => 'running', 'provider' => 'alpha' );
	}
	function flxai_media_status( $job ) {
		$dir = wp_upload_dir();
		$png = (string) $dir['basedir'] . '/flxst-demo/new.jpg';
		if ( ! is_file( $png ) ) {
			return array( 'job' => $job, 'state' => 'failed', 'provider' => 'alpha', 'error' => 'Demo image missing.' );
		}
		return array(
			'job'      => $job,
			'state'    => 'done',
			'provider' => 'alpha',
			'cost'     => 0.04,
			'outputs'  => array( array( 'url' => $dir['baseurl'] . '/flxst-demo/new.jpg', 'path' => $png, 'mime' => 'image/jpeg', 'width' => 1024, 'height' => 1024, 'bytes' => (int) filesize( $png ), 'stored' => true ) ),
		);
	}
	function flxai_media_cancel( $job ) {
		return array( 'job' => $job, 'state' => 'cancelled', 'provider' => 'alpha', 'error' => 'Cancelled.' );
	}
	function flxai_chat( $messages, $opts = array() ) {
		if ( 'brainstorm' === ( $opts['purpose'] ?? '' ) ) {
			return array( 'content' => '{"ideas":[{"title":"Fidan dikim günü","prompt":"A joyful group of volunteers planting saplings on a sunny hillside, warm light, wide angle, Instagram post"},{"title":"Yeşil gelecek","prompt":"Close-up of hands holding a young sapling with soil, shallow depth of field, hopeful mood"},{"title":"Orman afişi","prompt":"Poster of a dense green forest seen from above with a winding river, bold clean typography space on top"},{"title":"Çocuklar ve doğa","prompt":"Children watering a small tree together, soft morning light, illustrated style"}]}' );
		}
		return array( 'content' => 'A highly detailed, warm-toned photograph of volunteers planting saplings on a sunny hillside at golden hour, shallow depth of field, natural colors, 35mm lens, soft rim light.' );
	}
	function flxai_ready( $source = '' ) {
		return true;
	}
	function flxai_budget_status( $fresh = false ) {
		return array( 'daily' => array( 'label' => 'Daily', 'limit' => 5, 'spent' => 1.85, 'pct' => 37, 'over' => false ) );
	}
}
