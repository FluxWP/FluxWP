<?php
/**
 * Settings read and write. The settings form and flxMailSettingsUpdate both call save().
 * Secrets are write-only: empty keeps the stored value, "__clear__" removes it.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/**
 * Flux Mail settings.
 */
class FLXI_Settings_Service {

	public const CLEAR = '__clear__';

	/**
	 * GraphQL name => stored key and kind.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function map(): array {
		return array(
			'fromName'             => array( 'from_name', 'string' ),
			'fromEmail'            => array( 'from_email', 'string' ),
			'noreplyName'          => array( 'noreply_name', 'string' ),
			'noreplyEmail'         => array( 'noreply_email', 'string' ),
			'replyTo'              => array( 'reply_to', 'string' ),
			'forceFrom'            => array( 'force_from', 'bool' ),
			'setSender'            => array( 'set_sender', 'bool' ),
			'mailer'               => array( 'mailer', 'mailer' ),
			'smtpHost'             => array( 'smtp_host', 'string' ),
			'smtpPort'             => array( 'smtp_port', 'int' ),
			'smtpSecure'           => array( 'smtp_secure', 'secure' ),
			'smtpAuthentication'   => array( 'smtp_auth', 'bool' ),
			'smtpUser'             => array( 'smtp_user', 'string' ),
			'smtpNoreplyUser'      => array( 'smtp_nr_user', 'string' ),
			'mailboxEnabled'       => array( 'mailbox_enabled', 'bool' ),
			'imapHost'             => array( 'imap_host', 'string' ),
			'imapPort'             => array( 'imap_port', 'int' ),
			'imapSecure'           => array( 'imap_secure', 'secure' ),
			'imapUser'             => array( 'imap_user', 'string' ),
			'mailboxDays'          => array( 'mailbox_days', 'int' ),
			'mailboxKeepDays'      => array( 'mailbox_keep_days', 'int' ),
			'mailboxSignature'     => array( 'mailbox_signature', 'string' ),
			'wrapPlain'            => array( 'wrap_plain', 'bool' ),
			'wrapHtml'             => array( 'wrap_html', 'bool' ),
			'emailImagesFix'       => array( 'email_images_fix', 'bool' ),
			'embedImages'          => array( 'embed_images', 'bool' ),
			'footerArt'            => array( 'footer_art', 'string' ),
			'logo'                 => array( 'logo', 'string' ),
			'logoWhite'            => array( 'logo_white', 'string' ),
			'logoWidth'            => array( 'logo_width', 'int' ),
			'colorPrimary'         => array( 'color_primary', 'string' ),
			'colorDark'            => array( 'color_dark', 'string' ),
			'colorBg'              => array( 'color_bg', 'string' ),
			'colorText'            => array( 'color_text', 'string' ),
			'orgName'              => array( 'org_name', 'string' ),
			'tagline'              => array( 'tagline', 'string' ),
			'address'              => array( 'address', 'string' ),
			'phone'                => array( 'phone', 'string' ),
			'contactEmail'         => array( 'contact_email', 'string' ),
			'website'              => array( 'website', 'string' ),
			'social'               => array( 'social', 'social' ),
			'footerNote'           => array( 'footer_note', 'string' ),
			'socialBlockCore'      => array( 'social_block_core', 'bool' ),
			'socialBlockWc'        => array( 'social_block_wc', 'bool' ),
			'socialVerified'       => array( 'social_verified', 'bool' ),
			'logEnabled'           => array( 'log_enabled', 'bool' ),
			'logBody'              => array( 'log_body', 'bool' ),
			'logDays'              => array( 'log_days', 'int' ),
			'bulkPerRun'           => array( 'bulk_per_run', 'int' ),
			'bulkUnsubscribe'      => array( 'bulk_unsubscribe', 'bool' ),
			'optoutEnabled'        => array( 'optout_enabled', 'bool' ),
			'optoutFooterWa'       => array( 'optout_footer_wa', 'bool' ),
			'optoutFooterSms'      => array( 'optout_footer_sms', 'bool' ),
			'optoutText'           => array( 'optout_text', 'string' ),
			'optoutKeywords'       => array( 'optout_keywords', 'string' ),
			'optinKeywords'        => array( 'optin_keywords', 'string' ),
			'optoutReply'          => array( 'optout_reply', 'string' ),
			'optinReply'           => array( 'optin_reply', 'string' ),
			'limitDaily'           => array( 'limit_daily', 'int' ),
			'limitRecipients'      => array( 'limit_recipients', 'int' ),
			'limitReserve'         => array( 'limit_reserve', 'int' ),
			'limitSizeMb'          => array( 'limit_size_mb', 'int' ),
			'limitAttachMb'        => array( 'limit_attach_mb', 'int' ),
			'limitStorageGb'       => array( 'limit_storage_gb', 'int' ),
			'limitMailboxEmails'   => array( 'limit_mailbox_emails', 'int' ),
			'limitAliases'         => array( 'limit_aliases', 'int' ),
			'limitForwarders'      => array( 'limit_forwarders', 'int' ),
		);
	}

	/**
	 * Secret GraphQL name => stored key and wp-config constant.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function secrets(): array {
		return array(
			'smtpPassword'        => array( 'smtp_pass', 'FLXI_SMTP_PASS' ),
			'smtpNoreplyPassword' => array( 'smtp_nr_pass', 'FLXI_SMTP_NOREPLY_PASS' ),
			'imapPassword'        => array( 'imap_pass', 'FLXI_IMAP_PASS' ),
		);
	}

	/**
	 * @return list<string>
	 */
	public static function bools(): array {
		$out = array();
		foreach ( self::map() as $row ) {
			if ( 'bool' === $row[1] ) {
				$out[] = $row[0];
			}
		}
		return $out;
	}

	/**
	 * Settings for GraphQL. Secret values are never included.
	 *
	 * @return array<string,mixed>
	 */
	public static function read(): array {
		$s   = flxi_arr( flxi_settings() );
		$out = array();
		foreach ( self::map() as $gql => $row ) {
			$key = $row[0];
			$val = $s[ $key ] ?? null;
			if ( 'bool' === $row[1] ) {
				$out[ $gql ] = (bool) $val;
			} elseif ( 'int' === $row[1] ) {
				$out[ $gql ] = flxi_int( $val );
			} elseif ( 'secure' === $row[1] ) {
				$out[ $gql ] = in_array( $val, array( 'ssl', 'tls' ), true ) ? $val : 'none';
			} elseif ( 'mailer' === $row[1] ) {
				$out[ $gql ] = 'smtp' === $val ? 'smtp' : 'php';
			} elseif ( 'social' === $row[1] ) {
				$list = array();
				foreach ( flxi_arr( $val ) as $net => $url ) {
					$url = flxi_str( $url );
					if ( '' !== $url ) {
						$list[] = array( 'network' => $net, 'url' => $url );
					}
				}
				$out[ $gql ] = $list;
			} else {
				$out[ $gql ] = flxi_str( $val );
			}
		}
		foreach ( self::secrets() as $gql => $row ) {
			$out[ $gql ] = self::secret_view( $row[0], $row[1], 'imap_pass' === $row[0] );
		}
		$unsub = flxi_unsubscribed();
		$out['unsubscribedCount']   = is_array( $unsub ) ? count( $unsub ) : 0;
		$out['optoutWhatsappCount'] = function_exists( 'flxi_optout_count' ) ? flxi_int( flxi_optout_count( 'whatsapp' ) ) : 0;
		$out['optoutSmsCount']      = function_exists( 'flxi_optout_count' ) ? flxi_int( flxi_optout_count( 'sms' ) ) : 0;
		$out['wooActive']           = class_exists( 'WooCommerce' );
		$out['lastTest']            = self::last_test();
		$out['wcBranded']           = self::wc_branded();
		$out['otherSmtpPlugin']     = flxi_other_smtp_plugin() ? flxi_str( flxi_other_smtp_plugin() ) : null;
		$out['smtpAccountNotes']    = self::notes();
		$social                     = flxi_active_social_plugins();
		$out['activeSocialPlugins'] = is_array( $social ) ? array_map( 'flxi_str', array_values( $social ) ) : array();
		$out['quota']               = self::quota();
		$out['quotaFullDays']       = self::full_days();
		$out['dns']                 = FLXI_System_Service::stored_dns();
		$out['footerArts']          = self::choices( flxi_footer_arts() );
		$out['socialNetworks']      = self::choices( flxi_social_services() );
		if ( function_exists( 'flx_gql_cache_tag' ) ) {
			flx_gql_cache_tag( 'flx-mail:settings' );
		}
		return $out;
	}

	/**
	 * @param mixed $pairs Key => label.
	 * @return list<array{key:string,label:string}>
	 */
	private static function choices( $pairs ): array {
		$out = array();
		foreach ( flxi_arr( $pairs ) as $k => $label ) {
			$out[] = array( 'key' => flxi_str( $k ), 'label' => flxi_str( $label ) );
		}
		return $out;
	}

	/**
	 * @return array{set:bool,mask:string,fromConfig:bool}
	 */
	public static function secret_view( string $key, string $constant, bool $imap ): array {
		$from = defined( $constant );
		if ( $from ) {
			$plain = flxi_str( constant( $constant ) );
		} elseif ( $imap ) {
			$plain = defined( 'FLXI_IMAP_PASS' ) ? flxi_str( FLXI_IMAP_PASS ) : flxi_str( flxi_decrypt( flxi_str( flxi_setting( 'imap_pass' ) ) ) );
		} else {
			$plain = flxi_str( flxi_smtp_password( 'smtp_nr_pass' === $key ) );
		}
		return array(
			'set'        => '' !== $plain,
			'mask'       => flxi_str( flxi_mask( $plain ) ),
			'fromConfig' => $from,
		);
	}

	/**
	 * @return array{ok:bool,to:string,error:string,at:int}|null
	 */
	public static function last_test(): ?array {
		$last = get_option( 'flxi_last_test' );
		if ( ! is_array( $last ) || ! isset( $last['at'] ) ) {
			return null;
		}
		return array(
			'ok'    => ! empty( $last['ok'] ),
			'to'    => flxi_str( $last['to'] ?? '' ),
			'error' => flxi_str( $last['error'] ?? '' ),
			'at'    => flxi_int( $last['at'] ),
		);
	}

	public static function wc_branded(): ?int {
		$t = flxi_int( get_option( 'flxi_wc_branded' ) );
		return $t > 0 ? $t : null;
	}

	/**
	 * @return list<array{mailbox:string,level:string,text:string}>
	 */
	public static function notes(): array {
		$out = array();
		foreach ( flxi_arr( flxi_smtp_account_notes() ) as $box => $note ) {
			$note  = flxi_arr( $note );
			$out[] = array(
				'mailbox' => flxi_str( $box ),
				'level'   => flxi_str( $note['level'] ?? '' ),
				'text'    => flxi_str( $note['text'] ?? '' ),
			);
		}
		return $out;
	}

	/**
	 * @return list<array{mailbox:string,type:string,used:int,limit:int,left:?int,percent:int,next:int}>
	 */
	public static function quota(): array {
		$out = array();
		foreach ( flxi_rows( flxi_quota_summary() ) as $q ) {
			$left = $q['left'] ?? null;
			$out[] = array(
				'mailbox' => flxi_str( $q['mailbox'] ?? '' ),
				'type'    => flxi_str( $q['type'] ?? '' ),
				'used'    => flxi_int( $q['used'] ?? 0 ),
				'limit'   => flxi_int( $q['limit'] ?? 0 ),
				'left'    => null === $left ? null : flxi_int( $left ),
				'percent' => flxi_int( $q['percent'] ?? 0 ),
				'next'    => flxi_int( $q['next'] ?? 0 ),
			);
		}
		return $out;
	}

	public static function full_days(): int {
		$full = get_option( 'flxi_quota_full_days', array() );
		$full = is_array( $full ) ? array_filter(
			$full,
			static function ( $x ) {
				return strtotime( flxi_str( $x ) ) > time() - 30 * DAY_IN_SECONDS;
			}
		) : array();
		return count( $full );
	}

	/**
	 * Classic form save. $mode form keeps the admin-post rules; graphql writes only the given keys.
	 *
	 * @param array<string,mixed> $in Snake_case values as the form posts them.
	 * @return true|WP_Error
	 */
	public static function save( array $in, string $mode, bool $partial ) {
		$d     = flxi_arr( flxi_defaults() );
		$clean = array();
		$bools = self::bools();
		$keep  = array( 'log_enabled', 'log_body', 'bulk_unsubscribe', 'optout_enabled', 'optout_footer_wa', 'optout_footer_sms' );
		foreach ( $d as $k => $def ) {
			unset( $def );
			if ( in_array( $k, array( 'smtp_pass', 'smtp_nr_pass', 'imap_pass' ), true ) ) {
				continue;
			}
			if ( in_array( $k, $bools, true ) ) {
				if ( 'graphql' === $mode && ! array_key_exists( $k, $in ) ) {
					continue;
				}
				if ( 'form' === $mode && $partial && ! array_key_exists( $k, $in ) && ! in_array( $k, $keep, true ) ) {
					continue;
				}
				$clean[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
				continue;
			}
			if ( ! array_key_exists( $k, $in ) ) {
				continue;
			}
			if ( 'graphql' === $mode && in_array( $k, array( 'from_email', 'noreply_email', 'reply_to', 'contact_email' ), true ) ) {
				$raw = $in[ $k ];
				if ( ! is_string( $raw ) || ( '' !== trim( $raw ) && ! is_email( trim( $raw ) ) ) ) {
					return new WP_Error( 'flxi_invalid', __( 'Enter a valid email address.', 'flx-mail' ), array( 'status' => 400, 'field' => self::field_name( $k ) ) );
				}
			}
			try {
				$clean[ $k ] = flxi_sanitize_setting( $k, $in[ $k ] );
			} catch ( Exception $e ) {
				return new WP_Error( 'flxi_invalid', $e->getMessage(), array( 'status' => 400, 'field' => self::field_name( $k ) ) );
			}
		}
		foreach ( self::secrets() as $row ) {
			$key = $row[0];
			if ( ! array_key_exists( $key, $in ) ) {
				continue;
			}
			if ( defined( $row[1] ) ) {
				continue;
			}
			$v = trim( flxi_str( $in[ $key ] ) );
			if ( '' === $v ) {
				continue;
			}
			$clean[ $key ] = self::CLEAR === $v ? '' : flxi_encrypt( $v );
		}
		foreach ( array( 'from_name', 'noreply_name', 'from_email', 'noreply_email' ) as $k ) {
			if ( isset( $clean[ $k ] ) && '' === $clean[ $k ] ) {
				$clean[ $k ] = $d[ $k ];
			}
		}
		if ( isset( $clean['log_days'] ) ) {
			$clean['log_days'] = max( 1, min( 3650, flxi_int( $clean['log_days'] ) ) );
		}
		foreach ( array( 'limit_daily', 'limit_recipients', 'limit_size_mb', 'limit_attach_mb', 'limit_storage_gb', 'limit_mailbox_emails', 'limit_aliases', 'limit_forwarders', 'limit_reserve' ) as $k ) {
			if ( isset( $clean[ $k ] ) ) {
				$clean[ $k ] = max( 0, min( 1000000, flxi_int( $clean[ $k ] ) ) );
			}
		}
		if ( isset( $clean['limit_reserve'], $clean['limit_daily'] ) && $clean['limit_daily'] ) {
			$clean['limit_reserve'] = min( flxi_int( $clean['limit_reserve'] ), max( 0, flxi_int( $clean['limit_daily'] ) - 1 ) );
		} elseif ( isset( $clean['limit_reserve'] ) && 'graphql' === $mode ) {
			$daily = isset( $clean['limit_daily'] ) ? flxi_int( $clean['limit_daily'] ) : flxi_int( flxi_setting( 'limit_daily' ) );
			if ( $daily ) {
				$clean['limit_reserve'] = min( flxi_int( $clean['limit_reserve'] ), max( 0, $daily - 1 ) );
			}
		}
		if ( isset( $clean['bulk_per_run'] ) ) {
			$clean['bulk_per_run'] = max( 1, min( 500, flxi_int( $clean['bulk_per_run'] ) ) );
		}
		flxi_update_settings( $clean );
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( 'flx-mail:settings' );
		}
		return true;
	}

	/**
	 * GraphQL input (camelCase) → the snake_case array save() expects.
	 *
	 * @param array<string,mixed> $input Mutation input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function from_input( array $input ) {
		$in = array();
		foreach ( self::map() as $gql => $row ) {
			if ( ! array_key_exists( $gql, $input ) || null === $input[ $gql ] ) {
				continue;
			}
			$val = $input[ $gql ];
			if ( 'secure' === $row[1] ) {
				$val = strtolower( flxi_str( $val ) );
				$val = in_array( $val, array( 'ssl', 'tls' ), true ) ? $val : '';
			} elseif ( 'mailer' === $row[1] ) {
				$val = 'smtp' === strtolower( flxi_str( $val ) ) ? 'smtp' : 'php';
			} elseif ( 'social' === $row[1] ) {
				$assoc = array();
				foreach ( flxi_rows( $val ) as $item ) {
					$net = sanitize_key( flxi_str( $item['network'] ?? '' ) );
					if ( '' !== $net ) {
						$assoc[ $net ] = flxi_str( $item['url'] ?? '' );
					}
				}
				$val = $assoc;
			} elseif ( 'bool' === $row[1] ) {
				$val = ! empty( $val ) ? 1 : 0;
			}
			$in[ $row[0] ] = $val;
		}
		foreach ( self::secrets() as $gql => $row ) {
			if ( array_key_exists( $gql, $input ) && null !== $input[ $gql ] ) {
				$in[ $row[0] ] = flxi_str( $input[ $gql ] );
			}
		}
		foreach ( array( 'logoUploadId' => 'logo', 'logoWhiteUploadId' => 'logo_white' ) as $gql => $key ) {
			if ( empty( $input[ $gql ] ) || ! is_string( $input[ $gql ] ) ) {
				continue;
			}
			$url = self::logo_from_upload( $input[ $gql ] );
			if ( is_wp_error( $url ) ) {
				return $url;
			}
			$in[ $key ] = $url;
		}
		return $in;
	}

	/**
	 * @return string|WP_Error
	 */
	private static function logo_from_upload( string $upload_id ) {
		if ( ! function_exists( 'flx_gql_upload_take' ) ) {
			return new WP_Error( 'flxi_invalid', __( 'File upload is not available.', 'flx-mail' ), array( 'status' => 400, 'field' => 'logoUploadId' ) );
		}
		$file = flx_gql_upload_take( $upload_id, 'flx-mail/logo' );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$name     = is_string( $file['name'] ?? null ) ? $file['name'] : 'logo';
		$sideload = array(
			'name'     => '' !== $name ? $name : 'logo',
			'tmp_name' => is_string( $file['path'] ?? null ) ? $file['path'] : '',
		);
		$id = media_handle_sideload( $sideload, 0 );
		if ( is_wp_error( $id ) ) {
			return new WP_Error( 'flxi_invalid', $id->get_error_message(), array( 'status' => 400, 'field' => 'logoUploadId' ) );
		}
		$url = wp_get_attachment_url( (int) $id );
		return is_string( $url ) ? $url : '';
	}

	private static function field_name( string $key ): string {
		foreach ( self::map() as $gql => $row ) {
			if ( $row[0] === $key ) {
				return $gql;
			}
		}
		return $key;
	}
}
