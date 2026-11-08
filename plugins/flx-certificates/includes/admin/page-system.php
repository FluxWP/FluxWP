<?php
defined( 'ABSPATH' ) || exit;

/** Sertifika › Sistem kontrolü: Flux Certificates'nın kendi kontrolleri (tema tüm FluxWP kontrollerini tek ekranda toplar) */
function flxs_page_system() {
	$checks = flxs_system_checks();
	$count  = array_count_values( wp_list_pluck( $checks, 'status' ) );
	$names  = array(
		'ok'      => __( 'OK', 'flx-certificates' ),
		'warning' => __( 'Warning', 'flx-certificates' ),
		'error'   => __( 'Error', 'flx-certificates' ),
		'info'    => __( 'Info', 'flx-certificates' ),
	);
	$test = flxs_iletisim_active() ? '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=flxi-baglantilar' ) ) . '">' . esc_html__( 'Test WhatsApp/SMS connections', 'flx-certificates' ) . '</a>' : '';
	flxs_admin_header(
		__( 'System check', 'flx-certificates' ),
		array(),
		esc_html__( 'Whether setup, cron and sending are in place. Checked again each time this page opens. WhatsApp/SMS connections and tests live in Flux Mail.', 'flx-certificates' )
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
