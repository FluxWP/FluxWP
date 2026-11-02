<?php
/**
 * Eski adları yeni adlara çevirir. Kurallar map.json'dan gelir; bu dosyada sabit ad yok.
 *
 * @package FlxMigrate
 */

defined( 'ABSPATH' ) || exit;

final class Flx_Migrate_Mapper {

	/** @var array */
	private $map;

	/** @var string */
	private $token_rx;

	/** @var array<string,string> */
	private $slugs;

	public function __construct( array $map ) {
		$this->map   = $map;
		$this->slugs = (array) ( $map['slugs'] ?? array() );
		uksort(
			$this->slugs,
			static function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);
		$r   = $map['rules'];
		$alt = implode( '|', array_map( 'preg_quote', array_keys( $this->slugs ) ) );
		$lo  = preg_quote( $r['lower'][0], '/' );
		$up  = preg_quote( $r['upper'][0], '/' );
		$cap = preg_quote( $r['cap'][0], '/' );

		$this->token_rx = '/(?<![A-Za-z0-9])(?:'
			. ( '' !== $alt ? '(?P<slug>' . $alt . ')(?![a-z0-9])|' : '' )
			. '(?P<gl>' . $lo . ')(?=[-_])'
			. '|(?P<gu>' . $up . ')(?=[-_])'
			. '|(?P<lo>' . $lo . ')(?=[a-zA-Z0-9])'
			. '|(?P<up>' . $up . ')(?=[A-Z])'
			. '|(?P<cap>' . $cap . ')(?=[gcb][A-Z])'
			. '|(?P<bl>' . $lo . ')(?![A-Za-z0-9])'
			. ')/';
	}

	/** Eski önekle başlayan bir ad mı? */
	public function is_old( string $s ): bool {
		return $s !== $this->name( $s );
	}

	/** Tek bir ad/tanımlayıcı (option, tablo, meta anahtarı, slug, yol) çevirisi. */
	public function name( string $s ): string {
		foreach ( (array) ( $this->map['protect'] ?? array() ) as $p ) {
			if ( 0 === strpos( $s, $p ) ) {
				return $s;
			}
		}
		$blocks = (array) ( $this->map['blocks'] ?? array() );
		if ( isset( $blocks[ $s ] ) ) {
			return $blocks[ $s ];
		}
		$r = $this->map['rules'];
		return (string) preg_replace_callback(
			$this->token_rx,
			function ( $m ) use ( $r ) {
				if ( isset( $m['slug'] ) && '' !== $m['slug'] ) {
					return $this->slugs[ $m['slug'] ];
				}
				if ( isset( $m['gl'] ) && '' !== $m['gl'] ) {
					return $r['shared_lower'][1];
				}
				if ( isset( $m['gu'] ) && '' !== $m['gu'] ) {
					return $r['shared_upper'][1];
				}
				if ( isset( $m['lo'] ) && '' !== $m['lo'] ) {
					return $r['lower'][1];
				}
				if ( isset( $m['up'] ) && '' !== $m['up'] ) {
					return $r['upper'][1];
				}
				if ( isset( $m['cap'] ) && '' !== $m['cap'] ) {
					return $r['cap'][1];
				}
				if ( isset( $m['bl'] ) && '' !== $m['bl'] ) {
					return $r['lower'][1];
				}
				return $m[0];
			},
			$s
		);
	}

	/** Tema klasörü adı. */
	public function theme( string $s ): string {
		$t = (array) ( $this->map['themes'] ?? array() );
		return $t[ $s ] ?? $s;
	}

	/**
	 * Değer içinde dolaşır: dizi anahtarları ve tanımlayıcı gibi duran metinler çevrilir.
	 * Serbest metinde yalnız blok adları, kısa kodlar, class'lar ve yükleme yolları değişir.
	 *
	 * @param mixed $v Değer.
	 * @return mixed
	 */
	public function value( $v ) {
		if ( is_array( $v ) ) {
			$out = array();
			foreach ( $v as $k => $x ) {
				$out[ is_string( $k ) ? $this->name( $k ) : $k ] = $this->value( $x );
			}
			return $out;
		}
		if ( is_object( $v ) ) {
			if ( $v instanceof __PHP_Incomplete_Class ) {
				return $v;
			}
			foreach ( get_object_vars( $v ) as $k => $x ) {
				$v->$k = $this->value( $x );
			}
			return $v;
		}
		if ( ! is_string( $v ) || '' === $v ) {
			return $v;
		}
		if ( is_serialized( $v ) ) {
			$u = @unserialize( $v, array( 'allowed_classes' => false ) ); // phpcs:ignore
			if ( false !== $u || 'b:0;' === $v ) {
				return serialize( $this->value( $u ) ); // phpcs:ignore
			}
			return $v;
		}
		if ( preg_match( '/^[A-Za-z0-9_\-.\/:]+$/', $v ) && strlen( $v ) <= 200 ) {
			return $this->name( $v );
		}
		if ( '{' === $v[0] || '[' === $v[0] ) {
			$j = json_decode( $v, true );
			if ( is_array( $j ) ) {
				return wp_json_encode( $this->value( $j ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			}
		}
		return $this->content( $v );
	}

	/** Serbest metin: blok yorumları, kısa kodlar, class/id öznitelikleri, yükleme yolları. */
	public function content( string $s ): string {
		$s = (string) preg_replace_callback(
			'/<!--\s*(\/?)wp:([a-z0-9_-]+\/[a-z0-9_-]+)/i',
			function ( $m ) {
				return '<!-- ' . $m[1] . 'wp:' . $this->name( $m[2] );
			},
			$s
		);
		$s = (string) preg_replace_callback(
			'/\[(\/?)([A-Za-z0-9_-]+)/',
			function ( $m ) {
				return '[' . $m[1] . $this->name( $m[2] );
			},
			$s
		);
		$s = (string) preg_replace_callback(
			'/\b(class|id|data-[a-z-]+)=("|\')([^"\']*)\2/',
			function ( $m ) {
				return $m[1] . '=' . $m[2] . $this->name( $m[3] ) . $m[2];
			},
			$s
		);
		$s = (string) preg_replace_callback(
			'/(\/wp-content\/(?:uploads|plugins|themes)\/)([A-Za-z0-9_-]+)/',
			function ( $m ) {
				$t = $this->theme( $m[2] );
				return $m[1] . ( $t !== $m[2] ? $t : $this->name( $m[2] ) );
			},
			$s
		);
		return $s;
	}
}
