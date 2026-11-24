<?php
/**
 * Constants defined below includes/*.php. The strict bootstrap does not see them.
 *
 * FLXB_WC_READY is defined in includes/wc/class-flxb-wc.php when the WooCommerce layer boots.
 */
if ( ! defined( 'FLXB_WC_READY' ) ) {
	define( 'FLXB_WC_READY', true );
}
