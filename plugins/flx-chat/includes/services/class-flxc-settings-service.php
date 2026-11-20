<?php
/**
 * Settings, connections, AI model/test helpers and system checks shared between
 * classic wp-admin / REST and GraphQL.
 *
 * @package FLX_Chat
 */

defined( 'ABSPATH' ) || exit;

final class FLXC_Settings_Service {

	public const CACHE = 'flx-chat:settings';

	/**
	 * A legacy list of rows as a typed list.
	 *
	 * @param mixed $v Value.
	 * @return list<array<string,mixed>>
	 */
	private static function rows( $v ): array {
		$out = array();
		foreach ( FLXC_Val::arr( $v ) as $row ) {
			if ( is_array( $row ) ) {
				/** @var array<string,mixed> $row */
				$out[] = $row;
			}
		}
		return $out;
	}

	/**
	 * @param mixed $v Value.
	 * @return list<int>
	 */
	private static function ints( $v ): array {
		return array_values( array_map( static fn( $x ): int => FLXC_Val::int( $x ), FLXC_Val::arr( $v ) ) );
	}

	/**
	 * Purges settings and inbox caches after any settings/connection mutation.
	 */
	public static function changed(): void {
		FLXC_Chat_Service::changed();
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( array( self::CACHE ) );
		}
	}

	/**
	 * URL to Flux Mail connections page.
	 */
	public static function flxi_connections_url(): string {
		return function_exists( 'flxi_admin_url' ) ? FLXC_Val::str( flxi_admin_url( 'flxi-baglantilar' ) ) : admin_url( 'admin.php?page=flxi-baglantilar' );
	}

	/**
	 * Current source selector value for a connection row (`flxi:<id>`, `flxs:<id>`, or `own`).
	 *
	 * @param array<string,mixed> $c Connection row.
	 */
	public static function connection_source_value( array $c ): string {
		$source = FLXC_Val::str( $c['source'] ?? 'own' );
		$flxs   = FLXC_Val::int( $c['flxs_id'] ?? 0 );
		if ( 'flxi' === $source && ! flxc_flxi_on() && $flxs > 0 ) {
			return 'flxs:' . $flxs;
		}
		if ( in_array( $source, array( 'flxi', 'flxs' ), true ) ) {
			return $source . ':' . FLXC_Val::int( $c['provider_id'] ?? 0 );
		}
		return 'own';
	}

	/**
	 * Source dropdown choices for a connection row (or a new row when `$c` is null).
	 *
	 * @param array<string,mixed>|null        $c    Connection row.
	 * @param list<array<string,mixed>>|null  $flxs Cached Flux Certificates Evolution providers.
	 * @return list<array{value: string, label: string}>
	 */
	public static function connection_source_choices( ?array $c = null, ?array $flxs = null ): array {
		$flxs   = null !== $flxs ? $flxs : self::rows( flxc_flxs_evolution_providers() );
		$conn   = null !== $c ? $c : array( 'source' => '', 'provider_id' => 0 );
		$source = FLXC_Val::str( $conn['source'] ?? '' );
		$pid    = FLXC_Val::int( $conn['provider_id'] ?? 0 );

		$states = array(
			'open'       => __( 'connected', 'flx-chat' ),
			'connecting' => __( 'connecting', 'flx-chat' ),
			'close'      => __( 'not connected', 'flx-chat' ),
			'missing'    => __( 'not on server', 'flx-chat' ),
			'error'      => __( 'unreachable', 'flx-chat' ),
		);
		$map    = array();
		foreach ( self::rows( flxc_flxi_whatsapp_choices() ) as $x ) {
			$type  = FLXC_Val::str( $x['type'] ?? '' );
			$tname = 'evolution' === $type ? 'Evolution API' : ( in_array( $type, array( 'flxwpapi', 'flx-wpapi' ), true ) ? 'Flux WPAPI' : ( function_exists( 'flxc_wacloud_type' ) && flxc_wacloud_type( $type ) ? 'Cloud API' : '' ) );
			$st    = FLXC_Val::str( $x['state'] ?? '' );
			$extra = array_filter(
				array(
					$tname,
					FLXC_Val::str( $x['me'] ?? '' ),
					$states[ $st ] ?? '',
				)
			);
			$xid   = FLXC_Val::int( $x['id'] ?? 0 );
			/* translators: %s: connection name */
			$map[ 'flxi:' . $xid ] = sprintf( __( 'Flux Mail connection: %s', 'flx-chat' ), FLXC_Val::str( $x['name'] ?? '' ) ) . ( $extra ? ' (' . implode( ' · ', $extra ) . ')' : '' );
		}
		if ( 'flxi' === $source && ! isset( $map[ 'flxi:' . $pid ] ) && flxc_flxi_on() ) {
			/* translators: %d: connection id */
			$map[ 'flxi:' . $pid ] = sprintf( __( 'Flux Mail connection #%d (not found or disabled)', 'flx-chat' ), $pid );
		}
		foreach ( $flxs as $p ) {
			$fpid = FLXC_Val::int( $p['id'] ?? 0 );
			if ( ! flxc_flxi_on() || ( 'flxs' === $source && $pid === $fpid ) ) {
				/* translators: %s: provider name */
				$map[ 'flxs:' . $fpid ] = sprintf( __( 'Use Flux Certificates provider: %s', 'flx-chat' ), FLXC_Val::str( $p['name'] ?? '' ) );
			}
		}
		if ( ! flxc_flxi_on() || 'own' === $source || '' === $source ) {
			$map['own'] = __( 'Enter separate Evolution API details', 'flx-chat' );
		}

		$out = array();
		foreach ( $map as $val => $label ) {
			$out[] = array(
				'value' => FLXC_Val::str( $val ),
				'label' => $label,
			);
		}
		return $out;
	}

	/**
	 * Default source descriptor for newly added lines.
	 *
	 * @return array{value: string, label: string}
	 */
	public static function default_source_choice(): array {
		$flxi = self::rows( flxc_flxi_whatsapp_choices() );
		if ( $flxi ) {
			$val = 'flxi:' . FLXC_Val::int( $flxi[0]['id'] ?? 0 );
		} else {
			$flxs = self::rows( flxc_flxs_evolution_providers() );
			if ( $flxs && ! flxc_flxi_on() ) {
				$val = 'flxs:' . FLXC_Val::int( $flxs[0]['id'] ?? 0 );
			} else {
				$val = 'own';
			}
		}
		$choices = self::connection_source_choices();
		foreach ( $choices as $ch ) {
			if ( $ch['value'] === $val ) {
				return $ch;
			}
		}
		return $choices[0] ?? array( 'value' => 'own', 'label' => __( 'Enter separate Evolution API details', 'flx-chat' ) );
	}

	/**
	 * Public settings view + UI context (never exposes secrets or webhook tokens).
	 *
	 * @return array<string,mixed>
	 */
	public static function get(): array {
		$s     = FLXC_Val::arr( flxc_settings() );
		$flxs  = self::rows( flxc_flxs_evolution_providers() );
		$conns = array();
		foreach ( self::rows( flxc_connections() ) as $c ) {
			$cid  = FLXC_Val::str( $c['id'] ?? '' );
			$st   = '' !== $cid ? FLXC_Val::arr( flxc_connection_state( $cid ) ) : array( 'state' => '', 'at' => 0 );
			$last = '' !== $cid ? FLXC_Val::int( get_option( 'flxc_last_webhook_' . $cid, 0 ) ) : 0;
			$conns[] = array(
				'id'            => $cid,
				'label'         => FLXC_Val::str( $c['label'] ?? '' ),
				'source'        => FLXC_Val::str( $c['source'] ?? 'own' ),
				'sourceValue'   => self::connection_source_value( $c ),
				'providerId'    => FLXC_Val::int( $c['provider_id'] ?? 0 ),
				'flxsId'        => FLXC_Val::int( $c['flxs_id'] ?? 0 ),
				'origin'        => flxc_connection_origin( $c ),
				'baseUrl'       => FLXC_Val::str( $c['base_url'] ?? '' ),
				'instance'      => FLXC_Val::str( $c['instance'] ?? '' ),
				'hasApiKey'     => '' !== FLXC_Val::str( $c['api_key'] ?? '' ),
				'state'         => FLXC_Val::str( $st['state'] ?? '' ),
				'stateAt'       => FLXC_Val::int( $st['at'] ?? 0 ),
				'lastWebhookAt' => $last,
				'sources'       => self::connection_source_choices( $c, $flxs ),
			);
		}

		$providers = array();
		foreach ( FLXC_Val::arr( flxc_ai_provider_list() ) as $id => $label ) {
			$providers[] = array(
				'id'    => FLXC_Val::str( $id ),
				'label' => FLXC_Val::str( $label ),
			);
		}

		$usage_opt = get_option( 'flxc_ai_usage', array() );
		$usage     = null;
		if ( is_array( $usage_opt ) && ! empty( $usage_opt['month'] ) ) {
			$usage = array(
				'month'  => FLXC_Val::str( $usage_opt['month'] ),
				'calls'  => FLXC_Val::int( $usage_opt['calls'] ?? 0 ),
				'tokens' => FLXC_Val::int( $usage_opt['tokens'] ?? 0 ),
				'cost'   => FLXC_Val::float( $usage_opt['cost'] ?? 0 ),
			);
		}

		$merr_opt = get_option( 'flxc_mirror_error' );
		$merr     = null;
		if ( is_array( $merr_opt ) && '' !== FLXC_Val::str( $merr_opt['error'] ?? '' ) ) {
			$merr = array(
				'at'    => FLXC_Val::int( $merr_opt['at'] ?? 0 ),
				'error' => FLXC_Val::str( $merr_opt['error'] ),
			);
		}

		$dtypes = array();
		if ( function_exists( 'flxb_get_types' ) ) {
			foreach ( self::rows( flxb_get_types( array( 'status' => 'open', 'progress' => false ) ) ) as $t ) {
				$dtypes[] = array(
					'id'    => FLXC_Val::int( $t['id'] ?? 0 ),
					'title' => FLXC_Val::str( $t['title'] ?? '' ),
				);
			}
		}

		$auto_types = FLXC_Val::arr( flxc_site_types() );
		$ptypes     = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
			if ( 'attachment' === $pt->name ) {
				continue;
			}
			$lbl = $pt->labels->name ?? $pt->label;
			$ptypes[] = array(
				'name'         => $pt->name,
				'label'        => FLXC_Val::str( $lbl ),
				'autoSelected' => isset( $auto_types[ $pt->name ] ),
			);
		}

		$bank_acc = FLXC_Val::arr( flxc_bot_bank_account() );
		$donate   = flxc_donate_url();

		$labels = array();
		foreach ( self::rows( $s['labels'] ?? array() ) as $l ) {
			if ( '' !== FLXC_Val::str( $l['id'] ?? '' ) ) {
				$labels[] = array(
					'id'    => FLXC_Val::str( $l['id'] ),
					'name'  => FLXC_Val::str( $l['name'] ?? '' ),
					'color' => FLXC_Val::str( $l['color'] ?? '#25d366' ),
				);
			}
		}

		$agents = array();
		foreach ( self::rows( flxc_agents() ) as $a ) {
			$agents[] = array(
				'id'   => FLXC_Val::int( $a['id'] ?? 0 ),
				'name' => FLXC_Val::str( $a['name'] ?? '' ),
			);
		}

		return array(
			'connections'         => $conns,
			'brandName'           => FLXC_Val::str( $s['brand_name'] ?? '' ),
			'accent'              => FLXC_Val::str( $s['accent'] ?? '#00a884' ),
			'sendRead'            => FLXC_Val::bool( $s['send_read'] ?? 1 ),
			'agentSignature'      => FLXC_Val::bool( $s['agent_signature'] ?? 0 ),
			'restrictAgents'      => FLXC_Val::bool( $s['restrict_agents'] ?? 0 ),
			'showGroups'          => FLXC_Val::bool( $s['show_groups'] ?? 0 ),
			'uiFlux'              => FLXC_Val::bool( $s['ui_flux'] ?? 0 ),
			'deleteEveryoneHours' => FLXC_Val::int( $s['delete_everyone_hours'] ?? 60 ),
			'pollInterval'        => FLXC_Val::int( $s['poll_interval'] ?? 10 ),
			'deleteData'          => FLXC_Val::bool( $s['delete_data'] ?? 0 ),
			'welcomeEnabled'      => FLXC_Val::bool( $s['welcome_enabled'] ?? 0 ),
			'welcomeText'         => FLXC_Val::str( $s['welcome_text'] ?? '' ),
			'awayEnabled'         => FLXC_Val::bool( $s['away_enabled'] ?? 0 ),
			'awayText'            => FLXC_Val::str( $s['away_text'] ?? '' ),
			'awayDays'            => self::ints( $s['away_days'] ?? array() ),
			'awayStart'           => FLXC_Val::str( $s['away_start'] ?? '09:00' ),
			'awayEnd'             => FLXC_Val::str( $s['away_end'] ?? '18:00' ),
			'awayThrottle'        => FLXC_Val::int( $s['away_throttle'] ?? 6 ),
			'labels'              => $labels,
			'agentsDonations'     => FLXC_Val::bool( $s['agents_donations'] ?? 1 ),
			'agentsCertificates'  => FLXC_Val::bool( $s['agents_certificates'] ?? 1 ),
			'chipsBar'            => FLXC_Val::bool( $s['chips_bar'] ?? 1 ),
			'msgDonationPending'  => FLXC_Val::str( $s['msg_donation_pending'] ?? '' ),
			'msgDonationDone'     => FLXC_Val::str( $s['msg_donation_done'] ?? '' ),
			'msgReceipt'          => FLXC_Val::str( $s['msg_receipt'] ?? '' ),
			'bankText'            => FLXC_Val::str( $s['bank_text'] ?? '' ),
			'aiProvider'          => FLXC_Val::str( $s['ai_provider'] ?? '' ),
			'aiModel'             => FLXC_Val::str( $s['ai_model'] ?? '' ),
			'aiModelBot'          => FLXC_Val::str( $s['ai_model_bot'] ?? '' ),
			'aiModelVision'       => FLXC_Val::str( $s['ai_model_vision'] ?? '' ),
			'aiModelAudio'        => FLXC_Val::str( $s['ai_model_audio'] ?? '' ),
			'aiTemperature'       => FLXC_Val::float( $s['ai_temperature'] ?? 0.4 ),
			'aiAutoSuggest'       => FLXC_Val::bool( $s['ai_auto_suggest'] ?? 1 ),
			'aiInstructions'      => FLXC_Val::str( $s['ai_instructions'] ?? '' ),
			'botEnabled'          => FLXC_Val::bool( $s['bot_enabled'] ?? 0 ),
			'botScope'            => FLXC_Val::str( $s['bot_scope'] ?? 'all' ),
			'botTestOnly'         => FLXC_Val::bool( $s['bot_test_only'] ?? 0 ),
			'botTestNumbers'      => FLXC_Val::str( $s['bot_test_numbers'] ?? '' ),
			'botPauseHours'       => FLXC_Val::int( $s['bot_pause_hours'] ?? 12 ),
			'botDailyLimit'       => FLXC_Val::int( $s['bot_daily_limit'] ?? 40 ),
			'botPresets'          => FLXC_Val::str( $s['bot_presets'] ?? '1, 3, 5, 10' ),
			'botContextHours'     => FLXC_Val::int( $s['bot_context_hours'] ?? 24 ),
			'botTypes'            => self::ints( $s['bot_types'] ?? array() ),
			'botReceiptAi'        => FLXC_Val::bool( $s['bot_receipt_ai'] ?? 1 ),
			'botPriceFooter'      => FLXC_Val::str( $s['bot_price_footer'] ?? '' ),
			'bankHolder'          => FLXC_Val::str( $s['bank_holder'] ?? '' ),
			'bankIban'            => FLXC_Val::str( $s['bank_iban'] ?? '' ),
			'bankDesc'            => FLXC_Val::str( $s['bank_desc'] ?? '' ),
			'donateUrl'           => FLXC_Val::str( $s['donate_url'] ?? '' ),
			'botAfterPayment'     => FLXC_Val::str( $s['bot_after_payment'] ?? '' ),
			'botCancelText'       => FLXC_Val::str( $s['bot_cancel_text'] ?? '' ),
			'botLimitText'        => FLXC_Val::str( $s['bot_limit_text'] ?? '' ),
			'botHandoffText'      => FLXC_Val::str( $s['bot_handoff_text'] ?? '' ),
			'botFallbackText'     => FLXC_Val::str( $s['bot_fallback_text'] ?? '' ),
			'botFallbackWait'     => FLXC_Val::int( $s['bot_fallback_wait'] ?? 30 ),
			'botTimeBudget'       => FLXC_Val::int( $s['bot_time_budget'] ?? 50 ),
			'botNotifyNumbers'    => FLXC_Val::str( $s['bot_notify_numbers'] ?? '' ),
			'botHandoffWords'     => FLXC_Val::str( $s['bot_handoff_words'] ?? '' ),
			'botInstructions'     => FLXC_Val::str( $s['bot_instructions'] ?? '' ),
			'botOptoutText'       => FLXC_Val::str( $s['bot_optout_text'] ?? '' ),
			'memEnabled'          => FLXC_Val::bool( $s['mem_enabled'] ?? 1 ),
			'memAfterHours'       => FLXC_Val::int( $s['mem_after_hours'] ?? 24 ),
			'workersMax'          => FLXC_Val::int( $s['workers_max'] ?? 20 ),
			'workersReserved'     => FLXC_Val::int( $s['workers_reserved'] ?? 5 ),
			'memWindowFrom'       => FLXC_Val::str( $s['mem_window_from'] ?? '03:00' ),
			'memWindowTo'         => FLXC_Val::str( $s['mem_window_to'] ?? '05:00' ),
			'botReactions'        => FLXC_Val::bool( $s['bot_reactions'] ?? 1 ),
			'botVoice'            => FLXC_Val::bool( $s['bot_voice'] ?? 1 ),
			'botVoiceMax'         => FLXC_Val::int( $s['bot_voice_max'] ?? 60 ),
			'botWidgets'          => FLXC_Val::bool( $s['bot_widgets'] ?? 1 ),
			'benefCertNotify'     => FLXC_Val::bool( $s['benef_cert_notify'] ?? 1 ),
			'benefCertText'       => FLXC_Val::str( $s['benef_cert_text'] ?? '' ),
			'remEnabled'          => FLXC_Val::bool( $s['rem_enabled'] ?? 1 ),
			'remTime'             => FLXC_Val::str( $s['rem_time'] ?? '09:00' ),
			'remMax'              => FLXC_Val::int( $s['rem_max'] ?? 10 ),
			'staffOn'             => FLXC_Val::bool( $s['staff_on'] ?? 1 ),
			'refNotify'           => FLXC_Val::bool( $s['ref_notify'] ?? 1 ),
			'staffDonorText'      => FLXC_Val::str( $s['staff_donor_text'] ?? '' ),
			'refCertNotify'       => FLXC_Val::bool( $s['ref_cert_notify'] ?? 1 ),
			'refCertText'         => FLXC_Val::str( $s['ref_cert_text'] ?? '' ),
			'staffCertMax'        => FLXC_Val::int( $s['staff_cert_max'] ?? 3 ),
			'emailVerify'         => FLXC_Val::bool( $s['email_verify'] ?? 1 ),
			'verifyTtl'           => FLXC_Val::int( $s['verify_ttl'] ?? 5 ),
			'verifyMaxFails'      => FLXC_Val::int( $s['verify_max_fails'] ?? 3 ),
			'verifyPhoneChannel'  => FLXC_Val::str( $s['verify_phone_channel'] ?? 'auto' ),
			'verifyFailText'      => FLXC_Val::str( $s['verify_fail_text'] ?? '' ),
			'verifyMsg'           => FLXC_Val::str( $s['verify_msg'] ?? '' ),
			'botSiteSearch'       => FLXC_Val::bool( $s['bot_site_search'] ?? 1 ),
			'botSiteTypes'        => array_values( array_map( static fn( $x ): string => FLXC_Val::str( $x ), FLXC_Val::arr( $s['bot_site_types'] ?? array() ) ) ),
			'botSiteExclude'      => FLXC_Val::str( $s['bot_site_exclude'] ?? '' ),
			'mirrorGroup'         => FLXC_Val::str( $s['mirror_group'] ?? '' ),
			'mirrorEnabled'       => FLXC_Val::bool( $s['mirror_enabled'] ?? 0 ),
			'notifyToGroup'       => FLXC_Val::bool( $s['notify_to_group'] ?? 1 ),
			'context'             => array(
				'flxiOn'                 => flxc_flxi_on(),
				'flxiConnectionsUrl'     => self::flxi_connections_url(),
				'flxsOn'                 => flxc_flxs_on(),
				'flxbOn'                 => flxc_flxb_on(),
				'rolesOn'                => function_exists( 'flxr_phone_identity' ),
				'aiHub'                  => flxc_ai_hub(),
				'aiReady'                => flxc_ai_ready(),
				'aiTypesProblem'         => FLXC_Val::str( flxc_ai_types_problem() ),
				'aiSettingsUrl'          => admin_url( 'admin.php?page=flx-ai-settings' ),
				'assistantUrl'           => admin_url( 'admin.php?page=flx-chat-asistan' ),
				'chatsUrl'               => admin_url( 'admin.php?page=flx-chat' ),
				'newUserUrl'             => admin_url( 'user-new.php' ),
				'widgetsRendererOk'      => flxc_widgets_on(),
				'widgetsPluginOk'        => function_exists( 'flxai_render_widget' ),
				'fluxBuildReady'         => file_exists( FLXC_DIR . 'assets/flux/flux-modules.js' ),
				'timezone'               => wp_timezone_string(),
				'isOpenHours'            => FLXC_Val::bool( flxc_is_open_hours() ),
				'siteName'               => FLXC_Val::str( get_bloginfo( 'name' ) ),
				'agents'                 => $agents,
				'aiProviders'            => $providers,
				'aiUsage'                => $usage,
				'defaultSource'          => self::default_source_choice(),
				'sourceChoices'          => self::connection_source_choices(),
				'donationTypes'          => $dtypes,
				'postTypes'              => $ptypes,
				'mirrorError'            => $merr,
				'pricePreview'           => FLXC_Val::str( flxc_bot_price_text() ),
				'defaultDonationPending' => FLXC_Val::str( flxc_record_text( 'msg_donation_pending' ) ),
				'defaultDonationDone'    => FLXC_Val::str( flxc_record_text( 'msg_donation_done' ) ),
				'defaultReceipt'         => FLXC_Val::str( flxc_record_text( 'msg_receipt' ) ),
				'defaultBankText'        => FLXC_Val::str( flxc_bank_text() ),
				'defaultBankHolder'      => FLXC_Val::str( $bank_acc['holder'] ?? '' ),
				'defaultBankIban'        => FLXC_Val::str( $bank_acc['iban'] ?? '' ),
				'defaultDonateUrl'       => '' !== FLXC_Val::str( $donate ) ? FLXC_Val::str( $donate ) : home_url( '/bagis/' ),
				'defaultCancelText'      => FLXC_Val::str( flxc_bot_cancel_ask( '{bagis}', '{tutar}' ) ),
				'defaultHandoffWords'    => implode( ', ', array_map( static fn( $x ): string => FLXC_Val::str( $x ), FLXC_Val::arr( flxc_bot_handoff_words_default() ) ) ),
				'defaultBenefCertText'   => FLXC_Val::str( flxc_benef_cert_default_text() ),
				'defaultRefCertText'     => FLXC_Val::str( flxc_ref_cert_default_text() ),
				'defaultVerifyFailText'  => FLXC_Val::str( flxc_verify_fail_text() ),
				'defaultVerifyMsg'       => FLXC_Val::str( flxc_verify_default_text() ),
			),
		);
	}

	/**
	 * Saves a single classic tab (full tab payload, matching classic form POST semantics)
	 * or partial GraphQL fields when `$tab` is empty.
	 *
	 * @param string              $tab   Tab slug (`baglantilar|genel|otomatik|etiketler|kayitlar|ai`) or empty for partial.
	 * @param array<string,mixed> $in    Raw form/GraphQL input.
	 * @return array<string,mixed> Updated settings view.
	 */
	public static function save( string $tab, array $in ): array {
		self::apply( $tab, $in );
		return self::get();
	}

	/**
	 * Applies the input to the stored settings. The classic form posts a whole tab; GraphQL sends only the
	 * fields it changes (`$tab` empty) and may clear a secret with "__clear__" (write-only).
	 *
	 * @param string              $tab Tab slug or empty for a partial update.
	 * @param array<string,mixed> $in  Raw input (snake_case keys).
	 */
	public static function apply( string $tab, array $in ): void {
		$upd = array();
		if ( '' === $tab ) {
			$upd = self::build_partial_update( $in );
		} elseif ( 'baglantilar' === $tab ) {
			$upd['connections'] = self::sanitize_connections( FLXC_Val::arr( $in['connections'] ?? array() ), false );
		} elseif ( 'genel' === $tab ) {
			$accent                  = sanitize_hex_color( FLXC_Val::raw_str( $in['accent'] ?? '' ) );
			$upd['brand_name']       = sanitize_text_field( FLXC_Val::raw_str( $in['brand_name'] ?? '' ) );
			$upd['accent']           = $accent ? $accent : '#00a884';
			$upd['send_read']        = empty( $in['send_read'] ) ? 0 : 1;
			$upd['agent_signature']  = empty( $in['agent_signature'] ) ? 0 : 1;
			$upd['restrict_agents']  = empty( $in['restrict_agents'] ) ? 0 : 1;
			$upd['show_groups']      = empty( $in['show_groups'] ) ? 0 : 1;
			$upd['ui_flux']          = empty( $in['ui_flux'] ) ? 0 : 1;
			$upd['delete_data']      = empty( $in['delete_data'] ) ? 0 : 1;
			$upd['poll_interval']    = min( 60, max( 3, FLXC_Val::raw_int( $in['poll_interval'] ?? 10 ) ) );
			$upd['delete_everyone_hours'] = min( 60, max( 0, FLXC_Val::raw_int( $in['delete_everyone_hours'] ?? 60 ) ) );
		} elseif ( 'otomatik' === $tab ) {
			$upd['welcome_enabled'] = empty( $in['welcome_enabled'] ) ? 0 : 1;
			$upd['welcome_text']    = sanitize_textarea_field( FLXC_Val::raw_str( $in['welcome_text'] ?? '' ) );
			$upd['away_enabled']    = empty( $in['away_enabled'] ) ? 0 : 1;
			$upd['away_text']       = sanitize_textarea_field( FLXC_Val::raw_str( $in['away_text'] ?? '' ) );
			$upd['away_days']       = array_values(
				array_filter(
					self::ints( $in['away_days'] ?? array() ),
					static fn( int $d ): bool => $d >= 1 && $d <= 7
				)
			);
			$start = FLXC_Val::raw_str( $in['away_start'] ?? '' );
			$end   = FLXC_Val::raw_str( $in['away_end'] ?? '' );
			$upd['away_start']    = preg_match( '/^\d{2}:\d{2}$/', $start ) ? $start : '09:00';
			$upd['away_end']      = preg_match( '/^\d{2}:\d{2}$/', $end ) ? $end : '18:00';
			$upd['away_throttle'] = min( 72, max( 1, FLXC_Val::raw_int( $in['away_throttle'] ?? 6 ) ) );
		} elseif ( 'etiketler' === $tab ) {
			$upd['labels'] = self::sanitize_labels( FLXC_Val::arr( $in['labels'] ?? array() ) );
		} elseif ( 'kayitlar' === $tab ) {
			foreach ( array( 'agents_donations', 'agents_certificates', 'chips_bar' ) as $k ) {
				$upd[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
			}
			foreach ( array( 'msg_donation_pending', 'msg_donation_done', 'msg_receipt', 'bank_text' ) as $k ) {
				$upd[ $k ] = sanitize_textarea_field( FLXC_Val::raw_str( $in[ $k ] ?? '' ) );
			}
		} elseif ( 'ai' === $tab ) {
			$upd['ai_provider'] = sanitize_key( FLXC_Val::raw_str( $in['ai_provider'] ?? '' ) );
			foreach ( array( 'ai_model', 'ai_model_bot', 'ai_model_vision', 'ai_model_audio' ) as $f ) {
				$upd[ $f ] = FLXC_Val::str( preg_replace( '/[^a-zA-Z0-9_.:\/@~+-]/', '', trim( FLXC_Val::raw_str( $in[ $f ] ?? '' ) ) ) );
			}
			$temp = FLXC_Val::raw_float( str_replace( ',', '.', FLXC_Val::raw_str( $in['ai_temperature'] ?? 0.4 ) ) );
			$upd['ai_temperature']    = min( 1.0, max( 0.0, $temp ) );
			$upd['ai_auto_suggest']   = empty( $in['ai_auto_suggest'] ) ? 0 : 1;
			$upd['bot_enabled']       = empty( $in['bot_enabled'] ) ? 0 : 1;
			$upd['bot_receipt_ai']    = empty( $in['bot_receipt_ai'] ) ? 0 : 1;
			$scope                    = FLXC_Val::raw_str( $in['bot_scope'] ?? '' );
			$upd['bot_scope']         = in_array( $scope, array( 'all', 'away', 'unassigned' ), true ) ? $scope : 'all';
			$upd['bot_test_only']     = empty( $in['bot_test_only'] ) ? 0 : 1;
			$upd['bot_test_numbers']  = self::sanitize_phone_list( FLXC_Val::raw_str( $in['bot_test_numbers'] ?? '' ) );
			$upd['bot_notify_numbers'] = self::sanitize_phone_list( FLXC_Val::raw_str( $in['bot_notify_numbers'] ?? '' ) );
			$upd['bot_context_hours'] = min( 168, max( 1, FLXC_Val::raw_int( $in['bot_context_hours'] ?? 24 ) ) );
			$upd['bot_pause_hours']   = min( 720, max( 1, FLXC_Val::raw_int( $in['bot_pause_hours'] ?? 12 ) ) );
			$upd['bot_daily_limit']   = min( 500, max( 5, FLXC_Val::raw_int( $in['bot_daily_limit'] ?? 40 ) ) );
			$upd['bot_types']         = array_values( array_filter( array_map( static fn( $x ): int => abs( FLXC_Val::raw_int( $x ) ), FLXC_Val::arr( $in['bot_types'] ?? array() ) ) ) );
			$upd['bot_presets']       = self::sanitize_presets( FLXC_Val::raw_str( $in['bot_presets'] ?? '' ) );
			$upd['bank_iban']         = strtoupper( FLXC_Val::str( preg_replace( '/[^A-Za-z0-9 ]/', '', FLXC_Val::raw_str( $in['bank_iban'] ?? '' ) ) ) );
			$upd['donate_url']        = esc_url_raw( FLXC_Val::raw_str( $in['donate_url'] ?? '' ) );
			$upd['mem_enabled']       = empty( $in['mem_enabled'] ) ? 0 : 1;
			$upd['mem_after_hours']   = min( 168, max( 1, FLXC_Val::raw_int( $in['mem_after_hours'] ?? 24 ) ) );
			$upd['workers_max']       = min( 30, max( 1, FLXC_Val::raw_int( $in['workers_max'] ?? 20 ) ) );
			$upd['workers_reserved']  = min( 30, max( 0, FLXC_Val::raw_int( $in['workers_reserved'] ?? 5 ) ) );
			foreach ( array( 'mem_window_from' => '03:00', 'mem_window_to' => '05:00' ) as $f => $def ) {
				$v         = FLXC_Val::raw_str( $in[ $f ] ?? '' );
				$upd[ $f ] = preg_match( '/^\d{2}:\d{2}$/', $v ) ? $v : $def;
			}
			$upd['bot_site_search'] = empty( $in['bot_site_search'] ) ? 0 : 1;
			$upd['bot_site_types']  = array_values( array_intersect( array_map( static fn( $x ): string => sanitize_key( $x ), array_map( static fn( $x ): string => FLXC_Val::str( $x ), FLXC_Val::arr( $in['bot_site_types'] ?? array() ) ) ), get_post_types( array( 'public' => true ) ) ) );
			$upd['mirror_enabled']  = empty( $in['mirror_enabled'] ) ? 0 : 1;
			$upd['bot_time_budget'] = min( 110, max( 20, FLXC_Val::raw_int( $in['bot_time_budget'] ?? 50 ) ) );
			$upd['rem_enabled']     = empty( $in['rem_enabled'] ) ? 0 : 1;
			$rtime                  = FLXC_Val::raw_str( $in['rem_time'] ?? '' );
			$upd['rem_time']        = preg_match( '/^\d{2}:\d{2}$/', $rtime ) ? $rtime : '09:00';
			$upd['rem_max']         = min( 50, max( 1, FLXC_Val::raw_int( $in['rem_max'] ?? 10 ) ) );
			$upd['email_verify']    = empty( $in['email_verify'] ) ? 0 : 1;
			$upd['verify_ttl']      = min( 30, max( 1, FLXC_Val::raw_int( $in['verify_ttl'] ?? 5 ) ) );
			$ch                     = FLXC_Val::raw_str( $in['verify_phone_channel'] ?? '' );
			$upd['verify_phone_channel'] = in_array( $ch, array( 'auto', 'whatsapp', 'sms' ), true ) ? $ch : 'auto';
			$upd['verify_msg']      = sanitize_textarea_field( FLXC_Val::raw_str( $in['verify_msg'] ?? '' ) );
			$upd['bot_cancel_text'] = sanitize_textarea_field( FLXC_Val::raw_str( $in['bot_cancel_text'] ?? '' ) );
			$upd['verify_max_fails'] = min( 10, max( 1, FLXC_Val::raw_int( $in['verify_max_fails'] ?? 3 ) ) );
			$upd['verify_fail_text'] = sanitize_text_field( FLXC_Val::raw_str( $in['verify_fail_text'] ?? '' ) );
			$upd['bot_fallback_wait'] = min( 45, max( 0, FLXC_Val::raw_int( $in['bot_fallback_wait'] ?? 30 ) ) );
			$upd['notify_to_group'] = empty( $in['notify_to_group'] ) ? 0 : 1;
			$upd['staff_on']        = empty( $in['staff_on'] ) ? 0 : 1;
			$upd['ref_notify']      = empty( $in['ref_notify'] ) ? 0 : 1;
			$upd['benef_cert_notify'] = empty( $in['benef_cert_notify'] ) ? 0 : 1;
			$upd['ref_cert_notify'] = empty( $in['ref_cert_notify'] ) ? 0 : 1;
			$upd['staff_cert_max']  = min( 20, max( 0, FLXC_Val::raw_int( $in['staff_cert_max'] ?? 3 ) ) );
			$upd['bot_widgets']     = empty( $in['bot_widgets'] ) ? 0 : 1;
			$upd['bot_voice']       = empty( $in['bot_voice'] ) ? 0 : 1;
			$upd['bot_reactions']   = empty( $in['bot_reactions'] ) ? 0 : 1;
			$upd['bot_voice_max']   = min( 300, max( 10, FLXC_Val::raw_int( $in['bot_voice_max'] ?? 60 ) ) );
			$mg                     = FLXC_Val::raw_str( $in['mirror_group'] ?? '' );
			$upd['mirror_group']    = preg_match( '/^[0-9-]+@g\.us$/', $mg ) ? $mg : '';
			foreach ( array( 'bot_price_footer', 'bank_holder', 'bank_desc', 'bot_after_payment', 'bot_handoff_text', 'bot_limit_text', 'staff_donor_text', 'benef_cert_text', 'ref_cert_text', 'bot_optout_text', 'bot_site_exclude', 'bot_fallback_text' ) as $f ) {
				$upd[ $f ] = sanitize_text_field( FLXC_Val::raw_str( $in[ $f ] ?? '' ) );
			}
			foreach ( array( 'ai_instructions', 'bot_instructions', 'bot_handoff_words' ) as $f ) {
				$upd[ $f ] = sanitize_textarea_field( FLXC_Val::raw_str( $in[ $f ] ?? '' ) );
			}
		}

		flxc_update_settings( $upd );
		self::changed();
	}

	/**
	 * Partial GraphQL update when no single tab slug was specified.
	 *
	 * @param array<string,mixed> $in Input keys (snake_case).
	 * @return array<string,mixed>
	 */
	private static function build_partial_update( array $in ): array {
		$upd = array();
		if ( array_key_exists( 'connections', $in ) && is_array( $in['connections'] ) ) {
			$upd['connections'] = self::sanitize_connections( $in['connections'], true );
		}
		if ( array_key_exists( 'labels', $in ) && is_array( $in['labels'] ) ) {
			$upd['labels'] = self::sanitize_labels( $in['labels'] );
		}
		$bools = array(
			'send_read', 'agent_signature', 'restrict_agents', 'show_groups', 'ui_flux', 'delete_data',
			'welcome_enabled', 'away_enabled', 'agents_donations', 'agents_certificates', 'chips_bar',
			'ai_auto_suggest', 'bot_enabled', 'bot_test_only', 'bot_receipt_ai', 'mem_enabled',
			'bot_site_search', 'mirror_enabled', 'rem_enabled', 'email_verify', 'notify_to_group',
			'staff_on', 'ref_notify', 'benef_cert_notify', 'ref_cert_notify', 'bot_widgets',
			'bot_voice', 'bot_reactions',
		);
		foreach ( $bools as $k ) {
			if ( array_key_exists( $k, $in ) ) {
				$upd[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
			}
		}
		$ints = array(
			'poll_interval'         => array( 3, 60 ),
			'delete_everyone_hours' => array( 0, 60 ),
			'away_throttle'         => array( 1, 72 ),
			'bot_context_hours'     => array( 1, 168 ),
			'bot_pause_hours'       => array( 1, 720 ),
			'bot_daily_limit'       => array( 5, 500 ),
			'mem_after_hours'       => array( 1, 168 ),
			'workers_max'           => array( 1, 30 ),
			'workers_reserved'      => array( 0, 30 ),
			'bot_time_budget'       => array( 20, 110 ),
			'rem_max'               => array( 1, 50 ),
			'verify_ttl'            => array( 1, 30 ),
			'verify_max_fails'      => array( 1, 10 ),
			'bot_fallback_wait'     => array( 0, 45 ),
			'staff_cert_max'        => array( 0, 20 ),
			'bot_voice_max'         => array( 10, 300 ),
		);
		foreach ( $ints as $k => $rng ) {
			if ( array_key_exists( $k, $in ) ) {
				$upd[ $k ] = min( $rng[1], max( $rng[0], FLXC_Val::int( $in[ $k ] ) ) );
			}
		}
		$texts = array(
			'brand_name', 'bot_price_footer', 'bank_holder', 'bank_desc', 'bot_after_payment',
			'bot_handoff_text', 'bot_limit_text', 'staff_donor_text', 'benef_cert_text', 'ref_cert_text',
			'bot_optout_text', 'bot_site_exclude', 'bot_fallback_text', 'verify_fail_text',
		);
		foreach ( $texts as $k ) {
			if ( array_key_exists( $k, $in ) ) {
				$upd[ $k ] = sanitize_text_field( FLXC_Val::str( $in[ $k ] ) );
			}
		}
		$textareas = array(
			'welcome_text', 'away_text', 'msg_donation_pending', 'msg_donation_done', 'msg_receipt',
			'bank_text', 'verify_msg', 'bot_cancel_text', 'ai_instructions', 'bot_instructions', 'bot_handoff_words',
		);
		foreach ( $textareas as $k ) {
			if ( array_key_exists( $k, $in ) ) {
				$upd[ $k ] = sanitize_textarea_field( FLXC_Val::str( $in[ $k ] ) );
			}
		}
		if ( array_key_exists( 'accent', $in ) ) {
			$accent        = sanitize_hex_color( FLXC_Val::str( $in['accent'] ) );
			$upd['accent'] = $accent ? $accent : '#00a884';
		}
		if ( array_key_exists( 'away_days', $in ) ) {
			$upd['away_days'] = array_values( array_filter( self::ints( $in['away_days'] ), static fn( int $d ): bool => $d >= 1 && $d <= 7 ) );
		}
		foreach ( array( 'away_start' => '09:00', 'away_end' => '18:00', 'mem_window_from' => '03:00', 'mem_window_to' => '05:00', 'rem_time' => '09:00' ) as $f => $def ) {
			if ( array_key_exists( $f, $in ) ) {
				$v         = FLXC_Val::str( $in[ $f ] );
				$upd[ $f ] = preg_match( '/^\d{2}:\d{2}$/', $v ) ? $v : $def;
			}
		}
		if ( array_key_exists( 'ai_provider', $in ) ) {
			$upd['ai_provider'] = sanitize_key( FLXC_Val::str( $in['ai_provider'] ) );
		}
		foreach ( array( 'ai_model', 'ai_model_bot', 'ai_model_vision', 'ai_model_audio' ) as $f ) {
			if ( array_key_exists( $f, $in ) ) {
				$upd[ $f ] = FLXC_Val::str( preg_replace( '/[^a-zA-Z0-9_.:\/@~+-]/', '', trim( FLXC_Val::str( $in[ $f ] ) ) ) );
			}
		}
		if ( array_key_exists( 'ai_temperature', $in ) ) {
			$upd['ai_temperature'] = min( 1.0, max( 0.0, FLXC_Val::float( $in['ai_temperature'] ) ) );
		}
		if ( array_key_exists( 'bot_scope', $in ) ) {
			$sc               = FLXC_Val::str( $in['bot_scope'] );
			$upd['bot_scope'] = in_array( $sc, array( 'all', 'away', 'unassigned' ), true ) ? $sc : 'all';
		}
		if ( array_key_exists( 'verify_phone_channel', $in ) ) {
			$vc                          = FLXC_Val::str( $in['verify_phone_channel'] );
			$upd['verify_phone_channel'] = in_array( $vc, array( 'auto', 'whatsapp', 'sms' ), true ) ? $vc : 'auto';
		}
		if ( array_key_exists( 'bot_test_numbers', $in ) ) {
			$upd['bot_test_numbers'] = self::sanitize_phone_list( FLXC_Val::str( $in['bot_test_numbers'] ) );
		}
		if ( array_key_exists( 'bot_notify_numbers', $in ) ) {
			$upd['bot_notify_numbers'] = self::sanitize_phone_list( FLXC_Val::str( $in['bot_notify_numbers'] ) );
		}
		if ( array_key_exists( 'bot_types', $in ) ) {
			$upd['bot_types'] = array_values( array_filter( array_map( static fn( $x ): int => abs( FLXC_Val::raw_int( $x ) ), FLXC_Val::arr( $in['bot_types'] ) ) ) );
		}
		if ( array_key_exists( 'bot_presets', $in ) ) {
			$upd['bot_presets'] = self::sanitize_presets( FLXC_Val::str( $in['bot_presets'] ) );
		}
		if ( array_key_exists( 'bank_iban', $in ) ) {
			$upd['bank_iban'] = strtoupper( FLXC_Val::str( preg_replace( '/[^A-Za-z0-9 ]/', '', FLXC_Val::str( $in['bank_iban'] ) ) ) );
		}
		if ( array_key_exists( 'donate_url', $in ) ) {
			$upd['donate_url'] = esc_url_raw( FLXC_Val::str( $in['donate_url'] ) );
		}
		if ( array_key_exists( 'bot_site_types', $in ) ) {
			$upd['bot_site_types'] = array_values( array_intersect( array_map( static fn( $x ): string => sanitize_key( $x ), array_map( static fn( $x ): string => FLXC_Val::str( $x ), FLXC_Val::arr( $in['bot_site_types'] ) ) ), get_post_types( array( 'public' => true ) ) ) );
		}
		if ( array_key_exists( 'mirror_group', $in ) ) {
			$mg                  = FLXC_Val::str( $in['mirror_group'] );
			$upd['mirror_group'] = preg_match( '/^[0-9-]+@g\.us$/', $mg ) ? $mg : '';
		}
		return $upd;
	}

	/**
	 * @param array<mixed> $rows        Connection input rows.
	 * @param bool         $allow_clear "__clear__" removes the stored API key (GraphQL only).
	 * @return list<array<mixed>>
	 */
	private static function sanitize_connections( array $rows, bool $allow_clear ): array {
		$old  = FLXC_Val::arr( flxc_connections() );
		$list = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = sanitize_key( FLXC_Val::raw_str( $row['id'] ?? '' ) );
			if ( ! empty( $row['delete'] ) ) {
				continue;
			}
			$prev = '' !== $id && isset( $old[ $id ] ) && is_array( $old[ $id ] ) ? $old[ $id ] : null;
			$c    = $prev ? $prev : FLXC_Val::arr( flxc_new_connection() );
			$src  = FLXC_Val::raw_str( $row['source'] ?? 'own' );
			if ( 0 === strpos( $src, 'flxi:' ) ) {
				$pid = (int) substr( $src, 5 );
				if ( 'flxi' !== FLXC_Val::raw_str( $c['source'] ?? '' ) || FLXC_Val::int( $c['provider_id'] ?? 0 ) !== $pid ) {
					$c['flxs_id'] = 0;
				}
				$c['source']      = 'flxi';
				$c['provider_id'] = $pid;
			} elseif ( 0 === strpos( $src, 'flxs:' ) ) {
				$c['source']      = 'flxs';
				$c['provider_id'] = (int) substr( $src, 5 );
			} else {
				$c['source']      = 'own';
				$c['provider_id'] = 0;
			}
			$label         = sanitize_text_field( FLXC_Val::raw_str( $row['label'] ?? '' ) );
			$c['label']    = '' !== $label ? $label : 'WhatsApp';
			$c['base_url'] = esc_url_raw( trim( FLXC_Val::raw_str( $row['base_url'] ?? '' ) ) );
			$c['instance'] = sanitize_text_field( FLXC_Val::raw_str( $row['instance'] ?? '' ) );
			$key           = trim( FLXC_Val::raw_str( $row['api_key'] ?? '' ) );
			if ( $allow_clear && '__clear__' === $key ) {
				$c['api_key'] = '';
			} elseif ( '' !== $key ) {
				$c['api_key'] = sanitize_text_field( $key );
			}
			if ( ! empty( $row['new_secret'] ) || ! empty( $row['rotate_secret'] ) ) {
				$c['secret'] = wp_generate_password( 32, false, false );
			}
			$list[] = $c;
		}
		return $list;
	}

	/**
	 * @param array<mixed> $rows Label rows.
	 * @return list<array{id: string, name: string, color: string}>
	 */
	private static function sanitize_labels( array $rows ): array {
		$labels = array();
		$seen   = array();
		foreach ( $rows as $l ) {
			if ( ! is_array( $l ) ) {
				continue;
			}
			$name = sanitize_text_field( FLXC_Val::raw_str( $l['name'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			$id = sanitize_key( FLXC_Val::raw_str( $l['id'] ?? '' ) );
			if ( '' === $id ) {
				$id = substr( sanitize_key( remove_accents( $name ) ), 0, 30 );
				$id = '' !== $id ? $id : 'l' . strtolower( wp_generate_password( 5, false, false ) );
			}
			while ( isset( $seen[ $id ] ) ) {
				$id .= '2';
			}
			$seen[ $id ] = 1;
			$color       = sanitize_hex_color( FLXC_Val::raw_str( $l['color'] ?? '' ) );
			$labels[]    = array(
				'id'    => $id,
				'name'  => $name,
				'color' => $color ? $color : '#25d366',
			);
		}
		return $labels;
	}

	private static function sanitize_phone_list( string $raw ): string {
		$parts = (array) preg_split( '/[\s,;]+/', $raw );
		return implode( ', ', array_filter( array_map( static fn( $p ): string => FLXC_Val::str( flxc_normalize_phone( $p ) ), $parts ) ) );
	}

	private static function sanitize_presets( string $raw ): string {
		$parts = (array) preg_split( '/[\s,;]+/', $raw );
		return implode( ', ', array_slice( array_filter( array_map( static fn( $p ): int => absint( $p ), $parts ) ), 0, 8 ) );
	}

	/**
	 * Reveals (and optionally rotates) the webhook URL containing `?t=<secret>` for managers.
	 *
	 * @return array{url: string}|WP_Error
	 */
	public static function webhook_url_reveal( string $conn_id, bool $regenerate = false ) {
		$id = sanitize_key( $conn_id );
		$c  = flxc_get_connection( $id );
		if ( ! is_array( $c ) ) {
			return new WP_Error( 'flxc_conn', __( 'Connection not found. Save the settings first.', 'flx-chat' ), array( 'status' => 404 ) );
		}
		if ( $regenerate ) {
			$list = array();
			foreach ( self::rows( flxc_connections() ) as $row ) {
				if ( FLXC_Val::str( $row['id'] ?? '' ) === $id ) {
					$row['secret'] = wp_generate_password( 32, false, false );
				}
				$list[] = $row;
			}
			flxc_update_settings( array( 'connections' => $list ) );
			self::changed();
			$c = flxc_get_connection( $id );
			if ( ! is_array( $c ) ) {
				return new WP_Error( 'flxc_conn', __( 'Connection not found. Save the settings first.', 'flx-chat' ), array( 'status' => 404 ) );
			}
		}
		return array( 'url' => FLXC_Val::str( flxc_webhook_url( $c ) ) );
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public static function conn_or_error( string $id ) {
		$c = flxc_get_connection( sanitize_key( $id ) );
		return is_array( $c ) ? $c : new WP_Error( 'flxc_conn', __( 'Connection not found. Save the settings first.', 'flx-chat' ), array( 'status' => 404 ) );
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public static function connection_test( string $id ) {
		$c = self::conn_or_error( $id );
		if ( is_wp_error( $c ) ) {
			return $c;
		}
		$cid = FLXC_Val::str( $c['id'] );
		$st  = FLXC_Val::arr( flxc_evo_connection_state( $cid ) );
		if ( ! empty( $st['state'] ) ) {
			flxc_set_connection_state( $cid, FLXC_Val::str( $st['state'] ) );
			self::changed();
		}
		$hook  = FLXC_Val::arr( flxc_evo_find_webhook( $cid ) );
		$hjson = FLXC_Val::arr( $hook['json'] ?? array() );
		$hwh   = FLXC_Val::arr( $hjson['webhook'] ?? array() );
		$url   = FLXC_Val::str( $hjson['url'] ?? ( $hwh['url'] ?? '' ) );
		$ok    = ! empty( $st['ok'] );
		$count = $ok ? flxc_evo_message_count( $cid ) : null;
		$base  = strtok( FLXC_Val::str( flxc_webhook_url( $c ) ), '?' );
		return array(
			'stored'      => is_wp_error( $count ) || null === $count ? -1 : FLXC_Val::int( $count ),
			'ok'          => $ok,
			'state'       => FLXC_Val::str( $st['state'] ?? '' ),
			'error'       => FLXC_Val::str( $st['error'] ?? '' ),
			'webhook_ok'  => '' !== $url && is_string( $base ) && 0 === strpos( $url, $base ),
			'webhook_url' => '' !== $url ? FLXC_Val::str( preg_replace( '/([?&]t=)[^&]+/', '$1•••', $url ) ) : '',
			'version'     => $ok ? FLXC_Val::str( flxc_evo_version( $cid, true ) ) : '',
		);
	}

	/**
	 * @return array{ok: mixed, error: mixed}|WP_Error
	 */
	public static function connection_webhook( string $id ) {
		$c = self::conn_or_error( $id );
		if ( is_wp_error( $c ) ) {
			return $c;
		}
		$r = FLXC_Val::arr( flxc_evo_set_webhook( $c['id'] ) );
		self::changed();
		return array( 'ok' => $r['ok'] ?? false, 'error' => $r['error'] ?? '' );
	}

	/**
	 * @return array<mixed>|WP_Error
	 */
	public static function connection_qr( string $id ) {
		$c = self::conn_or_error( $id );
		if ( is_wp_error( $c ) ) {
			return $c;
		}
		$cid = FLXC_Val::str( $c['id'] );
		$st  = FLXC_Val::arr( flxc_evo_connection_state( $cid ) );
		if ( 'open' === FLXC_Val::str( $st['state'] ?? '' ) ) {
			flxc_set_connection_state( $cid, 'open' );
			self::changed();
			return array( 'ok' => true, 'state' => 'open' );
		}
		return FLXC_Val::arr( flxc_evo_connect( $cid ) );
	}

	/**
	 * @return array<mixed>|WP_Error
	 */
	public static function connection_import( string $id, int $chats = 30 ) {
		$c = self::conn_or_error( $id );
		if ( is_wp_error( $c ) ) {
			return $c;
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore
		}
		$r = flxc_import_history( FLXC_Val::str( $c['id'] ), min( 200, max( 1, $chats > 0 ? $chats : 30 ) ), 30 );
		if ( is_wp_error( $r ) ) {
			return array( 'ok' => false, 'error' => $r->get_error_message() );
		}
		FLXC_Chat_Service::changed();
		return array( 'ok' => true ) + FLXC_Val::arr( $r );
	}

	/**
	 * @return array{errorCount: int, warningCount: int, okCount: int, items: list<array<string,mixed>>}
	 */
	public static function system_checks(): array {
		$checks = self::rows( flxc_system_checks() );
		$counts = array();
		foreach ( $checks as $c ) {
			$st            = FLXC_Val::str( $c['status'] ?? '' );
			$counts[ $st ] = ( $counts[ $st ] ?? 0 ) + 1;
		}
		$items = array();
		foreach ( $checks as $c ) {
			$url = FLXC_Val::str( $c['action_url'] ?? '' );
			$tab = '';
			if ( '' !== $url ) {
				$q = wp_parse_url( $url, PHP_URL_QUERY );
				if ( is_string( $q ) ) {
					parse_str( $q, $parts );
					$tab = FLXC_Val::str( $parts['tab'] ?? '' );
				}
			}
			$items[] = array(
				'id'          => FLXC_Val::str( $c['id'] ?? '' ),
				'group'       => FLXC_Val::str( $c['group'] ?? '' ),
				'label'       => FLXC_Val::str( $c['label'] ?? '' ),
				'status'      => FLXC_Val::str( $c['status'] ?? 'info' ),
				'message'     => FLXC_Val::str( $c['message'] ?? '' ),
				'actionTab'   => $tab,
				'actionUrl'   => $url,
				'actionLabel' => FLXC_Val::str( $c['action_label'] ?? '' ),
			);
		}
		return array(
			'errorCount'   => FLXC_Val::int( $counts['error'] ?? 0 ),
			'warningCount' => FLXC_Val::int( $counts['warning'] ?? 0 ),
			'okCount'      => FLXC_Val::int( $counts['ok'] ?? 0 ),
			'items'        => $items,
		);
	}

	/**
	 * @return list<array{id: string, name: string}>
	 */
	public static function groups( bool $fresh = false ): array {
		$out = array();
		foreach ( FLXC_Val::arr( flxc_mirror_groups( $fresh ) ) as $jid => $name ) {
			$out[] = array(
				'id'   => FLXC_Val::str( $jid ),
				'name' => FLXC_Val::str( $name ),
			);
		}
		return $out;
	}

	/**
	 * Model catalog and ready providers, the shape of GET /admin/ai/models.
	 *
	 * @return array{models: mixed, providers: list<array{id: mixed, label: mixed}>}|WP_Error
	 */
	public static function ai_models( bool $fresh = false ) {
		$m = flxc_ai_models( $fresh );
		if ( is_wp_error( $m ) ) {
			$m->add_data( array( 'status' => 502 ) );
			return $m;
		}
		$prov = array();
		foreach ( FLXC_Val::arr( flxc_ai_provider_list() ) as $id => $label ) {
			$prov[] = array( 'id' => $id, 'label' => $label );
		}
		return array( 'models' => $m, 'providers' => $prov );
	}

	/**
	 * @return array{text: string, ms: int}|WP_Error
	 */
	public static function ai_test() {
		$t = microtime( true );
		$r = flxc_ai_chat(
			array(
				array( 'role' => 'system', 'content' => 'Kısa cevap ver.' ),
				array( 'role' => 'user', 'content' => 'Merhaba! Tek cümleyle kendini tanıt.' ),
			),
			array( 'max_tokens' => 60, 'timeout' => 30 )
		);
		if ( is_wp_error( $r ) ) {
			$r->add_data( array( 'status' => 400 ) );
			return $r;
		}
		return array(
			'text' => FLXC_Val::str( flxc_ai_clean_text( FLXC_Val::raw_str( $r['content'] ?? '' ) ) ),
			'ms'   => (int) ( ( microtime( true ) - $t ) * 1000 ),
		);
	}
}
