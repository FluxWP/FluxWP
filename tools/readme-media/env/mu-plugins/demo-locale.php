<?php
// Test ortamı: ön yüz dili demo_front_locale seçeneğinden.
add_filter( 'locale', function ( $l ) { $o = get_option( 'demo_front_locale' ); return $o ? $o : $l; } );
