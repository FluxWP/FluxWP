<?php
/**
 * Flux AI SDK: the small, versioned API a plugin uses to plug its AI features into Flux AI & MCP.
 *
 *  - flx_ai_plugin()   describe the plugin once (label, tool prefix, screens).
 *  - flx_ai_tool()     add a tool: shown in Flux AI & MCP › Tools, offered to the shared chat and the MCP server.
 *                      The name gets the plugin prefix automatically (summary → myp_summary).
 *  - flx_ai_settings() declare AI settings / feature switches; Flux AI draws and saves them
 *                      (Flux AI & MCP › Connected systems › Settings), the plugin reads them with flx_ai_get().
 *  - flx_ai_chat() / flx_ai_complete() call the model through Flux AI (keys, budget, logs in one place).
 *
 * Everything degrades quietly: without Flux AI the registry is simply unused, flx_ai_available() is false,
 * flx_ai_chat() returns WP_Error( 'flx_ai_missing' ) and flx_ai_get() returns the declared defaults.
 *
 * Source: FluxWP/fluxwp tools/ai-sdk/flx-ai-sdk (copy with tools/ai-sdk/sync.py, do not edit the copies).
 *
 * @package flx-ai-sdk
 */

defined( 'ABSPATH' ) || exit;

/** Settings of every plugin, one option: slug => key => value. */
const FLX_AI_SDK_OPTION = 'flx_ai_plugin_settings';

/** Short TR/EN strings (the SDK has no text domain of its own). */
function flx_ai_sdk_str( $key ) {
	$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
	$tr     = 0 === strpos( (string) $locale, 'tr' );
	$s      = array(
		'missing'  => $tr ? 'Flux AI & MCP kurulu ya da etkin değil.' : 'Flux AI & MCP is not installed or not active.',
		'disabled' => $tr ? 'Bu eklentinin yapay zekâ özellikleri kapalı (Flux AI & MCP › Bağlı sistemler).' : 'AI features of this plugin are turned off (Flux AI & MCP › Connected systems).',
	);
	return $s[ $key ] ?? '';
}

/**
 * Registry: plugins, tools, settings fields. `flx_ai_register` fires once, on first use after plugins_loaded.
 *
 * @return array{plugins: array, tools: array, settings: array}
 */
function flx_ai_registry() {
	if ( ! isset( $GLOBALS['flx_ai_sdk_registry'] ) ) {
		$GLOBALS['flx_ai_sdk_registry'] = array(
			'plugins'  => array(),
			'tools'    => array(),
			'settings' => array(),
		);
	}
	if ( empty( $GLOBALS['flx_ai_sdk_fired'] ) && did_action( 'plugins_loaded' ) ) {
		$GLOBALS['flx_ai_sdk_fired'] = true;
		/**
		 * Register AI plugins, tools and settings here (flx_ai_plugin, flx_ai_tool, flx_ai_settings).
		 *
		 * @param string $version SDK version that is loaded.
		 */
		do_action( 'flx_ai_register', FLX_AI_SDK_VERSION );
	}
	return $GLOBALS['flx_ai_sdk_registry'];
}

/**
 * Describes a plugin.
 *
 * @param string $slug Plugin folder / source id (flx-speed). Also the `source` of its model calls and logs.
 * @param array  $args {
 *     label       string  Display name (Flux Speed).
 *     prefix      string  Tool name prefix without underscore (flxp). Default: slug without dashes.
 *     plugin      string  Plugin file relative to the plugins folder (flx-speed/flx-speed.php), for the status column.
 *     page        string  Plugin's own screen (admin.php?page=flx-speed).
 *     description string  One line shown on the settings screen.
 * }
 */
function flx_ai_plugin( $slug, array $args = array() ) {
	$slug = sanitize_key( $slug );
	if ( '' === $slug ) {
		return;
	}
	flx_ai_registry();
	$prev = $GLOBALS['flx_ai_sdk_registry']['plugins'][ $slug ] ?? array();
	$args = array_merge( $prev, $args );

	$GLOBALS['flx_ai_sdk_registry']['plugins'][ $slug ] = array(
		'slug'        => $slug,
		'label'       => (string) ( $args['label'] ?? $slug ),
		'prefix'      => preg_replace( '/[^a-z0-9]/', '', strtolower( (string) ( $args['prefix'] ?? str_replace( '-', '', $slug ) ) ) ),
		'plugin'      => (string) ( $args['plugin'] ?? '' ),
		'page'        => (string) ( $args['page'] ?? '' ),
		'description' => (string) ( $args['description'] ?? '' ),
	);
}

/** Full tool name: prefix_name (unchanged when it already starts with the prefix). */
function flx_ai_tool_name( $slug, $name ) {
	$p = flx_ai_registry()['plugins'][ sanitize_key( $slug ) ]['prefix'] ?? '';
	$name = (string) $name;
	if ( '' === $p || 0 === strpos( $name, $p . '_' ) ) {
		return $name;
	}
	return $p . '_' . $name;
}

/**
 * Adds a tool.
 *
 * @param string $slug Plugin slug given to flx_ai_plugin().
 * @param string $name Short name (summary); the prefix is added.
 * @param array  $def {
 *     title       string    Shown in the tools list and the approval card.
 *     description string    Sent to the model (English recommended).
 *     schema      array     JSON Schema of the arguments (type object).
 *     run         callable  fn( array $args, array $ctx ) → array|string; throw an Exception on error.
 *                           $ctx: user_id, chat_id, source, purpose (chat) or channel => 'mcp'.
 *     readonly    bool      false (default) = changes data → the chat asks for approval every time.
 *     confirm     bool      Ask for approval even though it only reads.
 *     cap         string    Capability needed (default edit_posts).
 *     mcp         bool      Offer on the MCP server (default true).
 *     chat        bool      Offer in the Flux AI chat (default true).
 *     log         bool      false: successful MCP calls are not written to Recent activity (polling tools).
 *     aliases     string[]  Old names that keep working (renamed tools).
 * }
 * @return string Registered name.
 */
function flx_ai_tool( $slug, $name, array $def ) {
	$slug = sanitize_key( $slug );
	$full = flx_ai_tool_name( $slug, $name );
	if ( ! preg_match( '/^[a-zA-Z0-9_-]{1,64}$/', $full ) || empty( $def['run'] ) || ! is_callable( $def['run'] ) ) {
		if ( function_exists( '_doing_it_wrong' ) ) {
			_doing_it_wrong( __FUNCTION__, esc_html( 'Invalid tool name or run callback: ' . $full ), 'flx-ai-sdk 1.0.0' );
		}
		return '';
	}
	$run = $def['run'];
	$GLOBALS['flx_ai_sdk_registry']['tools'][ $full ][ $slug ] = array(
		'title'       => (string) ( $def['title'] ?? $full ),
		'description' => (string) ( $def['description'] ?? '' ),
		'schema'      => $def['schema'] ?? array( 'type' => 'object', 'properties' => new stdClass() ),
		'readonly'    => ! empty( $def['readonly'] ),
		'confirm'     => ! empty( $def['confirm'] ),
		'cap'         => (string) ( $def['cap'] ?? 'edit_posts' ),
		'mcp'         => ! isset( $def['mcp'] ) || ! empty( $def['mcp'] ),
		'chat'        => ! isset( $def['chat'] ) || ! empty( $def['chat'] ),
		'log'         => ! isset( $def['log'] ) || ! empty( $def['log'] ),
		'aliases'     => array_values( array_filter( array_map( 'strval', (array) ( $def['aliases'] ?? array() ) ) ) ),
		'source'      => $slug,
		'sdk'         => true,
		// MCP passes only $args; the chat passes $args and $ctx.
		'run'         => static function ( $args, $ctx = null ) use ( $run ) {
			return call_user_func( $run, is_array( $args ) ? $args : array(), is_array( $ctx ) ? $ctx : array( 'channel' => 'mcp' ) );
		},
	);
	return $full;
}

/**
 * Declares settings fields. Flux AI draws them under Flux AI & MCP › Connected systems › <plugin> and saves them.
 *
 * @param string $slug   Plugin slug.
 * @param array  $fields key => array(
 *     type        toggle|text|textarea|number|select|model
 *     label       string
 *     help        string  (optional)
 *     default     mixed
 *     options     array   value => label (select)
 *     min / max   number  (number)
 * )
 */
function flx_ai_settings( $slug, array $fields ) {
	$slug = sanitize_key( $slug );
	flx_ai_registry();
	foreach ( $fields as $key => $f ) {
		$key = sanitize_key( $key );
		if ( '' === $key || ! is_array( $f ) ) {
			continue;
		}
		$f += array(
			'type'    => 'text',
			'label'   => $key,
			'help'    => '',
			'default' => 'toggle' === ( $f['type'] ?? '' ) ? true : '',
			'options' => array(),
		);
		$GLOBALS['flx_ai_sdk_registry']['settings'][ $slug ][ $key ] = $f;
	}
}

/** Shorthand: an on/off feature switch (default on). Read it with flx_ai_enabled( $slug, $key ). */
function flx_ai_feature( $slug, $key, $label, $help = '', $default = true ) {
	flx_ai_settings(
		$slug,
		array(
			$key => array(
				'type'    => 'toggle',
				'label'   => $label,
				'help'    => $help,
				'default' => (bool) $default,
				'feature' => true,
			),
		)
	);
}

/** Saved value of a settings field (declared default when not saved). */
function flx_ai_get( $slug, $key, $fallback = null ) {
	$slug  = sanitize_key( $slug );
	$all   = get_option( FLX_AI_SDK_OPTION, array() );
	$saved = is_array( $all ) && isset( $all[ $slug ] ) && is_array( $all[ $slug ] ) ? $all[ $slug ] : array();
	if ( array_key_exists( $key, $saved ) ) {
		return $saved[ $key ];
	}
	$f = flx_ai_registry()['settings'][ $slug ][ $key ] ?? null;
	return null !== $f ? $f['default'] : $fallback;
}

/**
 * Saves values (used by Flux AI's settings screen; plugins may call it too).
 * Only declared fields are stored; values are sanitised by type.
 */
function flx_ai_set( $slug, array $values ) {
	$slug   = sanitize_key( $slug );
	$fields = flx_ai_registry()['settings'][ $slug ] ?? array();
	$all    = get_option( FLX_AI_SDK_OPTION, array() );
	$all    = is_array( $all ) ? $all : array();
	$cur    = isset( $all[ $slug ] ) && is_array( $all[ $slug ] ) ? $all[ $slug ] : array();
	foreach ( $fields as $key => $f ) {
		if ( ! array_key_exists( $key, $values ) ) {
			continue;
		}
		$v = $values[ $key ];
		switch ( $f['type'] ) {
			case 'toggle':
				$v = ! empty( $v ) && '0' !== $v;
				break;
			case 'number':
				$v = is_numeric( $v ) ? $v + 0 : $f['default'];
				if ( isset( $f['min'] ) ) {
					$v = max( $f['min'], $v );
				}
				if ( isset( $f['max'] ) ) {
					$v = min( $f['max'], $v );
				}
				break;
			case 'select':
				$v = array_key_exists( (string) $v, (array) $f['options'] ) ? (string) $v : $f['default'];
				break;
			case 'textarea':
				$v = sanitize_textarea_field( (string) $v );
				break;
			default:
				$v = sanitize_text_field( (string) $v );
		}
		$cur[ $key ] = $v;
	}
	$all[ $slug ] = $cur;
	update_option( FLX_AI_SDK_OPTION, $all, false );
}

/** Is Flux AI installed, configured, and AI on for this plugin (Connected systems switch)? */
function flx_ai_available( $slug = '' ) {
	if ( ! function_exists( 'flxai_chat' ) ) {
		return false;
	}
	if ( function_exists( 'flxai_ready' ) && ! flxai_ready( (string) $slug ) ) {
		return false;
	}
	return '' === (string) $slug || ! function_exists( 'flxai_source_enabled' ) || flxai_source_enabled( $slug );
}

/** Available and, when $feature is given, that feature switch is on. */
function flx_ai_enabled( $slug, $feature = '' ) {
	if ( ! flx_ai_available( $slug ) ) {
		return false;
	}
	return '' === (string) $feature || (bool) flx_ai_get( $slug, $feature, true );
}

/** Flux AI version, or '' when it is not active. */
function flx_ai_host_version() {
	return defined( 'FLXAI_VERSION' ) ? (string) FLXAI_VERSION : '';
}

/** Address of the plugin's AI settings in Flux AI ('' without Flux AI). */
function flx_ai_settings_url( $slug ) {
	return function_exists( 'flxai_chat' ) ? admin_url( 'admin.php?page=flx-ai-systems&system=' . rawurlencode( sanitize_key( $slug ) ) ) : '';
}

/**
 * Chat completion through Flux AI (OpenAI message format, see Flux AI API.md › flxai_chat).
 * A `model` settings field of the plugin is used when $opts['model'] is empty.
 *
 * @return array|WP_Error
 */
function flx_ai_chat( $slug, array $messages, array $opts = array() ) {
	if ( ! function_exists( 'flxai_chat' ) ) {
		return new WP_Error( 'flx_ai_missing', flx_ai_sdk_str( 'missing' ) );
	}
	if ( function_exists( 'flxai_source_enabled' ) && ! flxai_source_enabled( $slug ) ) {
		return new WP_Error( 'flx_ai_disabled', flx_ai_sdk_str( 'disabled' ) );
	}
	$opts['source'] = sanitize_key( $slug );
	if ( empty( $opts['model'] ) ) {
		$m = (string) flx_ai_get( $slug, 'model', '' );
		if ( '' !== $m ) {
			$opts['model'] = $m;
		}
	}
	return flxai_chat( $messages, $opts );
}

/**
 * One prompt → text.
 *
 * @return string|WP_Error
 */
function flx_ai_complete( $slug, $prompt, array $opts = array() ) {
	$r = flx_ai_chat( $slug, array( array( 'role' => 'user', 'content' => (string) $prompt ) ), $opts );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	return (string) ( $r['content'] ?? '' );
}
