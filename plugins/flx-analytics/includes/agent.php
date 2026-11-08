<?php
/**
 * Tarayıcı / işletim sistemi / cihaz tespiti, yönlendiren adresin sadeleştirilmesi ve kanal sınıfı.
 * Ham tarayıcı bilgisi (user agent) saklanmaz; yalnız bu fonksiyonların döndürdüğü adlar tutulur.
 *
 * @package flx-analytics
 */

defined( 'ABSPATH' ) || exit;

/** Tarayıcı adı. */
function flxan_ua_browser( $ua ) {
	$map = array(
		'Instagram'        => '~Instagram~',
		'Facebook'         => '~FBAN|FBAV|FB_IAB~',
		'Edge'             => '~Edg(e|A|iOS)?/~',
		'Opera'            => '~OPR/|Opera|OPT/~',
		'Samsung Internet' => '~SamsungBrowser~',
		'Yandex'           => '~YaBrowser~',
		'UC Browser'       => '~UCBrowser~',
		'Firefox'          => '~Firefox/|FxiOS~',
		'Chrome'           => '~Chrome/|CriOS~',
		'Safari'           => '~Version/[\d.]+.*Safari/~',
		'Internet Explorer'=> '~MSIE |Trident/~',
	);
	foreach ( $map as $name => $re ) {
		if ( preg_match( $re, $ua ) ) {
			return $name;
		}
	}
	return 'other';
}

/** İşletim sistemi adı. */
function flxan_ua_os( $ua ) {
	if ( preg_match( '~iPad~', $ua ) ) {
		return 'iPadOS';
	}
	if ( preg_match( '~iPhone|iPod~', $ua ) ) {
		return 'iOS';
	}
	if ( preg_match( '~Android~', $ua ) ) {
		return 'Android';
	}
	if ( preg_match( '~Windows~', $ua ) ) {
		return 'Windows';
	}
	if ( preg_match( '~CrOS~', $ua ) ) {
		return 'ChromeOS';
	}
	if ( preg_match( '~Macintosh|Mac OS X~', $ua ) ) {
		return 'macOS';
	}
	if ( preg_match( '~Linux|X11~', $ua ) ) {
		return 'Linux';
	}
	return 'other';
}

/** Cihaz türü: mobile | tablet | desktop. Ekran genişliği varsa o esas alınır. */
function flxan_ua_device( $ua, $width ) {
	if ( preg_match( '~iPad|Tablet|PlayBook|Silk/~i', $ua ) || ( preg_match( '~Android~', $ua ) && ! preg_match( '~Mobi~', $ua ) ) ) {
		return 'tablet';
	}
	if ( preg_match( '~Mobi|iPhone|iPod|Windows Phone~i', $ua ) ) {
		return 'mobile';
	}
	$width = (int) $width;
	if ( $width > 0 && $width < 768 ) {
		return 'mobile';
	}
	if ( $width >= 768 && $width < 1100 && ! preg_match( '~Windows|Macintosh|X11~', $ua ) ) {
		return 'tablet';
	}
	return 'desktop';
}

/** Arama motorları, sosyal ağlar, yapay zekâ ve e-posta servisleri: alan adı (regex) → tek ad. */
function flxan_known_sources() {
	return array(
		'gemini.google.com'    => array( '~^gemini\.google\.com$|^bard\.google\.com$~', 'ai' ),
		'mail.google.com'      => array( '~^mail\.google\.com$|^com\.google\.android\.gm$~', 'email' ),
		'google.com'           => array( '~^google\.[a-z.]{2,7}$|^com\.google\.android\.googlequicksearchbox$|^news\.google\.com$~', 'search' ),
		'bing.com'             => array( '~^bing\.com$|^cn\.bing\.com$~', 'search' ),
		'yandex.com'           => array( '~^yandex\.[a-z.]{2,7}$|^ya\.ru$|^com\.yandex\.browser$~', 'search' ),
		'yahoo.com'            => array( '~(^|\.)search\.yahoo\.com$|^yahoo\.com$~', 'search' ),
		'duckduckgo.com'       => array( '~^duckduckgo\.com$~', 'search' ),
		'ecosia.org'           => array( '~^ecosia\.org$~', 'search' ),
		'baidu.com'            => array( '~^baidu\.com$~', 'search' ),
		'brave.com'            => array( '~^search\.brave\.com$~', 'search' ),
		'facebook.com'         => array( '~^facebook\.com$|^fb\.me$|^fb\.com$|^com\.facebook\.(katana|orca)$~', 'social' ),
		'instagram.com'        => array( '~^instagram\.com$|^com\.instagram\.android$~', 'social' ),
		'x.com'                => array( '~^t\.co$|^twitter\.com$|^x\.com$|^com\.twitter\.android$~', 'social' ),
		'linkedin.com'         => array( '~^linkedin\.com$|^lnkd\.in$|^com\.linkedin\.android$~', 'social' ),
		'youtube.com'          => array( '~^youtube\.com$|^youtu\.be$|^com\.google\.android\.youtube$~', 'social' ),
		'tiktok.com'           => array( '~^tiktok\.com$|^com\.zhiliaoapp\.musically$~', 'social' ),
		'pinterest.com'        => array( '~^pinterest\.[a-z.]{2,7}$|^pin\.it$~', 'social' ),
		'reddit.com'           => array( '~^reddit\.com$|^old\.reddit\.com$~', 'social' ),
		'whatsapp.com'         => array( '~^whatsapp\.com$|^web\.whatsapp\.com$|^wa\.me$|^com\.whatsapp$~', 'social' ),
		'telegram.org'         => array( '~^t\.me$|^telegram\.org$|^web\.telegram\.org$|^org\.telegram\.messenger$~', 'social' ),
		'eksisozluk.com'       => array( '~^eksisozluk\.com$|^eksisozluk1923\.com$~', 'social' ),
		'chatgpt.com'          => array( '~^chatgpt\.com$|^chat\.openai\.com$|^com\.openai\.chatgpt$~', 'ai' ),
		'perplexity.ai'        => array( '~^perplexity\.ai$~', 'ai' ),
		'claude.ai'            => array( '~^claude\.ai$~', 'ai' ),
		'copilot.microsoft.com'=> array( '~^copilot\.microsoft\.com$~', 'ai' ),
		'outlook.com'          => array( '~^outlook\.(live|office|office365)\.com$|^mail\.yahoo\.com$|^mail\.yandex\.[a-z.]+$~', 'email' ),
	);
}

/**
 * Yönlendiren adresi sadeleştirir: alan adı (www., m. vb. olmadan), bilinen servisler tek ada.
 * Kendi sitemizden gelen ya da geçersiz adres için ''.
 */
function flxan_normalize_referrer( $url, $own_host = '' ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}
	if ( preg_match( '~^android-app://([a-z0-9._]+)~i', $url, $m ) ) {
		$host = strtolower( $m[1] );
	} else {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	}
	if ( '' === $host || ! preg_match( '~^[a-z0-9.-]+$~', $host ) ) {
		return '';
	}
	$host = preg_replace( '~^(www\d?|m|mobile|amp|l|lm|out|touch)\.~', '', $host );
	$own  = preg_replace( '~^www\.~', '', strtolower( (string) $own_host ) );
	if ( $own && ( $host === $own || substr( $host, -strlen( '.' . $own ) ) === '.' . $own ) ) {
		return '';
	}
	foreach ( flxan_known_sources() as $name => $def ) {
		if ( preg_match( $def[0], $host ) ) {
			return $name;
		}
	}
	return substr( $host, 0, 190 );
}

/** Kanal: direct | search | social | ai | email | paid | campaign | referral (ref = ?r= bağlantısı, toplayıcıda verilir). */
function flxan_channel( $ref_host, $utm_source = '', $utm_medium = '' ) {
	$medium = strtolower( (string) $utm_medium );
	if ( preg_match( '~^(cpc|ppc|paid|paidsocial|paid_social|display|banner|ads?)$~', $medium ) ) {
		return 'paid';
	}
	if ( preg_match( '~e-?mail|newsletter|bulten~', $medium ) ) {
		return 'email';
	}
	if ( '' !== $ref_host ) {
		$known = flxan_known_sources();
		if ( isset( $known[ $ref_host ] ) ) {
			return $known[ $ref_host ][1];
		}
	}
	if ( '' !== (string) $utm_source ) {
		$src = strtolower( (string) $utm_source );
		if ( preg_match( '~^(social|social-?media|sm|sosyal)$~', $medium ) || preg_match( '~instagram|^ig$|facebook|^fb$|twitter|^x$|tiktok|youtube|linkedin|whatsapp|telegram|pinterest~', $src ) ) {
			return 'social';
		}
		if ( preg_match( '~chatgpt|openai|perplexity|claude|gemini|copilot~', $src ) ) {
			return 'ai';
		}
		if ( 'organic' === $medium || preg_match( '~^(google|bing|yandex|duckduckgo)$~', $src ) ) {
			return 'search';
		}
		return 'campaign';
	}
	return '' === $ref_host ? 'direct' : 'referral';
}

/** Ekranda görünen adlar: kanal, cihaz. */
function flxan_dim_label( $kind, $val ) {
	static $labels = null;
	if ( null === $labels ) {
		$labels = array(
			'channel' => array(
				'direct'   => __( 'Direct', 'flx-analytics' ),
				'search'   => __( 'Search engine', 'flx-analytics' ),
				'social'   => __( 'Social media', 'flx-analytics' ),
				'ai'       => __( 'AI', 'flx-analytics' ),
				'email'    => __( 'Email', 'flx-analytics' ),
				'paid'     => __( 'Ads', 'flx-analytics' ),
				'campaign' => __( 'Campaign', 'flx-analytics' ),
				'referral' => __( 'Other sites', 'flx-analytics' ),
				'ref'      => __( 'Referral link', 'flx-analytics' ),
			),
			'device'  => array(
				'mobile'  => __( 'Mobile', 'flx-analytics' ),
				'tablet'  => __( 'Tablet', 'flx-analytics' ),
				'desktop' => __( 'Desktop', 'flx-analytics' ),
			),
		);
	}
	if ( 'other' === $val || '' === $val ) {
		return __( 'Other', 'flx-analytics' );
	}
	if ( isset( $labels[ $kind ][ $val ] ) ) {
		return $labels[ $kind ][ $val ];
	}
	if ( 'country' === $kind && class_exists( 'Locale' ) ) {
		$n = Locale::getDisplayRegion( '-' . $val, determine_locale() );
		return $n && $n !== $val ? $n : $val;
	}
	if ( 'lang' === $kind && class_exists( 'Locale' ) ) {
		$n = Locale::getDisplayLanguage( $val, determine_locale() );
		return $n && $n !== $val ? $n : $val;
	}
	return $val;
}

/**
 * Tarayıcının saat diliminden ülke kodu (IP'ye bakılmaz). "Europe/Istanbul" → "TR".
 * Eski adlar (Europe/Kiev, Asia/Calcutta…) için de çalışır.
 */
function flxan_tz_country( $tz ) {
	static $map = null;
	if ( '' === (string) $tz ) {
		return '';
	}
	if ( null === $map ) {
		$map = get_transient( 'flxan_tzmap' );
		if ( ! is_array( $map ) ) {
			$map = array();
			foreach ( DateTimeZone::listIdentifiers( DateTimeZone::ALL_WITH_BC ) as $id ) {
				try {
					$loc = ( new DateTimeZone( $id ) )->getLocation();
				} catch ( Exception $e ) {
					continue;
				}
				if ( $loc && ! empty( $loc['country_code'] ) && preg_match( '/^[A-Z]{2}$/', $loc['country_code'] ) ) {
					$map[ $id ] = $loc['country_code'];
				}
			}
			$map += array(
				'Europe/Kiev'    => 'UA',
				'Asia/Calcutta'  => 'IN',
				'Asia/Saigon'    => 'VN',
				'Asia/Katmandu'  => 'NP',
				'Asia/Rangoon'   => 'MM',
				'America/Buenos_Aires' => 'AR',
				'Asia/Istanbul'  => 'TR',
				'Turkey'         => 'TR',
			);
			set_transient( 'flxan_tzmap', $map, WEEK_IN_SECONDS );
		}
	}
	return isset( $map[ $tz ] ) ? $map[ $tz ] : '';
}
