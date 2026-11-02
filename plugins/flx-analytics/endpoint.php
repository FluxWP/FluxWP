<?php
/**
 * Hızlı toplama ucu: WordPress yüklenmeden çalışır, ziyaret satırını kuyruğa yazar ve çıkar.
 * Veri dizini wp-content/uploads/flx-analytics (eklenti klasörünün iki üstü). Dizin farklıysa
 * eklenti kendiliğinden REST ucuna geçer (Ayarlar › Toplama yöntemi).
 *
 * @package flx-analytics
 */

define( 'FLXAN_STANDALONE', true );
require __DIR__ . '/includes/collect.php';
flxan_c_run( dirname( __DIR__, 2 ) . '/uploads/flx-analytics' );
