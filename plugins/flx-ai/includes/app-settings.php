<?php
/** Full AI settings contract. Provider credentials remain write-only. */
defined( 'ABSPATH' ) || exit;

function flxai_admin_setting_definitions() {
	$out = array();
	$add = static function ( $section, $type, $names, $extra = array() ) use ( &$out ) { foreach ( explode( ' ', $names ) as $name ) { $out[ $name ] = array( 'section' => $section, 'type' => $type ) + $extra; } };
	$add( 'providers', 'secret', 'key_openrouter key_anthropic key_openai key_gemini key_fal key_evolink' );
	$add( 'providers', 'text', 'anthropic_workspace' );
	$add( 'models', 'model', 'model chat_model title_model' );
	$add( 'reasoning', 'select', 'cache', array( 'options' => array( 'off', '5m', '1h' ) ) );
	$add( 'reasoning', 'select', 'effort', array( 'options' => array( 'low', 'medium', 'high' ) ) );
	$add( 'reasoning', 'toggle', 'thinking' );
	$add( 'limits', 'number', 'limit_day limit_week limit_month', array( 'min' => 0, 'step' => .01 ) );
	$add( 'limits', 'select', 'limit_action', array( 'options' => array( 'block', 'warn' ) ) );
	$add( 'limits', 'number', 'limit_warn_pct', array( 'min' => 10, 'max' => 99, 'step' => 1 ) );
	$add( 'limits', 'toggle', 'limit_notify' );
	$add( 'chat', 'roles', 'chat_roles' );
	$add( 'chat', 'toggle', 'chat_admin chat_front' );
	$add( 'chat', 'select', 'chat_mode', array( 'options' => array( 'bubble', 'dock' ) ) );
	$add( 'chat', 'number', 'chat_max_rounds', array( 'min' => 2, 'max' => 30, 'step' => 1 ) );
	$add( 'chat', 'number', 'chat_history', array( 'min' => 6, 'max' => 200, 'step' => 1 ) );
	$add( 'chat', 'textarea', 'chat_instructions' );
	$add( 'media', 'image-model', 'media_image_model' );
	$add( 'media', 'video-model', 'media_video_model' );
	$add( 'media', 'number', 'media_days', array( 'min' => 7, 'max' => 1825, 'step' => 1 ) );
	$add( 'render', 'toggle', 'chat_widgets' );
	$add( 'render', 'url', 'render_url' );
	$add( 'render', 'text', 'render_user' );
	$add( 'render', 'secret', 'key_render' );
	$add( 'logs', 'number', 'log_days log_body_days', array( 'min' => 0, 'max' => 3650, 'step' => 1 ) );
	$add( 'logs', 'toggle', 'log_bodies delete_data' );
	return $out;
}

function flxai_admin_settings_snapshot() {
	$definitions = flxai_admin_setting_definitions(); $values = array(); $secrets = array(); $constants = array();
	foreach ( $definitions as $key => $field ) {
		if ( 'secret' === $field['type'] ) {
			$name = 'FLXAI_' . strtoupper( $key ); $locked = defined( $name ) && '' !== (string) constant( $name );
			$secrets[ $key ] = array( 'configured' => '' !== (string) flxai_setting( $key ), 'locked' => $locked );
			$constants[ $key ] = $locked ? hash( 'sha256', (string) constant( $name ) ) : '';
		} else { $value = flxai_setting( $key ); $values[ $key ] = 'toggle' === $field['type'] ? (bool) $value : $value; }
	}
	$roles = array(); foreach ( wp_roles()->get_names() as $id => $label ) { $roles[] = array( 'value' => $id, 'label' => translate_user_role( $label ) ); }
	$media = flxai_media_providers(); $models = array();
	foreach ( $media['models'] as $id => $model ) { $models[] = array( 'id' => $id, 'label' => $model['label'], 'kind' => $model['kind'], 'routes' => $model['routes'], 'auto' => $model['auto'] ); }
	$sources = array(); $overrides = (array) flxai_setting( 'source_models', array() );
	foreach ( flxai_sources() as $id => $label ) { if ( 'flx-ai' !== $id ) { $sources[] = array( 'source' => $id, 'label' => $label, 'model' => (string) ( $overrides[ $id ] ?? '' ) ); } }
	return array( 'revision' => hash_hmac( 'sha256', wp_json_encode( array( get_option( 'flxai_settings', array() ), $constants, $definitions, $roles, $models, $sources ) ), wp_salt( 'nonce' ) ), 'fields' => (object) $definitions, 'values' => (object) $values, 'secrets' => (object) $secrets, 'roles' => $roles, 'media' => array( 'models' => $models, 'providers' => $media['providers'] ), 'sources' => $sources, 'renderer' => flxai_widget_renderer(), 'default_model' => flxai_model_for( '' ) );
}

function flxai_admin_settings_get() { return flxai_admin_settings_snapshot(); }

/** Partial validated patch: absent secrets/values and inactive-plugin settings are preserved. */
function flxai_admin_settings_update( array $body, $require_revision = true ) {
	if ( ! flxai_can_manage() ) { return new WP_Error( 'flxai_forbidden', __( 'Yetkiniz yok.', 'flx-ai' ), array( 'status' => 403 ) ); }
	return flxai_with_settings_lock( static function () use ( $body, $require_revision ) {
		$current = flxai_admin_settings_snapshot();
		if ( ( $require_revision || isset( $body['revision'] ) ) && ( ! is_string( $body['revision'] ?? null ) || ! hash_equals( $current['revision'], $body['revision'] ) ) ) { return new WP_Error( 'flxai_stale', __( 'Ayarlar değişti. Yenileyip tekrar deneyin.', 'flx-ai' ), array( 'status' => 409 ) ); }
		$invalid = new WP_Error( 'flxai_settings_input', __( 'Geçersiz ayar. Değişiklikler kaydedilmedi.', 'flx-ai' ), array( 'status' => 400 ) );
		if ( array_diff( array_keys( $body ), array( 'revision', 'values', 'secrets', 'source_models' ) ) || ! is_array( $body['values'] ?? array() ) || ! is_array( $body['secrets'] ?? array() ) || ! is_array( $body['source_models'] ?? array() ) ) { return $invalid; }
		$definitions = flxai_admin_setting_definitions(); $changes = array(); $known_roles = array_keys( wp_roles()->get_names() ); $catalog = flxai_media_catalog();
		foreach ( $body['values'] ?? array() as $key => $value ) {
			if ( ! isset( $definitions[ $key ] ) || 'secret' === $definitions[ $key ]['type'] ) { return $invalid; }
			$field = $definitions[ $key ]; $type = $field['type'];
			if ( 'toggle' === $type ) { if ( ! is_bool( $value ) ) { return $invalid; } $value = $value ? 1 : 0; }
			elseif ( 'number' === $type ) {
				if ( ! is_int( $value ) && ! is_float( $value ) || ! is_finite( (float) $value ) || $value < $field['min'] || isset( $field['max'] ) && $value > $field['max'] || 1 === $field['step'] && floor( $value ) !== (float) $value ) { return $invalid; }
				$value = 1 === $field['step'] ? (int) $value : round( $value, 2 );
			} elseif ( 'roles' === $type ) {
				if ( ! is_array( $value ) ) { return $invalid; } foreach ( $value as $role ) { if ( ! is_string( $role ) || ! in_array( $role, $known_roles, true ) ) { return $invalid; } } $value = array_values( array_unique( $value ) );
			} else {
				if ( ! is_string( $value ) ) { return $invalid; }
				if ( 'select' === $type && ! in_array( $value, $field['options'], true ) ) { return $invalid; }
				if ( in_array( $type, array( 'image-model', 'video-model' ), true ) && ( ! isset( $catalog[ $value ] ) || str_replace( '-model', '', $type ) !== $catalog[ $value ]['kind'] ) ) { return $invalid; }
				if ( 'url' === $type ) {
					if ( '' !== $value && ( ! filter_var( $value, FILTER_VALIDATE_URL ) || ! wp_parse_url( $value, PHP_URL_HOST ) || ! in_array( strtolower( (string) wp_parse_url( $value, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true ) ) ) { return $invalid; }
					$value = esc_url_raw( $value, array( 'http', 'https' ) );
				} else { $value = 'textarea' === $type ? sanitize_textarea_field( $value ) : sanitize_text_field( $value ); }
				if ( 'anthropic_workspace' === $key ) { $value = preg_replace( '/[^a-zA-Z0-9_-]/', '', $value ); }
			}
			$changes[ $key ] = $value;
		}
		foreach ( $body['secrets'] ?? array() as $key => $action ) {
			if ( ! isset( $definitions[ $key ] ) || 'secret' !== $definitions[ $key ]['type'] || ! is_array( $action ) || array_diff( array_keys( $action ), array( 'action', 'value' ) ) || ! in_array( $action['action'] ?? '', array( 'replace', 'clear' ), true ) ) { return $invalid; }
			$secret_state = (array) $current['secrets'];
			if ( $secret_state[ $key ]['locked'] ) { return new WP_Error( 'flxai_constant', __( 'Bu anahtar sunucu yapılandırmasından yönetiliyor.', 'flx-ai' ), array( 'status' => 409 ) ); }
			if ( 'clear' === $action['action'] ) { if ( isset( $action['value'] ) ) { return $invalid; } $changes[ $key ] = ''; }
			else { if ( ! is_string( $action['value'] ?? null ) || '' === trim( $action['value'] ) ) { return $invalid; } $changes[ $key ] = sanitize_text_field( trim( $action['value'] ) ); }
		}
		if ( isset( $body['source_models'] ) ) {
			$models = (array) flxai_setting( 'source_models', array() ); $known = array_column( $current['sources'], 'source' );
			foreach ( $body['source_models'] as $source => $model ) { if ( ! in_array( $source, $known, true ) || ! is_string( $model ) ) { return $invalid; } if ( '' === trim( $model ) ) { unset( $models[ $source ] ); } else { $models[ $source ] = sanitize_text_field( $model ); } }
			$changes['source_models'] = $models;
		}
		$result = flxai_update_settings( $changes ); if ( is_wp_error( $result ) ) { return $result; }
		if ( array_intersect( array_keys( $changes ), array( 'key_openrouter', 'key_anthropic', 'anthropic_workspace' ) ) ) { flxai_models_flush(); }
		return flxai_admin_settings_snapshot();
	} );
}

function flxai_admin_settings_post( WP_REST_Request $request ) {
	$body = $request->get_json_params(); return is_array( $body ) ? flxai_admin_settings_update( $body ) : new WP_Error( 'flxai_settings_input', __( 'Geçersiz istek.', 'flx-ai' ), array( 'status' => 400 ) );
}

/** The old settings URL remains a capability-checked adapter; it cannot bypass the shared validator/lock. */
add_filter( 'flxapp_settings_override', static function ( $result, $option, $request ) {
	if ( 'flxai_settings' !== $option ) { return $result; }
	if ( ! flxai_can_manage() ) { return new WP_Error( 'flxai_forbidden', __( 'Yetkiniz yok.', 'flx-ai' ), array( 'status' => 403 ) ); }
	if ( 'GET' !== $request->get_method() ) {
		$input = $request->get_json_params(); if ( null === $input ) { $input = $request->get_body_params(); }
		if ( ! is_array( $input ) ) { return new WP_Error( 'flxai_settings_input', __( 'Geçersiz istek.', 'flx-ai' ), array( 'status' => 400 ) ); }
		$body = array( 'values' => $input['values'] ?? $input, 'secrets' => array() ); if ( isset( $input['revision'] ) ) { $body['revision'] = $input['revision']; }
		if ( ! is_array( $body['values'] ) ) { return new WP_Error( 'flxai_settings_input', __( 'Geçersiz değerler.', 'flx-ai' ), array( 'status' => 400 ) ); }
		if ( ! isset( $input['values'] ) ) { unset( $body['values']['revision'] ); }
		foreach ( flxai_admin_setting_definitions() as $key => $field ) {
			if ( ! array_key_exists( $key, $body['values'] ) ) { continue; }
			$value = $body['values'][ $key ];
			if ( 'secret' === $field['type'] ) { if ( is_string( $value ) && '' !== trim( $value ) ) { $body['secrets'][ $key ] = array( 'action' => 'replace', 'value' => $value ); } elseif ( ! is_string( $value ) ) { return new WP_Error( 'flxai_settings_input', __( 'Geçersiz anahtar.', 'flx-ai' ), array( 'status' => 400 ) ); } unset( $body['values'][ $key ] ); }
			elseif ( 'toggle' === $field['type'] && in_array( $value, array( 0, 1, '0', '1', 'true', 'false' ), true ) ) { $body['values'][ $key ] = rest_sanitize_boolean( $value ); }
			elseif ( 'number' === $field['type'] && is_string( $value ) && is_numeric( $value ) ) { $body['values'][ $key ] = $value + 0; }
		}
		$result = flxai_admin_settings_update( $body, false ); if ( is_wp_error( $result ) ) { return $result; }
	}
	$state = flxai_admin_settings_snapshot(); $saved = array(); foreach ( (array) $state['secrets'] as $key => $secret ) { $saved[ $key ] = $secret['configured']; }
	return array( 'option' => $option, 'values' => $state['values'], 'saved' => (object) $saved, 'schema' => $state['fields'], 'revision' => $state['revision'] );
}, 10, 3 );
