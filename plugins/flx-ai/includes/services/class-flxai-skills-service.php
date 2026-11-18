<?php
/**
 * Skills: list, edit, folder files, import and export, tests and the brand kit. GraphQL and the AI tools
 * share this class; the folder and zip work stays in includes/skills.php.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

final class FLXAI_Skills_Service {

	public const CACHE_TAG = 'flx-ai:skills';

	/** The skills page. */
	public static function overview(): FLXAI_Skills_Overview {
		$used = array();
		foreach ( FLXAI_Cast::arr( flxai_task_list( array( 'limit' => 500 ) ) ) as $t ) {
			$meta = FLXAI_Cast::arr( FLXAI_Cast::arr( $t )['meta'] ?? array() );
			$k    = FLXAI_Cast::str( $meta['skill'] ?? '' );
			if ( '' !== $k ) {
				$used[ $k ] = ( $used[ $k ] ?? 0 ) + 1;
			}
		}
		$rows = array();
		foreach ( FLXAI_Cast::arr( flxai_skills_all() ) as $s ) {
			$rows[] = self::summary( FLXAI_Cast::arr( $s ), $used );
		}
		return new FLXAI_Skills_Overview( $rows, (bool) flxai_skills_restrict_tools() );
	}

	/**
	 * @param array<mixed> $s    Skill from flxai_skills_all().
	 * @param array<mixed> $used Tasks per skill slug.
	 */
	public static function summary( array $s, array $used = array() ): FLXAI_Skill_Summary {
		$slug  = FLXAI_Cast::str( $s['slug'] ?? '' );
		$files = FLXAI_Cast::arr( flxai_skill_files( $s ) );
		return new FLXAI_Skill_Summary(
			$slug,
			FLXAI_Cast::str( $s['name'] ?? '' ),
			FLXAI_Cast::str( $s['description'] ?? '' ),
			FLXAI_Cast::int( $s['version'] ?? 1, 1 ),
			FLXAI_Cast::str( $s['status'] ?? 'active' ),
			! empty( $s['bundled'] ),
			FLXAI_Cast::str( $s['source'] ?? '' ),
			! empty( $s['changed'] ),
			(bool) flxai_skill_is_saved( $slug ),
			count( $files ),
			(bool) flxai_skill_has_scripts( $s ),
			FLXAI_Cast::int( $used[ $slug ] ?? 0 ),
			wp_nonce_url( admin_url( 'admin.php?page=flx-ai-skills&export=' . $slug ), 'flxai_skill_export' )
		);
	}

	/** One skill with its files, tests and last results. */
	public static function detail( string $slug ): FLXAI_Skill_Detail|WP_Error {
		$s = flxai_skill_get( $slug );
		if ( ! $s ) {
			return self::missing();
		}
		$s     = FLXAI_Cast::arr( $s );
		$sl    = FLXAI_Cast::str( $s['slug'] ?? '' );
		$files = array();
		foreach ( FLXAI_Cast::arr( flxai_skill_files( $s ) ) as $f ) {
			$f       = FLXAI_Cast::arr( $f );
			$path    = FLXAI_Cast::str( $f['path'] ?? '' );
			$files[] = new FLXAI_Skill_File(
				$path,
				FLXAI_Cast::int( $f['size'] ?? 0 ),
				wp_nonce_url( add_query_arg( array( 'skill' => $sl, 'download' => rawurlencode( $path ) ), admin_url( 'admin.php?page=flx-ai-skills' ) ), 'flxai_skill_file' )
			);
		}
		$names = array_keys( FLXAI_Cast::arr( flxai_tools_for_user( get_current_user_id() ) ) );
		return new FLXAI_Skill_Detail(
			self::summary( $s ),
			FLXAI_Cast::str( $s['body'] ?? '' ),
			FLXAI_Cast::strings( $s['tools'] ?? array() ),
			FLXAI_Cast::str( $s['license'] ?? '' ),
			FLXAI_Cast::str( $s['compatibility'] ?? '' ),
			FLXAI_Cast::str( $s['example'] ?? '' ),
			$files,
			array_map( static fn( $n ): string => FLXAI_Cast::str( $n ), $names ),
			self::evals( $s ),
			self::results( $s )
		);
	}

	/**
	 * @param array<mixed> $s Skill.
	 */
	private static function evals( array $s ): FLXAI_Skill_Evals {
		$ev   = FLXAI_Cast::arr( flxai_skill_evals_get( $s ) );
		$trig = array();
		foreach ( FLXAI_Cast::arr( $ev['trigger_evals'] ?? array() ) as $t ) {
			$t      = FLXAI_Cast::arr( $t );
			$trig[] = new FLXAI_Skill_Trigger_Case( FLXAI_Cast::str( $t['query'] ?? '' ), FLXAI_Cast::bool( $t['should_trigger'] ?? true ) );
		}
		$cases = array();
		foreach ( FLXAI_Cast::arr( $ev['evals'] ?? array() ) as $c ) {
			$c       = FLXAI_Cast::arr( $c );
			$cases[] = new FLXAI_Skill_Eval_Case( FLXAI_Cast::str( $c['id'] ?? '' ), FLXAI_Cast::str( $c['prompt'] ?? '' ), FLXAI_Cast::strings( $c['expectations'] ?? array() ) );
		}
		return new FLXAI_Skill_Evals( $trig, $cases );
	}

	/**
	 * @param array<mixed> $s Skill.
	 */
	private static function results( array $s ): FLXAI_Skill_Results {
		$r    = FLXAI_Cast::arr( flxai_skill_eval_results( $s ) );
		$func = array();
		foreach ( FLXAI_Cast::arr( $r['functional'] ?? array() ) as $f ) {
			$func[] = self::func_result( FLXAI_Cast::arr( $f ) );
		}
		$t = $r['trigger'] ?? null;
		return new FLXAI_Skill_Results( is_array( $t ) ? self::trigger_result( $t ) : null, $func );
	}

	/**
	 * @param array<mixed> $t Stored or fresh trigger result.
	 */
	private static function trigger_result( array $t ): FLXAI_Skill_Trigger_Result {
		$rows = array();
		foreach ( FLXAI_Cast::arr( $t['rows'] ?? array() ) as $x ) {
			$x      = FLXAI_Cast::arr( $x );
			$rows[] = new FLXAI_Skill_Trigger_Row(
				FLXAI_Cast::str( $x['query'] ?? '' ),
				FLXAI_Cast::bool( $x['should_trigger'] ?? false ),
				FLXAI_Cast::bool( $x['triggered'] ?? false ),
				FLXAI_Cast::strings( $x['picked'] ?? array() )
			);
		}
		return new FLXAI_Skill_Trigger_Result(
			FLXAI_Cast::int( $t['ts'] ?? 0 ),
			FLXAI_Cast::str( $t['model'] ?? '' ),
			FLXAI_Cast::int( $t['hits'] ?? 0 ),
			FLXAI_Cast::int( $t['total'] ?? 0 ),
			FLXAI_Cast::int( $t['rate'] ?? 0 ),
			FLXAI_Cast::float( $t['cost'] ?? 0 ),
			$rows,
			FLXAI_Cast::bool( $t['partial'] ?? false )
		);
	}

	/**
	 * @param array<mixed> $f Stored or fresh functional result.
	 */
	private static function func_result( array $f ): FLXAI_Skill_Func_Result {
		$checks = array();
		foreach ( FLXAI_Cast::arr( $f['checks'] ?? array() ) as $c ) {
			$c        = FLXAI_Cast::arr( $c );
			$checks[] = new FLXAI_Skill_Check( FLXAI_Cast::str( $c['text'] ?? '' ), FLXAI_Cast::bool( $c['with'] ?? false ), FLXAI_Cast::bool( $c['without'] ?? false ) );
		}
		return new FLXAI_Skill_Func_Result(
			FLXAI_Cast::int( $f['ts'] ?? 0 ),
			FLXAI_Cast::str( $f['id'] ?? '' ),
			FLXAI_Cast::str( $f['prompt'] ?? '' ),
			self::side( FLXAI_Cast::arr( $f['with'] ?? array() ) ),
			self::side( FLXAI_Cast::arr( $f['without'] ?? array() ) ),
			$checks,
			FLXAI_Cast::str( $f['note'] ?? '' ),
			FLXAI_Cast::float( $f['judge_cost'] ?? 0 )
		);
	}

	/**
	 * @param array<mixed> $s Side of a functional result.
	 */
	private static function side( array $s ): FLXAI_Skill_Side {
		return new FLXAI_Skill_Side(
			FLXAI_Cast::int( $s['passed'] ?? 0 ),
			FLXAI_Cast::int( $s['total'] ?? 0 ),
			FLXAI_Cast::int( $s['in'] ?? 0 ),
			FLXAI_Cast::int( $s['out'] ?? 0 ),
			FLXAI_Cast::float( $s['cost'] ?? 0 ),
			FLXAI_Cast::int( $s['ms'] ?? 0 ),
			FLXAI_Cast::str( $s['output'] ?? '' )
		);
	}

	/**
	 * Creates or changes a skill. Fields you leave out keep their value.
	 *
	 * @param array<mixed> $in      Input: slug, name, description, body, tools, license, compatibility, example, active.
	 * @param string       $current Slug of the skill to change; empty creates one.
	 */
	public static function save( array $in, string $current = '' ): FLXAI_Skill_Detail|WP_Error {
		$data = array();
		foreach ( array( 'slug', 'name', 'description', 'body', 'license', 'compatibility', 'example' ) as $k ) {
			if ( array_key_exists( $k, $in ) ) {
				$data[ $k ] = FLXAI_Cast::str( $in[ $k ] );
			}
		}
		if ( array_key_exists( 'tools', $in ) ) {
			$data['tools'] = implode( ' ', FLXAI_Cast::strings( $in['tools'] ) );
		}
		if ( array_key_exists( 'active', $in ) ) {
			$data['status'] = FLXAI_Cast::bool( $in['active'] ) ? 'active' : 'off';
		}
		$s = flxai_skill_save( $data, $current );
		if ( $s instanceof WP_Error ) {
			return self::coded( $s );
		}
		self::changed();
		return self::detail( FLXAI_Cast::str( FLXAI_Cast::arr( $s )['slug'] ?? '' ) );
	}

	/** Turns a skill on or off. */
	public static function toggle( string $slug ): FLXAI_Skill_Detail|WP_Error {
		if ( ! flxai_skill_toggle( $slug ) ) {
			return self::missing();
		}
		self::changed();
		return self::detail( $slug );
	}

	/** Deletes a site skill, or resets a shipped one to the version its plugin ships. */
	public static function delete( string $slug ): ?WP_Error {
		if ( ! flxai_skill_get( $slug ) ) {
			return self::missing();
		}
		flxai_skill_delete( $slug );
		self::changed();
		return null;
	}

	/** Restricts the tools of a message that uses a skill to its allowed-tools list. */
	public static function set_restrict( bool $on ): FLXAI_Skills_Overview {
		update_option( 'flxai_skills_restrict', $on ? 1 : 0, false );
		self::changed();
		return self::overview();
	}

	/** Reads one text file of the folder. */
	public static function file( string $slug, string $path ): FLXAI_Skill_File_Content|WP_Error {
		$s = flxai_skill_get( $slug );
		if ( ! $s ) {
			return self::missing();
		}
		$r = flxai_skill_file_read( FLXAI_Cast::arr( $s ), $path, 0, 2000000 );
		if ( $r instanceof WP_Error ) {
			return self::coded( $r );
		}
		$r = FLXAI_Cast::arr( $r );
		return new FLXAI_Skill_File_Content(
			FLXAI_Cast::str( $r['path'] ?? '' ),
			FLXAI_Cast::int( $r['size'] ?? 0 ),
			FLXAI_Cast::bool( $r['text'] ?? false ),
			FLXAI_Cast::str( $r['content'] ?? '' ),
			FLXAI_Cast::str( $r['note'] ?? '' )
		);
	}

	/** Writes a text file into the folder (a shipped skill is copied to the site first). */
	public static function file_save( string $slug, string $path, string $content ): FLXAI_Skill_Detail|WP_Error {
		return self::file_change( $slug, $path, str_replace( "\r\n", "\n", $content ) );
	}

	/** Deletes a file of the folder. */
	public static function file_delete( string $slug, string $path ): FLXAI_Skill_Detail|WP_Error {
		return self::file_change( $slug, $path, null );
	}

	/** Uploads a file (base64) into one of the folder's sub folders. */
	public static function file_upload( string $slug, string $folder, string $name, string $base64 ): FLXAI_Skill_Detail|WP_Error {
		$bytes = base64_decode( $base64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		if ( false === $bytes || '' === $bytes ) {
			return new WP_Error( 'flxai_skill_file', __( 'Choose a file to upload.', 'flx-ai' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'contentBase64' ) );
		}
		$dir  = trim( sanitize_text_field( $folder ), '/' );
		$path = ( '' !== $dir ? $dir . '/' : '' ) . sanitize_file_name( $name );
		return self::file_change( $slug, $path, $bytes );
	}

	private static function file_change( string $slug, string $path, ?string $content ): FLXAI_Skill_Detail|WP_Error {
		if ( ! flxai_skill_get( $slug ) ) {
			return self::missing();
		}
		$r = flxai_skill_file_write( $slug, $path, $content );
		if ( $r instanceof WP_Error ) {
			return self::coded( $r );
		}
		self::changed();
		return self::detail( $slug );
	}

	/**
	 * Imports a .zip / .skill / SKILL.md file (base64) or pasted SKILL.md text.
	 *
	 * @param string $markdown Pasted SKILL.md.
	 * @param string $base64   File content, base64.
	 */
	public static function import( string $markdown, string $base64, string $name ): FLXAI_Skill_Import_Result|WP_Error {
		if ( '' !== $base64 ) {
			$bytes = base64_decode( $base64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			if ( false === $bytes || strlen( $bytes ) > 20 * MB_IN_BYTES ) {
				return new WP_Error( 'flxai_skill_import', __( 'The file could not be read.', 'flx-ai' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'contentBase64' ) );
			}
			$tmp = wp_tempnam( sanitize_file_name( $name ) );
			file_put_contents( $tmp, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$r = flxai_skill_import_file( $tmp, sanitize_file_name( $name ) );
			wp_delete_file( $tmp );
		} else {
			$r = flxai_skill_import_md( $markdown );
		}
		if ( $r instanceof WP_Error ) {
			return self::coded( $r );
		}
		$r = FLXAI_Cast::arr( $r );
		self::changed();
		return new FLXAI_Skill_Import_Result( FLXAI_Cast::strings( $r['imported'] ?? array() ), FLXAI_Cast::strings( $r['notes'] ?? array() ), self::overview() );
	}

	/**
	 * Saves the tests of a skill.
	 *
	 * @param list<array<mixed>> $triggers Prompts: query, shouldTrigger.
	 * @param list<array<mixed>> $cases    Functional tests: prompt, expectations.
	 */
	public static function evals_save( string $slug, array $triggers, array $cases ): FLXAI_Skill_Detail|WP_Error {
		if ( ! flxai_skill_get( $slug ) ) {
			return self::missing();
		}
		$trig = array();
		foreach ( $triggers as $t ) {
			$trig[] = array(
				'query'          => FLXAI_Cast::str( $t['query'] ?? '' ),
				'should_trigger' => FLXAI_Cast::bool( $t['shouldTrigger'] ?? true ),
			);
		}
		$ev = array();
		foreach ( $cases as $k => $c ) {
			$ev[] = array(
				'id'           => (string) ( $k + 1 ),
				'prompt'       => FLXAI_Cast::str( $c['prompt'] ?? '' ),
				'expectations' => FLXAI_Cast::strings( $c['expectations'] ?? array() ),
			);
		}
		$r = flxai_skill_evals_save( $slug, array( 'trigger_evals' => $trig, 'evals' => $ev ) );
		if ( $r instanceof WP_Error ) {
			return self::coded( $r );
		}
		self::changed();
		return self::detail( $slug );
	}

	/**
	 * Runs the trigger test or one functional test. This calls the chat model and is billed like a short chat.
	 *
	 * @param string $kind trigger or functional.
	 */
	public static function test_run( string $slug, string $kind, string $id ): FLXAI_Skill_Detail|WP_Error {
		if ( ! flxai_skill_get( $slug ) ) {
			return self::missing();
		}
		$r = 'functional' === $kind ? flxai_skill_eval_functional( $slug, $id ) : flxai_skill_eval_trigger( $slug );
		if ( $r instanceof WP_Error ) {
			return self::coded( $r );
		}
		self::changed();
		return self::detail( $slug );
	}

	/** The brand kit. */
	public static function brand(): FLXAI_Brand {
		$raw  = FLXAI_Cast::arr( flxai_brand_raw() );
		$def  = FLXAI_Cast::arr( flxai_brand_defaults() );
		$p    = FLXAI_Cast::arr( $raw['profile'] ?? array() );
		$docs = array();
		foreach ( FLXAI_Cast::arr( $raw['docs'] ?? array() ) as $n => $md ) {
			$docs[] = new FLXAI_Brand_Doc( (string) $n, FLXAI_Cast::str( $md ) );
		}
		$ids  = array_values( array_filter( array_map( static fn( $v ): int => FLXAI_Cast::int( $v ), FLXAI_Cast::arr( $p['partner_ids'] ?? array() ) ) ) );
		$urls = array();
		foreach ( $ids as $i ) {
			$urls[] = (string) wp_get_attachment_url( $i );
		}
		$pal = array();
		foreach ( FLXAI_Cast::arr( $def['theme_palette'] ?? array() ) as $c ) {
			$c     = FLXAI_Cast::arr( $c );
			$pal[] = new FLXAI_Brand_Color( FLXAI_Cast::str( $c['slug'] ?? '' ), FLXAI_Cast::str( $c['name'] ?? '' ), FLXAI_Cast::str( $c['color'] ?? '' ) );
		}
		$logo  = FLXAI_Cast::int( $p['logo_id'] ?? 0 );
		$light = FLXAI_Cast::int( $p['logo_light_id'] ?? 0 );
		return new FLXAI_Brand(
			FLXAI_Cast::str( $p['name'] ?? '' ),
			FLXAI_Cast::str( $p['tagline'] ?? '' ),
			FLXAI_Cast::str( $p['website'] ?? '' ),
			FLXAI_Cast::str( $p['phone'] ?? '' ),
			FLXAI_Cast::str( $p['handle'] ?? '' ),
			FLXAI_Cast::str( $p['color_primary'] ?? '' ),
			FLXAI_Cast::str( $p['color_accent'] ?? '' ),
			FLXAI_Cast::str( $p['color_dark'] ?? '' ),
			FLXAI_Cast::str( $p['color_light'] ?? '' ),
			$logo,
			$logo ? (string) wp_get_attachment_url( $logo ) : '',
			$light,
			$light ? (string) wp_get_attachment_url( $light ) : '',
			$ids,
			$urls,
			FLXAI_Cast::str( $p['notes'] ?? '' ),
			$docs,
			FLXAI_Cast::str( $def['name'] ?? '' ),
			FLXAI_Cast::str( $def['tagline'] ?? '' ),
			FLXAI_Cast::str( $def['website'] ?? '' ),
			$pal
		);
	}

	/**
	 * Saves the brand kit. Fields you leave out keep their value; a document with empty content is deleted.
	 *
	 * @param array<mixed> $in Input: the profile fields in camelCase, docs [{name, content}].
	 */
	public static function brand_save( array $in ): FLXAI_Brand|WP_Error {
		$map = array(
			'name'         => 'name',
			'tagline'      => 'tagline',
			'website'      => 'website',
			'phone'        => 'phone',
			'handle'       => 'handle',
			'colorPrimary' => 'color_primary',
			'colorAccent'  => 'color_accent',
			'colorDark'    => 'color_dark',
			'colorLight'   => 'color_light',
			'logoId'       => 'logo_id',
			'logoLightId'  => 'logo_light_id',
			'partnerIds'   => 'partner_ids',
			'notes'        => 'notes',
		);
		$prof = array();
		foreach ( $map as $camel => $key ) {
			if ( array_key_exists( $camel, $in ) ) {
				$prof[ $key ] = $in[ $camel ];
			}
		}
		$docs = array();
		foreach ( FLXAI_Cast::arr( $in['docs'] ?? array() ) as $d ) {
			$d = FLXAI_Cast::arr( $d );
			$docs[ FLXAI_Cast::str( $d['name'] ?? '' ) ] = FLXAI_Cast::str( $d['content'] ?? '' );
		}
		$r = flxai_brand_save( $prof, $docs );
		if ( $r instanceof WP_Error ) {
			return self::coded( $r );
		}
		self::changed();
		return self::brand();
	}

	public static function changed(): void {
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( array( self::CACHE_TAG ) );
		}
	}

	private static function missing(): WP_Error {
		return new WP_Error( 'flxai_not_found', __( 'Skill not found.', 'flx-ai' ), array( 'graphql_code' => 'NOT_FOUND' ) );
	}

	/** Gives the older WP_Error a GraphQL code (404 → NOT_FOUND, else INVALID_INPUT) and the input field. */
	private static function coded( WP_Error $e ): WP_Error {
		$d      = FLXAI_Cast::arr( $e->get_error_data() );
		$status = FLXAI_Cast::int( $d['status'] ?? 400 );
		$field  = array(
			'flxai_skill_name'        => 'slug',
			'flxai_skill_taken'       => 'slug',
			'flxai_skill_description' => 'description',
			'flxai_skill_chars'       => 'description',
			'flxai_skill_compat'      => 'compatibility',
			'flxai_skill_body'        => 'body',
			'flxai_skill_path'        => 'path',
			'flxai_brand_color'       => 'colorPrimary',
		)[ $e->get_error_code() ] ?? '';
		$out = array( 'graphql_code' => 404 === $status ? 'NOT_FOUND' : 'INVALID_INPUT' );
		if ( '' !== $field ) {
			$out['field'] = $field;
		}
		return new WP_Error( (string) $e->get_error_code(), $e->get_error_message(), $out );
	}
}
