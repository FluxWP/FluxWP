<?php
/**
 * Flux Forms — Conditional Logic add-on (AdnSMF), server side.
 *
 * Loaded by includes/class-Emsfb.php when the AdnSMF setting is >= 1. It
 * provides:
 *   - \Emsfb\Emsfb_Logic_Validator, the server evaluator the core delegates to
 *     (build_values_map(), evaluate_condition_group(), set_environment()).
 *   - the `efb_logic_prepare_submission` filter, which recomputes field/step
 *     visibility from the SUBMITTED VALUES ONLY (client claims about what was
 *     visible are never read), drops the values of hidden/logic-disabled
 *     fields and tells the core which fields are ignored / required /
 *     optional, and whether a block_submit rule vetoes the submission.
 *   - translated strings for the builder and public runtimes.
 *
 * The evaluation algorithm is mirrored line for line in
 * assets/public/js/conditional-logic-efb.js; change both together.
 *
 * @package Flux Forms
 */

namespace Emsfb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Emsfb\Emsfb_Logic_Validator' ) ) :

class Emsfb_Logic_Validator {

	const VERSION = '1.0.0';

	/** Max passes of the visibility fixpoint (hidden fields count as empty). */
	const MAX_PASSES = 10;

	/** Structure rows that are never a field a rule can read or target. */
	private static $non_field_types = array( 'form', 'option', 'r_matrix', 'buttonnav', 'step' );

	private static $payment_types = array( 'payment', 'stripe', 'paypal', 'persiapay' );

	/** @var array{query: array, user: array, current_step: int} */
	private $env = array(
		'query'        => array(),
		'user'         => array( 'logged_in' => false, 'roles' => array() ),
		'current_step' => 1,
	);

	/** Rows of the submission being evaluated (payment conditions read them). */
	private $rows = array();

	/* ------------------------------------------------------------------ */
	/* Bootstrap                                                          */
	/* ------------------------------------------------------------------ */

	public static function register() {
		add_filter( 'efb_logic_prepare_submission', array( __CLASS__, 'filter_prepare_submission' ), 10, 3 );
		add_action( 'wp_print_footer_scripts', array( __CLASS__, 'maybe_enqueue_public' ), 0 );
		add_action( 'wp_print_footer_scripts', array( __CLASS__, 'localize_public' ), 1 );
		add_action( 'admin_print_footer_scripts', array( __CLASS__, 'localize_admin' ), 1 );
	}

	public function set_environment( $env ) {
		if ( ! is_array( $env ) ) return;
		if ( isset( $env['query'] ) && is_array( $env['query'] ) ) $this->env['query'] = $env['query'];
		if ( isset( $env['user'] ) && is_array( $env['user'] ) ) {
			$this->env['user'] = array(
				'logged_in' => ! empty( $env['user']['logged_in'] ),
				'roles'     => isset( $env['user']['roles'] ) && is_array( $env['user']['roles'] ) ? array_values( $env['user']['roles'] ) : array(),
			);
		}
		if ( isset( $env['current_step'] ) ) $this->env['current_step'] = max( 1, (int) $env['current_step'] );
	}

	/** Request environment of a submission: referer query + real WP user. */
	public static function request_environment( $form ) {
		$env = array( 'query' => array(), 'user' => array( 'logged_in' => false, 'roles' => array() ), 'current_step' => self::max_step( $form ) );
		$referer = function_exists( 'wp_get_referer' ) ? wp_get_referer() : '';
		if ( is_string( $referer ) && $referer !== '' ) {
			$qs = (string) wp_parse_url( $referer, PHP_URL_QUERY );
			if ( $qs !== '' ) parse_str( $qs, $env['query'] );
		}
		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
			$env['user']['logged_in'] = true;
			$env['user']['roles']     = array_values( (array) wp_get_current_user()->roles );
		}
		return $env;
	}

	/* ------------------------------------------------------------------ */
	/* Submission filter                                                  */
	/* ------------------------------------------------------------------ */

	/**
	 * @param array $default   ['is_conditional'=>false,'submitted_values'=>rows,'logic_result'=>[]]
	 * @param array $form      Decoded form structure (stored, trusted).
	 * @param array $submitted Submitted rows (untrusted).
	 */
	public static function filter_prepare_submission( $default, $form, $submitted ) {
		if ( ! is_array( $form ) || ! is_array( $submitted ) ) return $default;
		$rules = self::field_rules( $form );
		if ( empty( $rules ) ) return $default;

		$validator = new self();
		$validator->set_environment( self::request_environment( $form ) );
		$result = $validator->evaluate_form( $form, $submitted );

		$ignored = array_flip( $result['ignored_fields'] );
		$parents = self::parent_map( $form );
		$kept    = array();
		foreach ( $submitted as $row ) {
			if ( ! is_array( $row ) ) continue;
			if ( self::row_is_ignored( $row, $ignored, $parents ) ) continue;
			$kept[] = $row;
		}

		return array(
			'is_conditional'   => true,
			'submitted_values' => $kept,
			'logic_result'     => $result,
		);
	}

	private static function row_is_ignored( $row, $ignored, $parents ) {
		foreach ( array( 'id_', 'id_ob' ) as $key ) {
			if ( ! isset( $row[ $key ] ) || ! is_scalar( $row[ $key ] ) ) continue;
			$id = (string) $row[ $key ];
			$guard = 0;
			while ( $id !== '' && $guard++ < 5 ) {
				if ( isset( $ignored[ $id ] ) ) return true;
				$id = isset( $parents[ $id ] ) ? $parents[ $id ] : '';
			}
		}
		return false;
	}

	/* ------------------------------------------------------------------ */
	/* Core evaluator                                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * Full field/step evaluation for a submission.
	 *
	 * @return array ignored_fields, hidden_fields, hidden_steps, required_fields,
	 *               optional_fields, disabled_fields, enabled_fields,
	 *               submit_blocked, block_messages, end_form, matched_rules
	 */
	public function evaluate_form( $form, $rows ) {
		$this->rows = is_array( $rows ) ? $rows : array();
		$values     = $this->build_values_map( $form, $this->rows );
		return $this->evaluate_with_values( $form, $values );
	}

	public function evaluate_with_values( $form, $values ) {
		$rules  = self::field_rules( $form );
		$fields = self::field_index( $form );
		$steps  = self::step_index( $form );

		$base = array( 'hidden' => array(), 'hidden_steps' => array(), 'required' => array(), 'disabled' => array() );
		foreach ( $fields as $id => $f ) {
			$base['hidden'][ $id ]   = false;
			$base['required'][ $id ] = self::truthy( $f['required'] ?? false );
			$base['disabled'][ $id ] = self::truthy( $f['disabled'] ?? false );
		}
		foreach ( $steps as $sid => $num ) $base['hidden_steps'][ $sid ] = false;
		$touched_required = array();
		foreach ( $rules as $rule ) {
			foreach ( $rule['actions'] as $a ) {
				$t = (string) ( $a['target'] ?? '' );
				if ( $a['type'] === 'show_field' && isset( $base['hidden'][ $t ] ) ) $base['hidden'][ $t ] = true;
				if ( $a['type'] === 'show_step' && isset( $base['hidden_steps'][ $t ] ) ) $base['hidden_steps'][ $t ] = true;
				if ( ( $a['type'] === 'set_required' || $a['type'] === 'set_optional' ) && isset( $fields[ $t ] ) ) $touched_required[ $t ] = true;
			}
		}

		$hidden_prev = self::effective_hidden( $base, $fields, $steps );
		$state       = null;
		for ( $pass = 0; $pass < self::MAX_PASSES; $pass++ ) {
			$vi = $values;
			foreach ( $hidden_prev as $hid ) {
				if ( array_key_exists( $hid, $vi ) ) $vi[ $hid ] = is_array( $vi[ $hid ] ) ? array() : '';
			}
			$state = $this->apply_rules( $form, $rules, $base, $vi );
			$hidden_now = self::effective_hidden( $state, $fields, $steps );
			if ( $hidden_now === $hidden_prev ) break;
			$hidden_prev = $hidden_now;
		}

		$hidden_set = array_flip( $hidden_prev );
		$ignored = $hidden_prev;
		$disabled_fields = array();
		$enabled_fields  = array();
		foreach ( $fields as $id => $f ) {
			if ( $state['disabled'][ $id ] && ! $base['disabled'][ $id ] ) {
				$disabled_fields[] = $id;
				if ( ! isset( $hidden_set[ $id ] ) ) $ignored[] = $id;
			}
			if ( ! $state['disabled'][ $id ] && $base['disabled'][ $id ] ) $enabled_fields[] = $id;
		}
		$required_fields = array();
		$optional_fields = array();
		foreach ( array_keys( $touched_required ) as $id ) {
			if ( isset( $hidden_set[ $id ] ) ) continue;
			if ( $state['required'][ $id ] ) $required_fields[] = $id; else $optional_fields[] = $id;
		}
		$hidden_steps = array();
		foreach ( $state['hidden_steps'] as $sid => $h ) if ( $h ) $hidden_steps[] = $sid;
		$hidden_fields = array();
		foreach ( $state['hidden'] as $fid => $h ) if ( $h ) $hidden_fields[] = $fid;

		return array(
			'ignored_fields'  => array_values( array_unique( $ignored ) ),
			'hidden_fields'   => $hidden_fields,
			'hidden_steps'    => $hidden_steps,
			'required_fields' => $required_fields,
			'optional_fields' => $optional_fields,
			'disabled_fields' => $disabled_fields,
			'enabled_fields'  => $enabled_fields,
			'submit_blocked'  => ! empty( $state['blocked'] ),
			'block_messages'  => $state['block_messages'],
			'end_form'        => $state['end_form'],
			'matched_rules'   => $state['matched'],
		);
	}

	private function apply_rules( $form, $rules, $base, $values ) {
		$state = $base;
		$state['blocked'] = false;
		$state['block_messages'] = array();
		$state['end_form'] = array();
		$state['matched'] = array();
		foreach ( $rules as $rule ) {
			if ( ! $this->evaluate_condition_group( $form, $rule['conditions'], $values, $this->rows ) ) continue;
			$state['matched'][] = (string) ( $rule['id'] ?? '' );
			foreach ( $rule['actions'] as $a ) {
				$t = (string) ( $a['target'] ?? '' );
				switch ( $a['type'] ) {
					case 'show_field':    if ( isset( $state['hidden'][ $t ] ) ) $state['hidden'][ $t ] = false; break;
					case 'hide_field':    if ( isset( $state['hidden'][ $t ] ) ) $state['hidden'][ $t ] = true; break;
					case 'show_step':     if ( isset( $state['hidden_steps'][ $t ] ) ) $state['hidden_steps'][ $t ] = false; break;
					case 'hide_step':     if ( isset( $state['hidden_steps'][ $t ] ) ) $state['hidden_steps'][ $t ] = true; break;
					case 'set_required':  if ( isset( $state['required'][ $t ] ) ) $state['required'][ $t ] = true; break;
					case 'set_optional':  if ( isset( $state['required'][ $t ] ) ) $state['required'][ $t ] = false; break;
					case 'enable_field':  if ( isset( $state['disabled'][ $t ] ) ) $state['disabled'][ $t ] = false; break;
					case 'disable_field': if ( isset( $state['disabled'][ $t ] ) ) $state['disabled'][ $t ] = true; break;
					case 'block_submit':
						$state['blocked'] = true;
						$state['block_messages'][] = array( 'rule' => (string) ( $rule['id'] ?? '' ), 'value' => is_scalar( $a['value'] ?? '' ) ? (string) ( $a['value'] ?? '' ) : '' );
						break;
					case 'end_form':
						$state['blocked'] = true;
						$state['end_form'] = array( 'message' => is_scalar( $a['value'] ?? '' ) ? (string) ( $a['value'] ?? '' ) : '' );
						break;
				}
			}
			if ( ! empty( $rule['stop_processing'] ) ) break;
		}
		return $state;
	}

	/** Sorted list of field ids hidden directly or through a hidden step. */
	private static function effective_hidden( $state, $fields, $steps ) {
		$hidden_step_nums = array();
		foreach ( $state['hidden_steps'] as $sid => $h ) {
			if ( $h && isset( $steps[ $sid ] ) ) $hidden_step_nums[ (string) $steps[ $sid ] ] = true;
		}
		$out = array();
		foreach ( $fields as $id => $f ) {
			$step = isset( $f['step'] ) && is_scalar( $f['step'] ) ? (string) $f['step'] : '';
			if ( ! empty( $state['hidden'][ $id ] ) || ( $step !== '' && isset( $hidden_step_nums[ $step ] ) ) ) $out[] = (string) $id;
		}
		sort( $out, SORT_STRING );
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Values                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * field_id => string|string[] from submitted rows. Mirrors valuesMap() in
	 * the public runtime.
	 */
	public function build_values_map( $form, $rows ) {
		$values = array();
		foreach ( (array) $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['id_'] ) || ! is_scalar( $row['id_'] ) ) continue;
			$id   = (string) $row['id_'];
			$type = strtolower( (string) ( $row['type'] ?? '' ) );
			$raw  = $row['value'] ?? '';
			$val  = is_scalar( $raw ) ? (string) $raw : '';
			$ob   = isset( $row['id_ob'] ) && is_scalar( $row['id_ob'] ) ? (string) $row['id_ob'] : '';
			if ( strpos( $type, 'checkbox' ) !== false ) {
				if ( ! isset( $values[ $id ] ) || ! is_array( $values[ $id ] ) ) $values[ $id ] = array();
				$opt = $ob !== '' ? $ob : $val;
				if ( $opt !== '' && ! in_array( $opt, $values[ $id ], true ) ) $values[ $id ][] = $opt;
				continue;
			}
			if ( $type === 'yesno' ) {
				$v = $ob !== '' ? $ob : $val;
				$lv = self::lower( $v );
				$values[ $id ] = ( $v === $id . '_1' || $lv === 'yes' || $v === '1' ) ? 'yes' : ( ( $v === $id . '_2' || $lv === 'no' || $v === '0' ) ? 'no' : '' );
				continue;
			}
			if ( strpos( $type, 'radio' ) !== false ) {
				$values[ $id ] = $ob !== '' ? $ob : $val;
				continue;
			}
			if ( strpos( $type, 'multiselect' ) !== false ) {
				$list = is_array( $raw ) ? $raw : explode( '@efb!', $val );
				$out = array();
				foreach ( $list as $item ) {
					if ( ! is_scalar( $item ) ) continue;
					$item = self::trim( (string) $item );
					if ( $item !== '' ) $out[] = $item;
				}
				$values[ $id ] = $out;
				continue;
			}
			$values[ $id ] = $val;
		}
		return $values;
	}

	/* ------------------------------------------------------------------ */
	/* Conditions                                                         */
	/* ------------------------------------------------------------------ */

	public function evaluate_condition_group( $form, $group, $values, $rows = null ) {
		if ( is_array( $rows ) ) $this->rows = $rows;
		if ( ! is_array( $group ) ) return false;
		$items = isset( $group['items'] ) && is_array( $group['items'] ) ? array_values( $group['items'] ) : array();
		if ( empty( $items ) ) return false;
		$op = strtoupper( (string) ( $group['operator'] ?? 'AND' ) ) === 'OR' ? 'OR' : 'AND';
		$result = false;
		foreach ( $items as $i => $item ) {
			$is_group = is_array( $item ) && ( ( $item['type'] ?? '' ) === 'group' || isset( $item['items'] ) );
			$m = $is_group ? $this->evaluate_condition_group( $form, $item, $values ) : $this->evaluate_condition( $form, $item, $values );
			if ( $i === 0 ) { $result = $m; continue; }
			$conn = is_array( $item ) && isset( $item['connector'] ) && strtoupper( (string) $item['connector'] ) !== ''
				? ( strtoupper( (string) $item['connector'] ) === 'OR' ? 'OR' : 'AND' )
				: $op;
			$result = $conn === 'OR' ? ( $result || $m ) : ( $result && $m );
		}
		return ! empty( $group['negate'] ) ? ! $result : $result;
	}

	public function evaluate_condition( $form, $c, $values ) {
		if ( ! is_array( $c ) ) return false;
		$source   = (string) ( $c['source'] ?? 'field' );
		$field_id = (string) ( $c['field_id'] ?? '' );
		$compare  = (string) ( $c['compare'] ?? 'is' );
		$expected = $c['value'] ?? '';
		if ( $field_id === '' && $source !== 'current_step' ) return false;

		$spellings = null;
		if ( $source === 'query_param' ) {
			$param = (string) ( $c['param'] ?? $field_id );
			$q = $this->env['query'][ $param ] ?? '';
			$value = is_array( $q ) ? implode( ',', array_map( 'strval', array_filter( $q, 'is_scalar' ) ) ) : (string) $q;
		} elseif ( $source === 'user' ) {
			if ( $field_id === 'role' ) {
				$value = array_map( 'strval', $this->env['user']['roles'] );
			} else {
				$value = ! empty( $this->env['user']['logged_in'] ) ? 'yes' : 'no';
			}
		} elseif ( $source === 'current_step' ) {
			$value = (string) $this->env['current_step'];
		} else {
			$value = array_key_exists( $field_id, $values ) ? $values[ $field_id ] : '';
			$spellings = self::option_spellings( $form, $field_id, $expected );
		}
		return self::compare( $value, $expected, $compare, $spellings, $field_id, $this->rows );
	}

	/**
	 * Pure comparison. Mirrors compare() in the public runtime.
	 *
	 * @param string|array $value
	 * @param string|array $expected
	 * @param array|null   $spellings Lowercase spellings of the expected option, or null.
	 */
	public static function compare( $value, $expected, $compare, $spellings, $field_id = '', $rows = array() ) {
		$exp_s = is_array( $expected ) ? implode( ',', array_map( 'strval', $expected ) ) : (string) $expected;
		$exp_t = self::trim( $exp_s );
		$exp_l = self::lower( $exp_t );

		if ( $compare === 'is_paid' || $compare === 'is_not_paid' ) {
			$paid = self::payment_state( $rows, $field_id, 'paid' );
			return $compare === 'is_paid' ? $paid : ! $paid;
		}
		if ( $compare === 'amount_eq' || $compare === 'amount_gt' || $compare === 'amount_lt' ) {
			$amount = self::payment_state( $rows, $field_id, 'amount' );
			if ( $amount === null || ! self::is_num( $exp_t ) ) return false;
			if ( $compare === 'amount_eq' ) return abs( $amount - (float) $exp_t ) < 0.00001;
			return $compare === 'amount_gt' ? $amount > (float) $exp_t : $amount < (float) $exp_t;
		}

		if ( is_array( $value ) ) {
			$wanted = $spellings === null ? array( $exp_l ) : $spellings;
			$present = false;
			$partial = false;
			foreach ( $value as $entry ) {
				$e = self::lower( self::trim( (string) $entry ) );
				if ( in_array( $e, $wanted, true ) ) $present = true;
				if ( strpos( $e, $exp_l ) !== false ) $partial = true;
			}
			switch ( $compare ) {
				case 'is':           return $present;
				case 'is_not':       return ! $present;
				case 'contains':     return $present || $partial;
				case 'not_contains': return ! ( $present || $partial );
				case 'is_empty':     return count( $value ) === 0;
				case 'is_not_empty': return count( $value ) > 0;
			}
			$value = implode( ' ', array_map( 'strval', $value ) );
		}

		$v   = self::trim( (string) $value );
		$v_l = self::lower( $v );

		if ( $spellings !== null && ( $compare === 'is' || $compare === 'is_not' ) ) {
			$hit = in_array( $v_l, $spellings, true );
			return $compare === 'is' ? $hit : ! $hit;
		}

		switch ( $compare ) {
			case 'is':           return $v_l === $exp_l;
			case 'is_not':       return $v_l !== $exp_l;
			case 'contains':     return strpos( $v_l, $exp_l ) !== false;
			case 'not_contains': return strpos( $v_l, $exp_l ) === false;
			case 'starts_with':  return $exp_l === '' || strpos( $v_l, $exp_l ) === 0;
			case 'ends_with':
				$len = strlen( $exp_l );
				return $len === 0 || ( strlen( $v_l ) >= $len && substr( $v_l, -$len ) === $exp_l );
			case 'gt':  return self::is_num( $v ) && self::is_num( $exp_t ) && (float) $v > (float) $exp_t;
			case 'gte': return self::is_num( $v ) && self::is_num( $exp_t ) && (float) $v >= (float) $exp_t;
			case 'lt':  return self::is_num( $v ) && self::is_num( $exp_t ) && (float) $v < (float) $exp_t;
			case 'lte': return self::is_num( $v ) && self::is_num( $exp_t ) && (float) $v <= (float) $exp_t;
			case 'between':
			case 'not_between':
				$range = self::range( $expected );
				if ( count( $range ) < 2 || ! self::is_num( $v ) || ! self::is_num( $range[0] ) || ! self::is_num( $range[1] ) ) return false;
				$inside = (float) $v >= (float) $range[0] && (float) $v <= (float) $range[1];
				return $compare === 'between' ? $inside : ! $inside;
			case 'is_empty':     return $v === '';
			case 'is_not_empty': return $v !== '';
			case 'date_before':
			case 'date_after':
				$a = self::date_key( $v );
				$b = self::date_key( $exp_t );
				if ( $a === null || $b === null ) return false;
				return $compare === 'date_before' ? strcmp( $a, $b ) < 0 : strcmp( $a, $b ) > 0;
			case 'date_between':
				$range = self::range( $expected );
				if ( count( $range ) < 2 ) return false;
				$a = self::date_key( $v );
				$f = self::date_key( $range[0] );
				$t = self::date_key( $range[1] );
				if ( $a === null || $f === null || $t === null ) return false;
				return strcmp( $a, $f ) >= 0 && strcmp( $a, $t ) <= 0;
		}
		return false;
	}

	/* ------------------------------------------------------------------ */
	/* Helpers (each mirrored in JS)                                      */
	/* ------------------------------------------------------------------ */

	/** PHP trim() character set; JS uses the same set. */
	public static function trim( $s ) {
		return trim( (string) $s, " \t\n\r\0\x0B" );
	}

	public static function lower( $s ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $s, 'UTF-8' ) : strtolower( (string) $s );
	}

	/** Same grammar as the JS NUM_RE: optional sign, digits/decimal, optional exponent. */
	public static function is_num( $s ) {
		return is_string( $s ) || is_numeric( $s )
			? (bool) preg_match( '/^[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?$/', (string) $s )
			: false;
	}

	private static function range( $expected ) {
		$list = is_array( $expected ) ? array_values( $expected ) : preg_split( '/\s*,\s*/', self::trim( (string) $expected ) );
		$out = array();
		foreach ( $list as $item ) if ( is_scalar( $item ) ) $out[] = self::trim( (string) $item );
		return $out;
	}

	/** 'YYYY-MM-DD' or 'YYYY-MM-DD HH:MM[:SS]' / 'T' → sortable key, else null. */
	public static function date_key( $s ) {
		$s = self::trim( (string) $s );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/', $s, $m ) ) return null;
		return $m[1] . $m[2] . $m[3] . ( isset( $m[4] ) && $m[4] !== '' ? $m[4] : '00' ) . ( isset( $m[5] ) && $m[5] !== '' ? $m[5] : '00' ) . ( isset( $m[6] ) && $m[6] !== '' ? $m[6] : '00' );
	}

	public static function truthy( $v ) {
		return $v === true || $v === 1 || $v === '1' || $v === 'true';
	}

	private static function rule_enabled( $rule ) {
		if ( ! array_key_exists( 'enabled', $rule ) ) return true;
		$e = $rule['enabled'];
		return ! ( $e === false || $e === 0 || $e === '0' || $e === '' || $e === 'false' || $e === null );
	}

	/** Enabled field/step rules with conditions, ascending priority, stable. */
	public static function field_rules( $form ) {
		if ( ! is_array( $form ) || ! isset( $form[0]['logic_rules'] ) || ! is_array( $form[0]['logic_rules'] ) ) return array();
		$list = array();
		$pos = 0;
		foreach ( $form[0]['logic_rules'] as $rule ) {
			$pos++;
			if ( ! is_array( $rule ) || ! self::rule_enabled( $rule ) ) continue;
			$scope = isset( $rule['scope'] ) ? (string) $rule['scope'] : 'field';
			if ( $scope !== 'field' && $scope !== 'step' && $scope !== '' ) continue;
			if ( empty( $rule['conditions']['items'] ) || ! is_array( $rule['conditions']['items'] ) ) continue;
			$actions = array();
			foreach ( ( isset( $rule['actions'] ) && is_array( $rule['actions'] ) ? $rule['actions'] : array() ) as $a ) {
				if ( is_array( $a ) && isset( $a['type'] ) && is_string( $a['type'] ) ) $actions[] = $a;
			}
			$rule['actions']   = $actions;
			$rule['_priority'] = isset( $rule['priority'] ) && is_numeric( $rule['priority'] ) ? (int) $rule['priority'] : 10;
			$rule['_pos']      = $pos;
			$list[] = $rule;
		}
		usort( $list, function ( $a, $b ) {
			if ( $a['_priority'] === $b['_priority'] ) return $a['_pos'] - $b['_pos'];
			return $a['_priority'] - $b['_priority'];
		} );
		return $list;
	}

	/** id => row for every rule-addressable field (not form/step/option/...). */
	private static function field_index( $form ) {
		$out = array();
		foreach ( $form as $i => $row ) {
			if ( $i === 0 || ! is_array( $row ) || empty( $row['id_'] ) || ! is_scalar( $row['id_'] ) ) continue;
			if ( in_array( strtolower( (string) ( $row['type'] ?? '' ) ), self::$non_field_types, true ) ) continue;
			$out[ (string) $row['id_'] ] = $row;
		}
		return $out;
	}

	/** step row id => step number. */
	private static function step_index( $form ) {
		$out = array();
		foreach ( $form as $row ) {
			if ( ! is_array( $row ) || empty( $row['id_'] ) || strtolower( (string) ( $row['type'] ?? '' ) ) !== 'step' ) continue;
			$out[ (string) $row['id_'] ] = isset( $row['step'] ) && is_scalar( $row['step'] ) ? (string) $row['step'] : '';
		}
		return $out;
	}

	private static function max_step( $form ) {
		$max = 1;
		foreach ( (array) $form as $row ) {
			if ( is_array( $row ) && strtolower( (string) ( $row['type'] ?? '' ) ) === 'step' && isset( $row['step'] ) && is_numeric( $row['step'] ) ) $max = max( $max, (int) $row['step'] );
		}
		return $max;
	}

	private static function parent_map( $form ) {
		$out = array();
		foreach ( (array) $form as $row ) {
			if ( is_array( $row ) && ! empty( $row['id_'] ) && isset( $row['parent'] ) && is_scalar( $row['parent'] ) && is_scalar( $row['id_'] ) ) $out[ (string) $row['id_'] ] = (string) $row['parent'];
		}
		return $out;
	}

	/**
	 * Lowercase spellings meaning "this option" for a choice field (option id
	 * and visible text resolve to each other); null for fields without options.
	 */
	public static function option_spellings( $form, $field_id, $expected ) {
		if ( is_array( $expected ) ) $expected = implode( ',', array_map( 'strval', $expected ) );
		$expected = (string) $expected;
		$options = array();
		foreach ( (array) $form as $row ) {
			if ( ! is_array( $row ) || strtolower( (string) ( $row['type'] ?? '' ) ) !== 'option' ) continue;
			if ( (string) ( $row['parent'] ?? '' ) !== (string) $field_id ) continue;
			$options[] = array( (string) ( $row['id_'] ?? '' ), is_scalar( $row['value'] ?? '' ) ? (string) ( $row['value'] ?? '' ) : '' );
		}
		if ( empty( $options ) ) return null;
		$out = array();
		$add = function ( $c ) use ( &$out ) {
			$t = self::lower( self::trim( (string) $c ) );
			if ( $t !== '' && ! in_array( $t, $out, true ) ) $out[] = $t;
		};
		$add( $expected );
		foreach ( $options as $o ) {
			if ( $o[0] === $expected ) $add( $o[1] );
			if ( $o[1] === $expected ) $add( $o[0] );
		}
		return $out;
	}

	private static function payment_state( $rows, $field_id, $want ) {
		$paid_states = array( 'paid', 'succeeded', 'success', 'completed', 'complete', 'approved', 'captured', 'authorized' );
		$paid = false;
		$amount = null;
		foreach ( (array) $rows as $row ) {
			if ( ! is_array( $row ) ) continue;
			$type = strtolower( (string) ( $row['type'] ?? '' ) );
			$is_target = (string) ( $row['id_'] ?? '' ) === (string) $field_id;
			$is_pay = in_array( $type, self::$payment_types, true );
			if ( ! $is_target && ! $is_pay ) continue;
			foreach ( array( 'payment_status', 'status', 'state' ) as $k ) {
				if ( isset( $row[ $k ] ) && is_scalar( $row[ $k ] ) && in_array( strtolower( (string) $row[ $k ] ), $paid_states, true ) ) $paid = true;
			}
			if ( ! empty( $row['paymentIntent'] ) || ! empty( $row['transaction_id'] ) || ! empty( $row['refId'] ) || ! empty( $row['authority'] ) ) $paid = true;
			$keys = $is_pay ? array( 'paymentAmount', 'paid_amount', 'total', 'price', 'value', 'amount' ) : array( 'paymentAmount', 'paid_amount', 'total', 'price', 'amount' );
			foreach ( $keys as $k ) {
				if ( isset( $row[ $k ] ) && is_scalar( $row[ $k ] ) && self::is_num( self::trim( (string) $row[ $k ] ) ) ) { $amount = (float) $row[ $k ]; break; }
			}
		}
		return $want === 'paid' ? $paid : $amount;
	}

	/* ------------------------------------------------------------------ */
	/* i18n for the runtimes                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * The core enqueues the public runtime only when the stored form JSON
	 * contains '"logic_rules"' unescaped, but the structure is stored with
	 * escaped quotes (\"logic_rules\"), so forms that only carry logic_rules
	 * never get the runtime (class-Emsfb-public.php, $_efb_form_has_logic).
	 * Recover here: if the core runtime is on the page and its localized form
	 * data carries logic rules, enqueue ours before the footer prints.
	 */
	public static function maybe_enqueue_public() {
		if ( is_admin() || ! function_exists( 'wp_script_is' ) ) return;
		if ( wp_script_is( 'efb-conditional-logic-public', 'enqueued' ) ) return;
		if ( ! wp_script_is( 'Emsfb-core_js', 'enqueued' ) ) return;
		$data = wp_scripts()->get_data( 'Emsfb-core_js', 'data' );
		if ( ! is_string( $data ) || strpos( $data, 'logic_rules' ) === false ) return;
		wp_enqueue_script(
			'efb-conditional-logic-public',
			EMSFB_PLUGIN_URL . 'vendor/logic/logic/assets/public/js/conditional-logic-efb.js',
			array( 'Emsfb-core_js' ),
			defined( 'EMSFB_PLUGIN_VERSION' ) ? EMSFB_PLUGIN_VERSION : self::VERSION,
			true
		);
	}

	public static function localize_public() {
		if ( ! function_exists( 'wp_script_is' ) || ! wp_script_is( 'efb-conditional-logic-public', 'enqueued' ) ) return;
		wp_localize_script( 'efb-conditional-logic-public', 'efb_logic_i18n', self::public_strings() );
	}

	public static function localize_admin() {
		if ( ! function_exists( 'wp_script_is' ) ) return;
		if ( wp_script_is( 'efb-conditional-logic-preview-js', 'enqueued' ) ) {
			wp_localize_script( 'efb-conditional-logic-preview-js', 'efb_logic_i18n', self::public_strings() );
		}
		if ( wp_script_is( 'efb-conditional-logic-js', 'enqueued' ) ) {
			wp_localize_script( 'efb-conditional-logic-js', 'efb_logic_admin_i18n', self::admin_strings() );
		}
	}

	public static function public_strings() {
		return array(
			'submitBlocked' => __( 'Submission is not allowed for the current answers.', 'flx-forms' ),
		);
	}

	public static function admin_strings() {
		return array(
			'title'          => __( 'Conditional Logic', 'flx-forms' ),
			'intro'          => __( 'Show or hide fields and steps based on the answers to other fields. Rules run from the lowest priority number to the highest; when two rules change the same field, the later one wins. A field targeted by a "Show field" action stays hidden until its rule matches. Hidden fields are skipped by validation and their values are discarded on the server.', 'flx-forms' ),
			'noRules'        => __( 'No rules yet. Click "Add rule" to create your first IF / THEN rule.', 'flx-forms' ),
			'noFields'       => __( 'Add some fields to the form first; rules read and change form fields.', 'flx-forms' ),
			'addRule'        => __( 'Add rule', 'flx-forms' ),
			'rule'           => __( 'Rule', 'flx-forms' ),
			'ruleName'       => __( 'Rule name', 'flx-forms' ),
			'enabled'        => __( 'Enabled', 'flx-forms' ),
			'priority'       => __( 'Priority', 'flx-forms' ),
			'stopProcessing' => __( 'Stop after this rule matches', 'flx-forms' ),
			'duplicate'      => __( 'Duplicate', 'flx-forms' ),
			'delete'         => __( 'Delete', 'flx-forms' ),
			'if'             => __( 'IF', 'flx-forms' ),
			'then'           => __( 'THEN', 'flx-forms' ),
			'matchAll'       => __( 'All of these are true (AND)', 'flx-forms' ),
			'matchAny'       => __( 'Any of these is true (OR)', 'flx-forms' ),
			'group'          => __( 'Group', 'flx-forms' ),
			'addCondition'   => __( 'Add condition', 'flx-forms' ),
			'addGroup'       => __( 'Add group', 'flx-forms' ),
			'addAction'      => __( 'Add action', 'flx-forms' ),
			'remove'         => __( 'Remove', 'flx-forms' ),
			'selectField'    => __( 'Select a field', 'flx-forms' ),
			'selectStep'     => __( 'Select a step', 'flx-forms' ),
			'selectValue'    => __( 'Select a value', 'flx-forms' ),
			'value'          => __( 'Value', 'flx-forms' ),
			'rangeHint'      => __( 'min,max', 'flx-forms' ),
			'dateHint'       => __( 'YYYY-MM-DD', 'flx-forms' ),
			'message'        => __( 'Message shown to the visitor', 'flx-forms' ),
			'step'           => __( 'Step', 'flx-forms' ),
			'yes'            => __( 'Yes', 'flx-forms' ),
			'no'             => __( 'No', 'flx-forms' ),
			'srcField'       => __( 'Field', 'flx-forms' ),
			'srcQuery'       => __( 'URL parameter', 'flx-forms' ),
			'srcLoggedIn'    => __( 'Visitor is logged in', 'flx-forms' ),
			'srcRole'        => __( 'Visitor role', 'flx-forms' ),
			'paramName'      => __( 'Parameter name', 'flx-forms' ),
			'op_is'          => __( 'is', 'flx-forms' ),
			'op_is_not'      => __( 'is not', 'flx-forms' ),
			'op_contains'    => __( 'contains', 'flx-forms' ),
			'op_not_contains'=> __( 'does not contain', 'flx-forms' ),
			'op_starts_with' => __( 'starts with', 'flx-forms' ),
			'op_ends_with'   => __( 'ends with', 'flx-forms' ),
			'op_is_empty'    => __( 'is empty', 'flx-forms' ),
			'op_is_not_empty'=> __( 'is not empty', 'flx-forms' ),
			'op_gt'          => __( 'is greater than', 'flx-forms' ),
			'op_gte'         => __( 'is greater than or equal to', 'flx-forms' ),
			'op_lt'          => __( 'is less than', 'flx-forms' ),
			'op_lte'         => __( 'is less than or equal to', 'flx-forms' ),
			'op_between'     => __( 'is between', 'flx-forms' ),
			'op_not_between' => __( 'is not between', 'flx-forms' ),
			'op_date_before' => __( 'is before', 'flx-forms' ),
			'op_date_after'  => __( 'is after', 'flx-forms' ),
			'op_date_between'=> __( 'is between dates', 'flx-forms' ),
			'act_show_field'    => __( 'Show field', 'flx-forms' ),
			'act_hide_field'    => __( 'Hide field', 'flx-forms' ),
			'act_show_step'     => __( 'Show step', 'flx-forms' ),
			'act_hide_step'     => __( 'Hide step', 'flx-forms' ),
			'act_set_required'  => __( 'Make field required', 'flx-forms' ),
			'act_set_optional'  => __( 'Make field optional', 'flx-forms' ),
			'act_enable_field'  => __( 'Enable field', 'flx-forms' ),
			'act_disable_field' => __( 'Disable field', 'flx-forms' ),
			'act_block_submit'  => __( 'Block submission', 'flx-forms' ),
			'apply'          => __( 'Apply', 'flx-forms' ),
			'cancel'         => __( 'Cancel', 'flx-forms' ),
			'close'          => __( 'Close', 'flx-forms' ),
			'applied'        => __( 'Conditional logic rules updated. Save the form to keep them.', 'flx-forms' ),
			'droppedIncomplete' => __( 'Incomplete conditions or actions were removed.', 'flx-forms' ),
			'selfReference'  => __( 'A rule hides or shows a field it also reads. That field can never become visible again once hidden.', 'flx-forms' ),
			'unknownField'   => __( '(deleted field)', 'flx-forms' ),
		);
	}
}

Emsfb_Logic_Validator::register();

endif;
