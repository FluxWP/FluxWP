<?php
/**
 * Flux CRM: yolculuklar (otomatik kişi akışları). Bir tetikleyici (segment değişti, etiket, rol, olay,
 * yeni kişi) kişiyi akışa sokar; adımlar sırayla çalışır: bekle, e-posta / WhatsApp / SMS (Flux Mail),
 * etiket ekle/çıkar, koşul. Hedef olay (ör. bağış) gelince kişi akıştan "hedefe ulaştı" diye çıkar.
 *
 * Güvenlik kuralları:
 * - Yeni yolculuk TASLAK başlar; kimseye bir şey gitmez. Etkinleştirmek ayrı, bilinçli bir adımdır
 *   (AI/MCP'de onay ister).
 * - Mesaj yalnız o kanala izin vermiş kişiye gider (izin yoksa adım atlanır, zaman tünelinde görünür).
 * - Sessiz saatlerde (varsayılan 21:00–09:00, site saati) mesaj gönderilmez, sabaha kalır.
 * - Sıklık sınırı: bir kişiye tüm yolculuklardan 7 günde en çok N mesaj (varsayılan 3); aşılırsa ertesi güne.
 * - Kişi silinir, arşivlenir ya da kara listeye alınırsa akış biter.
 *
 * Cron'a güvenilmez (Hostinger): saatlik iş + CRM ekranı açılınca + istek sonunda (5 dk'da bir) işlenir.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Journeys {

	const BATCH = 100;

	public static function init() {
		add_action( 'flxcrm_segment_changed', array( __CLASS__, 'on_segment' ), 20, 3 );
		add_action( 'flxcrm_tag_added', array( __CLASS__, 'on_tag' ), 20, 2 );
		add_action( 'flxcrm_role_added', array( __CLASS__, 'on_role' ), 20, 2 );
		add_action( 'flxcrm_contact_created', array( __CLASS__, 'on_created' ), 20, 1 );
		add_action( 'flxcrm_event', array( __CLASS__, 'on_event' ), 20, 3 );
		add_action( 'flxcrm_contact_deleting', array( __CLASS__, 'on_deleted' ) );
		add_action( 'flxcrm_journeys_tick', array( __CLASS__, 'tick' ) );
		add_action( 'shutdown', array( __CLASS__, 'maybe_tick_later' ), 70 );
		add_filter(
			'flxcrm_event_types',
			static function ( $t ) {
				$t['journey']      = array( __( 'Journey message', 'flx-crm' ), 'send' );
				$t['journey_skip'] = array( __( 'Journey step skipped', 'flx-crm' ), 'note' );
				return $t;
			}
		);
		if ( ! wp_next_scheduled( 'flxcrm_journeys_tick' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'flxcrm_journeys_tick' );
		}
	}

	/* ------------------------------------------------------------ Tanımlar */

	public static function triggers() {
		return array(
			'segment' => __( 'Enters a value group (RFM)', 'flx-crm' ),
			'tag'     => __( 'Gets a tag', 'flx-crm' ),
			'role'    => __( 'Gets a role', 'flx-crm' ),
			'event'   => __( 'Something happens (timeline event)', 'flx-crm' ),
			'created' => __( 'Is added to the CRM', 'flx-crm' ),
		);
	}

	public static function step_types() {
		return array(
			'wait'     => __( 'Wait', 'flx-crm' ),
			'email'    => __( 'Send e-mail', 'flx-crm' ),
			'whatsapp' => __( 'Send WhatsApp', 'flx-crm' ),
			'sms'      => __( 'Send SMS', 'flx-crm' ),
			'tag'      => __( 'Add tag', 'flx-crm' ),
			'untag'    => __( 'Remove tag', 'flx-crm' ),
			'if'       => __( 'Continue only if', 'flx-crm' ),
		);
	}

	public static function conditions() {
		return array(
			'segment' => __( 'Value group is', 'flx-crm' ),
			'tag'     => __( 'Has tag', 'flx-crm' ),
			'no_tag'  => __( 'Does not have tag', 'flx-crm' ),
			'paid'    => __( 'Has paid since the journey started', 'flx-crm' ),
			'no_paid' => __( 'Has not paid since the journey started', 'flx-crm' ),
		);
	}

	public static function statuses() {
		return array(
			'draft'  => __( 'Draft', 'flx-crm' ),
			'active' => __( 'Active', 'flx-crm' ),
			'paused' => __( 'Paused', 'flx-crm' ),
		);
	}

	public static function defaults() {
		return array(
			'reenter_days' => 0,
			'goal'         => 'donation',
			'quiet_from'   => 21,
			'quiet_to'     => 9,
			'cap_week'     => 3,
		);
	}

	/* ------------------------------------------------------------ Kayıtlar */

	public static function all() {
		global $wpdb;
		$rows = (array) $wpdb->get_results( 'SELECT * FROM ' . flxcrm_table( 'journeys' ) . ' ORDER BY id DESC', ARRAY_A ); // phpcs:ignore WordPress.DB
		return array_map( array( __CLASS__, 'decode' ), $rows );
	}

	public static function get( $id ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxcrm_table( 'journeys' ) . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $r ? self::decode( $r ) : null;
	}

	private static function decode( array $r ) {
		foreach ( array( 'trigger_def', 'steps', 'opts', 'stats' ) as $k ) {
			$v       = json_decode( (string) $r[ $k ], true );
			$r[ $k ] = is_array( $v ) ? $v : array();
		}
		$r['opts']  += self::defaults();
		$r['stats'] += array( 'entered' => 0, 'done' => 0, 'goal' => 0, 'sent' => 0, 'skipped' => 0 );
		$r['id']     = (int) $r['id'];
		return $r;
	}

	/**
	 * Doğrular. Hatalıysa WP_Error.
	 *
	 * @return array|WP_Error [ name, trigger_def, steps, opts ]
	 */
	public static function clean( array $d ) {
		$name = mb_substr( trim( sanitize_text_field( (string) ( $d['name'] ?? '' ) ) ), 0, 190 );
		if ( '' === $name ) {
			return new WP_Error( 'flxcrm_journey', __( 'Give the journey a name.', 'flx-crm' ) );
		}
		$tr   = (array) ( $d['trigger'] ?? array() );
		$type = (string) ( $tr['type'] ?? '' );
		if ( ! isset( self::triggers()[ $type ] ) ) {
			return new WP_Error( 'flxcrm_journey', __( 'Pick what starts the journey.', 'flx-crm' ) );
		}
		$val = sanitize_text_field( (string) ( $tr['value'] ?? '' ) );
		if ( 'created' !== $type && '' === $val ) {
			return new WP_Error( 'flxcrm_journey', __( 'Pick the value group, tag, role or event that starts the journey.', 'flx-crm' ) );
		}
		if ( 'tag' === $type && ! ctype_digit( $val ) ) {
			$val = (string) FLXCRM_Tags::ensure( $val );
		}
		$steps = array();
		foreach ( array_values( (array) ( $d['steps'] ?? array() ) ) as $s ) {
			$s = (array) $s;
			$t = (string) ( $s['type'] ?? '' );
			if ( ! isset( self::step_types()[ $t ] ) ) {
				continue;
			}
			$st = array( 'type' => $t );
			switch ( $t ) {
				case 'wait':
					$st['days']  = max( 0, min( 365, (int) ( $s['days'] ?? 0 ) ) );
					$st['hours'] = max( 0, min( 23, (int) ( $s['hours'] ?? 0 ) ) );
					if ( ! $st['days'] && ! $st['hours'] ) {
						continue 2;
					}
					break;
				case 'email':
					$st['subject'] = mb_substr( trim( sanitize_text_field( (string) ( $s['subject'] ?? '' ) ) ), 0, 190 );
					$st['text']    = mb_substr( trim( sanitize_textarea_field( (string) ( $s['text'] ?? '' ) ) ), 0, 8000 );
					if ( '' === $st['subject'] || '' === $st['text'] ) {
						return new WP_Error( 'flxcrm_journey', __( 'Every e-mail step needs a subject and a text.', 'flx-crm' ) );
					}
					break;
				case 'whatsapp':
				case 'sms':
					$st['text'] = mb_substr( trim( sanitize_textarea_field( (string) ( $s['text'] ?? '' ) ) ), 0, 4000 );
					if ( '' === $st['text'] ) {
						return new WP_Error( 'flxcrm_journey', __( 'Every message step needs a text.', 'flx-crm' ) );
					}
					break;
				case 'tag':
				case 'untag':
					$tag = trim( sanitize_text_field( (string) ( $s['tag'] ?? '' ) ) );
					if ( '' === $tag ) {
						continue 2;
					}
					$st['tag'] = ctype_digit( $tag ) ? (int) $tag : (int) FLXCRM_Tags::ensure( $tag );
					break;
				case 'if':
					$c = (string) ( $s['cond'] ?? '' );
					if ( ! isset( self::conditions()[ $c ] ) ) {
						continue 2;
					}
					$st['cond']  = $c;
					$st['value'] = sanitize_text_field( (string) ( $s['value'] ?? '' ) );
					if ( in_array( $c, array( 'tag', 'no_tag' ), true ) && '' !== $st['value'] && ! ctype_digit( $st['value'] ) ) {
						$st['value'] = (string) FLXCRM_Tags::ensure( $st['value'] );
					}
					break;
			}
			$steps[] = $st;
			if ( count( $steps ) >= 30 ) {
				break;
			}
		}
		if ( ! array_filter(
			$steps,
			static function ( $s ) {
				return in_array( $s['type'], array( 'email', 'whatsapp', 'sms', 'tag', 'untag' ), true );
			}
		) ) {
			return new WP_Error( 'flxcrm_journey', __( 'Add at least one step that does something (message or tag).', 'flx-crm' ) );
		}
		$o    = (array) ( $d['opts'] ?? array() );
		$def  = self::defaults();
		$goal = sanitize_key( (string) ( $o['goal'] ?? $def['goal'] ) );
		$opts = array(
			'reenter_days' => max( 0, min( 3650, (int) ( $o['reenter_days'] ?? 0 ) ) ),
			'goal'         => '' === $goal || isset( flxcrm_event_types()[ $goal ] ) ? $goal : $def['goal'],
			'quiet_from'   => max( 0, min( 23, (int) ( $o['quiet_from'] ?? $def['quiet_from'] ) ) ),
			'quiet_to'     => max( 0, min( 23, (int) ( $o['quiet_to'] ?? $def['quiet_to'] ) ) ),
			'cap_week'     => max( 1, min( 14, (int) ( $o['cap_week'] ?? $def['cap_week'] ) ) ),
		);
		return array(
			'name'        => $name,
			'trigger_def' => array( 'type' => $type, 'value' => $val ),
			'steps'       => $steps,
			'opts'        => $opts,
		);
	}

	/**
	 * Kaydeder. Yeni yolculuk taslak olur. Etkin yolculuk düzenlenince duraklatılır (yanlış metin gitmesin);
	 * kişiler yerinde bekler, yeniden etkinleştirilince devam eder.
	 *
	 * @return int|WP_Error
	 */
	public static function save( array $d, $id = 0 ) {
		global $wpdb;
		$c = self::clean( $d );
		if ( is_wp_error( $c ) ) {
			return $c;
		}
		$row = array(
			'name'        => $c['name'],
			'trigger_def' => wp_json_encode( $c['trigger_def'] ),
			'steps'       => wp_json_encode( $c['steps'] ),
			'opts'        => wp_json_encode( $c['opts'] ),
			'updated_at'  => flxcrm_now(),
		);
		$t = flxcrm_table( 'journeys' );
		if ( $id ) {
			$cur = self::get( $id );
			if ( ! $cur ) {
				return new WP_Error( 'flxcrm_journey', __( 'Journey not found.', 'flx-crm' ) );
			}
			if ( 'active' === $cur['status'] ) {
				$row['status'] = 'paused';
			}
			$wpdb->update( $t, $row, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
			return (int) $id;
		}
		$row += array(
			'status'     => 'draft',
			'stats'      => wp_json_encode( array() ),
			'created_by' => get_current_user_id(),
			'created_at' => flxcrm_now(),
		);
		$wpdb->insert( $t, $row ); // phpcs:ignore WordPress.DB
		return (int) $wpdb->insert_id;
	}

	public static function set_status( $id, $status ) {
		global $wpdb;
		if ( ! in_array( $status, array( 'active', 'paused', 'draft' ), true ) || ! self::get( $id ) ) {
			return false;
		}
		$wpdb->update( flxcrm_table( 'journeys' ), array( 'status' => $status, 'updated_at' => flxcrm_now() ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
		return true;
	}

	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( flxcrm_table( 'journey_runs' ), array( 'journey_id' => (int) $id ) ); // phpcs:ignore WordPress.DB
		return (bool) $wpdb->delete( flxcrm_table( 'journeys' ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
	}

	private static function bump( $jid, $key, $n = 1 ) {
		global $wpdb;
		$j = self::get( $jid );
		if ( ! $j ) {
			return;
		}
		$j['stats'][ $key ] = (int) ( $j['stats'][ $key ] ?? 0 ) + $n;
		$wpdb->update( flxcrm_table( 'journeys' ), array( 'stats' => wp_json_encode( $j['stats'] ) ), array( 'id' => (int) $jid ) ); // phpcs:ignore WordPress.DB
	}

	/** Akıştaki kişi sayıları: active, done, goal, exited. */
	public static function run_counts( $jid ) {
		global $wpdb;
		$out = array( 'active' => 0, 'done' => 0, 'goal' => 0, 'exited' => 0 );
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS n FROM ' . flxcrm_table( 'journey_runs' ) . ' WHERE journey_id = %d GROUP BY status', (int) $jid ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB
			$out[ $r['status'] ] = (int) $r['n'];
		}
		return $out;
	}

	/* ------------------------------------------------------------ Tetikleyiciler */

	public static function on_segment( $cid, $seg, $from = '' ) {
		self::trigger( 'segment', (string) $seg, (int) $cid );
	}

	public static function on_tag( $cid, $tag_id ) {
		self::trigger( 'tag', (string) (int) $tag_id, (int) $cid );
	}

	public static function on_role( $cid, $role ) {
		self::trigger( 'role', (string) $role, (int) $cid );
	}

	public static function on_created( $cid ) {
		self::trigger( 'created', '', (int) $cid );
	}

	public static function on_event( $eid, $cid, $type ) {
		if ( 'journey' === $type || 'journey_skip' === $type ) {
			return;
		}
		self::reach_goal( (int) $cid, (string) $type );
		self::trigger( 'event', (string) $type, (int) $cid );
	}

	public static function on_deleted( $cid ) {
		global $wpdb;
		$wpdb->delete( flxcrm_table( 'journey_runs' ), array( 'contact_id' => (int) $cid ) ); // phpcs:ignore WordPress.DB
	}

	/** Etkin yolculuklar (istek içinde önbellekli). */
	private static function active() {
		static $list = null;
		if ( null === $list ) {
			$list = array_filter(
				self::all(),
				static function ( $j ) {
					return 'active' === $j['status'];
				}
			);
		}
		return $list;
	}

	private static function trigger( $type, $value, $cid ) {
		if ( ! $cid ) {
			return;
		}
		foreach ( self::active() as $j ) {
			if ( $j['trigger_def']['type'] === $type && ( 'created' === $type || (string) $j['trigger_def']['value'] === $value ) ) {
				self::enter( $j, $cid );
			}
		}
	}

	/** Kişiyi akışa sokar (zaten içindeyse ya da yeniden giriş süresi dolmadıysa girmez). */
	public static function enter( array $j, $cid ) {
		global $wpdb;
		$t    = flxcrm_table( 'journey_runs' );
		$last = $wpdb->get_row( $wpdb->prepare( "SELECT status, started_at FROM $t WHERE journey_id = %d AND contact_id = %d ORDER BY id DESC LIMIT 1", $j['id'], (int) $cid ), ARRAY_A ); // phpcs:ignore WordPress.DB
		if ( $last ) {
			if ( 'active' === $last['status'] ) {
				return false;
			}
			$days = (int) $j['opts']['reenter_days'];
			if ( ! $days || strtotime( $last['started_at'] . ' UTC' ) > time() - $days * DAY_IN_SECONDS ) {
				return false;
			}
		}
		$now = flxcrm_now();
		$wpdb->insert( $t, array( 'journey_id' => $j['id'], 'contact_id' => (int) $cid, 'step' => 0, 'status' => 'active', 'next_at' => $now, 'started_at' => $now, 'updated_at' => $now ) ); // phpcs:ignore WordPress.DB
		self::bump( $j['id'], 'entered' );
		set_transient( 'flxcrm_journeys_due', 1, HOUR_IN_SECONDS );
		return true;
	}

	/** Hedef olay geldi: o hedefi bekleyen akışlar biter. */
	private static function reach_goal( $cid, $type ) {
		global $wpdb;
		$t = flxcrm_table( 'journey_runs' );
		foreach ( self::active() as $j ) {
			if ( '' === (string) $j['opts']['goal'] || $j['opts']['goal'] !== $type ) {
				continue;
			}
			$n = $wpdb->query( $wpdb->prepare( "UPDATE $t SET status = 'goal', reason = 'goal', updated_at = %s WHERE journey_id = %d AND contact_id = %d AND status = 'active'", flxcrm_now(), $j['id'], $cid ) ); // phpcs:ignore WordPress.DB
			if ( $n ) {
				self::bump( $j['id'], 'goal', (int) $n );
			}
		}
	}

	/* ------------------------------------------------------------ İşleme */

	/** İstek sonunda, bekleyen iş varsa ve 5 dk geçtiyse işle (yanıt gönderildikten sonra). */
	public static function maybe_tick_later() {
		if ( ! get_transient( 'flxcrm_journeys_due' ) || get_transient( 'flxcrm_journeys_recent' ) || wp_doing_ajax() ) {
			return;
		}
		set_transient( 'flxcrm_journeys_recent', 1, 5 * MINUTE_IN_SECONDS );
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
		}
		self::tick();
	}

	/** Vadesi gelen akışları işler. @return int işlenen */
	public static function tick() {
		global $wpdb;
		if ( get_transient( 'flxcrm_journeys_lock' ) ) {
			return 0;
		}
		set_transient( 'flxcrm_journeys_lock', 1, 2 * MINUTE_IN_SECONDS );
		$t     = flxcrm_table( 'journey_runs' );
		$start = time();
		$done  = 0;
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE status = 'active' AND next_at <= %s ORDER BY next_at LIMIT %d", flxcrm_now(), self::BATCH ), ARRAY_A ); // phpcs:ignore WordPress.DB
		foreach ( $rows as $r ) {
			if ( time() - $start > 40 ) {
				break; // PHP ~60 sn'de kesilir
			}
			self::advance( $r );
			++$done;
		}
		$left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE status = 'active' AND next_at <= %s", flxcrm_now() ) ); // phpcs:ignore WordPress.DB
		if ( ! $left ) {
			delete_transient( 'flxcrm_journeys_due' );
		}
		delete_transient( 'flxcrm_journeys_lock' );
		return $done;
	}

	/** Bir kişinin akışını bir sonraki bekleme ya da sona kadar ilerletir. */
	public static function advance( array $r ) {
		global $wpdb;
		$t = flxcrm_table( 'journey_runs' );
		$j = self::get( (int) $r['journey_id'] );
		if ( ! $j ) {
			$wpdb->delete( $t, array( 'id' => (int) $r['id'] ) ); // phpcs:ignore WordPress.DB
			return;
		}
		if ( 'active' !== $j['status'] ) {
			return; // duraklatıldı: kişi yerinde bekler
		}
		$c = FLXCRM_Contacts::get( (int) $r['contact_id'] );
		if ( ! $c || 'active' !== ( $c['status'] ?? 'active' ) ) {
			self::finish( $r, 'exited', 'contact' );
			return;
		}
		$step = (int) $r['step'];
		$n    = count( $j['steps'] );
		for ( $guard = 0; $guard < 40 && $step < $n; $guard++ ) {
			$s = $j['steps'][ $step ];
			if ( 'wait' === $s['type'] ) {
				$next = time() + (int) $s['days'] * DAY_IN_SECONDS + (int) $s['hours'] * HOUR_IN_SECONDS;
				$wpdb->update( $t, array( 'step' => $step + 1, 'next_at' => gmdate( 'Y-m-d H:i:s', $next ), 'updated_at' => flxcrm_now() ), array( 'id' => (int) $r['id'] ) ); // phpcs:ignore WordPress.DB
				return;
			}
			if ( 'if' === $s['type'] ) {
				if ( ! self::check( $s, $c, $r ) ) {
					self::finish( array( 'step' => $step ) + $r, 'exited', 'condition' );
					return;
				}
				++$step;
				continue;
			}
			if ( in_array( $s['type'], array( 'tag', 'untag' ), true ) ) {
				if ( 'tag' === $s['type'] ) {
					FLXCRM_Tags::attach( (int) $c['id'], (int) $s['tag'] );
				} else {
					FLXCRM_Tags::detach( (int) $c['id'], (int) $s['tag'] );
				}
				++$step;
				continue;
			}
			/* Mesaj: sessiz saat ve sıklık sınırı, sonra izin. */
			$wait = self::hold_until( $j, (int) $c['id'] );
			if ( $wait ) {
				$wpdb->update( $t, array( 'step' => $step, 'next_at' => gmdate( 'Y-m-d H:i:s', $wait ), 'updated_at' => flxcrm_now() ), array( 'id' => (int) $r['id'] ) ); // phpcs:ignore WordPress.DB
				return;
			}
			$res = self::send( $j, $s, $c, $step );
			self::bump( $j['id'], true === $res ? 'sent' : 'skipped' );
			++$step;
		}
		self::finish( array( 'step' => $step ) + $r, 'done', '' );
	}

	private static function finish( array $r, $status, $reason ) {
		global $wpdb;
		$wpdb->update( flxcrm_table( 'journey_runs' ), array( 'status' => $status, 'reason' => $reason, 'step' => (int) $r['step'], 'updated_at' => flxcrm_now() ), array( 'id' => (int) $r['id'] ) ); // phpcs:ignore WordPress.DB
		if ( 'done' === $status ) {
			self::bump( (int) $r['journey_id'], 'done' );
		}
	}

	private static function check( array $s, array $c, array $r ) {
		global $wpdb;
		switch ( $s['cond'] ) {
			case 'segment':
				return (string) ( $c['segment'] ?? '' ) === (string) $s['value'];
			case 'tag':
			case 'no_tag':
				$has = in_array( (int) $s['value'], array_map( 'intval', wp_list_pluck( FLXCRM_Tags::of( (int) $c['id'] ), 'id' ) ), true );
				return 'tag' === $s['cond'] ? $has : ! $has;
			case 'paid':
			case 'no_paid':
				$paid = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . flxcrm_table( 'events' ) . " WHERE contact_id = %d AND paid = 1 AND at >= %s LIMIT 1", (int) $c['id'], $r['started_at'] ) ); // phpcs:ignore WordPress.DB
				return 'paid' === $s['cond'] ? $paid : ! $paid;
		}
		return true;
	}

	/**
	 * Gönderim şimdi yapılamıyorsa ne zamana ertelenecek (UTC ts), yapılabiliyorsa 0.
	 * Sessiz saat site saatine göre; sıklık sınırı tüm yolculuklardan son 7 gün.
	 */
	public static function hold_until( array $j, $cid ) {
		global $wpdb;
		$tz   = wp_timezone();
		$now  = new DateTime( 'now', $tz );
		$h    = (int) $now->format( 'G' );
		$from = (int) $j['opts']['quiet_from'];
		$to   = (int) $j['opts']['quiet_to'];
		$quiet = $from === $to ? false : ( $from > $to ? ( $h >= $from || $h < $to ) : ( $h >= $from && $h < $to ) );
		if ( $quiet ) {
			$wake = ( clone $now )->setTime( $to, 0 );
			if ( $wake <= $now ) {
				$wake->modify( '+1 day' );
			}
			return $wake->getTimestamp();
		}
		$sent = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxcrm_table( 'events' ) . " WHERE contact_id = %d AND type = 'journey' AND at >= %s", (int) $cid, gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB
		if ( $sent >= (int) $j['opts']['cap_week'] ) {
			return time() + DAY_IN_SECONDS;
		}
		return 0;
	}

	/** {first_name}, {name}, {site}. */
	public static function fill( $text, array $c ) {
		$name  = trim( (string) ( $c['name'] ?? '' ) );
		$first = '' !== $name ? (string) preg_split( '/\s+/u', $name )[0] : '';
		return strtr(
			(string) $text,
			array(
				'{first_name}' => $first,
				'{name}'       => $name,
				'{site}'       => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			)
		);
	}

	/**
	 * Mesaj adımı. İzin yoksa ya da Flux Mail yoksa atlanır.
	 *
	 * @return true|string atlama nedeni
	 */
	private static function send( array $j, array $s, array $c, $step ) {
		$ch     = $s['type'];
		$labels = self::step_types();
		$why    = '';
		if ( ( 'email' === $ch && ! function_exists( 'flxi_send_email' ) ) || ( 'email' !== $ch && ! function_exists( 'flxi_send_message' ) ) ) {
			$why = __( 'Flux Mail is not active for this channel.', 'flx-crm' );
		} elseif ( flxcrm_mail_requires_consent( $ch ) && ! (int) ( $c[ 'consent_' . flxcrm_channel_consent( $ch ) ] ?? 0 ) ) {
			$why = __( 'No permission for this channel.', 'flx-crm' );
		} elseif ( '' === (string) ( 'email' === $ch ? $c['email'] : $c['phone'] ) ) {
			$why = 'email' === $ch ? __( 'No e-mail address.', 'flx-crm' ) : __( 'No phone number.', 'flx-crm' );
		}
		$ok = false;
		if ( '' === $why ) {
			$text = self::fill( $s['text'], $c );
			if ( 'email' === $ch ) {
				$subject = self::fill( $s['subject'], $c );
				$ok      = (bool) flxi_send_email( array( 'to' => (string) $c['email'], 'subject' => $subject, 'heading' => $subject, 'body' => wpautop( esc_html( $text ) ) ) );
			} else {
				$r  = flxi_send_message( $ch, (string) $c['phone'], $text, array( 'source' => 'flx-crm-journey' ) );
				$ok = ! empty( $r['ok'] );
				if ( ! $ok ) {
					$why = (string) ( $r['error'] ?? __( 'Not sent.', 'flx-crm' ) );
				}
			}
			if ( ! $ok && '' === $why ) {
				$why = __( 'Not sent.', 'flx-crm' );
			}
		}
		/* Zaman tüneli: gönderilen (journey) sıklık sınırında sayılır; atlanan journey_skip olarak görünür. */
		FLXCRM_Events::add(
			(int) $c['id'],
			$ok ? 'journey' : 'journey_skip',
			array(
				/* translators: 1: journey name, 2: step label, 3: reason */
				'title' => $ok ? sprintf( '%1$s · %2$s', $j['name'], $labels[ $ch ] ) : sprintf( __( '%1$s · %2$s skipped: %3$s', 'flx-crm' ), $j['name'], $labels[ $ch ], $why ),
				'uniq'  => 'journey:' . $j['id'] . ':' . (int) $c['id'] . ':' . $step . ':' . gmdate( 'YmdH' ),
				'data'  => array( 'journey' => $j['id'], 'step' => $step, 'channel' => $ch, 'skipped' => $ok ? '' : $why ),
			)
		);
		return $ok ? true : $why;
	}

	/** "Değer grubu: Yeni olunca · 5 adım · hedef: Bağış". */
	public static function describe( array $j ) {
		$tr  = $j['trigger_def'];
		$val = (string) ( $tr['value'] ?? '' );
		switch ( $tr['type'] ?? '' ) {
			case 'segment':
				$val = flxcrm_segment_label( $val );
				break;
			case 'role':
				$val = flxcrm_role_label( $val );
				break;
			case 'event':
				$val = flxcrm_event_label( $val );
				break;
			case 'tag':
				foreach ( FLXCRM_Tags::all() as $t ) {
					if ( (int) $t['id'] === (int) $val ) {
						$val = $t['name'];
					}
				}
				break;
		}
		$parts = array( trim( ( self::triggers()[ $tr['type'] ?? '' ] ?? '' ) . ( '' !== $val ? ': ' . $val : '' ) ) );
		/* translators: %d: steps */
		$parts[] = sprintf( _n( '%d step', '%d steps', count( $j['steps'] ), 'flx-crm' ), count( $j['steps'] ) );
		if ( '' !== (string) $j['opts']['goal'] ) {
			/* translators: %s: event type */
			$parts[] = sprintf( __( 'goal: %s', 'flx-crm' ), flxcrm_event_label( $j['opts']['goal'] ) );
		}
		return implode( ' · ', $parts );
	}

	/* ------------------------------------------------------------ Hazır şablonlar */

	public static function templates() {
		return array(
			'welcome' => array(
				'name'    => __( 'Welcome new donors', 'flx-crm' ),
				'trigger' => array( 'type' => 'segment', 'value' => 'new' ),
				'steps'   => array(
					array( 'type' => 'wait', 'days' => 1 ),
					array( 'type' => 'email', 'subject' => __( 'Thank you, {first_name}', 'flx-crm' ), 'text' => __( "Dear {first_name},\n\nThank you for your first donation to {site}. Here is what your support makes possible…", 'flx-crm' ) ),
					array( 'type' => 'wait', 'days' => 30 ),
					array( 'type' => 'if', 'cond' => 'no_paid' ),
					array( 'type' => 'whatsapp', 'text' => __( 'Hello {first_name}, a short update from {site} on the work your donation supported. Thank you for being with us.', 'flx-crm' ) ),
				),
				'opts'    => array( 'goal' => '', 'reenter_days' => 0 ),
			),
			'winback' => array(
				'name'    => __( 'Win back donors at risk', 'flx-crm' ),
				'trigger' => array( 'type' => 'segment', 'value' => 'at_risk' ),
				'steps'   => array(
					array( 'type' => 'email', 'subject' => __( 'We miss you, {first_name}', 'flx-crm' ), 'text' => __( "Dear {first_name},\n\nIt has been a while. This is what we have been doing at {site} since your last donation…", 'flx-crm' ) ),
					array( 'type' => 'wait', 'days' => 14 ),
					array( 'type' => 'whatsapp', 'text' => __( 'Hello {first_name}, our new campaign at {site} has started. Would you like to support it again?', 'flx-crm' ) ),
					array( 'type' => 'wait', 'days' => 14 ),
					array( 'type' => 'tag', 'tag' => __( 'Win-back: no answer', 'flx-crm' ) ),
				),
				'opts'    => array( 'goal' => 'donation', 'reenter_days' => 180 ),
			),
		);
	}
}

/** RFM anahtarı => ad. */
function flxcrm_rfm_segments_labels() {
	return array_map(
		static function ( $s ) {
			return $s[0];
		},
		flxcrm_rfm_segments()
	);
}
