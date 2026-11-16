<?php
// FluxWP Panel: the wizard steps file is loaded on demand (FLXK_DIR points at tools/phpstan here).
return array(
	'ignoreErrors' => array(
		array(
			'message' => '#Path in require_once\(\) ".*/includes/onboarding\.php" is not a file#',
			'path'    => dirname( __DIR__, 3 ) . '/plugins/flx-setup/includes/graphql/schema.php',
		),
	),
);
