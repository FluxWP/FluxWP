<?php
// Stripe sürücüsü testi için WordPress taklitleri (yalnız tests/stripe-driver-test.php).
// phpcs:ignoreFile
$GLOBALS['flxt_filters'] = array();
$GLOBALS['flxt_calls']   = array();
function __( $s, $d = "" ) { return $s; }
function esc_html__( $s, $d = "" ) { return $s; }
function _n( $a, $b, $n ) { return 1 === $n ? $a : $b; }
function add_filter( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['flxt_filters'][ $h ][] = $cb; }
function apply_filters( $h, $v, ...$a ) { foreach ( $GLOBALS['flxt_filters'][ $h ] ?? array() as $cb ) { $v = $cb( $v, ...$a ); } return $v; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function wp_remote_request() { throw new Exception( 'no network in tests' ); }
function get_user_meta() { return ''; }
function update_user_meta() {}
function determine_locale() { return 'tr_TR'; }
function remove_accents( $s ) { return $s; }
class WP_Error { public $c; public $m; function __construct( $c = '', $m = '' ) { $this->c = $c; $this->m = $m; } function get_error_message() { return $this->m; } }
class FLXPOS_Payment { public $txn = array(); public $order; public $ref; public $amount; public $currency; public $customer = array(); public $return_url = 'https://example.test/?flxpos=return'; public $description = ''; public $lang = 'tr'; }
class FLXPOS_Stripe { static function page( $d, $p, $cs ) { return 'PAGE:' . $cs; } }

function wp_remote_retrieve_response_code( $r ) { return $r['response']['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function wp_remote_retrieve_headers( $r ) { return $r['headers']; }

