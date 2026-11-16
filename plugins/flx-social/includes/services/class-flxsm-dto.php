<?php
/**
 * Typed values the Flux Social services return (GraphQL, REST and Flux AI read the same shapes), and the
 * cast helpers that turn the legacy untyped rows into them.
 *
 * Nothing here carries a secret: no app key, no access token, no refresh token. Accounts expose their
 * public profile only.
 *
 * @package flx-social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Safe casts for values that come out of legacy rows, options and provider objects.
 */
final class FLXSM_Cast {

	/**
	 * @param mixed $v Value.
	 */
	public static function str( $v, string $default = '' ): string {
		return is_string( $v ) ? $v : ( is_scalar( $v ) ? (string) $v : $default );
	}

	/**
	 * @param mixed $v Value.
	 */
	public static function int( $v, int $default = 0 ): int {
		return is_int( $v ) ? $v : ( is_numeric( $v ) ? (int) $v : $default );
	}

	/**
	 * @param mixed $v Value.
	 */
	public static function float( $v, float $default = 0.0 ): float {
		return is_numeric( $v ) ? (float) $v : $default;
	}

	/**
	 * @param mixed $v Value.
	 */
	public static function bool( $v ): bool {
		return ! empty( $v );
	}

	/**
	 * @param mixed $v Value.
	 * @return array<mixed>
	 */
	public static function arr( $v ): array {
		return is_array( $v ) ? $v : array();
	}

	/**
	 * @param mixed $v Value.
	 * @return list<string>
	 */
	public static function strings( $v ): array {
		$out = array();
		foreach ( self::arr( $v ) as $x ) {
			if ( is_scalar( $x ) && '' !== (string) $x ) {
				$out[] = (string) $x;
			}
		}
		return $out;
	}

	/**
	 * @param mixed $v Value.
	 * @return list<int>
	 */
	public static function ints( $v ): array {
		$out = array();
		foreach ( self::arr( $v ) as $x ) {
			if ( is_numeric( $x ) ) {
				$out[] = (int) $x;
			}
		}
		return $out;
	}

	/**
	 * Row of a list of rows (each an array).
	 *
	 * @param mixed $v Value.
	 * @return list<array<string,mixed>>
	 */
	public static function rows( $v ): array {
		$out = array();
		foreach ( self::arr( $v ) as $r ) {
			if ( is_array( $r ) ) {
				$out[] = self::map( $r );
			}
		}
		return $out;
	}

	/**
	 * String-keyed copy of an array.
	 *
	 * @param array<mixed> $a Array.
	 * @return array<string,mixed>
	 */
	public static function map( array $a ): array {
		$out = array();
		foreach ( $a as $k => $v ) {
			$out[ (string) $k ] = $v;
		}
		return $out;
	}
}

/**
 * What a network can do (provider->caps()).
 */
final class FLXSM_Caps {

	/**
	 * @param list<string> $options Network options the composer shows (keys of FLXSM_Options::KEYS).
	 */
	public function __construct(
		public readonly bool $text,
		public readonly bool $image,
		public readonly bool $video,
		public readonly bool $gif,
		public readonly int $max_text,
		public readonly int $max_images,
		public readonly int $max_videos,
		public readonly bool $mix,
		public readonly bool $needs_media,
		public readonly bool $edit,
		public readonly bool $delete,
		public readonly bool $comment,
		public readonly bool $metrics,
		public readonly array $options,
	) {}

	/**
	 * @param array<mixed> $c provider->caps().
	 */
	public static function from_array( array $c ): self {
		return new self(
			FLXSM_Cast::bool( $c['text'] ?? false ),
			FLXSM_Cast::bool( $c['image'] ?? false ),
			FLXSM_Cast::bool( $c['video'] ?? false ),
			FLXSM_Cast::bool( $c['gif'] ?? false ),
			FLXSM_Cast::int( $c['max_text'] ?? 0 ),
			FLXSM_Cast::int( $c['max_images'] ?? 0 ),
			FLXSM_Cast::int( $c['max_videos'] ?? 0 ),
			FLXSM_Cast::bool( $c['mix'] ?? false ),
			FLXSM_Cast::bool( $c['needs_media'] ?? false ),
			FLXSM_Cast::bool( $c['edit'] ?? false ),
			FLXSM_Cast::bool( $c['delete'] ?? false ),
			FLXSM_Cast::bool( $c['comment'] ?? false ),
			FLXSM_Cast::bool( $c['metrics'] ?? false ),
			FLXSM_Cast::strings( $c['options'] ?? array() ),
		);
	}
}

/**
 * A network (Facebook, X, …).
 */
final class FLXSM_Network {

	public function __construct(
		public readonly string $id,
		public readonly string $label,
		public readonly string $color,
		public readonly FLXSM_Caps $caps,
	) {}
}

/**
 * Pinterest board, YouTube playlist, WhatsApp group.
 */
final class FLXSM_Choice {

	public function __construct(
		public readonly string $id,
		public readonly string $name,
	) {}

	/**
	 * @param mixed $list List of { id, name } rows.
	 * @return list<FLXSM_Choice>
	 */
	public static function list_from( $list ): array {
		$out = array();
		foreach ( FLXSM_Cast::rows( $list ) as $r ) {
			$out[] = new self( FLXSM_Cast::str( $r['id'] ?? '' ), FLXSM_Cast::str( $r['name'] ?? ( $r['title'] ?? '' ) ) );
		}
		return $out;
	}
}

/**
 * A connected social account (public profile only).
 */
final class FLXSM_Account {

	/**
	 * @param list<FLXSM_Choice> $boards    Pinterest boards.
	 * @param list<FLXSM_Choice> $groups    WhatsApp groups.
	 * @param list<FLXSM_Choice> $playlists YouTube playlists.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $provider,
		public readonly string $via,
		public readonly string $network,
		public readonly string $name,
		public readonly string $username,
		public readonly string $avatar,
		public readonly string $status,
		public readonly int $expires,
		public readonly string $kind,
		public readonly array $boards,
		public readonly array $groups,
		public readonly array $playlists,
		public readonly bool $premium,
		public readonly string $last_error,
		public readonly FLXSM_Caps $caps,
		public readonly int $created,
	) {}

	/**
	 * @param array<mixed> $a flxsm_account_public() row.
	 */
	public static function from_array( array $a ): self {
		return new self(
			FLXSM_Cast::int( $a['id'] ?? 0 ),
			FLXSM_Cast::str( $a['provider'] ?? '' ),
			FLXSM_Cast::str( $a['via'] ?? '' ),
			FLXSM_Cast::str( $a['network'] ?? '' ),
			FLXSM_Cast::str( $a['name'] ?? '' ),
			FLXSM_Cast::str( $a['username'] ?? '' ),
			FLXSM_Cast::str( $a['avatar'] ?? '' ),
			FLXSM_Cast::str( $a['status'] ?? 'active' ),
			FLXSM_Cast::int( $a['expires'] ?? 0 ),
			FLXSM_Cast::str( $a['kind'] ?? '' ),
			FLXSM_Choice::list_from( $a['boards'] ?? array() ),
			FLXSM_Choice::list_from( $a['groups'] ?? array() ),
			FLXSM_Choice::list_from( $a['playlists'] ?? array() ),
			FLXSM_Cast::bool( $a['premium'] ?? false ),
			FLXSM_Cast::str( $a['last_error'] ?? '' ),
			FLXSM_Caps::from_array( FLXSM_Cast::arr( $a['caps'] ?? array() ) ),
			FLXSM_Cast::int( $a['created'] ?? 0 ),
		);
	}
}

/**
 * An image, video or GIF in the media library.
 */
final class FLXSM_Media {

	public function __construct(
		public readonly int $id,
		public readonly string $url,
		public readonly string $kind,
		public readonly string $mime,
		public readonly int $bytes,
		public readonly int $width,
		public readonly int $height,
		public readonly float $duration,
		public readonly string $thumb,
		public readonly string $name,
		public readonly string $alt,
	) {}

	/**
	 * @param array<mixed> $m flxsm_media_public() row.
	 */
	public static function from_array( array $m ): self {
		return new self(
			FLXSM_Cast::int( $m['id'] ?? 0 ),
			FLXSM_Cast::str( $m['url'] ?? '' ),
			FLXSM_Cast::str( $m['kind'] ?? 'image' ),
			FLXSM_Cast::str( $m['mime'] ?? '' ),
			FLXSM_Cast::int( $m['bytes'] ?? 0 ),
			FLXSM_Cast::int( $m['width'] ?? 0 ),
			FLXSM_Cast::int( $m['height'] ?? 0 ),
			FLXSM_Cast::float( $m['duration'] ?? 0 ),
			FLXSM_Cast::str( $m['thumb'] ?? '' ),
			FLXSM_Cast::str( $m['name'] ?? '' ),
			FLXSM_Cast::str( $m['alt'] ?? '' ),
		);
	}

	/**
	 * @param mixed $list List of media rows.
	 * @return list<FLXSM_Media>
	 */
	public static function list_from( $list ): array {
		$out = array();
		foreach ( FLXSM_Cast::rows( $list ) as $m ) {
			$out[] = self::from_array( $m );
		}
		return $out;
	}
}

/**
 * Per-account post options. Keys are the stored (snake_case) ones; the GraphQL type maps them to camelCase.
 */
final class FLXSM_Options {

	/** Text options. */
	public const STRINGS = array( 'title', 'link', 'board', 'privacy', 'ig_type', 'flx_category', 'playlist', 'tiktok_privacy', 'gbp_topic', 'gbp_cta', 'gbp_cta_url', 'wa_kind', 'wa_to', 'first_comment', 'li_visibility', 'alt', 'thumb_url', 'reply_settings', 'event_title', 'event_start', 'event_end', 'offer_code' );

	/** Yes/no options. */
	public const BOOLS = array( 'made_for_kids', 'notify', 'shorts', 'disable_comment', 'disable_duet', 'disable_stitch', 'ai_generated', 'brand_content', 'brand_organic', 'share_to_feed', 'auto_music' );

	/**
	 * snake_case → camelCase.
	 */
	public static function camel( string $key ): string {
		return lcfirst( str_replace( ' ', '', ucwords( str_replace( '_', ' ', $key ) ) ) );
	}

	/**
	 * Stored options → { snake_key: value } with only known keys.
	 *
	 * @param array<mixed> $o Stored options.
	 * @return array<string,mixed>
	 */
	public static function known( array $o ): array {
		$out = array();
		foreach ( self::STRINGS as $k ) {
			if ( isset( $o[ $k ] ) && '' !== FLXSM_Cast::str( $o[ $k ] ) ) {
				$out[ $k ] = FLXSM_Cast::str( $o[ $k ] );
			}
		}
		foreach ( self::BOOLS as $k ) {
			if ( isset( $o[ $k ] ) ) {
				$out[ $k ] = FLXSM_Cast::bool( $o[ $k ] );
			}
		}
		if ( isset( $o['tags'] ) ) {
			$out['tags'] = FLXSM_Cast::strings( $o['tags'] );
		}
		return $out;
	}

	/**
	 * GraphQL input ({ camelKey: value }) → stored keys.
	 *
	 * @param array<mixed> $in Input object.
	 * @return array<string,mixed>
	 */
	public static function from_input( array $in ): array {
		$out = array();
		foreach ( array_merge( self::STRINGS, self::BOOLS, array( 'tags' ) ) as $k ) {
			$c = self::camel( $k );
			if ( array_key_exists( $c, $in ) && null !== $in[ $c ] ) {
				$out[ $k ] = $in[ $c ];
			}
		}
		return $out;
	}
}

/**
 * Network numbers of one published target.
 */
final class FLXSM_Metrics {

	public function __construct(
		public readonly int $views,
		public readonly int $impressions,
		public readonly int $reach,
		public readonly int $clicks,
		public readonly int $likes,
		public readonly int $comments,
		public readonly int $shares,
		public readonly int $saves,
	) {}

	/**
	 * @param array<mixed> $m Stored metrics.
	 */
	public static function from_array( array $m ): self {
		return new self(
			FLXSM_Cast::int( $m['views'] ?? 0 ),
			FLXSM_Cast::int( $m['impressions'] ?? 0 ),
			FLXSM_Cast::int( $m['reach'] ?? 0 ),
			FLXSM_Cast::int( $m['clicks'] ?? 0 ),
			FLXSM_Cast::int( $m['likes'] ?? 0 ),
			FLXSM_Cast::int( $m['comments'] ?? 0 ),
			FLXSM_Cast::int( $m['shares'] ?? 0 ),
			FLXSM_Cast::int( $m['saves'] ?? ( $m['saved'] ?? 0 ) ),
		);
	}

	public function is_empty(): bool {
		return 0 === $this->views + $this->impressions + $this->reach + $this->clicks + $this->likes + $this->comments + $this->shares + $this->saves;
	}
}

/**
 * A post on one account (post × account).
 */
final class FLXSM_Target {

	/**
	 * @param array<string,mixed>    $options Known options (snake_case keys).
	 * @param list<FLXSM_Media>|null $media   Own media; null = the shared media.
	 */
	public function __construct(
		public readonly int $id,
		public readonly int $account_id,
		public readonly string $provider,
		public readonly string $account,
		public readonly string $avatar,
		public readonly string $status,
		public readonly string $remote_url,
		public readonly string $error,
		public readonly int $published_at,
		public readonly FLXSM_Metrics $metrics,
		public readonly FLXSM_Caps $caps,
		public readonly ?string $content,
		public readonly ?array $media,
		public readonly array $options,
	) {}
}

/**
 * A post.
 */
final class FLXSM_Post {

	/**
	 * @param list<FLXSM_Media>  $media   Shared media.
	 * @param list<string>       $tags    Tags.
	 * @param list<FLXSM_Target> $targets Accounts the post goes to.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $status,
		public readonly string $title,
		public readonly string $content,
		public readonly array $media,
		public readonly array $tags,
		public readonly int $scheduled_at,
		public readonly int $published_at,
		public readonly int $repeat_days,
		public readonly string $source,
		public readonly string $note,
		public readonly string $user,
		public readonly int $user_id,
		public readonly int $approved_by,
		public readonly int $created,
		public readonly int $updated,
		public readonly array $targets,
	) {}
}

/**
 * A page of posts.
 */
final class FLXSM_Posts_Page {

	/**
	 * @param list<FLXSM_Post> $items Posts of the page.
	 */
	public function __construct(
		public readonly array $items,
		public readonly int $total,
	) {}
}

/**
 * One row of the activity log.
 */
final class FLXSM_Log_Row {

	public function __construct(
		public readonly int $id,
		public readonly int $time,
		public readonly string $level,
		public readonly int $account_id,
		public readonly string $message,
	) {}
}

/**
 * A WhatsApp group or Status the connection can post to.
 */
final class FLXSM_WA_Target {

	public function __construct(
		public readonly string $ext_id,
		public readonly string $name,
		public readonly string $kind,
		public readonly string $avatar,
		public readonly bool $exists,
	) {}
}

/**
 * A field of a network app (keys are never returned; only whether a secret is set).
 */
final class FLXSM_App_Field {

	public function __construct(
		public readonly string $key,
		public readonly string $label,
		public readonly bool $secret,
		public readonly string $value,
		public readonly bool $is_set,
		public readonly string $placeholder,
		public readonly bool $optional,
	) {}
}

/**
 * Where an app's accounts are connected from.
 */
final class FLXSM_App_Connect {

	public function __construct(
		public readonly string $for,
		public readonly string $label,
		public readonly string $provider,
		public readonly bool $local,
	) {}
}

/**
 * A network developer app (Meta, Google, X …) and whether it is ready.
 */
final class FLXSM_App {

	/**
	 * @param list<FLXSM_App_Field>   $fields  Fields.
	 * @param list<FLXSM_App_Connect> $connect Connect buttons.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $label,
		public readonly string $console,
		public readonly array $fields,
		public readonly string $redirect,
		public readonly string $help,
		public readonly array $connect,
		public readonly bool $ready,
	) {}
}

/**
 * Automatic sharing of new site content.
 */
final class FLXSM_Auto {

	/**
	 * @param list<string> $post_types Post types.
	 * @param list<int>    $accounts   Account IDs.
	 */
	public function __construct(
		public readonly bool $enabled,
		public readonly array $post_types,
		public readonly array $accounts,
		public readonly string $template,
		public readonly string $mode,
		public readonly int $delay,
		public readonly bool $image,
	) {}
}

/**
 * Settings of the Social Media screen. The scheduler key is never part of it.
 */
final class FLXSM_Settings {

	/**
	 * @param list<FLXSM_Choice> $post_types Public post types.
	 * @param list<FLXSM_Choice> $x_tiers    X plans.
	 * @param list<FLXSM_Choice> $wa_choices Evolution connections.
	 */
	public function __construct(
		public readonly bool $approval,
		public readonly string $signature,
		public readonly bool $utm,
		public readonly string $meta_version,
		public readonly string $x_tier,
		public readonly bool $x_premium,
		public readonly int $x_month_limit,
		public readonly string $tiktok_mode,
		public readonly bool $pin_sandbox,
		public readonly bool $li_org,
		public readonly int $wa_conn,
		public readonly FLXSM_Auto $auto,
		public readonly array $post_types,
		public readonly array $x_tiers,
		public readonly array $wa_choices,
		public readonly int $x_month,
		public readonly int $last_tick,
		public readonly string $tick_url,
	) {}
}

/**
 * Numbers of the sidebar and the heartbeat.
 */
final class FLXSM_Counts {

	public function __construct(
		public readonly int $pending,
		public readonly int $scheduled,
		public readonly int $failed,
		public readonly int $due,
	) {}
}

/**
 * Site-level facts the screen needs to start.
 */
final class FLXSM_Config {

	/**
	 * @param list<FLXSM_Network> $networks Networks.
	 */
	public function __construct(
		public readonly int $user_id,
		public readonly string $user_name,
		public readonly bool $can_publish,
		public readonly bool $can_manage,
		public readonly bool $can_upload,
		public readonly array $networks,
		public readonly bool $ai,
		public readonly bool $studio,
		public readonly bool $approval,
		public readonly string $signature,
		public readonly string $x_tier,
		public readonly string $timezone,
		public readonly int $tz_offset,
		public readonly int $week_start,
		public readonly string $version,
	) {}
}

/**
 * Totals of the dashboard.
 */
final class FLXSM_Totals {

	public function __construct(
		public readonly int $published,
		public readonly int $failed,
		public readonly int $scheduled,
		public readonly int $pending,
		public readonly int $drafts,
		public readonly int $engagement,
		public readonly int $views,
		public readonly int $followers,
		public readonly int $followers_delta,
		public readonly ?int $success,
	) {}
}

/**
 * Posts one network published on one day.
 */
final class FLXSM_Day_Count {

	public function __construct(
		public readonly string $provider,
		public readonly int $count,
	) {}
}

/**
 * One day of the activity chart.
 */
final class FLXSM_Day {

	/**
	 * @param list<FLXSM_Day_Count> $networks Per network.
	 */
	public function __construct(
		public readonly string $day,
		public readonly array $networks,
	) {}
}

/**
 * Numbers of one network.
 */
final class FLXSM_Net_Stat {

	public function __construct(
		public readonly string $provider,
		public readonly int $published,
		public readonly int $failed,
		public readonly int $engagement,
		public readonly int $views,
	) {}
}

/**
 * One day of an account's follower count.
 */
final class FLXSM_Follower_Point {

	public function __construct(
		public readonly string $day,
		public readonly int $count,
	) {}
}

/**
 * Follower series of an account.
 */
final class FLXSM_Followers {

	/**
	 * @param list<FLXSM_Follower_Point> $series Daily counts.
	 */
	public function __construct(
		public readonly int $account_id,
		public readonly string $name,
		public readonly string $provider,
		public readonly string $avatar,
		public readonly int $now,
		public readonly int $delta,
		public readonly array $series,
	) {}
}

/**
 * Best performing post of the period.
 */
final class FLXSM_Top_Post {

	public function __construct(
		public readonly int $target_id,
		public readonly int $post_id,
		public readonly string $provider,
		public readonly string $account,
		public readonly string $title,
		public readonly int $engagement,
		public readonly int $views,
		public readonly string $url,
		public readonly int $at,
	) {}
}

/**
 * A recent publishing error.
 */
final class FLXSM_Dash_Error {

	public function __construct(
		public readonly int $target_id,
		public readonly int $post_id,
		public readonly string $account,
		public readonly string $provider,
		public readonly string $error,
		public readonly int $at,
	) {}
}

/**
 * The dashboard.
 */
final class FLXSM_Stats {

	/**
	 * @param list<FLXSM_Day>       $by_day    Daily activity.
	 * @param list<FLXSM_Net_Stat>  $by_net    Per network.
	 * @param list<FLXSM_Followers> $followers Per account.
	 * @param list<FLXSM_Top_Post>  $top       Best posts.
	 * @param list<FLXSM_Post>      $upcoming  Next scheduled posts.
	 * @param list<FLXSM_Dash_Error> $errors   Recent errors.
	 */
	public function __construct(
		public readonly int $days,
		public readonly FLXSM_Totals $totals,
		public readonly array $by_day,
		public readonly array $by_net,
		public readonly array $followers,
		public readonly array $top,
		public readonly array $upcoming,
		public readonly array $errors,
		public readonly int $last_metrics,
	) {}
}

/**
 * Result of a bulk action.
 */
final class FLXSM_Bulk_Result {

	/**
	 * @param list<string> $errors Per-post errors.
	 */
	public function __construct(
		public readonly int $done,
		public readonly array $errors,
	) {}
}

/**
 * Result of one scheduler run.
 */
final class FLXSM_Tick_Result {

	public function __construct(
		public readonly int $processed,
		public readonly int $due,
	) {}
}

/**
 * An item of the Flux Studio library.
 */
final class FLXSM_Studio_Item {

	public function __construct(
		public readonly int $id,
		public readonly string $kind,
		public readonly string $url,
		public readonly string $thumb,
		public readonly string $title,
		public readonly string $prompt,
		public readonly int $attachment_id,
		public readonly int $width,
		public readonly int $height,
	) {}
}

/**
 * A page of the media library.
 */
final class FLXSM_Media_Page {

	/**
	 * @param list<FLXSM_Media> $items Media of the page.
	 */
	public function __construct(
		public readonly array $items,
		public readonly int $pages,
	) {}
}
