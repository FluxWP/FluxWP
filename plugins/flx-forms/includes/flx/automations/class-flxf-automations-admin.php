<?php
/**
 * Flux Forms Automations: admin screens (Forms › Automations): flow list, flow editor, run log.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FLXF_Automations_Admin {

	const SLUG  = 'flxf_automations';
	const NONCE = 'flxf_automations';

	private static $hook = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 99 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		foreach ( array( 'save', 'toggle', 'delete', 'duplicate', 'default', 'donation_form', 'clear_runs' ) as $a ) {
			add_action( 'wp_ajax_flxf_auto_' . $a, array( __CLASS__, 'ajax_' . $a ) );
		}
	}

	public static function menu() {
		self::$hook = (string) add_submenu_page(
			'Emsfb',
			__( '\\nAutomations\\n', 'flx-forms' ),
			__( '\\nAutomations\\n', 'flx-forms' ),
			FLXF_Automations::capability(),
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	private static function ver( $file ) {
		$p = __DIR__ . '/assets/' . $file;
		return is_readable( $p ) ? (string) filemtime( $p ) : '1';
	}

	private static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	public static function assets( $hook ) {
		if ( false !== strpos( (string) $hook, 'Emsfb_create' ) ) {
			/* EFB enqueues its builder scripts while rendering the page, so join in the footer. */
			add_action( 'admin_footer', array( __CLASS__, 'builder_template_assets' ), 1 );
		}
		if ( '' === self::$hook || $hook !== self::$hook ) {
			return;
		}
		wp_enqueue_style( 'flxf-automations', plugins_url( 'assets/admin.css', __FILE__ ), array(), self::ver( 'admin.css' ) );
		$flow_param = isset( $_GET['flow'] ) ? sanitize_key( wp_unslash( $_GET['flow'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		wp_enqueue_script( 'flxf-automations', plugins_url( 'assets/admin.js', __FILE__ ), array(), self::ver( 'admin.js' ), true );
		wp_localize_script( 'flxf-automations', 'FLXF_AUTO', self::boot_data( $flow_param ) );
	}

	/** "Sapling Donation" card on EFB's Create screen (added from outside admin-efb.js). */
	public static function builder_template_assets() {
		if ( ! wp_script_is( 'Emsfb-admin-js', 'registered' ) && ! wp_script_is( 'Emsfb-admin-js', 'enqueued' ) ) {
			return;
		}
		wp_enqueue_script( 'flxf-automations-form-template', plugins_url( 'assets/form-template.js', __FILE__ ), array( 'Emsfb-admin-js', 'efb-forms-js' ), self::ver( 'form-template.js' ), true );
		wp_localize_script(
			'flxf-automations-form-template',
			'FLXF_FORM_TPL',
			array(
				'title'     => FLXF_Automations::seed_text( 'Sapling Donation' ),
				'desc'      => __( '\\nDonation form with sapling packages, card payment and a certificate automation.\\n', 'flx-forms' ),
				'structure' => FLXF_Automations::donation_form_structure(),
			)
		);
	}

	private static function boot_data( $flow_param ) {
		$flow = null;
		if ( 'new' === $flow_param ) {
			$flow = array(
				'id'         => 0,
				'title'      => '',
				'enabled'    => true,
				'trigger'    => array( 'type' => 'form_submitted', 'form_id' => 0 ),
				'match'      => 'all',
				'conditions' => array(),
				'actions'    => array(),
			);
		} elseif ( '' !== $flow_param ) {
			$flow = FLXF_Automations::get_flow( absint( $flow_param ) );
		}
		$forms = array();
		foreach ( FLXF_Automations::forms() as $f ) {
			$f['guess'] = FLXF_Automations::guess_mapping( $f['fields'] );
			$forms[]    = $f;
		}
		$channels = function_exists( 'flxs_channels' ) ? flxs_channels() : array( 'email' => __( 'Email', 'flx-forms' ), 'sms' => 'SMS', 'whatsapp' => 'WhatsApp' );
		return array(
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( self::NONCE ),
			'listUrl'       => self::url(),
			'editUrl'       => self::url( array( 'flow' => '' ) ),
			'flow'          => $flow,
			'forms'         => $forms,
			'certAvailable' => FLXF_Automations::certificates_available(),
			'templates'     => FLXF_Automations::cert_templates(),
			'events'        => FLXF_Automations::cert_events(),
			'providers'     => FLXF_Automations::cert_providers(),
			'saplingPrice'  => FLXF_Automations::sapling_unit_price(),
			'channels'      => $channels,
			'adminEmail'    => get_option( 'admin_email' ),
			'locale'        => str_replace( '_', '-', get_user_locale() ),
			'i18n'          => self::strings(),
		);
	}

	private static function strings() {
		return array(
			'trigger'           => __( 'Trigger', 'flx-forms' ),
			'when'              => __( 'When', 'flx-forms' ),
			'form_submitted'    => __( '\\nA form is submitted\\n', 'flx-forms' ),
			'payment_completed' => __( '\\nA payment is completed\\n', 'flx-forms' ),
			'paymentHint'       => __( '\\nRuns only after the payment gateway has confirmed the payment.\\n', 'flx-forms' ),
			'form'              => __( 'Form', 'flx-forms' ),
			'anyForm'           => __( '\\nAny form\\n', 'flx-forms' ),
			'conditions'        => __( '\\nConditions\\n', 'flx-forms' ),
			'noConditions'      => __( '\\nNo conditions: runs for every submission.\', \'en\']]', 'flx-forms' ),
			'addCondition'      => __( '\\nAdd condition\\n', 'flx-forms' ),
			'matchAll'          => __( '\\nAll conditions must match\\n', 'flx-forms' ),
			'matchAny'          => __( '\\nAny condition may match\\n', 'flx-forms' ),
			'pickFormFirst'     => __( '\\nChoose a form to use its fields.\\n', 'flx-forms' ),
			'field'             => __( 'Field', 'flx-forms' ),
			'value'             => __( 'Value', 'flx-forms' ),
			'op_eq'             => __( '\\nequals\\n', 'flx-forms' ),
			'op_neq'            => __( '\\ndoes not equal\', \'en\']]', 'flx-forms' ),
			'op_contains'       => __( 'contains', 'flx-forms' ),
			'op_gt'             => __( '\\nis greater than\\n', 'flx-forms' ),
			'op_lt'             => __( '\\nis less than\\n', 'flx-forms' ),
			'op_filled'         => __( '\\nis filled\\n', 'flx-forms' ),
			'op_empty'          => __( '\\nis empty\\n', 'flx-forms' ),
			'addStep'           => __( 'Add step', 'flx-forms' ),
			'insertStep'        => __( '\\nInsert a step here\\n', 'flx-forms' ),
			'step'              => __( 'Step', 'flx-forms' ),
			'moveUp'            => __( 'Move up', 'flx-forms' ),
			'moveDown'          => __( 'Move down', 'flx-forms' ),
			'remove'            => __( 'Delete step', 'flx-forms' ),
			'collapse'          => __( '\\nShow / hide settings\\n', 'flx-forms' ),
			'confirmRemove'     => __( '\\nDelete this step?\\n', 'flx-forms' ),
			'noSteps'           => __( '\\nNo steps yet. Add the first one below.\\n', 'flx-forms' ),
			'end'               => __( 'End', 'flx-forms' ),
			'flowName'          => __( '\\nAutomation name\\n', 'flx-forms' ),
			'enabled'           => __( 'Enabled', 'flx-forms' ),
			'save'              => __( 'Save', 'flx-forms' ),
			'saving'            => __( 'Saving…', 'flx-forms' ),
			'saved'             => __( 'Saved.', 'flx-forms' ),
			'saveError'         => __( '\\nCould not save. Please try again.\\n', 'flx-forms' ),
			'unsaved'           => __( 'You have unsaved changes.', 'flx-forms' ),
			'back'              => __( '\\nAll automations\\n', 'flx-forms' ),
			'none'              => __( '— none —', 'flx-forms' ),
			'certOff'           => __( '\\nFlux Certificates is not active: certificate steps are disabled.\\n', 'flx-forms' ),
			'needsCert'         => __( '\\nNeeds a "Create certificate" step before it.\\n', 'flx-forms' ),
			// Action types
			'a_create_certificate'      => __( 'Create certificate', 'flx-forms' ),
			'a_create_certificate_desc' => __( '\\nCreates a certificate from a Flux Certificates template.\\n', 'flx-forms' ),
			'a_send_certificate'        => __( '\\nSend certificate\\n', 'flx-forms' ),
			'a_send_certificate_desc'   => __( '\\nSends the certificate link by e-mail, SMS or WhatsApp.\\n', 'flx-forms' ),
			'a_show_on_thankyou'        => __( '\\nShow on thank-you screen\\n', 'flx-forms' ),
			'a_show_on_thankyou_desc'   => __( '\\nShows a certificate card right after the submission.\\n', 'flx-forms' ),
			'a_send_email'              => __( '\\nSend e-mail\\n', 'flx-forms' ),
			'a_send_email_desc'         => __( '\\nSends a notification e-mail.\\n', 'flx-forms' ),
			'a_webhook'                 => __( '\\nWebhook\\n', 'flx-forms' ),
			'a_webhook_desc'            => __( '\\nPOSTs the submission as JSON to a URL.\\n', 'flx-forms' ),
			// create_certificate
			'template'          => __( 'Certificate template', 'flx-forms' ),
			'chooseTemplate'    => __( 'Choose a template', 'flx-forms' ),
			'person'            => __( '\\nCertificate owner\\n', 'flx-forms' ),
			'nameField'         => __( '\\nName field\\n', 'flx-forms' ),
			'surnameField'      => __( '\\nSurname field (optional)\\n', 'flx-forms' ),
			'phoneField'        => __( '\\nPhone field\\n', 'flx-forms' ),
			'emailField'        => __( '\\nE-mail field\\n', 'flx-forms' ),
			'variables'         => __( '\\nTemplate variables\\n', 'flx-forms' ),
			'nameFromPerson'    => __( '[["Filled from the owner name above.\\n', 'flx-forms' ),
			'm_default'         => __( '\\nTemplate default\\n', 'flx-forms' ),
			'm_field'           => __( '[[\'Form field\\n', 'flx-forms' ),
			'm_fixed'           => __( '\\nFixed value\\n', 'flx-forms' ),
			'm_amount_div'      => __( '\\nPayment amount ÷ unit price\\n', 'flx-forms' ),
			'm_option_qty'      => __( '\\nNumber in the selected option (e.g. “5 saplings” → 5)\\n', 'flx-forms' ),
			'unitPrice'         => __( '\\nUnit price\\n', 'flx-forms' ),
			'amountHint'        => __( '[[\'Verified paid amount divided by the unit price, rounded down, at least 1.\\n', 'flx-forms' ),
			'event'             => __( '[[\'Event (optional)\\n', 'flx-forms' ),
			'noEvent'           => __( 'No event', 'flx-forms' ),
			'defaultIs'         => __( '\\ndefault:\\n', 'flx-forms' ),
			'autoDetected'      => __( '\\nauto-detected\\n', 'flx-forms' ),
			// send_certificate
			'channelsLabel'     => __( 'Channels', 'flx-forms' ),
			'provider'          => __( 'Provider', 'flx-forms' ),
			'defaultProvider'   => __( '\\nDefault provider\\n', 'flx-forms' ),
			'noProvider'        => __( '\\nNo active provider for this channel yet (Flux Certificates › Providers).\\n', 'flx-forms' ),
			// show_on_thankyou
			'cardTitle'         => __( '[[\'Card title\\n', 'flx-forms' ),
			'message'           => __( 'Message', 'flx-forms' ),
			'tags'              => __( 'Tags', 'flx-forms' ),
			'showPreview'       => __( '\\nShow the certificate itself on the screen\\n', 'flx-forms' ),
			'btnShare'          => __( '\\n“Share” button\\n', 'flx-forms' ),
			'Share'             => FLXF_Automations::seed_text( 'Share' ),
			'btnView'           => __( '\\n“View certificate” link\\n', 'flx-forms' ),
			'btnPdf'            => __( '\\n“Download PDF” button\\n', 'flx-forms' ),
			'label'             => __( 'Label', 'flx-forms' ),
			'redirect'          => __( '\\nOpen the certificate page automatically after (seconds, 0 = off)\\n', 'flx-forms' ),
			'View certificate'  => __( '\\nView certificate\\n', 'flx-forms' ),
			'Download PDF'      => __( 'Download PDF', 'flx-forms' ),
			'Your certificate is ready' => __( '\\nYour certificate is ready\\n', 'flx-forms' ),
			// send_email
			'to'                => __( 'To', 'flx-forms' ),
			'toAdmin'           => __( '\\nSite administrator\\n', 'flx-forms' ),
			'toField'           => __( '\\nE-mail from a form field\', \'en\']]', 'flx-forms' ),
			'toCustom'          => __( '[[\'Custom addresses\\n', 'flx-forms' ),
			'subject'           => __( 'Subject', 'flx-forms' ),
			'body'              => __( '\\nBody\\n', 'flx-forms' ),
			// webhook
			'url'               => __( '\\nURL (https only)\\n', 'flx-forms' ),
			'secret'            => __( '\\nSigning secret (optional)\\n', 'flx-forms' ),
			'secretHint'        => __( '\\nAdds an X-FLXF-Signature: sha256=HMAC header.\\n', 'flx-forms' ),
			'httpsOnly'         => __( '\\nOnly https:// URLs to public hosts are allowed.\\n', 'flx-forms' ),
		);
	}

	/* ------------------------------------------------------------------
	 * Screens
	 * ------------------------------------------------------------------ */

	public static function render() {
		if ( ! FLXF_Automations::can() ) {
			wp_die( esc_html__( '\\nSorry, you are not allowed to access this page.\\n', 'flx-forms' ) );
		}
		FLXF_Automations::maybe_seed();
		$tab  = isset( $_GET['tab'] ) && 'runs' === $_GET['tab'] ? 'runs' : 'flows'; // phpcs:ignore WordPress.Security.NonceVerification
		$flow = isset( $_GET['flow'] ) ? sanitize_key( wp_unslash( $_GET['flow'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="wrap flxfa">';
		if ( '' !== $flow ) {
			echo '<div id="flxfa-editor" class="flxfa-editor"><p class="flxfa-loading">' . esc_html__( 'Loading…', 'flx-forms' ) . '</p></div>';
			echo '<noscript>' . esc_html__( '\\nThe flow editor needs JavaScript.\\n', 'flx-forms' ) . '</noscript>';
			echo '</div>';
			return;
		}
		echo '<div class="flxfa-head"><div><h1 class="flxfa-title">' . esc_html__( '\\nAutomations\\n', 'flx-forms' ) . '</h1>';
		echo '<p class="flxfa-sub">' . esc_html__( '\\nRun steps automatically after a form is submitted or a payment is completed: create and send certificates, show them on the thank-you screen, e-mail, webhook.\\n', 'flx-forms' ) . '</p></div>';
		echo '<div class="flxfa-head__actions"><a class="flxfa-btn flxfa-btn--primary" href="' . esc_url( self::url( array( 'flow' => 'new' ) ) ) . '">' . self::icon( 'plus' ) . esc_html__( '\\nNew automation\\n', 'flx-forms' ) . '</a></div></div>';
		echo '<hr class="wp-header-end">';
		if ( ! FLXF_Automations::certificates_available() ) {
			echo '<div class="flxfa-notice flxfa-notice--warn">' . self::icon( 'alert' ) . '<div><strong>' . esc_html__( 'Flux Certificates is not active.', 'flx-forms' ) . '</strong> ' . esc_html__( '\\nCertificate steps are disabled until the Flux Certificates plugin is activated. E-mail and webhook steps keep working.\\n', 'flx-forms' ) . '</div></div>';
		}
		echo '<nav class="flxfa-tabs" aria-label="' . esc_attr__( '\\nAutomations\\n', 'flx-forms' ) . '">';
		echo '<a class="flxfa-tab' . ( 'flows' === $tab ? ' is-active' : '' ) . '" href="' . esc_url( self::url() ) . '"' . ( 'flows' === $tab ? ' aria-current="page"' : '' ) . '>' . esc_html__( '\\nFlows\\n', 'flx-forms' ) . '</a>';
		echo '<a class="flxfa-tab' . ( 'runs' === $tab ? ' is-active' : '' ) . '" href="' . esc_url( self::url( array( 'tab' => 'runs' ) ) ) . '"' . ( 'runs' === $tab ? ' aria-current="page"' : '' ) . '>' . esc_html__( '\\nRun log\\n', 'flx-forms' ) . ' <span class="flxfa-count">' . esc_html( number_format_i18n( count( FLXF_Automations::get_runs() ) ) ) . '</span></a>';
		echo '</nav>';
		if ( 'runs' === $tab ) {
			self::render_runs();
		} else {
			self::render_list();
		}
		echo '</div>';
	}

	private static function icon( $name ) {
		$p = array(
			'plus'   => '<path d="M12 5v14M5 12h14"/>',
			'alert'  => '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
			'edit'   => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4z"/>',
			'copy'   => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
			'trash'  => '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>',
			'bolt'   => '<path d="M13 2 3 14h9l-1 8 10-12h-9z"/>',
			'card'   => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
			'form'   => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
			'tree'   => '<path d="M12 22v-6"/><path d="M12 16c-4 0-7-2.5-7-6a7 7 0 0 1 14 0c0 3.5-3 6-7 6z"/>',
		);
		return '<svg class="flxfa-ico" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $p[ $name ] ?? '' ) . '</svg>';
	}

	private static function step_summary( $flow ) {
		$labels = array(
			'create_certificate' => __( 'Create certificate', 'flx-forms' ),
			'send_certificate'   => __( '\\nSend certificate\\n', 'flx-forms' ),
			'show_on_thankyou'   => __( '\\nShow on thank-you screen\\n', 'flx-forms' ),
			'send_email'         => __( '\\nSend e-mail\\n', 'flx-forms' ),
			'webhook'            => __( '\\nWebhook\\n', 'flx-forms' ),
		);
		$out = array();
		foreach ( $flow['actions'] as $a ) {
			$out[] = $labels[ $a['type'] ] ?? $a['type'];
		}
		return $out;
	}

	private static function render_list() {
		$flows = FLXF_Automations::get_flows();
		$nonce = wp_create_nonce( self::NONCE );
		$donation_form = FLXF_Automations::find_donation_form();
		echo '<div class="flxfa-panel" data-nonce="' . esc_attr( $nonce ) . '" data-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" id="flxfa-list">';
		if ( ! $flows ) {
			echo '<div class="flxfa-empty">' . self::icon( 'bolt' ) . '<p>' . esc_html__( '\\nNo automations yet.\\n', 'flx-forms' ) . '</p></div>';
		} else {
			echo '<div class="flxfa-table-wrap"><table class="flxfa-table"><thead><tr>';
			echo '<th scope="col">' . esc_html__( 'Enabled', 'flx-forms' ) . '</th><th scope="col">' . esc_html__( 'Automation', 'flx-forms' ) . '</th><th scope="col">' . esc_html__( 'Trigger', 'flx-forms' ) . '</th><th scope="col">' . esc_html__( 'Form', 'flx-forms' ) . '</th><th scope="col">' . esc_html__( 'Last run', 'flx-forms' ) . '</th><th scope="col" class="num">' . esc_html__( 'Runs', 'flx-forms' ) . '</th><th scope="col"><span class="screen-reader-text">' . esc_html__( 'Actions', 'flx-forms' ) . '</span></th>';
			echo '</tr></thead><tbody>';
			foreach ( $flows as $f ) {
				$edit = self::url( array( 'flow' => $f['id'] ) );
				echo '<tr data-id="' . esc_attr( $f['id'] ) . '">';
				echo '<td><label class="flxfa-switch"><input type="checkbox" class="flxfa-toggle" ' . checked( $f['enabled'], true, false ) . ' aria-label="' . esc_attr( sprintf( /* translators: %s: automation name */ __( '\\nEnable%s\\n', 'flx-forms' ), $f['title'] ) ) . '"><span></span></label></td>';
				echo '<td><a class="flxfa-name" href="' . esc_url( $edit ) . '">' . esc_html( $f['title'] ) . '</a><div class="flxfa-steps">';
				foreach ( self::step_summary( $f ) as $i => $s ) {
					echo ( $i ? '<span class="flxfa-arrow" aria-hidden="true">→</span>' : '' ) . '<span class="flxfa-chip">' . esc_html( $s ) . '</span>';
				}
				echo '</div></td>';
				echo '<td>' . ( 'payment_completed' === $f['trigger']['type'] ? self::icon( 'card' ) . esc_html__( '\\nPayment completed\\n', 'flx-forms' ) : self::icon( 'form' ) . esc_html__( '\\nForm submitted\\n', 'flx-forms' ) ) . '</td>';
				echo '<td>' . esc_html( FLXF_Automations::form_title( $f['trigger']['form_id'] ) ) . '</td>';
				echo '<td>' . ( $f['last_run'] ? '<time datetime="' . esc_attr( gmdate( 'c', $f['last_run'] ) ) . '">' . esc_html( flx_date( $f['last_run'], 'datetime' ) ) . '</time>' : '<span class="flxfa-muted">—</span>' ) . '</td>';
				echo '<td class="num">' . esc_html( number_format_i18n( $f['runs'] ) ) . '</td>';
				echo '<td class="flxfa-row-actions"><a class="flxfa-icon-btn" href="' . esc_url( $edit ) . '" title="' . esc_attr__( 'Edit', 'flx-forms' ) . '">' . self::icon( 'edit' ) . '<span class="screen-reader-text">' . esc_html__( 'Edit', 'flx-forms' ) . '</span></a>';
				echo '<button type="button" class="flxfa-icon-btn flxfa-dup" title="' . esc_attr__( 'Duplicate', 'flx-forms' ) . '">' . self::icon( 'copy' ) . '<span class="screen-reader-text">' . esc_html__( 'Duplicate', 'flx-forms' ) . '</span></button>';
				echo '<button type="button" class="flxfa-icon-btn flxfa-icon-btn--danger flxfa-del" title="' . esc_attr__( 'Delete', 'flx-forms' ) . '" data-confirm="' . esc_attr__( '\\nDelete this automation? This cannot be undone.\\n', 'flx-forms' ) . '">' . self::icon( 'trash' ) . '<span class="screen-reader-text">' . esc_html__( 'Delete', 'flx-forms' ) . '</span></button></td>';
				echo '</tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '</div>';

		echo '<div class="flxfa-panel flxfa-starter">';
		echo '<div class="flxfa-starter__icon">' . self::icon( 'tree' ) . '</div><div class="flxfa-starter__body"><h2>' . esc_html__( '\\nReady-made: sapling donation certificate\\n', 'flx-forms' ) . '</h2>';
		echo '<p>' . esc_html__( '\\nAfter a verified payment on the donation form: create a “Fidan Bağış Sertifikası” (sapling count = amount ÷ unit price), send it by WhatsApp and e-mail, and show it on the thank-you screen.\\n', 'flx-forms' ) . '</p>';
		echo '<div class="flxfa-starter__actions">';
		echo '<button type="button" class="flxfa-btn" id="flxfa-default">' . self::icon( 'bolt' ) . esc_html__( '[[\'Create default flow\\n', 'flx-forms' ) . '</button>';
		if ( $donation_form ) {
			/* translators: %s: form name */
			echo '<span class="flxfa-muted">' . esc_html( sprintf( __( '\\nDonation form:%s\\n', 'flx-forms' ), FLXF_Automations::form_title( $donation_form ) ) ) . '</span>';
		} else {
			echo '<button type="button" class="flxfa-btn flxfa-btn--ghost" id="flxfa-donation-form">' . self::icon( 'form' ) . esc_html__( '\\nCreate the “Sapling Donation” form\\n', 'flx-forms' ) . '</button>';
		}
		echo '</div></div></div>';
	}

	private static function render_runs() {
		$runs   = FLXF_Automations::get_runs();
		$labels = array(
			'create_certificate' => __( 'Create certificate', 'flx-forms' ),
			'send_certificate'   => __( '\\nSend certificate\\n', 'flx-forms' ),
			'show_on_thankyou'   => __( '\\nShow on thank-you screen\\n', 'flx-forms' ),
			'send_email'         => __( '\\nSend e-mail\\n', 'flx-forms' ),
			'webhook'            => __( '\\nWebhook\\n', 'flx-forms' ),
		);
		$status = array(
			'ok'      => __( 'Done', 'flx-forms' ),
			'partial' => __( '\\nPartly done\\n', 'flx-forms' ),
			'error'   => __( 'Error', 'flx-forms' ),
			'skipped' => __( 'Skipped', 'flx-forms' ),
			'running' => __( 'Running', 'flx-forms' ),
		);
		echo '<div class="flxfa-panel" id="flxfa-runs" data-nonce="' . esc_attr( wp_create_nonce( self::NONCE ) ) . '" data-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '">';
		if ( ! $runs ) {
			echo '<div class="flxfa-empty">' . self::icon( 'bolt' ) . '<p>' . esc_html__( '[[\'No runs yet. Runs appear here after a matching submission.\\n', 'flx-forms' ) . '</p></div></div>';
			return;
		}
		/* translators: %d: maximum number of runs kept */
		echo '<div class="flxfa-runs-head"><p class="flxfa-muted">' . esc_html( sprintf( __( '\\nThe latest%druns are kept.\\n', 'flx-forms' ), FLXF_Automations::MAX_RUNS ) ) . '</p><button type="button" class="flxfa-btn" id="flxfa-clear-runs" data-confirm="' . esc_attr__( '\\nClear the run log?\\n', 'flx-forms' ) . '">' . esc_html__( 'Clear log', 'flx-forms' ) . '</button></div>';
		echo '<div class="flxfa-table-wrap"><table class="flxfa-table flxfa-runs"><thead><tr><th scope="col">' . esc_html__( 'Time', 'flx-forms' ) . '</th><th scope="col">' . esc_html__( 'Automation', 'flx-forms' ) . '</th><th scope="col">' . esc_html__( 'Form', 'flx-forms' ) . '</th><th scope="col">' . esc_html__( 'Tracking code', 'flx-forms' ) . '</th><th scope="col">' . esc_html__( 'Status', 'flx-forms' ) . '</th><th scope="col">' . esc_html__( 'Steps', 'flx-forms' ) . '</th></tr></thead><tbody>';
		foreach ( $runs as $r ) {
			$st = $r['status'] ?? 'running';
			echo '<tr>';
			echo '<td><time datetime="' . esc_attr( gmdate( 'c', (int) $r['time'] ) ) . '">' . esc_html( flx_date( (int) $r['time'], 'datetime' ) ) . '</time></td>';
			echo '<td><a href="' . esc_url( self::url( array( 'flow' => (int) $r['flow_id'] ) ) ) . '">' . esc_html( $r['flow_title'] ?? '' ) . '</a></td>';
			echo '<td>' . esc_html( $r['form_title'] ?? '' ) . '</td>';
			echo '<td><code>' . esc_html( $r['track'] ?? '' ) . '</code></td>';
			echo '<td><span class="flxfa-status flxfa-status--' . esc_attr( $st ) . '">' . esc_html( $status[ $st ] ?? $st ) . '</span></td>';
			echo '<td><ol class="flxfa-steplog">';
			foreach ( (array) ( $r['steps'] ?? array() ) as $s ) {
				$sst = $s['status'] ?? '';
				echo '<li><span class="flxfa-dot flxfa-dot--' . esc_attr( $sst ) . '" aria-hidden="true"></span><strong>' . esc_html( $labels[ $s['type'] ] ?? $s['type'] ) . '</strong> <span class="flxfa-status-text">' . esc_html( $status[ $sst ] ?? $sst ) . '</span>';
				if ( ! empty( $s['message'] ) ) {
					$m = (string) $s['message'];
					if ( 'show_on_thankyou' === $s['type'] && 0 === strpos( $m, 'http' ) ) {
						echo '<div class="flxfa-steplog__msg"><a href="' . esc_url( $m ) . '" target="_blank" rel="noopener">' . esc_html( $m ) . '</a></div>';
					} else {
						echo '<div class="flxfa-steplog__msg">' . esc_html( $m ) . '</div>';
					}
				}
				echo '</li>';
			}
			echo '</ol></td></tr>';
		}
		echo '</tbody></table></div></div>';
	}

	/* ------------------------------------------------------------------
	 * AJAX
	 * ------------------------------------------------------------------ */

	private static function guard() {
		if ( ! FLXF_Automations::can() ) {
			wp_send_json_error( array( 'message' => __( '\\nSorry, you are not allowed to do that.\\n', 'flx-forms' ) ), 403 );
		}
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( '\\nThe link has expired. Reload the page and try again.\\n', 'flx-forms' ) ), 403 );
		}
	}

	public static function ajax_save() {
		self::guard();
		$raw  = isset( $_POST['flow'] ) ? wp_unslash( $_POST['flow'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- JSON, sanitised field by field in sanitize_flow().
		$data = json_decode( (string) $raw, true );
		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( '\\nInvalid data.\\n', 'flx-forms' ) ), 400 );
		}
		$id  = absint( $data['id'] ?? 0 );
		$res = FLXF_Automations::save_flow( $data, $id );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'id' => (int) $res, 'flow' => FLXF_Automations::get_flow( (int) $res ) ) );
	}

	public static function ajax_toggle() {
		self::guard();
		$flow = FLXF_Automations::get_flow( absint( $_POST['id'] ?? 0 ) );
		if ( ! $flow ) {
			wp_send_json_error( array( 'message' => __( '\\nAutomation not found.\\n', 'flx-forms' ) ), 404 );
		}
		$flow['enabled'] = ! empty( $_POST['enabled'] ) && '0' !== $_POST['enabled'];
		$res             = FLXF_Automations::save_flow( $flow, $flow['id'] );
		is_wp_error( $res ) ? wp_send_json_error( array( 'message' => $res->get_error_message() ), 400 ) : wp_send_json_success( array( 'enabled' => $flow['enabled'] ) );
	}

	public static function ajax_delete() {
		self::guard();
		FLXF_Automations::delete_flow( absint( $_POST['id'] ?? 0 ) ) ? wp_send_json_success() : wp_send_json_error( array( 'message' => __( '\\nAutomation not found.\\n', 'flx-forms' ) ), 404 );
	}

	public static function ajax_duplicate() {
		self::guard();
		$flow = FLXF_Automations::get_flow( absint( $_POST['id'] ?? 0 ) );
		if ( ! $flow ) {
			wp_send_json_error( array( 'message' => __( '\\nAutomation not found.\\n', 'flx-forms' ) ), 404 );
		}
		/* translators: %s: automation name */
		$flow['title']   = sprintf( __( '%s (copy)', 'flx-forms' ), $flow['title'] );
		$flow['enabled'] = false;
		$res             = FLXF_Automations::save_flow( $flow, 0 );
		is_wp_error( $res ) ? wp_send_json_error( array( 'message' => $res->get_error_message() ), 400 ) : wp_send_json_success( array( 'id' => (int) $res ) );
	}

	public static function ajax_default() {
		self::guard();
		$res = FLXF_Automations::create_default_flow();
		is_wp_error( $res ) ? wp_send_json_error( array( 'message' => $res->get_error_message() ), 400 ) : wp_send_json_success( array( 'id' => (int) $res, 'url' => self::url( array( 'flow' => (int) $res ) ) ) );
	}

	/** Creates the donation form and points disabled/unattached default flows at it. */
	public static function ajax_donation_form() {
		self::guard();
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '\\nSorry, you are not allowed to do that.\\n', 'flx-forms' ) ), 403 );
		}
		$form_id = FLXF_Automations::find_donation_form();
		if ( ! $form_id ) {
			$form_id = FLXF_Automations::create_donation_form();
		}
		if ( ! $form_id ) {
			wp_send_json_error( array( 'message' => __( '\\nThe form could not be created.\\n', 'flx-forms' ) ), 500 );
		}
		$fresh = FLXF_Automations::default_flow_data( $form_id );
		foreach ( FLXF_Automations::get_flows() as $f ) {
			if ( 'payment_completed' === $f['trigger']['type'] && 0 === (int) $f['trigger']['form_id'] && ! $f['enabled'] && isset( $f['actions'][0]['type'] ) && 'create_certificate' === $f['actions'][0]['type'] ) {
				$f['trigger']['form_id'] = $form_id;
				$f['actions'][0]         = array_merge( $f['actions'][0], array_intersect_key( $fresh['actions'][0], array_flip( array( 'name_field', 'surname_field', 'phone_field', 'email_field' ) ) ) );
				$f['enabled']            = FLXF_Automations::certificates_available();
				FLXF_Automations::save_flow( $f, $f['id'] );
			}
		}
		wp_send_json_success(
			array(
				'form_id'  => $form_id,
				'shortcode' => '[EMS_Form_Builder id=' . $form_id . ']',
			)
		);
	}

	public static function ajax_clear_runs() {
		self::guard();
		FLXF_Automations::clear_runs();
		wp_send_json_success();
	}
}
