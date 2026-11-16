<?php
/**
 * Flux Social GraphQL schema (Flux GraphQL kit). Loaded on flx_gql_register, i.e. only inside a
 * GraphQL request. Resolvers call FLXSM_Accounts_Service, FLXSM_Posts_Service and FLXSM_Admin_Service;
 * the Social Media screen (wp-admin and FluxWP app) reads and writes only through these fields.
 *
 * Network connections (OAuth start, callback, account pick), the external scheduler address and the file
 * upload stay on REST (docs/graphql/ISTISNALAR.md). No token or app secret is ever returned: accounts are
 * public profiles, apps report only whether a secret is set, and the scheduler key is masked until a
 * manager asks for it.
 *
 * @package flx-social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Service result → value, or the WP_Error as a typed GraphQL error.
 *
 * @template T
 * @param T|WP_Error $r Result.
 * @return T
 * @throws FLXGQL_Error On WP_Error.
 */
function flxsm_gql_ok( $r ) {
	if ( $r instanceof WP_Error ) {
		throw flx_gql_from_wp_error( $r );
	}
	return $r;
}

/**
 * Typed parent value of a field.
 *
 * @template T of object
 * @param mixed           $source Parent.
 * @param class-string<T> $class  Expected class.
 * @return T
 * @throws FLXGQL_Error When the parent has another type.
 */
function flxsm_gql_src( $source, string $class ) {
	if ( ! $source instanceof $class ) {
		throw flx_gql_error( 'INTERNAL' );
	}
	return $source;
}

/**
 * Fields that read one property of the parent DTO.
 *
 * Each spec: [ property, type, description, kind? ]. Kind "time" returns FlxDateTime; "id:<Type>" returns a global ID.
 *
 * @param class-string                          $class DTO class.
 * @param array<string,array<int,string>>       $spec  Field specs.
 * @return array<string,array<string,mixed>>
 */
function flxsm_gql_props( string $class, array $spec ): array {
	$out = array();
	foreach ( $spec as $name => $s ) {
		$prop = $s[0];
		$kind = $s[3] ?? '';
		$out[ $name ] = array(
			'type'        => $s[1],
			'description' => $s[2],
			'resolve'     => static function ( $src ) use ( $class, $prop, $kind ) {
				$o = flxsm_gql_src( $src, $class );
				$v = $o->{$prop};
				if ( 'time' === $kind ) {
					return flx_gql_datetime( FLXSM_Cast::int( $v ) ? FLXSM_Cast::int( $v ) : null );
				}
				if ( str_starts_with( $kind, 'id:' ) ) {
					return flx_gql_global_id( substr( $kind, 3 ), FLXSM_Cast::int( $v ) );
				}
				return $v;
			},
		);
	}
	return $out;
}

/** General permission: anybody who can prepare posts. */
function flxsm_gql_cap_post(): Closure {
	return flx_gql_any_cap( FLXSM_CAP_POST, FLXSM_CAP_PUBLISH, FLXSM_CAP_MANAGE );
}

/** Manager permission: accounts, apps, settings. */
function flxsm_gql_cap_manage(): Closure {
	return flx_gql_any_cap( FLXSM_CAP_MANAGE );
}

/**
 * Account, post and settings changes clear these cache tags.
 *
 * @param list<string> $which accounts | posts | settings.
 */
function flxsm_gql_purge( array $which ): void {
	flx_gql_purge( array_map( static fn( string $w ): string => 'flx-social:' . $w, $which ) );
}

/* ---------------- Enums ---------------- */

flx_gql_enum(
	'FlxSocialPostStatus',
	array(
		'description' => __( 'Where a post is in its life.', 'flx-social' ),
		'values'      => array(
			'DRAFT'      => array( 'value' => 'draft', 'description' => __( 'Being prepared.', 'flx-social' ) ),
			'PENDING'    => array( 'value' => 'pending', 'description' => __( 'Waiting for approval.', 'flx-social' ) ),
			'SCHEDULED'  => array( 'value' => 'scheduled', 'description' => __( 'Will be shared at its time.', 'flx-social' ) ),
			'PUBLISHING' => array( 'value' => 'publishing', 'description' => __( 'Being shared now.', 'flx-social' ) ),
			'PUBLISHED'  => array( 'value' => 'published', 'description' => __( 'Shared on every account.', 'flx-social' ) ),
			'PARTIAL'    => array( 'value' => 'partial', 'description' => __( 'Shared on some accounts, failed on others.', 'flx-social' ) ),
			'FAILED'     => array( 'value' => 'failed', 'description' => __( 'Could not be shared.', 'flx-social' ) ),
			'CANCELLED'  => array( 'value' => 'cancelled', 'description' => __( 'Called off.', 'flx-social' ) ),
		),
	)
);

flx_gql_enum(
	'FlxSocialTargetStatus',
	array(
		'description' => __( 'State of a post on one account.', 'flx-social' ),
		'values'      => array(
			'DRAFT'      => array( 'value' => 'draft', 'description' => __( 'Being prepared.', 'flx-social' ) ),
			'SCHEDULED'  => array( 'value' => 'scheduled', 'description' => __( 'Waiting for its time.', 'flx-social' ) ),
			'PROCESSING' => array( 'value' => 'processing', 'description' => __( 'Being shared now.', 'flx-social' ) ),
			'PUBLISHED'  => array( 'value' => 'published', 'description' => __( 'Shared.', 'flx-social' ) ),
			'FAILED'     => array( 'value' => 'failed', 'description' => __( 'Could not be shared.', 'flx-social' ) ),
			'CANCELLED'  => array( 'value' => 'cancelled', 'description' => __( 'Called off.', 'flx-social' ) ),
			'DELETED'    => array( 'value' => 'deleted', 'description' => __( 'Deleted on the network.', 'flx-social' ) ),
		),
	)
);

flx_gql_enum(
	'FlxSocialAccountStatus',
	array(
		'description' => __( 'Connection state of an account.', 'flx-social' ),
		'values'      => array(
			'ACTIVE'   => array( 'value' => 'active', 'description' => __( 'Connected.', 'flx-social' ) ),
			'DISABLED' => array( 'value' => 'disabled', 'description' => __( 'Switched off in the panel.', 'flx-social' ) ),
			'EXPIRED'  => array( 'value' => 'expired', 'description' => __( 'The connection expired; connect again.', 'flx-social' ) ),
		),
	)
);

flx_gql_enum(
	'FlxSocialPostAction',
	array(
		'description' => __( 'A state change of a post.', 'flx-social' ),
		'values'      => array(
			'DRAFT'    => array( 'value' => 'draft', 'description' => __( 'Keep as a draft.', 'flx-social' ) ),
			'SUBMIT'   => array( 'value' => 'submit', 'description' => __( 'Send for approval.', 'flx-social' ) ),
			'SCHEDULE' => array( 'value' => 'schedule', 'description' => __( 'Share at the scheduled time.', 'flx-social' ) ),
			'NOW'      => array( 'value' => 'now', 'description' => __( 'Share now.', 'flx-social' ) ),
			'APPROVE'  => array( 'value' => 'approve', 'description' => __( 'Approve and share.', 'flx-social' ) ),
			'REJECT'   => array( 'value' => 'reject', 'description' => __( 'Send back to draft with a note.', 'flx-social' ) ),
			'CANCEL'   => array( 'value' => 'cancel', 'description' => __( 'Call off.', 'flx-social' ) ),
		),
	)
);

flx_gql_enum(
	'FlxSocialBulkAction',
	array(
		'description' => __( 'An action on many posts.', 'flx-social' ),
		'values'      => array(
			'DRAFT'    => array( 'value' => 'draft', 'description' => __( 'Back to draft.', 'flx-social' ) ),
			'SUBMIT'   => array( 'value' => 'submit', 'description' => __( 'Send for approval.', 'flx-social' ) ),
			'SCHEDULE' => array( 'value' => 'schedule', 'description' => __( 'Schedule.', 'flx-social' ) ),
			'NOW'      => array( 'value' => 'now', 'description' => __( 'Share now.', 'flx-social' ) ),
			'APPROVE'  => array( 'value' => 'approve', 'description' => __( 'Approve and share.', 'flx-social' ) ),
			'CANCEL'   => array( 'value' => 'cancel', 'description' => __( 'Call off.', 'flx-social' ) ),
			'DELETE'   => array( 'value' => 'delete', 'description' => __( 'Delete from the panel.', 'flx-social' ) ),
		),
	)
);

flx_gql_enum(
	'FlxSocialAiMode',
	array(
		'description' => __( 'What the writing assistant does.', 'flx-social' ),
		'values'      => array(
			'WRITE'    => array( 'value' => 'write', 'description' => __( 'Write from an idea.', 'flx-social' ) ),
			'IMPROVE'  => array( 'value' => 'improve', 'description' => __( 'Fix and polish.', 'flx-social' ) ),
			'SHORTEN'  => array( 'value' => 'shorten', 'description' => __( 'Make shorter.', 'flx-social' ) ),
			'HASHTAGS' => array( 'value' => 'hashtags', 'description' => __( 'Suggest hashtags.', 'flx-social' ) ),
			'ADAPT'    => array( 'value' => 'adapt', 'description' => __( 'A separate text per network.', 'flx-social' ) ),
			'TRANSLATE' => array( 'value' => 'translate', 'description' => __( 'Translate.', 'flx-social' ) ),
		),
	)
);

/* ---------------- Types ---------------- */

flx_gql_type(
	'FlxSocialCaps',
	array(
		'description' => __( 'What a network can do.', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_Caps::class,
			array(
				'text'       => array( 'text', 'Boolean!', __( 'Text posts.', 'flx-social' ) ),
				'image'      => array( 'image', 'Boolean!', __( 'Images.', 'flx-social' ) ),
				'video'      => array( 'video', 'Boolean!', __( 'Videos.', 'flx-social' ) ),
				'gif'        => array( 'gif', 'Boolean!', __( 'GIFs.', 'flx-social' ) ),
				'maxText'    => array( 'max_text', 'Int!', __( 'Longest text (characters).', 'flx-social' ) ),
				'maxImages'  => array( 'max_images', 'Int!', __( 'Most images in one post.', 'flx-social' ) ),
				'maxVideos'  => array( 'max_videos', 'Int!', __( 'Most videos in one post.', 'flx-social' ) ),
				'mix'        => array( 'mix', 'Boolean!', __( 'Images and videos together.', 'flx-social' ) ),
				'needsMedia' => array( 'needs_media', 'Boolean!', __( 'A post needs an image or video.', 'flx-social' ) ),
				'edit'       => array( 'edit', 'Boolean!', __( 'A shared post can be edited on the network.', 'flx-social' ) ),
				'delete'     => array( 'delete', 'Boolean!', __( 'A shared post can be deleted on the network.', 'flx-social' ) ),
				'comment'    => array( 'comment', 'Boolean!', __( 'A first comment can be added.', 'flx-social' ) ),
				'metrics'    => array( 'metrics', 'Boolean!', __( 'The network reports views and likes.', 'flx-social' ) ),
				'options'    => array( 'options', '[String!]!', __( 'Network options the composer shows (keys of FlxSocialOptions).', 'flx-social' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSocialNetwork',
	array(
		'description' => __( 'A social network FluxWP can share to.', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_Network::class,
			array(
				'id'    => array( 'id', 'String!', __( 'Network key: facebook, instagram, x …', 'flx-social' ) ),
				'label' => array( 'label', 'String!', __( 'Name.', 'flx-social' ) ),
				'color' => array( 'color', 'String!', __( 'Brand colour.', 'flx-social' ) ),
				'caps'  => array( 'caps', 'FlxSocialCaps!', __( 'What the network can do.', 'flx-social' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSocialChoice',
	array(
		'description' => __( 'A board, playlist, group or choice with a name.', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_Choice::class,
			array(
				'id'   => array( 'id', 'String!', __( 'Value.', 'flx-social' ) ),
				'name' => array( 'name', 'String!', __( 'Name.', 'flx-social' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSocialAccount',
	array(
		'description' => __( 'A connected social account (public profile only; tokens never leave the server).', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_Account::class,
			array(
				'id'         => array( 'id', 'ID!', __( 'Global ID.', 'flx-social' ), 'id:FlxSocialAccount' ),
				'databaseId' => array( 'id', 'Int!', __( 'Account ID.', 'flx-social' ) ),
				'provider'   => array( 'provider', 'String!', __( 'Network key.', 'flx-social' ) ),
				'via'        => array( 'via', 'String!', __( 'How it is connected (evolution for WhatsApp).', 'flx-social' ) ),
				'network'    => array( 'network', 'String!', __( 'Network name.', 'flx-social' ) ),
				'name'       => array( 'name', 'String!', __( 'Display name.', 'flx-social' ) ),
				'username'   => array( 'username', 'String!', __( 'Handle.', 'flx-social' ) ),
				'avatar'     => array( 'avatar', 'String!', __( 'Picture address.', 'flx-social' ) ),
				'status'     => array( 'status', 'FlxSocialAccountStatus!', __( 'Connection state.', 'flx-social' ) ),
				'expires'    => array( 'expires', 'FlxDateTime', __( 'When the connection ends; empty when it does not.', 'flx-social' ), 'time' ),
				'kind'       => array( 'kind', 'String!', __( 'WhatsApp: status or group.', 'flx-social' ) ),
				'boards'     => array( 'boards', '[FlxSocialChoice!]!', __( 'Pinterest boards.', 'flx-social' ) ),
				'groups'     => array( 'groups', '[FlxSocialChoice!]!', __( 'WhatsApp groups.', 'flx-social' ) ),
				'playlists'  => array( 'playlists', '[FlxSocialChoice!]!', __( 'YouTube playlists.', 'flx-social' ) ),
				'premium'    => array( 'premium', 'Boolean!', __( 'The account has the paid tier (longer posts on X).', 'flx-social' ) ),
				'lastError'  => array( 'last_error', 'String!', __( 'Last problem the network reported.', 'flx-social' ) ),
				'caps'       => array( 'caps', 'FlxSocialCaps!', __( 'What this account can do.', 'flx-social' ) ),
				'created'    => array( 'created', 'FlxDateTime', __( 'When it was connected.', 'flx-social' ), 'time' ),
			)
		),
	)
);

flx_gql_type(
	'FlxSocialMedia',
	array(
		'description' => __( 'An image, video or GIF of the media library.', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_Media::class,
			array(
				'databaseId' => array( 'id', 'Int!', __( 'Attachment ID.', 'flx-social' ) ),
				'url'        => array( 'url', 'String!', __( 'File address.', 'flx-social' ) ),
				'kind'       => array( 'kind', 'String!', __( 'image, video or gif.', 'flx-social' ) ),
				'mime'       => array( 'mime', 'String!', __( 'File type.', 'flx-social' ) ),
				'bytes'      => array( 'bytes', 'Int!', __( 'Size in bytes.', 'flx-social' ) ),
				'width'      => array( 'width', 'Int!', __( 'Width in pixels.', 'flx-social' ) ),
				'height'     => array( 'height', 'Int!', __( 'Height in pixels.', 'flx-social' ) ),
				'duration'   => array( 'duration', 'Float!', __( 'Video length in seconds.', 'flx-social' ) ),
				'thumb'      => array( 'thumb', 'String!', __( 'Small picture address.', 'flx-social' ) ),
				'name'       => array( 'name', 'String!', __( 'File title.', 'flx-social' ) ),
				'alt'        => array( 'alt', 'String!', __( 'Alternative text.', 'flx-social' ) ),
			)
		),
	)
);

/**
 * Option fields of FlxSocialOptions: camelCase of every key the composer knows.
 *
 * @return array<string,array<string,mixed>>
 */
function flxsm_gql_option_fields(): array {
	$out = array();
	foreach ( FLXSM_Options::STRINGS as $k ) {
		$out[ FLXSM_Options::camel( $k ) ] = array(
			'type'        => 'String',
			'description' => __( 'Network option.', 'flx-social' ),
			'resolve'     => static fn( $o ) => is_array( $o ) && isset( $o[ $k ] ) ? FLXSM_Cast::str( $o[ $k ] ) : null,
		);
	}
	foreach ( FLXSM_Options::BOOLS as $k ) {
		$out[ FLXSM_Options::camel( $k ) ] = array(
			'type'        => 'Boolean',
			'description' => __( 'Network option.', 'flx-social' ),
			'resolve'     => static fn( $o ) => is_array( $o ) && isset( $o[ $k ] ) ? FLXSM_Cast::bool( $o[ $k ] ) : null,
		);
	}
	$out['tags'] = array(
		'type'        => '[String!]',
		'description' => __( 'Hashtags of the account.', 'flx-social' ),
		'resolve'     => static fn( $o ) => is_array( $o ) && isset( $o['tags'] ) ? FLXSM_Cast::strings( $o['tags'] ) : null,
	);
	return $out;
}

/**
 * Option inputs (same names).
 *
 * @return array<string,array<string,mixed>>
 */
function flxsm_gql_option_inputs(): array {
	$out = array();
	foreach ( FLXSM_Options::STRINGS as $k ) {
		$out[ FLXSM_Options::camel( $k ) ] = array(
			'type'        => 'String',
			'description' => __( 'Network option.', 'flx-social' ),
		);
	}
	foreach ( FLXSM_Options::BOOLS as $k ) {
		$out[ FLXSM_Options::camel( $k ) ] = array(
			'type'        => 'Boolean',
			'description' => __( 'Network option.', 'flx-social' ),
		);
	}
	$out['tags'] = array(
		'type'        => '[String!]',
		'description' => __( 'Hashtags of the account.', 'flx-social' ),
	);
	return $out;
}

flx_gql_type(
	'FlxSocialOptions',
	array(
		'description' => __( 'Options of a post on one account (what the network asks for: link, board, privacy …).', 'flx-social' ),
		'fields'      => flxsm_gql_option_fields(),
	)
);

flx_gql_input(
	'FlxSocialOptionsInput',
	array(
		'description' => __( 'Options of a post on one account.', 'flx-social' ),
		'fields'      => flxsm_gql_option_inputs(),
	)
);

flx_gql_type(
	'FlxSocialMetrics',
	array(
		'description' => __( 'Numbers the network reported for a shared post.', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_Metrics::class,
			array(
				'views'       => array( 'views', 'Int!', __( 'Views.', 'flx-social' ) ),
				'impressions' => array( 'impressions', 'Int!', __( 'Impressions.', 'flx-social' ) ),
				'reach'       => array( 'reach', 'Int!', __( 'People reached.', 'flx-social' ) ),
				'clicks'      => array( 'clicks', 'Int!', __( 'Clicks.', 'flx-social' ) ),
				'likes'       => array( 'likes', 'Int!', __( 'Likes.', 'flx-social' ) ),
				'comments'    => array( 'comments', 'Int!', __( 'Comments.', 'flx-social' ) ),
				'shares'      => array( 'shares', 'Int!', __( 'Shares.', 'flx-social' ) ),
				'saves'       => array( 'saves', 'Int!', __( 'Saves.', 'flx-social' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSocialTarget',
	array(
		'description' => __( 'A post on one account.', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_Target::class,
			array(
				'id'          => array( 'id', 'ID!', __( 'Global ID.', 'flx-social' ), 'id:FlxSocialTarget' ),
				'databaseId'  => array( 'id', 'Int!', __( 'Target ID.', 'flx-social' ) ),
				'accountId'   => array( 'account_id', 'Int!', __( 'Account ID.', 'flx-social' ) ),
				'provider'    => array( 'provider', 'String!', __( 'Network key.', 'flx-social' ) ),
				'account'     => array( 'account', 'String!', __( 'Account name.', 'flx-social' ) ),
				'avatar'      => array( 'avatar', 'String!', __( 'Account picture address.', 'flx-social' ) ),
				'status'      => array( 'status', 'FlxSocialTargetStatus!', __( 'State on this account.', 'flx-social' ) ),
				'remoteUrl'   => array( 'remote_url', 'String!', __( 'Address of the shared post on the network.', 'flx-social' ) ),
				'error'       => array( 'error', 'String!', __( 'Why it failed.', 'flx-social' ) ),
				'publishedAt' => array( 'published_at', 'FlxDateTime', __( 'When it was shared.', 'flx-social' ), 'time' ),
				'metrics'     => array( 'metrics', 'FlxSocialMetrics!', __( 'Network numbers.', 'flx-social' ) ),
				'caps'        => array( 'caps', 'FlxSocialCaps!', __( 'What this account can do.', 'flx-social' ) ),
				'content'     => array( 'content', 'String', __( 'Own text for this account; empty uses the shared text.', 'flx-social' ) ),
				'media'       => array( 'media', '[FlxSocialMedia!]', __( 'Own media for this account; empty uses the shared media.', 'flx-social' ) ),
				'options'     => array( 'options', 'FlxSocialOptions!', __( 'Network options.', 'flx-social' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSocialPost',
	array(
		'description' => __( 'A post and the accounts it goes to.', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_Post::class,
			array(
				'id'          => array( 'id', 'ID!', __( 'Global ID.', 'flx-social' ), 'id:FlxSocialPost' ),
				'databaseId'  => array( 'id', 'Int!', __( 'Post ID.', 'flx-social' ) ),
				'status'      => array( 'status', 'FlxSocialPostStatus!', __( 'State.', 'flx-social' ) ),
				'title'       => array( 'title', 'String!', __( 'Internal title.', 'flx-social' ) ),
				'content'     => array( 'content', 'String!', __( 'Shared text.', 'flx-social' ) ),
				'media'       => array( 'media', '[FlxSocialMedia!]!', __( 'Shared images and videos.', 'flx-social' ) ),
				'tags'        => array( 'tags', '[String!]!', __( 'Labels.', 'flx-social' ) ),
				'scheduledAt' => array( 'scheduled_at', 'FlxDateTime', __( 'When it will be shared.', 'flx-social' ), 'time' ),
				'publishedAt' => array( 'published_at', 'FlxDateTime', __( 'When it was shared.', 'flx-social' ), 'time' ),
				'repeatDays'  => array( 'repeat_days', 'Int!', __( 'Share again every N days (0: once).', 'flx-social' ) ),
				'source'      => array( 'source', 'String!', __( 'manual, ai, app, auto or duplicate.', 'flx-social' ) ),
				'note'        => array( 'note', 'String!', __( 'Note (the reason when a post was sent back).', 'flx-social' ) ),
				'author'      => array( 'user', 'String!', __( 'Name of the person who prepared it.', 'flx-social' ) ),
				'authorId'    => array( 'user_id', 'Int!', __( 'User ID of the author.', 'flx-social' ) ),
				'approvedBy'  => array( 'approved_by', 'Int!', __( 'User ID of the approver (0: none).', 'flx-social' ) ),
				'created'     => array( 'created', 'FlxDateTime', __( 'When it was created.', 'flx-social' ), 'time' ),
				'updated'     => array( 'updated', 'FlxDateTime', __( 'When it was last changed.', 'flx-social' ), 'time' ),
				'targets'     => array( 'targets', '[FlxSocialTarget!]!', __( 'Accounts the post goes to.', 'flx-social' ) ),
			)
		),
	)
);

flx_gql_connection( 'FlxSocialPost', __( 'A page of posts.', 'flx-social' ) );

flx_gql_type(
	'FlxSocialConfig',
	array(
		'description' => __( 'What the Social Media screen needs to start.', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_Config::class,
			array(
				'userId'     => array( 'user_id', 'Int!', __( 'Current user.', 'flx-social' ) ),
				'userName'   => array( 'user_name', 'String!', __( 'Current user name.', 'flx-social' ) ),
				'canPublish' => array( 'can_publish', 'Boolean!', __( 'The user can publish and approve.', 'flx-social' ) ),
				'canManage'  => array( 'can_manage', 'Boolean!', __( 'The user can manage accounts and settings.', 'flx-social' ) ),
				'canUpload'  => array( 'can_upload', 'Boolean!', __( 'The user can upload files.', 'flx-social' ) ),
				'networks'   => array( 'networks', '[FlxSocialNetwork!]!', __( 'Networks.', 'flx-social' ) ),
				'ai'         => array( 'ai', 'Boolean!', __( 'The writing assistant is ready (Flux AI).', 'flx-social' ) ),
				'studio'     => array( 'studio', 'Boolean!', __( 'The Flux Studio library can be used.', 'flx-social' ) ),
				'approval'   => array( 'approval', 'Boolean!', __( 'Posts of people who cannot publish wait for approval.', 'flx-social' ) ),
				'signature'  => array( 'signature', 'String!', __( 'Text added to the end of posts.', 'flx-social' ) ),
				'xTier'      => array( 'x_tier', 'String!', __( 'X plan.', 'flx-social' ) ),
				'timezone'   => array( 'timezone', 'String!', __( 'Site time zone.', 'flx-social' ) ),
				'tzOffset'   => array( 'tz_offset', 'Int!', __( 'Site time zone offset in seconds.', 'flx-social' ) ),
				'weekStart'  => array( 'week_start', 'Int!', __( 'First day of the week (0: Sunday).', 'flx-social' ) ),
				'version'    => array( 'version', 'String!', __( 'Plugin version.', 'flx-social' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSocialCounts',
	array(
		'description' => __( 'Numbers for the sidebar and the scheduler.', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_Counts::class,
			array(
				'pending'   => array( 'pending', 'Int!', __( 'Posts waiting for approval.', 'flx-social' ) ),
				'scheduled' => array( 'scheduled', 'Int!', __( 'Scheduled posts.', 'flx-social' ) ),
				'failed'    => array( 'failed', 'Int!', __( 'Posts that failed.', 'flx-social' ) ),
				'due'       => array( 'due', 'Int!', __( 'Accounts waiting to be shared now.', 'flx-social' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSocialTotals',
	array(
		'description' => __( 'Totals of the dashboard.', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_Totals::class,
			array(
				'published'      => array( 'published', 'Int!', __( 'Shared in the period.', 'flx-social' ) ),
				'failed'         => array( 'failed', 'Int!', __( 'Failed in the period.', 'flx-social' ) ),
				'scheduled'      => array( 'scheduled', 'Int!', __( 'Scheduled now.', 'flx-social' ) ),
				'pending'        => array( 'pending', 'Int!', __( 'Waiting for approval now.', 'flx-social' ) ),
				'drafts'         => array( 'drafts', 'Int!', __( 'Drafts.', 'flx-social' ) ),
				'engagement'     => array( 'engagement', 'Int!', __( 'Likes, comments, shares and saves.', 'flx-social' ) ),
				'views'          => array( 'views', 'Int!', __( 'Views.', 'flx-social' ) ),
				'followers'      => array( 'followers', 'Int!', __( 'Followers on every account.', 'flx-social' ) ),
				'followersDelta' => array( 'followers_delta', 'Int!', __( 'Change of followers in the period.', 'flx-social' ) ),
				'successRate'    => array( 'success', 'Int', __( 'Share of posts that succeeded (percent); empty without posts.', 'flx-social' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSocialDayCount',
	array(
		'description' => __( 'Posts one network shared on a day.', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_Day_Count::class,
			array(
				'provider' => array( 'provider', 'String!', __( 'Network key.', 'flx-social' ) ),
				'count'    => array( 'count', 'Int!', __( 'Posts.', 'flx-social' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSocialDay',
	array(
		'description' => __( 'One day of the activity chart.', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_Day::class,
			array(
				'day'      => array( 'day', 'String!', __( 'Date (YYYY-MM-DD, site time zone).', 'flx-social' ) ),
				'networks' => array( 'networks', '[FlxSocialDayCount!]!', __( 'Posts per network.', 'flx-social' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSocialNetworkStat',
	array(
		'description' => __( 'Numbers of one network in the period.', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_Net_Stat::class,
			array(
				'provider'   => array( 'provider', 'String!', __( 'Network key.', 'flx-social' ) ),
				'published'  => array( 'published', 'Int!', __( 'Shared.', 'flx-social' ) ),
				'failed'     => array( 'failed', 'Int!', __( 'Failed.', 'flx-social' ) ),
				'engagement' => array( 'engagement', 'Int!', __( 'Likes, comments, shares and saves.', 'flx-social' ) ),
				'views'      => array( 'views', 'Int!', __( 'Views.', 'flx-social' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSocialFollowerPoint',
	array(
		'description' => __( 'Followers of an account on one day.', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_Follower_Point::class,
			array(
				'day'   => array( 'day', 'String!', __( 'Date (YYYY-MM-DD).', 'flx-social' ) ),
				'count' => array( 'count', 'Int!', __( 'Followers.', 'flx-social' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSocialFollowers',
	array(
		'description' => __( 'Follower series of an account.', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_Followers::class,
			array(
				'accountId' => array( 'account_id', 'Int!', __( 'Account ID.', 'flx-social' ) ),
				'name'      => array( 'name', 'String!', __( 'Account name.', 'flx-social' ) ),
				'provider'  => array( 'provider', 'String!', __( 'Network key.', 'flx-social' ) ),
				'avatar'    => array( 'avatar', 'String!', __( 'Picture address.', 'flx-social' ) ),
				'now'       => array( 'now', 'Int!', __( 'Followers now.', 'flx-social' ) ),
				'delta'     => array( 'delta', 'Int!', __( 'Change in the period.', 'flx-social' ) ),
				'series'    => array( 'series', '[FlxSocialFollowerPoint!]!', __( 'Daily counts.', 'flx-social' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSocialTopPost',
	array(
		'description' => __( 'A post that did well.', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_Top_Post::class,
			array(
				'targetId'   => array( 'target_id', 'Int!', __( 'Target ID.', 'flx-social' ) ),
				'postId'     => array( 'post_id', 'Int!', __( 'Post ID.', 'flx-social' ) ),
				'provider'   => array( 'provider', 'String!', __( 'Network key.', 'flx-social' ) ),
				'account'    => array( 'account', 'String!', __( 'Account name.', 'flx-social' ) ),
				'title'      => array( 'title', 'String!', __( 'Title or start of the text.', 'flx-social' ) ),
				'engagement' => array( 'engagement', 'Int!', __( 'Likes, comments, shares and saves.', 'flx-social' ) ),
				'views'      => array( 'views', 'Int!', __( 'Views.', 'flx-social' ) ),
				'url'        => array( 'url', 'String!', __( 'Address on the network.', 'flx-social' ) ),
				'at'         => array( 'at', 'FlxDateTime', __( 'When it was shared.', 'flx-social' ), 'time' ),
			)
		),
	)
);

flx_gql_type(
	'FlxSocialDashError',
	array(
		'description' => __( 'A recent sharing problem.', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_Dash_Error::class,
			array(
				'targetId' => array( 'target_id', 'Int!', __( 'Target ID.', 'flx-social' ) ),
				'postId'   => array( 'post_id', 'Int!', __( 'Post ID.', 'flx-social' ) ),
				'account'  => array( 'account', 'String!', __( 'Account name.', 'flx-social' ) ),
				'provider' => array( 'provider', 'String!', __( 'Network key.', 'flx-social' ) ),
				'error'    => array( 'error', 'String!', __( 'What the network said.', 'flx-social' ) ),
				'at'       => array( 'at', 'FlxDateTime', __( 'When.', 'flx-social' ), 'time' ),
			)
		),
	)
);

flx_gql_type(
	'FlxSocialStats',
	array(
		'description' => __( 'The dashboard of a period.', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_Stats::class,
			array(
				'days'        => array( 'days', 'Int!', __( 'Period in days (7, 30 or 90).', 'flx-social' ) ),
				'totals'      => array( 'totals', 'FlxSocialTotals!', __( 'Totals.', 'flx-social' ) ),
				'byDay'       => array( 'by_day', '[FlxSocialDay!]!', __( 'Posts per day.', 'flx-social' ) ),
				'byNetwork'   => array( 'by_net', '[FlxSocialNetworkStat!]!', __( 'Numbers per network.', 'flx-social' ) ),
				'followers'   => array( 'followers', '[FlxSocialFollowers!]!', __( 'Followers per account.', 'flx-social' ) ),
				'top'         => array( 'top', '[FlxSocialTopPost!]!', __( 'Best posts.', 'flx-social' ) ),
				'upcoming'    => array( 'upcoming', '[FlxSocialPost!]!', __( 'Next scheduled posts.', 'flx-social' ) ),
				'errors'      => array( 'errors', '[FlxSocialDashError!]!', __( 'Recent problems.', 'flx-social' ) ),
				'lastMetrics' => array( 'last_metrics', 'FlxDateTime', __( 'When the network numbers were last read.', 'flx-social' ), 'time' ),
			)
		),
	)
);

flx_gql_type(
	'FlxSocialAuto',
	array(
		'description' => __( 'Automatic sharing of new site content.', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_Auto::class,
			array(
				'enabled'   => array( 'enabled', 'Boolean!', __( 'On.', 'flx-social' ) ),
				'postTypes' => array( 'post_types', '[String!]!', __( 'Content types.', 'flx-social' ) ),
				'accounts'  => array( 'accounts', '[Int!]!', __( 'Account IDs.', 'flx-social' ) ),
				'template'  => array( 'template', 'String!', __( 'Text template ({title}, {excerpt}, {link}).', 'flx-social' ) ),
				'mode'      => array( 'mode', 'String!', __( 'draft or schedule.', 'flx-social' ) ),
				'delay'     => array( 'delay', 'Int!', __( 'Minutes to wait before sharing.', 'flx-social' ) ),
				'image'     => array( 'image', 'Boolean!', __( 'Add the featured image.', 'flx-social' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSocialSettings',
	array(
		'description' => __( 'Settings of Social Media. Keys and tokens are not part of it.', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_Settings::class,
			array(
				'approval'     => array( 'approval', 'Boolean!', __( 'Posts of people who cannot publish wait for approval.', 'flx-social' ) ),
				'signature'    => array( 'signature', 'String!', __( 'Text added to the end of posts.', 'flx-social' ) ),
				'utm'          => array( 'utm', 'Boolean!', __( 'Add UTM tags to links of this site.', 'flx-social' ) ),
				'metaVersion'  => array( 'meta_version', 'String!', __( 'Facebook and Instagram API version.', 'flx-social' ) ),
				'xTier'        => array( 'x_tier', 'String!', __( 'X plan.', 'flx-social' ) ),
				'xPremium'     => array( 'x_premium', 'Boolean!', __( 'Accounts have the paid tier on X.', 'flx-social' ) ),
				'xMonthLimit'  => array( 'x_month_limit', 'Int!', __( 'Posts per month on X (0: no limit).', 'flx-social' ) ),
				'tiktokMode'   => array( 'tiktok_mode', 'String!', __( 'inbox or direct.', 'flx-social' ) ),
				'pinSandbox'   => array( 'pin_sandbox', 'Boolean!', __( 'Pinterest sandbox.', 'flx-social' ) ),
				'liOrg'        => array( 'li_org', 'Boolean!', __( 'List LinkedIn pages.', 'flx-social' ) ),
				'waConn'       => array( 'wa_conn', 'Int!', __( 'WhatsApp connection ID (0: default).', 'flx-social' ) ),
				'auto'         => array( 'auto', 'FlxSocialAuto!', __( 'Automatic sharing.', 'flx-social' ) ),
				'postTypes'    => array( 'post_types', '[FlxSocialChoice!]!', __( 'Content types that can be shared automatically.', 'flx-social' ) ),
				'xTiers'       => array( 'x_tiers', '[FlxSocialChoice!]!', __( 'X plans.', 'flx-social' ) ),
				'waChoices'    => array( 'wa_choices', '[FlxSocialChoice!]!', __( 'WhatsApp connections.', 'flx-social' ) ),
				'xMonth'       => array( 'x_month', 'Int!', __( 'Posts shared on X this month.', 'flx-social' ) ),
				'lastTick'     => array( 'last_tick', 'FlxDateTime', __( 'When the scheduler last ran.', 'flx-social' ), 'time' ),
				'tickUrl'      => array( 'tick_url', 'String!', __( 'Address an external cron calls. The key is hidden; flxSocialTickKeyReveal returns it.', 'flx-social' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSocialAppField',
	array(
		'description' => __( 'A field of a network app. A secret is never returned, only whether it is set.', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_App_Field::class,
			array(
				'key'         => array( 'key', 'String!', __( 'Field key.', 'flx-social' ) ),
				'label'       => array( 'label', 'String!', __( 'Name.', 'flx-social' ) ),
				'secret'      => array( 'secret', 'Boolean!', __( 'The value is a secret.', 'flx-social' ) ),
				'value'       => array( 'value', 'String!', __( 'Value; empty for a secret.', 'flx-social' ) ),
				'isSet'       => array( 'is_set', 'Boolean!', __( 'The field has a value.', 'flx-social' ) ),
				'placeholder' => array( 'placeholder', 'String!', __( 'Hint.', 'flx-social' ) ),
				'optional'    => array( 'optional', 'Boolean!', __( 'The app works without it.', 'flx-social' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSocialAppConnect',
	array(
		'description' => __( 'A "connect account" button of an app.', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_App_Connect::class,
			array(
				'for'      => array( 'for', 'String!', __( 'Key to pass to flxSocialConnectUrl.', 'flx-social' ) ),
				'label'    => array( 'label', 'String!', __( 'Button text.', 'flx-social' ) ),
				'provider' => array( 'provider', 'String!', __( 'Network key.', 'flx-social' ) ),
				'local'    => array( 'local', 'Boolean!', __( 'Connected from this site (WhatsApp), not through the network sign-in.', 'flx-social' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSocialApp',
	array(
		'description' => __( 'A network developer app (Meta, Google, X …) and whether it is ready.', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_App::class,
			array(
				'id'       => array( 'id', 'String!', __( 'App key.', 'flx-social' ) ),
				'label'    => array( 'label', 'String!', __( 'Name.', 'flx-social' ) ),
				'console'  => array( 'console', 'String!', __( 'Address of the developer console.', 'flx-social' ) ),
				'fields'   => array( 'fields', '[FlxSocialAppField!]!', __( 'Fields.', 'flx-social' ) ),
				'redirect' => array( 'redirect', 'String!', __( 'Callback address to enter in the console.', 'flx-social' ) ),
				'help'     => array( 'help', 'String!', __( 'Setup help.', 'flx-social' ) ),
				'connect'  => array( 'connect', '[FlxSocialAppConnect!]!', __( 'Connect buttons.', 'flx-social' ) ),
				'ready'    => array( 'ready', 'Boolean!', __( 'Every required field is filled.', 'flx-social' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSocialLogRow',
	array(
		'description' => __( 'A row of the activity log.', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_Log_Row::class,
			array(
				'databaseId' => array( 'id', 'Int!', __( 'Row ID.', 'flx-social' ) ),
				'time'       => array( 'time', 'FlxDateTime', __( 'When.', 'flx-social' ), 'time' ),
				'level'      => array( 'level', 'String!', __( 'info, warning or error.', 'flx-social' ) ),
				'accountId'  => array( 'account_id', 'Int!', __( 'Account ID (0: none).', 'flx-social' ) ),
				'message'    => array( 'message', 'String!', __( 'What happened.', 'flx-social' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSocialWhatsAppTarget',
	array(
		'description' => __( 'A WhatsApp group or Status the connection can post to.', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_WA_Target::class,
			array(
				'extId'  => array( 'ext_id', 'String!', __( 'WhatsApp ID.', 'flx-social' ) ),
				'name'   => array( 'name', 'String!', __( 'Name.', 'flx-social' ) ),
				'kind'   => array( 'kind', 'String!', __( 'status or group.', 'flx-social' ) ),
				'avatar' => array( 'avatar', 'String!', __( 'Picture address.', 'flx-social' ) ),
				'exists' => array( 'exists', 'Boolean!', __( 'Already added as an account.', 'flx-social' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSocialMediaPage',
	array(
		'description' => __( 'A page of the media library.', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_Media_Page::class,
			array(
				'items' => array( 'items', '[FlxSocialMedia!]!', __( 'Media.', 'flx-social' ) ),
				'pages' => array( 'pages', 'Int!', __( 'All pages.', 'flx-social' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSocialStudioItem',
	array(
		'description' => __( 'A finished item of Flux Studio.', 'flx-social' ),
		'fields'      => flxsm_gql_props(
			FLXSM_Studio_Item::class,
			array(
				'databaseId'   => array( 'id', 'Int!', __( 'Item ID.', 'flx-social' ) ),
				'kind'         => array( 'kind', 'String!', __( 'image or video.', 'flx-social' ) ),
				'url'          => array( 'url', 'String!', __( 'File address.', 'flx-social' ) ),
				'thumb'        => array( 'thumb', 'String!', __( 'Small picture address.', 'flx-social' ) ),
				'title'        => array( 'title', 'String!', __( 'Title.', 'flx-social' ) ),
				'prompt'       => array( 'prompt', 'String!', __( 'The request it was made from.', 'flx-social' ) ),
				'attachmentId' => array( 'attachment_id', 'Int!', __( 'Media library ID (0: not imported yet).', 'flx-social' ) ),
				'width'        => array( 'width', 'Int!', __( 'Width in pixels.', 'flx-social' ) ),
				'height'       => array( 'height', 'Int!', __( 'Height in pixels.', 'flx-social' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSocialAiText',
	array(
		'description' => __( 'The text written for one network.', 'flx-social' ),
		'fields'      => array(
			'provider' => array(
				'type'        => 'String!',
				'description' => __( 'Network key.', 'flx-social' ),
				'resolve'     => static fn( $t ): string => is_array( $t ) ? FLXSM_Cast::str( $t['provider'] ?? '' ) : '',
			),
			'text'     => array(
				'type'        => 'String!',
				'description' => __( 'Text.', 'flx-social' ),
				'resolve'     => static fn( $t ): string => is_array( $t ) ? FLXSM_Cast::str( $t['text'] ?? '' ) : '',
			),
		),
	)
);

/* ---------------- Inputs ---------------- */

flx_gql_input(
	'FlxSocialTargetInput',
	array(
		'description' => __( 'A post on one account: own text, media and options.', 'flx-social' ),
		'fields'      => array(
			'accountId' => array(
				'type'        => 'Int!',
				'description' => __( 'Account ID.', 'flx-social' ),
			),
			'content'   => array(
				'type'        => 'String',
				'description' => __( 'Own text; empty uses the shared text.', 'flx-social' ),
			),
			'media'     => array(
				'type'        => '[Int!]',
				'description' => __( 'Own media (attachment IDs); empty uses the shared media.', 'flx-social' ),
			),
			'options'   => array(
				'type'        => 'FlxSocialOptionsInput',
				'description' => __( 'Network options.', 'flx-social' ),
			),
		),
	)
);

flx_gql_input(
	'FlxSocialWhatsAppTargetInput',
	array(
		'description' => __( 'A WhatsApp group or Status to add as an account.', 'flx-social' ),
		'fields'      => array(
			'extId'  => array(
				'type'        => 'String!',
				'description' => __( 'WhatsApp ID.', 'flx-social' ),
			),
			'name'   => array(
				'type'        => 'String',
				'description' => __( 'Name.', 'flx-social' ),
			),
			'kind'   => array(
				'type'        => 'String',
				'description' => __( 'status or group.', 'flx-social' ),
			),
			'avatar' => array(
				'type'        => 'String',
				'description' => __( 'Picture address.', 'flx-social' ),
			),
		),
	)
);

flx_gql_input(
	'FlxSocialAutoInput',
	array(
		'description' => __( 'Automatic sharing of new site content.', 'flx-social' ),
		'fields'      => array(
			'enabled'   => array(
				'type'        => 'Boolean',
				'description' => __( 'On.', 'flx-social' ),
			),
			'postTypes' => array(
				'type'        => '[String!]',
				'description' => __( 'Content types.', 'flx-social' ),
			),
			'accounts'  => array(
				'type'        => '[Int!]',
				'description' => __( 'Account IDs.', 'flx-social' ),
			),
			'template'  => array(
				'type'        => 'String',
				'description' => __( 'Text template.', 'flx-social' ),
			),
			'mode'      => array(
				'type'        => 'String',
				'description' => __( 'draft or schedule.', 'flx-social' ),
			),
			'delay'     => array(
				'type'        => 'Int',
				'description' => __( 'Minutes to wait.', 'flx-social' ),
			),
			'image'     => array(
				'type'        => 'Boolean',
				'description' => __( 'Add the featured image.', 'flx-social' ),
			),
		),
	)
);

flx_gql_input(
	'FlxSocialAppValueInput',
	array(
		'description' => __( 'One field of a network app. An empty secret keeps the old one; __clear__ removes it.', 'flx-social' ),
		'fields'      => array(
			'key'   => array(
				'type'        => 'String!',
				'description' => __( 'Field key.', 'flx-social' ),
			),
			'value' => array(
				'type'        => 'String!',
				'description' => __( 'Value.', 'flx-social' ),
			),
		),
	)
);

/* ---------------- Queries ---------------- */

flx_gql_query(
	'flxSocialConfig',
	array(
		'type'        => 'FlxSocialConfig!',
		'description' => __( 'What the Social Media screen needs to start.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_post(),
		'resolve'     => static function (): FLXSM_Config {
			flx_gql_cache_tag( 'flx-social:settings' );
			return FLXSM_Admin_Service::config();
		},
	)
);

flx_gql_query(
	'flxSocialAccounts',
	array(
		'type'        => '[FlxSocialAccount!]!',
		'description' => __( 'Connected accounts in the panel order.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_post(),
		'resolve'     => static function (): array {
			flx_gql_cache_tag( 'flx-social:accounts' );
			return FLXSM_Accounts_Service::accounts();
		},
	)
);

flx_gql_query(
	'flxSocialCounts',
	array(
		'type'        => 'FlxSocialCounts!',
		'description' => __( 'Numbers for the sidebar; "due" tells whether the scheduler has work. Light enough to poll.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_post(),
		'resolve'     => static fn(): FLXSM_Counts => FLXSM_Admin_Service::counts(),
	)
);

flx_gql_query(
	'flxSocialPost',
	array(
		'type'        => 'FlxSocialPost',
		'description' => __( 'One post; empty when it does not exist.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_post(),
		'args'        => array(
			'id' => array(
				'type'        => 'ID!',
				'description' => __( 'Global ID or post ID.', 'flx-social' ),
			),
		),
		'resolve'     => static function ( $root, array $args ): ?FLXSM_Post {
			flx_gql_cache_tag( 'flx-social:posts' );
			$p = FLXSM_Posts_Service::get( flx_gql_decode_id( $args['id'] ?? '', 'FlxSocialPost' ) );
			return $p instanceof WP_Error ? null : $p;
		},
	)
);

flx_gql_query(
	'flxSocialPosts',
	array(
		'type'        => 'FlxSocialPostConnection!',
		'description' => __( 'Posts, newest first.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_post(),
		'args'        => flx_gql_connection_args(
			array(
				'status'    => array(
					'type'        => '[FlxSocialPostStatus!]',
					'description' => __( 'Only these states.', 'flx-social' ),
				),
				'accountId' => array(
					'type'        => 'Int',
					'description' => __( 'Only posts that go to this account.', 'flx-social' ),
				),
				'provider'  => array(
					'type'        => 'String',
					'description' => __( 'Only posts that go to this network.', 'flx-social' ),
				),
				'search'    => array(
					'type'        => 'String',
					'description' => __( 'Words in the title, text or tags.', 'flx-social' ),
				),
				'tag'       => array(
					'type'        => 'String',
					'description' => __( 'Only this label.', 'flx-social' ),
				),
				'mine'      => array(
					'type'        => 'Boolean',
					'description' => __( 'Only posts the current user prepared.', 'flx-social' ),
				),
				'from'      => array(
					'type'        => 'Int',
					'description' => __( 'Not before this time (Unix timestamp; publish, schedule or creation time).', 'flx-social' ),
				),
				'to'        => array(
					'type'        => 'Int',
					'description' => __( 'Before this time (Unix timestamp).', 'flx-social' ),
				),
				'ascending' => array(
					'type'        => 'Boolean',
					'description' => __( 'Oldest first.', 'flx-social' ),
				),
			)
		),
		'resolve'     => static function ( $root, array $args ): array {
			/** @var array<string,mixed> $args */
			$f = array(
				'status'   => FLXSM_Cast::strings( $args['status'] ?? array() ),
				'account'  => flx_gql_int( $args['accountId'] ?? 0 ),
				'provider' => flx_gql_str( $args['provider'] ?? '' ),
				'q'        => flx_gql_str( $args['search'] ?? '' ),
				'tag'      => flx_gql_str( $args['tag'] ?? '' ),
				'mine'     => ! empty( $args['mine'] ),
				'from'     => flx_gql_int( $args['from'] ?? 0 ),
				'to'       => flx_gql_int( $args['to'] ?? 0 ),
				'asc'      => ! empty( $args['ascending'] ),
			);
			flx_gql_cache_tag( 'flx-social:posts' );
			return flx_gql_paginate(
				$args,
				static function ( int $offset, int $limit ) use ( $f ): array {
					$page = FLXSM_Posts_Service::page( $f, $offset, $limit );
					return array(
						'items' => $page->items,
						'total' => $page->total,
					);
				}
			);
		},
	)
);

flx_gql_query(
	'flxSocialStats',
	array(
		'type'        => 'FlxSocialStats!',
		'description' => __( 'The dashboard of the last 7, 30 or 90 days.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_post(),
		'args'        => array(
			'days' => array(
				'type'        => 'Int',
				'description' => __( 'Period: 7, 30 (default) or 90.', 'flx-social' ),
			),
		),
		'resolve'     => static fn( $root, array $args ): FLXSM_Stats => FLXSM_Admin_Service::stats( flx_gql_int( $args['days'] ?? 30, 30 ) ),
	)
);

flx_gql_query(
	'flxSocialSettings',
	array(
		'type'        => 'FlxSocialSettings!',
		'description' => __( 'Settings of Social Media.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_manage(),
		'resolve'     => static function (): FLXSM_Settings {
			flx_gql_cache_tag( 'flx-social:settings' );
			return FLXSM_Admin_Service::settings();
		},
	)
);

flx_gql_query(
	'flxSocialApps',
	array(
		'type'        => '[FlxSocialApp!]!',
		'description' => __( 'Network developer apps and whether they are ready. Secrets are never returned.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_manage(),
		'resolve'     => static function (): array {
			flx_gql_cache_tag( 'flx-social:settings' );
			return FLXSM_Admin_Service::apps();
		},
	)
);

flx_gql_query(
	'flxSocialLog',
	array(
		'type'        => '[FlxSocialLogRow!]!',
		'description' => __( 'The latest 200 rows of the activity log.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_manage(),
		'resolve'     => static fn(): array => FLXSM_Admin_Service::log( 200 ),
	)
);

flx_gql_query(
	'flxSocialWhatsAppTargets',
	array(
		'type'        => '[FlxSocialWhatsAppTarget!]!',
		'description' => __( 'Groups and Status the WhatsApp connection can post to.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_manage(),
		'resolve'     => static fn(): array => flxsm_gql_ok( FLXSM_Accounts_Service::whatsapp_targets() ),
	)
);

flx_gql_query(
	'flxSocialConnectUrl',
	array(
		'type'        => 'String!',
		'description' => __( 'Address that starts connecting a network; open it in the browser (the sign-in happens on the network).', 'flx-social' ),
		'cap'         => flxsm_gql_cap_manage(),
		'args'        => array(
			'for' => array(
				'type'        => 'String!',
				'description' => __( 'Key from FlxSocialAppConnect.for.', 'flx-social' ),
			),
		),
		'resolve'     => static fn( $root, array $args ): string => flxsm_gql_ok( FLXSM_Accounts_Service::connect_url( flx_gql_str( $args['for'] ?? '' ) ) ),
	)
);

flx_gql_query(
	'flxSocialMedia',
	array(
		'type'        => 'FlxSocialMediaPage!',
		'description' => __( 'Images and videos of the media library (40 per page), or the ones with the given IDs.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_post(),
		'args'        => array(
			'ids'    => array(
				'type'        => '[Int!]',
				'description' => __( 'Only these attachments.', 'flx-social' ),
			),
			'search' => array(
				'type'        => 'String',
				'description' => __( 'Search words.', 'flx-social' ),
			),
			'page'   => array(
				'type'        => 'Int',
				'description' => __( 'Page (from 1).', 'flx-social' ),
			),
		),
		'resolve'     => static function ( $root, array $args ): FLXSM_Media_Page {
			$ids = FLXSM_Cast::ints( $args['ids'] ?? array() );
			if ( $ids ) {
				return new FLXSM_Media_Page( FLXSM_Admin_Service::media_by_ids( array_slice( $ids, 0, 50 ) ), 1 );
			}
			return FLXSM_Admin_Service::media_page( flx_gql_str( $args['search'] ?? '' ), flx_gql_int( $args['page'] ?? 1, 1 ) );
		},
	)
);

flx_gql_query(
	'flxSocialStudioMedia',
	array(
		'type'        => '[FlxSocialStudioItem!]!',
		'description' => __( 'Finished items of Flux Studio (empty without Flux Studio).', 'flx-social' ),
		'cap'         => flxsm_gql_cap_post(),
		'resolve'     => static fn(): array => FLXSM_Admin_Service::studio_media(),
	)
);

/* ---------------- Mutations ---------------- */

$flxsm_gql_post_fields = array(
	'title'       => array(
		'type'        => 'String',
		'description' => __( 'Internal title.', 'flx-social' ),
	),
	'content'     => array(
		'type'        => 'String',
		'description' => __( 'Shared text.', 'flx-social' ),
	),
	'media'       => array(
		'type'        => '[Int!]',
		'description' => __( 'Shared media (attachment IDs).', 'flx-social' ),
	),
	'tags'        => array(
		'type'        => '[String!]',
		'description' => __( 'Labels.', 'flx-social' ),
	),
	'scheduledAt' => array(
		'type'        => 'Int',
		'description' => __( 'When to share (Unix timestamp); 0 clears it.', 'flx-social' ),
	),
	'repeatDays'  => array(
		'type'        => 'Int',
		'description' => __( 'Share again every N days (0: once).', 'flx-social' ),
	),
	'note'        => array(
		'type'        => 'String',
		'description' => __( 'Note.', 'flx-social' ),
	),
	'targets'     => array(
		'type'        => '[FlxSocialTargetInput!]',
		'description' => __( 'Accounts the post goes to (replaces the current ones).', 'flx-social' ),
	),
	'action'      => array(
		'type'        => 'FlxSocialPostAction',
		'description' => __( 'State change to apply after saving: DRAFT, SUBMIT, SCHEDULE or NOW.', 'flx-social' ),
	),
	'source'      => array(
		'type'        => 'String',
		'description' => __( 'manual, ai or app (when creating).', 'flx-social' ),
	),
);

/**
 * Post input → the array the service takes.
 *
 * @param array<string,mixed> $input Mutation input.
 * @return array<string,mixed>
 */
function flxsm_gql_post_input( array $input ): array {
	$out = array();
	foreach ( array( 'title', 'content', 'note', 'source', 'action' ) as $k ) {
		if ( isset( $input[ $k ] ) ) {
			$out[ $k ] = flx_gql_str( $input[ $k ] );
		}
	}
	if ( isset( $input['media'] ) ) {
		$out['media'] = FLXSM_Cast::ints( $input['media'] );
	}
	if ( isset( $input['tags'] ) ) {
		$out['tags'] = FLXSM_Cast::strings( $input['tags'] );
	}
	if ( isset( $input['scheduledAt'] ) ) {
		$out['scheduled_at'] = max( 0, flx_gql_int( $input['scheduledAt'] ) );
	}
	if ( isset( $input['repeatDays'] ) ) {
		$out['repeat_days'] = max( 0, flx_gql_int( $input['repeatDays'] ) );
	}
	if ( isset( $input['targets'] ) ) {
		$targets = array();
		foreach ( FLXSM_Cast::rows( $input['targets'] ) as $t ) {
			$row = array(
				'account_id' => flx_gql_int( $t['accountId'] ?? 0 ),
				'options'    => FLXSM_Options::from_input( FLXSM_Cast::arr( $t['options'] ?? array() ) ),
			);
			if ( isset( $t['content'] ) ) {
				$row['content'] = flx_gql_str( $t['content'] );
			}
			if ( isset( $t['media'] ) ) {
				$row['media'] = FLXSM_Cast::ints( $t['media'] );
			}
			$targets[] = $row;
		}
		$out['targets'] = $targets;
	}
	if ( isset( $out['source'] ) && ! in_array( $out['source'], array( 'manual', 'ai', 'app' ), true ) ) {
		unset( $out['source'] );
	}
	return $out;
}

/**
 * Post payload value for a mutation result.
 *
 * @param int|WP_Error $id Post ID or error.
 * @return array<string,mixed>
 */
function flxsm_gql_post_payload( $id ): array {
	$id = flxsm_gql_ok( $id );
	flxsm_gql_purge( array( 'posts' ) );
	return array( 'post' => flxsm_gql_ok( FLXSM_Posts_Service::get( $id ) ) );
}

flx_gql_mutation(
	'flxSocialPostCreate',
	array(
		'description' => __( 'Creates a post.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_post(),
		'input'       => $flxsm_gql_post_fields,
		'output'      => array(
			'post' => array(
				'type'        => 'FlxSocialPost',
				'description' => __( 'The new post.', 'flx-social' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			return flxsm_gql_post_payload( FLXSM_Posts_Service::save( flxsm_gql_post_input( $input ) ) );
		},
	)
);

flx_gql_mutation(
	'flxSocialPostUpdate',
	array(
		'description' => __( 'Changes a post. Fields left out stay as they are; accounts that already shared are not touched.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_post(),
		'input'       => array(
			'id' => array(
				'type'        => 'ID!',
				'description' => __( 'Global ID or post ID.', 'flx-social' ),
			),
		) + $flxsm_gql_post_fields,
		'output'      => array(
			'post' => array(
				'type'        => 'FlxSocialPost',
				'description' => __( 'The post after the change.', 'flx-social' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			$id = flx_gql_decode_id( $input['id'] ?? '', 'FlxSocialPost' );
			return flxsm_gql_post_payload( FLXSM_Posts_Service::save( flxsm_gql_post_input( $input ), $id ) );
		},
	)
);

flx_gql_mutation(
	'flxSocialPostAct',
	array(
		'description' => __( 'Moves a post to another state: approve, send back, call off …', 'flx-social' ),
		'cap'         => flxsm_gql_cap_post(),
		'input'       => array(
			'id'     => array(
				'type'        => 'ID!',
				'description' => __( 'Global ID or post ID.', 'flx-social' ),
			),
			'action' => array(
				'type'        => 'FlxSocialPostAction!',
				'description' => __( 'The state change.', 'flx-social' ),
			),
			'note'   => array(
				'type'        => 'String',
				'description' => __( 'Reason (when sending back).', 'flx-social' ),
			),
		),
		'output'      => array(
			'post' => array(
				'type'        => 'FlxSocialPost',
				'description' => __( 'The post after the change.', 'flx-social' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			$id = flx_gql_decode_id( $input['id'] ?? '', 'FlxSocialPost' );
			return flxsm_gql_post_payload( FLXSM_Posts_Service::act( $id, flx_gql_str( $input['action'] ?? '' ), flx_gql_str( $input['note'] ?? '' ) ) );
		},
	)
);

flx_gql_mutation(
	'flxSocialPostDelete',
	array(
		'description' => __( 'Deletes a post from the panel; with "remote" also on the networks that support it.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_post(),
		'input'       => array(
			'id'     => array(
				'type'        => 'ID!',
				'description' => __( 'Global ID or post ID.', 'flx-social' ),
			),
			'remote' => array(
				'type'        => 'Boolean',
				'description' => __( 'Also delete on the networks.', 'flx-social' ),
			),
		),
		'output'      => array(
			'deletedId' => array(
				'type'        => 'ID',
				'description' => __( 'Global ID of the deleted post.', 'flx-social' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			$id = flx_gql_decode_id( $input['id'] ?? '', 'FlxSocialPost' );
			flxsm_gql_ok( FLXSM_Posts_Service::delete( $id, ! empty( $input['remote'] ) ) );
			flxsm_gql_purge( array( 'posts' ) );
			return array( 'deletedId' => flx_gql_global_id( 'FlxSocialPost', $id ) );
		},
	)
);

flx_gql_mutation(
	'flxSocialPostBulkAct',
	array(
		'description' => __( 'One action on many posts; posts the user may not change are skipped.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_post(),
		'input'       => array(
			'ids'    => array(
				'type'        => '[ID!]!',
				'description' => __( 'Posts.', 'flx-social' ),
			),
			'action' => array(
				'type'        => 'FlxSocialBulkAction!',
				'description' => __( 'The action.', 'flx-social' ),
			),
		),
		'output'      => array(
			'done'   => array(
				'type'        => 'Int!',
				'description' => __( 'How many posts changed.', 'flx-social' ),
			),
			'issues' => array(
				'type'        => '[String!]!',
				'description' => __( 'What went wrong, per post.', 'flx-social' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			$ids = array();
			foreach ( array_slice( FLXSM_Cast::strings( $input['ids'] ?? array() ), 0, 100 ) as $raw ) {
				$ids[] = flx_gql_decode_id( $raw, 'FlxSocialPost' );
			}
			$res = FLXSM_Posts_Service::bulk( $ids, flx_gql_str( $input['action'] ?? '' ) );
			flxsm_gql_purge( array( 'posts' ) );
			return array(
				'done'   => $res->done,
				'issues' => $res->errors,
			);
		},
	)
);

flx_gql_mutation(
	'flxSocialPostDuplicate',
	array(
		'description' => __( 'Copies a post as a new draft.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_post(),
		'input'       => array(
			'id' => array(
				'type'        => 'ID!',
				'description' => __( 'Global ID or post ID.', 'flx-social' ),
			),
		),
		'output'      => array(
			'post' => array(
				'type'        => 'FlxSocialPost',
				'description' => __( 'The copy.', 'flx-social' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			return flxsm_gql_post_payload( FLXSM_Posts_Service::duplicate( flx_gql_decode_id( $input['id'] ?? '', 'FlxSocialPost' ) ) );
		},
	)
);

flx_gql_mutation(
	'flxSocialTargetRetry',
	array(
		'description' => __( 'Tries a failed account again.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_post(),
		'input'       => array(
			'id' => array(
				'type'        => 'ID!',
				'description' => __( 'Global ID or target ID.', 'flx-social' ),
			),
		),
		'output'      => array(
			'post' => array(
				'type'        => 'FlxSocialPost',
				'description' => __( 'The post.', 'flx-social' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			return flxsm_gql_post_payload( FLXSM_Posts_Service::retry_target( flx_gql_decode_id( $input['id'] ?? '', 'FlxSocialTarget' ) ) );
		},
	)
);

flx_gql_mutation(
	'flxSocialTargetRemoteEdit',
	array(
		'description' => __( 'Changes the text of a shared post on the network.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_post(),
		'input'       => array(
			'id'      => array(
				'type'        => 'ID!',
				'description' => __( 'Global ID or target ID.', 'flx-social' ),
			),
			'content' => array(
				'type'        => 'String',
				'description' => __( 'New text; empty keeps it.', 'flx-social' ),
			),
			'options' => array(
				'type'        => 'FlxSocialOptionsInput',
				'description' => __( 'Network options to change.', 'flx-social' ),
			),
		),
		'output'      => array(
			'post' => array(
				'type'        => 'FlxSocialPost',
				'description' => __( 'The post.', 'flx-social' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			$id = flx_gql_decode_id( $input['id'] ?? '', 'FlxSocialTarget' );
			return flxsm_gql_post_payload(
				FLXSM_Posts_Service::remote_edit(
					$id,
					isset( $input['content'] ) ? flx_gql_str( $input['content'] ) : null,
					FLXSM_Options::from_input( FLXSM_Cast::arr( $input['options'] ?? array() ) )
				)
			);
		},
	)
);

flx_gql_mutation(
	'flxSocialTargetRemoteDelete',
	array(
		'description' => __( 'Deletes a shared post on the network.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_post(),
		'input'       => array(
			'id' => array(
				'type'        => 'ID!',
				'description' => __( 'Global ID or target ID.', 'flx-social' ),
			),
		),
		'output'      => array(
			'post' => array(
				'type'        => 'FlxSocialPost',
				'description' => __( 'The post.', 'flx-social' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			return flxsm_gql_post_payload( FLXSM_Posts_Service::remote_delete( flx_gql_decode_id( $input['id'] ?? '', 'FlxSocialTarget' ) ) );
		},
	)
);

flx_gql_mutation(
	'flxSocialAccountUpdate',
	array(
		'description' => __( 'Renames, switches off or on, or moves an account.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_manage(),
		'input'       => array(
			'id'       => array(
				'type'        => 'ID!',
				'description' => __( 'Global ID or account ID.', 'flx-social' ),
			),
			'name'     => array(
				'type'        => 'String',
				'description' => __( 'Display name.', 'flx-social' ),
			),
			'disabled' => array(
				'type'        => 'Boolean',
				'description' => __( 'Switch off (true) or on (false).', 'flx-social' ),
			),
			'sort'     => array(
				'type'        => 'Int',
				'description' => __( 'Position.', 'flx-social' ),
			),
		),
		'output'      => array(
			'account' => array(
				'type'        => 'FlxSocialAccount',
				'description' => __( 'The account after the change.', 'flx-social' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			$acc = flxsm_gql_ok(
				FLXSM_Accounts_Service::update(
					flx_gql_decode_id( $input['id'] ?? '', 'FlxSocialAccount' ),
					isset( $input['disabled'] ) ? (bool) $input['disabled'] : null,
					isset( $input['name'] ) ? flx_gql_str( $input['name'] ) : null,
					isset( $input['sort'] ) ? flx_gql_int( $input['sort'] ) : null
				)
			);
			flxsm_gql_purge( array( 'accounts' ) );
			return array( 'account' => $acc );
		},
	)
);

flx_gql_mutation(
	'flxSocialAccountReorder',
	array(
		'description' => __( 'Saves the order of the accounts.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_manage(),
		'input'       => array(
			'ids' => array(
				'type'        => '[ID!]!',
				'description' => __( 'Accounts in the new order.', 'flx-social' ),
			),
		),
		'output'      => array(
			'accounts' => array(
				'type'        => '[FlxSocialAccount!]!',
				'description' => __( 'Accounts in the new order.', 'flx-social' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			$ids = array();
			foreach ( FLXSM_Cast::strings( $input['ids'] ?? array() ) as $raw ) {
				$ids[] = flx_gql_decode_id( $raw, 'FlxSocialAccount' );
			}
			$accounts = FLXSM_Accounts_Service::reorder( $ids );
			flxsm_gql_purge( array( 'accounts' ) );
			return array( 'accounts' => $accounts );
		},
	)
);

flx_gql_mutation(
	'flxSocialAccountDelete',
	array(
		'description' => __( 'Removes an account. Its open posts are called off.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_manage(),
		'input'       => array(
			'id' => array(
				'type'        => 'ID!',
				'description' => __( 'Global ID or account ID.', 'flx-social' ),
			),
		),
		'output'      => array(
			'deletedId' => array(
				'type'        => 'ID',
				'description' => __( 'Global ID of the removed account.', 'flx-social' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			$id = flx_gql_decode_id( $input['id'] ?? '', 'FlxSocialAccount' );
			flxsm_gql_ok( FLXSM_Accounts_Service::delete( $id ) );
			flxsm_gql_purge( array( 'accounts', 'posts' ) );
			return array( 'deletedId' => flx_gql_global_id( 'FlxSocialAccount', $id ) );
		},
	)
);

flx_gql_mutation(
	'flxSocialAccountRefresh',
	array(
		'description' => __( 'Renews the connection and reads the account numbers again.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_manage(),
		'input'       => array(
			'id' => array(
				'type'        => 'ID!',
				'description' => __( 'Global ID or account ID.', 'flx-social' ),
			),
		),
		'output'      => array(
			'account' => array(
				'type'        => 'FlxSocialAccount',
				'description' => __( 'The account.', 'flx-social' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			$acc = flxsm_gql_ok( FLXSM_Accounts_Service::refresh( flx_gql_decode_id( $input['id'] ?? '', 'FlxSocialAccount' ) ) );
			flxsm_gql_purge( array( 'accounts' ) );
			return array( 'account' => $acc );
		},
	)
);

flx_gql_mutation(
	'flxSocialWhatsAppAdd',
	array(
		'description' => __( 'Adds WhatsApp groups or Status as accounts.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_manage(),
		'input'       => array(
			'items' => array(
				'type'        => '[FlxSocialWhatsAppTargetInput!]!',
				'description' => __( 'Groups or Status to add.', 'flx-social' ),
			),
		),
		'output'      => array(
			'added'    => array(
				'type'        => 'Int!',
				'description' => __( 'How many were added.', 'flx-social' ),
			),
			'accounts' => array(
				'type'        => '[FlxSocialAccount!]!',
				'description' => __( 'Accounts after the change.', 'flx-social' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			$items = array();
			foreach ( FLXSM_Cast::rows( $input['items'] ?? array() ) as $it ) {
				$items[] = array(
					'ext_id' => flx_gql_str( $it['extId'] ?? '' ),
					'name'   => flx_gql_str( $it['name'] ?? '' ),
					'kind'   => flx_gql_str( $it['kind'] ?? '' ),
					'avatar' => flx_gql_str( $it['avatar'] ?? '' ),
				);
			}
			$n = flxsm_gql_ok( FLXSM_Accounts_Service::whatsapp_add( array_slice( $items, 0, 100 ) ) );
			flxsm_gql_purge( array( 'accounts' ) );
			return array(
				'added'    => $n,
				'accounts' => FLXSM_Accounts_Service::accounts(),
			);
		},
	)
);

flx_gql_mutation(
	'flxSocialSettingsUpdate',
	array(
		'description' => __( 'Changes settings. Fields left out stay as they are.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_manage(),
		'input'       => array(
			'approval'    => array(
				'type'        => 'Boolean',
				'description' => __( 'Posts of people who cannot publish wait for approval.', 'flx-social' ),
			),
			'signature'   => array(
				'type'        => 'String',
				'description' => __( 'Text added to the end of posts.', 'flx-social' ),
			),
			'utm'         => array(
				'type'        => 'Boolean',
				'description' => __( 'Add UTM tags to links of this site.', 'flx-social' ),
			),
			'metaVersion' => array(
				'type'        => 'String',
				'description' => __( 'Facebook and Instagram API version (v25.0).', 'flx-social' ),
			),
			'xTier'       => array(
				'type'        => 'String',
				'description' => __( 'X plan key.', 'flx-social' ),
			),
			'xPremium'    => array(
				'type'        => 'Boolean',
				'description' => __( 'Accounts have the paid tier on X.', 'flx-social' ),
			),
			'xMonthLimit' => array(
				'type'        => 'Int',
				'description' => __( 'Posts per month on X (0: no limit).', 'flx-social' ),
			),
			'tiktokMode'  => array(
				'type'        => 'String',
				'description' => __( 'inbox or direct.', 'flx-social' ),
			),
			'pinSandbox'  => array(
				'type'        => 'Boolean',
				'description' => __( 'Pinterest sandbox.', 'flx-social' ),
			),
			'liOrg'       => array(
				'type'        => 'Boolean',
				'description' => __( 'List LinkedIn pages.', 'flx-social' ),
			),
			'waConn'      => array(
				'type'        => 'Int',
				'description' => __( 'WhatsApp connection ID.', 'flx-social' ),
			),
			'auto'        => array(
				'type'        => 'FlxSocialAutoInput',
				'description' => __( 'Automatic sharing.', 'flx-social' ),
			),
		),
		'output'      => array(
			'settings' => array(
				'type'        => 'FlxSocialSettings',
				'description' => __( 'Settings after the change.', 'flx-social' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			$in  = array();
			$map = array(
				'approval'    => 'approval',
				'signature'   => 'signature',
				'utm'         => 'utm',
				'metaVersion' => 'meta_version',
				'xTier'       => 'x_tier',
				'xPremium'    => 'x_premium',
				'xMonthLimit' => 'x_month_limit',
				'tiktokMode'  => 'tiktok_mode',
				'pinSandbox'  => 'pin_sandbox',
				'liOrg'       => 'li_org',
				'waConn'      => 'wa_conn',
			);
			foreach ( $map as $gql => $key ) {
				if ( isset( $input[ $gql ] ) ) {
					$in[ $key ] = $input[ $gql ];
				}
			}
			if ( isset( $input['auto'] ) && is_array( $input['auto'] ) ) {
				$a          = $input['auto'];
				$cur        = FLXSM_Admin_Service::settings()->auto;
				$in['auto'] = array(
					'enabled'    => $a['enabled'] ?? $cur->enabled,
					'post_types' => $a['postTypes'] ?? $cur->post_types,
					'accounts'   => $a['accounts'] ?? $cur->accounts,
					'template'   => $a['template'] ?? $cur->template,
					'mode'       => $a['mode'] ?? $cur->mode,
					'delay'      => $a['delay'] ?? $cur->delay,
					'image'      => $a['image'] ?? $cur->image,
				);
			}
			FLXSM_Admin_Service::save_settings( $in );
			flxsm_gql_purge( array( 'settings' ) );
			return array( 'settings' => FLXSM_Admin_Service::settings() );
		},
	)
);

flx_gql_mutation(
	'flxSocialTickKeyReveal',
	array(
		'description' => __( 'Returns the full address an external cron calls (with the key). Managers only.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_manage(),
		'input'       => array(
			'regenerate' => array(
				'type'        => 'Boolean',
				'description' => __( 'Make a new key first (the old address stops working).', 'flx-social' ),
			),
		),
		'output'      => array(
			'url' => array(
				'type'        => 'String',
				'description' => __( 'The address with its key.', 'flx-social' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			if ( ! empty( $input['regenerate'] ) ) {
				FLXSM_Admin_Service::regenerate_tick_key();
			}
			return array( 'url' => FLXSM_Admin_Service::tick_url( false ) );
		},
	)
);

flx_gql_mutation(
	'flxSocialAppUpdate',
	array(
		'description' => __( 'Saves the fields of a network app. An empty secret keeps the old one; the value __clear__ removes it.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_manage(),
		'input'       => array(
			'id'     => array(
				'type'        => 'String!',
				'description' => __( 'App key (meta, google, x …).', 'flx-social' ),
			),
			'values' => array(
				'type'        => '[FlxSocialAppValueInput!]!',
				'description' => __( 'Fields to change.', 'flx-social' ),
			),
		),
		'output'      => array(
			'apps' => array(
				'type'        => '[FlxSocialApp!]!',
				'description' => __( 'Apps after the change.', 'flx-social' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			$values = array();
			foreach ( FLXSM_Cast::rows( $input['values'] ?? array() ) as $v ) {
				$values[ flx_gql_str( $v['key'] ?? '' ) ] = flx_gql_str( $v['value'] ?? '' );
			}
			flxsm_gql_ok( FLXSM_Admin_Service::save_app( flx_gql_str( $input['id'] ?? '' ), $values ) );
			flxsm_gql_purge( array( 'settings' ) );
			return array( 'apps' => FLXSM_Admin_Service::apps() );
		},
	)
);

flx_gql_mutation(
	'flxSocialStatsRefresh',
	array(
		'description' => __( 'Reads the numbers of the networks now.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_manage(),
		'input'       => array(),
		'output'      => array(
			'updated' => array(
				'type'        => 'Int!',
				'description' => __( 'How many records were updated.', 'flx-social' ),
			),
			'stats'   => array(
				'type'        => 'FlxSocialStats',
				'description' => __( 'The dashboard of the last 30 days.', 'flx-social' ),
			),
		),
		'resolve'     => static function (): array {
			$r = FLXSM_Admin_Service::refresh_stats();
			flxsm_gql_purge( array( 'accounts', 'posts' ) );
			return $r;
		},
	)
);

flx_gql_mutation(
	'flxSocialTickRun',
	array(
		'description' => __( 'Runs the scheduler for a moment. The open panel calls it while posts are due.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_post(),
		'input'       => array(),
		'output'      => array(
			'processed' => array(
				'type'        => 'Int!',
				'description' => __( 'Steps done.', 'flx-social' ),
			),
			'due'       => array(
				'type'        => 'Int!',
				'description' => __( 'Accounts still waiting.', 'flx-social' ),
			),
		),
		'resolve'     => static function (): array {
			$r = FLXSM_Admin_Service::tick();
			if ( $r->processed ) {
				flxsm_gql_purge( array( 'posts' ) );
			}
			return array(
				'processed' => $r->processed,
				'due'       => $r->due,
			);
		},
	)
);

flx_gql_mutation(
	'flxSocialAiWrite',
	array(
		'description' => __( 'Writing help through Flux AI. "texts" has one text per network for ADAPT; "text" holds the result of the other modes.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_post(),
		'input'       => array(
			'mode'      => array(
				'type'        => 'FlxSocialAiMode!',
				'description' => __( 'What to do.', 'flx-social' ),
			),
			'text'      => array(
				'type'        => 'String',
				'description' => __( 'Current text.', 'flx-social' ),
			),
			'brief'     => array(
				'type'        => 'String',
				'description' => __( 'Idea to write from.', 'flx-social' ),
			),
			'providers' => array(
				'type'        => '[String!]',
				'description' => __( 'Networks the text is for.', 'flx-social' ),
			),
			'tone'      => array(
				'type'        => 'String',
				'description' => __( 'Tone.', 'flx-social' ),
			),
			'limit'     => array(
				'type'        => 'Int',
				'description' => __( 'Character limit.', 'flx-social' ),
			),
			'to'        => array(
				'type'        => 'String',
				'description' => __( 'Language to translate to.', 'flx-social' ),
			),
		),
		'output'      => array(
			'text'  => array(
				'type'        => 'String',
				'description' => __( 'The written text.', 'flx-social' ),
			),
			'texts' => array(
				'type'        => '[FlxSocialAiText!]',
				'description' => __( 'A text per network (ADAPT).', 'flx-social' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			$res = flxsm_gql_ok(
				FLXSM_Posts_Service::ai(
					flx_gql_str( $input['mode'] ?? '' ),
					flx_gql_str( $input['text'] ?? '' ),
					flx_gql_str( $input['brief'] ?? '' ),
					FLXSM_Cast::strings( $input['providers'] ?? array() ),
					flx_gql_str( $input['tone'] ?? '' ),
					flx_gql_int( $input['limit'] ?? 0 ),
					flx_gql_str( $input['to'] ?? '' )
				)
			);
			if ( is_array( $res ) ) {
				$texts = array();
				foreach ( $res as $provider => $text ) {
					$texts[] = array(
						'provider' => (string) $provider,
						'text'     => $text,
					);
				}
				return array(
					'text'  => null,
					'texts' => $texts,
				);
			}
			return array(
				'text'  => $res,
				'texts' => null,
			);
		},
	)
);

flx_gql_mutation(
	'flxSocialMediaImport',
	array(
		'description' => __( 'Adds a file from an internet address to the media library.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_post(),
		'input'       => array(
			'url'    => array(
				'type'        => 'String!',
				'description' => __( 'File address.', 'flx-social' ),
			),
			'poster' => array(
				'type'        => 'Int',
				'description' => __( 'Cover image attachment (videos).', 'flx-social' ),
			),
		),
		'output'      => array(
			'media' => array(
				'type'        => 'FlxSocialMedia',
				'description' => __( 'The new media.', 'flx-social' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			return array( 'media' => flxsm_gql_ok( FLXSM_Admin_Service::media_import( flx_gql_str( $input['url'] ?? '' ), flx_gql_int( $input['poster'] ?? 0 ) ) ) );
		},
	)
);

flx_gql_mutation(
	'flxSocialMediaAdd',
	array(
		'description' => __( 'Adds a file sent to the upload endpoint (context flx-social/media) to the media library.', 'flx-social' ),
		'cap'         => flxsm_gql_cap_post(),
		'input'       => array(
			'uploadId' => array(
				'type'        => 'String!',
				'description' => __( 'Upload ID from the upload endpoint.', 'flx-social' ),
			),
			'poster'   => array(
				'type'        => 'Int',
				'description' => __( 'Cover image attachment (videos).', 'flx-social' ),
			),
		),
		'output'      => array(
			'media' => array(
				'type'        => 'FlxSocialMedia',
				'description' => __( 'The new media.', 'flx-social' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			/** @var array<string,mixed> $input */
			return array( 'media' => flxsm_gql_ok( FLXSM_Admin_Service::media_from_upload( flx_gql_str( $input['uploadId'] ?? '' ), flx_gql_int( $input['poster'] ?? 0 ) ) ) );
		},
	)
);
