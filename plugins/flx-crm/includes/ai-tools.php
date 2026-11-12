<?php
/**
 * Yapay zekâ ve MCP araçları: tek kayıt, iki kanal.
 *
 * Bütün CRM araçları `flxcrm_tools()` kaydında tanımlanır ve `flxcrm_run_tool()` ile çalışır. Buradan:
 *  - Flux AI sohbetine (`flxai_tools`): değişiklik yapan araçlar Flux AI onay kartında bekler.
 *  - Flux AI & MCP sunucusuna (`flxseo_mcp_tools`): yıkıcı araçlar (silme, birleştirme, toplu işlem, gönderim)
 *    ilk çağrıda yalnız önizleme ve `confirm_token` döner; kullanıcı onaylayınca aynı çağrı token ile tekrarlanır.
 *  - İleride otomasyonlar (`channel` = automation) aynı kaydı, aynı yetki ve kayıt düzeniyle kullanır.
 *
 * Araç tanımı: title, description (modele gider, İngilizce), schema (JSON Schema), kind (read | write | destructive),
 * cap (FLXCRM_CAP_VIEW | FLXCRM_CAP_MANAGE), run( $args, $ctx ), preview( $args, $ctx ) (yıkıcı araçlarda onay özeti).
 * Hata için Exception fırlatılır. Kayıt `flxcrm_tools` filtresiyle genişler.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

const FLXCRM_TOOL_LOG      = 'flxcrm_tool_log';
const FLXCRM_TOOL_BULK_MAX = 5000;
const FLXCRM_TOKEN_TTL     = 900;

/* ---------------------------------------------------------------- */
/* Kayıt                                                             */
/* ---------------------------------------------------------------- */

/** Tüm araçlar (ayar ve yetki süzülmeden). */
function flxcrm_tools() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$tools = (array) apply_filters( 'flxcrm_tools', flxcrm_core_tools() );
	foreach ( $tools as $name => $t ) {
		if ( ! is_array( $t ) || ! preg_match( '/^[a-z0-9_]{1,64}$/', (string) $name ) || empty( $t['run'] ) || ! is_callable( $t['run'] ) ) {
			unset( $tools[ $name ] );
			continue;
		}
		$tools[ $name ] += array(
			'title'       => $name,
			'description' => '',
			'schema'      => array( 'type' => 'object', 'properties' => new stdClass() ),
			'kind'        => 'write',
			'cap'         => FLXCRM_CAP_MANAGE,
			'preview'     => null,
		);
		if ( ! in_array( $tools[ $name ]['kind'], array( 'read', 'write', 'destructive' ), true ) ) {
			$tools[ $name ]['kind'] = 'write';
		}
	}
	$cache = $tools;
	return $tools;
}

/** Ayarlara göre açık araçlar: ai_tools kapalıysa hiçbiri, ai_write kapalıysa yalnız okuma, ai_destructive kapalıysa yıkıcılar yok. */
function flxcrm_tools_enabled() {
	if ( ! (int) flxcrm_setting( 'ai_tools' ) ) {
		return array();
	}
	$write = (int) flxcrm_setting( 'ai_write' );
	$destr = $write && (int) flxcrm_setting( 'ai_destructive' );
	$out   = array();
	foreach ( flxcrm_tools() as $name => $t ) {
		if ( ( 'write' === $t['kind'] && ! $write ) || ( 'destructive' === $t['kind'] && ! $destr ) ) {
			continue;
		}
		$out[ $name ] = $t;
	}
	return $out;
}

/** Geçerli kullanıcı bu aracı kullanabilir mi? (yöneticiler her zaman; flxcrm_manage görme yetkisini de kapsar) */
function flxcrm_tool_allowed( array $t ) {
	if ( 'manage_options' === $t['cap'] ) {
		return current_user_can( 'manage_options' );
	}
	return flxcrm_can( FLXCRM_CAP_MANAGE === $t['cap'] );
}

/** Kanallara verilecek yetki (Flux AI ve MCP listede bununla süzer; çalışırken flxcrm_tool_allowed ayrıca bakar). */
function flxcrm_tool_cap( array $t ) {
	return (string) $t['cap'];
}

function flxcrm_kind_labels() {
	return array(
		'read'        => __( 'Reads', 'flx-crm' ),
		'write'       => __( 'Edits', 'flx-crm' ),
		'destructive' => __( 'Needs approval', 'flx-crm' ),
	);
}

function flxcrm_channel_labels() {
	return array(
		'ai'         => 'Flux AI',
		'mcp'        => 'MCP',
		'automation' => __( 'Automation', 'flx-crm' ),
		'php'        => 'PHP',
	);
}

/**
 * Aracı çalıştırır: açık mı, yetki, onay (yıkıcı), kayıt.
 *
 * @param array $ctx channel (ai | mcp | automation | php), user_id, confirmed (bool: onay kanal tarafından alındı).
 * @return array
 * @throws Exception Hata.
 */
function flxcrm_run_tool( $name, $args, array $ctx = array() ) {
	$ctx  = array_merge( array( 'channel' => 'php', 'user_id' => get_current_user_id(), 'confirmed' => false ), $ctx );
	$args = is_array( $args ) ? $args : array();
	$all  = flxcrm_tools_enabled();
	if ( ! isset( $all[ $name ] ) ) {
		throw new Exception( isset( flxcrm_tools()[ $name ] ) ? 'This CRM tool is turned off in CRM › Settings › AI & MCP.' : 'Unknown CRM tool: ' . $name );
	}
	$t = $all[ $name ];
	if ( ! flxcrm_tool_allowed( $t ) ) {
		throw new Exception( 'You do not have permission for this CRM action.' );
	}
	$token = (string) ( $args['confirm_token'] ?? '' );
	unset( $args['confirm_token'] );

	if ( 'destructive' === $t['kind'] && ! $ctx['confirmed'] ) {
		if ( '' === $token ) {
			$preview = $t['preview'] ? call_user_func( $t['preview'], $args, $ctx ) : array( 'tool' => $name, 'arguments' => $args );
			return array(
				'confirmation_required' => true,
				'preview'               => $preview,
				'confirm_token'         => flxcrm_confirm_token( $name, $args ),
				'expires_in'            => FLXCRM_TOKEN_TTL,
				'next'                  => 'Nothing was changed. Show this preview to the user in plain words and ask for approval. Only after the user agrees, call the same tool again with the same arguments plus confirm_token.',
			);
		}
		if ( ! flxcrm_check_token( $token, $name, $args ) ) {
			throw new Exception( 'confirm_token is invalid, expired, already used or the arguments changed. Call the tool again without confirm_token to get a new preview.' );
		}
	}

	$ok  = false;
	$err = '';
	try {
		$res = call_user_func( $t['run'], $args, $ctx );
		$ok  = true;
	} catch ( Throwable $e ) {
		$err = $e->getMessage();
	}
	if ( 'read' !== $t['kind'] || ! $ok ) {
		flxcrm_tool_log( $name, $ctx, $args, $ok, $err );
	}
	if ( ! $ok ) {
		throw new Exception( $err );
	}
	if ( 'read' !== $t['kind'] ) {
		FLXCRM_Contacts::flush_stats();
	}
	/**
	 * Araç çalıştı. Otomasyonlar ve kendi kendini iyileştiren akışlar (ileride) buradan öğrenir.
	 *
	 * @param string $name Araç.
	 * @param array  $args Girdi (token hariç).
	 * @param mixed  $res  Sonuç.
	 * @param array  $ctx  Kanal, kullanıcı.
	 */
	do_action( 'flxcrm_tool_ran', $name, $args, $res, $ctx );
	return is_array( $res ) ? $res : array( 'result' => $res );
}

/* ---------------------------------------------------------------- */
/* Onay anahtarı                                                     */
/* ---------------------------------------------------------------- */

function flxcrm_args_hash( $name, array $args ) {
	$norm = static function ( $v ) use ( &$norm ) {
		if ( ! is_array( $v ) ) {
			return is_bool( $v ) ? (string) (int) $v : (string) $v;
		}
		if ( array_keys( $v ) !== range( 0, count( $v ) - 1 ) ) {
			ksort( $v );
		}
		return array_map( $norm, $v );
	};
	return hash( 'sha256', $name . '|' . get_current_user_id() . '|' . wp_json_encode( $norm( $args ) ) );
}

function flxcrm_confirm_token( $name, array $args ) {
	$ts = time();
	return $ts . '.' . substr( hash_hmac( 'sha256', $ts . '|' . flxcrm_args_hash( $name, $args ), wp_salt( 'nonce' ) ), 0, 32 );
}

/** Token doğru, süresi dolmamış ve kullanılmamış mı? Doğruysa kullanılmış sayılır. */
function flxcrm_check_token( $token, $name, array $args ) {
	$p = explode( '.', (string) $token );
	if ( 2 !== count( $p ) || ! ctype_digit( $p[0] ) || time() - (int) $p[0] > FLXCRM_TOKEN_TTL ) {
		return false;
	}
	$want = substr( hash_hmac( 'sha256', $p[0] . '|' . flxcrm_args_hash( $name, $args ), wp_salt( 'nonce' ) ), 0, 32 );
	if ( ! hash_equals( $want, $p[1] ) ) {
		return false;
	}
	$used = 'flxcrm_tok_' . md5( $token );
	if ( get_transient( $used ) ) {
		return false;
	}
	set_transient( $used, 1, FLXCRM_TOKEN_TTL + 60 );
	return true;
}

/* ---------------------------------------------------------------- */
/* Kayıt (son 200 değişiklik)                                        */
/* ---------------------------------------------------------------- */

function flxcrm_tool_log( $name, array $ctx, array $args, $ok, $err = '' ) {
	$target = $args['id'] ?? ( $args['primary_id'] ?? ( $args['name'] ?? ( $args['action'] ?? '' ) ) );
	$log    = (array) get_option( FLXCRM_TOOL_LOG, array() );
	array_unshift(
		$log,
		array(
			't'   => time(),
			'u'   => (int) $ctx['user_id'],
			'ch'  => (string) $ctx['channel'],
			'k'   => $name,
			'ok'  => (bool) $ok,
			'on'  => is_scalar( $target ) ? mb_substr( (string) $target, 0, 80 ) : '',
			'err' => mb_substr( (string) $err, 0, 200 ),
		)
	);
	update_option( FLXCRM_TOOL_LOG, array_slice( $log, 0, 200 ), false );
}

function flxcrm_tool_log_rows( $limit = 20 ) {
	return array_slice( (array) get_option( FLXCRM_TOOL_LOG, array() ), 0, (int) $limit );
}

/* ---------------------------------------------------------------- */
/* Yardımcılar                                                       */
/* ---------------------------------------------------------------- */

/** @throws Exception Kişi yok. */
function flxcrm_tool_contact( $id ) {
	$c = FLXCRM_Contacts::get( (int) $id );
	if ( ! $c ) {
		throw new Exception( 'Contact not found: ' . (int) $id );
	}
	return $c;
}

/** Etiket kimliği ya da adı → kimlik (create = yoksa aç). */
function flxcrm_tool_tag_id( $tag, $create = false ) {
	if ( is_numeric( $tag ) ) {
		$all = FLXCRM_Tags::all();
		return isset( $all[ (int) $tag ] ) ? (int) $tag : 0;
	}
	$name = trim( (string) $tag );
	if ( '' === $name ) {
		return 0;
	}
	if ( $create ) {
		return FLXCRM_Tags::ensure( $name );
	}
	$slug = sanitize_title( $name );
	foreach ( FLXCRM_Tags::all() as $id => $t ) {
		if ( $t['slug'] === $slug || 0 === strcasecmp( $t['name'], $name ) ) {
			return (int) $id;
		}
	}
	return 0;
}

/** Araç girdisinden kişi filtresi (find_contacts ve toplu işlem). */
function flxcrm_tool_filter( array $a ) {
	$f = array(
		's'       => sanitize_text_field( (string) ( $a['q'] ?? '' ) ),
		'role'    => sanitize_key( (string) ( $a['role'] ?? '' ) ),
		'segment' => sanitize_key( (string) ( $a['rfm'] ?? ( $a['segment'] ?? '' ) ) ),
		'consent' => sanitize_key( (string) ( $a['consent'] ?? '' ) ),
		'owner'   => absint( $a['owner_id'] ?? 0 ),
		'risk'    => ! empty( $a['risk'] ),
		'status'  => in_array( $a['status'] ?? '', array( 'active', 'archived', 'any' ), true ) ? $a['status'] : 'active',
	);
	if ( isset( $a['tag'] ) && '' !== (string) $a['tag'] ) {
		$f['tag'] = flxcrm_tool_tag_id( $a['tag'] );
		if ( ! $f['tag'] ) {
			throw new Exception( 'Tag not found: ' . sanitize_text_field( (string) $a['tag'] ) );
		}
	}
	$rules = array();
	if ( ! empty( $a['segment_id'] ) ) {
		$s = FLXCRM_Segments::get( (int) $a['segment_id'] );
		if ( ! $s ) {
			throw new Exception( 'Saved segment not found: ' . (int) $a['segment_id'] );
		}
		$rules = FLXCRM_Segments::sanitize_rules( $s['rules'] );
	}
	if ( ! empty( $a['rules'] ) && is_array( $a['rules'] ) ) {
		$extra = FLXCRM_Segments::sanitize_rules( $a['rules'] );
		if ( $rules && ( 'all' !== $rules['match'] || 'all' !== $extra['match'] ) ) {
			throw new Exception( 'segment_id and rules can be combined only when both use match=all.' );
		}
		$rules = $rules ? array( 'match' => 'all', 'rules' => array_merge( $rules['rules'], $extra['rules'] ) ) : $extra;
	}
	if ( $rules ) {
		$f['rules'] = $rules;
	}
	return $f;
}

/** Kişi kartı (etiketlerle). */
function flxcrm_tool_shape( array $c, $full = true ) {
	$out = FLXCRM_REST::shape( $c, $full );
	if ( $full ) {
		$out['tags'] = array_values(
			array_map(
				static function ( $t ) {
					return array( 'id' => (int) $t['id'], 'name' => $t['name'] );
				},
				FLXCRM_Tags::of( $c['id'] )
			)
		);
	}
	return $out;
}

/** Ortak şema parçaları. */
function flxcrm_tool_schema_parts() {
	$fields = array();
	foreach ( FLXCRM_Segments::fields() as $k => $f ) {
		$fields[] = $k . ' (' . $f[1] . ': ' . implode( '|', $f[2] ) . ')';
	}
	return array(
		'rules'   => array(
			'type'        => 'object',
			'description' => 'Segment rules: { "match": "all"|"any", "rules": [ { "field", "op", "value" } ] }. Fields: ' . implode( ', ', $fields ) . '. value: role key, tag id, RFM key, number, days, consent channel (email|sms|whatsapp|ads), source key or text.',
			'properties'  => array(
				'match' => array( 'type' => 'string', 'enum' => array( 'all', 'any' ) ),
				'rules' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'field' => array( 'type' => 'string' ),
							'op'    => array( 'type' => 'string' ),
							'value' => array( 'type' => array( 'string', 'number' ) ),
						),
						'required'   => array( 'field', 'op' ),
					),
				),
			),
		),
		'filter'  => array(
			'q'          => array( 'type' => 'string', 'description' => 'Search in name, email, phone, company, city.' ),
			'role'       => array( 'type' => 'string', 'description' => 'Role key (see flxcrm_meta), or "none".' ),
			'rfm'        => array( 'type' => 'string', 'description' => 'RFM segment: new, champion, loyal, promising, at_risk, hibernating, lost, or "none".' ),
			'tag'        => array( 'type' => array( 'string', 'integer' ), 'description' => 'Tag id or name.' ),
			'consent'    => array( 'type' => 'string', 'enum' => array( 'email', 'sms', 'whatsapp', 'ads' ) ),
			'owner_id'   => array( 'type' => 'integer', 'description' => 'Responsible WordPress user id.' ),
			'risk'       => array( 'type' => 'boolean', 'description' => 'Only paying contacts with high churn risk.' ),
			'status'     => array( 'type' => 'string', 'enum' => array( 'active', 'archived', 'any' ), 'description' => 'Default active.' ),
			'segment_id' => array( 'type' => 'integer', 'description' => 'Saved segment id.' ),
		),
		'consent' => array(
			'type'        => 'object',
			'description' => 'Contact permissions. Turn a channel on ONLY when the person explicitly gave permission (KVKK/GDPR); turning off is always allowed.',
			'properties'  => array(
				'email'    => array( 'type' => 'boolean' ),
				'sms'      => array( 'type' => 'boolean' ),
				'whatsapp' => array( 'type' => 'boolean' ),
				'ads'      => array( 'type' => 'boolean' ),
			),
		),
	);
}

/** Toplu işlem hedefindeki kişiler. */
function flxcrm_tool_bulk_ids( array $a ) {
	$target = (array) ( $a['target'] ?? array() );
	if ( ! empty( $target['ids'] ) && is_array( $target['ids'] ) ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $target['ids'] ) ) ) );
		return array_slice( $ids, 0, FLXCRM_TOOL_BULK_MAX + 1 );
	}
	$f = flxcrm_tool_filter( $target );
	if ( count( array_filter( array( $f['s'], $f['role'], $f['segment'], $f['consent'], $f['owner'], $f['risk'], $f['tag'] ?? 0, $f['rules'] ?? array() ) ) ) === 0 && 'archived' !== $f['status'] ) {
		if ( empty( $target['all'] ) ) {
			throw new Exception( 'Empty target. Give target.ids, a filter or segment_id. To target every contact on purpose set target.all = true.' );
		}
	}
	return FLXCRM_Contacts::ids( $f, FLXCRM_TOOL_BULK_MAX + 1 );
}

function flxcrm_bulk_actions() {
	return array( 'add_tag', 'remove_tag', 'add_role', 'remove_role', 'archive', 'restore', 'set_owner', 'revoke_consent', 'delete' );
}

/** Toplu işlem önizlemesi. */
function flxcrm_tool_bulk_preview( array $a ) {
	$action = sanitize_key( (string) ( $a['action'] ?? '' ) );
	if ( ! in_array( $action, flxcrm_bulk_actions(), true ) ) {
		throw new Exception( 'Unknown bulk action. Use one of: ' . implode( ', ', flxcrm_bulk_actions() ) );
	}
	$ids = flxcrm_tool_bulk_ids( $a );
	if ( count( $ids ) > FLXCRM_TOOL_BULK_MAX ) {
		throw new Exception( 'More than ' . FLXCRM_TOOL_BULK_MAX . ' contacts. Narrow the target.' );
	}
	$sample = array();
	foreach ( array_slice( $ids, 0, 8 ) as $id ) {
		$c = FLXCRM_Contacts::get( $id );
		if ( $c ) {
			$sample[] = array( 'id' => (int) $id, 'name' => flxcrm_display_name( $c ) );
		}
	}
	return array(
		'action'   => $action,
		'value'    => $a['value'] ?? '',
		'contacts' => count( $ids ),
		'sample'   => $sample,
		'warning'  => 'delete' === $action ? 'Deletes the CRM cards, their timelines and tags permanently. Source records (donations, orders) stay.' : '',
	);
}

/** Toplu işlemi uygular. */
function flxcrm_tool_bulk_run( array $a ) {
	$p      = flxcrm_tool_bulk_preview( $a );
	$ids    = flxcrm_tool_bulk_ids( $a );
	$action = $p['action'];
	$value  = $a['value'] ?? '';
	$tag    = 0;
	if ( in_array( $action, array( 'add_tag', 'remove_tag' ), true ) ) {
		$tag = flxcrm_tool_tag_id( $value, 'add_tag' === $action );
		if ( ! $tag ) {
			throw new Exception( 'Tag not found: ' . sanitize_text_field( (string) $value ) );
		}
	}
	if ( in_array( $action, array( 'add_role', 'remove_role' ), true ) && ! isset( flxcrm_roles()[ sanitize_key( (string) $value ) ] ) ) {
		throw new Exception( 'Unknown role: ' . sanitize_key( (string) $value ) );
	}
	if ( 'revoke_consent' === $action && ! in_array( $value, FLXCRM_Contacts::CONSENTS, true ) ) {
		throw new Exception( 'value must be a consent channel: email, sms, whatsapp or ads.' );
	}
	if ( 'set_owner' === $action && absint( $value ) && ! get_userdata( absint( $value ) ) ) {
		throw new Exception( 'Owner user not found.' );
	}
	$n = 0;
	FLXCRM_Events::$defer_recalc = true;
	foreach ( $ids as $id ) {
		$c = FLXCRM_Contacts::get( $id );
		if ( ! $c ) {
			continue;
		}
		switch ( $action ) {
			case 'add_tag':
				FLXCRM_Tags::attach( $id, $tag );
				break;
			case 'remove_tag':
				FLXCRM_Tags::detach( $id, $tag );
				break;
			case 'add_role':
				FLXCRM_Contacts::add_roles( $id, array( sanitize_key( (string) $value ) ) );
				break;
			case 'remove_role':
				FLXCRM_Contacts::set_roles( $id, array_diff( flxcrm_unpack_roles( $c['roles'] ), array( sanitize_key( (string) $value ) ) ) );
				break;
			case 'archive':
			case 'restore':
				FLXCRM_Contacts::update( $id, array( 'status' => 'archive' === $action ? 'archived' : 'active' ) );
				break;
			case 'set_owner':
				FLXCRM_Contacts::update( $id, array( 'owner_id' => absint( $value ) ) );
				break;
			case 'revoke_consent':
				FLXCRM_Contacts::update( $id, array( 'consent_' . $value => 0 ) );
				break;
			case 'delete':
				FLXCRM_Contacts::delete( $id );
				break;
		}
		++$n;
	}
	FLXCRM_Events::$defer_recalc = false;
	return array( 'action' => $action, 'changed' => $n );
}

/* ---------------------------------------------------------------- */
/* Araçlar                                                           */
/* ---------------------------------------------------------------- */

function flxcrm_core_tools() {
	$sp     = flxcrm_tool_schema_parts();
	$view   = FLXCRM_CAP_VIEW;
	$manage = FLXCRM_CAP_MANAGE;
	$t      = array();

	/* --- Okuma --- */

	$t['flxcrm_overview'] = array(
		'title'       => __( 'CRM overview', 'flx-crm' ),
		'description' => 'CRM overview: contact count, new in 30 days, active, high churn risk, total lifetime value, counts per role, per RFM segment and per source, and saved segments with their sizes.',
		'kind'        => 'read',
		'cap'         => $view,
		'run'         => function () {
			return array(
				'stats'    => FLXCRM_Contacts::stats(),
				'currency' => flxcrm_currency(),
				'segments' => FLXCRM_REST::segments(),
			);
		},
	);

	$t['flxcrm_meta'] = array(
		'title'       => __( 'CRM structure and settings', 'flx-crm' ),
		'description' => 'Everything needed to edit the CRM correctly: roles (key, label, color, builtin), RFM segments, tags with contact counts, timeline event types (and which can be added by hand), segment rule fields and operators, consent channels, contact sources, bulk actions and current settings. Call this first before editing.',
		'kind'        => 'read',
		'cap'         => $view,
		'run'         => function () {
			$m       = FLXCRM_REST::meta();
			$fields  = array();
			foreach ( FLXCRM_Segments::fields() as $k => $f ) {
				$fields[] = array( 'field' => $k, 'label' => $f[0], 'type' => $f[1], 'ops' => $f[2] );
			}
			$s = flxcrm_settings();
			unset( $s['ai_tools'], $s['ai_write'], $s['ai_destructive'] );
			return array_merge(
				$m,
				array(
					'tags'               => array_values(
						array_map(
							static function ( $x ) {
								return array( 'id' => (int) $x['id'], 'name' => $x['name'], 'color' => $x['color'], 'count' => (int) ( $x['count'] ?? 0 ) );
							},
							FLXCRM_Tags::all( true )
						)
					),
					'manual_event_types' => flxcrm_manual_event_types(),
					'segment_fields'     => $fields,
					'consent_channels'   => FLXCRM_Contacts::CONSENTS,
					'sources'            => flxcrm_sources(),
					'bulk_actions'       => flxcrm_bulk_actions(),
					'settings'           => $s,
				)
			);
		},
	);

	$t['flxcrm_find_contacts'] = array(
		'title'       => __( 'Search CRM contacts', 'flx-crm' ),
		'description' => 'Searches and lists CRM contacts (donors, customers, leads, volunteers…). All filters are optional and combine with AND; "rules" uses the segment rule format. Returns id, name, email, phone, roles, RFM segment, lifetime value, churn risk and last activity, with total and pages.',
		'kind'        => 'read',
		'cap'         => $view,
		'schema'      => array(
			'type'       => 'object',
			'properties' => $sp['filter'] + array(
				'rules'   => $sp['rules'],
				'orderby' => array( 'type' => 'string', 'enum' => FLXCRM_Contacts::sortable() ),
				'order'   => array( 'type' => 'string', 'enum' => array( 'asc', 'desc' ) ),
				'page'    => array( 'type' => 'integer' ),
				'limit'   => array( 'type' => 'integer', 'description' => '1–100, default 20.' ),
			),
		),
		'run'         => function ( $a ) {
			$f             = flxcrm_tool_filter( $a );
			$f['paged']    = max( 1, (int) ( $a['page'] ?? 1 ) );
			$f['per_page'] = min( 100, max( 1, (int) ( $a['limit'] ?? 20 ) ) );
			$f['orderby']  = sanitize_key( (string) ( $a['orderby'] ?? ( ! empty( $a['risk'] ) ? 'churn' : 'last_activity_at' ) ) );
			$f['order']    = sanitize_key( (string) ( $a['order'] ?? 'desc' ) );
			$q             = FLXCRM_Contacts::query( $f );
			return array(
				'total'    => $q['total'],
				'page'     => $q['paged'],
				'pages'    => $q['pages'],
				'contacts' => array_map( array( 'FLXCRM_REST', 'shape' ), $q['rows'] ),
			);
		},
	);

	$t['flxcrm_get_contact'] = array(
		'title'       => __( 'CRM contact card', 'flx-crm' ),
		'description' => 'Full CRM profile of one contact: contact fields, roles, consents, owner, note, totals (donations, orders, lifetime value), RFM scores, churn risk, tags, source links and the latest timeline events.',
		'kind'        => 'read',
		'cap'         => $view,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array(
				'id'             => array( 'type' => 'integer' ),
				'timeline_limit' => array( 'type' => 'integer', 'description' => '0–100, default 20.' ),
			),
			'required'   => array( 'id' ),
		),
		'run'         => function ( $a ) {
			$c               = flxcrm_tool_contact( $a['id'] ?? 0 );
			$out             = flxcrm_tool_shape( $c );
			$out['links']    = FLXCRM_Contacts::links( $c['id'] );
			$lim             = min( 100, max( 0, (int) ( $a['timeline_limit'] ?? 20 ) ) );
			$out['timeline'] = $lim ? array_map( array( 'FLXCRM_REST', 'shape_event' ), FLXCRM_Events::timeline( $c['id'], array( 'limit' => $lim ) ) ) : array();
			return $out;
		},
	);

	$t['flxcrm_timeline'] = array(
		'title'       => __( 'Timeline', 'flx-crm' ),
		'description' => 'Timeline of one contact, newest first: donations, orders, forms, every WhatsApp message, emails, SMS, notes, calls, meetings, role/segment/consent changes. Filter by event types; page with offset.',
		'kind'        => 'read',
		'cap'         => $view,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array(
				'id'     => array( 'type' => 'integer' ),
				'types'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Event type keys (see flxcrm_meta).' ),
				'limit'  => array( 'type' => 'integer', 'description' => '1–100, default 30.' ),
				'offset' => array( 'type' => 'integer' ),
			),
			'required'   => array( 'id' ),
		),
		'run'         => function ( $a ) {
			$c    = flxcrm_tool_contact( $a['id'] ?? 0 );
			$lim  = min( 100, max( 1, (int) ( $a['limit'] ?? 30 ) ) );
			$rows = FLXCRM_Events::timeline(
				$c['id'],
				array(
					'type'   => (array) ( $a['types'] ?? array() ),
					'limit'  => $lim,
					'offset' => max( 0, (int) ( $a['offset'] ?? 0 ) ),
				)
			);
			return array( 'contact_id' => (int) $c['id'], 'events' => array_map( array( 'FLXCRM_REST', 'shape_event' ), $rows ), 'more' => count( $rows ) === $lim );
		},
	);

	$t['flxcrm_recent_events'] = array(
		'title'       => __( 'Recent activity', 'flx-crm' ),
		'description' => 'Latest timeline events across all contacts (who donated, ordered, wrote or filled a form recently).',
		'kind'        => 'read',
		'cap'         => $view,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array(
				'types' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'limit' => array( 'type' => 'integer', 'description' => '1–100, default 20.' ),
			),
		),
		'run'         => function ( $a ) {
			$rows = FLXCRM_Events::recent( min( 100, max( 1, (int) ( $a['limit'] ?? 20 ) ) ), (array) ( $a['types'] ?? array() ) );
			return array(
				'events' => array_map(
					static function ( $e ) {
						return array_merge( FLXCRM_REST::shape_event( $e ), array( 'contact' => array( 'id' => (int) $e['contact_id'], 'name' => flxcrm_display_name( $e ) ) ) );
					},
					$rows
				),
			);
		},
	);

	$t['flxcrm_find_duplicates'] = array(
		'title'       => __( 'Find duplicate contacts', 'flx-crm' ),
		'description' => 'Finds likely duplicate contacts that share the same name (case-insensitive) but have different email/phone. Use with flxcrm_merge_contacts after the user checks them.',
		'kind'        => 'read',
		'cap'         => $view,
		'schema'      => array( 'type' => 'object', 'properties' => array( 'limit' => array( 'type' => 'integer', 'description' => '1–50 groups, default 20.' ) ) ),
		'run'         => function ( $a ) {
			global $wpdb;
			$t    = flxcrm_table( 'contacts' );
			$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT LOWER(TRIM(name)) AS k, COUNT(*) AS n FROM $t WHERE status = 'active' AND TRIM(name) <> '' GROUP BY LOWER(TRIM(name)) HAVING COUNT(*) > 1 ORDER BY n DESC LIMIT %d", min( 50, max( 1, (int) ( $a['limit'] ?? 20 ) ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB
			$out  = array();
			foreach ( $rows as $r ) {
				$cs    = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE status = 'active' AND LOWER(TRIM(name)) = %s ORDER BY id LIMIT 10", $r['k'] ), ARRAY_A ); // phpcs:ignore WordPress.DB
				$out[] = array_map( array( 'FLXCRM_REST', 'shape' ), $cs );
			}
			return array( 'groups' => $out );
		},
	);

	$t['flxcrm_list_segments'] = array(
		'title'       => __( 'Saved segments', 'flx-crm' ),
		'description' => 'Saved dynamic segments with id, name, description, rules, readable summary and contact count.',
		'kind'        => 'read',
		'cap'         => $view,
		'run'         => function () {
			return array( 'segments' => FLXCRM_REST::segments() );
		},
	);

	$t['flxcrm_preview_segment'] = array(
		'title'       => __( 'Preview segment', 'flx-crm' ),
		'description' => 'Counts contacts matching segment rules without saving: count, readable summary and a sample of top contacts by value.',
		'kind'        => 'read',
		'cap'         => $view,
		'schema'      => array( 'type' => 'object', 'properties' => array( 'rules' => $sp['rules'] ), 'required' => array( 'rules' ) ),
		'run'         => function ( $a ) {
			$rules = FLXCRM_Segments::sanitize_rules( $a['rules'] ?? array() );
			$q     = FLXCRM_Contacts::query( array( 'rules' => $rules, 'per_page' => 8, 'orderby' => 'ltv' ) );
			return array(
				'count'   => $q['total'],
				'summary' => FLXCRM_Segments::describe( $rules ),
				'rules'   => $rules,
				'sample'  => array_map( array( 'FLXCRM_REST', 'shape' ), $q['rows'] ),
			);
		},
	);

	$t['flxcrm_bulk_preview'] = array(
		'title'       => __( 'Preview bulk action', 'flx-crm' ),
		'description' => 'Preview of a bulk action (how many contacts, sample names) and a confirm_token for flxcrm_bulk. Nothing is changed. Same arguments as flxcrm_bulk.',
		'kind'        => 'read',
		'cap'         => $manage,
		'schema'      => null, // flxcrm_bulk ile aynı, aşağıda.
		'run'         => function ( $a ) {
			unset( $a['confirm_token'] );
			$p = flxcrm_tool_bulk_preview( $a );
			return array(
				'preview'       => $p,
				'confirm_token' => flxcrm_confirm_token( 'flxcrm_bulk', $a ),
				'expires_in'    => FLXCRM_TOKEN_TTL,
				'next'          => 'Tell the user what will change and for how many contacts. Only after approval call flxcrm_bulk with the same arguments plus confirm_token.',
			);
		},
	);

	/* --- Düzenleme --- */

	$contact_props = array(
		'name'     => array( 'type' => 'string' ),
		'email'    => array( 'type' => 'string' ),
		'phone'    => array( 'type' => 'string', 'description' => 'Any format; stored as digits with country code (Turkey default).' ),
		'company'  => array( 'type' => 'string' ),
		'city'     => array( 'type' => 'string' ),
		'country'  => array( 'type' => 'string', 'description' => 'ISO 3166-1 alpha-2, e.g. TR.' ),
		'note'     => array( 'type' => 'string', 'description' => 'Short pinned note on the card (not a timeline entry).' ),
		'owner_id' => array( 'type' => 'integer', 'description' => 'Responsible WordPress user id, 0 = none.' ),
		'consent'  => $sp['consent'],
	);

	$t['flxcrm_create_contact'] = array(
		'title'       => __( 'Add CRM contact', 'flx-crm' ),
		'description' => 'Creates a contact (email or phone required). If a contact with the same email/phone already exists, it is returned and only its empty fields are filled (existing = true).',
		'kind'        => 'write',
		'cap'         => $manage,
		'schema'      => array(
			'type'       => 'object',
			'properties' => $contact_props + array(
				'roles' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Role keys.' ),
				'tags'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Tag names (created if missing) or ids.' ),
			),
		),
		'run'         => function ( $a ) {
			if ( '' === flxcrm_email( $a['email'] ?? '' ) && '' === flxcrm_phone( $a['phone'] ?? '' ) ) {
				throw new Exception( 'Email or phone is required.' );
			}
			$before = FLXCRM_Contacts::find( array( 'email' => flxcrm_email( $a['email'] ?? '' ), 'phone' => flxcrm_phone( $a['phone'] ?? '' ) ) );
			$d      = array_intersect_key( $a, array_flip( array( 'name', 'email', 'phone', 'company', 'city', 'country', 'note', 'owner_id' ) ) );
			foreach ( (array) ( $a['consent'] ?? array() ) as $ch => $v ) {
				if ( in_array( $ch, FLXCRM_Contacts::CONSENTS, true ) ) {
					$d[ 'consent_' . $ch ] = ! empty( $v );
				}
			}
			$d['roles']  = array_map( 'sanitize_key', (array) ( $a['roles'] ?? array() ) );
			$d['source'] = 'manual';
			$id          = FLXCRM_Contacts::upsert( $d );
			if ( ! $id ) {
				throw new Exception( 'Contact could not be saved.' );
			}
			foreach ( (array) ( $a['tags'] ?? array() ) as $tag ) {
				FLXCRM_Tags::attach( $id, flxcrm_tool_tag_id( $tag, true ) );
			}
			return array( 'existing' => (bool) $before, 'contact' => flxcrm_tool_shape( FLXCRM_Contacts::get( $id ) ) );
		},
	);

	$t['flxcrm_update_contact'] = array(
		'title'       => __( 'Edit CRM contact', 'flx-crm' ),
		'description' => 'Edits a contact. Only the given fields change; an empty string clears a field. roles replaces all roles; add_roles/remove_roles change some. status: active | archived. consent changes are written to the timeline.',
		'kind'        => 'write',
		'cap'         => $manage,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array( 'id' => array( 'type' => 'integer' ) ) + $contact_props + array(
				'status'       => array( 'type' => 'string', 'enum' => array( 'active', 'archived' ) ),
				'roles'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'add_roles'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'remove_roles' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
			'required'   => array( 'id' ),
		),
		'run'         => function ( $a ) {
			$c = flxcrm_tool_contact( $a['id'] ?? 0 );
			$d = array_intersect_key( $a, array_flip( array( 'name', 'email', 'phone', 'company', 'city', 'country', 'note', 'owner_id', 'status' ) ) );
			if ( isset( $d['owner_id'] ) && absint( $d['owner_id'] ) && ! get_userdata( absint( $d['owner_id'] ) ) ) {
				throw new Exception( 'Owner user not found.' );
			}
			foreach ( (array) ( $a['consent'] ?? array() ) as $ch => $v ) {
				if ( in_array( $ch, FLXCRM_Contacts::CONSENTS, true ) ) {
					$d[ 'consent_' . $ch ] = ! empty( $v );
				}
			}
			$known = flxcrm_roles();
			$roles = flxcrm_unpack_roles( $c['roles'] );
			$touch = false;
			if ( isset( $a['roles'] ) && is_array( $a['roles'] ) ) {
				$roles = array_map( 'sanitize_key', $a['roles'] );
				$touch = true;
			}
			if ( ! empty( $a['add_roles'] ) ) {
				$roles = array_merge( $roles, array_map( 'sanitize_key', (array) $a['add_roles'] ) );
				$touch = true;
			}
			if ( ! empty( $a['remove_roles'] ) ) {
				$roles = array_diff( $roles, array_map( 'sanitize_key', (array) $a['remove_roles'] ) );
				$touch = true;
			}
			if ( $touch ) {
				$bad = array_diff( $roles, array_keys( $known ) );
				if ( $bad ) {
					throw new Exception( 'Unknown role: ' . implode( ', ', $bad ) . '. Add it first with flxcrm_update_settings (roles).' );
				}
				$d['roles'] = array_values( array_unique( $roles ) );
			}
			$res = FLXCRM_Contacts::update( (int) $c['id'], $d );
			if ( is_wp_error( $res ) ) {
				throw new Exception( $res->get_error_message() );
			}
			return array( 'contact' => flxcrm_tool_shape( FLXCRM_Contacts::get( (int) $c['id'] ) ) );
		},
	);

	$t['flxcrm_tag_contact'] = array(
		'title'       => __( 'Add/remove contact tags', 'flx-crm' ),
		'description' => 'Adds and/or removes tags on one contact. add: tag names (created if missing) or ids; remove: names or ids.',
		'kind'        => 'write',
		'cap'         => $manage,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array(
				'id'     => array( 'type' => 'integer' ),
				'add'    => array( 'type' => 'array', 'items' => array( 'type' => array( 'string', 'integer' ) ) ),
				'remove' => array( 'type' => 'array', 'items' => array( 'type' => array( 'string', 'integer' ) ) ),
			),
			'required'   => array( 'id' ),
		),
		'run'         => function ( $a ) {
			$c = flxcrm_tool_contact( $a['id'] ?? 0 );
			foreach ( (array) ( $a['add'] ?? array() ) as $tag ) {
				FLXCRM_Tags::attach( (int) $c['id'], flxcrm_tool_tag_id( $tag, true ) );
			}
			foreach ( (array) ( $a['remove'] ?? array() ) as $tag ) {
				$tid = flxcrm_tool_tag_id( $tag );
				if ( $tid ) {
					FLXCRM_Tags::detach( (int) $c['id'], $tid );
				}
			}
			return array( 'contact_id' => (int) $c['id'], 'tags' => flxcrm_tool_shape( $c )['tags'] );
		},
	);

	$t['flxcrm_add_note'] = array(
		'title'       => __( 'Add timeline entry', 'flx-crm' ),
		'description' => 'Adds a note, call, meeting or sent-email record to a contact timeline. type: note | call | meeting | email_sent. at: optional ISO date (default now).',
		'kind'        => 'write',
		'cap'         => $manage,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array(
				'id'   => array( 'type' => 'integer' ),
				'type' => array( 'type' => 'string', 'enum' => flxcrm_manual_event_types() ),
				'text' => array( 'type' => 'string' ),
				'at'   => array( 'type' => 'string', 'description' => 'ISO 8601 date/time, past only.' ),
			),
			'required'   => array( 'id', 'text' ),
		),
		'run'         => function ( $a ) {
			$c    = flxcrm_tool_contact( $a['id'] ?? 0 );
			$text = sanitize_textarea_field( (string) ( $a['text'] ?? '' ) );
			if ( '' === trim( $text ) ) {
				throw new Exception( 'Text is empty.' );
			}
			$type  = in_array( $a['type'] ?? 'note', flxcrm_manual_event_types(), true ) ? $a['type'] : 'note';
			$first = trim( strtok( $text, "\n" ) );
			$at    = ! empty( $a['at'] ) ? strtotime( (string) $a['at'] ) : 0;
			$id    = FLXCRM_Events::add(
				(int) $c['id'],
				$type,
				array(
					'source'  => 'manual',
					'title'   => mb_substr( $first, 0, 140 ) . ( mb_strlen( $first ) > 140 ? '…' : '' ),
					'data'    => array( 'body' => $text ),
					'user_id' => get_current_user_id(),
					'at'      => $at && $at <= time() ? gmdate( 'Y-m-d H:i:s', $at ) : '',
				)
			);
			return array( 'event_id' => $id );
		},
	);

	$t['flxcrm_save_tag'] = array(
		'title'       => __( 'Add/edit tag', 'flx-crm' ),
		'description' => 'Creates a tag, or renames / recolors an existing one when id is given. color: hex like #16a34a.',
		'kind'        => 'write',
		'cap'         => $manage,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array(
				'id'    => array( 'type' => 'integer' ),
				'name'  => array( 'type' => 'string' ),
				'color' => array( 'type' => 'string' ),
			),
		),
		'run'         => function ( $a ) {
			if ( ! empty( $a['id'] ) ) {
				$res = FLXCRM_Tags::update( (int) $a['id'], array_intersect_key( $a, array_flip( array( 'name', 'color' ) ) ) );
				if ( is_wp_error( $res ) ) {
					throw new Exception( $res->get_error_message() );
				}
				$id = (int) $a['id'];
			} else {
				$id = FLXCRM_Tags::ensure( (string) ( $a['name'] ?? '' ) );
				if ( ! $id ) {
					throw new Exception( 'Tag name is required.' );
				}
				if ( ! empty( $a['color'] ) ) {
					FLXCRM_Tags::update( $id, array( 'color' => $a['color'] ) );
				}
			}
			$all = FLXCRM_Tags::all( true );
			return array( 'tag' => array( 'id' => $id, 'name' => $all[ $id ]['name'] ?? '', 'color' => $all[ $id ]['color'] ?? '', 'count' => (int) ( $all[ $id ]['count'] ?? 0 ) ) );
		},
	);

	$t['flxcrm_save_segment'] = array(
		'title'       => __( 'Save segment', 'flx-crm' ),
		'description' => 'Creates a saved dynamic segment, or updates it when id is given. Saved segments appear in Flux Mail as recipients (only contacts with consent for the channel receive). Preview first with flxcrm_preview_segment.',
		'kind'        => 'write',
		'cap'         => $manage,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array(
				'id'          => array( 'type' => 'integer' ),
				'name'        => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
				'rules'       => $sp['rules'],
			),
			'required'   => array( 'name', 'rules' ),
		),
		'run'         => function ( $a ) {
			$id = (int) ( $a['id'] ?? 0 );
			if ( $id && ! FLXCRM_Segments::get( $id ) ) {
				throw new Exception( 'Segment not found: ' . $id );
			}
			$res = FLXCRM_Segments::save( $a, $id );
			if ( is_wp_error( $res ) ) {
				throw new Exception( $res->get_error_message() );
			}
			$s = FLXCRM_Segments::get( $res );
			return array( 'segment' => array( 'id' => (int) $s['id'], 'name' => $s['name'], 'count' => (int) $s['count'], 'summary' => FLXCRM_Segments::describe( $s['rules'] ), 'url' => flxcrm_admin_url( 'segments', array( 'segment' => (int) $s['id'] ) ) ) );
		},
	);

	$t['flxcrm_update_settings'] = array(
		'title'       => __( 'Change CRM settings', 'flx-crm' ),
		'description' => 'Changes CRM settings. Only the given keys change. Sources on/off: src_donate, src_commerce, src_chat, src_forms, src_users, src_mail (0|1). Role given to new contacts per source: role_donate, role_commerce, role_forms, role_chat, role_users (role key or ""). risk_threshold (10–100), active_days (7–730), base_currency (3 letters or ""), chat_events (0|1). roles: { key: { label, color, delete } } to rename/recolor roles, add custom roles or delete custom ones (builtin roles cannot be deleted). AI/MCP permissions cannot be changed with this tool.',
		'kind'        => 'write',
		'cap'         => 'manage_options',
		'schema'      => array(
			'type'       => 'object',
			'properties' => array(
				'src_donate'     => array( 'type' => 'integer' ),
				'src_commerce'   => array( 'type' => 'integer' ),
				'src_chat'       => array( 'type' => 'integer' ),
				'src_forms'      => array( 'type' => 'integer' ),
				'src_users'      => array( 'type' => 'integer' ),
				'src_mail'       => array( 'type' => 'integer' ),
				'role_donate'    => array( 'type' => 'string' ),
				'role_commerce'  => array( 'type' => 'string' ),
				'role_forms'     => array( 'type' => 'string' ),
				'role_chat'      => array( 'type' => 'string' ),
				'role_users'     => array( 'type' => 'string' ),
				'risk_threshold' => array( 'type' => 'integer' ),
				'active_days'    => array( 'type' => 'integer' ),
				'base_currency'  => array( 'type' => 'string' ),
				'chat_events'    => array( 'type' => 'integer' ),
				'roles'          => array( 'type' => 'object', 'additionalProperties' => array( 'type' => 'object' ) ),
			),
		),
		'run'         => function ( $a ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				throw new Exception( 'Only administrators can change CRM settings.' );
			}
			return array( 'settings' => flxcrm_apply_settings( $a ) );
		},
	);

	$t['flxcrm_rescore'] = array(
		'title'       => __( 'Recalculate scores', 'flx-crm' ),
		'description' => 'Recalculates RFM scores, segments and churn risk. One call runs for about 20 seconds and resumes from the same cursor on the next call or the daily job.',
		'kind'        => 'write',
		'cap'         => $manage,
		'run'         => function () {
			$r = FLXCRM_Scoring::run();
			if ( is_array( $r ) && empty( $r['done'] ) ) {
				return $r;
			}
			FLXCRM_Segments::recount_all();
			return is_array( $r ) ? $r : array( 'done' => true );
		},
	);

	/* --- Onaylı (yıkıcı / dışa giden) --- */

	$t['flxcrm_delete_event'] = array(
		'title'       => __( 'Delete timeline entry', 'flx-crm' ),
		'description' => 'Deletes a hand-added timeline entry (note, call, meeting, email_sent). Entries that came from a source (donation, order, message) cannot be deleted.',
		'kind'        => 'destructive',
		'cap'         => $manage,
		'schema'      => array( 'type' => 'object', 'properties' => array( 'event_id' => array( 'type' => 'integer' ) ), 'required' => array( 'event_id' ) ),
		'preview'     => function ( $a ) {
			$e = flxcrm_tool_event( $a['event_id'] ?? 0 );
			return array( 'delete' => 'timeline entry', 'event' => FLXCRM_REST::shape_event( $e ), 'contact' => flxcrm_display_name( FLXCRM_Contacts::get( $e['contact_id'] ) ) );
		},
		'run'         => function ( $a ) {
			flxcrm_tool_event( $a['event_id'] ?? 0 );
			return array( 'deleted' => FLXCRM_Events::delete( (int) $a['event_id'] ) );
		},
	);

	$t['flxcrm_delete_tag'] = array(
		'title'       => __( 'Delete tag', 'flx-crm' ),
		'description' => 'Deletes a tag everywhere (removes it from all contacts).',
		'kind'        => 'destructive',
		'cap'         => $manage,
		'schema'      => array( 'type' => 'object', 'properties' => array( 'id' => array( 'type' => 'integer' ) ), 'required' => array( 'id' ) ),
		'preview'     => function ( $a ) {
			$all = FLXCRM_Tags::all( true );
			$tg  = $all[ (int) ( $a['id'] ?? 0 ) ] ?? null;
			if ( ! $tg ) {
				throw new Exception( 'Tag not found.' );
			}
			return array( 'delete' => 'tag', 'name' => $tg['name'], 'contacts' => (int) $tg['count'] );
		},
		'run'         => function ( $a ) {
			if ( ! isset( FLXCRM_Tags::all()[ (int) ( $a['id'] ?? 0 ) ] ) ) {
				throw new Exception( 'Tag not found.' );
			}
			FLXCRM_Tags::delete( (int) $a['id'] );
			return array( 'deleted' => true );
		},
	);

	$t['flxcrm_delete_segment'] = array(
		'title'       => __( 'Delete segment', 'flx-crm' ),
		'description' => 'Deletes a saved segment. Contacts are not affected; Flux Mail campaigns using it lose this recipient source.',
		'kind'        => 'destructive',
		'cap'         => $manage,
		'schema'      => array( 'type' => 'object', 'properties' => array( 'id' => array( 'type' => 'integer' ) ), 'required' => array( 'id' ) ),
		'preview'     => function ( $a ) {
			$s = FLXCRM_Segments::get( (int) ( $a['id'] ?? 0 ) );
			if ( ! $s ) {
				throw new Exception( 'Segment not found.' );
			}
			return array( 'delete' => 'segment', 'name' => $s['name'], 'contacts' => (int) $s['count'] );
		},
		'run'         => function ( $a ) {
			if ( ! FLXCRM_Segments::get( (int) ( $a['id'] ?? 0 ) ) ) {
				throw new Exception( 'Segment not found.' );
			}
			FLXCRM_Segments::delete( (int) $a['id'] );
			return array( 'deleted' => true );
		},
	);

	$t['flxcrm_merge_contacts'] = array(
		'title'       => __( 'Merge contacts', 'flx-crm' ),
		'description' => 'Merges duplicate contacts into primary_id: timeline, source links and tags move to the primary card, empty fields are filled, roles are combined, consents are kept if any card had them, then the duplicates are deleted.',
		'kind'        => 'destructive',
		'cap'         => $manage,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array(
				'primary_id'    => array( 'type' => 'integer' ),
				'duplicate_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			),
			'required'   => array( 'primary_id', 'duplicate_ids' ),
		),
		'preview'     => function ( $a ) {
			$p   = flxcrm_tool_contact( $a['primary_id'] ?? 0 );
			$dup = array();
			foreach ( (array) ( $a['duplicate_ids'] ?? array() ) as $id ) {
				$dup[] = FLXCRM_REST::shape( flxcrm_tool_contact( $id ) );
			}
			return array( 'keep' => FLXCRM_REST::shape( $p ), 'merge_and_delete' => $dup );
		},
		'run'         => function ( $a ) {
			$res = FLXCRM_Contacts::merge( (int) ( $a['primary_id'] ?? 0 ), array_map( 'absint', (array) ( $a['duplicate_ids'] ?? array() ) ) );
			if ( is_wp_error( $res ) ) {
				throw new Exception( $res->get_error_message() );
			}
			return array( 'merged' => $res, 'contact' => flxcrm_tool_shape( FLXCRM_Contacts::get( (int) $a['primary_id'] ) ) );
		},
	);

	$t['flxcrm_delete_contact'] = array(
		'title'       => __( 'Delete CRM contact', 'flx-crm' ),
		'description' => 'Permanently deletes a CRM contact with its timeline, tags and source links. Source records (donations, orders, chats) stay. To hide a contact without losing history use flxcrm_update_contact status=archived instead.',
		'kind'        => 'destructive',
		'cap'         => $manage,
		'schema'      => array( 'type' => 'object', 'properties' => array( 'id' => array( 'type' => 'integer' ) ), 'required' => array( 'id' ) ),
		'preview'     => function ( $a ) {
			$c = flxcrm_tool_contact( $a['id'] ?? 0 );
			global $wpdb;
			$n = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxcrm_table( 'events' ) . ' WHERE contact_id = %d', (int) $c['id'] ) ); // phpcs:ignore WordPress.DB
			return array( 'delete' => 'contact', 'contact' => FLXCRM_REST::shape( $c ), 'timeline_events' => $n );
		},
		'run'         => function ( $a ) {
			flxcrm_tool_contact( $a['id'] ?? 0 );
			$res = FLXCRM_Contacts::delete( (int) $a['id'] );
			if ( is_wp_error( $res ) ) {
				throw new Exception( $res->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			return array( 'deleted' => $res );
		},
	);

	$bulk_schema = array(
		'type'       => 'object',
		'properties' => array(
			'target' => array(
				'type'        => 'object',
				'description' => 'Which contacts: { ids: [..] } or filter keys (q, role, rfm, tag, consent, owner_id, risk, status, segment_id, rules). all = true targets every active contact.',
				'properties'  => array(
					'ids'   => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
					'all'   => array( 'type' => 'boolean' ),
					'rules' => $sp['rules'],
				) + $sp['filter'],
			),
			'action' => array( 'type' => 'string', 'enum' => flxcrm_bulk_actions() ),
			'value'  => array( 'type' => array( 'string', 'integer' ), 'description' => 'Tag name/id (add_tag, remove_tag), role key (add_role, remove_role), user id (set_owner), consent channel (revoke_consent). Turning consent ON in bulk is not possible.' ),
		),
		'required'   => array( 'target', 'action' ),
	);
	$t['flxcrm_bulk_preview']['schema'] = $bulk_schema;

	$t['flxcrm_bulk'] = array(
		'title'       => __( 'Bulk action', 'flx-crm' ),
		'description' => 'Bulk action on up to ' . FLXCRM_TOOL_BULK_MAX . ' contacts: add_tag, remove_tag, add_role, remove_role, archive, restore, set_owner, revoke_consent, delete. Always needs confirm_token from flxcrm_bulk_preview (or from a first call without it).',
		'kind'        => 'destructive',
		'cap'         => $manage,
		'schema'      => $bulk_schema,
		'preview'     => function ( $a ) {
			return flxcrm_tool_bulk_preview( $a );
		},
		'run'         => function ( $a ) {
			return flxcrm_tool_bulk_run( $a );
		},
	);

	if ( function_exists( 'flxi_send_email' ) || function_exists( 'flxi_send_message' ) ) {
		$t['flxcrm_send_message'] = array(
			'title'       => __( 'Send message to contact', 'flx-crm' ),
			'description' => 'Sends one email, WhatsApp or SMS to one contact through Flux Mail (logged in Flux Mail and on the timeline). Requires the contact\'s consent for that channel. For campaigns to many people use Flux Mail campaigns with a CRM segment instead.',
			'kind'        => 'destructive',
			'cap'         => $manage,
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'id'      => array( 'type' => 'integer' ),
					'channel' => array( 'type' => 'string', 'enum' => array( 'email', 'whatsapp', 'sms' ) ),
					'subject' => array( 'type' => 'string', 'description' => 'Email only.' ),
					'text'    => array( 'type' => 'string' ),
				),
				'required'   => array( 'id', 'channel', 'text' ),
			),
			'preview'     => function ( $a ) {
				$m = flxcrm_tool_message( $a );
				return array( 'send' => $m['channel'], 'to' => $m['to'], 'contact' => flxcrm_display_name( $m['c'] ), 'subject' => $m['subject'], 'text' => $m['text'] );
			},
			'run'         => function ( $a ) {
				$m = flxcrm_tool_message( $a );
				if ( 'email' === $m['channel'] ) {
					$ok = flxi_send_email( array( 'to' => $m['to'], 'subject' => $m['subject'], 'heading' => $m['subject'], 'body' => wpautop( esc_html( $m['text'] ) ) ) );
					return array( 'sent' => (bool) $ok, 'channel' => 'email', 'to' => $m['to'] );
				}
				$r = flxi_send_message( $m['channel'], $m['raw_to'], $m['text'], array( 'source' => 'flx-crm' ) );
				if ( empty( $r['ok'] ) ) {
					throw new Exception( 'Not sent: ' . (string) ( $r['error'] ?? 'unknown error' ) );
				}
				return array( 'sent' => true, 'channel' => $m['channel'], 'to' => $m['to'] );
			},
		);
	}

	/* --- Yolculuklar --- */

	$jshape = static function ( array $j ) {
		return array(
			'id'      => (int) $j['id'],
			'name'    => $j['name'],
			'status'  => $j['status'],
			'trigger' => $j['trigger_def'],
			'steps'   => $j['steps'],
			'opts'    => $j['opts'],
			'stats'   => $j['stats'],
			'people'  => FLXCRM_Journeys::run_counts( $j['id'] ),
			'summary' => FLXCRM_Journeys::describe( $j ),
		);
	};
	$jget   = static function ( $id ) {
		$j = FLXCRM_Journeys::get( absint( $id ) );
		if ( ! $j ) {
			throw new Exception( 'Journey not found.' );
		}
		return $j;
	};

	$t['flxcrm_journeys'] = array(
		'title'       => __( 'List journeys', 'flx-crm' ),
		'description' => 'Lists automatic contact journeys (drip flows) with status (draft, active, paused), trigger, steps, rules, stats and how many people are in each. Also returns the trigger types, step types, conditions and ready templates you can use with flxcrm_journey_save.',
		'kind'        => 'read',
		'cap'         => $view,
		'run'         => function () use ( $jshape ) {
			return array(
				'journeys'   => array_map( $jshape, FLXCRM_Journeys::all() ),
				'triggers'   => FLXCRM_Journeys::triggers(),
				'step_types' => FLXCRM_Journeys::step_types(),
				'conditions' => FLXCRM_Journeys::conditions(),
				'segments'   => flxcrm_rfm_segments_labels(),
				'templates'  => FLXCRM_Journeys::templates(),
			);
		},
	);

	$t['flxcrm_journey_save'] = array(
		'title'       => __( 'Save journey draft', 'flx-crm' ),
		'description' => 'Creates or edits a journey. It never goes live by itself: a new journey is saved as a draft, and editing an active journey pauses it. The user turns it on in CRM › Journeys (or approves flxcrm_journey_status). trigger.type: segment (value = RFM key, e.g. new, at_risk), tag (value = tag name or id), role, event (value = event type) or created. Steps run in order: wait {days, hours}, email {subject, text}, whatsapp {text}, sms {text}, tag/untag {tag}, if {cond: segment|tag|no_tag|paid|no_paid, value} (if false the person leaves the journey). Texts may use {first_name}, {name}, {site}. opts: goal (event type that ends the journey early, e.g. donation, or empty), reenter_days (0 = never), quiet_from/quiet_to (hours with no messages), cap_week (max journey messages per person per 7 days). Messages are only sent to people who allowed that channel. Write texts in the site language.',
		'kind'        => 'write',
		'cap'         => $manage,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array(
				'id'      => array( 'type' => 'integer', 'description' => 'Existing journey to edit; omit to create.' ),
				'name'    => array( 'type' => 'string' ),
				'trigger' => array(
					'type'       => 'object',
					'properties' => array(
						'type'  => array( 'type' => 'string', 'enum' => array_keys( FLXCRM_Journeys::triggers() ) ),
						'value' => array( 'type' => 'string' ),
					),
					'required'   => array( 'type' ),
				),
				'steps'   => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'type'    => array( 'type' => 'string', 'enum' => array_keys( FLXCRM_Journeys::step_types() ) ),
							'days'    => array( 'type' => 'integer' ),
							'hours'   => array( 'type' => 'integer' ),
							'subject' => array( 'type' => 'string' ),
							'text'    => array( 'type' => 'string' ),
							'tag'     => array( 'type' => 'string' ),
							'cond'    => array( 'type' => 'string', 'enum' => array_keys( FLXCRM_Journeys::conditions() ) ),
							'value'   => array( 'type' => 'string' ),
						),
						'required'   => array( 'type' ),
					),
				),
				'opts'    => array(
					'type'       => 'object',
					'properties' => array(
						'goal'         => array( 'type' => 'string' ),
						'reenter_days' => array( 'type' => 'integer' ),
						'quiet_from'   => array( 'type' => 'integer' ),
						'quiet_to'     => array( 'type' => 'integer' ),
						'cap_week'     => array( 'type' => 'integer' ),
					),
				),
			),
			'required'   => array( 'name', 'trigger', 'steps' ),
		),
		'run'         => function ( $a ) use ( $jshape, $jget ) {
			$id = absint( $a['id'] ?? 0 );
			if ( $id ) {
				$jget( $id );
			}
			$r = FLXCRM_Journeys::save(
				array(
					'name'    => (string) ( $a['name'] ?? '' ),
					'trigger' => (array) ( $a['trigger'] ?? array() ),
					'steps'   => (array) ( $a['steps'] ?? array() ),
					'opts'    => (array) ( $a['opts'] ?? array() ) + FLXCRM_Journeys::defaults(),
				),
				$id
			);
			if ( is_wp_error( $r ) ) {
				throw new Exception( $r->get_error_message() );
			}
			$j = $jshape( FLXCRM_Journeys::get( $r ) );
			return array(
				'journey' => $j,
				'url'     => flxcrm_admin_url( 'journeys', array( 'edit' => $r ) ),
				'note'    => 'active' === $j['status'] ? '' : 'Saved as ' . $j['status'] . '. Nothing is sent until the user turns it on.',
			);
		},
	);

	$t['flxcrm_journey_status'] = array(
		'title'       => __( 'Turn journey on or pause it', 'flx-crm' ),
		'description' => 'Turns a journey on (status active: matching contacts from now on enter it and get its messages) or pauses it (status paused: people wait where they are). Needs the user\'s approval.',
		'kind'        => 'destructive',
		'cap'         => $manage,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array(
				'id'     => array( 'type' => 'integer' ),
				'status' => array( 'type' => 'string', 'enum' => array( 'active', 'paused' ) ),
			),
			'required'   => array( 'id', 'status' ),
		),
		'preview'     => function ( $a ) use ( $jshape, $jget ) {
			$j = $jshape( $jget( $a['id'] ?? 0 ) );
			return array( 'journey' => $j['name'], 'summary' => $j['summary'], 'from' => $j['status'], 'to' => (string) ( $a['status'] ?? '' ), 'steps' => $j['steps'], 'people_in_journey' => $j['people']['active'] );
		},
		'run'         => function ( $a ) use ( $jget ) {
			$jget( $a['id'] ?? 0 );
			if ( ! FLXCRM_Journeys::set_status( absint( $a['id'] ), (string) ( $a['status'] ?? '' ) ) ) {
				throw new Exception( 'status must be active or paused.' );
			}
			return array( 'id' => absint( $a['id'] ), 'status' => (string) $a['status'] );
		},
	);

	$t['flxcrm_journey_delete'] = array(
		'title'       => __( 'Delete journey', 'flx-crm' ),
		'description' => 'Deletes a journey and the progress of the people in it. Messages already sent stay on the timeline.',
		'kind'        => 'destructive',
		'cap'         => $manage,
		'schema'      => array( 'type' => 'object', 'properties' => array( 'id' => array( 'type' => 'integer' ) ), 'required' => array( 'id' ) ),
		'preview'     => function ( $a ) use ( $jshape, $jget ) {
			$j = $jshape( $jget( $a['id'] ?? 0 ) );
			return array( 'delete' => 'journey', 'name' => $j['name'], 'status' => $j['status'], 'people_in_journey' => $j['people']['active'] );
		},
		'run'         => function ( $a ) use ( $jget ) {
			$jget( $a['id'] ?? 0 );
			return array( 'deleted' => FLXCRM_Journeys::delete( absint( $a['id'] ) ) );
		},
	);

	return $t;
}

/** @throws Exception Olay yok ya da elle eklenmemiş. */
function flxcrm_tool_event( $id ) {
	global $wpdb;
	$e = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxcrm_table( 'events' ) . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
	if ( ! $e ) {
		throw new Exception( 'Timeline entry not found.' );
	}
	if ( null !== $e['uniq'] || ! in_array( $e['type'], flxcrm_manual_event_types(), true ) ) {
		throw new Exception( 'Only hand-added notes, calls, meetings and email records can be deleted.' );
	}
	return $e;
}

/** Gönderim girdisini doğrular. @throws Exception */
function flxcrm_tool_message( array $a ) {
	$c  = flxcrm_tool_contact( $a['id'] ?? 0 );
	$ch = in_array( $a['channel'] ?? '', array( 'email', 'whatsapp', 'sms' ), true ) ? $a['channel'] : '';
	if ( ! $ch ) {
		throw new Exception( 'channel must be email, whatsapp or sms.' );
	}
	if ( ( 'email' === $ch && ! function_exists( 'flxi_send_email' ) ) || ( 'email' !== $ch && ! function_exists( 'flxi_send_message' ) ) ) {
		throw new Exception( 'Flux Mail is not active for this channel.' );
	}
	if ( ! (int) $c[ 'consent_' . $ch ] ) {
		throw new Exception( 'The contact has not given permission for ' . $ch . '. Nothing was sent.' );
	}
	$to = 'email' === $ch ? (string) $c['email'] : (string) $c['phone'];
	if ( '' === $to ) {
		throw new Exception( 'The contact has no ' . ( 'email' === $ch ? 'email address' : 'phone number' ) . '.' );
	}
	$text = trim( sanitize_textarea_field( (string) ( $a['text'] ?? '' ) ) );
	if ( '' === $text ) {
		throw new Exception( 'Text is empty.' );
	}
	$subject = 'email' === $ch ? trim( sanitize_text_field( (string) ( $a['subject'] ?? '' ) ) ) : '';
	if ( 'email' === $ch && '' === $subject ) {
		throw new Exception( 'Email subject is required.' );
	}
	return array(
		'c'       => $c,
		'channel' => $ch,
		'to'      => 'email' === $ch ? $to : '+' . $to,
		'raw_to'  => $to,
		'subject' => $subject,
		'text'    => mb_substr( $text, 0, 4000 ),
	);
}

/**
 * Ayarları doğrulayıp kaydeder (yönetim ekranı ve araç ortak). Yalnız verilen anahtarlar değişir.
 *
 * @return array kaydedilen ayarlar (AI izinleri hariç)
 */
function flxcrm_apply_settings( array $in ) {
	$v     = array();
	$known = flxcrm_roles();
	$flags = array( 'chat_events' );
	foreach ( array_keys( flxcrm_registered_sources() ) as $sk ) {
		$flags[] = 'src_' . $sk;
	}
	foreach ( $flags as $k ) {
		if ( array_key_exists( $k, $in ) ) {
			$v[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}
	}
	if ( isset( $in['roles'] ) && is_array( $in['roles'] ) ) {
		$cur = (array) flxcrm_setting( 'roles' );
		foreach ( $in['roles'] as $k => $r ) {
			$key = sanitize_key( remove_accents( (string) $k ) );
			$key = substr( str_replace( '-', '_', $key ), 0, 24 );
			if ( '' === $key || ! is_array( $r ) ) {
				continue;
			}
			if ( ! empty( $r['delete'] ) ) {
				if ( ! empty( $known[ $key ]['builtin'] ) ) {
					throw new Exception( 'Builtin roles cannot be deleted: ' . $key );
				}
				unset( $cur[ $key ] );
				continue;
			}
			$label = trim( sanitize_text_field( (string) ( $r['label'] ?? ( $cur[ $key ]['label'] ?? ( $known[ $key ]['label'] ?? '' ) ) ) ) );
			if ( '' === $label ) {
				throw new Exception( 'Role label is required for a new role: ' . $key );
			}
			$color        = sanitize_hex_color( (string) ( $r['color'] ?? ( $cur[ $key ]['color'] ?? ( $known[ $key ]['color'] ?? '' ) ) ) );
			$cur[ $key ] = array( 'label' => mb_substr( $label, 0, 40 ), 'color' => $color ? $color : '#64748b' );
		}
		$v['roles'] = $cur;
		$known      = array_merge( $known, $cur );
	}
	$role_keys = array();
	foreach ( flxcrm_registered_sources() as $sk => $sd ) {
		if ( null !== $sd['role'] ) {
			$role_keys[] = 'role_' . $sk;
		}
	}
	foreach ( $role_keys as $k ) {
		if ( array_key_exists( $k, $in ) ) {
			$r = sanitize_key( (string) $in[ $k ] );
			if ( '' !== $r && ! isset( $known[ $r ] ) ) {
				throw new Exception( 'Unknown role: ' . $r );
			}
			$v[ $k ] = $r;
		}
	}
	if ( array_key_exists( 'risk_threshold', $in ) ) {
		$v['risk_threshold'] = min( 100, max( 10, absint( $in['risk_threshold'] ) ) );
	}
	if ( array_key_exists( 'active_days', $in ) ) {
		$v['active_days'] = min( 730, max( 7, absint( $in['active_days'] ) ) );
	}
	if ( array_key_exists( 'base_currency', $in ) ) {
		$cur                = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $in['base_currency'] ) );
		$v['base_currency'] = 3 === strlen( $cur ) ? $cur : '';
	}
	if ( $v ) {
		flxcrm_save_settings( $v );
		FLXCRM_Contacts::flush_stats();
	}
	$s = flxcrm_settings();
	unset( $s['ai_tools'], $s['ai_write'], $s['ai_destructive'] );
	return $s;
}

/* ---------------------------------------------------------------- */
/* Kanallar                                                          */
/* ---------------------------------------------------------------- */

/** MCP için şema: yıkıcı araçlara confirm_token alanı. */
function flxcrm_tool_schema_for( array $t, $with_token ) {
	$schema = is_array( $t['schema'] ) ? $t['schema'] : array( 'type' => 'object' );
	if ( empty( $schema['properties'] ) ) {
		$schema['properties'] = array();
	}
	if ( $with_token ) {
		$schema['properties']['confirm_token'] = array( 'type' => 'string', 'description' => 'From the preview returned by the first call. Send only after the user approved.' );
	}
	if ( ! $schema['properties'] ) {
		$schema['properties'] = new stdClass();
	}
	return $schema;
}

/* Flux AI & MCP sunucusu. */
add_filter(
	'flxseo_mcp_tools',
	function ( $tools ) {
		foreach ( flxcrm_tools_enabled() as $name => $t ) {
			$destr          = 'destructive' === $t['kind'];
			$tools[ $name ] = array(
				'title'       => $t['title'],
				'description' => $t['description'] . ( $destr ? ' [Needs approval: the first call changes nothing and returns a preview with confirm_token; show it to the user, then repeat the call with confirm_token.]' : '' ),
				'readonly'    => 'read' === $t['kind'],
				'cap'         => flxcrm_tool_cap( $t ),
				'schema'      => flxcrm_tool_schema_for( $t, $destr ),
				'log'         => 'read' !== $t['kind'],
				'run'         => function ( $args ) use ( $name ) {
					return flxcrm_run_tool( $name, $args, array( 'channel' => 'mcp' ) );
				},
			);
		}
		return $tools;
	}
);

/*
 * Flux AI sohbeti. MCP'den gelen aynı adlı araçların yerine doğrudan çalışan sürüm konur: değişiklik yapan her araç
 * Flux AI onay kartında bekler, kullanıcı onaylayınca çalışır (kart onaydır). Toplu işlem yine önizleme anahtarı ister:
 * model kaç kişinin etkileneceğini önce göstermek zorunda kalır.
 */
add_filter(
	'flxai_tools',
	function ( $tools ) {
		foreach ( flxcrm_tools_enabled() as $name => $t ) {
			$bulk           = 'flxcrm_bulk' === $name;
			$tools[ $name ] = array(
				'title'       => $t['title'],
				'description' => $t['description'],
				'schema'      => flxcrm_tool_schema_for( $t, $bulk ),
				'readonly'    => 'read' === $t['kind'],
				'confirm'     => 'destructive' === $t['kind'],
				'cap'         => flxcrm_tool_cap( $t ),
				'source'      => 'flx-crm',
				'run'         => function ( $args, $ctx ) use ( $name, $t, $bulk ) {
					return flxcrm_run_tool(
						$name,
						$args,
						array(
							'channel'   => 'ai',
							'user_id'   => (int) ( $ctx['user_id'] ?? get_current_user_id() ),
							'confirmed' => 'destructive' === $t['kind'] && ! $bulk,
						)
					);
				},
			);
		}
		return $tools;
	}
);

/* Sohbet talimatı: CRM araçlarıyla nasıl çalışılacağı. */
add_filter(
	'flxai_chat_system',
	function ( $text ) {
		if ( ! flxcrm_tools_enabled() || ! flxcrm_can() ) {
			return $text;
		}
		return $text . "\n\nFlux CRM: call flxcrm_meta before editing (roles, tags, rule fields). Prefer archiving over deleting. Turn a consent ON only if the person explicitly gave it. For bulk changes call flxcrm_bulk_preview, tell the user how many contacts will change, then call flxcrm_bulk with the confirm_token. Link contacts with their url.";
	}
);
