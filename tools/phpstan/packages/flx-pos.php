<?php
// Flux POS: the screen service reads Flux Donate's card rule and capability table when Donate is active.
return array(
	'scanFiles' => array(
		dirname( __DIR__, 3 ) . '/plugins/flx-donate/includes/wc/class-flxb-wc.php',
		dirname( __DIR__, 3 ) . '/plugins/flx-donate/includes/wc/class-flxb-wc-gateways.php',
	),
);
