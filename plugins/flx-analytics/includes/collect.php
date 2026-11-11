<?php
/**
 * Veri toplama: hem WordPress'siz hızlı uç (endpoint.php) hem REST yedeği bunu kullanır.
 * Bu dosya WordPress fonksiyonlarına dayanmaz.
 *
 * Gizlilik: IP ve tarayıcı bilgisi saklanmaz. Ziyaretçi, günlük rastgele bir tuz + IP + tarayıcıdan
 * üretilen kısa bir özetle (hash) yalnız o gün için tanınır; tuz ertesi gün silinir, özet geri çevrilemez.
 *
 * @package flx-analytics
 */

defined( 'ABSPATH' ) || defined( 'FLXAN_STANDALONE' ) || exit;

if ( ! function_exists( 'flxan_c_run' ) ) {

	/** Bot, tarayıcı ön yüklemesi ya da ucun elle açılması mı? */
	function flxan_c_is_automated( array $server ) {
		$ua = isset( $server['HTTP_USER_AGENT'] ) ? (string) $server['HTTP_USER_AGENT'] : '';
		if ( '' === $ua || strlen( $ua ) < 12 ) {
			return true;
		}
		if ( preg_match( '~bot\b|bot/|crawl|spider|slurp|scrape|fetch(er)?\b|monitor|uptime|pingdom|lighthouse|pagespeed|gtmetrix|headless|phantom|selenium|puppeteer|playwright|preview|facebookexternalhit|whatsapp/|telegram|embedly|curl/|wget|python|java/|go-http|okhttp|axios|node-fetch|guzzle|httpclient|libwww|scanner|validator|ahrefs|semrush|mj12|yandex(bot|images)|bingpreview~i', $ua ) ) {
			return true;
		}
		// Tarayıcı sayfayı önceden yüklüyor; kimse bakmıyor.
		if ( ! empty( $server['HTTP_SEC_PURPOSE'] ) || ( isset( $server['HTTP_PURPOSE'] ) && 'prefetch' === strtolower( $server['HTTP_PURPOSE'] ) ) || ( isset( $server['HTTP_X_MOZ'] ) && 'prefetch' === strtolower( $server['HTTP_X_MOZ'] ) ) ) {
			return true;
		}
		// Uç adres çubuğundan açılmış (sendBeacon "no-cors" gönderir).
		if ( isset( $server['HTTP_SEC_FETCH_MODE'] ) && 'navigate' === $server['HTTP_SEC_FETCH_MODE'] ) {
			return true;
		}
		return false;
	}

	/**
	 * Cloudflare's published ranges (ips-v4 / ips-v6, 2026-11-10).
	 * Filter `flxan_cloudflare_cidrs` when WordPress is loaded.
	 */
	function flxan_c_cloudflare_cidrs() {
		$cidrs = array(
			'173.245.48.0/20',
			'103.21.244.0/22',
			'103.22.200.0/22',
			'103.31.4.0/22',
			'141.101.64.0/18',
			'108.162.192.0/18',
			'190.93.240.0/20',
			'188.114.96.0/20',
			'197.234.240.0/22',
			'198.41.128.0/17',
			'162.158.0.0/15',
			'104.16.0.0/13',
			'104.24.0.0/14',
			'172.64.0.0/13',
			'131.0.72.0/22',
			'2400:cb00::/32',
			'2606:4700::/32',
			'2803:f800::/32',
			'2405:b500::/32',
			'2405:8100::/32',
			'2a06:98c0::/29',
			'2c0f:f248::/32',
		);
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'flxan_cloudflare_cidrs', $cidrs );
			if ( is_array( $filtered ) ) {
				$cidrs = $filtered;
			}
		}
		return $cidrs;
	}

	/** Direct peer is a Cloudflare address, so forwarded headers were set by Cloudflare. */
	function flxan_c_peer_is_cloudflare( $ip ) {
		$ip = trim( (string) $ip );
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false;
		}
		return flxan_c_ip_excluded( $ip, flxan_c_cloudflare_cidrs() );
	}

	/**
	 * Visitor IP, used only to build the daily hash and the exclusion list.
	 * The raw address is never stored. CF-Connecting-IP and other forwarded
	 * headers count only when REMOTE_ADDR is inside Cloudflare's ranges.
	 */
	function flxan_c_ip( array $server ) {
		$remote = isset( $server['REMOTE_ADDR'] ) ? trim( (string) $server['REMOTE_ADDR'] ) : '';
		if ( flxan_c_peer_is_cloudflare( $remote ) ) {
			foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP' ) as $header ) {
				if ( empty( $server[ $header ] ) ) {
					continue;
				}
				$candidate = trim( explode( ',', (string) $server[ $header ] )[0] );
				if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
					return $candidate;
				}
			}
		}
		// Doğrudan eş özel ağdaysa (Docker/Coolify ters vekili, yerel nginx) asıl adres iletilen başlıktadır;
		// yoksa bütün ziyaretçiler tek özete düşer. Dışarıdan gelen istek özel adresli olamaz.
		$private = '' !== $remote && ! filter_var( $remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		if ( $private ) {
			foreach ( array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP' ) as $header ) {
				if ( empty( $server[ $header ] ) ) {
					continue;
				}
				$candidate = trim( explode( ',', (string) $server[ $header ] )[0] );
				if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
					return $candidate;
				}
			}
		}
		return $remote;
	}

	/**
	 * Beacon token: HMAC of the 12-hour window (and the previous one) with the
	 * key written into config.php. Cached pages keep working for 24 hours.
	 */
	function flxan_c_token_ok( $token, array $cfg, $now ) {
		$key    = isset( $cfg['collect_key'] ) ? (string) $cfg['collect_key'] : '';
		$window = isset( $cfg['collect_window'] ) ? (int) $cfg['collect_window'] : 43200;
		$token  = strtolower( (string) $token );
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $token ) || ! preg_match( '/^[a-f0-9]{64}$/', $key ) || $window < 60 ) {
			return false;
		}
		$cur  = intdiv( (int) $now, $window );
		$nowt = substr( hash_hmac( 'sha256', (string) $cur, $key ), 0, 32 );
		$prev = substr( hash_hmac( 'sha256', (string) ( $cur - 1 ), $key ), 0, 32 );
		return hash_equals( $nowt, $token ) || hash_equals( $prev, $token );
	}

	/** IP dışlama listesinde mi? Tam adres, "1.2.3.*" ya da CIDR ("1.2.3.0/24"). */
	function flxan_c_ip_excluded( $ip, array $list ) {
		foreach ( $list as $rule ) {
			$rule = trim( (string) $rule );
			if ( '' === $rule ) {
				continue;
			}
			if ( $rule === $ip ) {
				return true;
			}
			if ( false !== strpos( $rule, '*' ) && preg_match( '/^' . str_replace( '\*', '[0-9a-f:.]*', preg_quote( $rule, '/' ) ) . '$/i', $ip ) ) {
				return true;
			}
			if ( false !== strpos( $rule, '/' ) ) {
				list( $net, $bits ) = explode( '/', $rule, 2 );
				$a = @inet_pton( $ip ); // phpcs:ignore
				$b = @inet_pton( $net ); // phpcs:ignore
				if ( false === $a || false === $b || strlen( $a ) !== strlen( $b ) ) {
					continue;
				}
				$bits  = max( 0, min( strlen( $a ) * 8, (int) $bits ) );
				$bytes = intdiv( $bits, 8 );
				if ( substr( $a, 0, $bytes ) !== substr( $b, 0, $bytes ) ) {
					continue;
				}
				$rest = $bits % 8;
				if ( ! $rest || ( ( ord( $a[ $bytes ] ) ^ ord( $b[ $bytes ] ) ) & ( 0xFF << ( 8 - $rest ) ) & 0xFF ) === 0 ) {
					return true;
				}
			}
		}
		return false;
	}

	/** Kontrol karakterlerini atıp kısaltır. */
	function flxan_c_text( $v, $max ) {
		if ( ! is_scalar( $v ) ) {
			return '';
		}
		$v = trim( (string) preg_replace( '/[\x00-\x1F\x7F]+/', ' ', (string) $v ) );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $v, 0, $max, 'UTF-8' );
		}
		return substr( $v, 0, $max );
	}

	/** Sitenin saat dilimine göre bugünün tarihi. */
	function flxan_c_today( array $cfg, $ts ) {
		try {
			$tz = new DateTimeZone( ! empty( $cfg['tz'] ) ? $cfg['tz'] : 'UTC' );
		} catch ( Exception $e ) {
			$tz = new DateTimeZone( 'UTC' );
		}
		$d = new DateTime( '@' . $ts );
		$d->setTimezone( $tz );
		return $d->format( 'Y-m-d' );
	}

	/** O günün rastgele tuzu; yoksa üretilir. Eski tuzları toplayıcı siler. */
	function flxan_c_salt( $dir, $day ) {
		$file = $dir . '/salt-' . str_replace( '-', '', $day ) . '.php';
		for ( $i = 0; $i < 5; $i++ ) {
			if ( is_file( $file ) ) {
				$s = include $file;
				if ( is_string( $s ) && strlen( $s ) >= 32 ) {
					return $s;
				}
				usleep( 20000 );
				continue;
			}
			$fh = @fopen( $file, 'x' ); // phpcs:ignore
			if ( $fh ) {
				$s = bin2hex( random_bytes( 24 ) );
				fwrite( $fh, "<?php\nreturn '" . $s . "';\n" ); // phpcs:ignore
				fclose( $fh ); // phpcs:ignore
				return $s;
			}
		}
		return '';
	}

	/** Gelen parametrelerden kayıt satırı; geçersizse boş dizi. */
	function flxan_c_record( array $in, array $server, array $cfg, $dir ) {
		$now  = time();
		$day  = flxan_c_today( $cfg, $now );
		$ua   = isset( $server['HTTP_USER_AGENT'] ) ? (string) $server['HTTP_USER_AGENT'] : '';
		$salt = flxan_c_salt( $dir, $day );
		if ( '' === $salt ) {
			return array();
		}
		$hash = substr( hash( 'sha256', $salt . '|' . flxan_c_ip( $server ) . '|' . $ua ), 0, 16 );
		$type = isset( $in['k'] ) ? (string) $in['k'] : 'p';
		$path = isset( $in['p'] ) && is_string( $in['p'] ) ? flxan_c_text( $in['p'], 255 ) : '';
		if ( '' !== $path && ( '/' !== $path[0] || false === filter_var( 'https://x.invalid' . str_replace( ' ', '%20', $path ), FILTER_VALIDATE_URL ) ) ) {
			return array();
		}

		$rec = array(
			'k' => 'p',
			't' => $now,
			'd' => $day,
			'h' => $hash,
			'p' => $path,
		);

		// Segment (giriş yapmış kişinin rolü) ve referans kodu (?r=…); hem sayfa hem olayda.
		$seg = isset( $in['g'] ) ? strtolower( (string) $in['g'] ) : '';
		if ( '' !== $seg && preg_match( '/^[a-z0-9_-]{1,20}$/', $seg ) ) {
			$rec['g'] = $seg;
		}
		$rc = isset( $in['rc'] ) ? strtolower( (string) $in['rc'] ) : '';
		if ( '' !== $rc && preg_match( '/^[a-z0-9_-]{1,32}$/', $rc ) ) {
			$rec['rc'] = $rc;
		}

		if ( 'e' === $type ) {
			$name = isset( $in['n'] ) ? (string) $in['n'] : '';
			if ( ! preg_match( '/^[a-z0-9_-]{1,40}$/i', $name ) ) {
				return array();
			}
			$rec['k'] = 'e';
			$rec['n'] = strtolower( $name );
			$rec['v'] = flxan_c_text( isset( $in['v'] ) ? $in['v'] : '', 190 );
			return $rec;
		}

		if ( '' === $path ) {
			return array();
		}
		$id = isset( $in['i'] ) ? filter_var( $in['i'], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0, 'max_range' => 4294967295 ) ) ) : 0;
		if ( false === $id ) {
			return array();
		}
		$ref = isset( $in['r'] ) && is_string( $in['r'] ) ? flxan_c_text( $in['r'], 255 ) : '';
		if ( '' !== $ref && ! preg_match( '~^(https?|android-app)://[^\s/]+~i', $ref ) ) {
			$ref = '';
		}
		$rec['i'] = (int) $id;
		$rec['r'] = $ref;
		$rec['u'] = substr( $ua, 0, 300 ); // yalnız tarayıcı/sistem adını çıkarmak için; toplanınca atılır
		$rec['w'] = isset( $in['w'] ) ? max( 0, min( 10000, (int) $in['w'] ) ) : 0;
		$lang     = isset( $in['l'] ) ? strtolower( substr( (string) $in['l'], 0, 2 ) ) : '';
		$rec['l'] = preg_match( '/^[a-z]{2}$/', $lang ) ? $lang : '';
		foreach ( array( 'us', 'um', 'uc' ) as $u ) {
			$v = isset( $in[ $u ] ) ? strtolower( flxan_c_text( $in[ $u ], 100 ) ) : '';
			if ( '' !== $v ) {
				$rec[ $u ] = $v;
			}
		}
		$tz = isset( $in['z'] ) ? (string) $in['z'] : '';
		if ( '' !== $tz && preg_match( '~^[A-Za-z][A-Za-z0-9_+/-]{1,39}$~', $tz ) ) {
			$rec['z'] = $tz; // ülke tahmini için (IP'ye bakılmaz)
		}
		if ( ! empty( $in['x'] ) ) {
			$rec['x'] = 1; // 404
		}
		foreach ( array( 'HTTP_CF_IPCOUNTRY', 'HTTP_X_COUNTRY_CODE', 'HTTP_X_GEO_COUNTRY', 'GEOIP_COUNTRY_CODE' ) as $hk ) {
			if ( ! empty( $server[ $hk ] ) && preg_match( '/^[A-Z]{2}$/', strtoupper( (string) $server[ $hk ] ) ) && 'XX' !== strtoupper( (string) $server[ $hk ] ) ) {
				$rec['c'] = strtoupper( (string) $server[ $hk ] );
				break;
			}
		}
		return $rec;
	}

	/** Kuyruk dosyası (rastgele adlı; dizin web'e kapalı). */
	function flxan_c_buffer_file( $dir, array $cfg ) {
		return $dir . '/buffer-' . preg_replace( '/[^a-f0-9]/', '', (string) ( isset( $cfg['token'] ) ? $cfg['token'] : 'x' ) ) . '.ndjson';
	}

	/** Satırı kuyruğa ekler. */
	function flxan_c_write( $dir, array $cfg, array $rec ) {
		$file = flxan_c_buffer_file( $dir, $cfg );
		$max  = max( 1, (int) ( isset( $cfg['max_mb'] ) ? $cfg['max_mb'] : 50 ) ) * 1048576;
		if ( is_file( $file ) && filesize( $file ) > $max ) {
			return false; // toplayıcı uzun süredir çalışmıyor; diski doldurma
		}
		$line = json_encode( $rec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! $line || false !== strpos( $line, "\n" ) ) {
			return false;
		}
		return false !== file_put_contents( $file, $line . "\n", FILE_APPEND | LOCK_EX ); // phpcs:ignore
	}

	/** Toplama isteğini işler; HTTP durum kodunu döndürür (yanıtı göndermez). */
	function flxan_c_handle( array $in, array $server, $dir ) {
		$cfg = is_file( $dir . '/config.php' ) ? include $dir . '/config.php' : null;
		if ( ! is_array( $cfg ) ) {
			return 503;
		}
		if ( ! empty( $in['test'] ) ) {
			return is_writable( $dir ) ? 200 : 500;
		}
		if ( ! flxan_c_token_ok( isset( $in['t'] ) ? $in['t'] : '', $cfg, time() ) ) {
			return 403;
		}
		if ( empty( $cfg['on'] ) || flxan_c_is_automated( $server ) ) {
			return 204;
		}
		if ( ! empty( $cfg['dnt'] ) && ( ( isset( $server['HTTP_DNT'] ) && '1' === $server['HTTP_DNT'] ) || ( isset( $server['HTTP_SEC_GPC'] ) && '1' === $server['HTTP_SEC_GPC'] ) ) ) {
			return 204;
		}
		if ( ! empty( $cfg['ips'] ) && flxan_c_ip_excluded( flxan_c_ip( $server ), (array) $cfg['ips'] ) ) {
			return 204;
		}
		$rec = flxan_c_record( $in, $server, $cfg, $dir );
		if ( ! $rec ) {
			return 400;
		}
		return flxan_c_write( $dir, $cfg, $rec ) ? 204 : 500;
	}

	/** Önbelleğe alınmasın, dizine girmesin. */
	function flxan_c_headers() {
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private' );
		header( 'X-LiteSpeed-Cache-Control: no-cache' );
		header( 'X-Robots-Tag: noindex, nofollow' );
	}

	/** Hızlı uç: WordPress yüklenmeden çalışır. */
	function flxan_c_run( $dir ) {
		$in   = array_merge( $_GET, $_POST ); // phpcs:ignore
		$code = flxan_c_handle( $in, $_SERVER, $dir );
		http_response_code( $code );
		flxan_c_headers();
		if ( 200 === $code ) {
			echo 'ok';
		}
		exit;
	}
}
