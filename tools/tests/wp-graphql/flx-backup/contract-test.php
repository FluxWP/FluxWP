<?php
/**
 * Flux Backup GraphQL contract tests. The documents are the persisted operations the Backups screen
 * sends (plugins/flx-backup/graphql/persisted-queries.json). The classic AJAX handlers and GraphQL
 * share FLXY_Service, so the classic behaviour is checked through the service too. The restore test
 * works on a throw-away folder (wp-content/flxy-test), never on the site itself.
 *
 *   wp eval-file tools/tests/wp-graphql/flx-backup/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';

echo "Flux Backup GraphQL\n";

$GLOBALS['flxy_ops'] = array();
foreach ( (array) json_decode( (string) file_get_contents( FLXY_DIR . 'graphql/persisted-queries.json' ), true ) as $doc ) {
	if ( preg_match( '/^(?:query|mutation) (Flx\w+)/', $doc, $m ) ) {
		$GLOBALS['flxy_ops'][ $m[1] ] = $doc;
	}
}
flxt_same( 12, count( $GLOBALS['flxy_ops'] ), 'Flux Backup ships 12 persisted operations' );

function flxy_t_op( string $name, array $vars = array(), int $user = 0 ): array {
	return flxt_gql( $GLOBALS['flxy_ops'][ $name ], $vars, $user );
}

/** Steps the job until it ends (or 60 steps). */
function flxy_t_finish( int $user ): array {
	$job = array();
	for ( $i = 0; $i < 60; $i++ ) {
		$job = flxy_t_op( 'FlxBackupStep', array(), $user )['data']['flxBackupStep']['job'] ?? array();
		if ( ! in_array( $job['status'] ?? '', array( 'RUNNING' ), true ) ) {
			break;
		}
	}
	return $job;
}

$admin = 1;
$sub   = (int) wp_insert_user( array( 'user_login' => 'flxy_sub_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );

// Clean slate: no backups, no job.
foreach ( FLXY_Storage::records() as $r ) {
	FLXY_Storage::delete( $r['id'] );
}
FLXY_Runner::save_backup( null );
FLXY_Runner::save_restore( null );

$dir = WP_CONTENT_DIR . '/flxy-test';
wp_mkdir_p( $dir );
file_put_contents( $dir . '/a.txt', 'original' );

// Permissions.
foreach ( array( 'FlxBackupDashboard', 'FlxBackupList', 'FlxBackupSettingsScreen', 'FlxBackupJobRead' ) as $op ) {
	flxt_same( 'UNAUTHENTICATED', flxt_code( flxy_t_op( $op ) ), "$op: visitor → UNAUTHENTICATED" );
	flxt_same( 'FORBIDDEN', flxt_code( flxy_t_op( $op, array(), $sub ) ), "$op: subscriber → FORBIDDEN" );
}
$r = flxy_t_op( 'FlxBackupStart', array( 'input' => array( 'type' => 'FILES' ) ), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'start: subscriber → FORBIDDEN' );
$r = flxy_t_op( 'FlxBackupDelete', array( 'input' => array( 'ids' => array( 'x' ) ) ), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'delete: subscriber → FORBIDDEN' );
$r = flxy_t_op( 'FlxBackupRestoreStart', array( 'input' => array( 'id' => 'x', 'database' => true ) ), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'restore: subscriber → FORBIDDEN' );

// Empty state.
$r = flxy_t_op( 'FlxBackupDashboard', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'dashboard: no errors' );
$o = $r['data']['flxBackupOverview'] ?? array();
flxt_same( array( 0, 0, null, 'OK' ), array( $o['backupCount'] ?? null, $o['lockedCount'] ?? null, array_key_exists( 'last', $o ) ? $o['last'] : 0, strtoupper( (string) ( $o['level'] ?? '' ) ) ), 'dashboard: empty (no backups, no last, level ok)' );
flxt_same( 'OFF', $o['scheduleKind'] ?? null, 'dashboard: schedule off by default' );
flxt_same( null, array_key_exists( 'nextRun', $o ) ? $o['nextRun'] : 0, 'dashboard: no next run while the schedule is off' );
$h = $r['data']['flxBackupHistory'] ?? array();
flxt_same( 180, count( $h['days'] ?? array() ), 'history: 180 days' );
flxt_same( array( 'full', 'pre_update', 'db', 'files', 'manual' ), array_column( $h['series'] ?? array(), 'key' ), 'history: series in the stored type order' );
flxt_same( array( 30, 90, 180 ), array_column( $h['ranges'] ?? array(), 'days' ), 'history: three ranges' );
flxt_same( count( $h['series'] ?? array() ), count( $h['days'][179]['values'] ?? array() ), 'history: one value per series' );
$health = $r['data']['flxBackupHealth'] ?? array();
flxt_same( array( true, true ), array( $health['zipAvailable'] ?? null, $health['writable'] ?? null ), 'health: zip available, folder writable' );
flxt_ok( '' !== ( $health['nonce'] ?? '' ) && str_ends_with( (string) ( $health['ajaxUrl'] ?? '' ), 'admin-ajax.php' ), 'health: upload nonce + admin-ajax address' );
$r = flxy_t_op( 'FlxBackupList', array(), $admin );
flxt_same( array( array(), 0 ), array( $r['data']['flxBackups']['nodes'] ?? null, $r['data']['flxBackups']['totalCount'] ?? null ), 'list: empty' );
flxt_same( 'IDLE', flxy_t_op( 'FlxBackupJobRead', array(), $admin )['data']['flxBackupJob']['status'] ?? null, 'job: idle' );
$r = flxy_t_op( 'FlxBackupStep', array(), $admin );
flxt_same( 'IDLE', $r['data']['flxBackupStep']['job']['status'] ?? null, 'step: nothing to do while idle' );
$r = flxy_t_op( 'FlxBackupCancel', array(), $admin );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'cancel: no job → NOT_FOUND' );

// Settings: partial update, limits, parity with FLXY_Settings.
$r = flxy_t_op( 'FlxBackupSettingsScreen', array(), $admin );
$s = $r['data']['flxBackupSettings'] ?? array();
flxt_same( array( 10.0, 'ALL', 7, 'OFF', 4, true ), array( $s['quotaGb'] ?? null, $s['preScope'] ?? null, $s['preKeepDays'] ?? null, $s['schedule'] ?? null, $s['scheduleKeep'] ?? null, $s['safetyRestore'] ?? null ), 'settings: defaults' );
$r = flxy_t_op( 'FlxBackupSettingsSave', array( 'input' => array( 'quotaGb' => 2.5, 'schedule' => 'DAILY', 'scheduleHour' => 99, 'preScope' => 'FLX', 'exclude' => "wp-content/uploads/big\n../etc\n/wp-content/cache/" ) ), $admin );
$s = $r['data']['flxBackupSettingsSave']['settings'] ?? array();
flxt_same( array( 2.5, 'DAILY', 23, 'FLX' ), array( $s['quotaGb'] ?? null, $s['schedule'] ?? null, $s['scheduleHour'] ?? null, $s['preScope'] ?? null ), 'settings save: values stored, hour limited to 23' );
flxt_same( "wp-content/uploads/big\nwp-content/cache", $s['exclude'] ?? null, 'settings save: exclude list cleaned (no .., no leading slash)' );
flxt_same( array( true, 7, 4 ), array( $s['safetyRestore'] ?? null, $s['preKeepDays'] ?? null, $s['scheduleKeep'] ?? null ), 'settings save: fields left out keep their value (checkbox stays on)' );
flxt_same( 'daily', FLXY_Settings::get( 'schedule' ), 'settings save: FLXY_Settings sees it (classic page reads the same option)' );
flxt_ok( FLXY_Cron::next_scheduled() > time() - 60, 'settings save: the schedule is planned' );
$r = flxy_t_op( 'FlxBackupSettingsSave', array( 'input' => array( 'safetyRestore' => false, 'schedule' => 'OFF', 'quotaGb' => 10 ) ), $admin );
$s = $r['data']['flxBackupSettingsSave']['settings'] ?? array();
flxt_same( array( false, 'OFF', 10.0 ), array( $s['safetyRestore'] ?? null, $s['schedule'] ?? null, $s['quotaGb'] ?? null ), 'settings save: a checkbox turns off when false' );
FLXY_Settings::save( FLXY_Settings::defaults() );

// Start a backup, step it to the end.
$r = flxy_t_op( 'FlxBackupStart', array( 'input' => array( 'type' => 'FILES', 'label' => 'Test files' ) ), $admin );
flxt_same( null, $r['errors'] ?? null, 'start: no errors' );
$job = $r['data']['flxBackupStart']['job'] ?? array();
flxt_same( array( 'RUNNING', 'BACKUP' ), array( $job['status'] ?? null, $job['kind'] ?? null ), 'start: a backup job is running' );
flxt_ok( strlen( (string) ( $job['token'] ?? '' ) ) >= 16, 'start: job carries the token for the admin-ajax fallback' );
$r = flxy_t_op( 'FlxBackupStart', array( 'input' => array( 'type' => 'DB' ) ), $admin );
flxt_same( null, $r['data']['flxBackupStart']['job'] ?? null, 'start while busy: no job' );
flxt_same( 'CONFLICT', $r['data']['flxBackupStart']['errors'][0]['code'] ?? null, 'start while busy: CONFLICT in errors' );
$running = flxy_t_op( 'FlxBackupList', array(), $admin )['data']['flxBackups']['nodes'][0] ?? array();
flxt_same( array( 'RUNNING', true ), array( $running['status'] ?? null, $running['running'] ?? null ), 'list: the running backup is listed' );
$job = flxy_t_finish( $admin );
flxt_same( array( 'DONE', 'BACKUP', 100 ), array( $job['status'] ?? null, $job['kind'] ?? null, $job['percent'] ?? null ), 'step: backup finishes at 100%', $job );
$list = flxy_t_op( 'FlxBackupList', array(), $admin )['data'] ?? array();
$b    = $list['flxBackups']['nodes'][0] ?? array();
flxt_same( array( 'FILES', 'OK', 'Test files', false, 'QUOTA', false ), array( $b['type'] ?? null, $b['status'] ?? null, $b['title'] ?? null, $b['locked'] ?? null, $b['keepKind'] ?? null, $b['running'] ?? null ), 'list: finished backup row' );
flxt_ok( '' !== ( $b['sizeLabel'] ?? '' ) && str_contains( (string) ( $b['downloadUrl'] ?? '' ), 'action=flxy_download' ) && str_contains( (string) ( $b['downloadUrl'] ?? '' ), '_wpnonce=' ), 'list: size and signed download link' );
flxt_same( false, $b['restore']['database'] ?? null, 'restore options: files backup has no database' );
$items = array();
foreach ( $b['restore']['groups'] ?? array() as $g ) {
	foreach ( $g['items'] as $it ) {
		$items[] = $g['key'] . ':' . $it['path'];
	}
}
flxt_ok( in_array( 'other:wp-content/flxy-test', $items, true ) && in_array( 'other:wp-content', $items, true ), 'restore options: the folder and wp-content are offered', $items );
flxt_same( 1, $list['flxBackupCounts']['all'] ?? null, 'counts: one backup' );
flxt_same( array( array( 'type' => 'FILES', 'label' => 'Files', 'count' => 1 ) ), $list['flxBackupCounts']['types'] ?? null, 'counts: by type' );
$id = (string) ( $b['id'] ?? '' );
flxt_same( $id, FLXY_Storage::get( $id )['id'] ?? null, 'service: the record is in the storage the classic page reads' );

// Filters and paging.
$db = flxy_backup_create( array( 'type' => 'db', 'sync' => true ) );
$dbid = is_array( $db ) ? $db['id'] : '';
$r = flxy_t_op( 'FlxBackupList', array( 'type' => 'DB' ), $admin );
flxt_same( array( $dbid ), array_column( $r['data']['flxBackups']['nodes'] ?? array(), 'id' ), 'list: type filter' );
$r = flxy_t_op( 'FlxBackupList', array( 'first' => 1 ), $admin );
$first_id = $r['data']['flxBackups']['nodes'][0]['id'] ?? '';
flxt_same( array( true, 2 ), array( $r['data']['flxBackups']['pageInfo']['hasNextPage'] ?? null, $r['data']['flxBackups']['totalCount'] ?? null ), 'list: paging (1 of 2)' );
$r = flxy_t_op( 'FlxBackupList', array( 'first' => 1, 'after' => $r['data']['flxBackups']['pageInfo']['endCursor'] ?? null ), $admin );
$second = array_column( $r['data']['flxBackups']['nodes'] ?? array(), 'id' );
flxt_same( array( true, 1 ), array( ! in_array( $first_id, $second, true ) && in_array( $id, array_merge( array( $first_id ), $second ), true ), count( $second ) ), 'list: the second page holds the other backup' );
$r = flxy_t_op( 'FlxBackupList', array( 'status' => 'FAILED' ), $admin );
flxt_same( array(), $r['data']['flxBackups']['nodes'] ?? null, 'list: status filter (none failed)' );
$restore = FLXY_Service::restore_for( $dbid );
flxt_same( true, $restore['database'] ?? null, 'restore options: database backup offers the database' );

// Detail with breakdown.
$r = flxy_t_op( 'FlxBackupDetail', array( 'id' => $id ), $admin );
$d = $r['data']['flxBackup'] ?? array();
flxt_same( null, $r['errors'] ?? null, 'detail: no errors' );
flxt_ok( ( $d['breakdown']['raw'] ?? 0 ) > 0 && ! empty( $d['breakdown']['categories'] ), 'detail: size breakdown', $d['breakdown'] ?? null );
flxt_same( false, $d['breakdown']['categories'][0]['isDatabase'] ?? null, 'detail: a files backup has no database section' );
$r = flxy_t_op( 'FlxBackupDetail', array( 'id' => 'no-such-id' ), $admin );
flxt_same( array( null, null ), array( array_key_exists( 'flxBackup', $r['data'] ?? array() ) ? $r['data']['flxBackup'] : 0, $r['errors'] ?? null ), 'detail: unknown id → null' );
$bd = flxy_t_op( 'FlxBackupDetail', array( 'id' => $dbid ), $admin )['data']['flxBackup']['breakdown']['categories'][0] ?? array();
flxt_same( array( 'db', true ), array( $bd['key'] ?? null, $bd['isDatabase'] ?? null ), 'detail: database backup counts rows, not files' );

// Lock.
$r = flxy_t_op( 'FlxBackupLock', array( 'input' => array( 'id' => $id, 'locked' => true ) ), $admin );
flxt_same( array( true, 'LOCKED' ), array( $r['data']['flxBackupLock']['backup']['locked'] ?? null, $r['data']['flxBackupLock']['backup']['keepKind'] ?? null ), 'lock: locked' );
flxt_same( true, FLXY_Storage::get( $id )['locked'] ?? null, 'lock: the classic page sees the lock' );
$r = flxy_t_op( 'FlxBackupDashboard', array(), $admin );
flxt_same( array( 2, 1 ), array( $r['data']['flxBackupOverview']['backupCount'] ?? null, $r['data']['flxBackupOverview']['lockedCount'] ?? null ), 'dashboard: counts 2 backups, 1 locked' );
flxt_same( array( 'DB', 'FILES' ), array_column( $r['data']['flxBackupOverview']['byType'] ?? array(), 'type' ), 'dashboard: storage by type (label order)' );
flxt_same( 2, count( $r['data']['flxBackupOverview']['recent'] ?? array() ), 'dashboard: recent backups' );
$r = flxy_t_op( 'FlxBackupLock', array( 'input' => array( 'id' => $id, 'locked' => false ) ), $admin );
flxt_same( 'QUOTA', $r['data']['flxBackupLock']['backup']['keepKind'] ?? null, 'lock: unlocked' );
$r = flxy_t_op( 'FlxBackupLock', array( 'input' => array( 'id' => 'nope', 'locked' => true ) ), $admin );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'lock: unknown id → NOT_FOUND' );

// Restore the throw-away folder.
file_put_contents( $dir . '/a.txt', 'changed' );
file_put_contents( $dir . '/extra.txt', 'new' );
$r = flxy_t_op( 'FlxBackupRestoreStart', array( 'input' => array( 'id' => $id ) ), $admin );
flxt_same( 'INVALID_INPUT', $r['data']['flxBackupRestoreStart']['errors'][0]['code'] ?? null, 'restore: nothing selected → INVALID_INPUT' );
flxt_same( 'paths', $r['data']['flxBackupRestoreStart']['errors'][0]['field'] ?? null, 'restore: the error belongs to the paths field' );
$r = flxy_t_op( 'FlxBackupRestoreStart', array( 'input' => array( 'id' => 'nope', 'paths' => array( 'wp-content/flxy-test' ) ) ), $admin );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'restore: unknown backup → NOT_FOUND' );
$r = flxy_t_op( 'FlxBackupRestoreStart', array( 'input' => array( 'id' => $id, 'paths' => array( 'wp-content/flxy-test' ), 'safety' => false ) ), $admin );
$job = $r['data']['flxBackupRestoreStart']['job'] ?? array();
flxt_same( array( 'RUNNING', 'RESTORE' ), array( $job['status'] ?? null, $job['kind'] ?? null ), 'restore: a restore job is running', $r );
$job = flxy_t_finish( $admin );
flxt_same( array( 'DONE', 'RESTORE' ), array( $job['status'] ?? null, $job['kind'] ?? null ), 'restore: finishes', $job );
flxt_ok( '' !== ( $job['result'] ?? '' ), 'restore: result text' );
flxt_same( 'original', (string) file_get_contents( $dir . '/a.txt' ), 'restore: the file is back to its backed up content' );
FLXY_Runner::save_restore( null );

// Cancel a running backup.
$r = flxy_t_op( 'FlxBackupStart', array( 'input' => array( 'type' => 'FILES' ) ), $admin );
$r = flxy_t_op( 'FlxBackupCancel', array(), $admin );
flxt_same( array( 'FAILED', true ), array( $r['data']['flxBackupCancel']['job']['status'] ?? null, $r['data']['flxBackupCancel']['job']['cancelled'] ?? null ), 'cancel: the job is cancelled' );
flxy_t_op( 'FlxBackupDelete', array( 'input' => array( 'ids' => array_column( FLXY_Storage::records(), 'id' ) ) ), $admin );
FLXY_Runner::save_backup( null );
$r = flxy_t_op( 'FlxBackupList', array(), $admin );
flxt_same( 0, $r['data']['flxBackups']['totalCount'] ?? null, 'delete: everything gone' );

// Delete.
$a = flxy_backup_create( array( 'type' => 'files', 'paths' => array( 'wp-content/flxy-test' ), 'sync' => true ) );
$b2 = flxy_backup_create( array( 'type' => 'files', 'paths' => array( 'wp-content/flxy-test' ), 'sync' => true ) );
$r = flxy_t_op( 'FlxBackupDelete', array( 'input' => array( 'ids' => array( $a['id'], 'nope' ) ) ), $admin );
flxt_same( array( $a['id'] ), $r['data']['flxBackupDelete']['deletedIds'] ?? null, 'delete: returns the ids that were deleted' );
flxt_same( 1, $r['data']['flxBackupDelete']['counts']['all'] ?? null, 'delete: counts after the delete' );
flxt_same( null, FLXY_Storage::get( $a['id'] ), 'delete: removed from the storage' );
flxt_ok( ! file_exists( (string) $a['file'] ), 'delete: the zip is gone' );
$r = flxy_t_op( 'FlxBackupDelete', array( 'input' => array( 'ids' => array( 'nope' ) ) ), $admin );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'delete: nothing deleted → NOT_FOUND' );
FLXY_Service::delete( array( $b2['id'] ) );

// The classic handlers share the service: a failed record shows its error.
FLXY_Storage::put( FLXY_Storage::normalize( array( 'id' => 'failed-1', 'type' => 'full', 'status' => 'failed', 'created' => time() - 100, 'error' => 'Disk full', 'context' => array( 'source' => 'schedule' ) ) ) );
$r = flxy_t_op( 'FlxBackupList', array( 'status' => 'FAILED' ), $admin );
$f = $r['data']['flxBackups']['nodes'][0] ?? array();
flxt_same( array( 'failed-1', 'FAILED', 'Disk full', 'schedule', '' ), array( $f['id'] ?? null, $f['status'] ?? null, $f['error'] ?? null, $f['source'] ?? null, $f['downloadUrl'] ?? null ), 'list: failed backup shows the error and has no download' );
flxt_same( null, array_key_exists( 'restore', $f ) ? $f['restore'] : 0, 'list: failed backup has no restore options' );
FLXY_Storage::delete( 'failed-1' );

// Pre-update backup naming and packages.
$pre = flxy_backup_create( array( 'type' => 'pre_update', 'paths' => array( 'wp-content/flxy-test' ), 'sync' => true, 'context' => array( 'source' => 'update', 'packages' => array( array( 'slug' => 'flx-demo', 'name' => 'Flux Demo', 'type' => 'plugin', 'from_version' => '1.0', 'to_version' => '1.1' ) ) ) ) );
$f   = flxy_t_op( 'FlxBackupDetail', array( 'id' => is_array( $pre ) ? $pre['id'] : '' ), $admin )['data']['flxBackup'] ?? array();
flxt_same( 'Flux Demo 1.0 → 1.1', $f['packages'][0]['label'] ?? null, 'detail: pre-update package line' );
flxt_same( array( 'UPDATE', true ), array( strtoupper( (string) ( $f['source'] ?? '' ) ), str_contains( (string) ( $f['title'] ?? '' ), 'Flux Demo' ) ), 'detail: pre-update source and a title that names the package' );

// Cleanup.
FLXY_Storage::sync();
foreach ( FLXY_Storage::records() as $r ) {
	FLXY_Storage::delete( $r['id'] );
}
FLXY_Runner::save_backup( null );
FLXY_Runner::save_restore( null );
foreach ( (array) glob( $dir . '/*' ) as $f ) {
	wp_delete_file( $f );
}
@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

flxt_done();
