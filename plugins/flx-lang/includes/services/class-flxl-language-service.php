<?php
/**
 * Flux Lang service: the one place that reads and changes languages, translations and the Languages
 * settings. The classic settings page, GraphQL, REST fields and the Flux AI tools call this.
 * Permission checks stay with the caller (manage_options for settings, edit_post for a content), as before.
 *
 * Errors are WP_Error with `graphql_code` INVALID_INPUT / NOT_FOUND / FORBIDDEN and the input `field`.
 *
 * @package flx-lang
 */

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type FlxlLanguageInput array{code: string, name?: string|null, locale?: string|null, flag?: string|null, active?: bool|null}
 * @phpstan-type FlxlTextInput array{lang: string, key: string, value: string}
 * @phpstan-type FlxlSettingsInput array{languages?: list<FlxlLanguageInput>|null, add_language?: string|null, default?: string|null, post_types?: list<string>|null, fallback?: bool|null, suggest?: string|null, suggest_to?: string|null, home_countries?: list<string>|null, switcher?: string|null, switcher_place?: string|null, texts?: list<FlxlTextInput>|null}
 */
final class FLXL_Language_Service {

	public const TAG_LANGUAGES = 'flx-lang:languages';

	/** Code pattern of a language (address prefix). */
	public const CODE_PATTERN = '/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/';

	public const SUGGEST     = array( 'off', 'banner', 'redirect' );
	public const SWITCHER    = array( 'code', 'name', 'flag' );
	public const SWITCHER_AT = array( 'footer', 'header', 'both' );

	/** Seconds a finished translation job stays readable. */
	public const JOB_DONE_TTL = 600;

	/* -----------------------------------------------------------------------
	 * Small typed readers over the legacy (untyped) helpers
	 * --------------------------------------------------------------------- */

	/**
	 * @param mixed $v Value.
	 */
	private static function str( $v ): string {
		return is_scalar( $v ) ? (string) $v : '';
	}

	/**
	 * @param mixed $v Value.
	 * @return array<mixed>
	 */
	private static function arr( $v ): array {
		return is_array( $v ) ? $v : array();
	}

	/**
	 * @param mixed $v Value.
	 */
	private static function num( $v ): int {
		return is_numeric( $v ) ? (int) $v : 0;
	}

	/**
	 * @param mixed $v Value.
	 * @return list<string>
	 */
	private static function strings( $v ): array {
		$out = array();
		foreach ( self::arr( $v ) as $x ) {
			$out[] = self::str( $x );
		}
		return $out;
	}

	/**
	 * Stored settings as a typed array.
	 *
	 * @return array<string,mixed>
	 */
	private static function raw_settings(): array {
		$s = array();
		foreach ( self::arr( flxl_settings() ) as $k => $v ) {
			$s[ (string) $k ] = $v;
		}
		return $s;
	}

	/**
	 * Hint (error tag) for the cache of languages.
	 */
	private static function purge(): void {
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( array( self::TAG_LANGUAGES ) );
		}
	}

	/* -----------------------------------------------------------------------
	 * Languages
	 * --------------------------------------------------------------------- */

	/**
	 * Languages of the site, default first.
	 *
	 * @param bool $all Inactive languages too.
	 * @return list<FLXL_Language>
	 */
	public static function languages( bool $all = false ): array {
		$def = self::str( flxl_default_language() );
		$out = array();
		foreach ( self::arr( flxl_languages( $all ) ) as $code => $l ) {
			$l     = self::arr( $l );
			$code  = (string) $code;
			$out[] = new FLXL_Language(
				$code,
				self::str( $l['name'] ?? $code ),
				self::str( $l['locale'] ?? $code ),
				self::str( $l['flag'] ?? '' ),
				! empty( $l['active'] ),
				$code === $def,
				$code === $def ? '/' : '/' . $code . '/',
				self::str( flxl_hreflang( $code ) ),
			);
		}
		return $out;
	}

	public static function language( string $code ): ?FLXL_Language {
		foreach ( self::languages( true ) as $l ) {
			if ( $l->code === $code ) {
				return $l;
			}
		}
		return null;
	}

	/* -----------------------------------------------------------------------
	 * Contents: language and translations
	 * --------------------------------------------------------------------- */

	/**
	 * Language of a content; null for a type that is not translated and for shared pages.
	 */
	public static function post_language( int $post_id ): ?string {
		$code = self::str( flxl_post_language( $post_id ) );
		return '' === $code ? null : $code;
	}

	/**
	 * Language versions of a content, in language order, including the content itself.
	 * Drafts and private versions are listed only for people who can edit them.
	 *
	 * @return list<FLXL_Translation>
	 */
	public static function translations( int $post_id ): array {
		$out = array();
		foreach ( self::arr( flxl_get_translations( $post_id ) ) as $code => $id ) {
			$id = self::num( $id );
			$p  = get_post( $id );
			if ( ! $p ) {
				continue;
			}
			$can_edit = current_user_can( 'edit_post', $id );
			if ( 'publish' !== $p->post_status && ! $can_edit ) {
				continue;
			}
			$url = 'publish' === $p->post_status ? flxl_strict_links(
				static function () use ( $p ) {
					return get_permalink( $p );
				}
			) : null;
			$edit = $can_edit ? get_edit_post_link( $id, 'raw' ) : null;
			$out[] = new FLXL_Translation(
				(string) $code,
				$id,
				get_the_title( $p ),
				$p->post_status,
				is_string( $url ) && '' !== $url ? $url : null,
				is_string( $edit ) && '' !== $edit ? $edit : null,
				$id === $post_id,
			);
		}
		return $out;
	}

	/**
	 * Sets the language of a content. A content that already shares its group with another one in the
	 * new language leaves the group first (same rule as the editor's Language box).
	 *
	 * @return true|WP_Error
	 */
	public static function set_post_language( int $post_id, string $code ) {
		$post = get_post( $post_id );
		if ( ! $post || ! flxl_is_translatable_type( $post->post_type ) ) {
			return new WP_Error( 'flxl_source', __( 'Source content not found or this type is not translated.', 'flx-lang' ), array( 'graphql_code' => 'NOT_FOUND', 'field' => 'id' ) );
		}
		if ( ! flxl_is_language( $code ) ) {
			return new WP_Error( 'flxl_lang', __( 'Language is not active.', 'flx-lang' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'lang' ) );
		}
		if ( flxl_is_shared( $post_id ) ) {
			return new WP_Error( 'flxl_shared', __( 'Shared page: the same in every language, interface in the visitor\'s language.', 'flx-lang' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'id' ) );
		}
		$has_term = is_array( get_the_terms( $post_id, FLXL_TAX ) );
		if ( $code === self::str( flxl_post_language( $post_id ) ) && ( $has_term || $code === self::str( flxl_default_language() ) ) ) {
			return true;
		}
		$tr = self::arr( flxl_get_translations( $post_id ) );
		if ( isset( $tr[ $code ] ) && self::num( $tr[ $code ] ) !== $post_id ) {
			flxl_unlink_translation( $post_id );
		}
		flxl_set_post_language( $post_id, $code );
		return true;
	}

	/**
	 * Creates (or finds) the translation of a content in a language: a draft copy of the source.
	 *
	 * @return FLXL_Translation|WP_Error
	 */
	public static function create_translation( int $source_id, string $code ) {
		$id = flxl_save_translation( $source_id, $code );
		if ( $id instanceof WP_Error ) {
			return self::coded( $id, 'lang' );
		}
		foreach ( self::translations( $source_id ) as $t ) {
			if ( $t->id === self::num( $id ) ) {
				return $t;
			}
		}
		return new WP_Error( 'flxl_source', __( 'Source content not found or this type is not translated.', 'flx-lang' ), array( 'graphql_code' => 'NOT_FOUND', 'field' => 'sourceId' ) );
	}

	/**
	 * Gives a legacy error a GraphQL code.
	 *
	 * @param WP_Error $e     Error.
	 * @param string   $field Input field the problem belongs to.
	 */
	private static function coded( WP_Error $e, string $field ): WP_Error {
		$map  = array(
			'flxl_cap'    => 'FORBIDDEN',
			'flxl_source' => 'NOT_FOUND',
			'flxl_job'    => 'NOT_FOUND',
		);
		$code = (string) $e->get_error_code();
		return new WP_Error( $code, $e->get_error_message(), array( 'graphql_code' => $map[ $code ] ?? 'INVALID_INPUT', 'field' => $field ) );
	}

	/* -----------------------------------------------------------------------
	 * Visitor country
	 * --------------------------------------------------------------------- */

	/**
	 * Country of the current request, from the first CDN / GeoIP header the server sends.
	 */
	public static function geo(): FLXL_Geo {
		$g = self::arr( flxl_geo_country() );
		return new FLXL_Geo( self::str( $g['country'] ?? '' ), self::str( $g['source'] ?? '' ) );
	}

	/**
	 * The visitor's country (public read) and a once-a-day note of which header carried it (settings page hint).
	 */
	public static function visitor_country(): FLXL_Geo {
		$g    = self::geo();
		$last = get_option( 'flxl_geo_seen' );
		$now  = '' !== $g->source ? $g->source : 'none';
		if ( ! is_array( $last ) || self::str( $last['source'] ?? '' ) !== $now || ( time() - self::num( $last['t'] ?? 0 ) ) > DAY_IN_SECONDS ) {
			update_option( 'flxl_geo_seen', array( 'source' => $now, 't' => time() ), false );
		}
		return $g;
	}

	/* -----------------------------------------------------------------------
	 * Settings
	 * --------------------------------------------------------------------- */

	/**
	 * Site text fields that can be translated.
	 *
	 * @return array<string,array<mixed>>
	 */
	private static function raw_text_fields(): array {
		$out = array();
		foreach ( self::arr( flxl_text_fields() ) as $k => $f ) {
			$out[ (string) $k ] = self::arr( $f );
		}
		return $out;
	}

	public static function settings(): FLXL_Settings {
		$s      = self::raw_settings();
		$def    = self::str( flxl_default_language() );
		$langs  = self::languages( true );
		$by     = array();
		foreach ( $langs as $l ) {
			$by[ $l->code ] = true;
		}
		$texts = array();
		foreach ( self::arr( $s['texts'] ?? array() ) as $code => $vals ) {
			foreach ( self::arr( $vals ) as $k => $v ) {
				$texts[] = new FLXL_Site_Text( (string) $code, (string) $k, self::str( $v ) );
			}
		}
		$catalog = array();
		foreach ( self::arr( flxl_catalog() ) as $code => $c ) {
			$c = self::arr( $c );
			if ( ! isset( $by[ (string) $code ] ) ) {
				$catalog[] = new FLXL_Catalog_Entry( (string) $code, self::str( $c['name'] ?? $code ), self::str( $c['locale'] ?? '' ), self::str( $c['flag'] ?? '' ) );
			}
		}
		$selected = self::strings( $s['post_types'] ?? array() );
		$choices  = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
			if ( in_array( $pt->name, array( 'attachment', 'product' ), true ) ) {
				continue;
			}
			$choices[] = new FLXL_Post_Type_Choice( $pt->name, self::str( $pt->labels->name ), in_array( $pt->name, $selected, true ) );
		}
		$fields = array();
		foreach ( self::raw_text_fields() as $k => $f ) {
			$fields[] = new FLXL_Text_Field( $k, self::str( $f['label'] ?? $k ), ! empty( $f['textarea'] ), self::text_placeholder( $f ) );
		}
		return new FLXL_Settings(
			$def,
			$langs,
			$selected,
			! empty( $s['fallback'] ),
			in_array( $s['suggest'] ?? '', self::SUGGEST, true ) ? self::str( $s['suggest'] ) : 'redirect',
			self::str( $s['suggest_to'] ?? 'en' ),
			self::strings( $s['home_countries'] ?? array() ),
			in_array( $s['switcher'] ?? '', self::SWITCHER, true ) ? self::str( $s['switcher'] ) : 'code',
			in_array( $s['switcher_place'] ?? '', self::SWITCHER_AT, true ) ? self::str( $s['switcher_place'] ) : 'footer',
			$texts,
			$catalog,
			$choices,
			$fields,
			self::geo(),
			(bool) flxl_ai_available(),
		);
	}

	/**
	 * Value of a text field in the default language (hint under the empty input).
	 *
	 * @param array<mixed> $f Field definition.
	 */
	private static function text_placeholder( array $f ): string {
		return self::str(
			flxl_raw(
				static function () use ( $f ) {
					$v = get_option( self::str( $f['option'] ?? '' ) );
					return empty( $f['key'] ) ? self::str( $v ) : self::str( is_array( $v ) ? ( $v[ self::str( $f['key'] ) ] ?? '' ) : '' );
				}
			)
		);
	}

	/**
	 * Checks a settings input; every problem is a separate error with its input field.
	 *
	 * @param FlxlSettingsInput $in Input.
	 * @return WP_Error|null Null when valid.
	 */
	public static function validate_settings( array $in ): ?WP_Error {
		$e      = new WP_Error();
		$bad    = static function ( string $field, string $message ) use ( $e ): void {
			// One code per field: WP_Error keeps a single data value per code.
			$e->add( 'flxl_invalid_' . $field, $message, array( 'graphql_code' => 'INVALID_INPUT', 'field' => $field ) );
		};
		$cat    = self::arr( flxl_catalog() );
		$codes  = array();
		$source = isset( $in['languages'] ) ? $in['languages'] : null;
		if ( null === $source ) {
			foreach ( self::languages( true ) as $l ) {
				$codes[ $l->code ] = true;
			}
		} else {
			foreach ( $source as $i => $row ) {
				$code = (string) $row['code'];
				if ( 1 !== preg_match( self::CODE_PATTERN, $code ) ) {
					$bad( 'languages.' . $i . '.code', __( 'A language code has 2–3 letters and, optionally, a region such as pt-br.', 'flx-lang' ) );
					continue;
				}
				if ( isset( $codes[ $code ] ) ) {
					$bad( 'languages.' . $i . '.code', __( 'A language can be listed only once.', 'flx-lang' ) );
					continue;
				}
				$codes[ $code ] = true;
				$locale         = self::str( $row['locale'] ?? '' );
				if ( '' !== $locale && 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $locale ) ) {
					$bad( 'languages.' . $i . '.locale', __( 'A WordPress locale has letters, digits and an underscore, e.g. en_US.', 'flx-lang' ) );
				}
			}
		}
		$add = self::str( $in['add_language'] ?? '' );
		if ( '' !== $add ) {
			if ( ! isset( $cat[ $add ] ) ) {
				$bad( 'addLanguage', __( 'This language is not in the catalog.', 'flx-lang' ) );
			} else {
				$codes[ $add ] = true;
			}
		}
		$given_default = isset( $in['default'] ) && '' !== $in['default'];
		$default       = $given_default ? $in['default'] : self::str( flxl_default_language() );
		if ( ! isset( $codes[ $default ] ) ) {
			$bad( $given_default ? 'defaultLanguage' : 'languages', __( 'The default language must be one of the listed languages and cannot be removed.', 'flx-lang' ) );
		}
		if ( isset( $in['suggest'] ) && ! in_array( $in['suggest'], self::SUGGEST, true ) ) {
			$bad( 'suggest', __( 'Unknown suggestion mode.', 'flx-lang' ) );
		}
		if ( isset( $in['suggest_to'] ) && '' !== $in['suggest_to'] && ! isset( $codes[ $in['suggest_to'] ] ) ) {
			$bad( 'suggestTo', __( 'The suggested language must be one of the listed languages.', 'flx-lang' ) );
		}
		if ( isset( $in['switcher'] ) && ! in_array( $in['switcher'], self::SWITCHER, true ) ) {
			$bad( 'switcher', __( 'Unknown switcher style.', 'flx-lang' ) );
		}
		if ( isset( $in['switcher_place'] ) && ! in_array( $in['switcher_place'], self::SWITCHER_AT, true ) ) {
			$bad( 'switcherPlace', __( 'Unknown switcher position.', 'flx-lang' ) );
		}
		foreach ( $in['home_countries'] ?? array() as $i => $c ) {
			if ( 1 !== preg_match( '/^[A-Za-z]{2}$/', $c ) ) {
				$bad( 'homeCountries.' . $i, __( 'Use two-letter country codes (TR, CY…).', 'flx-lang' ) );
			}
		}
		foreach ( $in['post_types'] ?? array() as $i => $pt ) {
			if ( ! post_type_exists( $pt ) ) {
				$bad( 'postTypes.' . $i, __( 'This content type does not exist.', 'flx-lang' ) );
			}
		}
		$fields = self::raw_text_fields();
		$def    = $default;
		foreach ( $in['texts'] ?? array() as $i => $t ) {
			if ( ! isset( $fields[ $t['key'] ] ) ) {
				$bad( 'texts.' . $i . '.key', __( 'Unknown site text.', 'flx-lang' ) );
			}
			if ( ! isset( $codes[ $t['lang'] ] ) || $t['lang'] === $def ) {
				$bad( 'texts.' . $i . '.lang', __( 'Site texts belong to a language other than the default one.', 'flx-lang' ) );
			}
		}
		return $e->has_errors() ? $e : null;
	}

	/**
	 * Saves the Languages settings. Absent keys stay as they are; `languages` and `texts`, when given,
	 * replace the whole list (a language that is not listed is removed). Same cleaning as the classic form.
	 *
	 * @param FlxlSettingsInput $in     Input.
	 * @param bool              $strict Refuse invalid input (GraphQL). The classic form passes false: bad values are dropped quietly, as before.
	 * @return FLXL_Settings|WP_Error
	 */
	public static function save_settings( array $in, bool $strict = true ) {
		$invalid = $strict ? self::validate_settings( $in ) : null;
		if ( $invalid instanceof WP_Error ) {
			return $invalid;
		}
		$old = self::raw_settings();
		$s   = $old;
		$cat = self::arr( flxl_catalog() );

		$old_langs = array();
		foreach ( self::arr( $old['languages'] ?? array() ) as $code => $l ) {
			$old_langs[ (string) $code ] = self::arr( $l );
		}
		$langs = array();
		if ( isset( $in['languages'] ) ) {
			foreach ( $in['languages'] as $row ) {
				$code = sanitize_key( $row['code'] );
				if ( 1 !== preg_match( self::CODE_PATTERN, $code ) ) {
					continue;
				}
				$langs[ $code ] = array(
					'name'   => sanitize_text_field( self::str( $row['name'] ?? $code ) ),
					'locale' => (string) preg_replace( '/[^A-Za-z0-9_]/', '', self::str( $row['locale'] ?? '' ) ),
					'flag'   => sanitize_text_field( self::str( $row['flag'] ?? '' ) ),
					'active' => empty( $row['active'] ) ? 0 : 1,
				);
			}
		} else {
			$langs = $old_langs;
		}
		$add = sanitize_key( self::str( $in['add_language'] ?? '' ) );
		if ( '' !== $add && isset( $cat[ $add ] ) && ! isset( $langs[ $add ] ) ) {
			$langs[ $add ] = self::arr( $cat[ $add ] ) + array( 'active' => 0 ); // Added inactive: prepare the content first.
		}
		$old_default = self::str( $old['default'] ?? '' );
		$def         = sanitize_key( self::str( $in['default'] ?? $old_default ) );
		if ( ! isset( $langs[ $def ] ) ) {
			$def = isset( $langs[ $old_default ] ) ? $old_default : (string) array_key_first( $langs );
		}
		if ( isset( $langs[ $def ] ) ) {
			$langs[ $def ]['active'] = 1;
		}
		$s['languages'] = $langs ? $langs : $old_langs;
		$s['default']   = $def;

		if ( isset( $in['post_types'] ) ) {
			$s['post_types'] = array_values( array_filter( array_map( 'sanitize_key', $in['post_types'] ), 'post_type_exists' ) );
		}
		if ( isset( $in['fallback'] ) ) {
			$s['fallback'] = $in['fallback'] ? 1 : 0;
		}
		if ( isset( $in['suggest'] ) ) {
			$s['suggest'] = in_array( $in['suggest'], self::SUGGEST, true ) ? $in['suggest'] : 'redirect';
		}
		if ( isset( $in['suggest_to'] ) ) {
			$to             = sanitize_key( $in['suggest_to'] );
			$s['suggest_to'] = isset( $langs[ $to ] ) ? $to : 'en';
		}
		if ( isset( $in['switcher'] ) ) {
			$s['switcher'] = in_array( $in['switcher'], self::SWITCHER, true ) ? $in['switcher'] : 'code';
		}
		if ( isset( $in['switcher_place'] ) ) {
			$s['switcher_place'] = in_array( $in['switcher_place'], self::SWITCHER_AT, true ) ? $in['switcher_place'] : 'footer';
		}
		if ( isset( $in['home_countries'] ) ) {
			$s['home_countries'] = array_values(
				array_unique(
					array_filter(
						array_map( 'strtoupper', $in['home_countries'] ),
						static fn( string $c ): bool => 1 === preg_match( '/^[A-Z]{2}$/', $c )
					)
				)
			);
		}
		if ( isset( $in['texts'] ) ) {
			$fields = self::raw_text_fields();
			$texts  = array();
			foreach ( $in['texts'] as $t ) {
				$code = sanitize_key( $t['lang'] );
				if ( ! isset( $s['languages'][ $code ] ) || $code === $def || ! isset( $fields[ $t['key'] ] ) || '' === trim( $t['value'] ) ) {
					continue;
				}
				$texts[ $code ][ $t['key'] ] = empty( $fields[ $t['key'] ]['textarea'] ) ? sanitize_text_field( $t['value'] ) : sanitize_textarea_field( $t['value'] );
			}
			$s['texts'] = $texts;
		}

		update_option( 'flxl_settings', $s, true );
		flxl_ensure_terms();
		flxl_index_flush();
		self::purge();
		return self::settings();
	}

	/* -----------------------------------------------------------------------
	 * AI translation jobs (two steps: start, then one step per request)
	 * --------------------------------------------------------------------- */

	/**
	 * @param array<mixed> $job Stored job.
	 */
	private static function job_of( string $key, array $job ): FLXL_Job {
		$parts  = self::arr( $job['parts'] ?? array() );
		$total  = ! empty( $job['total'] ) ? self::num( $job['total'] ) : count( $parts ) + 1;
		$status = ! empty( $job['finished'] ) ? 'done' : ( ! empty( $job['failed'] ) ? 'failed' : 'running' );
		$edit   = get_edit_post_link( self::num( $job['post'] ?? 0 ), 'raw' );
		return new FLXL_Job(
			$key,
			$status,
			isset( $job['step'] ) ? self::num( $job['step'] ) : -1,
			$total,
			self::num( $job['src'] ?? 0 ),
			self::num( $job['post'] ?? 0 ),
			self::str( $job['to'] ?? '' ),
			self::strings( $job['errors'] ?? array() ),
			is_string( $edit ) && '' !== $edit ? $edit : null,
		);
	}

	/**
	 * Starts an AI translation: creates the draft translation and the list of parts.
	 *
	 * @return FLXL_Job|WP_Error
	 */
	public static function translate_start( int $source_id, string $code ) {
		$r = flxl_ai_job_start( $source_id, $code );
		if ( $r instanceof WP_Error ) {
			return self::coded( $r, 'lang' );
		}
		return self::job( self::str( self::arr( $r )['job'] ?? '' ) ) ?? new WP_Error( 'flxl_job', __( 'Translation job not found or expired.', 'flx-lang' ), array( 'graphql_code' => 'NOT_FOUND', 'field' => 'jobId' ) );
	}

	/**
	 * Runs one step of a job (step 0 is the title/excerpt/SEO part; the last one writes the body).
	 *
	 * @return FLXL_Job|WP_Error
	 */
	public static function translate_step( string $key, int $step ) {
		$r = flxl_ai_job_step( $key, $step );
		if ( $r instanceof WP_Error ) {
			return self::coded( $r, 'jobId' );
		}
		return self::job( $key ) ?? new WP_Error( 'flxl_job', __( 'Translation job not found or expired.', 'flx-lang' ), array( 'graphql_code' => 'NOT_FOUND', 'field' => 'jobId' ) );
	}

	/**
	 * A job of the current user, or null.
	 */
	public static function job( string $key ): ?FLXL_Job {
		$job = get_transient( 'flxl_job_' . $key );
		if ( ! is_array( $job ) || self::num( $job['user'] ?? 0 ) !== get_current_user_id() ) {
			return null;
		}
		return self::job_of( $key, $job );
	}

	/**
	 * Language the AI translation of a source needs (its own language), for permission checks.
	 */
	public static function can_translate( int $source_id ): bool {
		$p = get_post( $source_id );
		return $p instanceof WP_Post && flxl_is_translatable_type( $p->post_type ) && current_user_can( 'edit_post', $source_id );
	}
}
