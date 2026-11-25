<?php
/**
 * Public form read. Returns the drawing allowlist only. The stored form_structer
 * JSON (notification recipients, webhooks, secrets, booking counters) never leaves
 * this class.
 *
 * @package FLX_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads one form for anonymous visitors and the block editor.
 *
 * @phpstan-type FlxfFormOption array{value:string,label:string,price:float|null,image:string}
 * @phpstan-type FlxfFormField array{id:string,type:string,label:string,required:bool,placeholder:string,options:list<FlxfFormOption>,help:string,step:int,width:int,defaultValue:string,min:string,max:string,rows:list<FlxfFormOption>,settings:array<string,string|int|float|bool>}
 * @phpstan-type FlxfFormStep array{number:int,title:string,help:string}
 * @phpstan-type FlxfPublicForm array{id:string,title:string,formType:string,steps:int,buttonLabel:string,successTitle:string,successMessage:string,fields:list<FlxfFormField>,stepList:list<FlxfFormStep>,currency:string}
 * @phpstan-type FlxfLoadedForm array{row:array<string,string>,structure:array<int,array<string,mixed>>}
 */
class FLXF_Form_Service {

	/**
	 * Keys on the form head that a public response must not contain.
	 *
	 * @var list<string>
	 */
	public const FORBIDDEN_HEAD = array(
		'email',
		'email_to',
		'sendEmail',
		'email_temp',
		'notification_rules',
		'webhook_rules',
		'confirmation_rules',
		'rePage',
		'smsnoti',
		'sms_msg_new_noti',
		'sms_msg_responsed_noti',
		'sms_msg_recived_usr',
		'sms_admins_phone_no',
		'telegramnoti',
		'telegram_msg_new_noti',
		'telegram_msg_responsed_noti',
		'telegram_msg_recived_usr',
		'telegram_bot_token',
		'telegram_admin_chat_ids',
		'logic_rules',
	);

	/**
	 * Option and field keys that stay on the server (booking counters).
	 *
	 * @var list<string>
	 */
	/**
	 * Field types whose `milen` / `mlen` are a length or a value limit the visitor sees (on choice fields they are
	 * booking counters and stay on the server).
	 *
	 * @var list<string>
	 */
	public const LIMIT_TYPES = array( 'text', 'textarea', 'tel', 'password', 'number', 'range', 'date' );

	/**
	 * Type-specific keys a front end needs to draw a field (FlxFormField.settings).
	 *
	 * @var list<string>
	 */
	public const SETTING_KEYS = array(
		'op_style',
		'minSelect',
		'maxSelect',
		'file',
		'file_ctype',
		'max_fsize',
		'button_1_text',
		'button_2_text',
		'on',
		'off',
		'pholder_chl_value',
		'max_duration',
		'rec_countdown',
		'rec_download',
		'record_quality',
		'country',
		'statePov',
		'href',
		'el_text_size',
		'lat',
		'lng',
		'zoom',
		'mark',
	);

	public const FORBIDDEN_FIELD = array(
		'registered_count',
		'mlen',
		'dateExp',
	);

	/**
	 * One public form, or null when it is missing or private to this visitor.
	 *
	 * @param int $id Form id.
	 * @return FlxfPublicForm|null
	 */
	public static function public_form( int $id ): ?array {
		$row = self::row( $id );
		if ( null === $row ) {
			return null;
		}
		$structure = self::structure( (string) $row['form_structer'] );
		if ( self::is_private( $structure ) && ! is_user_logged_in() ) {
			return null;
		}
		return self::allow( $row, $structure );
	}

	/**
	 * Stored row plus the decoded structure, for the submission service.
	 *
	 * @param int $id Form id.
	 * @return FlxfLoadedForm|null
	 */
	public static function load( int $id ): ?array {
		$row = self::row( $id );
		if ( null === $row ) {
			return null;
		}
		return array(
			'row'       => $row,
			'structure' => self::structure( (string) $row['form_structer'] ),
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $structure Decoded form_structer.
	 */
	public static function is_private( array $structure ): bool {
		$state = $structure[0]['stateForm'] ?? false;
		if ( true === $state || 1 === $state ) {
			return true;
		}
		return is_string( $state ) && ( '1' === $state || 'true' === $state );
	}

	/**
	 * @param array<string,string>           $row       Form row.
	 * @param array<int,array<string,mixed>> $structure Decoded structure.
	 * @return FlxfPublicForm
	 */
	private static function allow( array $row, array $structure ): array {
		$head    = $structure[0] ?? array();
		$thanks  = isset( $head['thank_you_message'] ) && is_array( $head['thank_you_message'] ) ? $head['thank_you_message'] : array();
		$fields  = array();
		$steps   = array();
		foreach ( $structure as $i => $field ) {
			if ( 0 === $i || self::is_child_row( $field ) ) {
				continue;
			}
			if ( 'step' === sanitize_key( self::str( $field['type'] ?? '' ) ) ) {
				$steps[] = array(
					'number' => max( 1, self::num( $field['step'] ?? $field['id_'] ?? count( $steps ) + 1 ) ),
					'title'  => self::text( $field['name'] ?? '' ),
					'help'   => self::text( $field['message'] ?? '' ),
				);
				continue;
			}
			$drawn = self::field( $field, $structure );
			if ( null !== $drawn ) {
				$fields[] = $drawn;
			}
		}
		return array(
			'id'              => (string) (int) $row['form_id'],
			'title'           => (string) $row['form_name'],
			'formType'        => self::form_type( (string) $row['form_type'] ),
			'steps'           => max( 1, self::num( $head['steps'] ?? 1 ) ),
			'buttonLabel'     => self::text( $head['button_single_text'] ?? '' ),
			'successTitle'    => self::text( $thanks['thankYou'] ?? '' ),
			'successMessage'  => self::text( $thanks['done'] ?? '' ),
			'fields'          => $fields,
			'stepList'        => $steps,
			'currency'        => strtoupper( sanitize_key( self::str( $head['currency'] ?? '' ) ) ),
		);
	}

	/**
	 * @param array<string,mixed>            $field     One structure row.
	 * @param array<int,array<string,mixed>> $structure Whole structure, so sibling option rows can be attached.
	 * @return FlxfFormField|null
	 */
	private static function field( array $field, array $structure ): ?array {
		if ( self::flag( $field['hidden'] ?? false ) || self::flag( $field['disabled'] ?? false ) ) {
			return null;
		}
		$type = sanitize_key( self::str( $field['type'] ?? '' ) );
		if ( '' === $type || 'step' === $type || self::is_child_row( $field ) ) {
			return null;
		}
		$id = self::field_id( $field );
		if ( '' === $id ) {
			return null;
		}
		$limits   = in_array( $type, self::LIMIT_TYPES, true );
		$settings = array();
		foreach ( self::SETTING_KEYS as $key ) {
			$value = $field[ $key ] ?? null;
			if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
				$settings[ $key ] = $value;
			} elseif ( is_string( $value ) && '' !== $value ) {
				$settings[ $key ] = 'href' === $key ? esc_url_raw( $value ) : sanitize_text_field( $value );
			}
		}
		$options = self::public_options( self::choice_rows( $field, $structure ) );
		$rows    = self::public_options( self::matrix_rows( $id, $structure ) );
		$choice = array() !== $options || array() !== $rows;
		return array(
			'id'           => $id,
			'type'         => $type,
			'label'        => self::text( $field['name'] ?? $field['label'] ?? '' ),
			'required'     => self::flag( $field['required'] ?? false ),
			'placeholder'  => self::text( $field['placeholder'] ?? '' ),
			'options'      => $options,
			'help'         => self::text( $field['message'] ?? '' ),
			'step'         => max( 1, self::num( $field['step'] ?? 1 ) ),
			'width'        => self::width( $field['size'] ?? 100 ),
			'defaultValue' => 'html' === $type ? wp_kses_post( self::str( $field['value'] ?? '' ) ) : ( $choice || in_array( $type, array( 'file', 'dadfile', 'audio_recorder', 'video_recorder', 'screen_recorder' ), true ) ? '' : self::text( $field['value'] ?? '' ) ),
			'min'          => $limits ? self::text( $field['milen'] ?? '' ) : '',
			'max'          => $limits ? self::text( $field['mlen'] ?? '' ) : '',
			'rows'         => $rows,
			'settings'     => $settings,
		);
	}

	/**
	 * Choice rows for one field. The builder stores them as sibling rows
	 * (`type: option`, `parent: <field id>`). Older rows keep an `options` or
	 * `choices` list on the field. Both shapes are returned.
	 *
	 * @param array<string,mixed>            $field     Parent field.
	 * @param array<int,array<string,mixed>> $structure Whole structure.
	 * @return list<array<string,mixed>>
	 */
	public static function choice_rows( array $field, array $structure ): array {
		$out = array();
		$raw = $field['options'] ?? $field['choices'] ?? array();
		if ( is_array( $raw ) ) {
			foreach ( $raw as $option ) {
				if ( is_array( $option ) ) {
					$clean = array();
					foreach ( $option as $key => $value ) {
						if ( is_string( $key ) ) {
							$clean[ $key ] = $value;
						}
					}
					$out[] = $clean;
				}
			}
		}
		$id = self::field_id( $field );
		if ( '' === $id ) {
			return $out;
		}
		foreach ( $structure as $row ) {
			if ( 'option' !== sanitize_key( self::str( $row['type'] ?? '' ) ) ) {
				continue;
			}
			if ( sanitize_text_field( self::str( $row['parent'] ?? '' ) ) !== $id ) {
				continue;
			}
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * Drop the GraphQL cache tag for one form. Called from the save and delete
	 * paths that already clear the classic form cache.
	 */
	public static function purge( int $id ): void {
		if ( $id < 1 || ! function_exists( 'flx_gql_purge' ) ) {
			return;
		}
		flx_gql_purge( 'flx-forms:form:' . (string) $id );
	}

	/**
	 * @param list<array<string,mixed>> $list Option rows.
	 * @return list<FlxfFormOption>
	 */
	private static function public_options( array $list ): array {
		$out = array();
		foreach ( $list as $option ) {
			$value = self::text( $option['value'] ?? '' );
			$label = self::text( $option['label'] ?? $option['name'] ?? $value );
			if ( '' === $value ) {
				$value = self::field_id( $option );
			}
			if ( '' === $value && '' === $label ) {
				continue;
			}
			$price = $option['price'] ?? null;
			$out[] = array(
				'value' => '' !== $value ? $value : $label,
				'label' => '' !== $label ? $label : $value,
				'price' => is_numeric( $price ) ? (float) $price : null,
				'image' => esc_url_raw( self::str( $option['src'] ?? '' ) ),
			);
		}
		return $out;
	}

	/**
	 * Rows of a rating table (`r_matrix` rows whose parent is the field).
	 *
	 * @param array<int,array<string,mixed>> $structure Whole structure.
	 * @return list<array<string,mixed>>
	 */
	private static function matrix_rows( string $id, array $structure ): array {
		$out = array();
		foreach ( $structure as $row ) {
			if ( 'r_matrix' === sanitize_key( self::str( $row['type'] ?? '' ) ) && sanitize_text_field( self::str( $row['parent'] ?? '' ) ) === $id ) {
				$out[] = $row;
			}
		}
		return $out;
	}

	/**
	 * Field width in percent (the builder's size: 100, 75, 50, 33, 25).
	 *
	 * @param mixed $size Stored size.
	 */
	private static function width( mixed $size ): int {
		$n = self::num( $size );
		return $n > 0 && $n <= 100 ? $n : 100;
	}

	/**
	 * Option and matrix rows belong to their parent. They are not fields.
	 *
	 * @param array<string,mixed> $field Structure row.
	 */
	public static function is_child_row( array $field ): bool {
		$type = sanitize_key( self::str( $field['type'] ?? '' ) );
		return 'option' === $type || 'r_matrix' === $type;
	}

	/**
	 * @param array<string,mixed> $field Field row.
	 */
	public static function field_id( array $field ): string {
		$id = sanitize_text_field( self::str( $field['id'] ?? '' ) );
		if ( '' === $id ) {
			$id = sanitize_text_field( self::str( $field['id_'] ?? '' ) );
		}
		return $id;
	}

	/**
	 * @return array<string,string>|null
	 */
	private static function row( int $id ): ?array {
		if ( $id < 1 ) {
			return null;
		}
		global $wpdb;
		/** @var wpdb $wpdb */
		$table = $wpdb->prefix . 'emsfb_form';
		$found = $wpdb->get_row(
			$wpdb->prepare( 'SELECT form_id, form_name, form_type, form_structer FROM %i WHERE form_id = %d', $table, $id ),
			ARRAY_A
		);
		if ( ! is_array( $found ) || ! isset( $found['form_id'] ) ) {
			return null;
		}
		return array(
			'form_id'       => self::str( $found['form_id'] ),
			'form_name'     => self::str( $found['form_name'] ?? '' ),
			'form_type'     => self::str( $found['form_type'] ?? 'form' ),
			'form_structer' => self::str( $found['form_structer'] ?? '' ),
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function structure( string $raw ): array {
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			$decoded = json_decode( str_replace( '\\', '', $raw ), true );
		}
		if ( ! is_array( $decoded ) ) {
			return array();
		}
		$out = array();
		foreach ( $decoded as $row ) {
			$clean = array();
			if ( is_array( $row ) ) {
				foreach ( $row as $key => $value ) {
					if ( is_string( $key ) ) {
						$clean[ $key ] = $value;
					}
				}
			}
			$out[] = $clean;
		}
		return $out;
	}

	/**
	 * Known form types. Anything else is reported as form so the enum stays closed.
	 */
	private static function form_type( string $type ): string {
		$known = array( 'form', 'survey', 'subscribe', 'payment', 'register', 'login' );
		return in_array( $type, $known, true ) ? $type : 'form';
	}

	/**
	 * @param mixed $value Stored flag.
	 */
	public static function flag( mixed $value ): bool {
		return true === $value || 1 === $value || '1' === $value || 'true' === $value;
	}

	/**
	 * @param mixed $value Stored text.
	 */
	private static function text( mixed $value ): string {
		return sanitize_text_field( self::str( $value ) );
	}

	/**
	 * A stored scalar as text. Arrays and objects become an empty string.
	 */
	private static function str( mixed $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * A stored number as int. Anything else is 0.
	 */
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
