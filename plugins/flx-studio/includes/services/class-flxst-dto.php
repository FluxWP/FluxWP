<?php
/**
 * Typed values the Studio service returns. GraphQL, REST (to_rest(), same JSON as before) and the
 * Flux App read the same shapes.
 *
 * @package flx-studio
 */

defined( 'ABSPATH' ) || exit;

/**
 * Small typed readers for WordPress' mixed values (the same as the Flux GraphQL helpers, which may
 * not be installed: the service also runs REST without them).
 */
final class FLXST_Util {

	/**
	 * A scalar as string (anything else: the default).
	 *
	 * @param mixed  $v       Value.
	 * @param string $default Default.
	 */
	public static function str( $v, string $default = '' ): string {
		return is_scalar( $v ) ? (string) $v : $default;
	}

	/**
	 * A number or numeric string as int (anything else: the default).
	 *
	 * @param mixed $v       Value.
	 * @param int   $default Default.
	 */
	public static function int( $v, int $default = 0 ): int {
		if ( is_int( $v ) ) {
			return $v;
		}
		return is_numeric( $v ) || is_bool( $v ) ? (int) $v : $default;
	}

	/**
	 * An array with string keys (anything else: empty).
	 *
	 * @param mixed $v Value.
	 * @return array<string,mixed>
	 */
	public static function arr( $v ): array {
		$out = array();
		if ( is_array( $v ) ) {
			foreach ( $v as $k => $x ) {
				$out[ (string) $k ] = $x;
			}
		}
		return $out;
	}

	/**
	 * The WordPress database object.
	 *
	 * @throws RuntimeException When WordPress has no database yet.
	 */
	public static function db(): wpdb {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			throw new RuntimeException( 'No database.' );
		}
		return $wpdb;
	}

	/**
	 * Clears cached GraphQL responses (a no-op without Flux GraphQL).
	 *
	 * @param list<string> $tags Cache tags.
	 */
	public static function purge( array $tags ): void {
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( $tags );
		}
	}
}

/**
 * A reference image of a generation, as saved with the item (params.refs).
 */
final class FLXST_Item_Ref {

	/**
	 * @param string $type   item | ref | person.
	 * @param int    $id     Record id.
	 * @param string $role   Role (identity, style, edit …); empty for general.
	 * @param string $name   Person name; empty for images.
	 * @param string $thumb  Small image address.
	 * @param int    $photos Photos used (person only).
	 */
	public function __construct(
		public readonly string $type,
		public readonly int $id,
		public readonly string $role,
		public readonly string $name,
		public readonly string $thumb,
		public readonly int $photos,
	) {}

	/**
	 * @param array<string,mixed> $a Saved summary.
	 */
	public static function from_array( array $a ): self {
		return new self(
			FLXST_Util::str( $a['type'] ?? '' ),
			FLXST_Util::int( $a['id'] ?? 0 ),
			FLXST_Util::str( $a['role'] ?? '' ),
			FLXST_Util::str( $a['name'] ?? '' ),
			FLXST_Util::str( $a['thumb'] ?? '' ),
			FLXST_Util::int( $a['photos'] ?? 0 ),
		);
	}
}

/**
 * What a generation was asked for (the item's params JSON).
 */
final class FLXST_Item_Params {

	/**
	 * @param string                $aspect     Aspect ratio.
	 * @param string                $resolution Resolution.
	 * @param string                $quality    Quality.
	 * @param int                   $n          Images asked for.
	 * @param int                   $duration   Video seconds (0 = model default).
	 * @param bool|null             $audio      Video sound; null for images.
	 * @param float                 $progress   0–1 while running.
	 * @param list<FLXST_Item_Ref>  $refs       References used.
	 * @param string                $draft      First draft before Enhance.
	 * @param bool                  $edit       Area edit (masked).
	 * @param bool                  $enhanced   The prompt came from Enhance.
	 * @param string                $idea       Brainstorm idea title.
	 * @param bool                  $protected  The provider marked the file protected.
	 * @param array<string,mixed>   $raw        The stored JSON (REST sends it as is).
	 */
	public function __construct(
		public readonly string $aspect,
		public readonly string $resolution,
		public readonly string $quality,
		public readonly int $n,
		public readonly int $duration,
		public readonly ?bool $audio,
		public readonly float $progress,
		public readonly array $refs,
		public readonly string $draft,
		public readonly bool $edit,
		public readonly bool $enhanced,
		public readonly string $idea,
		public readonly bool $protected,
		public readonly array $raw,
	) {}

	/**
	 * @param array<string,mixed> $p Decoded params JSON.
	 */
	public static function from_array( array $p ): self {
		$refs = array();
		foreach ( is_array( $p['refs'] ?? null ) ? $p['refs'] : array() as $r ) {
			$refs[] = FLXST_Item_Ref::from_array( FLXST_Util::arr( $r ) );
		}
		return new self(
			FLXST_Util::str( $p['aspect'] ?? '' ),
			FLXST_Util::str( $p['resolution'] ?? '' ),
			FLXST_Util::str( $p['quality'] ?? '' ),
			FLXST_Util::int( $p['n'] ?? 0 ),
			FLXST_Util::int( $p['duration'] ?? 0 ),
			array_key_exists( 'audio', $p ) ? (bool) $p['audio'] : null,
			is_numeric( $p['progress'] ?? null ) ? (float) $p['progress'] : 0.0,
			$refs,
			FLXST_Util::str( $p['draft'] ?? '' ),
			! empty( $p['edit'] ),
			! empty( $p['enhanced'] ),
			FLXST_Util::str( $p['idea'] ?? '' ),
			! empty( $p['protected'] ),
			$p,
		);
	}
}

/**
 * One generation (image or video) of the gallery.
 */
class FLXST_Item {

	/**
	 * @param int               $id            Item id.
	 * @param string            $kind          image | video.
	 * @param string            $state         queued | running | done | failed | cancelled.
	 * @param string            $job           Flux AI job (flxd_… until sent, then flxm_…).
	 * @param string            $batch         Brainstorm batch id.
	 * @param int               $parent_id     Source item of an area edit.
	 * @param int               $folder_id     Folder (0 = none).
	 * @param string            $title         Title.
	 * @param string            $prompt        Prompt.
	 * @param string            $model         Model id.
	 * @param string            $provider      Provider id.
	 * @param FLXST_Item_Params $params        Request details.
	 * @param string            $url           File address.
	 * @param string            $thumb         Small image (falls back to the file).
	 * @param string            $mime          MIME type.
	 * @param int               $width         Pixels.
	 * @param int               $height        Pixels.
	 * @param int               $bytes         File size.
	 * @param float             $cost          USD.
	 * @param string            $error         Why it failed.
	 * @param bool              $favorite      Starred.
	 * @param int               $attachment_id Media Library copy.
	 * @param int               $owner_id      User who made it.
	 * @param string            $owner_name    Display name.
	 * @param int               $created       Unix time.
	 * @param int               $updated       Unix time.
	 */
	final public function __construct(
		public readonly int $id,
		public readonly string $kind,
		public readonly string $state,
		public readonly string $job,
		public readonly string $batch,
		public readonly int $parent_id,
		public readonly int $folder_id,
		public readonly string $title,
		public readonly string $prompt,
		public readonly string $model,
		public readonly string $provider,
		public readonly FLXST_Item_Params $params,
		public readonly string $url,
		public readonly string $thumb,
		public readonly string $mime,
		public readonly int $width,
		public readonly int $height,
		public readonly int $bytes,
		public readonly float $cost,
		public readonly string $error,
		public readonly bool $favorite,
		public readonly int $attachment_id,
		public readonly int $owner_id,
		public readonly string $owner_name,
		public readonly int $created,
		public readonly int $updated,
	) {}

	/**
	 * @param array<string,mixed> $r Table row.
	 */
	public static function from_row( array $r ): static {
		$thumb = FLXST_Util::str( $r['thumb'] ?? '' );
		$url   = FLXST_Util::str( $r['url'] ?? '' );
		$owner = FLXST_Util::int( $r['user_id'] ?? 0 );
		return new static(
			FLXST_Util::int( $r['id'] ?? 0 ),
			FLXST_Util::str( $r['kind'] ?? '' ),
			FLXST_Util::str( $r['state'] ?? '' ),
			FLXST_Util::str( $r['job'] ?? '' ),
			FLXST_Util::str( $r['batch'] ?? '' ),
			FLXST_Util::int( $r['parent_id'] ?? 0 ),
			FLXST_Util::int( $r['folder_id'] ?? 0 ),
			FLXST_Util::str( $r['title'] ?? '' ),
			FLXST_Util::str( $r['prompt'] ?? '' ),
			FLXST_Util::str( $r['model'] ?? '' ),
			FLXST_Util::str( $r['provider'] ?? '' ),
			FLXST_Item_Params::from_array( FLXST_Service::decode( $r['params'] ?? '' ) ),
			$url,
			'' !== $thumb ? $thumb : $url,
			FLXST_Util::str( $r['mime'] ?? '' ),
			FLXST_Util::int( $r['width'] ?? 0 ),
			FLXST_Util::int( $r['height'] ?? 0 ),
			FLXST_Util::int( $r['bytes'] ?? 0 ),
			is_numeric( $r['cost'] ?? null ) ? (float) $r['cost'] : 0.0,
			FLXST_Util::str( $r['error'] ?? '' ),
			(bool) FLXST_Util::int( $r['favorite'] ?? 0 ),
			FLXST_Util::int( $r['attachment_id'] ?? 0 ),
			$owner,
			FLXST_Service::user_name( $owner ),
			FLXST_Util::int( $r['created'] ?? 0 ),
			FLXST_Util::int( $r['updated'] ?? 0 ),
		);
	}

	/**
	 * REST shape (flx-studio/v1, FluxWP app): unchanged since 0.4.
	 *
	 * @return array<string,mixed>
	 */
	public function to_rest(): array {
		return array(
			'id'         => $this->id,
			'kind'       => $this->kind,
			'state'      => $this->state,
			'job'        => $this->job,
			'batch'      => $this->batch,
			'parent'     => $this->parent_id,
			'folder'     => $this->folder_id,
			'title'      => $this->title,
			'prompt'     => $this->prompt,
			'model'      => $this->model,
			'provider'   => $this->provider,
			'params'     => $this->params->raw,
			'url'        => $this->url,
			'thumb'      => $this->thumb,
			'mime'       => $this->mime,
			'width'      => $this->width,
			'height'     => $this->height,
			'bytes'      => $this->bytes,
			'cost'       => $this->cost,
			'error'      => $this->error,
			'favorite'   => $this->favorite,
			'attachment' => $this->attachment_id,
			'owner'      => $this->owner_id,
			'owner_name' => $this->owner_name,
			'created'    => $this->created,
			'updated'    => $this->updated,
		);
	}
}

/**
 * A gallery folder.
 */
final class FLXST_Folder {

	/**
	 * @param int    $id        Folder id.
	 * @param int    $parent_id Parent folder (0 = top).
	 * @param string $name      Name.
	 * @param bool   $shared    Visible to the whole team.
	 * @param int    $owner_id  Owner user.
	 * @param int    $created   Unix time.
	 */
	public function __construct(
		public readonly int $id,
		public readonly int $parent_id,
		public readonly string $name,
		public readonly bool $shared,
		public readonly int $owner_id,
		public readonly int $created,
	) {}

	/**
	 * @param array<string,mixed> $r Table row.
	 */
	public static function from_row( array $r ): self {
		return new self(
			FLXST_Util::int( $r['id'] ?? 0 ),
			FLXST_Util::int( $r['parent_id'] ?? 0 ),
			FLXST_Util::str( $r['name'] ?? '' ),
			(bool) FLXST_Util::int( $r['shared'] ?? 0 ),
			FLXST_Util::int( $r['user_id'] ?? 0 ),
			FLXST_Util::int( $r['created'] ?? 0 ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function to_rest(): array {
		return array(
			'id'      => $this->id,
			'parent'  => $this->parent_id,
			'name'    => $this->name,
			'shared'  => $this->shared,
			'owner'   => $this->owner_id,
			'created' => $this->created,
		);
	}
}

/**
 * A saved reference image (kept for reuse) or a photo of a person.
 */
final class FLXST_Ref {

	/**
	 * @param int    $id        Reference id.
	 * @param int    $person_id Person it belongs to (0 = saved reference).
	 * @param string $name      Name.
	 * @param string $role      Role (identity, style …); empty for general.
	 * @param string $url       File address.
	 * @param string $thumb     Small image (falls back to the file).
	 * @param string $mime      MIME type.
	 * @param int    $width     Pixels.
	 * @param int    $height    Pixels.
	 * @param bool   $saved     Kept in the saved references list.
	 */
	public function __construct(
		public readonly int $id,
		public readonly int $person_id,
		public readonly string $name,
		public readonly string $role,
		public readonly string $url,
		public readonly string $thumb,
		public readonly string $mime,
		public readonly int $width,
		public readonly int $height,
		public readonly bool $saved,
	) {}

	/**
	 * @param array<string,mixed> $r Table row.
	 */
	public static function from_row( array $r ): self {
		$thumb = FLXST_Util::str( $r['thumb'] ?? '' );
		$url   = FLXST_Util::str( $r['url'] ?? '' );
		return new self(
			FLXST_Util::int( $r['id'] ?? 0 ),
			FLXST_Util::int( $r['person_id'] ?? 0 ),
			FLXST_Util::str( $r['name'] ?? '' ),
			FLXST_Util::str( $r['role'] ?? '' ),
			$url,
			'' !== $thumb ? $thumb : $url,
			FLXST_Util::str( $r['mime'] ?? '' ),
			FLXST_Util::int( $r['width'] ?? 0 ),
			FLXST_Util::int( $r['height'] ?? 0 ),
			(bool) FLXST_Util::int( $r['saved'] ?? 0 ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function to_rest(): array {
		return array(
			'id'     => $this->id,
			'person' => $this->person_id,
			'name'   => $this->name,
			'role'   => $this->role,
			'url'    => $this->url,
			'thumb'  => $this->thumb,
			'mime'   => $this->mime,
			'width'  => $this->width,
			'height' => $this->height,
			'saved'  => $this->saved,
		);
	}
}

/**
 * A person whose photos are used as identity references.
 */
final class FLXST_Person {

	/**
	 * @param int            $id       Person id.
	 * @param string         $name     Name.
	 * @param int            $owner_id Owner user.
	 * @param list<FLXST_Ref> $photos  Photos.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $name,
		public readonly int $owner_id,
		public readonly array $photos,
	) {}

	/**
	 * @return array<string,mixed>
	 */
	public function to_rest(): array {
		return array(
			'id'     => $this->id,
			'name'   => $this->name,
			'owner'  => $this->owner_id,
			'photos' => array_map( static fn( FLXST_Ref $p ): array => $p->to_rest(), $this->photos ),
		);
	}
}

/**
 * A saved set of generation settings.
 */
final class FLXST_Template {

	/**
	 * @param int                 $id       Template id.
	 * @param string              $name     Name.
	 * @param string              $prompt   Prompt.
	 * @param array<string,mixed> $params   Kept settings (kind, model, aspect, resolution, quality, n, duration, audio, refs …).
	 * @param bool                $shared   Visible to the whole team.
	 * @param int                 $owner_id Owner user.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $name,
		public readonly string $prompt,
		public readonly array $params,
		public readonly bool $shared,
		public readonly int $owner_id,
	) {}

	/**
	 * @return array<string,mixed>
	 */
	public function to_rest(): array {
		return array(
			'id'     => $this->id,
			'name'   => $this->name,
			'prompt' => $this->prompt,
			'params' => $this->params,
			'shared' => $this->shared,
			'owner'  => $this->owner_id,
		);
	}
}

/**
 * An image or video model of the Flux AI catalog (no keys).
 */
final class FLXST_Model {

	/**
	 * @param string                $id          Model id.
	 * @param string                $label       Name.
	 * @param string                $kind        image | video.
	 * @param list<string>          $routes      Providers that can serve it.
	 * @param list<string>          $providers   Routes with a key entered.
	 * @param string                $auto        Provider used for "auto".
	 * @param list<string>          $aspects     Aspect ratios.
	 * @param list<string>          $resolutions Resolutions.
	 * @param list<string>          $qualities   Quality levels.
	 * @param list<int>             $duration    [min, max, default] seconds; empty for images.
	 * @param int                   $max_refs    Reference images.
	 * @param int                   $max_n       Images per request.
	 * @param bool                  $mask        Area editing supported.
	 * @param array<string,float>   $price       USD by resolution (per image, or per second for video).
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $label,
		public readonly string $kind,
		public readonly array $routes,
		public readonly array $providers,
		public readonly string $auto,
		public readonly array $aspects,
		public readonly array $resolutions,
		public readonly array $qualities,
		public readonly array $duration,
		public readonly int $max_refs,
		public readonly int $max_n,
		public readonly bool $mask,
		public readonly array $price,
	) {}
}

/**
 * A generation provider (fal.ai, EvoLink …).
 */
final class FLXST_Provider {

	/**
	 * @param string $id    Provider id.
	 * @param string $label Name.
	 * @param bool   $ready A key is entered.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $label,
		public readonly bool $ready,
	) {}
}

/**
 * Models and providers of Flux AI.
 */
final class FLXST_Catalog {

	/**
	 * @param list<FLXST_Model>    $models        Models.
	 * @param list<FLXST_Provider> $providers     Providers.
	 * @param string               $default_image Default image model (Flux AI).
	 * @param string               $default_video Default video model (Flux AI).
	 */
	public function __construct(
		public readonly array $models,
		public readonly array $providers,
		public readonly string $default_image,
		public readonly string $default_video,
	) {}
}

/**
 * Studio settings (option flxst_settings).
 */
final class FLXST_Settings {

	/**
	 * @param string $image_model   Default image model ('' = Flux AI default).
	 * @param string $video_model   Default video model ('' = Flux AI default).
	 * @param string $text_model    Model for Enhance and Brainstorm ('' = Flux AI default).
	 * @param int    $max_n         Most images per request (1–8).
	 * @param int    $max_batch     Most ideas per Brainstorm (1–40).
	 * @param bool   $shared_people People are shared with every Studio user.
	 */
	public function __construct(
		public readonly string $image_model,
		public readonly string $video_model,
		public readonly string $text_model,
		public readonly int $max_n,
		public readonly int $max_batch,
		public readonly bool $shared_people,
	) {}
}

/**
 * One Flux AI spending limit.
 */
final class FLXST_Budget {

	/**
	 * @param string $period Period key (daily …).
	 * @param string $label  Period name.
	 * @param float  $limit  USD (0 = no limit).
	 * @param float  $spent  USD.
	 * @param float  $pct    Spent / limit, percent.
	 * @param bool   $over   Limit reached.
	 */
	public function __construct(
		public readonly string $period,
		public readonly string $label,
		public readonly float $limit,
		public readonly float $spent,
		public readonly float $pct,
		public readonly bool $over,
	) {}
}

/**
 * Everything the Studio screen needs on opening (the old REST "boot").
 */
final class FLXST_Workspace {

	/**
	 * @param string               $version       Plugin version.
	 * @param int                  $user_id       Current user.
	 * @param string               $user_name     Display name.
	 * @param bool                 $can_manage    Sees every gallery, dashboard and settings.
	 * @param bool                 $can_library   May add to the Media Library.
	 * @param bool                 $ai_media      Flux AI image/video backend is installed.
	 * @param bool                 $ai_text       Flux AI text model is ready (Enhance, Brainstorm).
	 * @param string               $setup_url     Flux AI settings page (administrators; '' otherwise).
	 * @param FLXST_Catalog        $catalog       Models and providers.
	 * @param FLXST_Settings       $settings      Settings.
	 * @param list<FLXST_Folder>   $folders       Folders the user sees.
	 * @param list<FLXST_Person>   $people        People the user sees.
	 * @param list<FLXST_Ref>      $refs          Saved references.
	 * @param list<FLXST_Template> $templates     Templates.
	 * @param list<FLXST_Budget>   $budget        Spending limits.
	 */
	public function __construct(
		public readonly string $version,
		public readonly int $user_id,
		public readonly string $user_name,
		public readonly bool $can_manage,
		public readonly bool $can_library,
		public readonly bool $ai_media,
		public readonly bool $ai_text,
		public readonly string $setup_url,
		public readonly FLXST_Catalog $catalog,
		public readonly FLXST_Settings $settings,
		public readonly array $folders,
		public readonly array $people,
		public readonly array $refs,
		public readonly array $templates,
		public readonly array $budget,
	) {}
}

/**
 * Result of adding an item to the Media Library.
 */
final class FLXST_Library_Result {

	/**
	 * @param int    $attachment Attachment id.
	 * @param string $edit_url   Edit screen.
	 * @param string $url        File address.
	 */
	public function __construct(
		public readonly int $attachment,
		public readonly string $edit_url,
		public readonly string $url,
	) {}
}

/**
 * Brainstorm result.
 */
final class FLXST_Idea {

	/**
	 * @param string $title  Short title.
	 * @param string $prompt Production prompt.
	 */
	public function __construct(
		public readonly string $title,
		public readonly string $prompt,
	) {}
}

/** Dashboard: one day. */
final class FLXST_Stats_Day {

	/**
	 * @param string $date   Y-m-d.
	 * @param int    $image  Images made.
	 * @param int    $video  Videos made.
	 * @param int    $failed Failures.
	 * @param float  $cost   USD.
	 */
	public function __construct(
		public readonly string $date,
		public readonly int $image,
		public readonly int $video,
		public readonly int $failed,
		public readonly float $cost,
	) {}
}

/** Dashboard: a model or provider row. */
final class FLXST_Stats_Row {

	/**
	 * @param string $id     Model or provider id.
	 * @param string $label  Name.
	 * @param string $kind   image | video ('' for providers).
	 * @param int    $done   Made.
	 * @param int    $failed Failed.
	 * @param float  $cost   USD.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $label,
		public readonly string $kind,
		public readonly int $done,
		public readonly int $failed,
		public readonly float $cost,
	) {}
}

/** Dashboard: a person's usage. */
final class FLXST_Stats_User {

	/**
	 * @param int    $id     User id.
	 * @param string $name   Display name.
	 * @param string $avatar Avatar address.
	 * @param int    $image  Images made.
	 * @param int    $video  Videos made.
	 * @param float  $cost   USD.
	 * @param int    $last   Unix time of the last generation.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $name,
		public readonly string $avatar,
		public readonly int $image,
		public readonly int $video,
		public readonly float $cost,
		public readonly int $last,
	) {}
}

/** Dashboard: a recent failure. */
final class FLXST_Stats_Error {

	/**
	 * @param int    $id       Item id.
	 * @param string $model    Model name.
	 * @param string $provider Provider name.
	 * @param string $error    Message.
	 * @param int    $created  Unix time.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $model,
		public readonly string $provider,
		public readonly string $error,
		public readonly int $created,
	) {}
}

/**
 * Dashboard for administrators.
 */
final class FLXST_Stats {

	/**
	 * @param int                      $days        Days covered.
	 * @param int                      $image       Images made.
	 * @param int                      $video       Videos made.
	 * @param int                      $failed      Failures.
	 * @param int                      $cancelled   Cancelled.
	 * @param int                      $pending     Waiting or running.
	 * @param int                      $users       People who generated.
	 * @param float                    $bytes       Storage used.
	 * @param float                    $spend_total USD.
	 * @param float                    $spend_media USD on images and videos.
	 * @param float                    $spend_text  USD on text (Enhance, Brainstorm).
	 * @param bool                     $spend_est   Some amounts are estimates.
	 * @param string                   $spend_from  flx-ai | flx-studio.
	 * @param list<FLXST_Stats_Day>    $daily       Per day.
	 * @param list<FLXST_Stats_Row>    $models      Per model.
	 * @param list<FLXST_Stats_Row>    $providers   Per provider.
	 * @param list<FLXST_Stats_User>   $people      Per person.
	 * @param list<FLXST_Stats_Error>  $errors      Recent failures.
	 * @param list<FLXST_Budget>       $budget      Spending limits.
	 * @param string                   $log_url     Flux AI log page ('' without access).
	 */
	public function __construct(
		public readonly int $days,
		public readonly int $image,
		public readonly int $video,
		public readonly int $failed,
		public readonly int $cancelled,
		public readonly int $pending,
		public readonly int $users,
		public readonly float $bytes,
		public readonly float $spend_total,
		public readonly float $spend_media,
		public readonly float $spend_text,
		public readonly bool $spend_est,
		public readonly string $spend_from,
		public readonly array $daily,
		public readonly array $models,
		public readonly array $providers,
		public readonly array $people,
		public readonly array $errors,
		public readonly array $budget,
		public readonly string $log_url,
	) {}
}
