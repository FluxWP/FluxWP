<?php
/**
 * Kurumsal e-posta düzeni: logolu üst kısım, içerik kartı, düğme, görsel, kurumsal alt bilgi.
 * Tablo tabanlı ve satır içi stilli (Gmail, Outlook, Apple Mail), 600 px, telefonda tam genişlik, koyu mod desteği.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/**
 * E-postayı HTML olarak üretir.
 *
 * @param array $a {
 *   subject, preheader, heading, body (HTML), button_text, button_url, button2_text, button2_url,
 *   image (url), image_link, image_alt, image_caption, facts (array label=>value), note (küçük not, HTML),
 *   sender ('info'|'noreply'), unsubscribe_url, lang,
 *   partner (name, logo, url: "Örnek Vakfı × Firma" üst kısmı), accent (renk), quote (alıntılanan eski yazışma, HTML),
 *   signature (HTML), style ('card' | 'letter': posta kutusundan sade mektup görünümü)
 * }
 */
function flxi_render_email( array $a ) {
	$a = wp_parse_args(
		$a,
		array(
			'subject'         => '',
			'preheader'       => '',
			'heading'         => '',
			'body'            => '',
			'button_text'     => '',
			'button_url'      => '',
			'button2_text'    => '',
			'button2_url'     => '',
			'image'           => '',
			'image_link'      => '',
			'image_alt'       => '',
			'image_caption'   => '',
			'image_width'     => 520,
			'facts'           => array(),
			'note'            => '',
			'sender'          => 'info',
			'unsubscribe_url' => '',
			'lang'            => '',
			'partner'         => array(),
			'accent'          => '',
			'quote'           => '',
			'signature'       => '',
			'style'           => 'card',
		)
	);
	$a    = apply_filters( 'flxi_render_email_args', $a );
	if ( 'letter' === $a['style'] ) {
		return apply_filters( 'flxi_email_html', flxi_render_letter( $a ), $a );
	}
	$b    = flxi_brand();
	$lang = $a['lang'] ? $a['lang'] : str_replace( '_', '-', determine_locale() );
	$p    = $b['primary'];
	$dark = $b['dark'];
	$txt  = $b['text'];
	$font = "'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif";
	$head = "'Nunito', 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif";

	$pre = $a['preheader'] ? $a['preheader'] : wp_strip_all_tags( $a['heading'] );
	$pre = mb_substr( trim( preg_replace( '/\s+/', ' ', $pre ) ), 0, 140 );

	$o  = '<!DOCTYPE html><html lang="' . esc_attr( $lang ) . '" xmlns="http://www.w3.org/1999/xhtml"><head>';
	$o .= '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta http-equiv="X-UA-Compatible" content="IE=edge">';
	$o .= '<meta name="color-scheme" content="light dark"><meta name="supported-color-schemes" content="light dark">';
	$o .= '<title>' . esc_html( $a['subject'] ) . '</title>';
	$o .= '<style>'
		. 'body{margin:0!important;padding:0!important;width:100%!important;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}'
		. 'table,td{mso-table-lspace:0;mso-table-rspace:0;border-collapse:collapse}img{border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;max-width:100%;height:auto}'
		. 'a{color:' . $p . '}.flxi-body p{margin:0 0 16px}.flxi-body ul,.flxi-body ol{margin:0 0 16px;padding-left:22px}.flxi-body li{margin:0 0 6px}'
		. '.flxi-body h2{font-family:' . $head . ';font-size:19px;line-height:1.3;margin:24px 0 10px;color:' . $txt . '}.flxi-body h3{font-size:16px;margin:20px 0 8px;color:' . $txt . '}'
		. '.flxi-body blockquote{margin:0 0 16px;padding:14px 18px;background:#F4F8F5;border-left:4px solid ' . $p . ';border-radius:0 10px 10px 0;color:#33454F}'
		. '.flxi-body a{color:' . $p . ';font-weight:600}.flxi-body img{border-radius:10px}'
		. '.flxi-body table.flxi-kv{width:100%;margin:0 0 16px}.flxi-body table.flxi-kv td{padding:8px 0;border-bottom:1px solid #E3EEE6;font-size:14px}'
		. '.flxi-btn a:hover{background:' . $dark . '!important}'
		. '@media only screen and (max-width:620px){'
		. '.flxi-wrap{padding:0!important}.flxi-card{border-radius:0!important;border-left:0!important;border-right:0!important}'
		. '.flxi-top{padding-top:20px!important}.flxi-pad{padding-left:22px!important;padding-right:22px!important}.flxi-h1{font-size:23px!important}'
		. '.flxi-btn,.flxi-btn a{display:block!important;width:100%!important;box-sizing:border-box;text-align:center!important}'
		. '.flxi-col{display:block!important;width:100%!important;text-align:left!important;padding:0 0 10px!important}'
		. '}'
		. '@media (prefers-color-scheme:dark){'
		. '.flxi-bg{background:#0F1A16!important}.flxi-card{background:#16231E!important;border-color:#24382F!important}'
		. '.flxi-h1,.flxi-body,.flxi-body h2,.flxi-body h3,.flxi-fact-v{color:#E8F1EC!important}.flxi-muted,.flxi-fact-l{color:#A9BDB3!important}'
		. '.flxi-logo-l,.flxi-art-l{display:none!important}.flxi-logo-d,.flxi-art-d{display:block!important;max-height:none!important;overflow:visible!important}'
		. '.flxi-body blockquote,.flxi-facts{background:#1D2D27!important;color:#D6E4DC!important}.flxi-body a,.flxi-link{color:#7FD3A8!important}'
		. '}'
		. '</style>';
	$o .= '<!--[if mso]><style>body,table,td,a,p{font-family:Arial,Helvetica,sans-serif!important}</style><![endif]-->';
	$o .= '</head>';
	$o .= '<body class="flxi-bg" style="margin:0;padding:0;background:' . $b['bg'] . ';">';

	// Önizleme metni (gelen kutusunda konunun yanında görünen satır).
	$o .= '<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all">' . esc_html( $pre ) . str_repeat( '&#8204;&nbsp;', 40 ) . '</div>';

	$o .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" class="flxi-bg" style="background:' . $b['bg'] . ';"><tr><td align="center" class="flxi-wrap" style="padding:28px 12px ' . ( $b['art'] ? '0' : '36px' ) . ';">';
	$o .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;border-collapse:separate;">';

	// Logo (işbirliği şablonunda: bizim logomuz × firmanın logosu)
	$logo = '<a href="' . esc_url( home_url( '/' ) ) . '" target="_blank" style="text-decoration:none;display:block">'
		. '<img class="flxi-logo-l" src="' . esc_url( $b['logo'] ) . '" width="' . (int) $b['logo_width'] . '" alt="' . esc_attr( $b['org'] ) . '" style="display:block;width:' . (int) $b['logo_width'] . 'px;max-width:100%;height:auto;">'
		. '<!--[if !mso]><!--><img class="flxi-logo-d" src="' . esc_url( $b['logo_white'] ) . '" width="' . (int) $b['logo_width'] . '" alt="' . esc_attr( $b['org'] ) . '" style="display:none;width:' . (int) $b['logo_width'] . 'px;max-width:100%;height:auto;max-height:0;overflow:hidden;"><!--<![endif]-->'
		. '</a>';
	if ( ! empty( $a['partner']['logo'] ) || ! empty( $a['partner']['name'] ) ) {
		$logo = flxi_partner_header( $logo, $a['partner'], $b, $font );
	}
	$o .= '<tr><td class="flxi-top flxi-pad" align="left" style="padding:0 6px 18px;">' . $logo . '</td></tr>';

	// Kart
	$o .= '<tr><td class="flxi-card" style="background:#ffffff;border:1px solid #DDE9E1;border-radius:20px;overflow:hidden;border-collapse:separate;">';
	$o .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:separate;">';
	$o .= '<tr><td style="height:6px;line-height:6px;font-size:0;background:' . $p . ';background-image:linear-gradient(90deg,' . $p . ',' . ( $a['accent'] ? esc_attr( $a['accent'] ) : '#6DBE45' ) . ');border-radius:19px 19px 0 0;">&nbsp;</td></tr>';

	if ( $a['heading'] ) {
		$o .= '<tr><td class="flxi-pad" style="padding:34px 40px 4px;">'
			. '<h1 class="flxi-h1" style="margin:0;font-family:' . $head . ';font-size:26px;line-height:1.25;font-weight:800;color:' . $txt . ';">' . wp_kses_post( $a['heading'] ) . '</h1>'
			. '</td></tr>';
	}

	$o .= '<tr><td class="flxi-pad flxi-body" style="padding:' . ( $a['heading'] ? '18px' : '34px' ) . ' 40px 8px;font-family:' . $font . ';font-size:16px;line-height:1.65;color:#33454F;">' . $a['body'] . '</td></tr>';

	if ( $a['image'] ) {
		$img = '<img src="' . esc_url( $a['image'] ) . '" width="' . (int) $a['image_width'] . '" alt="' . esc_attr( $a['image_alt'] ) . '" style="display:block;width:100%;max-width:' . (int) $a['image_width'] . 'px;height:auto;border-radius:14px;border:1px solid #E3EEE6;">';
		if ( $a['image_link'] ) {
			$img = '<a href="' . esc_url( $a['image_link'] ) . '" target="_blank" style="text-decoration:none;display:block">' . $img . '</a>';
		}
		$o .= '<tr><td class="flxi-pad" align="center" style="padding:8px 40px 6px;">' . $img;
		if ( $a['image_caption'] ) {
			$o .= '<div class="flxi-muted" style="font-family:' . $font . ';font-size:13px;color:#6B7C86;padding-top:10px;">' . wp_kses_post( $a['image_caption'] ) . '</div>';
		}
		$o .= '</td></tr>';
	}

	if ( $a['facts'] ) {
		$rows = '';
		foreach ( $a['facts'] as $label => $value ) {
			if ( '' === (string) $value ) {
				continue;
			}
			$rows .= '<td class="flxi-col" valign="top" style="padding:0 12px;">'
				. '<div class="flxi-fact-l" style="font-family:' . $font . ';font-size:12px;letter-spacing:.04em;text-transform:uppercase;color:#6B7C86;">' . esc_html( $label ) . '</div>'
				. '<div class="flxi-fact-v" style="font-family:' . $head . ';font-size:17px;font-weight:800;color:' . $txt . ';padding-top:2px;">' . esc_html( $value ) . '</div></td>';
		}
		if ( $rows ) {
			$o .= '<tr><td class="flxi-pad" style="padding:14px 40px 4px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="flxi-facts" style="background:#F4F8F5;border-radius:14px;"><tr><td style="padding:16px 6px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr>' . $rows . '</tr></table></td></tr></table></td></tr>';
		}
	}

	if ( $a['button_text'] && $a['button_url'] ) {
		$o .= '<tr><td class="flxi-pad" style="padding:20px 40px 6px;">' . flxi_email_button( $a['button_text'], $a['button_url'], $p );
		if ( $a['button2_text'] && $a['button2_url'] ) {
			$o .= '<div style="padding-top:12px;font-family:' . $font . ';font-size:14px;"><a class="flxi-link" href="' . esc_url( $a['button2_url'] ) . '" target="_blank" style="color:' . $p . ';font-weight:700;text-decoration:none;">' . esc_html( $a['button2_text'] ) . ' &rarr;</a></div>';
		}
		$o .= '</td></tr>';
	}

	if ( $a['signature'] ) {
		$o .= '<tr><td class="flxi-pad flxi-body" style="padding:14px 40px 0;font-family:' . $font . ';font-size:15px;line-height:1.6;color:#33454F;">' . wp_kses_post( $a['signature'] ) . '</td></tr>';
	}
	if ( $a['quote'] ) {
		$o .= '<tr><td class="flxi-pad" style="padding:18px 40px 0;">' . flxi_quote_block( $a['quote'], $font ) . '</td></tr>';
	}

	if ( $a['note'] ) {
		$o .= '<tr><td class="flxi-pad flxi-muted" style="padding:18px 40px 0;font-family:' . $font . ';font-size:13px;line-height:1.55;color:#6B7C86;">' . wp_kses_post( $a['note'] ) . '</td></tr>';
	}
	$o .= '<tr><td style="height:34px;line-height:34px;font-size:0;">&nbsp;</td></tr>';
	$o .= '</table></td></tr>';

	$o .= flxi_email_footer( $a, $b, $font );

	$o .= '</table></td></tr></table></body></html>';
	return apply_filters( 'flxi_email_html', $o, $a );
}

/** Her istemcide çalışan yuvarlak düğme. */
function flxi_email_button( $text, $url, $color ) {
	return '<table role="presentation" cellspacing="0" cellpadding="0" border="0" class="flxi-btn"><tr><td class="flxi-btn" align="center" bgcolor="' . esc_attr( $color ) . '" style="border-radius:999px;background:' . esc_attr( $color ) . ';">'
		. '<!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" href="' . esc_url( $url ) . '" style="height:50px;v-text-anchor:middle;width:260px;" arcsize="50%" fillcolor="' . esc_attr( $color ) . '" stroke="f"><center style="color:#ffffff;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;">' . esc_html( $text ) . '</center></v:roundrect><![endif]-->'
		. '<!--[if !mso]><!--><a href="' . esc_url( $url ) . '" target="_blank" style="display:inline-block;padding:15px 34px;font-family:\'Segoe UI\',Helvetica,Arial,sans-serif;font-size:16px;font-weight:700;line-height:20px;color:#ffffff;text-decoration:none;border-radius:999px;background:' . esc_attr( $color ) . ';">' . esc_html( $text ) . '</a><!--<![endif]-->'
		. '</td></tr></table>';
}

function flxi_email_footer( $a, $b, $font ) {
	$c = 'color:#5E707A;';
	$o = '<tr><td class="flxi-pad" style="padding:28px 26px 0;font-family:' . $font . ';font-size:13px;line-height:1.6;' . $c . '" align="center">';

	if ( $b['tagline'] ) {
		$o .= '<div style="font-family:\'Segoe UI\',Helvetica,Arial,sans-serif;font-size:14px;font-weight:600;letter-spacing:.2px;color:' . $b['primary'] . ';padding-bottom:14px;" class="flxi-link">' . esc_html( $b['tagline'] ) . '</div>';
	}

	if ( $b['social'] ) {
		$icons = '';
		foreach ( $b['social'] as $k => $url ) {
			$file = FLXI_DIR . 'assets/img/social/' . $k . '.png';
			if ( ! file_exists( $file ) ) {
				continue;
			}
			$icons .= '<td style="padding:0 5px;"><a href="' . esc_url( $url ) . '" target="_blank" style="text-decoration:none;"><img src="' . esc_url( FLXI_URL . 'assets/img/social/' . $k . '.png' ) . '" width="32" height="32" alt="' . esc_attr( flxi_social_services()[ $k ] ?? $k ) . '" style="display:block;width:32px;height:32px;border-radius:50%;"></a></td>';
		}
		if ( $icons ) {
			$o .= '<table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin:0 auto 14px;"><tr>' . $icons . '</tr></table>';
		}
	}

	$o .= '<div style="font-weight:700;color:' . $b['text'] . ';font-size:14px;" class="flxi-fact-v">' . esc_html( $b['org'] ) . '</div>';
	if ( $b['address'] ) {
		$o .= '<div>' . esc_html( $b['address'] ) . '</div>';
	}
	$line = array();
	if ( $b['phone'] ) {
		$tel    = preg_replace( '/[^0-9+]/', '', $b['phone'] );
		$line[] = '<a class="flxi-link" href="tel:' . esc_attr( $tel ) . '" style="' . $c . 'text-decoration:none;">' . esc_html( $b['phone'] ) . '</a>';
	}
	if ( $b['email'] ) {
		$line[] = '<a class="flxi-link" href="mailto:' . esc_attr( $b['email'] ) . '" style="' . $c . 'text-decoration:none;">' . esc_html( $b['email'] ) . '</a>';
	}
	if ( $b['host'] ) {
		$line[] = '<a class="flxi-link" href="' . esc_url( $b['website'] ) . '" target="_blank" style="color:' . $b['primary'] . ';font-weight:700;text-decoration:none;">' . esc_html( $b['host'] ) . '</a>';
	}
	if ( $line ) {
		$o .= '<div style="padding-top:4px;">' . implode( ' &nbsp;·&nbsp; ', $line ) . '</div>';
	}

	$small = array();
	if ( 'noreply' === $a['sender'] ) {
		$small[] = sprintf(
			/* translators: %s: iletişim e-posta adresi */
			esc_html__( 'Bu e-posta otomatik gönderildi, lütfen yanıtlamayın. Sorularınız için %s adresine yazabilirsiniz.', 'flx-mail' ),
			'<a class="flxi-link" href="mailto:' . esc_attr( $b['email'] ) . '" style="color:#5E707A;">' . esc_html( $b['email'] ) . '</a>'
		);
	}
	if ( $b['note'] ) {
		$small[] = esc_html( flxi_fill( $b['note'], array() ) );
	}
	if ( $a['unsubscribe_url'] ) {
		$small[] = '<a class="flxi-link" href="' . esc_url( $a['unsubscribe_url'] ) . '" style="color:#5E707A;">' . esc_html__( 'Bu tür e-postaları almak istemiyorum', 'flx-mail' ) . '</a>';
	}
	$small[] = '© ' . esc_html( wp_date( 'Y' ) ) . ' ' . esc_html( $b['org'] );
	$o      .= '<div class="flxi-muted" style="padding-top:16px;font-size:12px;line-height:1.6;color:#7C8C95;">' . implode( '<br>', $small ) . '</div>';
	$o      .= '</td></tr>';

	// Ağaç silüeti: e-postanın en altına yaslanır; koyu modda koyu çizim.
	if ( $b['art'] ) {
		$o .= '<tr><td style="padding:22px 0 0;font-size:0;line-height:0;" align="center">'
			. '<img class="flxi-art-l" src="' . esc_url( $b['art']['light'] ) . '" width="600" alt="" style="display:block;width:100%;max-width:600px;height:auto;border:0;">'
			. '<!--[if !mso]><!--><img class="flxi-art-d" src="' . esc_url( $b['art']['dark'] ) . '" width="600" alt="" style="display:none;width:100%;max-width:600px;height:auto;border:0;max-height:0;overflow:hidden;"><!--<![endif]-->'
			. '</td></tr>';
	}
	return $o;
}

/** Düz metni e-posta gövdesine çevirir: paragraflar, bağlantılar. */
function flxi_text_to_html( $text ) {
	$text = trim( str_replace( array( "\r\n", "\r" ), "\n", (string) $text ) );
	// WordPress'in <https://...> biçimli bağlantıları
	$text = preg_replace( '~<(https?://[^>\s]+)>~', '$1', $text );
	$html = esc_html( $text );
	$html = preg_replace_callback(
		'~(https?://[^\s<]+)~',
		function ( $m ) {
			$url   = html_entity_decode( $m[1] );
			$trail = '';
			if ( preg_match( '/[.,;:!?)]+$/', $url, $t ) ) {
				$trail = $t[0];
				$url   = substr( $url, 0, -strlen( $trail ) );
			}
			$show = strlen( $url ) > 70 ? substr( $url, 0, 60 ) . '…' : $url;
			return '<a href="' . esc_url( $url ) . '" style="word-break:break-all">' . esc_html( $show ) . '</a>' . esc_html( $trail );
		},
		$html
	);
	return wpautop( $html );
}

/** HTML'den okunabilir düz metin (çok parçalı e-postanın metin kısmı). */
function flxi_html_to_text( $html ) {
	$html = preg_replace( '~<(style|script|head)[^>]*>.*?</\1>~is', '', (string) $html );
	$html = preg_replace( '~<div style="display:none[^>]*>.*?</div>~is', '', $html );
	$html = preg_replace_callback(
		'~<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>~is',
		function ( $m ) {
			$label = trim( wp_strip_all_tags( $m[2] ) );
			$url   = html_entity_decode( $m[1] );
			if ( '' === $label || false !== strpos( $label, $url ) || 0 === strpos( $url, 'mailto:' ) || 0 === strpos( $url, 'tel:' ) ) {
				return '' === $label ? $url : $label;
			}
			return $label . ' ( ' . $url . ' )';
		},
		$html
	);
	$html = preg_replace( '~<br\s*/?>~i', "\n", $html );
	$html = preg_replace( '~</(p|div|h1|h2|h3|tr|li|table|blockquote)>~i', "\n\n", $html );
	$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
	$text = preg_replace( "/[ \t\x{200C}\x{00A0}]+/u", ' ', $text );
	$text = preg_replace( "/\n\s*\n\s*\n+/", "\n\n", $text );
	return trim( implode( "\n", array_map( 'trim', explode( "\n", $text ) ) ) );
}

/** "Örnek Vakfı × Firma" üst kısmı. */
function flxi_partner_header( $logo, array $partner, array $b, $font ) {
	$w    = min( 150, (int) $b['logo_width'] );
	$logo = str_replace( array( 'width="' . (int) $b['logo_width'] . '"', 'width:' . (int) $b['logo_width'] . 'px' ), array( 'width="' . $w . '"', 'width:' . $w . 'px' ), $logo );
	$name = (string) ( $partner['name'] ?? '' );
	$pl   = (string) ( $partner['logo'] ?? '' );
	$them = $pl
		? '<img src="' . esc_url( $pl ) . '" alt="' . esc_attr( $name ) . '" height="44" style="display:block;height:44px;width:auto;max-width:170px;border:0;">'
		: '<span style="font-family:' . $font . ';font-size:18px;font-weight:800;color:' . $b['text'] . ';">' . esc_html( $name ) . '</span>';
	if ( ! empty( $partner['url'] ) ) {
		$them = '<a href="' . esc_url( $partner['url'] ) . '" target="_blank" style="text-decoration:none;display:block">' . $them . '</a>';
	}
	// Firma logosu koyu zeminde kaybolmasın diye beyaz kutu içinde.
	return '<table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr>'
		. '<td valign="middle">' . $logo . '</td>'
		. '<td valign="middle" class="flxi-muted" style="padding:0 16px;font-family:' . $font . ';font-size:22px;font-weight:300;color:#8DA39A;">&times;</td>'
		. '<td valign="middle" style="background:#ffffff;border-radius:12px;padding:' . ( $pl ? '6px 10px' : '0' ) . ';">' . $them . '</td>'
		. '</tr></table>';
}

/** Yanıtta alıntılanan eski yazışma. */
function flxi_quote_block( $html, $font ) {
	return '<div class="flxi-muted" style="border-left:3px solid #D5E3DA;padding:2px 0 2px 14px;font-family:' . $font . ';font-size:13px;line-height:1.55;color:#6B7C86;">' . wp_kses_post( $html ) . '</div>';
}

/**
 * Sade mektup görünümü (posta kutusundan yazılan kişisel yazışmalar):
 * beyaz zemin, düz metin, altta küçük logolu imza; kart ve büyük alt bilgi yok.
 */
function flxi_render_letter( array $a ) {
	$b    = flxi_brand();
	$lang = $a['lang'] ? $a['lang'] : str_replace( '_', '-', determine_locale() );
	$font = "'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif";
	$o    = '<!DOCTYPE html><html lang="' . esc_attr( $lang ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
	$o   .= '<meta name="color-scheme" content="light dark"><meta name="supported-color-schemes" content="light dark"><title>' . esc_html( $a['subject'] ) . '</title>';
	$o   .= '<style>body{margin:0;padding:0}p{margin:0 0 14px}a{color:' . $b['primary'] . '}img{max-width:100%;height:auto}'
		. '@media (prefers-color-scheme:dark){body,.flxi-l{background:#0F1A16!important;color:#E8F1EC!important}.flxi-muted{color:#A9BDB3!important}a{color:#7FD3A8!important}.flxi-logo-l{display:none!important}.flxi-logo-d{display:block!important;max-height:none!important}}'
		. '</style></head>';
	$o   .= '<body class="flxi-l" style="margin:0;padding:0;background:#ffffff;">';
	$o   .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" class="flxi-l" style="background:#ffffff;"><tr><td style="padding:20px 16px;">';
	$o   .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;"><tr><td class="flxi-l" style="font-family:' . $font . ';font-size:15px;line-height:1.6;color:#1F2D35;">';
	if ( ! empty( $a['partner']['logo'] ) || ! empty( $a['partner']['name'] ) ) {
		$logo = '<img class="flxi-logo-l" src="' . esc_url( $b['logo'] ) . '" width="' . (int) $b['logo_width'] . '" alt="' . esc_attr( $b['org'] ) . '" style="display:block;width:' . (int) $b['logo_width'] . 'px;height:auto;">';
		$o   .= '<div style="padding-bottom:18px;">' . flxi_partner_header( $logo, $a['partner'], $b, $font ) . '</div>';
	}
	if ( $a['heading'] ) {
		$o .= '<p style="font-size:18px;font-weight:700;margin:0 0 14px;">' . wp_kses_post( $a['heading'] ) . '</p>';
	}
	$o .= $a['body'];
	if ( $a['button_text'] && $a['button_url'] ) {
		$o .= '<p><a href="' . esc_url( $a['button_url'] ) . '" target="_blank" style="color:' . $b['primary'] . ';font-weight:700;">' . esc_html( $a['button_text'] ) . ' &rarr;</a></p>';
	}
	// İmza
	$sig = $a['signature'] ? wp_kses_post( $a['signature'] ) : '';
	$o  .= '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top:22px;border-top:1px solid #E3EEE6;"><tr><td style="padding-top:14px;font-family:' . $font . ';font-size:13px;line-height:1.55;color:#5E707A;" class="flxi-muted">';
	if ( $sig ) {
		$o .= '<div style="padding-bottom:8px;color:#1F2D35;font-size:14px;" class="flxi-l">' . $sig . '</div>';
	}
	$w   = min( 130, (int) $b['logo_width'] );
	$o  .= '<a href="' . esc_url( $b['website'] ) . '" target="_blank" style="display:block;text-decoration:none;padding-bottom:6px;">'
		. '<img class="flxi-logo-l" src="' . esc_url( $b['logo'] ) . '" width="' . $w . '" alt="' . esc_attr( $b['org'] ) . '" style="display:block;width:' . $w . 'px;height:auto;">'
		. '<!--[if !mso]><!--><img class="flxi-logo-d" src="' . esc_url( $b['logo_white'] ) . '" width="' . $w . '" alt="" style="display:none;width:' . $w . 'px;height:auto;max-height:0;overflow:hidden;"><!--<![endif]-->'
		. '</a>';
	$o .= '<strong style="color:#1F2D35;" class="flxi-l">' . esc_html( $b['org'] ) . '</strong><br>';
	$line = array();
	if ( $b['phone'] ) {
		$line[] = esc_html( $b['phone'] );
	}
	if ( $b['email'] ) {
		$line[] = '<a href="mailto:' . esc_attr( $b['email'] ) . '" style="color:#5E707A;text-decoration:none;">' . esc_html( $b['email'] ) . '</a>';
	}
	if ( $b['host'] ) {
		$line[] = '<a href="' . esc_url( $b['website'] ) . '" target="_blank" style="color:' . $b['primary'] . ';text-decoration:none;font-weight:700;">' . esc_html( $b['host'] ) . '</a>';
	}
	$o .= implode( ' &nbsp;·&nbsp; ', $line );
	$o .= '</td></tr></table>';
	if ( $a['quote'] ) {
		$o .= '<div style="padding-top:22px;">' . flxi_quote_block( $a['quote'], $font ) . '</div>';
	}
	$o .= '</td></tr></table></td></tr></table></body></html>';
	return $o;
}

