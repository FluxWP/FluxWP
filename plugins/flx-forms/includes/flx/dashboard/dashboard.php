<?php
/**
 * Flux Forms: Dashboard loader (required from includes/flx/flx-fork.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( is_admin() && ! class_exists( 'FLXF_Dashboard' ) ) {
	require_once __DIR__ . '/class-flxf-dashboard.php';
	FLXF_Dashboard::init();
}
