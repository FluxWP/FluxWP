<?php
namespace Stripe\Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\ApiErrorException', false ) ) {
	require_once __DIR__ . '/ApiErrorException.php';
}

class PermissionException extends ApiErrorException {}
