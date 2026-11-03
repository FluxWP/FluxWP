<?php
/**
 * Re-encrypts stored secrets (SMTP/IMAP passwords, WhatsApp/API tokens,
 * social tokens, AI keys, card references) whose key was derived from the
 * old package prefix. The rename changed those key strings (yti → flxi,
 * ytai → flxai, ytsm → flxsm, ytb → flxb), so the new plugins could not
 * open the old values and treated them as empty.
 *
 * @package FlxMigrate
 */

defined( 'ABSPATH' ) || exit;

final class Flx_Migrate_Secrets {

	/** @var bool */
	private $changed = false;

	/** @var int */
	private $failed = 0;

	public static function available(): bool {
		return function_exists( 'sodium_crypto_secretbox_open' );
	}

	/**
	 * Secret schemes: prefix => list of [old key, new key, new prefix].
	 * Keys are derived exactly like the old and new plugins derive them.
	 */
	private function schemes(): array {
		static $s = null;
		if ( null !== $s ) {
			return $s;
		}
		$b    = SODIUM_CRYPTO_SECRETBOX_KEYBYTES;
		$mail = static function ( string $p ) use ( $b ) {
			return substr( hash( 'sha256', wp_salt( 'secure_auth' ) . $p, true ), 0, $b );
		};
		$ai = static function ( string $p ) use ( $b ) {
			return sodium_crypto_generichash( wp_salt( 'auth' ) . $p, '', $b );
		};
		$social = static function ( string $p ) {
			return substr( hash( 'sha256', $p . '|' . wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' ), true ), 0, 32 );
		};
		$card = static function ( string $p ) {
			return hash( 'sha256', $p . '-iyzico-card|' . wp_salt( 'auth' ), true );
		};
		$s = array(
			'mail'   => array( $mail( 'yti' ), $mail( 'flxi' ) ),
			'ai'     => array( $ai( 'ytai' ), $ai( 'flxai' ) ),
			'social' => array( $social( 'ytsm' ), $social( 'flxsm' ) ),
			'card'   => array( $card( 'ytb' ), $card( 'flxb' ) ),
		);
		return $s;
	}

	/**
	 * Converts a stored value (plain, serialized or JSON). Returns null when
	 * nothing in it changed, so callers write only real changes.
	 *
	 * @param string $v Raw column value.
	 */
	public function convert( string $v ): ?string {
		$this->changed = false;
		$out           = $this->raw( $v );
		return $this->changed ? $out : null;
	}

	public function failed(): int {
		return $this->failed;
	}

	private function raw( string $v ): string {
		if ( '' === $v ) {
			return $v;
		}
		if ( is_serialized( $v ) ) {
			$u = @unserialize( $v, array( 'allowed_classes' => false ) ); // phpcs:ignore
			if ( false !== $u || 'b:0;' === $v ) {
				$before        = $this->changed;
				$this->changed = false;
				$n             = $this->walk( $u );
				$hit           = $this->changed;
				$this->changed = $before || $hit;
				return $hit ? serialize( $n ) : $v; // phpcs:ignore
			}
			return $v;
		}
		if ( '{' === $v[0] || '[' === $v[0] ) {
			$j = json_decode( $v, true );
			if ( is_array( $j ) ) {
				$before        = $this->changed;
				$this->changed = false;
				$n             = $this->walk( $j );
				$hit           = $this->changed;
				$this->changed = $before || $hit;
				return $hit ? (string) wp_json_encode( $n, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : $v;
			}
		}
		return $this->one( $v );
	}

	/** @param mixed $v */
	private function walk( $v ) {
		if ( is_array( $v ) ) {
			foreach ( $v as $k => $x ) {
				$v[ $k ] = $this->walk( $x );
			}
			return $v;
		}
		return is_string( $v ) ? $this->raw( $v ) : $v;
	}

	/** A single string that may be a secret. */
	private function one( string $v ): string {
		$sc = $this->schemes();
		if ( preg_match( '/^(yti|flxi)0:(.*)$/s', $v, $m ) ) {
			return $this->set( $v, 'flxi0:' . $m[2] );
		}
		if ( preg_match( '/^(yti|flxi)1:([A-Za-z0-9+\/=]+)$/', $v, $m ) ) {
			return $this->box( $v, $m[2], $sc['mail'], 'flxi1:' );
		}
		if ( preg_match( '/^enc1:([A-Za-z0-9+\/=]+)$/', $v, $m ) ) {
			return $this->box( $v, $m[1], $sc['ai'], 'enc1:' );
		}
		if ( preg_match( '/^s1:([A-Za-z0-9+\/=]+)$/', $v, $m ) ) {
			// Flux Social and the card references share the "s1:" prefix.
			$r = $this->box( $v, $m[1], $sc['social'], 's1:', false );
			return $r !== $v ? $r : $this->box( $v, $m[1], $sc['card'], 's1:' );
		}
		if ( preg_match( '/^o1:([A-Za-z0-9+\/=]+)$/', $v, $m ) && function_exists( 'openssl_decrypt' ) ) {
			$raw = base64_decode( $m[1], true );
			if ( false === $raw || strlen( $raw ) <= 28 ) {
				return $v;
			}
			list( $old, $new ) = $sc['card'];
			$iv  = substr( $raw, 0, 12 );
			$tag = substr( $raw, 12, 16 );
			$c   = substr( $raw, 28 );
			if ( false !== openssl_decrypt( $c, 'aes-256-gcm', $new, OPENSSL_RAW_DATA, $iv, $tag ) ) {
				return $v;
			}
			$p = openssl_decrypt( $c, 'aes-256-gcm', $old, OPENSSL_RAW_DATA, $iv, $tag );
			if ( false === $p ) {
				$this->failed++;
				return $v;
			}
			$iv2  = random_bytes( 12 );
			$tag2 = '';
			$c2   = openssl_encrypt( $p, 'aes-256-gcm', $new, OPENSSL_RAW_DATA, $iv2, $tag2 );
			return false === $c2 ? $v : $this->set( $v, 'o1:' . base64_encode( $iv2 . $tag2 . $c2 ) );
		}
		return $v;
	}

	/**
	 * Sodium secretbox value: kept when the new key opens it, re-sealed when
	 * only the old key does.
	 */
	private function box( string $v, string $b64, array $keys, string $prefix, bool $count_fail = true ): string {
		$raw = base64_decode( $b64, true );
		$nb  = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
		if ( false === $raw || strlen( $raw ) <= $nb ) {
			return $v;
		}
		list( $old, $new ) = $keys;
		$nonce = substr( $raw, 0, $nb );
		$box   = substr( $raw, $nb );
		if ( false !== sodium_crypto_secretbox_open( $box, $nonce, $new ) ) {
			return $this->set( $v, $prefix . $b64 );
		}
		$p = sodium_crypto_secretbox_open( $box, $nonce, $old );
		if ( false === $p ) {
			if ( $count_fail ) {
				$this->failed++;
			}
			return $v;
		}
		$n2 = random_bytes( $nb );
		return $this->set( $v, $prefix . base64_encode( $n2 . sodium_crypto_secretbox( $p, $n2, $new ) ) );
	}

	private function set( string $old, string $new ): string {
		if ( $new !== $old ) {
			$this->changed = true;
		}
		return $new;
	}
}
