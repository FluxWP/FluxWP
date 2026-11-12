<?php
/**
 * Load all the files for Advanced form block.
 *
 * @package Flux Block Editor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once FLXBK_PATH . 'includes/blocks/class-flx-blocks-advanced-form-block.php';
require_once FLXBK_PATH . 'includes/blocks/form/class-flx-blocks-advanced-form-input-block.php';
require_once FLXBK_PATH . 'includes/blocks/form/class-flx-blocks-text-input-block.php';
require_once FLXBK_PATH . 'includes/blocks/form/class-flx-blocks-textarea-input-block.php';
require_once FLXBK_PATH . 'includes/blocks/form/class-flx-blocks-email-input-block.php';
require_once FLXBK_PATH . 'includes/blocks/form/class-flx-blocks-number-input-block.php';
require_once FLXBK_PATH . 'includes/blocks/form/class-flx-blocks-hidden-input-block.php';
require_once FLXBK_PATH . 'includes/blocks/form/class-flx-blocks-date-input-block.php';
require_once FLXBK_PATH . 'includes/blocks/form/class-flx-blocks-time-input-block.php';
require_once FLXBK_PATH . 'includes/blocks/form/class-flx-blocks-telephone-input-block.php';
require_once FLXBK_PATH . 'includes/blocks/form/class-flx-blocks-file-block.php';
require_once FLXBK_PATH . 'includes/blocks/form/class-flx-blocks-radio-block.php';
require_once FLXBK_PATH . 'includes/blocks/form/class-flx-blocks-select-block.php';
require_once FLXBK_PATH . 'includes/blocks/form/class-flx-blocks-checkbox-block.php';
require_once FLXBK_PATH . 'includes/blocks/form/class-flx-blocks-captcha-block.php';
require_once FLXBK_PATH . 'includes/blocks/form/class-flx-blocks-accept-block.php';
require_once FLXBK_PATH . 'includes/blocks/form/class-flx-blocks-submit-block.php';
require_once FLXBK_PATH . 'includes/advanced-form/advanced-form-rest-api.php';
require_once FLXBK_PATH . 'includes/advanced-form/advanced-form-cpt.php';
require_once FLXBK_PATH . 'includes/advanced-form/advanced-form-ajax.php';
require_once FLXBK_PATH . 'includes/advanced-form/advanced-form-submit-actions.php';
require_once FLXBK_PATH . 'includes/advanced-form/activecampaign-rest-api.php';
require_once FLXBK_PATH . 'includes/advanced-form/getresponse-rest-api.php';
require_once FLXBK_PATH . 'includes/advanced-form/advanced-form-captcha-settings.php';
require_once FLXBK_PATH . 'includes/advanced-form/advanced-form-captcha-verify.php';
