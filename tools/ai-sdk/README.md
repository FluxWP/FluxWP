# Flux AI SDK (flx-ai-sdk)

Small, versioned PHP library that a plugin bundles to plug its AI features into **Flux AI & MCP**: tools for the shared chat and the MCP server, AI settings drawn by Flux AI, and model calls through Flux AI's keys, budget and logs. Works for FluxWP packages and third-party plugins alike.

Every plugin ships an identical copy in `<pkg>/includes/flx-ai-sdk/`. Each copy registers itself; on `plugins_loaded` (priority -100) only the newest `core.php` is loaded, so old copies never shadow new API. Without Flux AI nothing breaks: tools are simply not offered, `flx_ai_available()` is false, `flx_ai_chat()` returns `WP_Error( 'flx_ai_missing' )`, `flx_ai_get()` returns the declared defaults.

## Add it to a plugin

```sh
python3 tools/ai-sdk/sync.py --add plugins/flx-x      # copy + require line in the main file
python3 tools/ai-sdk/sync.py . ../wp-extras           # refresh every package that has it (after editing the source)
```

Edit only `tools/ai-sdk/flx-ai-sdk/`; bump `version` in `loader.php` when the API changes. Third-party plugins copy the folder and add `require_once __DIR__ . '/includes/flx-ai-sdk/loader.php';`.

## Use

```php
add_action( 'flx_ai_register', function () {
	flx_ai_plugin( 'flx-x', array(
		'label'  => 'Flux X',
		'prefix' => 'flxx',                       // tool names get this prefix: summary → flxx_summary
		'plugin' => 'flx-x/flx-x.php',
		'page'   => 'admin.php?page=flx-x',
	) );

	flx_ai_tool( 'flx-x', 'summary', array(
		'title'       => __( 'Özet', 'flx-x' ),
		'description' => 'Returns the plugin summary.', // sent to the model, English recommended
		'readonly'    => true,                          // false = changes data → approval every time
		'cap'         => 'manage_options',
		'schema'      => array( 'type' => 'object', 'properties' => new stdClass() ),
		'aliases'     => array( 'x_summary' ),          // old names keep working
		'run'         => function ( array $args, array $ctx ) { return array( 'ok' => true ); },
	) );

	flx_ai_feature( 'flx-x', 'auto_summary', __( 'Otomatik özet', 'flx-x' ), __( 'Yeni kayıtta kısa özet yazar.', 'flx-x' ) );
	flx_ai_settings( 'flx-x', array(
		'tone'  => array( 'type' => 'select', 'label' => __( 'Ton', 'flx-x' ), 'default' => 'formal', 'options' => array( 'formal' => 'Resmî', 'friendly' => 'Samimi' ) ),
		'model' => array( 'type' => 'model', 'label' => __( 'Model', 'flx-x' ) ), // used by flx_ai_chat() when $opts['model'] is empty
	) );
} );

// Anywhere after init:
if ( flx_ai_enabled( 'flx-x', 'auto_summary' ) ) {
	$text = flx_ai_complete( 'flx-x', 'Summarise: ' . $body );
}
```

Settings appear under **Flux AI & MCP › Connected systems › Flux X › AI settings** (`flx_ai_settings_url( 'flx-x' )`); values live in the option `flx_ai_plugin_settings`.

| Function | What |
|---|---|
| `flx_ai_plugin( $slug, $args )` | label, prefix, plugin, page, description |
| `flx_ai_tool( $slug, $name, $def )` | title, description, schema, run( $args, $ctx ), readonly, confirm, cap, mcp (default true), chat (default true), log, aliases. Returns the full name. |
| `flx_ai_settings( $slug, $fields )` | key => type (toggle, text, textarea, number, select, model), label, help, default, options, min, max |
| `flx_ai_feature( $slug, $key, $label, $help, $default = true )` | on/off switch |
| `flx_ai_get( $slug, $key, $fallback )` / `flx_ai_set( $slug, $values )` | read / save values |
| `flx_ai_available( $slug )` / `flx_ai_enabled( $slug, $feature )` | Flux AI ready and AI on for the plugin (and the feature) |
| `flx_ai_chat( $slug, $messages, $opts )` / `flx_ai_complete( $slug, $prompt, $opts )` | model calls via `flxai_chat()` |
| `flx_ai_settings_url( $slug )`, `flx_ai_host_version()`, `flx_ai_registry()` | helpers |

Tool names must be unique. If two plugins register the same name, Flux AI keeps the first one on and the other off, and shows the conflict on Flux AI & MCP › Tools where the admin picks which one is used.
