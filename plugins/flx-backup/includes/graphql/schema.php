<?php
/**
 * Flux Backup GraphQL schema (Flux GraphQL kit). Loaded on flx_gql_register, i.e. only inside a
 * GraphQL request. Resolvers call FLXY_Service; the Backups screen (wp-admin and the FluxWP app)
 * reads and writes only through these fields.
 *
 * Long work is not done inside a request: flxBackupStart / flxBackupRestoreStart create the job,
 * flxBackupStep advances it by one time budget (PHP stops at ~60 s on shared hosting) and
 * flxBackupJob only reads its state. The backup file itself is downloaded through the signed
 * `downloadUrl` and uploaded in chunks over admin-ajax (docs/graphql/ISTISNALAR.md).
 *
 * @package flx-backup
 */

defined( 'ABSPATH' ) || exit;

/**
 * Highest permission: restoring overwrites the database and files, deleting removes the only copy.
 * Administrators on a single site; super administrators on a network.
 */
function flxy_gql_top_cap(): bool {
	return current_user_can( 'manage_options' ) && ( ! is_multisite() || is_super_admin() );
}

/**
 * Unix time → FlxDateTime, null when unset.
 *
 * @param mixed $ts Time.
 * @return array<string,mixed>|null
 */
function flxy_gql_time( $ts ): ?array {
	$n = is_numeric( $ts ) ? (int) $ts : 0;
	return $n > 0 ? flx_gql_datetime( $n ) : null;
}

/**
 * A field that reads a Unix time key of the parent as FlxDateTime.
 *
 * @param string $key         Key of the parent array.
 * @param string $description Field description.
 * @return array<string,mixed>
 */
function flxy_gql_time_field( string $key, string $description ): array {
	return array(
		'type'        => 'FlxDateTime',
		'description' => $description,
		'resolve'     => static fn( $src ): ?array => flxy_gql_time( flx_gql_arr( $src )[ $key ] ?? 0 ),
	);
}

/**
 * Plain fields: name => [ type, description ].
 *
 * @param array<string,array{0: string, 1: string}> $spec Fields.
 * @return array<string,array<string,string>>
 */
function flxy_gql_fields( array $spec ): array {
	$out = array();
	foreach ( $spec as $name => $f ) {
		$out[ $name ] = array(
			'type'        => $f[0],
			'description' => $f[1],
		);
	}
	return $out;
}

flx_gql_enum(
	'FlxBackupType',
	array(
		'description' => __( 'What a backup holds or why it was taken.', 'flx-backup' ),
		'values'      => array(
			'FULL'       => array( 'value' => 'full' ),
			'DB'         => array( 'value' => 'db' ),
			'FILES'      => array( 'value' => 'files' ),
			'PRE_UPDATE' => array( 'value' => 'pre_update' ),
			'MANUAL'     => array( 'value' => 'manual' ),
		),
	)
);
flx_gql_enum(
	'FlxBackupStartType',
	array(
		'description' => __( 'Backup types a person can start.', 'flx-backup' ),
		'values'      => array(
			'FULL'  => array( 'value' => 'full' ),
			'DB'    => array( 'value' => 'db' ),
			'FILES' => array( 'value' => 'files' ),
		),
	)
);
flx_gql_enum(
	'FlxBackupStatus',
	array(
		'description' => __( 'State of a backup.', 'flx-backup' ),
		'values'      => array(
			'RUNNING' => array( 'value' => 'running' ),
			'OK'      => array( 'value' => 'ok' ),
			'FAILED'  => array( 'value' => 'failed' ),
		),
	)
);
flx_gql_enum(
	'FlxBackupKeepKind',
	array(
		'description' => __( 'How long a backup is kept.', 'flx-backup' ),
		'values'      => array(
			'LOCKED'   => array( 'value' => 'locked' ),
			'UNTIL'    => array( 'value' => 'until' ),
			'SCHEDULE' => array( 'value' => 'schedule' ),
			'QUOTA'    => array( 'value' => 'quota' ),
		),
	)
);
flx_gql_enum(
	'FlxBackupJobStatus',
	array(
		'description' => __( 'State of the background job.', 'flx-backup' ),
		'values'      => array(
			'IDLE'    => array( 'value' => 'idle' ),
			'RUNNING' => array( 'value' => 'running' ),
			'DONE'    => array( 'value' => 'done' ),
			'FAILED'  => array( 'value' => 'failed' ),
		),
	)
);
flx_gql_enum(
	'FlxBackupJobKind',
	array(
		'description' => __( 'What the background job does.', 'flx-backup' ),
		'values'      => array(
			'BACKUP'  => array( 'value' => 'backup' ),
			'RESTORE' => array( 'value' => 'restore' ),
		),
	)
);
flx_gql_enum(
	'FlxBackupPreScope',
	array(
		'description' => __( 'Which updates are backed up first.', 'flx-backup' ),
		'values'      => array(
			'ALL' => array( 'value' => 'all' ),
			'FLX' => array( 'value' => 'flx' ),
			'OFF' => array( 'value' => 'off' ),
		),
	)
);
flx_gql_enum(
	'FlxBackupSchedule',
	array(
		'description' => __( 'How often a full backup is scheduled.', 'flx-backup' ),
		'values'      => array(
			'OFF'    => array( 'value' => 'off' ),
			'DAILY'  => array( 'value' => 'daily' ),
			'WEEKLY' => array( 'value' => 'weekly' ),
		),
	)
);

flx_gql_type(
	'FlxBackupPackage',
	array(
		'description' => __( 'A package updated right after a pre-update backup.', 'flx-backup' ),
		'fields'      => flxy_gql_fields(
			array(
				'slug'        => array( 'String!', __( 'Package slug.', 'flx-backup' ) ),
				'name'        => array( 'String!', __( 'Package name (the slug when unknown).', 'flx-backup' ) ),
				'fromVersion' => array( 'String!', __( 'Version before the update; empty when unknown.', 'flx-backup' ) ),
				'toVersion'   => array( 'String!', __( 'Version after the update; empty when unknown.', 'flx-backup' ) ),
				'label'       => array( 'String!', __( 'Name and versions as one line.', 'flx-backup' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxBackupRestoreItem',
	array(
		'description' => __( 'One folder of a backup that can be restored.', 'flx-backup' ),
		'fields'      => flxy_gql_fields(
			array(
				'path'  => array( 'String!', __( 'Path relative to the WordPress root folder.', 'flx-backup' ) ),
				'label' => array( 'String!', __( 'Name shown in the dialog.', 'flx-backup' ) ),
			)
		),
	)
);
flx_gql_type(
	'FlxBackupRestoreGroup',
	array(
		'description' => __( 'Restore choices of one kind: plugins, themes or other folders.', 'flx-backup' ),
		'fields'      => flxy_gql_fields(
			array(
				'key'   => array( 'String!', __( 'Group key: plugins, themes or other.', 'flx-backup' ) ),
				'label' => array( 'String!', __( 'Group name.', 'flx-backup' ) ),
				'items' => array( '[FlxBackupRestoreItem!]!', __( 'Folders in the group.', 'flx-backup' ) ),
			)
		),
	)
);
flx_gql_type(
	'FlxBackupRestoreOptions',
	array(
		'description' => __( 'What the restore dialog offers for a backup.', 'flx-backup' ),
		'fields'      => flxy_gql_fields(
			array(
				'id'             => array( 'ID!', __( 'Backup ID.', 'flx-backup' ) ),
				'title'          => array( 'String!', __( 'Backup name and date.', 'flx-backup' ) ),
				'database'       => array( 'Boolean!', __( 'The database can be restored (it is in the backup and the table prefix matches).', 'flx-backup' ) ),
				'groups'         => array( '[FlxBackupRestoreGroup!]!', __( 'Folders that can be restored, by kind.', 'flx-backup' ) ),
				'foreignSite'    => array( 'String', __( 'Address of the site the backup was taken on, when it is another site.', 'flx-backup' ) ),
				'prefixInBackup' => array( 'String', __( 'Table prefix in the backup when it differs from this site\'s.', 'flx-backup' ) ),
				'prefixOnSite'   => array( 'String', __( 'This site\'s table prefix when it differs from the backup\'s.', 'flx-backup' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxBackupBreakdownItem',
	array(
		'description' => __( 'One plugin, theme, table or folder in the size breakdown.', 'flx-backup' ),
		'fields'      => flxy_gql_fields(
			array(
				'name'  => array( 'String!', __( 'Path or table name in the backup.', 'flx-backup' ) ),
				'label' => array( 'String!', __( 'Name shown on the screen.', 'flx-backup' ) ),
				'raw'   => array( 'Float!', __( 'Size on the site in bytes (for the database, the SQL dump).', 'flx-backup' ) ),
				'zip'   => array( 'Float!', __( 'Compressed size in the archive in bytes.', 'flx-backup' ) ),
				'count' => array( 'Int!', __( 'Number of files (rows for the database).', 'flx-backup' ) ),
			)
		),
	)
);
flx_gql_type(
	'FlxBackupBreakdownCategory',
	array(
		'description' => __( 'A section of the size breakdown: database, core, plugins, themes, media or other.', 'flx-backup' ),
		'fields'      => flxy_gql_fields(
			array(
				'key'        => array( 'String!', __( 'Section key.', 'flx-backup' ) ),
				'label'      => array( 'String!', __( 'Section name.', 'flx-backup' ) ),
				'color'      => array( 'String!', __( 'Chart colour.', 'flx-backup' ) ),
				'isDatabase' => array( 'Boolean!', __( 'Counts are rows, not files.', 'flx-backup' ) ),
				'raw'        => array( 'Float!', __( 'Size on the site in bytes.', 'flx-backup' ) ),
				'zip'        => array( 'Float!', __( 'Compressed size in bytes.', 'flx-backup' ) ),
				'count'      => array( 'Int!', __( 'Number of files (rows for the database).', 'flx-backup' ) ),
				'items'      => array( '[FlxBackupBreakdownItem!]!', __( 'The biggest items of the section.', 'flx-backup' ) ),
				'moreCount'  => array( 'Int!', __( 'Files or rows in the items not listed.', 'flx-backup' ) ),
				'moreRaw'    => array( 'Float!', __( 'Size of the items not listed in bytes.', 'flx-backup' ) ),
				'moreZip'    => array( 'Float!', __( 'Compressed size of the items not listed in bytes.', 'flx-backup' ) ),
				'moreItems'  => array( 'Int!', __( 'How many items are not listed.', 'flx-backup' ) ),
			)
		),
	)
);
flx_gql_type(
	'FlxBackupBreakdown',
	array(
		'description' => __( 'Where the size of a backup goes.', 'flx-backup' ),
		'fields'      => flxy_gql_fields(
			array(
				'raw'        => array( 'Float!', __( 'Total size on the site in bytes.', 'flx-backup' ) ),
				'zip'        => array( 'Float!', __( 'Total size in the archive in bytes.', 'flx-backup' ) ),
				'categories' => array( '[FlxBackupBreakdownCategory!]!', __( 'Sections, in display order.', 'flx-backup' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxBackup',
	array(
		'description' => __( 'A backup: a zip with the database and/or files, kept in the backup folder.', 'flx-backup' ),
		'fields'      => flxy_gql_fields(
			array(
				'id'               => array( 'ID!', __( 'Backup ID (not a global ID).', 'flx-backup' ) ),
				'type'             => array( 'FlxBackupType!', __( 'What the backup holds or why it was taken.', 'flx-backup' ) ),
				'typeLabel'        => array( 'String!', __( 'Type name in the user\'s language.', 'flx-backup' ) ),
				'typeColor'        => array( 'String!', __( 'Chart colour of the type.', 'flx-backup' ) ),
				'status'           => array( 'FlxBackupStatus!', __( 'Running, finished or failed.', 'flx-backup' ) ),
				'statusLabel'      => array( 'String!', __( 'Status name in the user\'s language.', 'flx-backup' ) ),
				'title'            => array( 'String!', __( 'Description, or a generated name such as "Before Plugin 1.0 → 1.1".', 'flx-backup' ) ),
				'label'            => array( 'String!', __( 'Description the person typed; empty when none.', 'flx-backup' ) ),
				'size'             => array( 'Float!', __( 'Size of the zip in bytes (0 while running).', 'flx-backup' ) ),
				'sizeLabel'        => array( 'String!', __( 'Size as text; empty unless finished.', 'flx-backup' ) ),
				'files'            => array( 'Int!', __( 'Number of files in the backup.', 'flx-backup' ) ),
				'includesDatabase' => array( 'Boolean!', __( 'The database is in the backup.', 'flx-backup' ) ),
				'contents'         => array( '[String!]!', __( 'What the backup holds, as short names.', 'flx-backup' ) ),
				'source'           => array( 'String!', __( 'Where it came from: manual, schedule, auto, update, cli, safety, api, upload; empty when unknown.', 'flx-backup' ) ),
				'sourceLabel'      => array( 'String!', __( 'Source name in the user\'s language.', 'flx-backup' ) ),
				'originalSite'     => array( 'String', __( 'Site the backup was taken on; set for uploaded backups only.', 'flx-backup' ) ),
				'wpVersion'        => array( 'String!', __( 'WordPress version when the backup was taken; empty when unknown.', 'flx-backup' ) ),
				'locked'           => array( 'Boolean!', __( 'Never deleted automatically.', 'flx-backup' ) ),
				'keepKind'         => array( 'FlxBackupKeepKind!', __( 'How long the backup is kept.', 'flx-backup' ) ),
				'keepLabel'        => array( 'String!', __( 'Retention as text.', 'flx-backup' ) ),
				'error'            => array( 'String!', __( 'Why it failed; empty otherwise.', 'flx-backup' ) ),
				'packages'         => array( '[FlxBackupPackage!]!', __( 'Packages a pre-update backup was taken for.', 'flx-backup' ) ),
				'downloadUrl'      => array( 'String!', __( 'Signed link that downloads the zip (the file is not sent through GraphQL); empty unless finished.', 'flx-backup' ) ),
				'percent'          => array( 'Int', __( 'Progress of a running backup; empty otherwise.', 'flx-backup' ) ),
				'progress'         => array( 'String!', __( 'Progress text of a running backup.', 'flx-backup' ) ),
				'running'          => array( 'Boolean!', __( 'The backup is still being taken.', 'flx-backup' ) ),
			)
		) + array(
			'created'         => flxy_gql_time_field( 'created', __( 'When the backup was started.', 'flx-backup' ) ),
			'finished'        => flxy_gql_time_field( 'finished', __( 'When the backup was finished; empty while running.', 'flx-backup' ) ),
			'keepUntil'       => flxy_gql_time_field( 'keepUntil', __( 'Date the backup is kept until; empty when there is no date.', 'flx-backup' ) ),
			'originalCreated' => flxy_gql_time_field( 'originalCreated', __( 'When an uploaded backup was originally taken.', 'flx-backup' ) ),
			'restore'         => array(
				'type'        => 'FlxBackupRestoreOptions',
				'description' => __( 'What can be restored; empty unless the backup is finished.', 'flx-backup' ),
				'resolve'     => static function ( $src ): ?array {
					return FLXY_Service::restore_for( flx_gql_str( flx_gql_arr( $src )['id'] ?? '' ) );
				},
			),
			'breakdown'       => array(
				'type'        => 'FlxBackupBreakdown',
				'description' => __( 'Where the size goes. Reading the zip takes a while the first time, so ask for it on the detail page only.', 'flx-backup' ),
				'resolve'     => static function ( $src ): ?array {
					return FLXY_Service::breakdown( flx_gql_str( flx_gql_arr( $src )['id'] ?? '' ) );
				},
			),
		),
	)
);
flx_gql_connection( 'FlxBackup', __( 'A page of backups, newest first.', 'flx-backup' ) );

flx_gql_type(
	'FlxBackupTypeCount',
	array(
		'description' => __( 'Number of backups of one type.', 'flx-backup' ),
		'fields'      => flxy_gql_fields(
			array(
				'type'  => array( 'FlxBackupType!', __( 'Type.', 'flx-backup' ) ),
				'label' => array( 'String!', __( 'Type name in the user\'s language.', 'flx-backup' ) ),
				'count' => array( 'Int!', __( 'Backups of the type.', 'flx-backup' ) ),
			)
		),
	)
);
flx_gql_type(
	'FlxBackupCounts',
	array(
		'description' => __( 'Totals for the filter links and the footer of the backup list.', 'flx-backup' ),
		'fields'      => flxy_gql_fields(
			array(
				'all'       => array( 'Int!', __( 'Number of backups.', 'flx-backup' ) ),
				'usedBytes' => array( 'Float!', __( 'Total size of the backups in bytes.', 'flx-backup' ) ),
				'usedLabel' => array( 'String!', __( 'Total size as text.', 'flx-backup' ) ),
				'types'     => array( '[FlxBackupTypeCount!]!', __( 'Backups by type; types without a backup are left out.', 'flx-backup' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxBackupTypeShare',
	array(
		'description' => __( 'Storage used by one backup type.', 'flx-backup' ),
		'fields'      => flxy_gql_fields(
			array(
				'type'       => array( 'FlxBackupType!', __( 'Type.', 'flx-backup' ) ),
				'label'      => array( 'String!', __( 'Type name in the user\'s language.', 'flx-backup' ) ),
				'color'      => array( 'String!', __( 'Chart colour.', 'flx-backup' ) ),
				'bytes'      => array( 'Float!', __( 'Bytes used.', 'flx-backup' ) ),
				'bytesLabel' => array( 'String!', __( 'Bytes used as text.', 'flx-backup' ) ),
				'count'      => array( 'Int!', __( 'Finished backups of the type.', 'flx-backup' ) ),
				'share'      => array( 'Float!', __( 'Percent of all backup storage.', 'flx-backup' ) ),
			)
		),
	)
);
flx_gql_type(
	'FlxBackupEvent',
	array(
		'description' => __( 'A line of the backup event log.', 'flx-backup' ),
		'fields'      => flxy_gql_fields(
			array(
				'level'   => array( 'String!', __( 'info, warning or error.', 'flx-backup' ) ),
				'message' => array( 'String!', __( 'What happened.', 'flx-backup' ) ),
			)
		) + array( 'at' => flxy_gql_time_field( 'at', __( 'When it happened.', 'flx-backup' ) ) ),
	)
);
flx_gql_type(
	'FlxBackupOverview',
	array(
		'description' => __( 'The dashboard: storage, counts, last and next backup, by type, recent backups and events.', 'flx-backup' ),
		'fields'      => flxy_gql_fields(
			array(
				'usedBytes'    => array( 'Float!', __( 'Storage used by backups in bytes.', 'flx-backup' ) ),
				'usedLabel'    => array( 'String!', __( 'Storage used as text.', 'flx-backup' ) ),
				'quotaBytes'   => array( 'Float!', __( 'Storage quota in bytes.', 'flx-backup' ) ),
				'quotaLabel'   => array( 'String!', __( 'Storage quota as text.', 'flx-backup' ) ),
				'percent'      => array( 'Float!', __( 'Percent of the quota used (0–100).', 'flx-backup' ) ),
				'level'        => array( 'String!', __( 'ok, warning (over 80%) or error (over 95%).', 'flx-backup' ) ),
				'freeLabel'    => array( 'String!', __( 'Free quota as text.', 'flx-backup' ) ),
				'backupCount'  => array( 'Int!', __( 'Finished backups.', 'flx-backup' ) ),
				'lockedCount'  => array( 'Int!', __( 'Finished backups that are locked.', 'flx-backup' ) ),
				'last'         => array( 'FlxBackup', __( 'Last finished backup; empty when there is none.', 'flx-backup' ) ),
				'scheduleKind' => array( 'FlxBackupSchedule!', __( 'Scheduled backup frequency.', 'flx-backup' ) ),
				'preScope'     => array( 'FlxBackupPreScope!', __( 'Which updates are backed up first.', 'flx-backup' ) ),
				'byType'       => array( '[FlxBackupTypeShare!]!', __( 'Storage by type.', 'flx-backup' ) ),
				'recent'       => array( '[FlxBackup!]!', __( 'The six newest backups.', 'flx-backup' ) ),
				'events'       => array( '[FlxBackupEvent!]!', __( 'The seven latest events.', 'flx-backup' ) ),
			)
		) + array( 'nextRun' => flxy_gql_time_field( 'nextRun', __( 'Next scheduled backup; empty when the schedule is off.', 'flx-backup' ) ) ),
	)
);

flx_gql_type(
	'FlxBackupHistoryDay',
	array(
		'description' => __( 'Storage on one day of the history chart.', 'flx-backup' ),
		'fields'      => flxy_gql_fields(
			array(
				'key'         => array( 'String!', __( 'Date, YYYY-MM-DD.', 'flx-backup' ) ),
				'label'       => array( 'String!', __( 'Short date for the axis.', 'flx-backup' ) ),
				'fullLabel'   => array( 'String!', __( 'Date for the tooltip.', 'flx-backup' ) ),
				'values'      => array( '[Float!]!', __( 'Bytes by type, in the order of the series.', 'flx-backup' ) ),
				'valueLabels' => array( '[String!]!', __( 'Bytes by type as text.', 'flx-backup' ) ),
				'totalLabel'  => array( 'String!', __( 'Total bytes as text.', 'flx-backup' ) ),
				'measured'    => array( 'Boolean!', __( 'A measurement exists for the day; false before the first one.', 'flx-backup' ) ),
			)
		),
	)
);
flx_gql_type(
	'FlxBackupHistorySeries',
	array(
		'description' => __( 'A backup type in the history chart.', 'flx-backup' ),
		'fields'      => flxy_gql_fields(
			array(
				'key'   => array( 'String!', __( 'Type key.', 'flx-backup' ) ),
				'name'  => array( 'String!', __( 'Type name in the user\'s language.', 'flx-backup' ) ),
				'color' => array( 'String!', __( 'Chart colour.', 'flx-backup' ) ),
			)
		),
	)
);
flx_gql_type(
	'FlxBackupHistoryTick',
	array(
		'description' => __( 'A mark on the Y axis.', 'flx-backup' ),
		'fields'      => flxy_gql_fields(
			array(
				'value' => array( 'Float!', __( 'Bytes.', 'flx-backup' ) ),
				'label' => array( 'String!', __( 'Bytes as text.', 'flx-backup' ) ),
			)
		),
	)
);
flx_gql_type(
	'FlxBackupHistoryRange',
	array(
		'description' => __( 'Y axis of the chart for the last 30, 90 or 180 days.', 'flx-backup' ),
		'fields'      => flxy_gql_fields(
			array(
				'days'      => array( 'Int!', __( 'Number of days.', 'flx-backup' ) ),
				'max'       => array( 'Float!', __( 'Top of the axis in bytes.', 'flx-backup' ) ),
				'showQuota' => array( 'Boolean!', __( 'Usage is near the quota, so the quota line is drawn.', 'flx-backup' ) ),
				'ticks'     => array( '[FlxBackupHistoryTick!]!', __( 'Marks of the axis.', 'flx-backup' ) ),
			)
		),
	)
);
flx_gql_type(
	'FlxBackupHistory',
	array(
		'description' => __( 'Storage history: one measurement a day for 180 days.', 'flx-backup' ),
		'fields'      => flxy_gql_fields(
			array(
				'days'       => array( '[FlxBackupHistoryDay!]!', __( 'The last 180 days, oldest first; days without a measurement repeat the previous one.', 'flx-backup' ) ),
				'series'     => array( '[FlxBackupHistorySeries!]!', __( 'Backup types, in the order of each day\'s values.', 'flx-backup' ) ),
				'ranges'     => array( '[FlxBackupHistoryRange!]!', __( 'Axis for each range.', 'flx-backup' ) ),
				'quota'      => array( 'Float!', __( 'Quota in bytes.', 'flx-backup' ) ),
				'quotaLabel' => array( 'String!', __( 'Quota as a chart label.', 'flx-backup' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxBackupSettings',
	array(
		'description' => __( 'Flux Backup settings.', 'flx-backup' ),
		'fields'      => flxy_gql_fields(
			array(
				'quotaGb'           => array( 'Float!', __( 'Storage quota in GB.', 'flx-backup' ) ),
				'preScope'          => array( 'FlxBackupPreScope!', __( 'Which updates are backed up first.', 'flx-backup' ) ),
				'preKeepDays'       => array( 'Int!', __( 'Days a pre-update backup is kept.', 'flx-backup' ) ),
				'schedule'          => array( 'FlxBackupSchedule!', __( 'Scheduled full backup frequency.', 'flx-backup' ) ),
				'scheduleHour'      => array( 'Int!', __( 'Hour of the day (0–23) in the site\'s time zone.', 'flx-backup' ) ),
				'scheduleDay'       => array( 'Int!', __( 'Day of the week for weekly backups: 0 = Sunday … 6 = Saturday.', 'flx-backup' ) ),
				'scheduleKeep'      => array( 'Int!', __( 'How many scheduled backups are kept.', 'flx-backup' ) ),
				'includeCore'       => array( 'Boolean!', __( 'WordPress core files are added to full backups.', 'flx-backup' ) ),
				'exclude'           => array( 'String!', __( 'Extra paths left out, one per line.', 'flx-backup' ) ),
				'safetyRestore'     => array( 'Boolean!', __( 'A safety backup of the database is taken before restoring it.', 'flx-backup' ) ),
				'deleteOnUninstall' => array( 'Boolean!', __( 'Backup files are deleted when the plugin is deleted.', 'flx-backup' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxBackupHealth',
	array(
		'description' => __( 'Server state and the values the upload and start dialogs need.', 'flx-backup' ),
		'fields'      => flxy_gql_fields(
			array(
				'zipAvailable'       => array( 'Boolean!', __( 'The PHP zip extension is available.', 'flx-backup' ) ),
				'writable'           => array( 'Boolean!', __( 'The backup folder is writable.', 'flx-backup' ) ),
				'folder'             => array( 'String!', __( 'Backup folder relative to the WordPress root folder.', 'flx-backup' ) ),
				'chunkSize'          => array( 'Int!', __( 'Size in bytes of the pieces a backup is uploaded in.', 'flx-backup' ) ),
				'nonce'              => array( 'String!', __( 'Token the chunked upload (admin-ajax flxy_upload_*) needs.', 'flx-backup' ) ),
				'ajaxUrl'            => array( 'String!', __( 'admin-ajax address of the chunked upload.', 'flx-backup' ) ),
				'lastRestore'        => array( 'String', __( 'Result of a restore finished in the last 10 minutes.', 'flx-backup' ) ),
				'includeCoreDefault' => array( 'Boolean!', __( 'Default of the "include WordPress core" choice.', 'flx-backup' ) ),
				'safetyDefault'      => array( 'Boolean!', __( 'Default of the "safety backup" choice.', 'flx-backup' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxBackupJob',
	array(
		'description' => __( 'The backup or restore running in the background (one at a time).', 'flx-backup' ),
		'fields'      => flxy_gql_fields(
			array(
				'status'       => array( 'FlxBackupJobStatus!', __( 'Idle, running, done or failed.', 'flx-backup' ) ),
				'kind'         => array( 'FlxBackupJobKind', __( 'Backup or restore; empty when idle.', 'flx-backup' ) ),
				'id'           => array( 'String!', __( 'ID of the backup the job makes or restores; empty when idle.', 'flx-backup' ) ),
				'phase'        => array( 'String!', __( 'Internal phase name.', 'flx-backup' ) ),
				'percent'      => array( 'Int!', __( 'Progress, 0–100.', 'flx-backup' ) ),
				'text'         => array( 'String!', __( 'What the job is doing now.', 'flx-backup' ) ),
				'error'        => array( 'String!', __( 'Why the job failed; empty otherwise.', 'flx-backup' ) ),
				'token'        => array( 'String!', __( 'Job token. After a database restore the login session may be gone; the screen then keeps stepping through admin-ajax flxy_step with this token.', 'flx-backup' ) ),
				'cancelled'    => array( 'Boolean!', __( 'The job was cancelled.', 'flx-backup' ) ),
				'elapsed'      => array( 'Int!', __( 'Seconds since the job started.', 'flx-backup' ) ),
				'elapsedLabel' => array( 'String!', __( 'Elapsed time as text.', 'flx-backup' ) ),
				'eta'          => array( 'Int', __( 'Estimated seconds left; empty until it can be estimated.', 'flx-backup' ) ),
				'etaLabel'     => array( 'String!', __( 'Estimated time left as text.', 'flx-backup' ) ),
				'title'        => array( 'String!', __( 'Name of the backup.', 'flx-backup' ) ),
				'sizeLabel'    => array( 'String!', __( 'Size of the backup as text; empty until known.', 'flx-backup' ) ),
				'result'       => array( 'String!', __( 'Result text of a finished restore.', 'flx-backup' ) ),
			)
		),
	)
);

/* ------------------------------------------------------------------ Queries */

flx_gql_query(
	'flxBackups',
	array(
		'type'        => 'FlxBackupConnection!',
		'description' => __( 'Backups, newest first.', 'flx-backup' ),
		'cap'         => 'manage_options',
		'args'        => flx_gql_connection_args(
			array(
				'type'   => array(
					'type'        => 'FlxBackupType',
					'description' => __( 'Only backups of this type.', 'flx-backup' ),
				),
				'status' => array(
					'type'        => 'FlxBackupStatus',
					'description' => __( 'Only backups in this state.', 'flx-backup' ),
				),
			)
		),
		'resolve'     => /**
		 * @param mixed               $root Root.
		 * @param array<string,mixed> $args Args.
		 */
		static function ( $root, array $args ): array {
			flx_gql_cache_tag( FLXY_Service::CACHE_TAG );
			return flx_gql_paginate(
				$args,
				static function ( int $offset, int $limit ) use ( $args ): array {
					$page = FLXY_Service::backups( flx_gql_str( $args['type'] ?? '' ), flx_gql_str( $args['status'] ?? '' ), $offset, $limit );
					return array(
						'items' => $page['items'],
						'total' => $page['total'],
					);
				}
			);
		},
	)
);

flx_gql_query(
	'flxBackup',
	array(
		'type'        => 'FlxBackup',
		'description' => __( 'One backup; empty when the ID is unknown.', 'flx-backup' ),
		'cap'         => 'manage_options',
		'args'        => array(
			'id' => array(
				'type'        => 'ID!',
				'description' => __( 'Backup ID.', 'flx-backup' ),
			),
		),
		'resolve'     => static function ( $root, array $args ): ?array {
			flx_gql_cache_tag( FLXY_Service::CACHE_TAG );
			return FLXY_Service::backup( flx_gql_str( $args['id'] ?? '' ) );
		},
	)
);

flx_gql_query(
	'flxBackupCounts',
	array(
		'type'        => 'FlxBackupCounts!',
		'description' => __( 'Totals for the filter links and the footer of the backup list.', 'flx-backup' ),
		'cap'         => 'manage_options',
		'resolve'     => static function (): array {
			flx_gql_cache_tag( FLXY_Service::CACHE_TAG );
			$page = FLXY_Service::backups( '', '', 0, 0 );
			return array(
				'all'       => $page['all'],
				'usedBytes' => $page['usedBytes'],
				'usedLabel' => $page['usedLabel'],
				'types'     => $page['counts'],
			);
		},
	)
);

flx_gql_query(
	'flxBackupOverview',
	array(
		'type'        => 'FlxBackupOverview!',
		'description' => __( 'The dashboard: storage, counts, last and next backup, by type, recent backups and events.', 'flx-backup' ),
		'cap'         => 'manage_options',
		'resolve'     => static function (): array {
			flx_gql_cache_tag( FLXY_Service::CACHE_TAG );
			return FLXY_Service::overview();
		},
	)
);

flx_gql_query(
	'flxBackupHistory',
	array(
		'type'        => 'FlxBackupHistory!',
		'description' => __( 'Storage history for the dashboard chart.', 'flx-backup' ),
		'cap'         => 'manage_options',
		'resolve'     => static function (): array {
			flx_gql_cache_tag( FLXY_Service::CACHE_TAG );
			return FLXY_Service::history();
		},
	)
);

flx_gql_query(
	'flxBackupSettings',
	array(
		'type'        => 'FlxBackupSettings!',
		'description' => __( 'Flux Backup settings.', 'flx-backup' ),
		'cap'         => 'manage_options',
		'resolve'     => static function (): array {
			flx_gql_cache_tag( FLXY_Service::CACHE_TAG );
			return FLXY_Service::settings();
		},
	)
);

flx_gql_query(
	'flxBackupHealth',
	array(
		'type'        => 'FlxBackupHealth!',
		'description' => __( 'Server state and the values the upload and start dialogs need.', 'flx-backup' ),
		'cap'         => 'manage_options',
		'resolve'     => static fn(): array => FLXY_Service::health(),
	)
);

flx_gql_query(
	'flxBackupJob',
	array(
		'type'        => 'FlxBackupJob!',
		'description' => __( 'State of the running or just finished backup or restore. Only reads: use flxBackupStep to advance it.', 'flx-backup' ),
		'cap'         => 'manage_options',
		'resolve'     => static fn(): array => FLXY_Service::job(),
	)
);

/* ---------------------------------------------------------------- Mutations */

flx_gql_mutation(
	'flxBackupStart',
	array(
		'description' => __( 'Starts a backup in the background; poll flxBackupJob and call flxBackupStep to keep it moving. Conflict (errors) while another job runs.', 'flx-backup' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'type'        => array(
				'type'        => 'FlxBackupStartType',
				'description' => __( 'What to back up. Default: full.', 'flx-backup' ),
			),
			'label'       => array(
				'type'        => 'String',
				'description' => __( 'Description (up to 120 characters).', 'flx-backup' ),
			),
			'includeCore' => array(
				'type'        => 'Boolean',
				'description' => __( 'Add WordPress core files (full backups only). Default: the setting.', 'flx-backup' ),
			),
			'lock'        => array(
				'type'        => 'Boolean',
				'description' => __( 'Never delete automatically, even when the quota is full.', 'flx-backup' ),
			),
		),
		'output'      => array(
			'job' => array(
				'type'        => 'FlxBackupJob',
				'description' => __( 'The job that was started.', 'flx-backup' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$label = mb_substr( flx_gql_str( $input['label'] ?? '' ), 0, 120 );
			$core  = array_key_exists( 'includeCore', $input ) && null !== $input['includeCore'] ? (bool) $input['includeCore'] : null;
			$job   = FLXY_Service::start_backup( flx_gql_str( $input['type'] ?? 'full', 'full' ), $label, $core, ! empty( $input['lock'] ) );
			return $job instanceof WP_Error ? $job : array( 'job' => $job );
		},
	)
);

flx_gql_mutation(
	'flxBackupRestoreStart',
	array(
		'description' => __( 'Starts restoring the chosen parts of a backup in the background. Overwrites the current database and files; needs the highest permission.', 'flx-backup' ),
		'cap'         => static fn(): bool => flxy_gql_top_cap(),
		'input'       => array(
			'id'       => array(
				'type'        => 'ID!',
				'description' => __( 'Backup ID.', 'flx-backup' ),
			),
			'database' => array(
				'type'        => 'Boolean',
				'description' => __( 'Restore the whole database.', 'flx-backup' ),
			),
			'paths'    => array(
				'type'        => '[String!]',
				'description' => __( 'Paths to restore (the path values of flxBackup.restore.groups); the backup\'s own paths or folders below them.', 'flx-backup' ),
			),
			'safety'   => array(
				'type'        => 'Boolean',
				'description' => __( 'Take a safety backup of the current database first. Default: the setting.', 'flx-backup' ),
			),
		),
		'output'      => array(
			'job' => array(
				'type'        => 'FlxBackupJob',
				'description' => __( 'The job that was started.', 'flx-backup' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$db    = ! empty( $input['database'] );
			$paths = array_values( array_map( 'flx_gql_str', (array) ( $input['paths'] ?? array() ) ) );
			if ( ! $db && ! $paths ) {
				return new WP_Error( 'flxy_nothing', __( 'Select at least one part.', 'flx-backup' ), array( 'status' => 400, 'field' => 'paths' ) );
			}
			$safety = array_key_exists( 'safety', $input ) && null !== $input['safety'] ? (bool) $input['safety'] : null;
			$job    = FLXY_Service::start_restore( flx_gql_str( $input['id'] ?? '' ), $db, $paths, $safety );
			return $job instanceof WP_Error ? $job : array( 'job' => $job );
		},
	)
);

flx_gql_mutation(
	'flxBackupStep',
	array(
		'description' => __( 'Advances the running job by one time budget (about 8 seconds) and returns its state. Does nothing when no job runs.', 'flx-backup' ),
		'cap'         => 'manage_options',
		'input'       => array(),
		'output'      => array(
			'job' => array(
				'type'        => 'FlxBackupJob',
				'description' => __( 'The job after the step.', 'flx-backup' ),
			),
		),
		'resolve'     => static fn(): array => array( 'job' => FLXY_Service::step() ),
	)
);

flx_gql_mutation(
	'flxBackupCancel',
	array(
		'description' => __( 'Cancels the running backup or restore. A step in progress finishes first (pending).', 'flx-backup' ),
		'cap'         => 'manage_options',
		'input'       => array(),
		'output'      => array(
			'pending' => array(
				'type'        => 'Boolean',
				'description' => __( 'A step is running; the job is cancelled when it ends.', 'flx-backup' ),
			),
			'job'     => array(
				'type'        => 'FlxBackupJob',
				'description' => __( 'The job after the cancel.', 'flx-backup' ),
			),
		),
		'resolve'     => static function () {
			$r = FLXY_Service::cancel();
			return $r instanceof WP_Error ? $r : array(
				'pending' => $r['pending'],
				'job'     => FLXY_Service::job(),
			);
		},
	)
);

flx_gql_mutation(
	'flxBackupDelete',
	array(
		'description' => __( 'Permanently deletes backups; a running one is cancelled first. Needs the highest permission.', 'flx-backup' ),
		'cap'         => static fn(): bool => flxy_gql_top_cap(),
		'input'       => array(
			'ids' => array(
				'type'        => '[ID!]!',
				'description' => __( 'Backup IDs.', 'flx-backup' ),
			),
		),
		'output'      => array(
			'deletedIds' => array(
				'type'        => '[ID!]',
				'description' => __( 'IDs that were deleted.', 'flx-backup' ),
			),
			'counts'     => array(
				'type'        => 'FlxBackupCounts',
				'description' => __( 'Totals after the delete.', 'flx-backup' ),
				'resolve'     => static function (): array {
					$page = FLXY_Service::backups( '', '', 0, 0 );
					return array(
						'all'       => $page['all'],
						'usedBytes' => $page['usedBytes'],
						'usedLabel' => $page['usedLabel'],
						'types'     => $page['counts'],
					);
				},
			),
		),
		'resolve'     => static function ( array $input ) {
			$ids     = array_values( array_map( 'flx_gql_str', (array) ( $input['ids'] ?? array() ) ) );
			$deleted = FLXY_Service::delete( $ids );
			return $deleted instanceof WP_Error ? $deleted : array( 'deletedIds' => $deleted );
		},
	)
);

flx_gql_mutation(
	'flxBackupLock',
	array(
		'description' => __( 'Locks a backup so the quota and retention never delete it, or unlocks it.', 'flx-backup' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'id'     => array(
				'type'        => 'ID!',
				'description' => __( 'Backup ID.', 'flx-backup' ),
			),
			'locked' => array(
				'type'        => 'Boolean!',
				'description' => __( 'true locks, false unlocks.', 'flx-backup' ),
			),
		),
		'output'      => array(
			'backup' => array(
				'type'        => 'FlxBackup',
				'description' => __( 'The backup after the change.', 'flx-backup' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXY_Service::set_lock( flx_gql_str( $input['id'] ?? '' ), ! empty( $input['locked'] ) );
			return $r instanceof WP_Error ? $r : array( 'backup' => $r );
		},
	)
);

/**
 * The posted form shape FLXY_Settings::save() reads, from a partial GraphQL input: what is left
 * out keeps its current value, and a checkbox is "on" by being present.
 *
 * @param array<string,mixed> $input Input.
 * @return array<string,mixed>
 */
function flxy_gql_settings_form( array $input ): array {
	$cur  = FLXY_Service::settings();
	$map  = array(
		'quotaGb'           => 'quota_gb',
		'preScope'          => 'pre_scope',
		'preKeepDays'       => 'pre_keep_days',
		'schedule'          => 'schedule',
		'scheduleHour'      => 'schedule_hour',
		'scheduleDay'       => 'schedule_day',
		'scheduleKeep'      => 'schedule_keep',
		'includeCore'       => 'include_core',
		'exclude'           => 'exclude',
		'safetyRestore'     => 'safety_restore',
		'deleteOnUninstall' => 'delete_on_uninstall',
	);
	$bool = array( 'includeCore', 'safetyRestore', 'deleteOnUninstall' );
	$form = array();
	foreach ( $map as $gql => $post ) {
		$v = array_key_exists( $gql, $input ) && null !== $input[ $gql ] ? $input[ $gql ] : $cur[ $gql ];
		if ( in_array( $gql, $bool, true ) ) {
			if ( $v ) {
				$form[ $post ] = '1';
			}
			continue;
		}
		$form[ $post ] = $v;
	}
	return $form;
}

flx_gql_mutation(
	'flxBackupSettingsSave',
	array(
		'description' => __( 'Saves Flux Backup settings. Fields you leave out stay as they are; values are limited to their range.', 'flx-backup' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'quotaGb'           => array(
				'type'        => 'Float',
				'description' => __( 'Storage quota in GB (0.1–10000).', 'flx-backup' ),
			),
			'preScope'          => array(
				'type'        => 'FlxBackupPreScope',
				'description' => __( 'Which updates are backed up first.', 'flx-backup' ),
			),
			'preKeepDays'       => array(
				'type'        => 'Int',
				'description' => __( 'Days a pre-update backup is kept (1–365).', 'flx-backup' ),
			),
			'schedule'          => array(
				'type'        => 'FlxBackupSchedule',
				'description' => __( 'Scheduled full backup frequency.', 'flx-backup' ),
			),
			'scheduleHour'      => array(
				'type'        => 'Int',
				'description' => __( 'Hour of the day (0–23) in the site\'s time zone.', 'flx-backup' ),
			),
			'scheduleDay'       => array(
				'type'        => 'Int',
				'description' => __( 'Day of the week for weekly backups: 0 = Sunday … 6 = Saturday.', 'flx-backup' ),
			),
			'scheduleKeep'      => array(
				'type'        => 'Int',
				'description' => __( 'How many scheduled backups are kept (1–100).', 'flx-backup' ),
			),
			'includeCore'       => array(
				'type'        => 'Boolean',
				'description' => __( 'Add WordPress core files to full backups.', 'flx-backup' ),
			),
			'exclude'           => array(
				'type'        => 'String',
				'description' => __( 'Extra paths left out, one per line, relative to the WordPress root folder; * works as a wildcard.', 'flx-backup' ),
			),
			'safetyRestore'     => array(
				'type'        => 'Boolean',
				'description' => __( 'Take a safety backup of the database before restoring it.', 'flx-backup' ),
			),
			'deleteOnUninstall' => array(
				'type'        => 'Boolean',
				'description' => __( 'Delete backup files when the plugin is deleted.', 'flx-backup' ),
			),
		),
		'output'      => array(
			'settings' => array(
				'type'        => 'FlxBackupSettings',
				'description' => __( 'The settings after the change.', 'flx-backup' ),
			),
		),
		'resolve'     => /**
		 * @param array<string,mixed> $input Input.
		 */
		static fn( array $input ): array => array( 'settings' => FLXY_Service::save_settings( flxy_gql_settings_form( $input ) ) ),
	)
);
