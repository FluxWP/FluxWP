<?php
defined( 'ABSPATH' ) || exit;

/** Sertifika › Sistem kontrolü: Flux Certificates'nın kendi kontrolleri (tema tüm FluxWP kontrollerini tek ekranda toplar) */
function flxs_page_system() {
	$checks = flxs_system_checks();
	$count  = array_count_values( wp_list_pluck( $checks, 'status' ) );
	$names  = array(
		'ok'      => __( 'Tamam', 'flx-certificates' ),
		'warning' => __( 'Uyarı', 'flx-certificates' ),
		'error'   => __( 'Hata', 'flx-certificates' ),
		'info'    => __( 'Bilgi', 'flx-certificates' ),
	);
	$test = flxs_iletisim_active() ? '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=flxi-baglantilar' ) ) . '">' . esc_html__( 'WhatsApp/SMS bağlantılarını test et', 'flx-certificates' ) . '</a>' : '';
	flxs_admin_header(
		__( 'Sistem kontrolü', 'flx-certificates' ),
		array(),
		esc_html__( 'Kurulumun, cron\'un ve gönderimin yerli yerinde olup olmadığı. Sayfa her açıldığında yeniden kontrol edilir. WhatsApp/SMS bağlantıları ve testleri Flux Mail\'dedir.', 'flx-certificates' )
	);
	echo '<div class="flxs-sys__bar">';
	foreach ( array( 'error', 'warning', 'ok', 'info' ) as $s ) {
		if ( ! empty( $count[ $s ] ) ) {
			echo '<span class="flxs-sys__pill flxs-sys__pill--' . esc_attr( $s ) . '">' . (int) $count[ $s ] . ' ' . esc_html( $names[ $s ] ) . '</span>';
		}
	}
	echo '<span class="flxs-sys__test">' . $test . '</span></div>'; // phpcs:ignore

	$groups = array();
	foreach ( $checks as $c ) {
		$groups[ $c['group'] ][] = $c;
	}
	$icons = array( 'ok' => '✓', 'warning' => '!', 'error' => '✕', 'info' => 'i' );
	foreach ( $groups as $g => $list ) {
		echo '<div class="flxs-card flxs-sys"><h2 class="flxs-card__title">' . esc_html( $g ) . '</h2><ul class="flxs-sys__list">';
		foreach ( $list as $c ) {
			echo '<li class="flxs-sys__item is-' . esc_attr( $c['status'] ) . '"><span class="flxs-sys__ico" aria-label="' . esc_attr( $names[ $c['status'] ] ) . '">' . esc_html( $icons[ $c['status'] ] ) . '</span>';
			echo '<div class="flxs-sys__body"><strong>' . esc_html( $c['label'] ) . '</strong><p>' . esc_html( $c['message'] ) . '</p></div>';
			if ( $c['action_url'] && false === strpos( $c['action_url'], 'page=flxs-sistem' ) ) {
				echo '<a class="button button-small" href="' . esc_url( $c['action_url'] ) . '">' . esc_html( $c['action_label'] ) . '</a>';
			}
			echo '</li>';
		}
		echo '</ul></div>';
	}
	echo '</div>';
}
