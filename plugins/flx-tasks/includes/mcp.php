<?php
/**
 * Flux Tasks araçları ortak Flux AI MCP sunucusuna kaydolur (`flx_mcp_tools`).
 * Bu eklenti kendi MCP rotasını veya anahtar deposunu çalıştırmaz.
 * Flux AI yoksa filtre uygulanmaz; görev takibi MCP'siz çalışır.
 *
 * @package FLX_Gorev
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'flx_mcp_tools', 'flxg_register_mcp_tools' );
add_filter( 'flx_mcp_instructions', 'flxg_mcp_instruction_lines' );

/**
 * Araçlar tek kayıtla hem MCP'de hem ortak sohbette görünür. Ad `flxg_` önekiyle kaydedilir (ortak sunucuda
 * `list_users`, `create_doc` gibi adlar başka eklentiyle çakışmasın); eski Tasks sunucusunun öneksiz adı alias'tır.
 * Yetki aracın içindedir (`flxg_run_tool`: `flxg_use` + liste kuralları). Flux AI yalnız kullanıcıyı doğrular.
 *
 * @param mixed $tools
 * @return array
 */
function flxg_register_mcp_tools( $tools ) {
	$tools  = is_array( $tools ) ? $tools : array();
	$titles = function_exists( 'flxg_flxai_tool_titles' ) ? flxg_flxai_tool_titles() : array();
	foreach ( flxg_tools() as $name => $t ) {
		$row = array(
			'title'       => $titles[ $name ] ?? $t['title'],
			'description' => 'Flux Tasks: ' . $t['description'],
			'schema'      => $t['schema'],
			'readonly'    => ! empty( $t['readonly'] ),
			'confirm'     => ! empty( $t['destructive'] ),
			'cap'         => 'flxg_use',
			'source'      => 'flx-tasks',
			'aliases'     => array( $name ),
			'run'         => function ( $args ) use ( $name ) {
				list( $ok, $data ) = flxg_run_tool( $name, is_array( $args ) ? $args : array() );
				if ( ! $ok ) {
					throw new Exception( (string) ( $data['error'] ?? 'error' ) );
				}
				return $data;
			},
		);
		if ( ! empty( $t['destructive'] ) ) {
			$row['destructive'] = true;
		}
		$tools[ 'flxg_' . $name ] = $row;
	}
	return $tools;
}

/**
 * Eski sunucu talimatının görev kuralları. Sunucu kimliği Flux AI'de kalır.
 *
 * @param mixed $lines
 * @return array
 */
function flxg_mcp_instruction_lines( $lines ) {
	$lines   = is_array( $lines ) ? $lines : array();
	$lines[] = 'Flux Tasks: projects contain folders and lists; tasks live in lists. Call flxg_list_projects first to learn list ids and statuses.';
	$lines[] = 'Flux Tasks dates are site-local (' . wp_timezone_string() . '), format YYYY-MM-DD or YYYY-MM-DDTHH:MM. Today is ' . flxg_today() . '.';
	$lines[] = 'Flux Tasks priority: 1 urgent, 2 high, 3 normal, 4 low, 0 none.';
	$lines[] = 'Write Flux Tasks titles and comments in the user\'s language (the site is mostly Turkish).';
	$lines[] = 'flxg_delete_task is permanent; confirm with the user first.';
	return $lines;
}
