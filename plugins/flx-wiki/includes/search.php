<?php
/**
 * Arama: tüm sayfaların küçük bir dizini (başlık, ara başlıklar, düz metin) önbellekte tutulur;
 * sorgu Türkçe karakterleri katlayarak (ı=i, ş=s …) puanlanır. Başlık > ara başlık > özet > metin.
 * Sonuç en iyi eşleşen ara başlığa (#bolum) bağlanır, metinden vurgulu kısa alıntı döner.
 *
 * @package FLX_Wiki
 */

defined( 'ABSPATH' ) || exit;

/** Markdown'u aranabilir düz metne çevirir. */
function flxwk_plain( $md ) {
	$t = (string) $md;
	$t = preg_replace( '/^```[^\n]*$/m', '', $t );
	$t = preg_replace( '/!\[([^\]]*)\]\([^)]*\)/', '$1', $t );
	$t = preg_replace( '/\[([^\]]+)\]\([^)]*\)/', '$1', $t );
	$t = preg_replace_callback(
		'/\[\[([^\]|#]+)(?:#[^\]|]*)?(?:\|([^\]]+))?\]\]/',
		function ( $m ) {
			if ( isset( $m[2] ) && '' !== $m[2] ) {
				return $m[2];
			}
			$p = flxwk_find_page( $m[1] );
			return $p ? get_the_title( $p ) : $m[1];
		},
		$t
	);
	$t = preg_replace( '/^\s{0,3}(#{1,6}|>|[-*+]|\d+\.)\s+/m', '', $t );
	$t = preg_replace( '/\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]/', '', $t );
	$t = str_replace( array( '**', '`' ), '', $t );
	$t = preg_replace( '/^\s*\|?[\s:|-]+\|?\s*$/m', ' ', $t ); // tablo ayırıcı satırı
	$t = str_replace( '|', ' ', $t );
	$t = wp_strip_all_tags( $t );
	return trim( preg_replace( '/\s+/u', ' ', $t ) );
}

/** Arama dizini (önbellekli). */
function flxwk_index() {
	$idx = get_transient( flxwk_cache_key( 'index' ) );
	if ( is_array( $idx ) ) {
		return $idx;
	}
	$idx = array();
	foreach ( flxwk_all_pages() as $p ) {
		$md   = flxwk_md( $p );
		$r    = flxwk_page_html( $p );
		$heads = array();
		foreach ( $r['toc'] as $h ) {
			$heads[] = array( $h['id'], $h['text'] );
		}
		/* Ara başlığa göre bölünmüş metin: eşleşme hangi bölümdeyse oraya bağlanır. */
		$chunks = array();
		$cur    = '';
		$hi     = -1;
		$code   = false;
		foreach ( explode( "\n", $md ) as $line ) {
			if ( preg_match( '/^\s{0,3}(```|~~~)/', $line ) ) {
				$code = ! $code;
			}
			if ( ! $code && preg_match( '/^#{2,3}\s+(.+)$/', $line ) && $hi + 1 < count( $heads ) ) {
				$chunks[] = array( $hi, flxwk_plain( $cur ) );
				$cur      = '';
				++$hi;
				continue;
			}
			$cur .= $line . "\n";
		}
		$chunks[] = array( $hi, flxwk_plain( $cur ) );
		$sec      = flxwk_section_of( $p );
		$idx[]    = array(
			'id'      => $p->ID,
			'title'   => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
			'url'     => get_permalink( $p ),
			'section' => $sec ? $sec->name : '',
			'sslug'   => $sec ? $sec->slug : '',
			'excerpt' => flxwk_excerpt( $p, 200 ),
			'heads'   => $heads,
			'chunks'  => $chunks,
			'mod'     => get_post_modified_time( 'U', true, $p ),
		);
	}
	set_transient( flxwk_cache_key( 'index' ), $idx, DAY_IN_SECONDS );
	return $idx;
}

/** Sorguyu katlanmış kelimelere böler; soru kelimeleri (nasıl, nedir, how …) atılır. */
function flxwk_tokens( $q ) {
	$words = preg_split( '/[^\p{L}\p{N}_]+/u', flxwk_fold( $q ), -1, PREG_SPLIT_NO_EMPTY );
	$words = array_values( array_unique( $words ) );
	if ( count( $words ) > 1 ) {
		$stop  = array_flip( (array) apply_filters( 'flxwk_stopwords', array( 'nasil', 'ne', 'neden', 'nedir', 'nerede', 'nereye', 'nereden', 'neyi', 'niye', 'kac', 'hangi', 'kim', 'mi', 'mu', 've', 'ile', 'bir', 'bu', 'su', 'o', 'icin', 'gibi', 'da', 'de', 'ki', 'ya', 'veya', 'how', 'what', 'why', 'where', 'which', 'the', 'a', 'an', 'to', 'is', 'are', 'do', 'does', 'i', 'in', 'of', 'for', 'and', 'or', 'can', 'my' ) ) );
		$kept  = array_values( array_filter( $words, fn( $w ) => mb_strlen( $w ) > 1 && ! isset( $stop[ $w ] ) ) );
		$words = $kept ? $kept : $words;
	}
	return array_slice( $words, 0, 8 );
}

/** Kaba Türkçe kök: yaygın ekler atılır (yazılır → yaz, eklentiler → eklenti). */
function flxwk_stem( $w ) {
	static $suffixes = array( 'lerin', 'larin', 'leri', 'lari', 'ilir', 'ulur', 'inin', 'unun', 'nin', 'nun', 'ler', 'lar', 'mak', 'mek', 'dir', 'dur', 'tir', 'tur', 'den', 'dan', 'ten', 'tan', 'lik', 'luk', 'si', 'su', 'yi', 'yu', 'ya', 'ye', 'da', 'de', 'ma', 'me', 'in', 'un', 'i', 'u', 'a', 'e', 's' );
	$w = (string) $w;
	for ( $round = 0; $round < 2; $round++ ) {
		foreach ( $suffixes as $suf ) {
			$l = strlen( $suf );
			if ( strlen( $w ) - $l >= 3 && substr( $w, -$l ) === $suf ) {
				$w = substr( $w, 0, -$l );
				continue 2;
			}
		}
		break;
	}
	return $w;
}

/**
 * Arar.
 *
 * @param string $q    Sorgu.
 * @param array  $args limit (20), section (bölüm kısa adı).
 * @return array { total, results: [ {id, title, url, section, heading, snippet, score} ] }
 */
function flxwk_search( $q, $args = array() ) {
	$q      = trim( (string) $q );
	$limit  = max( 1, min( 50, (int) ( $args['limit'] ?? 20 ) ) );
	$filter = (string) ( $args['section'] ?? '' );
	$tokens = flxwk_tokens( $q );
	if ( ! $tokens ) {
		return array( 'total' => 0, 'results' => array() );
	}
	$phrase = implode( ' ', $tokens );
	$hits   = array();
	foreach ( flxwk_index() as $doc ) {
		if ( $filter && $filter !== $doc['sslug'] ) {
			continue;
		}
		$title   = flxwk_fold( $doc['title'] );
		$excerpt = flxwk_fold( $doc['excerpt'] );
		$heads   = array_map( fn( $h ) => flxwk_fold( $h[1] ), $doc['heads'] );
		$chunks  = array_map( fn( $c ) => array( $c[0], flxwk_fold( $c[1] ) ), $doc['chunks'] );
		/* Kök araması yalnız kelime başında eşleşir ("tem" → "tema" evet, "sistem" hayır). */
		$wd      = fn( $x ) => ' ' . preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $x );
		$plain   = array(
			'title'   => $title,
			'excerpt' => $excerpt,
			'heads'   => $heads,
			'chunks'  => $chunks,
		);
		$words   = array(
			'title'   => $wd( $title ),
			'excerpt' => $wd( $excerpt ),
			'heads'   => array_map( $wd, $heads ),
			'chunks'  => array_map( fn( $c ) => array( $c[0], $wd( $c[1] ) ), $chunks ),
		);
		$score   = 0;
		$matched = 0;
		$best    = array( -2, 0 ); // [başlık sırası, puan]
		$rate    = function ( $t, $w, $f ) use ( &$best ) {
			$s = 0;
			if ( false !== ( $pos = strpos( $f['title'], $t ) ) ) {
				$s += 12 + ( 0 === $pos || ' ' === $f['title'][ $pos - 1 ] || ' ' === $t[0] ? 4 : 0 );
			}
			foreach ( $f['heads'] as $i => $h ) {
				if ( false !== strpos( $h, $t ) ) {
					$s += 6;
					if ( $best[1] < 6 * $w ) {
						$best = array( $i, 6 * $w );
					}
				}
			}
			if ( false !== strpos( $f['excerpt'], $t ) ) {
				$s += 3;
			}
			foreach ( $f['chunks'] as $c ) {
				$n = substr_count( $c[1], $t );
				if ( $n ) {
					$s += min( $n, 8 );
					if ( $best[1] < min( $n, 5 ) * $w && $c[0] >= 0 ) {
						$best = array( $c[0], min( $n, 5 ) * $w );
					}
				}
			}
			return $s * $w;
		};
		foreach ( $tokens as $t ) {
			$s = $rate( $t, 1, $plain );
			if ( ! $s ) {
				$stem = flxwk_stem( $t );
				if ( $stem !== $t ) {
					$s = $rate( ' ' . $stem, 0.6, $words );
				}
			}
			if ( $s ) {
				++$matched;
				$score += $s;
			}
		}
		if ( ! $score ) {
			continue;
		}
		if ( count( $tokens ) > 1 ) {
			if ( false !== strpos( $title, $phrase ) ) {
				$score += 40;
			} elseif ( count( array_filter( $tokens, fn( $t ) => false !== strpos( $title, $t ) || false !== strpos( $words['title'], ' ' . flxwk_stem( $t ) ) ) ) === count( $tokens ) ) {
				$score += 30; // Tüm kelimeler başlıkta: o sayfa konunun kendisidir.
			}
			foreach ( $chunks as $c ) {
				if ( false !== strpos( $c[1], $phrase ) ) {
					$score += 8;
					break;
				}
			}
		}
		/* Tüm kelimeler eşleşenler öne; eksikler puan kaybeder. */
		$all    = $matched === count( $tokens );
		$score  = $all ? $score + 50 : $score * $matched / count( $tokens );
		$hits[] = array( $doc, $score, $best[0], $all );
	}
	/* Sonuç yoksa yazım hatası düzeltilir ("kurlum" → "kurulum") ve bir kez daha aranır. */
	if ( ! $hits && empty( $args['_fixed'] ) ) {
		$fixed = flxwk_fix_tokens( $tokens );
		if ( $fixed !== $tokens ) {
			$res = flxwk_search( implode( ' ', $fixed ), array( '_fixed' => 1 ) + $args );
			if ( $res['total'] ) {
				$res['fixed'] = implode( ' ', $fixed );
			}
			return $res;
		}
	}
	$any_all = (bool) array_filter( $hits, fn( $h ) => $h[3] );
	if ( $any_all ) {
		$hits = array_values( array_filter( $hits, fn( $h ) => $h[3] ) );
	}
	usort( $hits, fn( $a, $b ) => $b[1] <=> $a[1] );
	$out = array();
	foreach ( array_slice( $hits, 0, $limit ) as $h ) {
		list( $doc, $score, $hi ) = $h;
		$heading = $hi >= 0 && isset( $doc['heads'][ $hi ] ) ? $doc['heads'][ $hi ] : null;
		$out[]   = array(
			'id'      => $doc['id'],
			'title'   => $doc['title'],
			'url'     => $doc['url'] . ( $heading ? '#' . $heading[0] : '' ),
			'section' => $doc['section'],
			'heading' => $heading ? $heading[1] : '',
			'snippet' => flxwk_snippet( $doc, $tokens, $hi ),
			'score'   => round( $score, 1 ),
		);
	}
	return array( 'total' => count( $hits ), 'results' => $out );
}

/** Dizindeki kelimeler ve sıklıkları (katlanmış, 3+ harf; önbellekli). */
function flxwk_vocab() {
	$v = get_transient( flxwk_cache_key( 'vocab' ) );
	if ( is_array( $v ) ) {
		return $v;
	}
	$v = array();
	foreach ( flxwk_index() as $doc ) {
		$text = $doc['title'] . ' ' . $doc['excerpt'] . ' ' . implode( ' ', array_column( $doc['heads'], 1 ) ) . ' ' . implode( ' ', array_column( $doc['chunks'], 1 ) );
		foreach ( preg_split( '/[^\p{L}\p{N}_]+/u', flxwk_fold( $text ), -1, PREG_SPLIT_NO_EMPTY ) as $w ) {
			if ( strlen( $w ) >= 3 && strlen( $w ) <= 30 ) {
				$v[ $w ] = ( $v[ $w ] ?? 0 ) + 1;
			}
		}
	}
	set_transient( flxwk_cache_key( 'vocab' ), $v, DAY_IN_SECONDS );
	return $v;
}

/**
 * Dizinde geçmeyen kelimeyi en yakın dizin kelimesiyle değiştirir (Levenshtein: 4-5 harfte 1, daha uzunda 2 fark).
 * Eşitlikte sık geçen kelime seçilir. Bulunamazsa kelime aynen kalır.
 */
function flxwk_fix_tokens( array $tokens ) {
	$vocab = flxwk_vocab();
	$out   = array();
	foreach ( $tokens as $t ) {
		$len = strlen( $t );
		if ( isset( $vocab[ $t ] ) || $len < 4 ) {
			$out[] = $t;
			continue;
		}
		$max  = $len <= 5 ? 1 : 2;
		$best = array( $t, $max + 1, 0 );
		foreach ( $vocab as $w => $n ) {
			if ( abs( strlen( $w ) - $len ) > $max ) {
				continue;
			}
			$d = levenshtein( $t, (string) $w );
			if ( $d <= $max && ( $d < $best[1] || ( $d === $best[1] && $n > $best[2] ) ) ) {
				$best = array( (string) $w, $d, $n );
			}
		}
		$out[] = $best[0];
	}
	return $out;
}

/** Eşleşen kelimeyi çevreleyen ~180 karakterlik alıntı; kelimeler <mark> ile (HTML güvenli). */
function flxwk_snippet( $doc, $tokens, $hi ) {
	$text = '';
	foreach ( $doc['chunks'] as $c ) {
		if ( $c[0] === $hi ) {
			$text = $c[1];
			break;
		}
	}
	$folded = flxwk_fold( $text );
	$pos    = false;
	$stems  = array_diff( array_map( 'flxwk_stem', $tokens ), $tokens );
	foreach ( array_merge( $tokens, $stems ) as $t ) {
		$p = mb_strpos( $folded, $t );
		if ( false !== $p && ( false === $pos || $p < $pos ) ) {
			$pos = $p;
		}
	}
	if ( false === $pos ) {
		$text = $doc['excerpt'] ? $doc['excerpt'] : implode( ' ', array_column( $doc['chunks'], 1 ) );
		$pos  = 0;
	}
	$start = max( 0, $pos - 60 );
	if ( $start > 0 ) {
		$sp    = mb_strpos( $text, ' ', $start );
		$start = false !== $sp && $sp < $pos ? $sp + 1 : $start;
	}
	$snip = mb_substr( $text, $start, 180 );
	if ( $start + 180 < mb_strlen( $text ) ) {
		$sp   = mb_strrpos( $snip, ' ' );
		$snip = ( $sp > 120 ? mb_substr( $snip, 0, $sp ) : $snip ) . '…';
	}
	$html = esc_html( ( $start > 0 ? '…' : '' ) . $snip );
	$tokens = array_unique( array_filter( $tokens, fn( $t ) => mb_strlen( $t ) >= 2 ) );
	usort( $tokens, fn( $a, $b ) => mb_strlen( $b ) - mb_strlen( $a ) );
	$alts = array_map( 'flxwk_token_regex', $tokens );
	foreach ( $stems as $st ) {
		$alts[] = '(?<![\p{L}\p{N}])' . flxwk_token_regex( $st );
	}
	if ( $alts ) {
		$re   = '/(' . implode( '|', $alts ) . ')(?![^<]*>)/iu';
		$html = preg_replace( $re, '<mark>$1</mark>', $html );
	}
	return preg_replace( '#</mark>(\s*)<mark>#', '$1', $html );
}

/** Katlanmış kelimeyi Türkçe karakter karşılıklarıyla eşleşen ifadeye çevirir. */
function flxwk_token_regex( $t ) {
	$map = array(
		'i' => '[iıİI]',
		'g' => '[gğĞ]',
		's' => '[sşŞ]',
		'c' => '[cçÇ]',
		'o' => '[oöÖ]',
		'u' => '[uüÜ]',
		'a' => '[aâÂ]',
	);
	$out = '';
	foreach ( preg_split( '//u', $t, -1, PREG_SPLIT_NO_EMPTY ) as $ch ) {
		$out .= $map[ $ch ] ?? preg_quote( $ch, '/' );
	}
	return $out;
}
