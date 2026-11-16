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
		$s   = flxi_settings();
		$out = array();
		foreach ( self::map() as $gql => $row ) {
			$key = $row[0];
			$val = $s[ $key ] ?? null;
			if ( 'bool' === $row[1] ) {
				$out[ $gql ] = (bool) $val;
			} elseif ( 'int' === $row[1] ) {
				$out[ $gql ] = (int) $val;
			} elseif ( 'secure' === $row[1] ) {
				$out[ $gql ] = in_array( $val, array( 'ssl', 'tls' ), true ) ? $val : 'none';
			} elseif ( 'mailer' === $row[1] ) {
				$out[ $gql ] = 'smtp' === $val ? 'smtp' : 'php';
			} elseif ( 'social' === $row[1] ) {
				$list = array();
				foreach ( (array) $val as $net => $url ) {
					if ( is_string( $net ) && is_string( $url ) && '' !== $url ) {
						$list[] = array( 'network' => $net, 'url' => $url );
					}
				}
				$out[ $gql ] = $list;
			} else {
				$out[ $gql ] = is_string( $val ) ? $val : ( is_scalar( $val ) ? (string) $val : '' );
			}
		}
		foreach ( self::secrets() as $gql => $row ) {
			$out[ $gql ] = self::secret_view( $row[0], $row[1], 'imap_pass' === $row[0] );
		}
		$out['unsubscribedCount']   = count( flxi_unsubscribed() );
		$out['optoutWhatsappCount'] = function_exists( 'flxi_optout_count' ) ? flxi_optout_count( 'whatsapp' ) : 0;
		$out['optoutSmsCount']      = function_exists( 'flxi_optout_count' ) ? flxi_optout_count( 'sms' ) : 0;
		$out['wooActive']           = class_exists( 'WooCommerce' );
		$out['lastTest']            = self::last_test();
		$out['wcBranded']           = self::wc_branded();
		$out['otherSmtpPlugin']    = flxi_other_smtp_plugin() ? flxi_other_smtp_plugin() : null;
		$out['smtpAccountNotes']   = self::notes();
		$out['activeSocialPlugins'] = array_values( flxi_active_social_plugins() );
		$out['quota']              = self::quota();
		$out['quotaFullDays']      = self::full_days();
		$out['dns']                = FLXI_System_Service::stored_dns();
		$out['footerArts']         = self::choices( flxi_footer_arts() );
		$out['socialNetworks']     = self::choices( flxi_social_services() );
		if ( function_exists( 'flx_gql_cache_tag' ) ) {
			flx_gql_cache_tag( 'flx-mail:settings' );
		}
		return $out;
	}

	/**
	 * @param array<string,string> $pairs Key => label.
	 * @return list<array{key:string,label:string}>
	 */
	private static function choices( array $pairs ): array {
		$out = array();
		foreach ( $pairs as $k => $label ) {
			$out[] = array( 'key' => (string) $k, 'label' => (string) $label );
		}
		return $out;
	}

	/**
	 * @return array{set:bool,mask:string,fromConfig:bool}
	 */
	public static function secret_view( string $key, string $constant, bool $imap ): array {
		$from = defined( $constant );
		if ( $from ) {
			$plain = (string) constant( $constant );
		} elseif ( $imap ) {
			$plain = defined( 'FLXI_IMAP_PASS' ) ? (string) FLXI_IMAP_PASS : flxi_decrypt( (string) flxi_setting( 'imap_pass' ) );
		} else {
			$plain = flxi_smtp_password( 'smtp_nr_pass' === $key );
		}
		return array(
			'set'        => '' !== $plain,
			'mask'       => flxi_mask( $plain ),
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
			'to'    => (string) ( $last['to'] ?? '' ),
			'error' => (string) ( $last['error'] ?? '' ),
			'at'    => (int) $last['at'],
		);
	}

	public static function wc_branded(): ?int {
		$t = (int) get_option( 'flxi_wc_branded' );
		return $t > 0 ? $t : null;
	}

	/**
	 * @return list<array{mailbox:string,level:string,text:string}>
	 */
	public static function notes(): array {
		$out = array();
		foreach ( flxi_smtp_account_notes() as $box => $note ) {
			$out[] = array(
				'mailbox' => (string) $box,
				'level'   => (string) ( $note['level'] ?? '' ),
				'text'    => (string) ( $note['text'] ?? '' ),
			);
		}
		return $out;
	}

	/**
	 * @return list<array{mailbox:string,type:string,used:int,limit:int,left:?int,percent:int,next:int}>
	 */
	public static function quota(): array {
		$out = array();
		foreach ( flxi_quota_summary() as $q ) {
			$out[] = array(
				'mailbox' => (string) $q['mailbox'],
				'type'    => (string) $q['type'],
				'used'    => (int) $q['used'],
				'limit'   => (int) $q['limit'],
				'left'    => null === $q['left'] ? null : (int) $q['left'],
				'percent' => (int) $q['percent'],
				'next'    => (int) $q['next'],
			);
		}
		return $out;
	}

	public static function full_days(): int {
		$full = get_option( 'flxi_quota_full_days', array() );
		$full = is_array( $full ) ? array_filter(
			$full,
			static function ( $x ) {
				return strtotime( (string) $x ) > time() - 30 * DAY_IN_SECONDS;
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
		$d     = flxi_defaults();
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
			$v = trim( (string) $in[ $key ] );
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
			$clean['log_days'] = max( 1, min( 3650, (int) $clean['log_days'] ) );
		}
		foreach ( array( 'limit_daily', 'limit_recipients', 'limit_size_mb', 'limit_attach_mb', 'limit_storage_gb', 'limit_mailbox_emails', 'limit_aliases', 'limit_forwarders', 'limit_reserve' ) as $k ) {
			if ( isset( $clean[ $k ] ) ) {
				$clean[ $k ] = max( 0, min( 1000000, (int) $clean[ $k ] ) );
			}
		}
		if ( isset( $clean['limit_reserve'], $clean['limit_daily'] ) && $clean['limit_daily'] ) {
			$clean['limit_reserve'] = min( $clean['limit_reserve'], max( 0, $clean['limit_daily'] - 1 ) );
		} elseif ( isset( $clean['limit_reserve'] ) && 'graphql' === $mode ) {
			$daily = isset( $clean['limit_daily'] ) ? (int) $clean['limit_daily'] : (int) flxi_setting( 'limit_daily' );
			if ( $daily ) {
				$clean['limit_reserve'] = min( (int) $clean['limit_reserve'], max( 0, $daily - 1 ) );
			}
		}
		if ( isset( $clean['bulk_per_run'] ) ) {
			$clean['bulk_per_run'] = max( 1, min( 500, (int) $clean['bulk_per_run'] ) );
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
				$val = strtolower( (string) $val );
				$val = in_array( $val, array( 'ssl', 'tls' ), true ) ? $val : '';
			} elseif ( 'mailer' === $row[1] ) {
				$val = 'smtp' === strtolower( (string) $val ) ? 'smtp' : 'php';
			} elseif ( 'social' === $row[1] ) {
				$assoc = array();
				foreach ( (array) $val as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					$net = sanitize_key( (string) ( $item['network'] ?? '' ) );
					if ( '' !== $net ) {
						$assoc[ $net ] = (string) ( $item['url'] ?? '' );
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
				$in[ $row[0] ] = (string) $input[ $gql ];
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
		$sideload = array(
			'name'     => (string) ( $file['name'] ?? 'logo' ),
			'tmp_name' => (string) ( $file['path'] ?? '' ),
			'error'    => 0,
			'size'     => (int) ( $file['size'] ?? 0 ),
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
