<?php
/**
 * Event messages (0.6.0): an extra message to everyone tied to an event (e.g. a thank-you with a photo after it),
 * by e-mail, WhatsApp and/or SMS, now or at a planned time. Sending is Flux Mail's bulk send (flxi_create_campaign):
 * its queue, delays between WhatsApp messages, blocklist and daily mail limit apply as for any campaign.
 *
 * Who receives it:
 * - "info" (event information / thank-you): every person of the event with an address for that channel.
 * - "marketing" (promotion): only people who allowed that channel (consent_email / _sms / _whatsapp).
 * The blocklist and e-mail unsubscribes are always respected (Flux Mail checks them at send time).
 *
 * Flux Mail recipient sources: `flxcrm_event:<id>` (consent required, shown on Flux Mail › Send) and
 * `flxcrm_event_info:<id>` (no consent requirement, used by this screen only).
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Event_Messages {

	const OPT = 'flxcrm_event_msgs';
	const MAX = 50; // sends kept per event

	public static function init() {
		add_filter( 'flxi_recipient_sources', array( __CLASS__, 'sources' ), 20 );
		add_filter( 'flxi_recipients_from_source', array( __CLASS__, 'from_source' ), 10, 3 );
		add_filter( 'flxi_recipient_source_info', array( __CLASS__, 'source_info' ), 10, 3 );
		add_action( 'admin_post_flxcrm_event_msg', array( __CLASS__, 'post_send' ) );
		add_action( 'admin_post_flxcrm_event_msg_cancel', array( __CLASS__, 'post_cancel' ) );
		add_action( 'flx_event_deleting', array( __CLASS__, 'forget' ) );
	}

	/** Flux Mail is there for this channel. */
	public static function ready( $channel = '' ) {
		if ( ! function_exists( 'flxi_create_campaign' ) || ! function_exists( 'flxi_collect_recipients' ) ) {
			return false;
		}
		if ( '' === $channel || 'email' === $channel ) {
			return true;
		}
		return function_exists( 'flxi_resolve_connection' ) && (bool) flxi_resolve_connection( $channel, 0 );
	}

	/** Flux Mail campaigns carry a WhatsApp attachment (`media`, `file_name`). Flux Mail declares it in `flxi_campaign_features`. */
	public static function mail_has_media() {
		return in_array( 'media', (array) apply_filters( 'flxi_campaign_features', array() ), true );
	}

	public static function channels() {
		return array(
			'whatsapp' => __( 'WhatsApp', 'flx-crm' ),
			'email'    => __( 'E-mail', 'flx-crm' ),
			'sms'      => __( 'SMS', 'flx-crm' ),
		);
	}

	/* ------------------------------------------------------------ */
	/* Recipients                                                    */
	/* ------------------------------------------------------------ */

	/** Event source key → [ event id, needs consent ] or null. */
	private static function parse( $src ) {
		$src = (string) $src;
		if ( 0 === strpos( $src, 'flxcrm_event_info:' ) ) {
			return array( (int) substr( $src, 18 ), false );
		}
		if ( 0 === strpos( $src, 'flxcrm_event:' ) ) {
			return array( (int) substr( $src, 13 ), true );
		}
		return null;
	}

	/** Shared people rows of an event. */
	public static function people( $event_id ) {
		if ( ! function_exists( 'flx_event_people' ) || ! function_exists( 'flx_people_query' ) ) {
			return array();
		}
		$ids = array_keys( flx_event_people( (int) $event_id ) );
		$out = array();
		foreach ( array_chunk( $ids, 500 ) as $chunk ) {
			$out = array_merge( $out, flx_people_query( array( 'ids' => $chunk, 'per_page' => 500 ) ) );
		}
		return $out;
	}

	/**
	 * Who gets it on one channel.
	 *
	 * @return array [ 'list' => [ [name,email,phone] ], 'total' => people, 'no_address' => n, 'no_consent' => n ]
	 */
	public static function audience( $event_id, $channel, $consent ) {
		$res = array( 'list' => array(), 'total' => 0, 'no_address' => 0, 'no_consent' => 0 );
		$col = 'email' === $channel ? 'email' : 'phone';
		foreach ( self::people( $event_id ) as $p ) {
			++$res['total'];
			if ( '' === trim( (string) $p[ $col ] ) ) {
				++$res['no_address'];
				continue;
			}
			if ( $consent && empty( $p[ 'consent_' . flxcrm_channel_consent( $channel ) ] ) ) {
				++$res['no_consent'];
				continue;
			}
			$res['list'][] = array( 'id' => (int) $p['id'], 'name' => (string) $p['name'], 'email' => (string) $p['email'], 'phone' => (string) $p['phone'] );
		}
		return $res;
	}

	/** Flux Mail › Send: open events (and those closed in the last 60 days) as recipient sources. */
	public static function sources( $out ) {
		if ( ! flxcrm_can() || ! function_exists( 'flx_events_query' ) ) {
			return $out;
		}
		$since = gmdate( 'Y-m-d', time() - 60 * DAY_IN_SECONDS );
		foreach ( array_merge( flx_events_query( array( 'status' => 'open' ) ), flx_events_query( array( 'status' => 'closed' ) ) ) as $e ) {
			if ( 'closed' === $e['status'] && (string) $e['event_date'] < $since ) {
				continue;
			}
			/* translators: 1: event name, 2: number of people */
			$out[ 'flxcrm_event:' . (int) $e['id'] ] = sprintf( __( 'CRM event: %1$s (%2$s)', 'flx-crm' ), $e['name'], number_format_i18n( count( flx_event_people( (int) $e['id'] ) ) ) );
		}
		return $out;
	}

	public static function from_source( $list, $src, $channel ) {
		$p = self::parse( $src );
		if ( ! $p ) {
			return $list;
		}
		$consent = $p[1] && flxcrm_mail_requires_consent( $channel );
		return array_merge( (array) $list, self::audience( $p[0], $channel, $consent )['list'] );
	}

	public static function source_info( $info, $src, $channel ) {
		$p = self::parse( $src );
		if ( ! $p ) {
			return $info;
		}
		$a = self::audience( $p[0], $channel, $p[1] && flxcrm_mail_requires_consent( $channel ) );
		return array( 'total' => $a['total'] - $a['no_address'], 'no_consent' => $a['no_consent'] );
	}


	/* ------------------------------------------------------------ */
	/* Message variables (0.7.0)                                     */
	/* ------------------------------------------------------------ */

	/** Flux Mail keeps per-recipient values on a campaign (`flxi_campaign_features` contains `recipient_vars`). */
	public static function mail_has_recipient_vars() {
		return in_array( 'recipient_vars', (array) apply_filters( 'flxi_campaign_features', array() ), true );
	}

	/** Same for everyone: written into the text before it goes to Flux Mail. key => [ label, value ]. */
	public static function event_vars( array $e ) {
		$date = '' !== (string) $e['event_date'] ? flx_date( $e['event_date'] . ' 12:00:00', get_option( 'date_format' ) ) : '';
		$out  = array(
			'etkinlik'        => array( __( 'Event name', 'flx-crm' ), (string) $e['name'] ),
			'etkinlik_tarihi' => array( __( 'Event date', 'flx-crm' ), $date ),
			'etkinlik_saati'  => array( __( 'Event time', 'flx-crm' ), (string) $e['event_time'] ),
			'etkinlik_yeri'   => array( __( 'Event place', 'flx-crm' ), '' !== (string) $e['loc_name'] ? (string) $e['loc_name'] : (string) $e['loc_address'] ),
			'etkinlik_link'   => array( __( 'Event page', 'flx-crm' ), (int) $e['post_id'] && 'publish' === get_post_status( (int) $e['post_id'] ) ? (string) get_permalink( (int) $e['post_id'] ) : '' ),
		);
		/**
		 * Event-wide message variables: key => [ label, value ].
		 *
		 * @param array $out Variables.
		 * @param array $e   Event row.
		 */
		return (array) apply_filters( 'flx_event_message_vars', $out, $e );
	}

	/**
	 * Per person: key => [ 'label' => …, 'sample' => …, 'values' => [ person_id => value ] ]. {ad} and {ad_soyad} are
	 * always there (Flux Mail fills them from the name); packages add their own through `flx_event_vars`
	 * (Flux Certificates: {fidan}, {sertifika_sayisi}, {sertifika_link}, {sertifika_no}).
	 */
	public static function person_vars( $event_id, array $people ) {
		$ad = array();
		$fn = array();
		foreach ( $people as $p ) {
			$fn[ (int) $p['id'] ] = (string) $p['name'];
			$ad[ (int) $p['id'] ] = '' !== trim( (string) $p['name'] ) ? preg_split( '/\s+/u', trim( (string) $p['name'] ) )[0] : '';
		}
		$out = (array) apply_filters( 'flx_event_vars', array(), (int) $event_id, array_keys( $fn ) );
		foreach ( $out as $k => $v ) {
			if ( ! preg_match( '/^[a-z0-9_]+$/', (string) $k ) || ! is_array( $v ) ) {
				unset( $out[ $k ] );
				continue;
			}
			$out[ $k ] = array(
				'label'  => (string) ( $v['label'] ?? $k ),
				'sample' => (string) ( $v['sample'] ?? '' ),
				'values' => array_map( 'strval', (array) ( $v['values'] ?? array() ) ),
			);
		}
		return array(
			'ad'       => array( 'label' => __( 'First name', 'flx-crm' ), 'sample' => 'Ayşe', 'values' => $ad, 'builtin' => true ),
			'ad_soyad' => array( 'label' => __( 'Full name', 'flx-crm' ), 'sample' => 'Ayşe Yılmaz', 'values' => $fn, 'builtin' => true ),
		) + $out;
	}

	/** Replaces {key} with event-wide values; other tokens stay for Flux Mail. */
	public static function fill_event_vars( $text, array $e ) {
		$vars = self::event_vars( $e );
		return preg_replace_callback(
			'/\{([a-z0-9_]+)\}/i',
			function ( $m ) use ( $vars ) {
				$k = strtolower( $m[1] );
				return isset( $vars[ $k ] ) ? (string) $vars[ $k ][1] : $m[0];
			},
			(string) $text
		);
	}

	/** Person-variable keys (not built in) used in the text. */
	public static function used_person_vars( $text, array $pvars ) {
		preg_match_all( '/\{([a-z0-9_]+)\}/i', (string) $text, $m );
		$used = array();
		foreach ( array_unique( array_map( 'strtolower', $m[1] ) ) as $k ) {
			if ( isset( $pvars[ $k ] ) && empty( $pvars[ $k ]['builtin'] ) ) {
				$used[] = $k;
			}
		}
		return $used;
	}

	/** Address the way Flux Mail stores it, to match a recipient back to a person. */
	private static function addr( $channel, array $p ) {
		if ( 'email' === $channel ) {
			return strtolower( trim( (string) $p['email'] ) );
		}
		return function_exists( 'flxi_normalize_phone' ) ? (string) flxi_normalize_phone( $p['phone'] ) : preg_replace( '/\D+/', '', (string) $p['phone'] );
	}

	/* ------------------------------------------------------------ */
	/* Sending                                                       */
	/* ------------------------------------------------------------ */

	/**
	 * Queues the message on every chosen channel.
	 *
	 * @param int   $event_id Event.
	 * @param array $a channels[], kind (info|marketing), text, subject, media_id, send_at ('' or Y-m-d\TH:i, site time).
	 * @return array|WP_Error [ 'campaigns' => [ channel => id ], 'counts' => [ channel => n ], 'skipped' => [ channel => reason ] ]
	 */
	public static function send( $event_id, array $a ) {
		$e = flx_event_get( (int) $event_id );
		if ( ! $e ) {
			return new WP_Error( 'flxcrm_event', __( 'Event not found.', 'flx-crm' ) );
		}
		if ( ! self::ready() ) {
			return new WP_Error( 'flxcrm_mail', __( 'Flux Mail is not active; messages are sent through it.', 'flx-crm' ) );
		}
		$channels = array_values( array_intersect( array_keys( self::channels() ), array_map( 'sanitize_key', (array) ( $a['channels'] ?? array() ) ) ) );
		if ( ! $channels ) {
			return new WP_Error( 'flxcrm_channels', __( 'Pick at least one channel.', 'flx-crm' ) );
		}
		$text = trim( sanitize_textarea_field( (string) ( $a['text'] ?? '' ) ) );
		if ( '' === $text ) {
			return new WP_Error( 'flxcrm_text', __( 'Write the message.', 'flx-crm' ) );
		}
		$subject = trim( sanitize_text_field( (string) ( $a['subject'] ?? '' ) ) );
		if ( in_array( 'email', $channels, true ) && '' === $subject ) {
			$subject = $e['name'];
		}
		$text    = self::fill_event_vars( $text, $e );
		$subject = self::fill_event_vars( $subject, $e );
		$pvars   = array();
		$used    = array();
		if ( preg_match( '/\{[a-z0-9_]+\}/i', $text . $subject ) ) {
			$pvars = self::person_vars( (int) $e['id'], self::people( (int) $e['id'] ) );
			$used  = self::used_person_vars( $text . ' ' . $subject, $pvars );
			if ( $used && ! self::mail_has_recipient_vars() ) {
				/* translators: %s: variable list */
				return new WP_Error( 'flxcrm_vars', sprintf( __( 'Personal values (%s) need a newer Flux Mail that keeps a value for each recipient. Update Flux Mail or take them out of the text.', 'flx-crm' ), '{' . implode( '}, {', $used ) . '}' ) );
			}
		}
		$kind    = 'marketing' === ( $a['kind'] ?? '' ) ? 'marketing' : 'info';
		$send_at = trim( (string) ( $a['send_at'] ?? '' ) );
		$media   = absint( $a['media_id'] ?? 0 );
		$url     = $media ? (string) wp_get_attachment_url( $media ) : '';
		$fname   = $url ? wp_basename( (string) get_attached_file( $media ) ) : '';
		$is_img  = $url && wp_attachment_is_image( $media );
		$src     = ( 'marketing' === $kind ? 'flxcrm_event:' : 'flxcrm_event_info:' ) . (int) $e['id'];

		$out = array( 'campaigns' => array(), 'counts' => array(), 'skipped' => array() );
		foreach ( $channels as $ch ) {
			if ( ! self::ready( $ch ) ) {
				$out['skipped'][ $ch ] = __( 'No active connection for this channel in Flux Mail.', 'flx-crm' );
				continue;
			}
			$rec = flxi_collect_recipients( array( $src ), '', $ch );
			if ( ! $rec ) {
				$out['skipped'][ $ch ] = __( 'No one to send to on this channel.', 'flx-crm' );
				continue;
			}
			if ( $used ) { // each recipient carries their own values
				$by = array();
				foreach ( self::people( (int) $e['id'] ) as $p ) {
					$k = self::addr( $ch, $p );
					if ( '' !== $k && ! isset( $by[ $k ] ) ) {
						$by[ $k ] = (int) $p['id'];
					}
				}
				foreach ( $rec as &$r ) {
					$pid = $by[ (string) $r['to'] ] ?? 0;
					$v   = array();
					foreach ( $used as $k ) {
						$v[ $k ] = $pid ? ( $pvars[ $k ]['values'][ $pid ] ?? '' ) : '';
					}
					$r['vars'] = $v;
				}
				unset( $r );
			}
			$data = array(
				/* translators: 1: event name, 2: channel */
				'name'        => sprintf( __( '%1$s · event message (%2$s)', 'flx-crm' ), $e['name'], self::channels()[ $ch ] ),
				'channel'     => $ch,
				'send_at'     => $send_at,
				'kind'        => $kind,
				'ref'         => 'flxcrm_event:' . (int) $e['id'],
				'unsubscribe' => 'marketing' === $kind,
			);
			if ( 'email' === $ch ) {
				$body = wpautop( esc_html( $text ) );
				if ( $is_img ) {
					$body = '<p><img src="' . esc_url( $url ) . '" alt="" style="max-width:100%;height:auto;border-radius:8px"></p>' . $body;
				} elseif ( $url ) {
					$data['button_text'] = __( 'Open the file', 'flx-crm' );
					$data['button_url']  = $url;
				}
				$data += array( 'subject' => $subject, 'heading' => $subject, 'body' => $body );
			} else {
				$data['text'] = $text;
				if ( 'whatsapp' === $ch && $url ) {
					if ( self::mail_has_media() ) {
						$data['media']     = $url;
						$data['file_name'] = $fname;
					} else {
						$data['text'] .= "\n\n" . $url; // older Flux Mail: the file goes as a link
					}
				} elseif ( 'sms' === $ch && $url ) {
					$data['text'] .= "\n" . $url;
				}
			}
			$cid = flxi_create_campaign( $data, $rec );
			if ( is_wp_error( $cid ) ) {
				if ( 'flxi_schedule' === $cid->get_error_code() ) {
					return $cid; // same for every channel
				}
				$out['skipped'][ $ch ] = $cid->get_error_message();
				continue;
			}
			$out['campaigns'][ $ch ] = (int) $cid;
			$out['counts'][ $ch ]    = count( $rec );
		}
		if ( ! $out['campaigns'] ) {
			return new WP_Error( 'flxcrm_none', implode( ' ', array_map( function ( $ch, $why ) { return self::channels()[ $ch ] . ': ' . $why; }, array_keys( $out['skipped'] ), $out['skipped'] ) ) );
		}
		self::remember(
			(int) $e['id'],
			array(
				'at'        => time(),
				'by'        => get_current_user_id(),
				'kind'      => $kind,
				'send_at'   => $send_at,
				'text'      => mb_substr( $text, 0, 300 ),
				'media'     => $media,
				'campaigns' => $out['campaigns'],
			)
		);
		/**
		 * After an event message was queued.
		 *
		 * @param int   $event_id Event.
		 * @param array $out      campaigns, counts, skipped.
		 * @param array $a        The request.
		 */
		do_action( 'flxcrm_event_message_queued', (int) $e['id'], $out, $a );
		return $out;
	}

	private static function all() {
		$all = get_option( self::OPT, array() );
		return is_array( $all ) ? $all : array();
	}

	private static function remember( $event_id, array $row ) {
		$all              = self::all();
		$list             = isset( $all[ $event_id ] ) ? (array) $all[ $event_id ] : array();
		$row['id']        = wp_generate_password( 8, false );
		array_unshift( $list, $row );
		$all[ $event_id ] = array_slice( $list, 0, self::MAX );
		update_option( self::OPT, $all, false );
	}

	public static function forget( $event_id ) {
		$all = self::all();
		unset( $all[ (int) $event_id ] );
		update_option( self::OPT, $all, false );
	}

	/** Sends of an event, newest first, with their Flux Mail state. */
	public static function history( $event_id ) {
		$all = self::all();
		$out = array();
		foreach ( (array) ( $all[ (int) $event_id ] ?? array() ) as $row ) {
			$row['state'] = array();
			foreach ( (array) $row['campaigns'] as $ch => $cid ) {
				$c = function_exists( 'flxi_get_campaign' ) ? flxi_get_campaign( $cid ) : null;
				$row['state'][ $ch ] = $c ? array(
					'status' => (string) $c['status'],
					'total'  => (int) $c['total'],
					'sent'   => (int) $c['sent'],
					'failed' => (int) $c['failed'],
				) : null;
			}
			$out[] = $row;
		}
		return $out;
	}

	/** Planned and not started yet: can be cancelled. */
	public static function is_pending( array $row ) {
		foreach ( $row['state'] as $s ) {
			if ( $s && in_array( $s['status'], array( 'queued', 'sending', 'running' ), true ) ) {
				return true;
			}
		}
		return false;
	}

	/* ------------------------------------------------------------ */
	/* Admin posts                                                   */
	/* ------------------------------------------------------------ */

	public static function post_send() {
		check_admin_referer( 'flxcrm_event_msg' );
		if ( ! flxcrm_can( true ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-crm' ), 403 );
		}
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- sanitised in send()
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$r  = self::send(
			$id,
			array(
				'channels' => isset( $_POST['channels'] ) ? (array) wp_unslash( $_POST['channels'] ) : array(),
				'kind'     => isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : 'info',
				'text'     => isset( $_POST['text'] ) ? wp_unslash( $_POST['text'] ) : '',
				'subject'  => isset( $_POST['subject'] ) ? wp_unslash( $_POST['subject'] ) : '',
				'media_id' => isset( $_POST['media_id'] ) ? absint( $_POST['media_id'] ) : 0,
				'send_at'  => ( isset( $_POST['when'] ) && 'later' === $_POST['when'] && isset( $_POST['send_at'] ) ) ? sanitize_text_field( wp_unslash( $_POST['send_at'] ) ) : '',
			)
		);
		// phpcs:enable
		if ( is_wp_error( $r ) ) {
			wp_safe_redirect( flxcrm_events_url( array( 'id' => $id, 'flxcrm_err' => rawurlencode( $r->get_error_message() ) ) ) . '#flxcrm-evmsg' );
			exit;
		}
		$n    = array_sum( $r['counts'] );
		$args = array( 'id' => $id, 'flxcrm_msg' => 'evmsg' . $n );
		if ( $r['skipped'] ) { // some channels did not go: say why
			$args['flxcrm_err'] = rawurlencode( implode( ' ', array_map( function ( $ch, $why ) { return self::channels()[ $ch ] . ': ' . $why; }, array_keys( $r['skipped'] ), $r['skipped'] ) ) );
		}
		wp_safe_redirect( flxcrm_events_url( $args ) . '#flxcrm-evmsg-log' );
		exit;
	}

	public static function post_cancel() {
		check_admin_referer( 'flxcrm_event_msg_cancel' );
		if ( ! flxcrm_can( true ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-crm' ), 403 );
		}
		$id  = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$key = isset( $_POST['send'] ) ? sanitize_key( wp_unslash( $_POST['send'] ) ) : '';
		foreach ( (array) ( self::all()[ $id ] ?? array() ) as $row ) {
			if ( strtolower( (string) $row['id'] ) === $key && function_exists( 'flxi_cancel_campaign' ) ) {
				foreach ( (array) $row['campaigns'] as $cid ) {
					flxi_cancel_campaign( (int) $cid );
				}
			}
		}
		wp_safe_redirect( flxcrm_events_url( array( 'id' => $id, 'flxcrm_msg' => 'evmsgstop' ) ) . '#flxcrm-evmsg-log' );
		exit;
	}
}

FLXCRM_Event_Messages::init();
