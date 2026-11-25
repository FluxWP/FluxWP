<?php
/**
 * Flux Mail GraphQL: settings, templates, log and system check (part 1).
 * Loaded on flx_gql_register, so only inside a GraphQL request.
 *
 * Secrets are write-only. An empty secret input keeps the stored value; "__clear__" removes it.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/**
 * @return Closure
 */
function flxi_gql_cap(): Closure {
	return flx_gql_any_cap( 'manage_options', 'flxi_manage' );
}

/**
 * @param string $type Type.
 * @param string $desc Description.
 * @return array{type:string,description:string}
 */
function flxi_gql_f( string $type, string $desc ): array {
	return array(
		'type'        => $type,
		'description' => $desc,
	);
}

/**
 * @return array<string,mixed>
 */
function flxi_gql_settings(): array {
	$s    = FLXI_Settings_Service::read();
	$last = $s['lastTest'] ?? null;
	if ( is_array( $last ) ) {
		$at = $last['at'] ?? null;
		$last['at']    = ( is_int( $at ) || is_string( $at ) ) && '' !== $at && 0 !== $at ? flx_gql_datetime( $at ) : null;
		$s['lastTest'] = $last;
	}
	$branded = $s['wcBranded'] ?? null;
	$s['wcBranded'] = is_int( $branded ) || is_string( $branded ) ? flx_gql_datetime( $branded ) : null;
	$quota = $s['quota'] ?? array();
	if ( is_array( $quota ) ) {
		foreach ( $quota as $i => $q ) {
			if ( ! is_array( $q ) ) {
				continue;
			}
			$next = $q['next'] ?? null;
			$q['next']   = ( is_int( $next ) || is_string( $next ) ) && '' !== $next && 0 !== $next ? flx_gql_datetime( $next ) : null;
			$quota[ $i ] = $q;
		}
		$s['quota'] = $quota;
	}
	$dns = $s['dns'] ?? null;
	if ( is_array( $dns ) ) {
		$at = $dns['checkedAt'] ?? null;
		$dns['checkedAt'] = ( is_int( $at ) || is_string( $at ) ) && '' !== $at && 0 !== $at ? flx_gql_datetime( $at ) : null;
		$s['dns']         = $dns;
	}
	return $s;
}

/**
 * @param array<string,mixed> $input Preview or test input.
 * @return array<string,mixed>
 */
function flxi_gql_content( array $input ): array {
	$brand_in = isset( $input['brand'] ) && is_array( $input['brand'] ) ? $input['brand'] : array();
	$brand    = array();
	foreach ( array(
		'logo' => 'logo', 'logoWhite' => 'logo_white', 'logoWidth' => 'logo_width',
		'colorPrimary' => 'color_primary', 'colorDark' => 'color_dark', 'colorBg' => 'color_bg', 'colorText' => 'color_text', 'colorAccent' => 'color_accent',
		'orgName' => 'org_name', 'tagline' => 'tagline', 'address' => 'address', 'phone' => 'phone',
		'contactEmail' => 'contact_email', 'website' => 'website', 'footerNote' => 'footer_note', 'footerArt' => 'footer_art', 'fromName' => 'from_name',
	) as $gql => $key ) {
		if ( array_key_exists( $gql, $brand_in ) && null !== $brand_in[ $gql ] ) {
			$brand[ $key ] = $brand_in[ $gql ];
		}
	}
	if ( isset( $brand_in['social'] ) && is_array( $brand_in['social'] ) ) {
		$assoc = array();
		foreach ( $brand_in['social'] as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$net = $item['network'] ?? '';
			if ( ! is_string( $net ) || '' === $net ) {
				continue;
			}
			$url = $item['url'] ?? '';
			$assoc[ sanitize_key( $net ) ] = is_scalar( $url ) ? (string) $url : '';
		}
		$brand['social'] = $assoc;
	}
	$str = static function ( mixed $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	};
	$out = array( 'brand' => $brand );
	foreach ( array(
		'subject'    => 'subject',
		'heading'    => 'heading',
		'body'       => 'body',
		'buttonText' => 'button_text',
		'buttonUrl'  => 'button_url',
		'sender'     => 'sender',
	) as $gql => $field ) {
		if ( array_key_exists( $gql, $input ) && null !== $input[ $gql ] ) {
			$out[ $field ] = $str( $input[ $gql ] );
		}
	}
	if ( ! empty( $input['unsubscribe'] ) ) {
		$out['unsubscribe'] = true;
	}
	return $out;
}

/**
 * Registers the part-1 schema.
 */
function flxi_gql_register(): void {
	flx_gql_enum(
		'FlxMailMailer',
		array(
			'description' => __( 'How the site sends email.', 'flx-mail' ),
			'values'      => array(
				'PHP'  => array( 'value' => 'php', 'description' => __( 'Server PHP mail.', 'flx-mail' ) ),
				'SMTP' => array( 'value' => 'smtp', 'description' => __( 'SMTP account.', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_enum(
		'FlxMailSecure',
		array(
			'description' => __( 'Connection encryption.', 'flx-mail' ),
			'values'      => array(
				'SSL'  => array( 'value' => 'ssl' ),
				'TLS'  => array( 'value' => 'tls' ),
				'NONE' => array( 'value' => 'none' ),
			),
		)
	);
	flx_gql_enum(
		'FlxMailSender',
		array(
			'description' => __( 'Which address an email is sent from.', 'flx-mail' ),
			'values'      => array(
				'INFO'    => array( 'value' => 'info' ),
				'NOREPLY' => array( 'value' => 'noreply' ),
			),
		)
	);
	flx_gql_enum(
		'FlxMailChannel',
		array(
			'description' => __( 'A send channel.', 'flx-mail' ),
			'values'      => array(
				'EMAIL'    => array( 'value' => 'email' ),
				'WHATSAPP' => array( 'value' => 'whatsapp' ),
				'SMS'      => array( 'value' => 'sms' ),
			),
		)
	);
	flx_gql_enum(
		'FlxMailLogStatus',
		array(
			'description' => __( 'Result of one send.', 'flx-mail' ),
			'values'      => array(
				'SENT'    => array( 'value' => 'sent' ),
				'FAILED'  => array( 'value' => 'failed' ),
				'BLOCKED' => array( 'value' => 'blocked' ),
				'SENDING' => array( 'value' => 'sending' ),
			),
		)
	);
	flx_gql_enum(
		'FlxMailTemplateKind',
		array(
			'description' => __( 'Where a template comes from.', 'flx-mail' ),
			'values'      => array(
				'REGISTERED'  => array( 'value' => 'registered' ),
				'WOOCOMMERCE' => array( 'value' => 'woocommerce' ),
				'DISCOVERED'  => array( 'value' => 'discovered' ),
			),
		)
	);

	flx_gql_type(
		'FlxMailSecret',
		array(
			'description' => __( 'A stored secret. The value itself is never returned.', 'flx-mail' ),
			'fields'      => array(
				'set'        => flxi_gql_f( 'Boolean!', __( 'A value is stored or set in wp-config.php.', 'flx-mail' ) ),
				'mask'       => flxi_gql_f( 'String!', __( 'A masked hint, not the value.', 'flx-mail' ) ),
				'fromConfig' => flxi_gql_f( 'Boolean!', __( 'The value comes from a wp-config.php constant.', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_type(
		'FlxMailChoice',
		array(
			'description' => __( 'One option in a settings list.', 'flx-mail' ),
			'fields'      => array(
				'key'   => flxi_gql_f( 'String!', __( 'Stored key.', 'flx-mail' ) ),
				'label' => flxi_gql_f( 'String!', __( 'Label.', 'flx-mail' ) ),
				'image' => flxi_gql_f( 'String', __( 'Preview image URL, when the option has one.', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_type(
		'FlxMailOptoutDefaults',
		array(
			'description' => __( 'Default opt-out texts and words.', 'flx-mail' ),
			'fields'      => array(
				'text'          => flxi_gql_f( 'String!', __( 'Opt-out line.', 'flx-mail' ) ),
				'keywords'      => flxi_gql_f( 'String!', __( 'Opt-out words.', 'flx-mail' ) ),
				'optinKeywords' => flxi_gql_f( 'String!', __( 'Start words.', 'flx-mail' ) ),
				'reply'         => flxi_gql_f( 'String!', __( 'Reply after opting out.', 'flx-mail' ) ),
				'optinReply'    => flxi_gql_f( 'String!', __( 'Reply after starting again.', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_type(
		'FlxMailThemeColors',
		array(
			'description' => __( 'Email colours taken from the site theme (#RRGGBB).', 'flx-mail' ),
			'fields'      => array(
				'primary' => flxi_gql_f( 'String!', __( 'Primary colour.', 'flx-mail' ) ),
				'dark'    => flxi_gql_f( 'String!', __( 'Dark colour (button hover).', 'flx-mail' ) ),
				'bg'      => flxi_gql_f( 'String!', __( 'Background.', 'flx-mail' ) ),
				'text'    => flxi_gql_f( 'String!', __( 'Heading colour.', 'flx-mail' ) ),
				'accent'  => flxi_gql_f( 'String!', __( 'Accent colour (top stripe).', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_type(
		'FlxMailSocialLink',
		array(
			'description' => __( 'A social profile URL.', 'flx-mail' ),
			'fields'      => array(
				'network' => flxi_gql_f( 'String!', __( 'Network key.', 'flx-mail' ) ),
				'url'     => flxi_gql_f( 'String!', __( 'Profile URL.', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_input(
		'FlxMailSocialLinkInput',
		array(
			'description' => __( 'A social profile URL to store.', 'flx-mail' ),
			'fields'      => array(
				'network' => flxi_gql_f( 'String!', __( 'Network key.', 'flx-mail' ) ),
				'url'     => flxi_gql_f( 'String', __( 'Profile URL. Empty removes it.', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_type(
		'FlxMailNote',
		array(
			'description' => __( 'A note about an SMTP account.', 'flx-mail' ),
			'fields'      => array(
				'mailbox' => flxi_gql_f( 'String!', __( 'Account key.', 'flx-mail' ) ),
				'level'   => flxi_gql_f( 'String!', __( 'ok, warning or error.', 'flx-mail' ) ),
				'text'    => flxi_gql_f( 'String!', __( 'Note.', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_type(
		'FlxMailTestResult',
		array(
			'description' => __( 'The last settings test email.', 'flx-mail' ),
			'fields'      => array(
				'ok'    => flxi_gql_f( 'Boolean!', __( 'The send was accepted.', 'flx-mail' ) ),
				'to'    => flxi_gql_f( 'String!', __( 'Recipient.', 'flx-mail' ) ),
				'error' => flxi_gql_f( 'String!', __( 'Error text, empty when it was sent.', 'flx-mail' ) ),
				'at'    => flxi_gql_f( 'FlxDateTime', __( 'When it was sent.', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_type(
		'FlxMailQuotaBox',
		array(
			'description' => __( 'How much of the daily mailbox allowance is used.', 'flx-mail' ),
			'fields'      => array(
				'mailbox' => flxi_gql_f( 'String!', __( 'Mailbox address.', 'flx-mail' ) ),
				'type'    => flxi_gql_f( 'String!', __( 'info or noreply.', 'flx-mail' ) ),
				'used'    => flxi_gql_f( 'Int!', __( 'Sent in the last 24 hours.', 'flx-mail' ) ),
				'limit'   => flxi_gql_f( 'Int!', __( 'Daily limit. 0 means no limit.', 'flx-mail' ) ),
				'left'    => flxi_gql_f( 'Int', __( 'Remaining sends. Null when there is no limit.', 'flx-mail' ) ),
				'percent' => flxi_gql_f( 'Int!', __( 'Used percent.', 'flx-mail' ) ),
				'next'    => flxi_gql_f( 'FlxDateTime', __( 'When the next slot frees up.', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_type(
		'FlxMailDns',
		array(
			'description' => __( 'Stored SPF, DMARC and MX lookup.', 'flx-mail' ),
			'fields'      => array(
				'domain'    => flxi_gql_f( 'String!', __( 'Domain that was checked.', 'flx-mail' ) ),
				'spf'       => flxi_gql_f( 'String!', __( 'SPF record, empty when missing.', 'flx-mail' ) ),
				'dmarc'     => flxi_gql_f( 'String!', __( 'DMARC record, empty when missing.', 'flx-mail' ) ),
				'mx'        => flxi_gql_f( '[String!]!', __( 'MX hosts.', 'flx-mail' ) ),
				'error'     => flxi_gql_f( 'String!', __( 'Lookup error, empty when the lookup ran.', 'flx-mail' ) ),
				'checkedAt' => flxi_gql_f( 'FlxDateTime', __( 'When the lookup ran.', 'flx-mail' ) ),
			),
		)
	);

	$settings = array();
	foreach ( FLXI_Settings_Service::map() as $name => $row ) {
		$type = 'String!';
		if ( 'bool' === $row[1] ) {
			$type = 'Boolean!';
		} elseif ( 'int' === $row[1] ) {
			$type = 'Int!';
		} elseif ( 'secure' === $row[1] ) {
			$type = 'FlxMailSecure!';
		} elseif ( 'mailer' === $row[1] ) {
			$type = 'FlxMailMailer!';
		} elseif ( 'social' === $row[1] ) {
			$type = '[FlxMailSocialLink!]!';
		}
		$settings[ $name ] = flxi_gql_f( $type, $name );
	}
	foreach ( FLXI_Settings_Service::secrets() as $name => $row ) {
		unset( $row );
		$settings[ $name ] = flxi_gql_f( 'FlxMailSecret!', __( 'Write-only secret. Empty keeps the stored value.', 'flx-mail' ) );
	}
	$settings['unsubscribedCount']   = flxi_gql_f( 'Int!', __( 'Addresses that unsubscribed.', 'flx-mail' ) );
	$settings['optoutWhatsappCount'] = flxi_gql_f( 'Int!', __( 'People who opted out of promotional WhatsApp.', 'flx-mail' ) );
	$settings['optoutSmsCount']      = flxi_gql_f( 'Int!', __( 'People who opted out of promotional SMS.', 'flx-mail' ) );
	$settings['wooActive']           = flxi_gql_f( 'Boolean!', __( 'WooCommerce is active.', 'flx-mail' ) );
	$settings['lastTest']            = flxi_gql_f( 'FlxMailTestResult', __( 'Last settings test.', 'flx-mail' ) );
	$settings['wcBranded']           = flxi_gql_f( 'FlxDateTime', __( 'When the WooCommerce brand was last applied.', 'flx-mail' ) );
	$settings['otherSmtpPlugin']     = flxi_gql_f( 'String', __( 'Another SMTP plugin that is sending mail, when one is active.', 'flx-mail' ) );
	$settings['smtpAccountNotes']    = flxi_gql_f( '[FlxMailNote!]!', __( 'Sign-in notes for the SMTP accounts.', 'flx-mail' ) );
	$settings['activeSocialPlugins'] = flxi_gql_f( '[String!]!', __( 'Other social-login plugins that are active.', 'flx-mail' ) );
	$settings['quota']               = flxi_gql_f( '[FlxMailQuotaBox!]!', __( 'Daily mailbox usage.', 'flx-mail' ) );
	$settings['quotaFullDays']       = flxi_gql_f( 'Int!', __( 'Days in the last 30 when the daily limit was hit.', 'flx-mail' ) );
	$settings['optoutDefaults']      = flxi_gql_f( 'FlxMailOptoutDefaults!', __( 'Default opt-out texts and words in the site language.', 'flx-mail' ) );
	$settings['dns']                 = flxi_gql_f( 'FlxMailDns', __( 'Last DNS lookup.', 'flx-mail' ) );
	$settings['footerArts']          = flxi_gql_f( '[FlxMailChoice!]!', __( 'Footer art choices.', 'flx-mail' ) );
	$settings['socialNetworks']      = flxi_gql_f( '[FlxMailChoice!]!', __( 'Social networks the footer can show.', 'flx-mail' ) );
	$settings['themeColors']         = flxi_gql_f( 'FlxMailThemeColors!', __( 'Colours the site theme gives; used when a colour setting is empty.', 'flx-mail' ) );
	flx_gql_type( 'FlxMailSettings', array( 'description' => __( 'Communication settings. Secrets are masked.', 'flx-mail' ), 'fields' => $settings ) );

	$input = array();
	foreach ( FLXI_Settings_Service::map() as $name => $row ) {
		$type = 'String';
		if ( 'bool' === $row[1] ) {
			$type = 'Boolean';
		} elseif ( 'int' === $row[1] ) {
			$type = 'Int';
		} elseif ( 'secure' === $row[1] ) {
			$type = 'FlxMailSecure';
		} elseif ( 'mailer' === $row[1] ) {
			$type = 'FlxMailMailer';
		} elseif ( 'social' === $row[1] ) {
			$type = '[FlxMailSocialLinkInput!]';
		}
		$input[ $name ] = flxi_gql_f( $type, $name );
	}
	foreach ( FLXI_Settings_Service::secrets() as $name => $row ) {
		unset( $row );
		$input[ $name ] = flxi_gql_f( 'String', __( 'Empty keeps the stored value. __clear__ removes it.', 'flx-mail' ) );
	}
	$input['logoUploadId']      = flxi_gql_f( 'String', __( 'Upload id from the shared upload endpoint, context flx-mail/logo.', 'flx-mail' ) );
	$input['logoWhiteUploadId'] = flxi_gql_f( 'String', __( 'Upload id for the light logo, context flx-mail/logo.', 'flx-mail' ) );
	flx_gql_input( 'FlxMailSettingsInput', array( 'description' => __( 'Settings to change. Omitted fields stay as they are.', 'flx-mail' ), 'fields' => $input ) );

	flx_gql_input(
		'FlxMailBrandInput',
		array(
			'description' => __( 'Unsaved appearance fields for a preview.', 'flx-mail' ),
			'fields'      => array(
				'logo' => flxi_gql_f( 'String', 'logo' ), 'logoWhite' => flxi_gql_f( 'String', 'logoWhite' ), 'logoWidth' => flxi_gql_f( 'Int', 'logoWidth' ),
				'colorPrimary' => flxi_gql_f( 'String', 'colorPrimary' ), 'colorDark' => flxi_gql_f( 'String', 'colorDark' ), 'colorBg' => flxi_gql_f( 'String', 'colorBg' ), 'colorText' => flxi_gql_f( 'String', 'colorText' ), 'colorAccent' => flxi_gql_f( 'String', 'colorAccent' ),
				'orgName' => flxi_gql_f( 'String', 'orgName' ), 'tagline' => flxi_gql_f( 'String', 'tagline' ), 'address' => flxi_gql_f( 'String', 'address' ), 'phone' => flxi_gql_f( 'String', 'phone' ),
				'contactEmail' => flxi_gql_f( 'String', 'contactEmail' ), 'website' => flxi_gql_f( 'String', 'website' ), 'footerNote' => flxi_gql_f( 'String', 'footerNote' ), 'footerArt' => flxi_gql_f( 'String', 'footerArt' ), 'fromName' => flxi_gql_f( 'String', 'fromName' ),
				'social' => flxi_gql_f( '[FlxMailSocialLinkInput!]', 'social' ),
			),
		)
	);
	flx_gql_input(
		'FlxMailFreeInput',
		array(
			'description' => __( 'Unsaved email content for a test send.', 'flx-mail' ),
			'fields'      => array(
				'subject'    => flxi_gql_f( 'String', __( 'Subject.', 'flx-mail' ) ),
				'heading'    => flxi_gql_f( 'String', __( 'Heading.', 'flx-mail' ) ),
				'body'       => flxi_gql_f( 'String', __( 'Body HTML.', 'flx-mail' ) ),
				'buttonText' => flxi_gql_f( 'String', __( 'Button text.', 'flx-mail' ) ),
				'buttonUrl'  => flxi_gql_f( 'String', __( 'Button link.', 'flx-mail' ) ),
				'sender'     => flxi_gql_f( 'FlxMailSender', __( 'Sender.', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_type(
		'FlxMailPreview',
		array(
			'description' => __( 'A rendered email preview.', 'flx-mail' ),
			'fields'      => array(
				'subject' => flxi_gql_f( 'String!', __( 'Subject.', 'flx-mail' ) ),
				'html'    => flxi_gql_f( 'String!', __( 'HTML body.', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_type(
		'FlxMailPdf',
		array(
			'description' => __( 'A preview exported as PDF.', 'flx-mail' ),
			'fields'      => array(
				'name' => flxi_gql_f( 'String!', __( 'File name.', 'flx-mail' ) ),
				'data' => flxi_gql_f( 'String!', __( 'Base64 PDF.', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_type(
		'FlxMailVar',
		array(
			'description' => __( 'A template variable.', 'flx-mail' ),
			'fields'      => array(
				'name'        => flxi_gql_f( 'String!', __( 'Name without braces.', 'flx-mail' ) ),
				'description' => flxi_gql_f( 'String!', __( 'What it is replaced with.', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_type(
		'FlxMailTemplate',
		array(
			'description' => __( 'One email template.', 'flx-mail' ),
			'fields'      => array(
				'key'           => flxi_gql_f( 'ID!', __( 'Template key.', 'flx-mail' ) ),
				'label'         => flxi_gql_f( 'String!', __( 'Name.', 'flx-mail' ) ),
				'group'         => flxi_gql_f( 'String', __( 'Group on the list.', 'flx-mail' ) ),
				'description'   => flxi_gql_f( 'String!', __( 'Description.', 'flx-mail' ) ),
				'audience'      => flxi_gql_f( 'String!', __( 'Who receives it.', 'flx-mail' ) ),
				'enabled'       => flxi_gql_f( 'Boolean!', __( 'It is sent.', 'flx-mail' ) ),
				'canDisable'    => flxi_gql_f( 'Boolean!', __( 'It can be turned off.', 'flx-mail' ) ),
				'sender'        => flxi_gql_f( 'FlxMailSender!', __( 'Sender account.', 'flx-mail' ) ),
				'editable'      => flxi_gql_f( 'Boolean!', __( 'The content can be edited here.', 'flx-mail' ) ),
				'kind'          => flxi_gql_f( 'FlxMailTemplateKind!', __( 'Where it comes from.', 'flx-mail' ) ),
				'wooUrl'        => flxi_gql_f( 'String', __( 'WooCommerce editor URL. Content is edited there.', 'flx-mail' ) ),
				'lastSubject'   => flxi_gql_f( 'String', __( 'Last subject seen for a discovered email.', 'flx-mail' ) ),
				'senderInfo'    => flxi_gql_f( 'String', __( 'Info address label.', 'flx-mail' ) ),
				'senderNoreply' => flxi_gql_f( 'String', __( 'No-reply address label.', 'flx-mail' ) ),
				'subject'       => flxi_gql_f( 'String', __( 'Subject.', 'flx-mail' ) ),
				'heading'       => flxi_gql_f( 'String', __( 'Heading.', 'flx-mail' ) ),
				'body'          => flxi_gql_f( 'String', __( 'Body HTML.', 'flx-mail' ) ),
				'buttonText'    => flxi_gql_f( 'String', __( 'Button text.', 'flx-mail' ) ),
				'buttonUrl'     => flxi_gql_f( 'String', __( 'Button link.', 'flx-mail' ) ),
				'wrap'          => flxi_gql_f( 'Boolean', __( 'Wrap a non-editable email in the brand layout.', 'flx-mail' ) ),
				'vars'          => flxi_gql_f( '[FlxMailVar!]', __( 'Variables the editor can insert.', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_connection( 'FlxMailTemplate', __( 'A page of email templates.', 'flx-mail' ) );
	flx_gql_type(
		'FlxMailLogStats',
		array(
			'description' => __( 'Sends in the last 7 days.', 'flx-mail' ),
			'fields'      => array(
				'sent'        => flxi_gql_f( 'Int!', __( 'Sent.', 'flx-mail' ) ),
				'failed'      => flxi_gql_f( 'Int!', __( 'Failed.', 'flx-mail' ) ),
				'blocked'     => flxi_gql_f( 'Int!', __( 'Not sent because the template was off.', 'flx-mail' ) ),
				'senderName'  => flxi_gql_f( 'String!', __( 'Sender name.', 'flx-mail' ) ),
				'senderEmail' => flxi_gql_f( 'String!', __( 'Sender address.', 'flx-mail' ) ),
				'method'      => flxi_gql_f( 'String!', __( 'How mail is sent.', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_type(
		'FlxMailLog',
		array(
			'description' => __( 'One send log row.', 'flx-mail' ),
			'fields'      => array(
				'databaseId' => flxi_gql_f( 'Int!', __( 'Row id.', 'flx-mail' ) ),
				'createdAt'  => array(
					'type'        => 'FlxDateTime',
					'description' => __( 'When it was sent.', 'flx-mail' ),
					'resolve'     => static fn( $row ) => flx_gql_datetime( is_array( $row ) ? ( $row['createdAt'] ?? null ) : null ),
				),
				'channel'    => flxi_gql_f( 'String!', __( 'Channel.', 'flx-mail' ) ),
				'template'   => flxi_gql_f( 'String!', __( 'Template key.', 'flx-mail' ) ),
				'channelLabel'  => flxi_gql_f( 'String!', __( 'Channel name for display.', 'flx-mail' ) ),
				'templateLabel' => flxi_gql_f( 'String!', __( 'Email name and source for display ("Test · Flux Mail").', 'flx-mail' ) ),
				'recipient'  => flxi_gql_f( 'String!', __( 'Recipient.', 'flx-mail' ) ),
				'subject'    => flxi_gql_f( 'String!', __( 'Subject.', 'flx-mail' ) ),
				'status'     => flxi_gql_f( 'String!', __( 'sent, failed, blocked or sending.', 'flx-mail' ) ),
				'error'      => flxi_gql_f( 'String', __( 'Error text.', 'flx-mail' ) ),
				'sender'     => flxi_gql_f( 'String!', __( 'Sender.', 'flx-mail' ) ),
				'campaignId' => flxi_gql_f( 'Int!', __( 'Campaign id, 0 when it was not a campaign.', 'flx-mail' ) ),
				'body'       => flxi_gql_f( 'String', __( 'Body. Null when log bodies are turned off.', 'flx-mail' ) ),
				'person'     => flxi_gql_f( 'FlxPerson', __( 'The contact, when Flux CRM is active and the viewer may see them. Null otherwise.', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_connection( 'FlxMailLog', __( 'A page of send log rows.', 'flx-mail' ) );
	flx_gql_type(
		'FlxMailSystemCheck',
		array(
			'description' => __( 'One system-check row from this package.', 'flx-mail' ),
			'fields'      => array(
				'id'          => flxi_gql_f( 'ID!', __( 'Check id.', 'flx-mail' ) ),
				'group'       => flxi_gql_f( 'String!', __( 'Group.', 'flx-mail' ) ),
				'label'       => flxi_gql_f( 'String!', __( 'Label.', 'flx-mail' ) ),
				'status'      => flxi_gql_f( 'String!', __( 'ok, warning, error or info.', 'flx-mail' ) ),
				'message'     => flxi_gql_f( 'String!', __( 'What was found.', 'flx-mail' ) ),
				'actionUrl'   => flxi_gql_f( 'String!', __( 'Where to fix it.', 'flx-mail' ) ),
				'actionLabel' => flxi_gql_f( 'String!', __( 'Fix link label.', 'flx-mail' ) ),
			),
		)
	);

	$cap = flxi_gql_cap();
	flx_gql_query( 'flxMailSettings', array( 'type' => 'FlxMailSettings!', 'description' => __( 'Communication settings.', 'flx-mail' ), 'cap' => $cap, 'resolve' => static fn() => flxi_gql_settings() ) );
	flx_gql_query(
		'flxMailTemplates',
		array(
			'type'        => 'FlxMailTemplateConnection!',
			'description' => __( 'Email templates.', 'flx-mail' ),
			'cap'         => $cap,
			'args'        => flx_gql_connection_args( array( 'search' => flxi_gql_f( 'String', __( 'Name, key or group contains.', 'flx-mail' ) ) ) ),
			'resolve'     => static function ( $root, array $args ): array {
				unset( $root );
				$args = flx_gql_arr( $args );
				$rows = FLXI_Template_Service::rows( sanitize_text_field( flx_gql_str( $args['search'] ?? '' ) ) );
				return flx_gql_paginate( $args, static fn( int $offset, int $limit ): array => array( 'items' => array_slice( $rows, $offset, $limit ), 'total' => count( $rows ) ) );
			},
		)
	);
	flx_gql_query(
		'flxMailTemplate',
		array(
			'type'        => 'FlxMailTemplate',
			'description' => __( 'One email template, including the editor fields.', 'flx-mail' ),
			'cap'         => $cap,
			'args'        => array( 'key' => flxi_gql_f( 'ID!', __( 'Template key.', 'flx-mail' ) ) ),
			'resolve'     => static function ( $root, array $args ) {
				unset( $root );
				return FLXI_Template_Service::one( flx_gql_str( flx_gql_arr( $args )['key'] ?? '' ) );
			},
		)
	);
	flx_gql_query( 'flxMailLogStats', array( 'type' => 'FlxMailLogStats!', 'description' => __( 'Send counts for the last 7 days.', 'flx-mail' ), 'cap' => $cap, 'resolve' => static fn() => FLXI_Template_Service::stats() ) );
	flx_gql_query(
		'flxMailLogs',
		array(
			'type'        => 'FlxMailLogConnection!',
			'description' => __( 'Send log.', 'flx-mail' ),
			'cap'         => $cap,
			'args'        => flx_gql_connection_args(
				array(
					'status'   => flxi_gql_f( 'String', __( 'sent, failed, blocked or sending.', 'flx-mail' ) ),
					'channel'  => flxi_gql_f( 'String', __( 'email, whatsapp or sms.', 'flx-mail' ) ),
					'template' => flxi_gql_f( 'String', __( 'Template key.', 'flx-mail' ) ),
					'campaign' => flxi_gql_f( 'Int', __( 'Campaign id.', 'flx-mail' ) ),
					'search'   => flxi_gql_f( 'String', __( 'Recipient or subject.', 'flx-mail' ) ),
				)
			),
			'resolve'     => static function ( $root, array $args ): array {
				unset( $root );
				$args   = flx_gql_arr( $args );
				$filter = array(
					'status'   => flx_gql_str( $args['status'] ?? '' ),
					'channel'  => flx_gql_str( $args['channel'] ?? '' ),
					'template' => flx_gql_str( $args['template'] ?? '' ),
					'campaign' => flx_gql_int( $args['campaign'] ?? 0 ),
					'search'   => flx_gql_str( $args['search'] ?? '' ),
				);
				return flx_gql_paginate( $args, static fn( int $offset, int $limit ): array => FLXI_Log_Service::page( $filter, $offset, $limit ) );
			},
		)
	);
	flx_gql_query(
		'flxMailLog',
		array(
			'type'        => 'FlxMailLog',
			'description' => __( 'One send log row, with the body when logging bodies is on.', 'flx-mail' ),
			'cap'         => $cap,
			'args'        => array( 'id' => flxi_gql_f( 'Int!', __( 'Row id.', 'flx-mail' ) ) ),
			'resolve'     => static function ( $root, array $args ) {
				unset( $root );
				return FLXI_Log_Service::one( flx_gql_int( flx_gql_arr( $args )['id'] ?? 0 ) );
			},
		)
	);
	flx_gql_query(
		'flxMailSystemChecks',
		array(
			'type'        => '[FlxMailSystemCheck!]!',
			'description' => __( 'System-check rows from this package. Hidden when Flux Speed owns the system screen.', 'flx-mail' ),
			'cap'         => $cap,
			'resolve'     => static function () {
				if ( flxi_system_moved() ) {
					return array();
				}
				return FLXI_System_Service::checks();
			},
		)
	);

	flx_gql_mutation(
		'flxMailSettingsUpdate',
		array(
			'description' => __( 'Saves communication settings. A secret left empty keeps the stored one; __clear__ removes it.', 'flx-mail' ),
			'cap'         => $cap,
			'input'       => array( 'settings' => flxi_gql_f( 'FlxMailSettingsInput!', __( 'Fields to save.', 'flx-mail' ) ) ),
			'output'      => array( 'settings' => flxi_gql_f( 'FlxMailSettings', __( 'Settings after the change.', 'flx-mail' ) ) ),
			'resolve'     => static function ( array $input ) {
				$raw = FLXI_Settings_Service::from_input( flx_gql_arr( flx_gql_arr( $input )['settings'] ?? array() ) );
				if ( is_wp_error( $raw ) ) {
					return $raw;
				}
				$saved = FLXI_Settings_Service::save( $raw, 'graphql', true );
				if ( is_wp_error( $saved ) ) {
					return $saved;
				}
				return array( 'settings' => flxi_gql_settings() );
			},
		)
	);
	flx_gql_mutation(
		'flxMailTemplateUpdate',
		array(
			'description' => __( 'Saves one template, or turns it on or off.', 'flx-mail' ),
			'cap'         => $cap,
			'input'       => array(
				'key'        => flxi_gql_f( 'ID!', __( 'Template key.', 'flx-mail' ) ),
				'enabled'    => flxi_gql_f( 'Boolean', __( 'Send this email.', 'flx-mail' ) ),
				'sender'     => flxi_gql_f( 'FlxMailSender', __( 'Sender.', 'flx-mail' ) ),
				'subject'    => flxi_gql_f( 'String', __( 'Subject.', 'flx-mail' ) ),
				'heading'    => flxi_gql_f( 'String', __( 'Heading.', 'flx-mail' ) ),
				'body'       => flxi_gql_f( 'String', __( 'Body HTML.', 'flx-mail' ) ),
				'buttonText' => flxi_gql_f( 'String', __( 'Button text.', 'flx-mail' ) ),
				'buttonUrl'  => flxi_gql_f( 'String', __( 'Button link.', 'flx-mail' ) ),
				'wrap'       => flxi_gql_f( 'Boolean', __( 'Wrap in the brand layout.', 'flx-mail' ) ),
				'toggleOnly' => flxi_gql_f( 'Boolean', __( 'Only change enabled.', 'flx-mail' ) ),
			),
			'output'      => array( 'template' => flxi_gql_f( 'FlxMailTemplate', __( 'Template after the change.', 'flx-mail' ) ) ),
			'resolve'     => static function ( array $input ) {
				$input = flx_gql_arr( $input );
				if ( ! empty( $input['toggleOnly'] ) ) {
					$r = FLXI_Template_Service::toggle( flx_gql_str( $input['key'] ?? '' ), ! empty( $input['enabled'] ) );
				} else {
					$r = FLXI_Template_Service::save_input( $input );
				}
				if ( is_wp_error( $r ) ) {
					return $r;
				}
				return array( 'template' => FLXI_Template_Service::one( flx_gql_str( $input['key'] ?? '' ) ) );
			},
		)
	);
	flx_gql_mutation(
		'flxMailTemplateReset',
		array(
			'description' => __( 'Restores a template to its default content.', 'flx-mail' ),
			'cap'         => $cap,
			'input'       => array( 'key' => flxi_gql_f( 'ID!', __( 'Template key.', 'flx-mail' ) ) ),
			'output'      => array( 'template' => flxi_gql_f( 'FlxMailTemplate', __( 'Template after the reset.', 'flx-mail' ) ) ),
			'resolve'     => static function ( array $input ) {
				$input = flx_gql_arr( $input );
				$key   = flx_gql_str( $input['key'] ?? '' );
				$r     = FLXI_Template_Service::reset( $key );
				if ( is_wp_error( $r ) ) {
					return $r;
				}
				return array( 'template' => FLXI_Template_Service::one( $key ) );
			},
		)
	);
	$preview_in = array(
		'key'         => flxi_gql_f( 'String', __( 'Template key, or brand for an appearance preview.', 'flx-mail' ) ),
		'dark'        => flxi_gql_f( 'Boolean', __( 'Apply the dark rules. Used by the PDF export.', 'flx-mail' ) ),
		'subject'     => flxi_gql_f( 'String', __( 'Subject.', 'flx-mail' ) ),
		'heading'     => flxi_gql_f( 'String', __( 'Heading.', 'flx-mail' ) ),
		'body'        => flxi_gql_f( 'String', __( 'Body HTML.', 'flx-mail' ) ),
		'buttonText'  => flxi_gql_f( 'String', __( 'Button text.', 'flx-mail' ) ),
		'buttonUrl'   => flxi_gql_f( 'String', __( 'Button link.', 'flx-mail' ) ),
		'sender'      => flxi_gql_f( 'FlxMailSender', __( 'Sender.', 'flx-mail' ) ),
		'unsubscribe' => flxi_gql_f( 'Boolean', __( 'Show the unsubscribe link.', 'flx-mail' ) ),
		'brand'       => flxi_gql_f( 'FlxMailBrandInput', __( 'Unsaved appearance.', 'flx-mail' ) ),
	);
	flx_gql_mutation(
		'flxMailPreview',
		array(
			'description' => __( 'Renders an email preview from saved or unsaved fields.', 'flx-mail' ),
			'cap'         => $cap,
			'input'       => $preview_in,
			'output'      => array( 'preview' => flxi_gql_f( 'FlxMailPreview', __( 'Rendered email.', 'flx-mail' ) ) ),
			'resolve'     => static function ( array $input ) {
				$input = flx_gql_arr( $input );
				$p     = FLXI_Delivery_Service::preview( array( 'key' => flx_gql_str( $input['key'] ?? '' ), 'fields' => flxi_gql_content( $input ) ) );
				return array( 'preview' => $p );
			},
		)
	);
	flx_gql_mutation(
		'flxMailPreviewPdf',
		array(
			'description' => __( 'Exports the preview as a PDF.', 'flx-mail' ),
			'cap'         => $cap,
			'input'       => $preview_in,
			'output'      => array( 'pdf' => flxi_gql_f( 'FlxMailPdf', __( 'PDF file.', 'flx-mail' ) ) ),
			'resolve'     => static function ( array $input ) {
				$input = flx_gql_arr( $input );
				$p     = FLXI_Delivery_Service::preview_pdf( array( 'key' => flx_gql_str( $input['key'] ?? '' ), 'dark' => ! empty( $input['dark'] ), 'fields' => flxi_gql_content( $input ) ) );
				if ( is_wp_error( $p ) ) {
					return $p;
				}
				return array( 'pdf' => $p );
			},
		)
	);
	flx_gql_mutation(
		'flxMailTestSend',
		array(
			'description' => __( 'Sends a test email to the current user, a template, or unsaved content.', 'flx-mail' ),
			'cap'         => $cap,
			'input'       => array(
				'to'     => flxi_gql_f( 'String', __( 'Recipient. Defaults to the current user.', 'flx-mail' ) ),
				'key'    => flxi_gql_f( 'String', __( 'Template key.', 'flx-mail' ) ),
				'sender' => flxi_gql_f( 'FlxMailSender', __( 'Sender for a settings test.', 'flx-mail' ) ),
				'free'   => flxi_gql_f( 'FlxMailFreeInput', __( 'Unsaved content from the send screen.', 'flx-mail' ) ),
			),
			'output'      => array(
				'ok'    => flxi_gql_f( 'Boolean', __( 'The send was accepted.', 'flx-mail' ) ),
				'to'    => flxi_gql_f( 'String', __( 'Recipient.', 'flx-mail' ) ),
				'error' => flxi_gql_f( 'String', __( 'Error text.', 'flx-mail' ) ),
				'at'    => array(
					'type'        => 'FlxDateTime',
					'description' => __( 'When it was attempted.', 'flx-mail' ),
					'resolve'     => static fn( $row ) => flx_gql_datetime( is_array( $row ) ? ( $row['at'] ?? null ) : null ),
				),
			),
			'resolve'     => static function ( array $input ): array {
				$input = flx_gql_arr( $input );
				$in    = array(
					'to'     => flx_gql_str( $input['to'] ?? '' ),
					'key'    => flx_gql_str( $input['key'] ?? '' ),
					'sender' => flx_gql_str( $input['sender'] ?? '' ),
				);
				$free = flx_gql_arr( $input['free'] ?? array() );
				if ( isset( $input['free'] ) && is_array( $input['free'] ) ) {
					$in['free'] = array(
						'subject'     => flx_gql_str( $free['subject'] ?? '' ),
						'heading'     => flx_gql_str( $free['heading'] ?? '' ),
						'body'        => flx_gql_str( $free['body'] ?? '' ),
						'button_text' => flx_gql_str( $free['buttonText'] ?? '' ),
						'button_url'  => flx_gql_str( $free['buttonUrl'] ?? '' ),
						'sender'      => flx_gql_str( $free['sender'] ?? 'info' ),
					);
				}
				return FLXI_Delivery_Service::test_email( $in );
			},
		)
	);
	flx_gql_mutation(
		'flxMailDnsCheck',
		array(
			'description' => __( 'Looks up SPF, DMARC and MX and stores the result.', 'flx-mail' ),
			'cap'         => $cap,
			'input'       => array(),
			'output'      => array( 'dns' => flxi_gql_f( 'FlxMailDns', __( 'Lookup result.', 'flx-mail' ) ) ),
			'resolve'     => static function (): array {
				FLXI_System_Service::check_dns();
				$dns = FLXI_System_Service::stored_dns();
				if ( is_array( $dns ) ) {
					$dns['checkedAt'] = ! empty( $dns['checkedAt'] ) ? flx_gql_datetime( $dns['checkedAt'] ) : null;
				}
				return array( 'dns' => $dns );
			},
		)
	);
	flx_gql_mutation(
		'flxMailWcBrandApply',
		array(
			'description' => __( 'Copies the logo, colors, sender and footer onto WooCommerce emails.', 'flx-mail' ),
			'cap'         => $cap,
			'input'       => array(),
			'output'      => array( 'wcBranded' => flxi_gql_f( 'FlxDateTime', __( 'When it was applied.', 'flx-mail' ) ) ),
			'resolve'     => static function (): array {
				return array( 'wcBranded' => flx_gql_datetime( FLXI_Settings_Service::apply_wc_brand() ) );
			},
		)
	);
	flx_gql_mutation(
		'flxMailImapTest',
		array(
			'description' => __( 'Tests the saved mailbox login. The password is not returned.', 'flx-mail' ),
			'cap'         => $cap,
			'input'       => array(),
			'output'      => array(
				'ok'      => flxi_gql_f( 'Boolean', __( 'The login worked.', 'flx-mail' ) ),
				'message' => flxi_gql_f( 'String', __( 'What happened.', 'flx-mail' ) ),
			),
			'resolve'     => static function (): array {
				$r = flxi_arr( flxi_rest_mb_test() );
				return array( 'ok' => ! empty( $r['ok'] ), 'message' => flxi_str( $r['message'] ?? '' ) );
			},
		)
	);
}
