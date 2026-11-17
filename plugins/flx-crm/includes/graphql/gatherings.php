<?php
/**
 * CRM › Events (gatherings) over GraphQL. "Event" is already the contact timeline, so this schema says gathering.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Unix time on a send → FlxDateTime.
 *
 * @param array<mixed,mixed> $send Send card.
 * @return array<string,mixed>
 */
function flxcrm_gql_send( array $send ): array {
	$send            = FLXCRM_Val::keyed( $send );
	$send['at']      = flxcrm_gql_ts( $send['at'] ?? 0 );
	$send['eta']     = flxcrm_gql_ts( $send['eta'] ?? 0 );
	$planned         = $send['sendAt'] ?? null;
	$send['sendAt']  = $planned instanceof DateTimeInterface ? flx_gql_datetime( $planned ) : null;
	$channels    = array();
	foreach ( FLXCRM_Val::arr( $send['channels'] ?? array() ) as $ch ) {
		if ( ! is_array( $ch ) ) {
			continue;
		}
		$ch['eta']  = flxcrm_gql_ts( $ch['eta'] ?? 0 );
		$channels[] = $ch;
	}
	$send['channels'] = $channels;
	return $send;
}

/**
 * @param array<string,mixed> $gathering Gathering.
 * @return array<string,mixed>
 */
function flxcrm_gql_gathering( array $gathering ): array {
	$sends = array();
	foreach ( FLXCRM_Val::arr( $gathering['recentSends'] ?? array() ) as $send ) {
		if ( is_array( $send ) ) {
			$sends[] = flxcrm_gql_send( $send );
		}
	}
	if ( array_key_exists( 'recentSends', $gathering ) ) {
		$gathering['recentSends'] = $sends;
	}
	$starts = $gathering['startsAt'] ?? null;
	if ( array_key_exists( 'startsAt', $gathering ) ) {
		$gathering['startsAt'] = $starts instanceof DateTimeInterface ? flx_gql_datetime( $starts ) : null;
	}
	return $gathering;
}

/**
 * @param mixed $rows Field values from the input.
 * @return array<string,string>
 */
function flxcrm_gql_field_values( $rows ): array {
	$out = array();
	foreach ( flx_gql_arr( $rows ) as $row ) {
		$row = flx_gql_arr( $row );
		$key = sanitize_key( flx_gql_str( $row['key'] ?? '' ) );
		if ( '' !== $key ) {
			$out[ $key ] = flx_gql_str( $row['value'] ?? '' );
		}
	}
	return $out;
}

flx_gql_enum(
	'FlxGatheringStatus',
	array(
		'description' => __( 'Open events are the working list. Closed events are the archive.', 'flx-crm' ),
		'values'      => array(
			'OPEN'   => array( 'value' => 'open', 'description' => __( 'On the working list.', 'flx-crm' ) ),
			'CLOSED' => array( 'value' => 'closed', 'description' => __( 'Archived. Its records stay.', 'flx-crm' ) ),
		),
	)
);
flx_gql_enum(
	'FlxGatheringChannel',
	array(
		'description' => __( 'A channel Flux Mail can send an event message on.', 'flx-crm' ),
		'values'      => array(
			'WHATSAPP' => array( 'value' => 'whatsapp', 'description' => __( 'WhatsApp.', 'flx-crm' ) ),
			'EMAIL'    => array( 'value' => 'email', 'description' => __( 'E-mail.', 'flx-crm' ) ),
			'SMS'      => array( 'value' => 'sms', 'description' => __( 'SMS.', 'flx-crm' ) ),
		),
	)
);
flx_gql_enum(
	'FlxGatheringKind',
	array(
		'description' => __( 'Whether an event message is information or marketing.', 'flx-crm' ),
		'values'      => array(
			'INFO'      => array( 'value' => 'info', 'description' => __( 'Information. No marketing consent.', 'flx-crm' ) ),
			'MARKETING' => array( 'value' => 'marketing', 'description' => __( 'Marketing. Consent is required.', 'flx-crm' ) ),
		),
	)
);
flx_gql_enum(
	'FlxGatheringControl',
	array(
		'description' => __( 'What to do with a send that is still going out.', 'flx-crm' ),
		'values'      => array(
			'PAUSE'  => array( 'value' => 'pause', 'description' => __( 'Pause.', 'flx-crm' ) ),
			'RESUME' => array( 'value' => 'resume', 'description' => __( 'Resume.', 'flx-crm' ) ),
			'CANCEL' => array( 'value' => 'cancel', 'description' => __( 'Cancel.', 'flx-crm' ) ),
		),
	)
);
flx_gql_enum(
	'FlxGatheringOverall',
	array(
		'description' => __( 'Where a send is, across every channel.', 'flx-crm' ),
		'values'      => array(
			'RUNNING'   => array( 'value' => 'running', 'description' => __( 'Sending.', 'flx-crm' ) ),
			'PAUSED'    => array( 'value' => 'paused', 'description' => __( 'Paused.', 'flx-crm' ) ),
			'PLANNED'   => array( 'value' => 'planned', 'description' => __( 'Planned.', 'flx-crm' ) ),
			'REPORTING' => array( 'value' => 'reporting', 'description' => __( 'Waiting for reports.', 'flx-crm' ) ),
			'REVIEW'    => array( 'value' => 'review', 'description' => __( 'Needs a check.', 'flx-crm' ) ),
			'DONE'      => array( 'value' => 'done', 'description' => __( 'Sent.', 'flx-crm' ) ),
			'CANCELLED' => array( 'value' => 'cancelled', 'description' => __( 'Cancelled.', 'flx-crm' ) ),
			'UNKNOWN'   => array( 'value' => 'unknown', 'description' => __( 'Not known.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxGatheringAction',
	array(
		'description' => __( 'A link a package adds to an event card.', 'flx-crm' ),
		'fields'      => array(
			'label'   => array( 'type' => 'String!', 'description' => __( 'Button label.', 'flx-crm' ) ),
			'url'     => array( 'type' => 'String!', 'description' => __( 'Address.', 'flx-crm' ) ),
			'target'  => array( 'type' => 'String!', 'description' => __( 'Link target. Empty stays in this tab.', 'flx-crm' ) ),
			'primary' => array( 'type' => 'Boolean!', 'description' => __( 'Shown as the main button.', 'flx-crm' ) ),
		),
	)
);
flx_gql_type(
	'FlxGatheringStat',
	array(
		'description' => __( 'A number a package shows on an event card.', 'flx-crm' ),
		'fields'      => array(
			'label'    => array( 'type' => 'String!', 'description' => __( 'What the number is.', 'flx-crm' ) ),
			'value'    => array( 'type' => 'String!', 'description' => __( 'The number, already formatted.', 'flx-crm' ) ),
			'hint'     => array( 'type' => 'String!', 'description' => __( 'Short extra line.', 'flx-crm' ) ),
			'progress' => array( 'type' => 'Int', 'description' => __( 'Bar from 0 to 100. Empty when there is no bar.', 'flx-crm' ) ),
		),
	)
);
flx_gql_type(
	'FlxGatheringReason',
	array(
		'description' => __( 'Why people are on an event, grouped.', 'flx-crm' ),
		'fields'      => array(
			'label'   => array( 'type' => 'String!', 'description' => __( 'Reason.', 'flx-crm' ) ),
			'count'   => array( 'type' => 'Int!', 'description' => __( 'People.', 'flx-crm' ) ),
			'percent' => array( 'type' => 'Int!', 'description' => __( 'Share of the largest group, 0–100.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxGatheringChannelProgress',
	array(
		'description' => __( 'How far one channel of a send has got.', 'flx-crm' ),
		'fields'      => array(
			'key'        => array( 'type' => 'String!', 'description' => __( 'Channel key.', 'flx-crm' ) ),
			'label'      => array( 'type' => 'String!', 'description' => __( 'Channel name.', 'flx-crm' ) ),
			'missing'    => array( 'type' => 'Boolean!', 'description' => __( 'Flux Mail has no campaign for this channel.', 'flx-crm' ) ),
			'sent'       => array( 'type' => 'Int!', 'description' => __( 'Sent.', 'flx-crm' ) ),
			'total'      => array( 'type' => 'Int!', 'description' => __( 'Recipients.', 'flx-crm' ) ),
			'failed'     => array( 'type' => 'Int!', 'description' => __( 'Failed.', 'flx-crm' ) ),
			'skipped'    => array( 'type' => 'Int!', 'description' => __( 'Skipped.', 'flx-crm' ) ),
			'percent'    => array( 'type' => 'Int!', 'description' => __( 'Done, 0–100.', 'flx-crm' ) ),
			'eta'        => array( 'type' => 'FlxDateTime', 'description' => __( 'Estimated finish. Empty when it is not running.', 'flx-crm' ) ),
			'campaignId' => array( 'type' => 'Int', 'description' => __( 'Flux Mail campaign. Empty when there is none.', 'flx-crm' ) ),
		),
	)
);
flx_gql_type(
	'FlxGatheringSend',
	array(
		'description' => __( 'One message queued for the people of an event.', 'flx-crm' ),
		'fields'      => array(
			'eventId'      => array( 'type' => 'Int!', 'description' => __( 'Event ID.', 'flx-crm' ) ),
			'key'          => array( 'type' => 'String!', 'description' => __( 'Send key.', 'flx-crm' ) ),
			'overall'      => array( 'type' => 'FlxGatheringOverall!', 'description' => __( 'Where the send is.', 'flx-crm' ) ),
			'overallLabel' => array( 'type' => 'String!', 'description' => __( 'Where the send is, in words.', 'flx-crm' ) ),
			'tone'         => array( 'type' => 'String!', 'description' => __( 'ok, info, warn or mute.', 'flx-crm' ) ),
			'kind'         => array( 'type' => 'FlxGatheringKind!', 'description' => __( 'Information or marketing.', 'flx-crm' ) ),
			'text'         => array( 'type' => 'String!', 'description' => __( 'Message text (stored up to 300 characters).', 'flx-crm' ) ),
			'at'           => array( 'type' => 'FlxDateTime', 'description' => __( 'When it was queued.', 'flx-crm' ) ),
			'sendAt'       => array( 'type' => 'FlxDateTime', 'description' => __( 'Planned time in the site timezone. Empty sends now.', 'flx-crm' ) ),
			'byName'       => array( 'type' => 'String!', 'description' => __( 'Who queued it.', 'flx-crm' ) ),
			'mediaId'      => array( 'type' => 'Int!', 'description' => __( 'Attached file. 0 when there is none.', 'flx-crm' ) ),
			'mediaUrl'     => array( 'type' => 'String!', 'description' => __( 'File address.', 'flx-crm' ) ),
			'mediaIsImage' => array( 'type' => 'Boolean!', 'description' => __( 'The file is a picture.', 'flx-crm' ) ),
			'thumbUrl'     => array( 'type' => 'String!', 'description' => __( 'Picture preview.', 'flx-crm' ) ),
			'channels'     => array( 'type' => '[FlxGatheringChannelProgress!]!', 'description' => __( 'Progress of each channel.', 'flx-crm' ) ),
			'eta'          => array( 'type' => 'FlxDateTime', 'description' => __( 'Latest estimated finish across channels.', 'flx-crm' ) ),
			'delivered'    => array( 'type' => 'Int', 'description' => __( 'Delivered. Empty when no channel reports it.', 'flx-crm' ) ),
			'read'         => array( 'type' => 'Int', 'description' => __( 'Read. Empty when no channel reports it.', 'flx-crm' ) ),
			'replied'      => array( 'type' => 'Int', 'description' => __( 'Replied. Empty when no channel reports it.', 'flx-crm' ) ),
			'optout'       => array( 'type' => 'Int', 'description' => __( 'Opted out. Empty when no channel reports it.', 'flx-crm' ) ),
			'failed'       => array( 'type' => 'Int!', 'description' => __( 'Failed.', 'flx-crm' ) ),
			'reachedTotal' => array( 'type' => 'Int!', 'description' => __( 'Recipients across channels.', 'flx-crm' ) ),
			'canPause'     => array( 'type' => 'Boolean!', 'description' => __( 'Flux Mail can pause a send.', 'flx-crm' ) ),
			'pending'      => array( 'type' => 'Boolean!', 'description' => __( 'The send can still be stopped.', 'flx-crm' ) ),
			'recipients'   => array(
				'type'        => 'FlxGatheringRecipientConnection!',
				'description' => __( 'Who this send went to, on one channel.', 'flx-crm' ),
				'args'        => flx_gql_connection_args(
					array(
						'channel' => array( 'type' => 'String', 'description' => __( 'Channel key. Empty uses the first.', 'flx-crm' ) ),
						'status'  => array( 'type' => 'String', 'description' => __( 'sent, waiting, failed or other.', 'flx-crm' ) ),
					)
				),
				'resolve'     => static function ( $src, array $args ): array {
					$src  = flx_gql_arr( $src );
					$id   = flx_gql_int( $src['eventId'] ?? 0 );
					$key  = flx_gql_str( $src['key'] ?? '' );
					$page = null;
					$conn = flx_gql_paginate(
						flx_gql_arr( $args ),
						static function ( int $offset, int $limit ) use ( $id, $key, $args, &$page ): array {
							$page = FLXCRM_Gathering_Service::recipients( $id, $key, flx_gql_str( $args['channel'] ?? '' ), flx_gql_str( $args['status'] ?? '' ), $offset, $limit );
							return is_array( $page ) ? array( 'items' => $page['items'], 'total' => $page['total'] ) : array( 'items' => array(), 'total' => 0 );
						}
					);
					$counts = is_array( $page ) ? $page['counts'] : array( 'sent' => 0, 'waiting' => 0, 'failed' => 0, 'other' => 0 );
					$conn['channel']      = is_array( $page ) ? $page['channel'] : '';
					$conn['channels']     = is_array( $page ) ? $page['channels'] : array();
					$conn['countSent']    = $counts['sent'];
					$conn['countWaiting'] = $counts['waiting'];
					$conn['countFailed']  = $counts['failed'];
					$conn['countOther']   = $counts['other'];
					return $conn;
				},
			),
		),
	)
);
flx_gql_type(
	'FlxGatheringRecipient',
	array(
		'description' => __( 'One recipient of an event message.', 'flx-crm' ),
		'fields'      => array(
			'name'          => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-crm' ) ),
			'recipient'     => array( 'type' => 'String!', 'description' => __( 'Address or phone.', 'flx-crm' ) ),
			'status'        => array( 'type' => 'String!', 'description' => __( 'Queue status.', 'flx-crm' ) ),
			'error'         => array( 'type' => 'String!', 'description' => __( 'Why it failed.', 'flx-crm' ) ),
			'sentAt'        => array( 'type' => 'FlxDateTime', 'description' => __( 'When it was sent (GMT storage).', 'flx-crm' ) ),
			'scheduledAt'   => array( 'type' => 'FlxDateTime', 'description' => __( 'When it is planned (GMT storage).', 'flx-crm' ) ),
			'delivered'     => array( 'type' => 'Boolean!', 'description' => __( 'Delivered.', 'flx-crm' ) ),
			'read'          => array( 'type' => 'Boolean!', 'description' => __( 'Read.', 'flx-crm' ) ),
			'replied'       => array( 'type' => 'Boolean!', 'description' => __( 'Replied.', 'flx-crm' ) ),
			'optout'        => array( 'type' => 'Boolean!', 'description' => __( 'Opted out.', 'flx-crm' ) ),
			'hasEngagement' => array( 'type' => 'Boolean!', 'description' => __( 'This row knows about reads or replies.', 'flx-crm' ) ),
		),
	)
);
flx_gql_connection( 'FlxGatheringRecipient', __( 'A page of recipients.', 'flx-crm' ) );
foreach (
	array(
		'channel'      => array( 'type' => 'String!', 'description' => __( 'Channel these recipients belong to.', 'flx-crm' ) ),
		'channels'     => array( 'type' => '[String!]!', 'description' => __( 'Channels this send used.', 'flx-crm' ) ),
		'countSent'    => array( 'type' => 'Int!', 'description' => __( 'Sent on this channel.', 'flx-crm' ) ),
		'countWaiting' => array( 'type' => 'Int!', 'description' => __( 'Still waiting on this channel.', 'flx-crm' ) ),
		'countFailed'  => array( 'type' => 'Int!', 'description' => __( 'Failed on this channel.', 'flx-crm' ) ),
		'countOther'   => array( 'type' => 'Int!', 'description' => __( 'Skipped or cancelled on this channel.', 'flx-crm' ) ),
	) as $flxcrm_rec_field => $flxcrm_rec_cfg
) {
	flx_gql_field( 'FlxGatheringRecipientConnection', $flxcrm_rec_field, $flxcrm_rec_cfg );
}

flx_gql_type(
	'FlxGathering',
	array(
		'description' => __( 'An event: a planting day, a meeting or a training.', 'flx-crm' ),
		'fields'      => array(
			'databaseId'    => array( 'type' => 'Int!', 'description' => __( 'Event ID.', 'flx-crm' ) ),
			'name'           => array( 'type' => 'String!', 'description' => __( 'Event name.', 'flx-crm' ) ),
			'status'         => array( 'type' => 'FlxGatheringStatus!', 'description' => __( 'Open or archived.', 'flx-crm' ) ),
			'whenLabel'      => array( 'type' => 'String!', 'description' => __( 'Date and time, for the card.', 'flx-crm' ) ),
			'place'          => array( 'type' => 'String!', 'description' => __( 'Place, or a short address.', 'flx-crm' ) ),
			'phaseTone'      => array( 'type' => 'String!', 'description' => __( 'ok, info, warn or mute.', 'flx-crm' ) ),
			'phaseLabel'     => array( 'type' => 'String!', 'description' => __( 'Today, in N days, archived, or no date.', 'flx-crm' ) ),
			'peopleCount'    => array( 'type' => 'Int!', 'description' => __( 'People the packages attach to this event.', 'flx-crm' ) ),
			'coverUrl'       => array( 'type' => 'String!', 'description' => __( 'Cover picture.', 'flx-crm' ) ),
			'dateDay'        => array( 'type' => 'String!', 'description' => __( 'Day of the month.', 'flx-crm' ) ),
			'dateMonth'      => array( 'type' => 'String!', 'description' => __( 'Month name.', 'flx-crm' ) ),
			'dateYear'       => array( 'type' => 'String!', 'description' => __( 'Year.', 'flx-crm' ) ),
			'stats'          => array( 'type' => '[FlxGatheringStat!]!', 'description' => __( 'Numbers from packages.', 'flx-crm' ) ),
			'progress'       => array( 'type' => 'Int', 'description' => __( 'First package bar, 0–100.', 'flx-crm' ) ),
			'progressHint'   => array( 'type' => 'String!', 'description' => __( 'Caption of that bar.', 'flx-crm' ) ),
			'actions'        => array( 'type' => '[FlxGatheringAction!]!', 'description' => __( 'Links from packages.', 'flx-crm' ) ),
			'mailReady'      => array( 'type' => 'Boolean!', 'description' => __( 'Flux Mail can send.', 'flx-crm' ) ),
			'postId'         => array( 'type' => 'Int!', 'description' => __( 'Page on the site. 0 when there is none.', 'flx-crm' ) ),
			'pageState'      => array( 'type' => 'String!', 'description' => __( 'published, draft or none.', 'flx-crm' ) ),
			'pageUrl'        => array( 'type' => 'String!', 'description' => __( 'Public page. Empty until it is published.', 'flx-crm' ) ),
			'pageTitle'      => array( 'type' => 'String!', 'description' => __( 'Page title.', 'flx-crm' ) ),
			'canCreatePage'  => array( 'type' => 'Boolean!', 'description' => __( 'The theme has an event post type.', 'flx-crm' ) ),
			'startsAt'       => array( 'type' => 'FlxDateTime', 'description' => __( 'Site-local start. Empty when the event has no date.', 'flx-crm' ) ),
			'summary'        => array( 'type' => 'String!', 'description' => __( 'Short description. Empty on a list card.', 'flx-crm' ) ),
			'note'           => array(
				'type'        => 'String',
				'description' => __( 'Internal note. Empty unless the viewer may edit events.', 'flx-crm' ),
				'resolve'     => static function ( $row ): ?string {
					if ( ! flxcrm_can( true ) || ! is_array( $row ) ) {
						return null;
					}
					$n = FLXCRM_Val::str( $row['note'] ?? '' );
					return '' === $n ? null : $n;
				},
			),
			'address'        => array( 'type' => 'String!', 'description' => __( 'Address. Empty on a list card.', 'flx-crm' ) ),
			'mapUrl'         => array( 'type' => 'String!', 'description' => __( 'Map link.', 'flx-crm' ) ),
			'deliveredCount' => array( 'type' => 'Int!', 'description' => __( 'Messages delivered for this event. 0 on a list card.', 'flx-crm' ) ),
			'sendCount'      => array( 'type' => 'Int!', 'description' => __( 'Sends. 0 on a list card.', 'flx-crm' ) ),
			'reasons'        => array( 'type' => '[FlxGatheringReason!]!', 'description' => __( 'Why people are included. Empty on a list card.', 'flx-crm' ) ),
			'recentSends'    => array( 'type' => '[FlxGatheringSend!]!', 'description' => __( 'Newest sends (up to 3). Empty on a list card.', 'flx-crm' ) ),
		),
	)
);
flx_gql_connection( 'FlxGathering', __( 'A page of events.', 'flx-crm' ) );

flx_gql_type(
	'FlxGatheringBoard',
	array(
		'description' => __( 'The Events screen: counts, the site tab, and one page of cards.', 'flx-crm' ),
		'fields'      => array(
			'openCount'   => array( 'type' => 'Int!', 'description' => __( 'Open events.', 'flx-crm' ) ),
			'closedCount' => array( 'type' => 'Int!', 'description' => __( 'Archived events.', 'flx-crm' ) ),
			'hasSite'     => array( 'type' => 'Boolean!', 'description' => __( 'A package prints an On the site tab.', 'flx-crm' ) ),
			'siteHtml'    => array( 'type' => 'String!', 'description' => __( 'That tab. Empty when no package hooks it.', 'flx-crm' ) ),
			'mailReady'   => array( 'type' => 'Boolean!', 'description' => __( 'Flux Mail can send.', 'flx-crm' ) ),
			'events'      => array(
				'type'        => 'FlxGatheringConnection!',
				'description' => __( 'Event cards. Search is not on this list; open an event for its people.', 'flx-crm' ),
				'args'        => flx_gql_connection_args(
					array(
						'status' => array( 'type' => 'FlxGatheringStatus', 'description' => __( 'Open or archived (default open).', 'flx-crm' ) ),
					)
				),
				'resolve'     => static function ( $src, array $args ): array {
					$status = flx_gql_str( $args['status'] ?? ( is_array( $src ) ? ( $src['status'] ?? 'open' ) : 'open' ) );
					flx_gql_cache_tag( FLXCRM_Gathering_Service::TAG_LIST );
					return flx_gql_paginate(
						flx_gql_arr( $args ),
						static function ( int $offset, int $limit ) use ( $status ): array {
							return array(
								'items' => array_map( 'flxcrm_gql_gathering', FLXCRM_Gathering_Service::cards( $status, $offset, $limit ) ),
								'total' => FLXCRM_Gathering_Service::count( $status ),
							);
						}
					);
				},
			),
		),
	)
);

flx_gql_input(
	'FlxGatheringFieldInput',
	array(
		'description' => __( 'One event field. Packages add their own keys.', 'flx-crm' ),
		'fields'      => array(
			'key'   => array( 'type' => 'String!', 'description' => __( 'Field key (name, event_date, …).', 'flx-crm' ) ),
			'value' => array( 'type' => 'String', 'description' => __( 'Posted value.', 'flx-crm' ) ),
		),
	)
);
flx_gql_type(
	'FlxGatheringOption',
	array(
		'description' => __( 'A choice of a form field.', 'flx-crm' ),
		'fields'      => array(
			'value' => array( 'type' => 'String!', 'description' => __( 'Stored value.', 'flx-crm' ) ),
			'label' => array( 'type' => 'String!', 'description' => __( 'Label.', 'flx-crm' ) ),
		),
	)
);
flx_gql_type(
	'FlxGatheringFormField',
	array(
		'description' => __( 'One field of the event form.', 'flx-crm' ),
		'fields'      => array(
			'key'         => array( 'type' => 'String!', 'description' => __( 'Field key.', 'flx-crm' ) ),
			'type'        => array( 'type' => 'String!', 'description' => __( 'text, textarea, date, time, number, url, image or post.', 'flx-crm' ) ),
			'label'       => array( 'type' => 'String!', 'description' => __( 'Label.', 'flx-crm' ) ),
			'placeholder' => array( 'type' => 'String!', 'description' => __( 'Placeholder.', 'flx-crm' ) ),
			'help'        => array( 'type' => 'String!', 'description' => __( 'Help under the field.', 'flx-crm' ) ),
			'required'    => array( 'type' => 'Boolean!', 'description' => __( 'Required.', 'flx-crm' ) ),
			'value'       => array( 'type' => 'String!', 'description' => __( 'Current value.', 'flx-crm' ) ),
			'options'     => array( 'type' => '[FlxGatheringOption!]!', 'description' => __( 'Choices (page field).', 'flx-crm' ) ),
		),
	)
);
flx_gql_type(
	'FlxGatheringFormSection',
	array(
		'description' => __( 'A group of fields on the event form.', 'flx-crm' ),
		'fields'      => array(
			'key'    => array( 'type' => 'String!', 'description' => __( 'Section key.', 'flx-crm' ) ),
			'title'  => array( 'type' => 'String!', 'description' => __( 'Heading.', 'flx-crm' ) ),
			'html'   => array( 'type' => 'String!', 'description' => __( 'Extra HTML a package prints in this section.', 'flx-crm' ) ),
			'fields' => array( 'type' => '[FlxGatheringFormField!]!', 'description' => __( 'Fields.', 'flx-crm' ) ),
		),
	)
);
flx_gql_type(
	'FlxGatheringForm',
	array(
		'description' => __( 'The event form, blank or filled.', 'flx-crm' ),
		'fields'      => array(
			'databaseId' => array( 'type' => 'Int!', 'description' => __( 'Event ID. 0 for a new event.', 'flx-crm' ) ),
			'name'        => array( 'type' => 'String!', 'description' => __( 'Event name.', 'flx-crm' ) ),
			'sections'    => array( 'type' => '[FlxGatheringFormSection!]!', 'description' => __( 'Field groups.', 'flx-crm' ) ),
			'afterHtml'   => array( 'type' => 'String!', 'description' => __( 'HTML a package prints after the fields.', 'flx-crm' ) ),
			'createPost'  => array( 'type' => 'Boolean!', 'description' => __( 'Offer to create the site page.', 'flx-crm' ) ),
			'postType'    => array( 'type' => 'Boolean!', 'description' => __( 'The theme has an event post type.', 'flx-crm' ) ),
		),
	)
);

flx_gql_type(
	'FlxGatheringPerson',
	array(
		'description' => __( 'A person attached to an event by a package.', 'flx-crm' ),
		'fields'      => array(
			'databaseId' => array( 'type' => 'Int!', 'description' => __( 'Person ID.', 'flx-crm' ) ),
			'name'        => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-crm' ) ),
			'email'       => array( 'type' => 'String!', 'description' => __( 'E-mail.', 'flx-crm' ) ),
			'phone'       => array( 'type' => 'String!', 'description' => __( 'Phone.', 'flx-crm' ) ),
			'why'         => array( 'type' => '[String!]!', 'description' => __( 'Why they are included.', 'flx-crm' ) ),
		),
	)
);
flx_gql_connection( 'FlxGatheringPerson', __( 'A page of people on an event.', 'flx-crm' ) );

flx_gql_type(
	'FlxGatheringAudience',
	array(
		'description' => __( 'How many people a channel can reach.', 'flx-crm' ),
		'fields'      => array(
			'key'            => array( 'type' => 'String!', 'description' => __( 'Channel key.', 'flx-crm' ) ),
			'label'          => array( 'type' => 'String!', 'description' => __( 'Channel name.', 'flx-crm' ) ),
			'ready'          => array( 'type' => 'Boolean!', 'description' => __( 'Flux Mail has a connection.', 'flx-crm' ) ),
			'infoCount'      => array( 'type' => 'Int!', 'description' => __( 'People for an information message.', 'flx-crm' ) ),
			'marketingCount' => array( 'type' => 'Int!', 'description' => __( 'People for a marketing message.', 'flx-crm' ) ),
		),
	)
);
flx_gql_type(
	'FlxGatheringVariable',
	array(
		'description' => __( 'A {token} the message composer can insert.', 'flx-crm' ),
		'fields'      => array(
			'group' => array( 'type' => 'String!', 'description' => __( 'person, event or site.', 'flx-crm' ) ),
			'key'   => array( 'type' => 'String!', 'description' => __( 'Token without braces.', 'flx-crm' ) ),
			'label' => array( 'type' => 'String!', 'description' => __( 'What it is.', 'flx-crm' ) ),
			'off'   => array( 'type' => 'Boolean!', 'description' => __( 'Needs a newer Flux Mail, so the composer disables it.', 'flx-crm' ) ),
		),
	)
);
flx_gql_type(
	'FlxGatheringVarValue',
	array(
		'description' => __( 'One sample value.', 'flx-crm' ),
		'fields'      => array(
			'key'   => array( 'type' => 'String!', 'description' => __( 'Token.', 'flx-crm' ) ),
			'value' => array( 'type' => 'String!', 'description' => __( 'Value for this person.', 'flx-crm' ) ),
		),
	)
);
flx_gql_type(
	'FlxGatheringSample',
	array(
		'description' => __( 'A person the composer can preview the message as.', 'flx-crm' ),
		'fields'      => array(
			'name'   => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-crm' ) ),
			'values' => array( 'type' => '[FlxGatheringVarValue!]!', 'description' => __( 'Token values.', 'flx-crm' ) ),
		),
	)
);
flx_gql_type(
	'FlxGatheringMessages',
	array(
		'description' => __( 'Composer and send history of one event.', 'flx-crm' ),
		'fields'      => array(
			'databaseId'   => array( 'type' => 'Int!', 'description' => __( 'Event ID.', 'flx-crm' ) ),
			'mailReady'    => array( 'type' => 'Boolean!', 'description' => __( 'Flux Mail can send.', 'flx-crm' ) ),
			'canPause'     => array( 'type' => 'Boolean!', 'description' => __( 'Flux Mail can pause a send.', 'flx-crm' ) ),
			'hasMedia'     => array( 'type' => 'Boolean!', 'description' => __( 'WhatsApp can take a file, not only a link.', 'flx-crm' ) ),
			'timezone'     => array( 'type' => 'String!', 'description' => __( 'Site timezone.', 'flx-crm' ) ),
			'defaultSendAt' => array( 'type' => 'FlxDateTime', 'description' => __( 'Suggested time: the event date at 20:00, if it is still ahead.', 'flx-crm' ) ),
			'channels'     => array( 'type' => '[FlxGatheringAudience!]!', 'description' => __( 'Channels and how many people each reaches.', 'flx-crm' ) ),
			'variables'    => array( 'type' => '[FlxGatheringVariable!]!', 'description' => __( 'Tokens.', 'flx-crm' ) ),
			'samples'      => array( 'type' => '[FlxGatheringSample!]!', 'description' => __( 'Up to 40 people for the preview.', 'flx-crm' ) ),
			'sends'        => array( 'type' => '[FlxGatheringSend!]!', 'description' => __( 'History, newest first.', 'flx-crm' ) ),
		),
	)
);
flx_gql_type(
	'FlxGatheringSkip',
	array(
		'description' => __( 'A channel that was not queued, and why.', 'flx-crm' ),
		'fields'      => array(
			'channel' => array( 'type' => 'String!', 'description' => __( 'Channel key.', 'flx-crm' ) ),
			'reason'  => array( 'type' => 'String!', 'description' => __( 'Why it was skipped.', 'flx-crm' ) ),
		),
	)
);

flx_gql_query(
	'flxGatherings',
	array(
		'type'        => 'FlxGatheringBoard!',
		'description' => __( 'Events screen: counts and one page of cards.', 'flx-crm' ),
		'cap'         => flxcrm_gql_view(),
		'args'        => array(
			'status' => array( 'type' => 'FlxGatheringStatus', 'description' => __( 'Which list the cards start on (default open).', 'flx-crm' ) ),
		),
		'resolve'     => static function ( $root, array $args ): array {
			unset( $root );
			$status = flx_gql_str( $args['status'] ?? 'open', 'open' );
			$html   = FLXCRM_Gathering_Service::site_html();
			flx_gql_cache_tag( FLXCRM_Gathering_Service::TAG_LIST );
			return array(
				'status'      => 'closed' === $status ? 'closed' : 'open',
				'openCount'   => FLXCRM_Gathering_Service::count( 'open' ),
				'closedCount' => FLXCRM_Gathering_Service::count( 'closed' ),
				'hasSite'     => '' !== $html,
				'siteHtml'    => $html,
				'mailReady'   => class_exists( 'FLXCRM_Event_Messages' ) && (bool) FLXCRM_Event_Messages::ready(),
			);
		},
	)
);

flx_gql_query(
	'flxGathering',
	array(
		'type'        => 'FlxGathering',
		'description' => __( 'One event. Empty when it does not exist.', 'flx-crm' ),
		'cap'         => flxcrm_gql_view(),
		'args'        => array( 'id' => array( 'type' => 'Int!', 'description' => __( 'Event ID.', 'flx-crm' ) ) ),
		'resolve'     => static function ( $root, array $args ): ?array {
			unset( $root );
			$id  = flx_gql_int( $args['id'] ?? 0 );
			$row = FLXCRM_Gathering_Service::get( $id );
			if ( null === $row ) {
				return null;
			}
			flx_gql_cache_tag( array( FLXCRM_Gathering_Service::TAG_LIST, FLXCRM_Gathering_Service::tag( $id ) ) );
			return flxcrm_gql_gathering( $row );
		},
	)
);

flx_gql_query(
	'flxGatheringForm',
	array(
		'type'        => 'FlxGatheringForm',
		'description' => __( 'Event form. Empty id is a blank form. Unknown id is empty.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'args'        => array( 'id' => array( 'type' => 'Int', 'description' => __( 'Event ID. Empty for a new event.', 'flx-crm' ) ) ),
		'resolve'     => static function ( $root, array $args ): ?array {
			unset( $root );
			return FLXCRM_Gathering_Service::form( flx_gql_int( $args['id'] ?? 0 ) );
		},
	)
);

flx_gql_query(
	'flxGatheringPeople',
	array(
		'type'        => 'FlxGatheringPersonConnection!',
		'description' => __( 'People of an event. Search matches name, phone and e-mail.', 'flx-crm' ),
		'cap'         => flxcrm_gql_view(),
		'args'        => flx_gql_connection_args(
			array(
				'id'     => array( 'type' => 'Int!', 'description' => __( 'Event ID.', 'flx-crm' ) ),
				'search' => array( 'type' => 'String', 'description' => __( 'Name, phone or e-mail.', 'flx-crm' ) ),
			)
		),
		'resolve'     => static function ( $root, array $args ): array {
			unset( $root );
			$id = flx_gql_int( $args['id'] ?? 0 );
			$q  = flx_gql_str( $args['search'] ?? '' );
			flx_gql_cache_tag( FLXCRM_Gathering_Service::tag( $id ) );
			return flx_gql_paginate(
				flx_gql_arr( $args ),
				static function ( int $offset, int $limit ) use ( $id, $q ): array {
					return FLXCRM_Gathering_Service::people( $id, $q, $offset, $limit );
				}
			);
		},
	)
);

flx_gql_query(
	'flxGatheringMessages',
	array(
		'type'        => 'FlxGatheringMessages',
		'description' => __( 'Composer and history. Empty when the event does not exist.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'args'        => array( 'id' => array( 'type' => 'Int!', 'description' => __( 'Event ID.', 'flx-crm' ) ) ),
		'resolve'     => static function ( $root, array $args ) {
			unset( $root );
			$id = flx_gql_int( $args['id'] ?? 0 );
			$r  = FLXCRM_Gathering_Service::messages( $id );
			if ( $r instanceof WP_Error ) {
				throw flx_gql_from_wp_error( $r );
			}
			$when               = $r['defaultSendAt'];
			$r['defaultSendAt'] = $when instanceof DateTimeInterface ? flx_gql_datetime( $when ) : null;
			$sends              = array();
			foreach ( $r['sends'] as $send ) {
				$sends[] = flxcrm_gql_send( $send );
			}
			$r['sends'] = $sends;
			flx_gql_cache_tag( FLXCRM_Gathering_Service::tag( $id ) );
			return $r;
		},
	)
);

flx_gql_query(
	'flxGatheringSend',
	array(
		'type'        => 'FlxGatheringSend',
		'description' => __( 'One send, with its recipients. Empty when it does not exist.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'args'        => array(
			'id'  => array( 'type' => 'Int!', 'description' => __( 'Event ID.', 'flx-crm' ) ),
			'key' => array( 'type' => 'String!', 'description' => __( 'Send key.', 'flx-crm' ) ),
		),
		'resolve'     => static function ( $root, array $args ): ?array {
			unset( $root );
			$id  = flx_gql_int( $args['id'] ?? 0 );
			$row = FLXCRM_Gathering_Service::send( $id, flx_gql_str( $args['key'] ?? '' ) );
			if ( null === $row ) {
				return null;
			}
			flx_gql_cache_tag( FLXCRM_Gathering_Service::tag( $id ) );
			return flxcrm_gql_send( $row );
		},
	)
);

flx_gql_mutation(
	'flxGatheringSave',
	array(
		'description' => __( 'Creates an event, or updates one when an ID is given.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'input'       => array(
			'id'            => array( 'type' => 'Int', 'description' => __( 'Event ID. Empty creates one.', 'flx-crm' ) ),
			'fields'        => array( 'type' => '[FlxGatheringFieldInput!]', 'description' => __( 'Fields to write. Omitted keys stay as they are.', 'flx-crm' ) ),
			'createPost'    => array( 'type' => 'Boolean', 'description' => __( 'Also create the page on the site.', 'flx-crm' ) ),
			'imageUploadId' => array( 'type' => 'String', 'description' => __( 'Upload id from flx-crm/event-image.', 'flx-crm' ) ),
		),
		'output'      => array( 'gathering' => array( 'type' => 'FlxGathering', 'description' => __( 'The event after the change.', 'flx-crm' ) ) ),
		'resolve'     => static function ( array $input ) {
			$r = FLXCRM_Gathering_Service::save( flx_gql_int( $input['id'] ?? 0 ), flxcrm_gql_field_values( $input['fields'] ?? array() ), ! empty( $input['createPost'] ), flx_gql_str( $input['imageUploadId'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'gathering' => flxcrm_gql_gathering( $r ) );
		},
	)
);

flx_gql_mutation(
	'flxGatheringSetStatus',
	array(
		'description' => __( 'Archives an event, or puts it back on the working list.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'input'       => array(
			'id'     => array( 'type' => 'Int!', 'description' => __( 'Event ID.', 'flx-crm' ) ),
			'status' => array( 'type' => 'FlxGatheringStatus!', 'description' => __( 'Open or archived.', 'flx-crm' ) ),
		),
		'output'      => array( 'gathering' => array( 'type' => 'FlxGathering', 'description' => __( 'The event after the change.', 'flx-crm' ) ) ),
		'resolve'     => static function ( array $input ) {
			$r = FLXCRM_Gathering_Service::set_status( flx_gql_int( $input['id'] ?? 0 ), flx_gql_str( $input['status'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'gathering' => flxcrm_gql_gathering( $r ) );
		},
	)
);

flx_gql_mutation(
	'flxGatheringDelete',
	array(
		'description' => __( 'Deletes an event. Package records stay; they detach themselves.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'input'       => array( 'id' => array( 'type' => 'Int!', 'description' => __( 'Event ID.', 'flx-crm' ) ) ),
		'output'      => array( 'deleted' => array( 'type' => 'Boolean', 'description' => __( 'True when it was deleted.', 'flx-crm' ) ) ),
		'resolve'     => static function ( array $input ) {
			$r = FLXCRM_Gathering_Service::delete( flx_gql_int( $input['id'] ?? 0 ) );
			return $r instanceof WP_Error ? $r : array( 'deleted' => true );
		},
	)
);

flx_gql_mutation(
	'flxGatheringEnsurePage',
	array(
		'description' => __( 'Creates or publishes the site page of an event.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'input'       => array( 'id' => array( 'type' => 'Int!', 'description' => __( 'Event ID.', 'flx-crm' ) ) ),
		'output'      => array(
			'postId'    => array( 'type' => 'Int', 'description' => __( 'Page ID.', 'flx-crm' ) ),
			'gathering' => array( 'type' => 'FlxGathering', 'description' => __( 'The event after the change.', 'flx-crm' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXCRM_Gathering_Service::ensure_page( flx_gql_int( $input['id'] ?? 0 ) );
			if ( $r instanceof WP_Error ) {
				return $r;
			}
			return array( 'postId' => $r['postId'], 'gathering' => flxcrm_gql_gathering( $r['gathering'] ) );
		},
	)
);

flx_gql_mutation(
	'flxGatheringMessageSend',
	array(
		'description' => __( 'Queues an event message through Flux Mail.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'input'       => array(
			'id'            => array( 'type' => 'Int!', 'description' => __( 'Event ID.', 'flx-crm' ) ),
			'channels'      => array( 'type' => '[FlxGatheringChannel!]!', 'description' => __( 'Channels.', 'flx-crm' ) ),
			'kind'          => array( 'type' => 'FlxGatheringKind', 'description' => __( 'Information or marketing (default information).', 'flx-crm' ) ),
			'text'          => array( 'type' => 'String!', 'description' => __( 'Message.', 'flx-crm' ) ),
			'subject'       => array( 'type' => 'String', 'description' => __( 'E-mail subject. Empty uses the event name.', 'flx-crm' ) ),
			'mediaId'       => array( 'type' => 'Int', 'description' => __( 'Already uploaded attachment.', 'flx-crm' ) ),
			'mediaUploadId' => array( 'type' => 'String', 'description' => __( 'Upload id from flx-crm/event-file.', 'flx-crm' ) ),
			'sendAt'        => array( 'type' => 'String', 'description' => __( 'Site time Y-m-dTH:i. Empty sends now.', 'flx-crm' ) ),
		),
		'output'      => array(
			'send'    => array( 'type' => 'FlxGatheringSend', 'description' => __( 'The queued send.', 'flx-crm' ) ),
			'skipped' => array( 'type' => '[FlxGatheringSkip!]!', 'description' => __( 'Channels that were not queued.', 'flx-crm' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$channels = array();
			foreach ( flx_gql_arr( $input['channels'] ?? array() ) as $ch ) {
				$channels[] = flx_gql_str( $ch );
			}
			$r = FLXCRM_Gathering_Service::queue_message(
				flx_gql_int( $input['id'] ?? 0 ),
				$channels,
				flx_gql_str( $input['kind'] ?? 'info', 'info' ),
				flx_gql_str( $input['text'] ?? '' ),
				flx_gql_str( $input['subject'] ?? '' ),
				flx_gql_int( $input['mediaId'] ?? 0 ),
				flx_gql_str( $input['mediaUploadId'] ?? '' ),
				flx_gql_str( $input['sendAt'] ?? '' )
			);
			if ( $r instanceof WP_Error ) {
				return $r;
			}
			$send = null !== $r['send'] ? flxcrm_gql_send( $r['send'] ) : null;
			return array( 'send' => $send, 'skipped' => $r['skipped'] );
		},
	)
);

flx_gql_mutation(
	'flxGatheringSendControl',
	array(
		'description' => __( 'Pauses, resumes or cancels every channel of one send.', 'flx-crm' ),
		'cap'         => flxcrm_gql_manage(),
		'input'       => array(
			'id'  => array( 'type' => 'Int!', 'description' => __( 'Event ID.', 'flx-crm' ) ),
			'key' => array( 'type' => 'String!', 'description' => __( 'Send key.', 'flx-crm' ) ),
			'do'  => array( 'type' => 'FlxGatheringControl!', 'description' => __( 'Pause, resume or cancel.', 'flx-crm' ) ),
		),
		'output'      => array( 'send' => array( 'type' => 'FlxGatheringSend', 'description' => __( 'The send after the change.', 'flx-crm' ) ) ),
		'resolve'     => static function ( array $input ) {
			$r = FLXCRM_Gathering_Service::control( flx_gql_int( $input['id'] ?? 0 ), flx_gql_str( $input['key'] ?? '' ), flx_gql_str( $input['do'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'send' => flxcrm_gql_send( $r ) );
		},
	)
);
