<?php
/**
 * Çalışma alanı (0.13.0): her sohbetin ve zamanlanmış görevin Flux Files'ta bir klasörü olur,
 * AI'ın dosya değişiklikleri "mini git" gibi kaydedilir.
 *
 * - Kök: wp-content/flx-ai (filtre flxai_workspace_root), web'e kapalı. Sohbet: chats/<id>, görev: tasks/<id>-<ad>,
 *   skill'ler: .skills/<ad>/SKILL.md.
 * - Dosya işleri Flux Files araçlarıyla (flxfm_*) yapılır; Flux AI kendi dosya okuma/yazma aracını getirmez.
 *   Flux Files kurulu değilse çalışma alanı kapalıdır.
 * - Kendi klasöründe AI doğrudan yazar; her tur bir commit (eklendi/değişti/silindi + fark).
 * - Klasör dışındaki (site) dosya değişiklikleri hemen uygulanmaz: sohbetin açık değişiklik isteğinde (PR) birikir,
 *   kullanıcı farkı görüp Birleştir ya da Reddet der. AI okurken PR'deki halini görür.
 * - Erişim: "workspace" (varsayılan: yalnız kendi klasörü + kullanıcının izin verdiği klasörler) ya da "site"
 *   (tüm site okunur). Kendi klasörü dışındaki her değişiklik PR olur.
 * - Sert sınırlar PHP'de, her araç çağrısında uygulanır (model talimatı değil): başka sohbet/görev klasörleri ve
 *   .skills yazımı her durumda kapalı; yol temizleme ve kök dışı sembolik bağ reddi Flux Files'ta; gizli/çekirdek
 *   dosyalar kapalı; yazma başına 1 MB, tur/PR başına 50 dosya (filtreler flxai_workspace_max_write / _max_files).
 *
 * Kayıt: flxai_changes tablosu (kind commit|pr, status done|open|merged|rejected|partial, files JSON).
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** Fark için saklanan en büyük içerik (bayt). Daha büyük dosyalarda yalnız özet tutulur. */
const FLXAI_WS_MAX_DIFF = 400000;

/** AI'ın bir seferde yazabileceği en büyük dosya (bayt). */
function flxai_ws_max_write() {
	return max( 1024, (int) apply_filters( 'flxai_workspace_max_write', 1048576 ) );
}

/** Bir turda (commit) ya da bir değişiklik isteğinde en fazla dosya. */
function flxai_ws_max_files() {
	return max( 1, (int) apply_filters( 'flxai_workspace_max_files', 50 ) );
}

/** Flux Files hazır mı? */
function flxai_ws_ready() {
	return class_exists( 'FLXFM_FS' ) && function_exists( 'flxfm_clean' ) && function_exists( 'flxfm_tool_call' );
}

/** Çalışma alanı kökü (site köküne göre). */
function flxai_ws_root() {
	$r = trim( str_replace( '\\', '/', (string) apply_filters( 'flxai_workspace_root', 'wp-content/flx-ai' ) ), '/' );
	return '' !== $r && false === strpos( $r, '..' ) ? $r : 'wp-content/flx-ai';
}

function flxai_ws_skills_dir() {
	return flxai_ws_root() . '/.skills';
}

/** Site kökü (Flux Files kökü). */
function flxai_ws_base() {
	return function_exists( 'flxfm_root' ) ? untrailingslashit( flxfm_root() ) : untrailingslashit( ABSPATH );
}

function flxai_ws_abs( $rel ) {
	return flxai_ws_base() . '/' . ltrim( (string) $rel, '/' );
}

/** Göreli yolu temizler (Flux Files kuralları); geçersizse ''. */
function flxai_ws_clean( $rel ) {
	$rel = trim( str_replace( '\\', '/', (string) $rel ), '/' );
	if ( function_exists( 'flxfm_clean' ) ) {
		$c = flxfm_clean( $rel );
		return is_wp_error( $c ) ? '' : (string) $c;
	}
	return false === strpos( '/' . $rel . '/', '/../' ) ? $rel : '';
}

/** $rel, $dir'in içinde mi (kendisi dahil)? */
function flxai_ws_inside( $rel, $dir ) {
	$dir = trim( (string) $dir, '/' );
	return '' !== $dir && ( $rel === $dir || 0 === strpos( $rel . '/', $dir . '/' ) );
}

/** Kök klasörü kurar ve web erişimini kapatır; $rel verilirse onu da (kökün içindeyse) oluşturur. */
function flxai_ws_mkdir( $rel = '' ) {
	$root = flxai_ws_abs( flxai_ws_root() );
	if ( ! is_dir( $root ) ) {
		wp_mkdir_p( $root );
	}
	if ( is_dir( $root ) && ! file_exists( $root . '/.htaccess' ) ) {
		@file_put_contents( $root . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore
		@file_put_contents( $root . '/index.php', "<?php // Silence.\n" ); // phpcs:ignore
	}
	$rel = flxai_ws_clean( $rel );
	if ( '' !== $rel && flxai_ws_inside( $rel, flxai_ws_root() ) && ! is_dir( flxai_ws_abs( $rel ) ) ) {
		wp_mkdir_p( flxai_ws_abs( $rel ) );
	}
	return '' === $rel || is_dir( flxai_ws_abs( $rel ) );
}

/** Dosyayı okur (yoksa null). Flux Files varsa onun üzerinden. */
function flxai_ws_read( $rel ) {
	$rel = flxai_ws_clean( $rel );
	if ( '' === $rel || ! is_file( flxai_ws_abs( $rel ) ) ) {
		return null;
	}
	if ( flxai_ws_ready() ) {
		$r = FLXFM_FS::read( $rel );
		return is_wp_error( $r ) ? null : (string) $r['content'];
	}
	return (string) file_get_contents( flxai_ws_abs( $rel ) ); // phpcs:ignore
}

/**
 * Çalışma alanına (kök içine) dosya yazar: Flux Files varsa sürüm + etkinlik kaydıyla.
 *
 * @return true|WP_Error
 */
function flxai_ws_write( $rel, $content ) {
	$rel = flxai_ws_clean( $rel );
	if ( '' === $rel || ! flxai_ws_inside( $rel, flxai_ws_root() ) ) {
		return new WP_Error( 'flxai_ws_path', __( 'Invalid path.', 'flx-ai' ) );
	}
	flxai_ws_mkdir( dirname( $rel ) );
	if ( flxai_ws_ready() ) {
		$r = FLXFM_FS::write( $rel, (string) $content );
		return is_wp_error( $r ) ? $r : true;
	}
	return false === file_put_contents( flxai_ws_abs( $rel ), (string) $content ) // phpcs:ignore
		? new WP_Error( 'flxai_ws_write', __( 'The file could not be written (permissions or disk space).', 'flx-ai' ) )
		: true;
}

/** Çalışma alanındaki dosya/klasörü kaldırır (Flux Files varsa çöpe). */
function flxai_ws_remove( $rel ) {
	$rel = flxai_ws_clean( $rel );
	$abs = flxai_ws_abs( $rel );
	if ( '' === $rel || ! flxai_ws_inside( $rel, flxai_ws_root() ) || ! file_exists( $abs ) ) {
		return false;
	}
	if ( flxai_ws_ready() ) {
		return ! is_wp_error( FLXFM_FS::trash( $rel ) );
	}
	if ( is_dir( $abs ) ) {
		foreach ( array_diff( (array) scandir( $abs ), array( '.', '..', '' ) ) as $n ) {
			$f = $abs . '/' . $n;
			is_dir( $f ) ? flxai_ws_remove( substr( $f, strlen( flxai_ws_base() ) + 1 ) ) : wp_delete_file( $f );
		}
		return @rmdir( $abs ); // phpcs:ignore
	}
	wp_delete_file( $abs );
	return ! file_exists( $abs );
}

/* ---------- Sohbetin / görevin klasörü ve erişimi ---------- */

/** Görevin klasör adı: tasks/<id>-<başlık>. İlk kullanımda göreve yazılır, başlık değişse de aynı kalır. */
/**
 * Klasör adı için benzersiz, tahmin edilemez 13 haneli numara (sıra numarası görünmez).
 * Sitede bir kez üretilen gizli anahtarla türetilir; saklamaya gerek yoktur, aynı sohbet hep aynı adı alır.
 */
function flxai_ws_uid( $kind, $id ) {
	$key = (string) get_option( 'flxai_ws_key', '' );
	if ( '' === $key ) {
		$key = wp_generate_password( 40, false, false );
		add_option( 'flxai_ws_key', $key, '', false );
		$key = (string) get_option( 'flxai_ws_key', $key );
	}
	$h = hash_hmac( 'sha256', $kind . ':' . (int) $id, $key );
	return (string) ( 1000000000000 + ( hexdec( substr( $h, 0, 12 ) ) % 9000000000000 ) );
}

/** Eski sıra numaralı klasör (0.13.0) varsa bir kez yeni adına taşınır. */
function flxai_ws_adopt( $old, $new ) {
	if ( $old !== $new && is_dir( flxai_ws_abs( $old ) ) && ! file_exists( flxai_ws_abs( $new ) ) ) {
		@rename( flxai_ws_abs( $old ), flxai_ws_abs( $new ) ); // phpcs:ignore
	}
}

/** Sohbetin klasörü: chats/<benzersiz numara>. */
function flxai_ws_chat_dir( $chat_id ) {
	$d = flxai_ws_root() . '/chats/' . flxai_ws_uid( 'chat', $chat_id );
	flxai_ws_adopt( flxai_ws_root() . '/chats/' . (int) $chat_id, $d );
	return $d;
}

function flxai_ws_task_dir( array $task ) {
	$d = (string) ( $task['meta']['ws']['dir'] ?? '' );
	$n = flxai_ws_root() . '/tasks/' . flxai_ws_uid( 'task', $task['id'] );
	if ( $d === $n ) {
		return $d;
	}
	if ( '' !== $d ) {
		flxai_ws_adopt( $d, $n );
	}
	$d    = $n;
	$meta = (array) $task['meta'];
	$meta['ws']        = (array) ( $meta['ws'] ?? array() );
	$meta['ws']['dir'] = $d;
	if ( function_exists( 'flxai_task_patch' ) ) {
		flxai_task_patch( (int) $task['id'], array( 'meta' => $meta ) );
	}
	return $d;
}

/** Erişim ayarını temizler. */
function flxai_ws_clean_cfg( $in ) {
	$in      = is_array( $in ) ? $in : array();
	$folders = $in['folders'] ?? array();
	if ( is_string( $folders ) ) {
		$folders = preg_split( '/[\r\n,]+/', $folders );
	}
	$out = array();
	foreach ( (array) $folders as $f ) {
		$f = flxai_ws_clean( (string) $f );
		if ( '' !== $f && ! in_array( $f, $out, true ) ) {
			$out[] = $f;
		}
	}
	return array(
		'access'  => in_array( $in['access'] ?? '', array( 'site', 'workspace' ), true ) ? $in['access'] : '',
		'folders' => array_slice( $out, 0, 20 ),
	);
}

/**
 * Sohbetin çalışma alanı kuralları.
 *
 * @return array dir, access (site|workspace), folders[], task (id ya da 0), ready
 */
function flxai_ws_policy( array $chat ) {
	static $cache = array();
	$cid = (int) $chat['id'];
	if ( isset( $cache[ $cid ] ) ) {
		return $cache[ $cid ];
	}
	$task = function_exists( 'flxai_task_by_chat' ) ? flxai_task_by_chat( $cid ) : null;
	if ( $task ) {
		$cfg = flxai_ws_clean_cfg( $task['meta']['ws'] ?? array() );
		$dir = flxai_ws_task_dir( $task );
		$def = 'workspace';
	} else {
		$cfg = flxai_ws_clean_cfg( $chat['state']['ws'] ?? array() );
		$dir = flxai_ws_chat_dir( $cid );
		$def = 'workspace';
	}
	$p = array(
		'dir'     => $dir,
		'access'  => '' !== $cfg['access'] ? $cfg['access'] : ( 'site' === apply_filters( 'flxai_workspace_default_access', $def, $task ) ? 'site' : 'workspace' ),
		'folders' => $cfg['folders'],
		'task'    => $task ? (int) $task['id'] : 0,
		'ready'   => flxai_ws_ready(),
	);
	$cache[ $cid ] = $p;
	return $p;
}

/** Yol için izin: own (kendi klasörü, doğrudan), pr (değişiklik isteği), read (yalnız okunur), deny. */
function flxai_ws_kind( $rel, array $p, $write = true ) {
	if ( flxai_ws_inside( $rel, $p['dir'] ) ) {
		return 'own';
	}
	// Çalışma alanı kökü: başka sohbet/görev klasörleri her durumda kapalı; skill'ler yalnız okunur
	// (değiştirmek için flxai_skill_save). Erişim ayarı ve izinli klasörler bunu açamaz.
	if ( flxai_ws_inside( $rel, flxai_ws_root() ) || ( $write && flxai_ws_inside( flxai_ws_root(), $rel ) ) ) {
		return ! $write && flxai_ws_inside( $rel, flxai_ws_skills_dir() ) ? 'read' : 'deny';
	}
	$in_folder = false;
	foreach ( $p['folders'] as $f ) {
		if ( flxai_ws_inside( $rel, $f ) ) {
			$in_folder = true;
			break;
		}
	}
	if ( 'site' === $p['access'] || $in_folder ) {
		return $write ? 'pr' : 'read';
	}
	if ( ! $write && flxai_ws_inside( $rel, flxai_ws_skills_dir() ) ) {
		return 'read';
	}
	return 'deny';
}

function flxai_ws_denied( array $p ) {
	/* translators: %s: folder path */
	return new Exception( sprintf( __( 'Outside this workspace. You may only use your own folder (%s) and the folders allowed for this chat or task.', 'flx-ai' ), $p['dir'] ) );
}

/* ---------- Değişiklik kayıtları (commit / PR) ---------- */

function flxai_change_decode( $row ) {
	if ( ! $row ) {
		return null;
	}
	$row = (array) $row;
	foreach ( array( 'id', 'chat_id', 'task_id', 'user_id', 'created', 'updated' ) as $k ) {
		$row[ $k ] = (int) $row[ $k ];
	}
	$f            = json_decode( (string) $row['files'], true );
	$row['files'] = is_array( $f ) ? $f : array();
	return $row;
}

function flxai_change_get( $id ) {
	global $wpdb;
	return flxai_change_decode( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxai_table( 'changes' ) . ' WHERE id = %d', (int) $id ), ARRAY_A ) ); // phpcs:ignore
}

/** Kaydeder (id yoksa ekler); kaydı döndürür. */
function flxai_change_save( array $c ) {
	global $wpdb;
	$row = array(
		'chat_id' => (int) ( $c['chat_id'] ?? 0 ),
		'task_id' => (int) ( $c['task_id'] ?? 0 ),
		'user_id' => (int) ( $c['user_id'] ?? 0 ),
		'kind'    => 'pr' === ( $c['kind'] ?? '' ) ? 'pr' : 'commit',
		'status'  => (string) ( $c['status'] ?? 'done' ),
		'title'   => mb_substr( (string) ( $c['title'] ?? '' ), 0, 190 ),
		'turn'    => (string) ( $c['turn'] ?? '' ),
		'files'   => wp_json_encode( array_values( (array) ( $c['files'] ?? array() ) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
		'updated' => time(),
	);
	if ( ! empty( $c['id'] ) ) {
		$wpdb->update( flxai_table( 'changes' ), $row, array( 'id' => (int) $c['id'] ) ); // phpcs:ignore
		return flxai_change_get( (int) $c['id'] );
	}
	$row['created'] = time();
	$wpdb->insert( flxai_table( 'changes' ), $row ); // phpcs:ignore
	return flxai_change_get( (int) $wpdb->insert_id );
}

/**
 * Değişiklik listesi.
 *
 * @param array $args chat_id, task_id, user_id, kind, status, limit.
 */
function flxai_changes_list( array $args = array() ) {
	global $wpdb;
	$w = array( '1=1' );
	foreach ( array( 'chat_id', 'task_id', 'user_id' ) as $k ) {
		if ( ! empty( $args[ $k ] ) ) {
			$w[] = $wpdb->prepare( "{$k} = %d", (int) $args[ $k ] ); // phpcs:ignore
		}
	}
	foreach ( array( 'kind', 'status' ) as $k ) {
		if ( ! empty( $args[ $k ] ) ) {
			$w[] = $wpdb->prepare( "{$k} = %s", (string) $args[ $k ] ); // phpcs:ignore
		}
	}
	$limit = max( 1, min( 200, (int) ( $args['limit'] ?? 50 ) ) );
	$rows  = $wpdb->get_results( 'SELECT * FROM ' . flxai_table( 'changes' ) . ' WHERE ' . implode( ' AND ', $w ) . " ORDER BY id DESC LIMIT {$limit}", ARRAY_A ); // phpcs:ignore
	return array_map( 'flxai_change_decode', (array) $rows );
}

/** Sohbetin açık değişiklik isteği (yoksa null). */
function flxai_change_open_pr( $chat_id ) {
	$l = flxai_changes_list( array( 'chat_id' => (int) $chat_id, 'kind' => 'pr', 'status' => 'open', 'limit' => 1 ) );
	return $l ? $l[0] : null;
}

/** Bekleyen değişiklik istekleri (kullanıcının). */
function flxai_changes_open_count( $user_id = 0 ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxai_table( 'changes' ) . " WHERE kind = 'pr' AND status = 'open' AND user_id = %d", $user_id ? (int) $user_id : get_current_user_id() ) ); // phpcs:ignore
}

/** Satır ekleme/silme sayısı (basit LCS yerine satır çoklu kümesi farkı; liste özeti için yeterli). */
function flxai_change_stat( $before, $after ) {
	$a = null === $before ? array() : preg_split( '/\R/', (string) $before );
	$b = null === $after ? array() : preg_split( '/\R/', (string) $after );
	if ( null === $before ) {
		return array( count( $b ), 0 );
	}
	if ( null === $after ) {
		return array( 0, count( $a ) );
	}
	$ca  = array_count_values( $a );
	$cb  = array_count_values( $b );
	$add = 0;
	$del = 0;
	foreach ( $cb as $l => $n ) {
		$add += max( 0, $n - ( $ca[ $l ] ?? 0 ) );
	}
	foreach ( $ca as $l => $n ) {
		$del += max( 0, $n - ( $cb[ $l ] ?? 0 ) );
	}
	return array( $add, $del );
}

/**
 * Kayda dosya değişikliği ekler. Aynı dosya tekrar değişirse ilk "önce" ve son "sonra" kalır.
 *
 * @param array       $c      Kayıt (değişir).
 * @param string      $path   Yol.
 * @param string|null $before Önceki içerik (yoksa null).
 * @param string|null $after  Sonraki içerik (silindiyse null).
 * @param array       $extra  base (PR çakışma kontrolü için md5), bin.
 */
function flxai_change_put( array &$c, $path, $before, $after, array $extra = array() ) {
	$files = (array) ( $c['files'] ?? array() );
	$idx   = null;
	foreach ( $files as $i => $f ) {
		if ( $f['path'] === $path ) {
			$idx = $i;
			break;
		}
	}
	$e = null !== $idx ? $files[ $idx ] : array(
		'path'   => $path,
		'before' => $before,
		'base'   => $extra['base'] ?? ( null === $before ? null : md5( (string) $before ) ),
	);
	$e['after'] = $after;
	if ( null !== $e['before'] && strlen( (string) $e['before'] ) > FLXAI_WS_MAX_DIFF ) {
		$e['big']    = true;
		$e['before'] = '';
	}
	if ( null !== $after && strlen( (string) $after ) > FLXAI_WS_MAX_DIFF && 'pr' !== ( $c['kind'] ?? '' ) ) {
		$e['big']   = true;
		$e['after'] = '';
	}
	if ( null === $e['before'] && null === $e['after'] ) {
		if ( null !== $idx ) {
			array_splice( $files, $idx, 1 );
		}
		$c['files'] = $files;
		return;
	}
	if ( null !== $e['before'] && null !== $e['after'] && $e['before'] === $e['after'] && empty( $e['big'] ) ) {
		if ( null !== $idx ) {
			array_splice( $files, $idx, 1 );
		}
		$c['files'] = $files;
		return;
	}
	$e['op'] = null === $e['before'] ? 'add' : ( null === $e['after'] ? 'delete' : 'edit' );
	list( $e['add'], $e['del'] ) = ! empty( $e['big'] ) ? array( 0, 0 ) : flxai_change_stat( $e['before'], $e['after'] );
	if ( null !== $idx ) {
		$files[ $idx ] = $e;
	} else {
		$files[] = $e;
	}
	$c['files'] = $files;
}

/** Değişikliğin başlığı: görevde görev adı, sohbette son kullanıcı mesajı. */
function flxai_change_title( array $chat, array $p ) {
	if ( $p['task'] && function_exists( 'flxai_task_get' ) ) {
		$t = flxai_task_get( $p['task'] );
		if ( $t ) {
			return $t['title'];
		}
	}
	for ( $i = count( $chat['messages'] ) - 1; $i >= 0; $i-- ) {
		$m = $chat['messages'][ $i ];
		if ( 'user' === ( $m['role'] ?? '' ) && is_string( $m['content'] ?? null ) && '' !== trim( $m['content'] ) ) {
			return wp_html_excerpt( preg_replace( '/\s+/u', ' ', $m['content'] ), 120, '…' );
		}
	}
	return '' !== (string) $chat['title'] ? (string) $chat['title'] : __( 'AI changes', 'flx-ai' );
}

/** Bu turun commit kaydı (yoksa yeni). */
function flxai_change_turn_commit( array $chat, array $p, $turn ) {
	$l = flxai_changes_list( array( 'chat_id' => (int) $chat['id'], 'kind' => 'commit', 'limit' => 1 ) );
	if ( $l && (string) $l[0]['turn'] === (string) $turn ) {
		return $l[0];
	}
	return array(
		'chat_id' => (int) $chat['id'],
		'task_id' => (int) $p['task'],
		'user_id' => (int) $chat['user_id'],
		'kind'    => 'commit',
		'status'  => 'done',
		'title'   => flxai_change_title( $chat, $p ),
		'turn'    => (string) $turn,
		'files'   => array(),
	);
}

/* ---------- Flux Files araçlarını çalışma alanına bağlama ---------- */

/** AI'ın değiştiremeyeceği dosya: Flux Files kurallarının yazmadan önceki kopyası (PR'de erken hata için). */
function flxai_ws_guard( $rel ) {
	if ( function_exists( 'flxfm_in_data' ) && flxfm_in_data( $rel ) ) {
		throw new Exception( 'This folder is managed by Flux Files.' );
	}
	if ( flxfm_is_sensitive( $rel ) || flxfm_is_core( $rel ) ) {
		throw new Exception( 'AI cannot change protected or WordPress core files.' );
	}
	if ( flxfm_is_executable( $rel ) && ! flxfm_code_edit_allowed() ) {
		throw new Exception( 'Editing code files is disabled on this site (DISALLOW_FILE_EDIT).' );
	}
	if ( flxfm_is_code( $rel ) && ! flxfm_opt( 'ai_code' ) ) {
		throw new Exception( 'AI is not allowed to write code files. Enable it in Flux Files › Settings.' );
	}
}

/** Dosyanın şu anki içeriği (yoksa null; ikili ya da okunamazsa false). */
function flxai_ws_snapshot( $rel ) {
	$abs = flxai_ws_abs( $rel );
	if ( ! is_file( $abs ) ) {
		return null;
	}
	if ( FLXFM_FS::is_binary( $abs ) || filesize( $abs ) > FLXFM_FS::MAX_EDIT ) {
		return false;
	}
	return (string) file_get_contents( $abs ); // phpcs:ignore
}

/** Araç tanımını bulup asıl işlevini çalıştırır. */
function flxai_ws_call_tool( $name, array $args, array $ctx ) {
	$tools = flxai_tools_for_user( (int) ( $ctx['user_id'] ?? 0 ) );
	$t     = $tools[ $name ] ?? null;
	// MCP'den gelen araçların işlevi MCP kaydında (yetki burada zaten denetlendi).
	$run = $t && ! empty( $t['mcp'] ) && function_exists( 'flxai_mcp_tools' ) ? ( flxai_mcp_tools()[ $name ]['run'] ?? null ) : ( $t['run'] ?? null );
	if ( ! is_callable( $run ) ) {
		/* translators: %s: tool name */
		throw new Exception( sprintf( __( 'Tool not found or not permitted: %s', 'flx-ai' ), $name ) );
	}
	return call_user_func( $run, $args, $ctx );
}

/** Sonuçtan izin dışı çalışma alanı yollarını (path alanı olan öğeler) çıkarır. */
function flxai_ws_scrub( $r, array $p ) {
	if ( ! is_array( $r ) ) {
		return $r;
	}
	$list = array_keys( $r ) === range( 0, count( $r ) - 1 );
	$out  = array();
	foreach ( $r as $k => $v ) {
		if ( is_array( $v ) && isset( $v['path'] ) && is_string( $v['path'] ) ) {
			$rel = flxai_ws_clean( $v['path'] );
			if ( flxai_ws_inside( $rel, flxai_ws_root() ) && 'deny' === flxai_ws_kind( $rel, $p, false ) ) {
				continue;
			}
		}
		$out[ $k ] = is_array( $v ) ? flxai_ws_scrub( $v, $p ) : $v;
	}
	return $list ? array_values( $out ) : $out;
}

/** Tool çağrısının yazdığı yollar. */
function flxai_ws_tool_paths( $name, array $args ) {
	if ( in_array( $name, array( 'flxfm_move', 'flxfm_copy' ), true ) ) {
		return array( flxai_ws_clean( $args['from'] ?? '' ), flxai_ws_clean( $args['to'] ?? '' ) );
	}
	return array( flxai_ws_clean( $args['path'] ?? '' ) );
}

/**
 * Sohbetteki bir dosya aracı çağrısını çalışma alanı kurallarıyla çalıştırır.
 *
 * @return array|null Sonuç; null = olağan çalıştırma.
 * @throws Exception İzin yoksa.
 */
function flxai_ws_run( $name, array $args, array $ctx, array $chat ) {
	$p    = flxai_ws_policy( $chat );
	$read = array( 'flxfm_list', 'flxfm_read', 'flxfm_search', 'flxfm_info', 'flxfm_activity' );
	if ( in_array( $name, $read, true ) ) {
		$rel = flxai_ws_clean( $args['path'] ?? '' );
		if ( '' === $rel && 'workspace' === $p['access'] && in_array( $name, array( 'flxfm_list', 'flxfm_search' ), true ) ) {
			flxai_ws_mkdir( $p['dir'] );
			$args['path'] = $p['dir'];
			return flxai_ws_call_tool( $name, $args, $ctx );
		}
		if ( ( '' !== $rel || 'workspace' === $p['access'] ) && 'deny' === flxai_ws_kind( $rel, $p, false ) ) {
			throw flxai_ws_denied( $p );
		}
		if ( in_array( $name, array( 'flxfm_list', 'flxfm_search', 'flxfm_activity' ), true ) ) {
			// Üst klasörde arama/listeleme başka çalışma alanlarının dosyalarını göstermesin.
			return flxai_ws_scrub( flxai_ws_call_tool( $name, $args, $ctx ), $p );
		}
		if ( 'flxfm_read' === $name ) {
			$pr = flxai_change_open_pr( $chat['id'] );
			foreach ( $pr ? $pr['files'] : array() as $f ) {
				if ( $f['path'] === $rel ) {
					if ( null === $f['after'] ) {
						throw new Exception( 'This file is marked for deletion in the open change request; it still exists until the user merges.' );
					}
					return array(
						'path'    => $rel,
						'content' => (string) $f['after'],
						'staged'  => true,
						'note'    => 'Version from your open change request #' . $pr['id'] . ' (not yet merged by the user).',
					);
				}
			}
		}
		return null;
	}
	if ( 'flxfm_trash_list' === $name || 'flxfm_restore' === $name ) {
		if ( 'site' !== $p['access'] ) {
			throw flxai_ws_denied( $p );
		}
		return null;
	}
	$paths = flxai_ws_tool_paths( $name, $args );
	foreach ( $paths as $rel ) {
		if ( '' === $rel ) {
			throw new Exception( 'Invalid path.' );
		}
		if ( 'deny' === flxai_ws_kind( $rel, $p ) ) {
			throw flxai_ws_denied( $p );
		}
	}
	$own = true;
	foreach ( $paths as $rel ) {
		$own = $own && 'own' === flxai_ws_kind( $rel, $p );
	}
	$turn = (string) ( $ctx['turn'] ?? '' );
	$size = strlen( (string) ( $args['content'] ?? '' ) ) + strlen( (string) ( $args['replace'] ?? '' ) );
	if ( $size > flxai_ws_max_write() ) {
		/* translators: %s: size limit */
		throw new Exception( sprintf( 'The content is too large (max %s per write).', size_format( flxai_ws_max_write() ) ) );
	}
	if ( $own ) {
		// Kendi klasörü: doğrudan yazılır, bu turun commit'ine eklenir.
		foreach ( $paths as $rel ) {
			flxai_ws_mkdir( dirname( $rel ) );
		}
		$snap = array();
		foreach ( array_unique( $paths ) as $rel ) {
			$snap[ $rel ] = flxai_ws_snapshot( $rel );
		}
		$c = flxai_change_turn_commit( $chat, $p, '' !== $turn ? $turn : 'r' . time() );
		if ( count( $c['files'] ) >= flxai_ws_max_files() ) {
			throw new Exception( sprintf( 'File limit for one turn reached (%d files). Finish and report; the user can ask you to continue.', flxai_ws_max_files() ) );
		}
		$res = flxai_ws_call_tool( $name, $args, $ctx );
		foreach ( $snap as $rel => $before ) {
			$after = flxai_ws_snapshot( $rel );
			if ( false === $before || false === $after ) {
				continue; // ikili dosya: farkı yok
			}
			flxai_change_put( $c, $rel, $before, $after );
		}
		if ( $c['files'] ) {
			$c = flxai_change_save( $c );
			if ( is_array( $res ) ) {
				$res['commit'] = (int) $c['id'];
			}
			do_action( 'flxai_ws_committed', $c, $chat );
		}
		return $res;
	}
	if ( ! in_array( $name, array( 'flxfm_write', 'flxfm_replace', 'flxfm_delete' ), true ) ) {
		return null; // taşıma/kopyalama/klasör: olağan onay kartı
	}
	// Site dosyası: değişiklik isteğine (PR) eklenir, dosya henüz değişmez.
	$rel = $paths[0];
	flxai_ws_guard( $rel );
	$pr = flxai_change_open_pr( $chat['id'] );
	if ( ! $pr ) {
		$pr = array(
			'chat_id' => (int) $chat['id'],
			'task_id' => (int) $p['task'],
			'user_id' => (int) $chat['user_id'],
			'kind'    => 'pr',
			'status'  => 'open',
			'title'   => flxai_change_title( $chat, $p ),
			'files'   => array(),
		);
	}
	$disk   = flxai_ws_snapshot( $rel );
	$staged = null;
	foreach ( $pr['files'] as $f ) {
		if ( $f['path'] === $rel ) {
			$staged = $f;
		}
	}
	if ( ! $staged && count( $pr['files'] ) >= flxai_ws_max_files() ) {
		throw new Exception( sprintf( 'The change request already has %d files; wait until the user reviews it.', flxai_ws_max_files() ) );
	}
	if ( false === $disk ) {
		throw new Exception( 'This is a binary or very large file; AI can only change text files.' );
	}
	$cur = $staged ? $staged['after'] : $disk;
	if ( 'flxfm_write' === $name ) {
		if ( ! empty( $args['create_only'] ) && null !== $cur ) {
			throw new Exception( 'A file with this name already exists.' );
		}
		$after = (string) ( $args['content'] ?? '' );
	} elseif ( 'flxfm_replace' === $name ) {
		if ( null === $cur ) {
			throw new Exception( 'File or folder not found.' );
		}
		$find = (string) ( $args['find'] ?? '' );
		if ( '' === $find ) {
			throw new Exception( 'find is empty.' );
		}
		$n = substr_count( $cur, $find );
		if ( ! $n ) {
			throw new Exception( 'The text to find was not found in the file.' );
		}
		if ( $n > 1 && empty( $args['all'] ) ) {
			throw new Exception( sprintf( 'The text occurs %d times; make it unique or set all=true.', $n ) );
		}
		$after = empty( $args['all'] ) ? implode( (string) ( $args['replace'] ?? '' ), explode( $find, $cur, 2 ) ) : str_replace( $find, (string) ( $args['replace'] ?? '' ), $cur );
	} else {
		if ( null === $cur ) {
			throw new Exception( 'File or folder not found.' );
		}
		if ( null !== $disk && is_dir( flxai_ws_abs( $rel ) ) ) {
			throw new Exception( 'Folders outside your workspace cannot be deleted through a change request.' );
		}
		$after = null;
	}
	flxai_change_put( $pr, $rel, $disk, $after, array( 'base' => null === $disk ? null : md5( $disk ) ) );
	$pr = flxai_change_save( $pr );
	if ( ! $pr['files'] ) {
		$pr['status'] = 'rejected';
		flxai_change_save( $pr );
	}
	do_action( 'flxai_ws_staged', $pr, $chat );
	return array(
		'staged' => true,
		'change' => (int) $pr['id'],
		'path'   => $rel,
		'note'   => 'Not applied yet: added to change request #' . $pr['id'] . '. The user reviews the diff and merges or rejects it. Continue with other steps; reading this file now returns your staged version.',
	);
}

/* Sohbet dosya araçlarını çalışma alanından geçirir (MCP istemcilerinde değil). */
add_filter(
	'flxai_pre_run_tool',
	function ( $pre, $name, $args, $ctx ) {
		if ( null !== $pre || 0 !== strpos( (string) $name, 'flxfm_' ) || empty( $ctx['chat_id'] ) || ! flxai_ws_ready() ) {
			return $pre;
		}
		$chat = flxai_chat_get( (int) $ctx['chat_id'], (int) ( $ctx['user_id'] ?? 0 ) );
		return $chat ? flxai_ws_run( (string) $name, is_array( $args ) ? $args : array(), $ctx, $chat ) : $pre;
	},
	10,
	4
);

/* Kendi klasöründeki yazma ve site dosyası değişiklik isteği onay kartı istemez (inceleme PR'de). */
add_filter(
	'flxai_call_needs_approval',
	function ( $needs, $name, $args, $chat ) {
		if ( ! $needs || 0 !== strpos( (string) $name, 'flxfm_' ) || ! flxai_ws_ready() || ! is_array( $chat ) ) {
			return $needs;
		}
		if ( in_array( $name, array( 'flxfm_write', 'flxfm_replace', 'flxfm_delete' ), true ) ) {
			return false;
		}
		if ( in_array( $name, array( 'flxfm_mkdir', 'flxfm_move', 'flxfm_copy' ), true ) ) {
			$p = flxai_ws_policy( $chat );
			foreach ( flxai_ws_tool_paths( $name, is_array( $args ) ? $args : array() ) as $rel ) {
				if ( '' === $rel || 'own' !== flxai_ws_kind( $rel, $p ) ) {
					return $needs;
				}
			}
			return false;
		}
		return $needs;
	},
	10,
	4
);

/* Modele çalışma alanını anlatır (oturum bölümü). */
add_filter(
	'flxai_chat_session',
	function ( $text, $chat ) {
		if ( ! is_array( $chat ) || ! flxai_ws_ready() ) {
			return $text;
		}
		$p   = flxai_ws_policy( $chat );
		$out = "\n\n## Çalışma alanı (dosyalar)\n"
			. '- Kendi klasörün: ' . $p['dir'] . ". Notlar, raporlar, taslaklar ve ara dosyalar buraya; flxfm_write ile onaysız yazarsın, her tur bir commit olarak kaydedilir.\n";
		if ( 'workspace' === $p['access'] ) {
			$out .= '- Yalnız kendi klasörünü' . ( $p['folders'] ? ' ve şu klasörleri: ' . implode( ', ', $p['folders'] ) : '' ) . " kullanabilirsin; dışındaki yollar reddedilir.\n";
		} else {
			$out .= "- Sitenin diğer dosyalarını okuyabilirsin.\n";
		}
		$out .= "- Klasörün dışındaki dosya değişiklikleri (flxfm_write/flxfm_replace/flxfm_delete) hemen uygulanmaz, değişiklik isteğinde (PR) birikir; kullanıcı farkı görüp birleştirir. Ayrıca onay kartı çıkmaz, işine devam et; sonunda neyi neden değiştirdiğini kısaca yaz.\n"
			. '- Skill dosyaları: ' . flxai_ws_skills_dir() . '/<ad>/SKILL.md (okunur; skill değiştirmek için flxai_skill_save).';
		return $text . $out;
	},
	10,
	2
);

/* ---------- Birleştirme / reddetme ---------- */

/**
 * PR'yi (tamamını ya da seçili dosyaları) birleştirir ya da reddeder.
 *
 * @param array      $pr    Kayıt.
 * @param bool       $merge Birleştir (false: reddet).
 * @param array|null $paths Yalnız bu dosyalar.
 * @return array|WP_Error array( change, applied[], conflicts[], errors[] )
 */
function flxai_change_decide( array $pr, $merge, $paths = null ) {
	if ( 'pr' !== $pr['kind'] || 'open' !== $pr['status'] ) {
		return new WP_Error( 'flxai_change_closed', __( 'This change request is already closed.', 'flx-ai' ), array( 'status' => 409 ) );
	}
	if ( $merge && ! flxai_ws_ready() ) {
		return new WP_Error( 'flxai_ws_files', __( 'Flux Files is required to apply file changes.', 'flx-ai' ), array( 'status' => 400 ) );
	}
	$applied   = array();
	$conflicts = array();
	$errors    = array();
	foreach ( $pr['files'] as $i => $f ) {
		if ( ! empty( $f['state'] ) || ( is_array( $paths ) && ! in_array( $f['path'], $paths, true ) ) ) {
			continue;
		}
		if ( ! $merge ) {
			$pr['files'][ $i ]['state'] = 'rejected';
			continue;
		}
		$disk = flxai_ws_snapshot( $f['path'] );
		$base = null === $disk || false === $disk ? null : md5( $disk );
		if ( $base !== ( $f['base'] ?? null ) ) {
			$conflicts[] = $f['path'];
			continue;
		}
		$path = $f['path'];
		$after = $f['after'];
		$r    = flxfm_tool_call(
			true,
			static function () use ( $path, $after ) {
				if ( null === $after ) {
					return FLXFM_FS::trash( $path );
				}
				$dir  = dirname( $path );
				$miss = array();
				while ( '.' !== $dir && '' !== $dir && ! is_dir( flxai_ws_abs( $dir ) ) ) {
					array_unshift( $miss, $dir );
					$dir = dirname( $dir );
				}
				foreach ( $miss as $d ) {
					$m = FLXFM_FS::mkdir( $d );
					if ( is_wp_error( $m ) ) {
						return $m;
					}
				}
				return FLXFM_FS::write( $path, (string) $after );
			}
		);
		if ( is_wp_error( $r ) ) {
			$errors[ $path ] = $r->get_error_message();
			continue;
		}
		$pr['files'][ $i ]['state'] = 'merged';
		$applied[]                  = $path;
	}
	$states = array_map( fn( $f ) => (string) ( $f['state'] ?? '' ), $pr['files'] );
	if ( ! in_array( '', $states, true ) ) {
		$pr['status'] = ! in_array( 'merged', $states, true ) ? 'rejected' : ( in_array( 'rejected', $states, true ) ? 'partial' : 'merged' );
	}
	$pr = flxai_change_save( $pr );
	if ( 'open' !== $pr['status'] ) {
		flxai_change_note_chat( $pr );
	}
	/**
	 * Değişiklik isteği karara bağlandı.
	 *
	 * @param array $pr      Kayıt.
	 * @param bool  $merge   Birleştirme mi.
	 * @param array $applied Uygulanan dosyalar.
	 */
	do_action( 'flxai_change_decided', $pr, $merge, $applied );
	return array(
		'change'    => $pr,
		'applied'   => $applied,
		'conflicts' => $conflicts,
		'errors'    => $errors,
	);
}

/** PR kapanınca sohbete kısa not düşer (model de sonraki turda görür). */
function flxai_change_note_chat( array $pr ) {
	$chat = flxai_chat_get( $pr['chat_id'], $pr['user_id'] );
	if ( ! $chat || in_array( $chat['status'], array( 'running', 'approval' ), true ) || ! flxai_agent_lock( $chat['id'] ) ) {
		return;
	}
	$m = count( array_filter( $pr['files'], fn( $f ) => 'merged' === ( $f['state'] ?? '' ) ) );
	$r = count( $pr['files'] ) - $m;
	if ( 'merged' === $pr['status'] ) {
		/* translators: 1: change request number, 2: file count */
		$txt = sprintf( __( 'Change request #%1$d was merged: %2$d file(s) changed.', 'flx-ai' ), $pr['id'], $m );
	} elseif ( 'partial' === $pr['status'] ) {
		/* translators: 1: change request number, 2: merged file count, 3: rejected file count */
		$txt = sprintf( __( 'Change request #%1$d was partly merged: %2$d file(s) applied, %3$d rejected.', 'flx-ai' ), $pr['id'], $m, $r );
	} else {
		/* translators: %d: change request number */
		$txt = sprintf( __( 'Change request #%d was rejected; no files were changed.', 'flx-ai' ), $pr['id'] );
	}
	$chat['messages'][] = array(
		'role'    => 'assistant',
		'content' => $txt,
		'_id'     => flxai_new_id(),
		'_ts'     => time(),
		'_notice' => 'change',
		'_change' => (int) $pr['id'],
	);
	flxai_chat_save( $chat );
	flxai_agent_unlock( $chat['id'] );
}

/** Sohbet silinince çalışma klasörü ve kayıtlar da silinir (görev sohbeti değilse). */
function flxai_ws_delete_for_chat( array $chat ) {
	global $wpdb;
	$p = flxai_ws_policy( $chat );
	if ( $p['task'] ) {
		return;
	}
	flxai_ws_remove( $p['dir'] );
	$wpdb->delete( flxai_table( 'changes' ), array( 'chat_id' => (int) $chat['id'] ) ); // phpcs:ignore
}

/* ---------- REST ---------- */

/** Paneldeki dosya ağacı için klasör içeriği (yalnız izinli yollar). */
function flxai_ws_list( $rel ) {
	if ( ! is_dir( flxai_ws_abs( $rel ) ) ) {
		return array();
	}
	$r = FLXFM_FS::list_dir( $rel, array( 'hidden' => true ) );
	if ( is_wp_error( $r ) ) {
		return array();
	}
	$out = array();
	foreach ( $r['items'] as $it ) {
		if ( in_array( $it['name'], array( '.htaccess', 'index.php' ), true ) && flxai_ws_inside( $rel, flxai_ws_root() ) ) {
			continue;
		}
		$out[] = array(
			'name'  => $it['name'],
			'path'  => $it['path'],
			'type'  => $it['type'],
			'size'  => $it['size'],
			'ago'   => $it['ago'],
			'count' => $it['count'] ?? null,
		);
	}
	return $out;
}

/** Liste için özet (içerik olmadan). */
function flxai_change_public( array $c, $full = false ) {
	$files = array();
	foreach ( $c['files'] as $f ) {
		$x = array(
			'path'  => $f['path'],
			'op'    => $f['op'] ?? 'edit',
			'add'   => (int) ( $f['add'] ?? 0 ),
			'del'   => (int) ( $f['del'] ?? 0 ),
			'state' => (string) ( $f['state'] ?? '' ),
			'big'   => ! empty( $f['big'] ),
		);
		if ( $full ) {
			$x['before'] = $f['before'];
			$x['after']  = $f['after'];
		}
		$files[] = $x;
	}
	return array(
		'id'      => $c['id'],
		'kind'    => $c['kind'],
		'status'  => $c['status'],
		'title'   => $c['title'],
		'chat_id' => $c['chat_id'],
		'task_id' => $c['task_id'],
		'created' => $c['created'],
		'ago'     => flx_time_ago( $c['created'] ),
		'date'    => flx_date( $c['created'], 'datetime' ),
		'files'   => $files,
	);
}

/** Paneldeki çalışma alanı özeti. */
function flxai_ws_state( array $chat, $path = '' ) {
	$p    = flxai_ws_policy( $chat );
	$out  = array(
		'ready'   => $p['ready'],
		'dir'     => $p['dir'],
		'access'  => $p['access'],
		'folders' => $p['folders'],
		'task'    => $p['task'],
		'skills'  => flxai_ws_skills_dir(),
		'files'   => function_exists( 'flxfm_root' ) ? admin_url( 'admin.php?page=flx-files#path=' . rawurlencode( $p['dir'] ) ) : '',
		'changes' => array_map( 'flxai_change_public', flxai_changes_list( array( 'chat_id' => (int) $chat['id'], 'limit' => 50 ) ) ),
	);
	if ( $p['ready'] ) {
		$out['exists'] = is_dir( flxai_ws_abs( $p['dir'] ) );
		$out['items']  = flxai_ws_list( $p['dir'] );
	}
	return $out;
}

/** Panelden gezilebilen yol mu? Kendi klasörü, izinli klasörler, skill'ler; "site" erişiminde tüm site. */
/** Kullanıcı bu dosyayı panelden düzenleyebilir mi: yalnız sohbetin kendi klasörü, metin, hassas/kod değil. */
function flxai_ws_user_can_edit( $rel, array $chat, $content = '' ) {
	$p = flxai_ws_policy( $chat );
	if ( ! $p['dir'] || ! flxai_ws_inside( $rel, $p['dir'] ) ) {
		return false;
	}
	if ( function_exists( 'flxfm_is_sensitive' ) && flxfm_is_sensitive( $rel ) ) {
		return false;
	}
	if ( function_exists( 'flxfm_is_executable' ) && flxfm_is_executable( $rel ) ) {
		return false;
	}
	return '' === $content || false === strpos( (string) $content, "\0" );
}

function flxai_ws_can_browse( $rel, array $p ) {
	return 'deny' !== flxai_ws_kind( $rel, $p, false ) && ( ! function_exists( 'flxfm_is_sensitive' ) || ! flxfm_is_sensitive( $rel ) );
}

add_action(
	'rest_api_init',
	function () {
		$ns   = 'flx-ai/v1';
		$perm = function () {
			return is_user_logged_in() && flxai_can_chat();
		};
		$chat = function ( WP_REST_Request $req ) {
			$c = flxai_chat_get( (int) $req['id'] );
			return $c ? $c : new WP_Error( 'flxai_not_found', __( 'Chat not found.', 'flx-ai' ), array( 'status' => 404 ) );
		};
		register_rest_route(
			$ns,
			'/chats/(?P<id>\d+)/ws',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => $perm,
					'callback'            => function ( WP_REST_Request $req ) use ( $chat ) {
						$c = $chat( $req );
						return is_wp_error( $c ) ? $c : flxai_ws_state( $c );
					},
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => $perm,
					'callback'            => function ( WP_REST_Request $req ) use ( $chat ) {
						$c = $chat( $req );
						if ( is_wp_error( $c ) ) {
							return $c;
						}
						$cfg = flxai_ws_clean_cfg( array( 'access' => $req['access'], 'folders' => $req['folders'] ) );
						$p   = flxai_ws_policy( $c );
						if ( $p['task'] ) {
							$t = flxai_task_get( $p['task'] );
							if ( ! $t || ( (int) $t['user_id'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) ) {
								return new WP_Error( 'flxai_forbidden', __( 'You cannot change this task.', 'flx-ai' ), array( 'status' => 403 ) );
							}
							$meta       = (array) $t['meta'];
							$meta['ws'] = array_merge( (array) ( $meta['ws'] ?? array() ), $cfg );
							flxai_task_patch( $t['id'], array( 'meta' => $meta ) );
						} else {
							$c['state']['ws'] = $cfg;
							flxai_chat_save( $c );
						}
						return flxai_ws_state( flxai_chat_get( $c['id'] ) );
					},
				),
			)
		);
		register_rest_route(
			$ns,
			'/chats/(?P<id>\d+)/ws/list',
			array(
				'methods'             => 'GET',
				'permission_callback' => $perm,
				'callback'            => function ( WP_REST_Request $req ) use ( $chat ) {
					$c = $chat( $req );
					if ( is_wp_error( $c ) ) {
						return $c;
					}
					$rel = flxai_ws_clean( (string) $req['path'] );
					if ( ! flxai_ws_ready() || ! flxai_ws_can_browse( $rel, flxai_ws_policy( $c ) ) ) {
						return new WP_Error( 'flxai_forbidden', __( 'This folder is not part of the workspace.', 'flx-ai' ), array( 'status' => 403 ) );
					}
					return array( 'path' => $rel, 'items' => flxai_ws_list( $rel ) );
				},
			)
		);
		register_rest_route(
			$ns,
			'/chats/(?P<id>\d+)/ws/file',
			array(
				'methods'             => 'GET',
				'permission_callback' => $perm,
				'callback'            => function ( WP_REST_Request $req ) use ( $chat ) {
					$c = $chat( $req );
					if ( is_wp_error( $c ) ) {
						return $c;
					}
					$rel = flxai_ws_clean( (string) $req['path'] );
					if ( ! flxai_ws_ready() || '' === $rel || ! flxai_ws_can_browse( $rel, flxai_ws_policy( $c ) ) ) {
						return new WP_Error( 'flxai_forbidden', __( 'This file is not part of the workspace.', 'flx-ai' ), array( 'status' => 403 ) );
					}
					$r = FLXFM_FS::read( $rel, 1048576 );
					if ( is_wp_error( $r ) ) {
						return $r;
					}
					return array(
						'path'    => $rel,
						'content' => $r['content'],
						'size'    => $r['size'],
						'ago'     => $r['ago'],
						'files'    => admin_url( 'admin.php?page=flx-files#path=' . rawurlencode( dirname( $rel ) ) ),
						'editable' => flxai_ws_user_can_edit( $rel, $c, $r['content'] ),
					);
				},
			)
		);
		// Kullanıcı kendi sohbetinin klasöründeki metin dosyasını düzenler (Flux Files sürüm kaydıyla).
		register_rest_route(
			$ns,
			'/chats/(?P<id>\d+)/ws/save',
			array(
				'methods'             => 'POST',
				'permission_callback' => function () use ( $perm ) {
					return $perm() && current_user_can( 'manage_options' );
				},
				'callback'            => function ( WP_REST_Request $req ) use ( $chat ) {
					$c = $chat( $req );
					if ( is_wp_error( $c ) ) {
						return $c;
					}
					$rel     = flxai_ws_clean( (string) $req['path'] );
					$content = (string) $req['content'];
					if ( ! flxai_ws_ready() || '' === $rel || ! flxai_ws_user_can_edit( $rel, $c, $content ) ) {
						return new WP_Error( 'flxai_forbidden', __( 'Only text files in this chat\'s own folder can be edited.', 'flx-ai' ), array( 'status' => 403 ) );
					}
					if ( strlen( $content ) > flxai_ws_max_write() ) {
						return new WP_Error( 'flxai_too_big', __( 'The file is too large.', 'flx-ai' ), array( 'status' => 413 ) );
					}
					$r = flxai_ws_write( $rel, $content );
					if ( is_wp_error( $r ) ) {
						return $r;
					}
					return array( 'path' => $rel, 'content' => $content );
				},
			)
		);
		$own = function ( WP_REST_Request $req ) {
			$c = flxai_change_get( (int) $req['cid'] );
			if ( ! $c || ( $c['user_id'] !== get_current_user_id() ) ) {
				return new WP_Error( 'flxai_not_found', __( 'Change not found.', 'flx-ai' ), array( 'status' => 404 ) );
			}
			return $c;
		};
		register_rest_route(
			$ns,
			'/changes/(?P<cid>\d+)',
			array(
				'methods'             => 'GET',
				'permission_callback' => $perm,
				'callback'            => function ( WP_REST_Request $req ) use ( $own ) {
					$c = $own( $req );
					return is_wp_error( $c ) ? $c : flxai_change_public( $c, true );
				},
			)
		);
		register_rest_route(
			$ns,
			'/changes/(?P<cid>\d+)/(?P<do>merge|reject)',
			array(
				'methods'             => 'POST',
				'permission_callback' => function () use ( $perm ) {
					return $perm() && current_user_can( function_exists( 'flxfm_root' ) ? 'flxfm_manage' : 'manage_options' );
				},
				'callback'            => function ( WP_REST_Request $req ) use ( $own ) {
					$c = $own( $req );
					if ( is_wp_error( $c ) ) {
						return $c;
					}
					$paths = $req['paths'];
					$r     = flxai_change_decide( $c, 'merge' === $req['do'], is_array( $paths ) ? array_map( 'strval', $paths ) : null );
					if ( is_wp_error( $r ) ) {
						return $r;
					}
					$r['change'] = flxai_change_public( $r['change'] );
					return $r;
				},
			)
		);
	}
);
