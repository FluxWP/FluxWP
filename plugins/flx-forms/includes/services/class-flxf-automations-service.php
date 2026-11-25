<?php
/**
 * Forms › Automations for the Forms screen (GraphQL): the flow list, the editor data, the run log and
 * the writes the classic AJAX handlers did (save, on/off, delete, duplicate, default flow, donation form,
 * clear log). Flows themselves stay in FLXF_Automations (private post type, JSON config).
 *
 * @package FLX_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Automation reads and writes behind flxFormsAutomation* operations.
 */
final class FLXF_Automations_Service {

	/**
	 * Automations are loaded and this user may manage them.
	 */
	public static function can(): bool {
		return class_exists( 'FLXF_Automations' ) && FLXF_Automations::can();
	}

	/**
	 * The flow list, the certificate state and the ready-made donation flow.
	 *
	 * @return array<string,mixed>
	 */
	public static function overview(): array {
		FLXF_Automations::maybe_seed();
		$flows = array();
		foreach ( FLXF_Automations::get_flows() as $f ) {
			$flows[] = self::summary( $f );
		}
		$form = self::num( FLXF_Automations::find_donation_form() );
		return array(
			'flows'             => $flows,
			'certAvailable'     => (bool) FLXF_Automations::certificates_available(),
			'donationFormId'    => $form,
			'donationFormTitle' => $form ? self::str( FLXF_Automations::form_title( $form ) ) : '',
			'runCount'          => count( (array) FLXF_Automations::get_runs() ),
			'maxRuns'           => FLXF_Automations::MAX_RUNS,
		);
	}

	/**
	 * One row of the list.
	 *
	 * @param array<mixed> $f Flow.
	 * @return array<string,mixed>
	 */
	private static function summary( array $f ): array {
		$trigger = is_array( $f['trigger'] ?? null ) ? $f['trigger'] : array();
		$steps   = array();
		foreach ( (array) ( $f['actions'] ?? array() ) as $a ) {
			if ( is_array( $a ) ) {
				$steps[] = self::str( $a['type'] ?? '' );
			}
		}
		$last = self::num( $f['last_run'] ?? 0 );
		return array(
			'id'          => self::num( $f['id'] ?? 0 ),
			'title'       => self::str( $f['title'] ?? '' ),
			'enabled'     => ! empty( $f['enabled'] ),
			'triggerType' => self::str( $trigger['type'] ?? 'form_submitted' ),
			'formId'      => self::num( $trigger['form_id'] ?? 0 ),
			'formTitle'   => self::str( FLXF_Automations::form_title( self::num( $trigger['form_id'] ?? 0 ) ) ),
			'steps'       => $steps,
			'lastRun'     => $last ? flx_gql_datetime( $last ) : null,
			'runs'        => self::num( $f['runs'] ?? 0 ),
		);
	}

	/**
	 * The run log, newest first.
	 *
	 * @return list<array<string,mixed>>
	 */
	public static function runs(): array {
		$out = array();
		foreach ( (array) FLXF_Automations::get_runs() as $i => $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$steps = array();
			foreach ( (array) ( $r['steps'] ?? array() ) as $s ) {
				if ( is_array( $s ) ) {
					$steps[] = array(
						'type'    => self::str( $s['type'] ?? '' ),
						'status'  => self::str( $s['status'] ?? '' ),
						'message' => self::str( $s['message'] ?? '' ),
					);
				}
			}
			$time  = self::num( $r['time'] ?? 0 );
			$out[] = array(
				'id'        => '' !== self::str( $r['id'] ?? '' ) ? self::str( $r['id'] ) : 'run-' . self::str( $i ),
				'time'      => $time ? flx_gql_datetime( $time ) : null,
				'flowId'    => self::num( $r['flow_id'] ?? 0 ),
				'flowTitle' => self::str( $r['flow_title'] ?? '' ),
				'formTitle' => self::str( $r['form_title'] ?? '' ),
				'track'     => self::str( $r['track'] ?? '' ),
				'status'    => '' !== self::str( $r['status'] ?? '' ) ? self::str( $r['status'] ) : 'running',
				'steps'     => $steps,
			);
		}
		return $out;
	}

	/**
	 * What the flow editor needs: the flow (a blank one for id 0) and the lists its pickers offer.
	 *
	 * @return array<string,mixed>
	 */
	public static function editor( int $id ): array {
		$flow = $id > 0 ? FLXF_Automations::get_flow( $id ) : self::blank();
		$forms = array();
		foreach ( (array) FLXF_Automations::forms() as $f ) {
			if ( ! is_array( $f ) ) {
				continue;
			}
			$fields     = is_array( $f['fields'] ?? null ) ? $f['fields'] : array();
			$f['guess'] = FLXF_Automations::guess_mapping( $fields );
			$forms[]    = $f;
		}
		$channels = function_exists( 'flxs_channels' ) ? flxs_channels() : array(
			'email'    => __( 'Email', 'flx-forms' ),
			'sms'      => 'SMS',
			'whatsapp' => 'WhatsApp',
		);
		return array(
			'flow'          => is_array( $flow ) ? self::flow_out( $flow ) : null,
			'forms'         => $forms,
			'templates'     => FLXF_Automations::cert_templates(),
			'events'        => FLXF_Automations::cert_events(),
			'providers'     => (object) FLXF_Automations::cert_providers(),
			'channels'      => (object) ( is_array( $channels ) ? $channels : array() ),
			'certAvailable' => (bool) FLXF_Automations::certificates_available(),
			'saplingPrice'  => (float) FLXF_Automations::sapling_unit_price(),
			'adminEmail'    => self::str( get_option( 'admin_email' ) ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function blank(): array {
		return array(
			'id'         => 0,
			'title'      => '',
			'enabled'    => true,
			'trigger'    => array(
				'type'    => 'form_submitted',
				'form_id' => 0,
			),
			'match'      => 'all',
			'conditions' => array(),
			'actions'    => array(),
		);
	}

	/**
	 * The flow as the editor reads it (open maps stay objects in JSON).
	 *
	 * @param array<mixed> $flow Flow.
	 * @return array<string,mixed>
	 */
	private static function flow_out( array $flow ): array {
		$actions = array();
		foreach ( (array) ( $flow['actions'] ?? array() ) as $a ) {
			if ( ! is_array( $a ) ) {
				continue;
			}
			foreach ( array( 'vars', 'providers' ) as $k ) {
				if ( isset( $a[ $k ] ) && is_array( $a[ $k ] ) ) {
					$a[ $k ] = (object) $a[ $k ];
				}
			}
			$actions[] = $a;
		}
		$flow['actions'] = $actions;
		unset( $flow['modified'], $flow['last_run'], $flow['runs'] );
		$out = array();
		foreach ( $flow as $k => $v ) {
			$out[ self::str( $k ) ] = $v;
		}
		return $out;
	}

	/**
	 * Saves a flow from the editor (id 0 creates one).
	 *
	 * @param mixed $flow Flow JSON from the editor.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function save( $flow ): array|WP_Error {
		$data = self::arr( $flow );
		if ( ! $data ) {
			return self::invalid( 'flow', __( 'Invalid data.', 'flx-forms' ) );
		}
		$res = FLXF_Automations::save_flow( $data, absint( self::num( $data['id'] ?? 0 ) ) );
		if ( $res instanceof WP_Error ) {
			return $res;
		}
		$saved = FLXF_Automations::get_flow( self::num( $res ) );
		return is_array( $saved ) ? self::flow_out( $saved ) : self::invalid( 'flow', __( 'Automation not found.', 'flx-forms' ) );
	}

	/**
	 * Turns a flow on or off.
	 */
	public static function toggle( int $id, bool $enabled ): bool|WP_Error {
		$flow = FLXF_Automations::get_flow( $id );
		if ( ! is_array( $flow ) ) {
			return self::invalid( 'id', __( 'Automation not found.', 'flx-forms' ) );
		}
		$flow['enabled'] = $enabled;
		$res             = FLXF_Automations::save_flow( $flow, $id );
		return $res instanceof WP_Error ? $res : $enabled;
	}

	/**
	 * Deletes a flow.
	 */
	public static function delete( int $id ): bool|WP_Error {
		return FLXF_Automations::delete_flow( $id ) ? true : self::invalid( 'id', __( 'Automation not found.', 'flx-forms' ) );
	}

	/**
	 * Copies a flow (switched off). Returns the new id.
	 */
	public static function duplicate( int $id ): int|WP_Error {
		$flow = FLXF_Automations::get_flow( $id );
		if ( ! is_array( $flow ) ) {
			return self::invalid( 'id', __( 'Automation not found.', 'flx-forms' ) );
		}
		/* translators: %s: automation name */
		$flow['title']   = sprintf( __( '%s (copy)', 'flx-forms' ), self::str( $flow['title'] ?? '' ) );
		$flow['enabled'] = false;
		$res             = FLXF_Automations::save_flow( $flow, 0 );
		return $res instanceof WP_Error ? $res : self::num( $res );
	}

	/**
	 * Creates the ready-made "sapling donation → certificate" flow. Returns its id.
	 */
	public static function create_default(): int|WP_Error {
		$res = FLXF_Automations::create_default_flow();
		return $res instanceof WP_Error ? $res : self::num( $res );
	}

	/**
	 * Creates the donation form (once) and points the unattached default flows at it.
	 *
	 * @return array{formId:int,shortcode:string}|WP_Error
	 */
	public static function donation_form(): array|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'flxf_forbidden', __( 'Sorry, you are not allowed to do that.', 'flx-forms' ), array( 'status' => 403 ) );
		}
		$form_id = self::num( FLXF_Automations::find_donation_form() );
		if ( ! $form_id ) {
			$form_id = self::num( FLXF_Automations::create_donation_form() );
		}
		if ( ! $form_id ) {
			return self::invalid( 'form', __( 'The form could not be created.', 'flx-forms' ) );
		}
		$fresh = FLXF_Automations::default_flow_data( $form_id );
		$first = is_array( $fresh['actions'][0] ?? null ) ? $fresh['actions'][0] : array();
		foreach ( FLXF_Automations::get_flows() as $f ) {
			$trigger = is_array( $f['trigger'] ?? null ) ? $f['trigger'] : array();
			$actions = is_array( $f['actions'] ?? null ) ? $f['actions'] : array();
			$a0      = is_array( $actions[0] ?? null ) ? $actions[0] : array();
			if ( 'payment_completed' !== ( $trigger['type'] ?? '' ) || 0 !== self::num( $trigger['form_id'] ?? 0 ) || ! empty( $f['enabled'] ) || 'create_certificate' !== ( $a0['type'] ?? '' ) ) {
				continue;
			}
			$trigger['form_id'] = $form_id;
			$actions[0]         = array_merge( $a0, array_intersect_key( $first, array_flip( array( 'name_field', 'surname_field', 'phone_field', 'email_field' ) ) ) );
			$f['trigger']       = $trigger;
			$f['actions']       = $actions;
			$f['enabled']       = FLXF_Automations::certificates_available();
			FLXF_Automations::save_flow( $f, self::num( $f['id'] ?? 0 ) );
		}
		return array(
			'formId'    => $form_id,
			'shortcode' => '[EMS_Form_Builder id=' . $form_id . ']',
		);
	}

	/**
	 * Empties the run log.
	 */
	public static function clear_runs(): bool {
		FLXF_Automations::clear_runs();
		return true;
	}

	/* ------------------------------------------------------------ Helpers */

	/**
	 * JSON value (object or array) as a PHP array.
	 *
	 * @param mixed $v Value.
	 * @return array<mixed>
	 */
	private static function arr( $v ): array {
		if ( is_string( $v ) ) {
			$v = json_decode( $v, true );
		}
		if ( is_object( $v ) ) {
			$v = json_decode( (string) wp_json_encode( $v ), true );
		}
		return is_array( $v ) ? $v : array();
	}

	private static function invalid( string $field, string $message ): WP_Error {
		return new WP_Error(
			'flxf_invalid',
			$message,
			array(
				'graphql_code' => 'INVALID_INPUT',
				'field'        => $field,
				'status'       => 400,
			)
		);
	}

	private static function str( mixed $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	private static function num( mixed $value ): int {
		if ( is_int( $value ) ) {
			return $value;
		}
		if ( is_float( $value ) || ( is_string( $value ) && is_numeric( $value ) ) ) {
			return (int) $value;
		}
		return 0;
	}
}
