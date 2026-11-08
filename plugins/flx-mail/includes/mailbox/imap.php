<?php
/**
 * Küçük IMAP istemcisi (PHP imap eklentisi gerekmez; PHP 8.4'te o eklenti çekirdekten çıktı).
 *
 * Yalnız posta kutusu eşitlemesinin ihtiyacı: giriş, klasör listesi, seçme, UID arama,
 * UID FETCH (BODYSTRUCTURE, başlık, bölüm), bayrak, APPEND.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

class FLXI_Imap {

	/** @var resource|null */
	private $fp;
	private $tag = 0;
	private $host;
	private $port;
	private $secure;
	private $timeout;

	/** Son hata (şifre içermez). */
	public $error = '';

	public function __construct( $host, $port = 993, $secure = 'ssl', $timeout = 20 ) {
		$this->host    = trim( (string) $host );
		$this->port    = (int) $port ? (int) $port : 993;
		$this->secure  = in_array( $secure, array( 'ssl', 'tls' ), true ) ? $secure : '';
		$this->timeout = max( 5, (int) $timeout );
	}

	public function __destruct() {
		if ( $this->fp ) {
			@fclose( $this->fp ); // phpcs:ignore
		}
	}

	public function connect() {
		$verify = (bool) apply_filters( 'flxi_imap_verify_peer', true, $this->host );
		$ctx    = stream_context_create(
			array(
				'ssl' => array(
					'verify_peer'      => $verify,
					'verify_peer_name' => $verify,
					'peer_name'        => $this->host,
					'SNI_enabled'      => true,
				),
			)
		);
		$proto    = 'ssl' === $this->secure ? 'ssl://' : 'tcp://';
		$errno    = 0;
		$errstr   = '';
		$this->fp = @stream_socket_client( $proto . $this->host . ':' . $this->port, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $ctx ); // phpcs:ignore
		if ( ! $this->fp ) {
			/* translators: 1: sunucu, 2: hata */
			$this->error = sprintf( __( 'Could not connect to %1$s: %2$s', 'flx-mail' ), $this->host . ':' . $this->port, $errstr ? $errstr : $errno );
			return false;
		}
		stream_set_timeout( $this->fp, $this->timeout );
		$greet = $this->read_line();
		if ( 0 !== strpos( $greet, '* OK' ) && 0 !== strpos( $greet, '* PREAUTH' ) ) {
			$this->error = __( 'The server sent no IMAP greeting.', 'flx-mail' );
			return false;
		}
		if ( 'tls' === $this->secure ) {
			$r = $this->cmd( 'STARTTLS' );
			if ( ! $r['ok'] || ! @stream_socket_enable_crypto( $this->fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT ) ) { // phpcs:ignore
				$this->error = __( 'STARTTLS failed.', 'flx-mail' );
				return false;
			}
		}
		return true;
	}

	public function login( $user, $pass ) {
		$r = $this->cmd( 'LOGIN ' . $this->quote( $user ) . ' ' . $this->quote( $pass ) );
		if ( ! $r['ok'] ) {
			$this->error = __( 'IMAP login failed: wrong user name or password.', 'flx-mail' ) . ( $r['text'] ? ' (' . $this->clean( $r['text'] ) . ')' : '' );
			return false;
		}
		return true;
	}

	public function logout() {
		if ( $this->fp ) {
			$this->cmd( 'LOGOUT' );
			@fclose( $this->fp ); // phpcs:ignore
			$this->fp = null;
		}
	}

	/** Klasörler: [ [ name (sunucudaki ad, UTF-7), label (okunur ad), attrs[], delim ] ]. */
	public function folders() {
		$r   = $this->cmd( 'LIST "" "*"' );
		$out = array();
		foreach ( $r['untagged'] as $u ) {
			if ( ! preg_match( '/^\* LIST /i', $u ) ) {
				continue;
			}
			$p = self::parse( substr( $u, 7 ) );
			if ( count( $p ) >= 3 ) {
				$out[] = array(
					'name'  => (string) $p[2],
					'label' => self::utf7_decode( (string) $p[2] ),
					'attrs' => array_map( 'strtolower', (array) $p[0] ),
					'delim' => (string) $p[1],
				);
			}
		}
		return $out;
	}

	/** Klasörü seçer (sunucudaki adıyla): exists, uidvalidity, uidnext ya da false. */
	public function select( $folder, $readonly = false ) {
		$r = $this->cmd( ( $readonly ? 'EXAMINE ' : 'SELECT ' ) . $this->quote( $folder ) );
		if ( ! $r['ok'] ) {
			/* translators: %s: klasör */
			$this->error = sprintf( __( 'Could not open folder %s.', 'flx-mail' ), $folder );
			return false;
		}
		$info = array( 'exists' => 0, 'uidvalidity' => 0, 'uidnext' => 0 );
		foreach ( $r['untagged'] as $u ) {
			if ( preg_match( '/^\* (\d+) EXISTS/i', $u, $m ) ) {
				$info['exists'] = (int) $m[1];
			} elseif ( preg_match( '/\[UIDVALIDITY (\d+)\]/i', $u, $m ) ) {
				$info['uidvalidity'] = (int) $m[1];
			} elseif ( preg_match( '/\[UIDNEXT (\d+)\]/i', $u, $m ) ) {
				$info['uidnext'] = (int) $m[1];
			}
		}
		return $info;
	}

	/** UID SEARCH: artan UID listesi. */
	public function uid_search( $criteria ) {
		$r    = $this->cmd( 'UID SEARCH ' . $criteria );
		$uids = array();
		foreach ( $r['untagged'] as $u ) {
			if ( preg_match( '/^\* SEARCH\s*(.*)$/i', trim( $u ), $m ) ) {
				foreach ( preg_split( '/\s+/', trim( $m[1] ) ) as $x ) {
					if ( ctype_digit( $x ) ) {
						$uids[] = (int) $x;
					}
				}
			}
		}
		sort( $uids );
		return $uids;
	}

	/**
	 * UID FETCH: uid => [ 'UID'=>, 'FLAGS'=>[], 'BODYSTRUCTURE'=>[], 'BODY[HEADER]'=>'…', … ].
	 * Bölüm anahtarları "<0>" gibi kısmi ekleri olmadan döner.
	 */
	public function uid_fetch( $uids, $items ) {
		$set = is_array( $uids ) ? implode( ',', array_map( 'intval', $uids ) ) : (string) $uids;
		if ( '' === $set ) {
			return array();
		}
		$r   = $this->cmd( 'UID FETCH ' . $set . ' (' . $items . ')' );
		$out = array();
		foreach ( $r['untagged'] as $u ) {
			if ( ! preg_match( '/^\* \d+ FETCH /i', $u, $m ) ) {
				continue;
			}
			$p = self::parse( substr( $u, strlen( $m[0] ) ) );
			if ( ! isset( $p[0] ) || ! is_array( $p[0] ) ) {
				continue;
			}
			$list = $p[0];
			$item = array();
			for ( $i = 0; $i + 1 < count( $list ); $i += 2 ) {
				$k          = strtoupper( preg_replace( '/<\d+>$/', '', (string) $list[ $i ] ) );
				$k          = str_replace( 'BODY.PEEK[', 'BODY[', $k );
				$item[ $k ] = $list[ $i + 1 ];
			}
			if ( isset( $item['UID'] ) ) {
				$uid         = (int) $item['UID'];
				$out[ $uid ] = isset( $out[ $uid ] ) ? array_merge( $out[ $uid ], $item ) : $item;
			}
		}
		return $out;
	}

	public function uid_store( $uids, $op, $flags ) {
		$set = implode( ',', array_map( 'intval', (array) $uids ) );
		return '' !== $set && $this->cmd( 'UID STORE ' . $set . ' ' . $op . ' (' . $flags . ')' )['ok'];
	}

	/** Klasöre ham ileti ekler (ör. siteden gönderilen e-posta "Gönderilenler"e). */
	public function append( $folder, $raw, $flags = '\\Seen' ) {
		$raw = preg_replace( "/\r?\n/", "\r\n", (string) $raw );
		$tag = $this->next_tag();
		$this->write( $tag . ' APPEND ' . $this->quote( $folder ) . ' (' . $flags . ') {' . strlen( $raw ) . "}\r\n" );
		$line = $this->read_line();
		if ( 0 !== strpos( $line, '+' ) ) {
			$this->error = $this->clean( $line );
			return false;
		}
		$this->write( $raw . "\r\n" );
		$r = $this->read_until( $tag );
		if ( ! $r['ok'] ) {
			$this->error = $this->clean( $r['text'] );
		}
		return $r['ok'];
	}

	/* ------------------------------------------------------------------ */

	/** Komut gönderir; ok, text (etiketli satır), untagged (literaller içinde) döner. */
	public function cmd( $command ) {
		if ( ! $this->fp ) {
			return array( 'ok' => false, 'text' => 'not connected', 'untagged' => array() );
		}
		$tag = $this->next_tag();
		$this->write( $tag . ' ' . $command . "\r\n" );
		return $this->read_until( $tag );
	}

	private function next_tag() {
		return 'Y' . str_pad( (string) ( ++$this->tag ), 4, '0', STR_PAD_LEFT );
	}

	private function write( $s ) {
		$len = strlen( $s );
		for ( $done = 0; $done < $len; ) {
			$n = @fwrite( $this->fp, substr( $s, $done, 65536 ) ); // phpcs:ignore
			if ( ! $n ) {
				$this->error = __( 'IMAP connection lost.', 'flx-mail' );
				return false;
			}
			$done += $n;
		}
		return true;
	}

	private function read_line() {
		$line = @fgets( $this->fp, 65536 ); // phpcs:ignore
		if ( false === $line ) {
			return '';
		}
		// Çok uzun satırlar parça parça gelir.
		while ( "\n" !== substr( $line, -1 ) ) {
			$more = @fgets( $this->fp, 65536 ); // phpcs:ignore
			if ( false === $more ) {
				break;
			}
			$line .= $more;
		}
		return $line;
	}

	private function read_bytes( $n ) {
		$buf = '';
		while ( strlen( $buf ) < $n ) {
			$chunk = @fread( $this->fp, min( 65536, $n - strlen( $buf ) ) ); // phpcs:ignore
			if ( false === $chunk || '' === $chunk ) {
				$meta = stream_get_meta_data( $this->fp );
				if ( $meta['timed_out'] || $meta['eof'] ) {
					break;
				}
				continue;
			}
			$buf .= $chunk;
		}
		return $buf;
	}

	/** Etiketli cevaba kadar okur. Literaller ({n}) yanıt metnine aynen eklenir. */
	private function read_until( $tag ) {
		$untagged = array();
		$cur      = null;
		while ( true ) {
			$line = $this->read_line();
			if ( '' === $line ) {
				$this->error = $this->error ? $this->error : __( 'IMAP server did not respond (timeout).', 'flx-mail' );
				if ( null !== $cur ) {
					$untagged[] = $cur;
				}
				return array( 'ok' => false, 'text' => $this->error, 'untagged' => $untagged );
			}
			$resp = null === $cur ? $line : $cur . $line;
			// Satır literal ile bitiyorsa veriyi oku, aynı yanıta devam et.
			if ( preg_match( '/\{(\d+)\+?\}\r\n$/', $line, $m ) ) {
				$cur = $resp . $this->read_bytes( (int) $m[1] );
				continue;
			}
			$cur = null;
			if ( 0 === strpos( $resp, $tag . ' ' ) ) {
				$rest = substr( $resp, strlen( $tag ) + 1 );
				return array(
					'ok'       => 0 === stripos( $rest, 'OK' ),
					'text'     => trim( preg_replace( '/^(OK|NO|BAD)\s*/i', '', $rest ) ),
					'untagged' => $untagged,
				);
			}
			$untagged[] = rtrim( $resp, "\r\n" );
		}
	}

	private function quote( $s ) {
		$s = (string) $s;
		if ( preg_match( '/[\r\n\x00]/', $s ) || preg_match( '/[^\x20-\x7e]/', $s ) ) {
			// 8 bit / satır sonu içerenler literal olarak.
			return '{' . strlen( $s ) . "+}\r\n" . $s;
		}
		return '"' . addcslashes( $s, '"\\' ) . '"';
	}

	private function clean( $s ) {
		return mb_substr( trim( preg_replace( '/\s+/', ' ', (string) $s ) ), 0, 200 );
	}

	/* ------------------------------------------------------------------ */
	/* Yanıt ayrıştırıcı                                                   */
	/* ------------------------------------------------------------------ */

	/** IMAP s-ifadesini PHP dizisine çevirir (NIL → null). */
	public static function parse( $s ) {
		$i   = 0;
		$out = array();
		$len = strlen( $s );
		while ( $i < $len ) {
			$t = self::token( $s, $i, $len );
			if ( '__END__' === $t ) {
				break;
			}
			if ( '__CLOSE__' === $t ) {
				continue;
			}
			$out[] = $t;
		}
		return $out;
	}

	private static function token( $s, &$i, $len ) {
		while ( $i < $len && ( ' ' === $s[ $i ] || "\r" === $s[ $i ] || "\n" === $s[ $i ] ) ) {
			++$i;
		}
		if ( $i >= $len ) {
			return '__END__';
		}
		$c = $s[ $i ];
		if ( '(' === $c ) {
			++$i;
			$list = array();
			while ( $i < $len ) {
				$t = self::token( $s, $i, $len );
				if ( '__CLOSE__' === $t || '__END__' === $t ) {
					break;
				}
				$list[] = $t;
			}
			return $list;
		}
		if ( ')' === $c ) {
			++$i;
			return '__CLOSE__';
		}
		if ( '"' === $c ) {
			++$i;
			$v = '';
			while ( $i < $len && '"' !== $s[ $i ] ) {
				if ( '\\' === $s[ $i ] && $i + 1 < $len ) {
					++$i;
				}
				$v .= $s[ $i ];
				++$i;
			}
			++$i;
			return $v;
		}
		if ( '{' === $c && preg_match( '/\G\{(\d+)\+?\}\r\n/', $s, $m, 0, $i ) ) {
			$i += strlen( $m[0] );
			$v  = substr( $s, $i, (int) $m[1] );
			$i += (int) $m[1];
			return $v;
		}
		// Atom: köşeli parantez içindeki boşluk ve parantezler atoma dahil (BODY[HEADER.FIELDS (FROM)]).
		$v     = '';
		$depth = 0;
		while ( $i < $len ) {
			$c = $s[ $i ];
			if ( '[' === $c ) {
				++$depth;
			} elseif ( ']' === $c ) {
				--$depth;
			} elseif ( 0 >= $depth && ( ' ' === $c || '(' === $c || ')' === $c || "\r" === $c || "\n" === $c ) ) {
				break;
			}
			$v .= $c;
			++$i;
		}
		return 'NIL' === strtoupper( $v ) ? null : $v;
	}

	/* ------------------------------------------------------------------ */
	/* Değiştirilmiş UTF-7 (klasör adları: "Gönderilmiş Öğeler")             */
	/* ------------------------------------------------------------------ */

	public static function utf7_decode( $s ) {
		return preg_replace_callback(
			'/&([A-Za-z0-9+,]*)-/',
			function ( $m ) {
				if ( '' === $m[1] ) {
					return '&';
				}
				$b = base64_decode( str_replace( ',', '/', $m[1] ) );
				$u = function_exists( 'mb_convert_encoding' ) ? mb_convert_encoding( $b, 'UTF-8', 'UTF-16BE' ) : $b;
				return $u;
			},
			(string) $s
		);
	}

	public static function utf7_encode( $s ) {
		$s = (string) $s;
		if ( ! preg_match( '/[^\x20-\x7e]|&/', $s ) ) {
			return $s;
		}
		return preg_replace_callback(
			'/&|[^\x20-\x7e]+/u',
			function ( $m ) {
				if ( '&' === $m[0] ) {
					return '&-';
				}
				return '&' . str_replace( '/', ',', rtrim( base64_encode( mb_convert_encoding( $m[0], 'UTF-16BE', 'UTF-8' ) ), '=' ) ) . '-';
			},
			$s
		);
	}
}
