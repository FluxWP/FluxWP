<?php
/**
 * Studio service: galleries, folders, people, references, templates, settings, generation and the
 * dashboard. REST (flx-studio/v1), GraphQL and the FluxWP app all call these methods, so access rules
 * and validation live in one place. Generation itself (queue, dispatch, polling) stays in includes/ai.php.
 *
 * Errors are WP_Error (status → GraphQL code: 400 INVALID_INPUT, 403 FORBIDDEN, 404 NOT_FOUND …).
 *
 * @package flx-studio
 */

defined( 'ABSPATH' ) || exit;

/**
 * Studio data and actions of the signed-in user.
 */
final class FLXST_Service {

	public const TAG_ITEMS     = 'flx-studio:items';
	public const TAG_FOLDERS   = 'flx-studio:folders';
	public const TAG_PEOPLE    = 'flx-studio:people';
	public const TAG_REFS      = 'flx-studio:refs';
	public const TAG_TEMPLATES = 'flx-studio:templates';
	public const TAG_SETTINGS  = 'flx-studio:settings';
	public const TAG_STATS     = 'flx-studio:stats';

	/** Reference roles the screen offers ('' = general). */
	public const REF_ROLES = array( '', 'identity', 'style', 'product', 'background', 'composition', 'first_frame', 'last_frame' );

	/**
	 * JSON text → array (anything else: empty).
	 *
	 * @param mixed $s JSON text.
	 * @return array<string,mixed>
	 */
	public static function decode( $s ): array {
		return FLXST_Util::arr( json_decode( FLXST_Util::str( $s ), true ) );
	}

	/**
	 * Display name of a user ('' for a deleted one).
	 *
	 * @param int $id User id.
	 */
	public static function user_name( int $id ): string {
		/** @var array<int,string> $names */
		static $names = array();
		if ( ! array_key_exists( $id, $names ) ) {
			$u            = get_userdata( $id );
			$names[ $id ] = $u ? $u->display_name : '';
		}
		return $names[ $id ];
	}

	/**
	 * A not-found error.
	 */
	public static function not_found(): WP_Error {
		return new WP_Error( 'flxst_not_found', __( 'Record not found.', 'flx-studio' ), array( 'status' => 404 ) );
	}

	/**
	 * Whether the current user can open Studio.
	 */
	public static function can_use(): bool {
		return flxst_can_use();
	}

	/**
	 * Whether the current user sees every gallery, the dashboard and the settings.
	 */
	public static function can_manage(): bool {
		return flxst_can_manage();
	}

	/* ------------------------------------------------------------------ Workspace */

	/**
	 * Models and providers of Flux AI (empty without Flux AI).
	 */
	public static function catalog(): FLXST_Catalog {
		$cat    = FLXST_Util::arr( flxst_models() );
		$provs  = array();
		foreach ( FLXST_Util::arr( $cat['providers'] ?? null ) as $id => $p ) {
			$p       = FLXST_Util::arr( $p );
			$provs[] = new FLXST_Provider( $id, FLXST_Util::str( $p['label'] ?? '', $id ), ! empty( $p['ready'] ) );
		}
		$models = array();
		foreach ( FLXST_Util::arr( $cat['models'] ?? null ) as $id => $m ) {
			$m       = FLXST_Util::arr( $m );
			$price   = array();
			foreach ( FLXST_Util::arr( $m['price'] ?? null ) as $res => $amount ) {
				if ( is_numeric( $amount ) ) {
					$price[ $res ] = (float) $amount;
				}
			}
			$dur = array();
			if ( is_array( $m['duration'] ?? null ) ) {
				foreach ( $m['duration'] as $d ) {
					$dur[] = FLXST_Util::int( $d );
				}
			}
			$models[] = new FLXST_Model(
				$id,
				FLXST_Util::str( $m['label'] ?? '', $id ),
				'video' === ( $m['kind'] ?? '' ) ? 'video' : 'image',
				self::strings( $m['routes'] ?? null ),
				self::strings( $m['providers'] ?? null ),
				FLXST_Util::str( $m['auto'] ?? '' ),
				self::strings( $m['aspects'] ?? null ),
				self::strings( $m['resolutions'] ?? null ),
				self::strings( $m['qualities'] ?? null ),
				$dur,
				FLXST_Util::int( $m['max_refs'] ?? 14, 14 ),
				FLXST_Util::int( $m['max_n'] ?? 1, 1 ),
				! empty( $m['mask'] ),
				$price
			);
		}
		return new FLXST_Catalog( $models, $provs, FLXST_Util::str( $cat['default_image'] ?? '' ), FLXST_Util::str( $cat['default_video'] ?? '' ) );
	}

	/**
	 * Strings of a list value.
	 *
	 * @param mixed $v List.
	 * @return list<string>
	 */
	private static function strings( $v ): array {
		$out = array();
		foreach ( is_array( $v ) ? $v : array() as $x ) {
			if ( is_scalar( $x ) ) {
				$out[] = (string) $x;
			}
		}
		return $out;
	}

	/**
	 * Studio settings; the default models are the ones the screen starts with.
	 */
	public static function settings(): FLXST_Settings {
		$s   = flxst_settings();
		$cat = self::catalog();
		$img = FLXST_Util::str( $s['image_model'] ?? '' );
		$vid = FLXST_Util::str( $s['video_model'] ?? '' );
		return new FLXST_Settings(
			'' !== $img ? $img : $cat->default_image,
			'' !== $vid ? $vid : $cat->default_video,
			flxst_can_manage() ? FLXST_Util::str( $s['text_model'] ?? '' ) : '',
			FLXST_Util::int( $s['max_n'] ?? 4, 4 ),
			FLXST_Util::int( $s['max_batch'] ?? 20, 20 ),
			(bool) FLXST_Util::int( $s['shared_people'] ?? 1, 1 )
		);
	}

	/**
	 * Saves settings (administrators of Studio). Only the keys given are changed; values are clamped.
	 *
	 * @param array<string,mixed> $in image_model, video_model, text_model, max_n, max_batch, shared_people.
	 */
	public static function settings_update( array $in ): FLXST_Settings {
		$s = FLXST_Util::arr( flxst_settings() );
		foreach ( array( 'image_model', 'video_model', 'text_model' ) as $k ) {
			if ( array_key_exists( $k, $in ) && null !== $in[ $k ] ) {
				$s[ $k ] = sanitize_text_field( FLXST_Util::str( $in[ $k ] ) );
			}
		}
		foreach ( array( 'max_n', 'max_batch', 'shared_people' ) as $k ) {
			if ( array_key_exists( $k, $in ) && null !== $in[ $k ] ) {
				$s[ $k ] = is_bool( $in[ $k ] ) ? (int) $in[ $k ] : FLXST_Util::int( $in[ $k ] );
			}
		}
		update_option( 'flxst_settings', self::sanitize_settings( $s ), false );
		FLXST_Util::purge( array( self::TAG_SETTINGS ) );
		return self::settings();
	}

	/**
	 * Clamps the settings option (every write: Studio, flxSettingsUpdate, presets).
	 *
	 * @param array<string,mixed> $s Settings.
	 * @return array<string,mixed>
	 */
	public static function sanitize_settings( array $s ): array {
		$d = FLXST_Util::arr( flxst_defaults() );
		return array(
			'image_model'   => sanitize_text_field( FLXST_Util::str( $s['image_model'] ?? $d['image_model'] ) ),
			'video_model'   => sanitize_text_field( FLXST_Util::str( $s['video_model'] ?? $d['video_model'] ) ),
			'text_model'    => sanitize_text_field( FLXST_Util::str( $s['text_model'] ?? $d['text_model'] ) ),
			'max_n'         => max( 1, min( 8, FLXST_Util::int( $s['max_n'] ?? $d['max_n'], 4 ) ) ),
			'max_batch'     => max( 1, min( 40, FLXST_Util::int( $s['max_batch'] ?? $d['max_batch'], 20 ) ) ),
			'shared_people' => ! empty( $s['shared_people'] ) ? 1 : 0,
		);
	}

	/**
	 * Flux AI spending limits (empty without Flux AI).
	 *
	 * @param bool $fresh Recount instead of using the cached total.
	 * @return list<FLXST_Budget>
	 */
	public static function budget( bool $fresh = false ): array {
		if ( ! function_exists( 'flxai_budget_status' ) ) {
			return array();
		}
		$out = array();
		foreach ( FLXST_Util::arr( flxai_budget_status( $fresh ) ) as $period => $row ) {
			$row   = FLXST_Util::arr( $row );
			$out[] = new FLXST_Budget(
				$period,
				FLXST_Util::str( $row['label'] ?? '' ),
				is_numeric( $row['limit'] ?? null ) ? (float) $row['limit'] : 0.0,
				is_numeric( $row['spent'] ?? null ) ? (float) $row['spent'] : 0.0,
				is_numeric( $row['pct'] ?? null ) ? (float) $row['pct'] : 0.0,
				! empty( $row['over'] )
			);
		}
		return $out;
	}

	/**
	 * Everything the screen needs when it opens.
	 */
	public static function workspace(): FLXST_Workspace {
		$user = wp_get_current_user();
		return new FLXST_Workspace(
			FLXST_VERSION,
			$user->ID,
			$user->display_name,
			self::can_manage(),
			current_user_can( 'upload_files' ),
			flxst_ai_ready(),
			flxst_text_ready(),
			current_user_can( 'manage_options' ) ? admin_url( 'admin.php?page=flx-ai-settings' ) : '',
			self::catalog(),
			self::settings(),
			self::folders(),
			self::people(),
			self::refs(),
			self::templates(),
			self::budget()
		);
	}

	/* ------------------------------------------------------------------ Items */

	/**
	 * Row of an item.
	 *
	 * @param int $id Item id.
	 * @return array<string,mixed>|null
	 */
	private static function item_row( int $id ): ?array {
		$row = flxst_item( $id );
		return is_array( $row ) ? FLXST_Util::arr( $row ) : null;
	}

	/**
	 * An item the user may see (their own, a shared folder's, or any for Studio administrators).
	 *
	 * @param int $id Item id.
	 */
	public static function item( int $id ): ?FLXST_Item {
		$row = self::item_row( $id );
		return $row && flxst_item_visible( $row, get_current_user_id() ) ? FLXST_Item::from_row( $row ) : null;
	}

	/**
	 * Items by id the user may see, for the node loader.
	 *
	 * @param list<int> $ids Item ids.
	 * @return array<int,FLXST_Item>
	 */
	public static function items_by_ids( array $ids ): array {
		$out = array();
		foreach ( $ids as $id ) {
			$i = self::item( $id );
			if ( $i ) {
				$out[ $id ] = $i;
			}
		}
		return $out;
	}

	/**
	 * Gallery page, newest first.
	 *
	 * @param array{folder?: int|null, kind?: string, favorite?: bool, batch?: string, search?: string, all_owners?: bool} $filter Filters (folder null = every folder).
	 * @param int                                                                                                           $offset Rows to skip.
	 * @param int                                                                                                           $limit  Rows to return.
	 * @return list<FLXST_Item>
	 */
	public static function items( array $filter, int $offset, int $limit ): array {
		$folder = $filter['folder'] ?? null;
		$rows   = flxst_items_rows(
			get_current_user_id(),
			array(
				'folder' => null === $folder ? 'all' : $folder,
				'kind'   => $filter['kind'] ?? '',
				'fav'    => ! empty( $filter['favorite'] ),
				'batch'  => $filter['batch'] ?? '',
				'search' => $filter['search'] ?? '',
				'owner'  => ! empty( $filter['all_owners'] ) ? 'all' : 'me',
				'offset' => max( 0, $offset ),
				'limit'  => $limit,
			)
		);
		$out    = array();
		foreach ( $rows as $r ) {
			$out[] = FLXST_Item::from_row( FLXST_Util::arr( $r ) );
		}
		return $out;
	}

	/**
	 * Editable rows of an id list (own items; every item for Studio administrators).
	 *
	 * @param list<int> $ids Item ids (at most 500 are looked at).
	 * @return list<array<string,mixed>>
	 */
	private static function editable_rows( array $ids ): array {
		$out = array();
		foreach ( array_slice( array_unique( array_map( 'intval', $ids ) ), 0, 500 ) as $id ) {
			$r = self::item_row( $id );
			if ( $r && flxst_item_editable( $r, get_current_user_id() ) ) {
				$out[] = $r;
			}
		}
		return $out;
	}

	/**
	 * Favorite, title or folder of an item. A folder the user cannot write to is left unchanged.
	 *
	 * @param int                                               $id     Item id.
	 * @param array{favorite?: bool|null, title?: string|null, folder?: int|null} $change Fields to change.
	 */
	public static function item_update( int $id, array $change ): FLXST_Item|WP_Error {
		$uid = get_current_user_id();
		$row = self::item_row( $id );
		if ( ! $row || ! flxst_item_editable( $row, $uid ) ) {
			return self::not_found();
		}
		$d = array();
		if ( isset( $change['favorite'] ) ) {
			$d['favorite'] = $change['favorite'] ? 1 : 0;
		}
		if ( isset( $change['title'] ) ) {
			$d['title'] = mb_substr( sanitize_text_field( $change['title'] ), 0, 190 );
		}
		if ( isset( $change['folder'] ) && flxst_folder_writable( $change['folder'], $uid ) ) {
			$d['folder_id'] = $change['folder'];
		}
		if ( $d ) {
			flxst_item_update( $id, $d );
			FLXST_Util::purge( array( self::TAG_ITEMS ) );
		}
		$fresh = self::item_row( $id );
		return $fresh ? FLXST_Item::from_row( $fresh ) : self::not_found();
	}

	/**
	 * Deletes items (running ones are cancelled at the provider). Files in the Flux AI folder are removed too.
	 *
	 * @param list<int> $ids Item ids.
	 * @return list<int> Ids deleted.
	 */
	public static function items_delete( array $ids ): array {
		$done = array();
		foreach ( self::editable_rows( $ids ) as $row ) {
			if ( in_array( $row['state'] ?? '', array( 'queued', 'running' ), true ) && function_exists( 'flxai_media_cancel' ) && ! empty( $row['job'] ) ) {
				flxai_media_cancel( FLXST_Util::str( $row['job'] ) );
			}
			flxst_item_delete( $row );
			$done[] = FLXST_Util::int( $row['id'] ?? 0 );
		}
		if ( $done ) {
			FLXST_Util::purge( array( self::TAG_ITEMS ) );
		}
		return $done;
	}

	/**
	 * Moves items to a folder (0 = none).
	 *
	 * @param list<int> $ids    Item ids.
	 * @param int       $folder Folder id.
	 * @return list<int>|WP_Error Ids moved.
	 */
	public static function items_move( array $ids, int $folder ): array|WP_Error {
		$uid = get_current_user_id();
		if ( ! flxst_folder_writable( $folder, $uid ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You cannot move items to this folder.', 'flx-studio' ), array( 'status' => 403 ) );
		}
		$done = array();
		foreach ( self::editable_rows( $ids ) as $row ) {
			$id = FLXST_Util::int( $row['id'] ?? 0 );
			flxst_item_update( $id, array( 'folder_id' => $folder ) );
			$done[] = $id;
		}
		if ( $done ) {
			FLXST_Util::purge( array( self::TAG_ITEMS ) );
		}
		return $done;
	}

	/**
	 * Copies a finished item into the WordPress Media Library.
	 *
	 * @param int $id Item id.
	 */
	public static function item_to_library( int $id ): FLXST_Library_Result|WP_Error {
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You are not allowed to add to the Media Library.', 'flx-studio' ), array( 'status' => 403 ) );
		}
		$row = self::item_row( $id );
		if ( ! $row || ! flxst_item_visible( $row, get_current_user_id() ) || 'done' !== ( $row['state'] ?? '' ) ) {
			return self::not_found();
		}
		$att = flxst_to_library( $row );
		if ( $att instanceof WP_Error ) {
			return $att;
		}
		$att = FLXST_Util::int( $att );
		FLXST_Util::purge( array( self::TAG_ITEMS ) );
		return new FLXST_Library_Result( $att, FLXST_Util::str( get_edit_post_link( $att, 'raw' ) ), FLXST_Util::str( wp_get_attachment_url( $att ) ) );
	}

	/* ------------------------------------------------------------------ Generation */

	/**
	 * Starts a generation: one queued item per output, sent to Flux AI after the response.
	 *
	 * @param array<string,mixed> $in Request (kind, model, provider, prompt, n, aspect, resolution, quality, refs, folder, mask …).
	 * @return list<FLXST_Item>|WP_Error
	 */
	public static function generate( array $in ): array|WP_Error {
		$ids = flxst_generate_ids( $in, get_current_user_id() );
		if ( $ids instanceof WP_Error ) {
			return $ids;
		}
		FLXST_Util::purge( array( self::TAG_ITEMS, self::TAG_STATS ) );
		return self::items_of_ids( $ids );
	}

	/**
	 * Items by id (no access check: ids came from this user's own request).
	 *
	 * @param list<int> $ids Item ids.
	 * @return list<FLXST_Item>
	 */
	private static function items_of_ids( array $ids ): array {
		$out = array();
		foreach ( $ids as $id ) {
			$r = self::item_row( $id );
			if ( $r ) {
				$out[] = FLXST_Item::from_row( $r );
			}
		}
		return $out;
	}

	/**
	 * Follows running jobs (at most 8): asks Flux AI and writes finished files into the items.
	 *
	 * @param list<string> $jobs Job ids (flxd_… or flxm_…).
	 * @return list<FLXST_Item>
	 */
	public static function poll( array $jobs ): array {
		$out = array();
		foreach ( flxst_poll_rows( $jobs, get_current_user_id() ) as $r ) {
			$out[] = FLXST_Item::from_row( FLXST_Util::arr( $r ) );
		}
		FLXST_Util::purge( array( self::TAG_ITEMS, self::TAG_STATS ) );
		return $out;
	}

	/**
	 * Cancels a job (not yet sent: dropped; running: stopped at the provider).
	 *
	 * @param string $job Job id.
	 * @return list<FLXST_Item>|WP_Error The job's items after the cancel.
	 */
	public static function cancel( string $job ): array|WP_Error {
		$wpdb = FLXST_Util::db();
		$t    = FLXST_Util::str( flxst_table( 'items' ) );
		$real = 0 === strpos( $job, 'flxd_' ) ? get_transient( 'flxst_map_' . $job ) : '';
		$job  = $real ? FLXST_Util::str( $real ) : $job; // Sent in the meantime: the real job.
		$row  = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE job = %s LIMIT 1', $t, $job ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$row  = is_array( $row ) ? FLXST_Util::arr( $row ) : null;
		if ( ! $row || ! flxst_item_editable( $row, get_current_user_id() ) ) {
			return self::not_found();
		}
		$job = FLXST_Util::str( $row['job'] ?? '' );
		if ( 0 === strpos( $job, 'flxd_' ) ) {
			// Not sent yet: items are cancelled and nothing is sent (a send in progress is stopped afterwards).
			$wpdb->query( (string) $wpdb->prepare( "UPDATE %i SET state = 'cancelled', error = %s WHERE job = %s AND state IN ('queued','running')", $t, __( 'Cancelled.', 'flx-studio' ), $job ) ); // phpcs:ignore WordPress.DB
			delete_transient( 'flxst_' . $job );
		} else {
			$st = function_exists( 'flxai_media_cancel' ) ? flxai_media_cancel( $job ) : null;
			if ( is_array( $st ) ) {
				flxst_apply_job( $st );
			}
		}
		FLXST_Util::purge( array( self::TAG_ITEMS, self::TAG_STATS ) );
		return self::poll_items_of_job( $job );
	}

	/**
	 * Items of a job after a status refresh.
	 *
	 * @param string $job Job id.
	 * @return list<FLXST_Item>
	 */
	private static function poll_items_of_job( string $job ): array {
		$out = array();
		foreach ( flxst_poll_rows( array( $job ), get_current_user_id() ) as $r ) {
			$out[] = FLXST_Item::from_row( FLXST_Util::arr( $r ) );
		}
		return $out;
	}

	/**
	 * The References of a request as Flux AI summaries (for Enhance and Brainstorm; unresolved ones are left out).
	 *
	 * @param list<array{type: string, id: int, role?: string}> $refs References.
	 * @return list<array<string,mixed>>
	 */
	private static function ref_summaries( array $refs ): array {
		$res = flxst_resolve_refs( $refs, get_current_user_id() );
		if ( $res instanceof WP_Error ) {
			return array();
		}
		$out = array();
		foreach ( is_array( $res[1] ?? null ) ? $res[1] : array() as $s ) {
			$out[] = FLXST_Util::arr( $s );
		}
		return $out;
	}

	/**
	 * Turns a short draft into a detailed prompt for the model (Flux AI text model).
	 *
	 * @param string                                            $prompt      Draft.
	 * @param string                                            $mode        image | video | edit.
	 * @param list<array{type: string, id: int, role?: string}> $refs        References.
	 * @param string                                            $model_label Target model name.
	 */
	public static function enhance( string $prompt, string $mode, array $refs, string $model_label ): string|WP_Error {
		$out = flxst_enhance( $prompt, sanitize_key( $mode ), self::ref_summaries( $refs ), sanitize_text_field( $model_label ) );
		return $out instanceof WP_Error ? $out : FLXST_Util::str( $out );
	}

	/**
	 * Several different prompts from one idea (Flux AI text model).
	 *
	 * @param string                                            $brief Idea.
	 * @param int                                               $count Ideas wanted.
	 * @param string                                            $style simple | youtube.
	 * @param list<array{type: string, id: int, role?: string}> $refs  References.
	 * @param string                                            $mode  image | video.
	 * @return array{ideas: list<FLXST_Idea>, batch: string}|WP_Error
	 */
	public static function brainstorm( string $brief, int $count, string $style, array $refs, string $mode ): array|WP_Error {
		$ideas = flxst_brainstorm( $brief, $count, sanitize_key( $style ), self::ref_summaries( $refs ), 'video' === $mode ? 'video' : 'image' );
		if ( $ideas instanceof WP_Error ) {
			return $ideas;
		}
		$out = array();
		foreach ( is_array( $ideas ) ? $ideas : array() as $i ) {
			$i     = FLXST_Util::arr( $i );
			$out[] = new FLXST_Idea( FLXST_Util::str( $i['title'] ?? '' ), FLXST_Util::str( $i['prompt'] ?? '' ) );
		}
		return array(
			'ideas' => $out,
			'batch' => strtolower( wp_generate_password( 12, false ) ),
		);
	}

	/* ------------------------------------------------------------------ Folders */

	/**
	 * Folders the user sees (every folder for Studio administrators).
	 *
	 * @return list<FLXST_Folder>
	 */
	public static function folders(): array {
		$out = array();
		foreach ( flxst_folders_rows( get_current_user_id() ) as $r ) {
			$out[] = FLXST_Folder::from_row( FLXST_Util::arr( $r ) );
		}
		return $out;
	}

	/**
	 * Folder row.
	 *
	 * @param int $id Folder id.
	 */
	private static function folder_of( int $id ): ?FLXST_Folder {
		$r = flxst_folder( $id );
		return is_array( $r ) ? FLXST_Folder::from_row( FLXST_Util::arr( $r ) ) : null;
	}

	/**
	 * Adds a folder. A parent the user cannot write to becomes the top level.
	 *
	 * @param string $name   Name (up to 120 characters).
	 * @param int    $parent Parent folder (0 = top).
	 * @param bool   $shared Visible to the team.
	 */
	public static function folder_create( string $name, int $parent, bool $shared ): FLXST_Folder|WP_Error {
		$wpdb = FLXST_Util::db();
		$uid  = get_current_user_id();
		$name = mb_substr( sanitize_text_field( $name ), 0, 120 );
		if ( '' === $name ) {
			return new WP_Error( 'flxst_name', __( 'Folder name is required.', 'flx-studio' ), array( 'status' => 400, 'field' => 'name' ) );
		}
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			FLXST_Util::str( flxst_table( 'folders' ) ),
			array(
				'user_id'   => $uid,
				'parent_id' => flxst_folder_writable( $parent, $uid ) ? $parent : 0,
				'name'      => $name,
				'shared'    => $shared ? 1 : 0,
				'created'   => time(),
			)
		);
		FLXST_Util::purge( array( self::TAG_FOLDERS ) );
		return self::folder_of( (int) $wpdb->insert_id ) ?? self::not_found();
	}

	/**
	 * Renames, shares or moves a folder (the owner, or a Studio administrator). A folder cannot move into itself.
	 *
	 * @param int         $id     Folder id.
	 * @param string|null $name   New name (empty names are ignored).
	 * @param bool|null   $shared Visible to the team.
	 * @param int|null    $parent New parent (0 = top).
	 */
	public static function folder_update( int $id, ?string $name, ?bool $shared, ?int $parent ): FLXST_Folder|WP_Error {
		$wpdb = FLXST_Util::db();
		$uid = get_current_user_id();
		if ( ! flxst_folder_owned( $id, $uid ) ) {
			return self::not_found();
		}
		$d = array();
		if ( null !== $name && '' !== trim( $name ) ) {
			$d['name'] = mb_substr( sanitize_text_field( $name ), 0, 120 );
		}
		if ( null !== $shared ) {
			$d['shared'] = $shared ? 1 : 0;
		}
		if ( null !== $parent ) {
			$x  = $parent;
			$ok = true;
			for ( $i = 0; $x && $i < 50; $i++ ) {
				if ( $x === $id ) {
					$ok = false;
					break;
				}
				$f = self::folder_of( $x );
				$x = $f ? $f->parent_id : 0;
			}
			if ( $ok && flxst_folder_writable( $parent, $uid ) ) {
				$d['parent_id'] = $parent;
			}
		}
		if ( $d ) {
			$wpdb->update( FLXST_Util::str( flxst_table( 'folders' ) ), $d, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			FLXST_Util::purge( array( self::TAG_FOLDERS ) );
		}
		return self::folder_of( $id ) ?? self::not_found();
	}

	/**
	 * Deletes a folder; its items and subfolders move up one level (no file is deleted).
	 *
	 * @param int $id Folder id.
	 */
	public static function folder_delete( int $id ): bool|WP_Error {
		$wpdb = FLXST_Util::db();
		$f = self::folder_of( $id );
		if ( ! $f || ! flxst_folder_owned( $id, get_current_user_id() ) ) {
			return self::not_found();
		}
		$wpdb->update( FLXST_Util::str( flxst_table( 'items' ) ), array( 'folder_id' => $f->parent_id ), array( 'folder_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update( FLXST_Util::str( flxst_table( 'folders' ) ), array( 'parent_id' => $f->parent_id ), array( 'parent_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->delete( FLXST_Util::str( flxst_table( 'folders' ) ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		FLXST_Util::purge( array( self::TAG_FOLDERS, self::TAG_ITEMS ) );
		return true;
	}

	/* ------------------------------------------------------------------ People and references */

	/**
	 * Photos or saved references.
	 *
	 * @param int $person Person id (0 = the user's saved references).
	 * @return list<FLXST_Ref>
	 */
	public static function refs( int $person = 0 ): array {
		$out = array();
		foreach ( flxst_refs_rows( get_current_user_id(), $person ? array( 'person' => $person ) : array() ) as $r ) {
			$out[] = FLXST_Ref::from_row( FLXST_Util::arr( $r ) );
		}
		return $out;
	}

	/**
	 * People the user sees, with their photos.
	 *
	 * @return list<FLXST_Person>
	 */
	public static function people(): array {
		$wpdb = FLXST_Util::db();
		$uid  = get_current_user_id();
		$t    = FLXST_Util::str( flxst_table( 'people' ) );
		$shr  = (int) FLXST_Util::int( flxst_settings()['shared_people'] ?? 1, 1 );
		$sql  = flxst_can_manage() || $shr ? $wpdb->prepare( 'SELECT * FROM %i ORDER BY name', $t ) : $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d OR shared = 1 ORDER BY name', $t, $uid ); // phpcs:ignore WordPress.DB
		$out  = array();
		foreach ( (array) $wpdb->get_results( (string) $sql, ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB
			$r     = FLXST_Util::arr( $r );
			$id    = FLXST_Util::int( $r['id'] ?? 0 );
			$out[] = new FLXST_Person( $id, FLXST_Util::str( $r['name'] ?? '' ), FLXST_Util::int( $r['user_id'] ?? 0 ), self::refs( $id ) );
		}
		return $out;
	}

	/**
	 * Person row the user may see.
	 *
	 * @param int $id Person id.
	 * @return array<string,mixed>|null
	 */
	private static function person_row( int $id ): ?array {
		$p = flxst_person( $id );
		return is_array( $p ) ? FLXST_Util::arr( $p ) : null;
	}

	/**
	 * Whether the user owns the person (or administers Studio).
	 *
	 * @param array<string,mixed>|null $p Person row.
	 */
	private static function person_editable( ?array $p ): bool {
		return $p && ( FLXST_Util::int( $p['user_id'] ?? 0 ) === get_current_user_id() || flxst_can_manage() );
	}

	/**
	 * Adds a person.
	 *
	 * @param string $name Name (up to 120 characters).
	 */
	public static function person_create( string $name ): FLXST_Person|WP_Error {
		$wpdb = FLXST_Util::db();
		$name = mb_substr( sanitize_text_field( $name ), 0, 120 );
		if ( '' === $name ) {
			return new WP_Error( 'flxst_name', __( 'Person name is required.', 'flx-studio' ), array( 'status' => 400, 'field' => 'name' ) );
		}
		$wpdb->insert( FLXST_Util::str( flxst_table( 'people' ) ), array( 'user_id' => get_current_user_id(), 'name' => $name, 'shared' => 1, 'created' => time() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		FLXST_Util::purge( array( self::TAG_PEOPLE ) );
		return new FLXST_Person( (int) $wpdb->insert_id, $name, get_current_user_id(), array() );
	}

	/**
	 * Renames a person (an empty name changes nothing).
	 *
	 * @param int    $id   Person id.
	 * @param string $name New name.
	 */
	public static function person_rename( int $id, string $name ): FLXST_Person|WP_Error {
		$wpdb = FLXST_Util::db();
		$p = self::person_row( $id );
		if ( ! $p || ! self::person_editable( $p ) ) {
			return self::not_found();
		}
		$name = mb_substr( sanitize_text_field( $name ), 0, 120 );
		if ( '' !== $name ) {
			$wpdb->update( FLXST_Util::str( flxst_table( 'people' ) ), array( 'name' => $name ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			FLXST_Util::purge( array( self::TAG_PEOPLE ) );
		}
		$fresh = self::person_row( $id );
		return new FLXST_Person( $id, FLXST_Util::str( $fresh['name'] ?? $name ), FLXST_Util::int( $fresh['user_id'] ?? 0 ), self::refs( $id ) );
	}

	/**
	 * Deletes a person and their photos (earlier generations stay).
	 *
	 * @param int $id Person id.
	 */
	public static function person_delete( int $id ): bool|WP_Error {
		$wpdb = FLXST_Util::db();
		$p = self::person_row( $id );
		if ( ! $p || ! self::person_editable( $p ) ) {
			return self::not_found();
		}
		foreach ( flxst_refs_rows( get_current_user_id(), array( 'person' => $id ) ) as $ph ) {
			flxst_delete_ref( flxst_ref( FLXST_Util::int( FLXST_Util::arr( $ph )['id'] ?? 0 ) ) );
		}
		$wpdb->delete( FLXST_Util::str( flxst_table( 'people' ) ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		FLXST_Util::purge( array( self::TAG_PEOPLE, self::TAG_REFS ) );
		return true;
	}

	/**
	 * Whether the user may attach a file to a person.
	 *
	 * @param int $person Person id (0 = none).
	 */
	public static function person_usable( int $person ): bool {
		return 0 === $person || flxst_person_visible( self::person_row( $person ), get_current_user_id() );
	}

	/**
	 * Saves an uploaded file as a reference or a person's photo. The file was stored by flxst_store_file().
	 *
	 * @param array{path: string, url: string, mime: string, width: int, height: int, bytes: int} $file   Stored file.
	 * @param int                                                                                $person Person id (0 = a plain reference).
	 * @param string                                                                             $name   Name.
	 * @param string                                                                             $role   Role.
	 * @param bool                                                                               $saved  Keep in the saved references (always for a person's photo).
	 */
	public static function ref_create( array $file, int $person, string $name, string $role, bool $saved ): FLXST_Ref|WP_Error {
		if ( ! self::person_usable( $person ) ) {
			return self::not_found();
		}
		$id = flxst_insert_ref( $file, get_current_user_id(), $person, $name, $role, $person || $saved );
		FLXST_Util::purge( array( self::TAG_REFS, self::TAG_PEOPLE ) );
		$row = flxst_ref( $id );
		return is_array( $row ) ? FLXST_Ref::from_row( FLXST_Util::arr( $row ) ) : self::not_found();
	}

	/**
	 * Reference row the user may change (the owner, or a Studio administrator).
	 *
	 * @param int $id Reference id.
	 * @return array<string,mixed>|null
	 */
	private static function ref_editable_row( int $id ): ?array {
		$row = flxst_ref( $id );
		$row = is_array( $row ) ? FLXST_Util::arr( $row ) : null;
		return $row && ( FLXST_Util::int( $row['user_id'] ?? 0 ) === get_current_user_id() || flxst_can_manage() ) ? $row : null;
	}

	/**
	 * Renames a reference, sets its role, or keeps / drops it in the saved list.
	 *
	 * @param int         $id    Reference id.
	 * @param string|null $name  Name.
	 * @param string|null $role  Role key.
	 * @param bool|null   $saved Kept in the saved list.
	 */
	public static function ref_update( int $id, ?string $name, ?string $role, ?bool $saved ): FLXST_Ref|WP_Error {
		$wpdb = FLXST_Util::db();
		$row = self::ref_editable_row( $id );
		if ( ! $row ) {
			return self::not_found();
		}
		$d = array();
		if ( null !== $name ) {
			$d['name'] = mb_substr( sanitize_text_field( $name ), 0, 190 );
		}
		if ( null !== $role ) {
			$d['role'] = sanitize_key( $role );
		}
		if ( null !== $saved ) {
			$d['saved'] = $saved ? 1 : 0;
		}
		if ( $d ) {
			$wpdb->update( FLXST_Util::str( flxst_table( 'refs' ) ), $d, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			FLXST_Util::purge( array( self::TAG_REFS, self::TAG_PEOPLE ) );
		}
		$fresh = flxst_ref( $id );
		return is_array( $fresh ) ? FLXST_Ref::from_row( FLXST_Util::arr( $fresh ) ) : self::not_found();
	}

	/**
	 * Deletes a reference or photo and its files.
	 *
	 * @param int $id Reference id.
	 */
	public static function ref_delete( int $id ): bool|WP_Error {
		$row = self::ref_editable_row( $id );
		if ( ! $row ) {
			return self::not_found();
		}
		flxst_delete_ref( $row );
		FLXST_Util::purge( array( self::TAG_REFS, self::TAG_PEOPLE ) );
		return true;
	}

	/* ------------------------------------------------------------------ Templates */

	/**
	 * Templates of the user and the shared ones.
	 *
	 * @return list<FLXST_Template>
	 */
	public static function templates(): array {
		$out = array();
		foreach ( flxst_templates( get_current_user_id() ) as $r ) {
			$out[] = new FLXST_Template( FLXST_Util::int( $r['id'] ?? 0 ), FLXST_Util::str( $r['name'] ?? '' ), FLXST_Util::str( $r['prompt'] ?? '' ), FLXST_Util::arr( $r['params'] ?? null ), ! empty( $r['shared'] ), FLXST_Util::int( $r['owner'] ?? 0 ) );
		}
		return $out;
	}

	/**
	 * Saves the current settings as a template. Only known settings are kept.
	 *
	 * @param string              $name   Name (up to 120 characters).
	 * @param string              $prompt Prompt.
	 * @param array<string,mixed> $params kind, model, aspect, resolution, quality, size, n, duration, audio, mode, refs.
	 * @param bool                $shared Visible to the team.
	 * @return list<FLXST_Template>|WP_Error All templates after the change.
	 */
	public static function template_create( string $name, string $prompt, array $params, bool $shared ): array|WP_Error {
		$wpdb = FLXST_Util::db();
		$name = mb_substr( sanitize_text_field( $name ), 0, 120 );
		if ( '' === $name ) {
			return new WP_Error( 'flxst_name', __( 'Template name is required.', 'flx-studio' ), array( 'status' => 400, 'field' => 'name' ) );
		}
		$keep = array_intersect_key( $params, array_flip( array( 'kind', 'model', 'aspect', 'resolution', 'quality', 'size', 'n', 'duration', 'audio', 'mode', 'refs' ) ) );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			FLXST_Util::str( flxst_table( 'templates' ) ),
			array(
				'user_id' => get_current_user_id(),
				'name'    => $name,
				'prompt'  => $prompt,
				'params'  => FLXST_Util::str( flxst_json( $keep ) ),
				'shared'  => $shared ? 1 : 0,
				'created' => time(),
			)
		);
		FLXST_Util::purge( array( self::TAG_TEMPLATES ) );
		return self::templates();
	}

	/**
	 * Deletes a template (the owner, or a Studio administrator).
	 *
	 * @param int $id Template id.
	 * @return list<FLXST_Template>|WP_Error All templates after the change.
	 */
	public static function template_delete( int $id ): array|WP_Error {
		$wpdb = FLXST_Util::db();
		$t   = FLXST_Util::str( flxst_table( 'templates' ) );
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $t, $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$row = is_array( $row ) ? FLXST_Util::arr( $row ) : null;
		if ( ! $row || ( FLXST_Util::int( $row['user_id'] ?? 0 ) !== get_current_user_id() && ! flxst_can_manage() ) ) {
			return self::not_found();
		}
		$wpdb->delete( $t, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		FLXST_Util::purge( array( self::TAG_TEMPLATES ) );
		return self::templates();
	}

	/* ------------------------------------------------------------------ Dashboard */

	/**
	 * Usage and spending for administrators.
	 *
	 * @param int $days 1–365.
	 */
	public static function stats( int $days ): FLXST_Stats {
		$s    = FLXST_Util::arr( flxst_stats( $days ) );
		$tot  = FLXST_Util::arr( $s['totals'] ?? null );
		$sp   = FLXST_Util::arr( $s['spend'] ?? null );
		$day  = array();
		foreach ( is_array( $s['daily'] ?? null ) ? $s['daily'] : array() as $d ) {
			$d     = FLXST_Util::arr( $d );
			$day[] = new FLXST_Stats_Day( FLXST_Util::str( $d['date'] ?? '' ), FLXST_Util::int( $d['image'] ?? 0 ), FLXST_Util::int( $d['video'] ?? 0 ), FLXST_Util::int( $d['failed'] ?? 0 ), self::num( $d['cost'] ?? 0 ) );
		}
		$mod  = array();
		foreach ( is_array( $s['models'] ?? null ) ? $s['models'] : array() as $r ) {
			$r     = FLXST_Util::arr( $r );
			$mod[] = new FLXST_Stats_Row( FLXST_Util::str( $r['id'] ?? '' ), FLXST_Util::str( $r['label'] ?? '' ), FLXST_Util::str( $r['kind'] ?? '' ), FLXST_Util::int( $r['done'] ?? 0 ), FLXST_Util::int( $r['failed'] ?? 0 ), self::num( $r['cost'] ?? 0 ) );
		}
		$prov = array();
		foreach ( is_array( $s['providers'] ?? null ) ? $s['providers'] : array() as $r ) {
			$r      = FLXST_Util::arr( $r );
			$prov[] = new FLXST_Stats_Row( FLXST_Util::str( $r['id'] ?? '' ), FLXST_Util::str( $r['label'] ?? '' ), '', FLXST_Util::int( $r['done'] ?? 0 ), FLXST_Util::int( $r['failed'] ?? 0 ), self::num( $r['cost'] ?? 0 ) );
		}
		$ppl  = array();
		foreach ( is_array( $s['users'] ?? null ) ? $s['users'] : array() as $r ) {
			$r     = FLXST_Util::arr( $r );
			$ppl[] = new FLXST_Stats_User( FLXST_Util::int( $r['id'] ?? 0 ), FLXST_Util::str( $r['name'] ?? '' ), FLXST_Util::str( $r['avatar'] ?? '' ), FLXST_Util::int( $r['image'] ?? 0 ), FLXST_Util::int( $r['video'] ?? 0 ), self::num( $r['cost'] ?? 0 ), FLXST_Util::int( $r['last'] ?? 0 ) );
		}
		$err  = array();
		foreach ( is_array( $s['errors'] ?? null ) ? $s['errors'] : array() as $r ) {
			$r     = FLXST_Util::arr( $r );
			$err[] = new FLXST_Stats_Error( FLXST_Util::int( $r['id'] ?? 0 ), FLXST_Util::str( $r['model'] ?? '' ), FLXST_Util::str( $r['provider'] ?? '' ), FLXST_Util::str( $r['error'] ?? '' ), FLXST_Util::int( $r['created'] ?? 0 ) );
		}
		return new FLXST_Stats(
			FLXST_Util::int( $s['days'] ?? $days ),
			FLXST_Util::int( $tot['image'] ?? 0 ),
			FLXST_Util::int( $tot['video'] ?? 0 ),
			FLXST_Util::int( $tot['failed'] ?? 0 ),
			FLXST_Util::int( $tot['cancelled'] ?? 0 ),
			FLXST_Util::int( $tot['pending'] ?? 0 ),
			FLXST_Util::int( $tot['users'] ?? 0 ),
			self::num( $tot['bytes'] ?? 0 ),
			self::num( $sp['total'] ?? 0 ),
			self::num( $sp['media'] ?? 0 ),
			self::num( $sp['text'] ?? 0 ),
			! empty( $sp['est'] ),
			FLXST_Util::str( $sp['from'] ?? 'flx-studio' ),
			$day,
			$mod,
			$prov,
			$ppl,
			$err,
			self::budget( true ),
			FLXST_Util::str( $s['log_url'] ?? '' )
		);
	}

	/**
	 * A number as float.
	 *
	 * @param mixed $v Value.
	 */
	private static function num( $v ): float {
		return is_numeric( $v ) ? (float) $v : 0.0;
	}
}
