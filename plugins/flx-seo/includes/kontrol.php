<?php
/**
 * SEO kontrolü: içerik bazında eksikler (yazı listesindeki SEO sütunu, ayarlardaki rapor ve MCP).
 *
 * @package flx-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return array[] level (error|warning), code, msg
 */
function flxseo_audit_post( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return array();
	}
	$issues  = array();
	$add     = function ( $level, $code, $msg ) use ( &$issues ) {
		$issues[] = compact( 'level', 'code', 'msg' );
	};
	$content = $post->post_content;
	$text    = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( $content ) ) ) );
	$words   = $text ? count( preg_split( '/\s+/u', $text ) ) : 0;
	$title   = flxseo_get( $post->ID, 'title' );
	$full    = $title ? $title : get_the_title( $post ) . ' ' . flxseo_opt( 'sep' ) . ' ' . flxseo_opt( 'alt_name' );
	$desc    = flxseo_get( $post->ID, 'desc' );
	$article = in_array( $post->post_type, flxseo_article_types(), true );

	if ( flxseo_get( $post->ID, 'noindex' ) ) {
		$add( 'warning', 'noindex', __( 'Hidden from search engines (noindex).', 'flx-seo' ) );
	}
	if ( mb_strlen( $full ) > 65 ) {
		/* translators: %d: character count */
		$add( 'warning', 'title_long', sprintf( __( 'Title is long (%d characters); Google cuts after about 60.', 'flx-seo' ), mb_strlen( $full ) ) );
	} elseif ( $article && mb_strlen( get_the_title( $post ) ) < 15 ) {
		$add( 'warning', 'title_short', __( 'Title is very short; write a title that describes the topic.', 'flx-seo' ) );
	}
	if ( ! $desc ) {
		$add( 'warning', 'desc_missing', __( 'No meta description; it is built from the content automatically.', 'flx-seo' ) );
	} elseif ( mb_strlen( $desc ) > 165 ) {
		/* translators: %d: character count */
		$add( 'warning', 'desc_long', sprintf( __( 'Meta description is long (%d characters).', 'flx-seo' ), mb_strlen( $desc ) ) );
	} elseif ( mb_strlen( $desc ) < 70 ) {
		/* translators: %d: character count */
		$add( 'warning', 'desc_short', sprintf( __( 'Meta description is short (%d characters).', 'flx-seo' ), mb_strlen( $desc ) ) );
	}
	if ( $article && ! has_post_thumbnail( $post ) ) {
		$add( 'warning', 'no_image', __( 'No featured image (it will not show in shares or Google Discover).', 'flx-seo' ) );
	}
	if ( preg_match_all( '/<img\b[^>]*>/i', $content, $imgs ) ) {
		$noalt = 0;
		foreach ( $imgs[0] as $img ) {
			if ( ! preg_match( '/\balt="[^"]+"/i', $img ) ) {
				++$noalt;
			}
		}
		if ( $noalt ) {
			if ( flxseo_opt( 'auto_alt' ) ) {
				/* translators: %d: image count */
				$add( 'warning', 'img_alt', sprintf( __( '%d images have no alt text (filled in automatically from the image name; write a meaningful alt text).', 'flx-seo' ), $noalt ) );
			} else {
				/* translators: %d: image count */
				$add( 'error', 'img_alt', sprintf( __( '%d images have no alt text.', 'flx-seo' ), $noalt ) );
			}
		}
	}
	// Tam genişlik şablonu sayfa başlığını göstermez; başlık içerikteki H1'dir.
	$h1       = preg_match_all( '/<h1[\s>]/i', $content );
	$own_head = 'page-full' === get_page_template_slug( $post );
	if ( $own_head && $h1 > 1 ) {
		/* translators: %d: H1 count */
		$add( 'error', 'h1', sprintf( __( 'The page has %d H1 headings; there must be only one.', 'flx-seo' ), $h1 ) );
	} elseif ( $own_head && ! $h1 ) {
		$add( 'warning', 'h1', __( 'The page has no main heading (H1).', 'flx-seo' ) );
	} elseif ( ! $own_head && $h1 ) {
		$add( 'error', 'h1', __( 'The content has an H1; the page title is already the H1. Make sub-headings H2.', 'flx-seo' ) );
	}
	if ( $article && $words < 150 ) {
		/* translators: %d: word count */
		$add( 'warning', 'thin', sprintf( __( 'The content is short (%d words). 300+ words are recommended.', 'flx-seo' ), $words ) );
	}
	if ( $words > 350 && ! preg_match( '/<h2[\s>]/i', $content ) ) {
		$add( 'warning', 'no_h2', __( 'Long content has no sub-heading (H2).', 'flx-seo' ) );
	}
	$focus = mb_strtolower( trim( (string) flxseo_get( $post->ID, 'focus' ) ) );
	if ( $focus ) {
		if ( false === mb_strpos( mb_strtolower( $full ), $focus ) ) {
			$add( 'warning', 'focus_title', __( 'The focus keyword is not in the title.', 'flx-seo' ) );
		}
		if ( false === mb_strpos( mb_strtolower( flxseo_post_description( $post ) ), $focus ) ) {
			$add( 'warning', 'focus_desc', __( 'The focus keyword is not in the description.', 'flx-seo' ) );
		}
	}
	if ( $article && false === strpos( $content, home_url() ) && ! preg_match( '/href="\/(?!\/)/', $content ) ) {
		$add( 'warning', 'no_internal', __( 'No internal links (link to related pages).', 'flx-seo' ) );
	}
	return $issues;
}

/**
 * Site geneli özet.
 */
function flxseo_audit_site( $limit = 200, $post_type = '' ) {
	$q     = new WP_Query(
		array(
			'post_type'      => $post_type ? $post_type : flxseo_post_types(),
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'no_found_rows'  => true,
			'orderby'        => 'modified',
		)
	);
	$rows  = array();
	$count = array();
	foreach ( $q->posts as $p ) {
		$issues = flxseo_audit_post( $p );
		foreach ( $issues as $i ) {
			$count[ $i['code'] ] = ( $count[ $i['code'] ] ?? 0 ) + 1;
		}
		if ( $issues ) {
			$rows[] = array(
				'id'     => $p->ID,
				'title'  => get_the_title( $p ),
				'type'   => $p->post_type,
				'url'    => get_permalink( $p ),
				'issues' => $issues,
			);
		}
	}
	usort(
		$rows,
		function ( $a, $b ) {
			return count( $b['issues'] ) <=> count( $a['issues'] );
		}
	);
	return array(
		'checked' => count( $q->posts ),
		'summary' => $count,
		'items'   => $rows,
	);
}

function flxseo_audit_page() {
	$r = flxseo_audit_site( 300 );
	?>
	<p>Yayındaki <strong><?php echo (int) $r['checked']; ?></strong> içerik kontrol edildi; <strong><?php echo count( $r['items'] ); ?></strong> içerikte öneri var. Düzeltmek için içeriği açıp sağdaki <em>SEO</em> panelini kullanın ya da yapay zekâ asistanına (MCP) "SEO eksiklerini düzelt" deyin.</p>
	<table class="widefat striped">
		<thead><tr><th>İçerik</th><th>Tür</th><th>Öneriler</th></tr></thead>
		<tbody>
		<?php foreach ( array_slice( $r['items'], 0, 100 ) as $row ) : ?>
			<tr>
				<td><a href="<?php echo esc_url( get_edit_post_link( $row['id'] ) ); ?>"><strong><?php echo esc_html( $row['title'] ); ?></strong></a><br><a href="<?php echo esc_url( $row['url'] ); ?>" target="_blank" rel="noopener" style="font-size:12px">görüntüle</a></td>
				<td><?php echo esc_html( $row['type'] ); ?></td>
				<td><ul style="margin:0">
				<?php foreach ( $row['issues'] as $i ) : ?>
					<li style="color:<?php echo 'error' === $i['level'] ? 'var(--flxa-error, #d63638)' : 'var(--flxa-warn, #8a6100)'; ?>"><?php echo esc_html( $i['msg'] ); ?></li>
				<?php endforeach; ?>
				</ul></td>
			</tr>
		<?php endforeach; ?>
		<?php if ( ! $r['items'] ) : ?>
			<tr><td colspan="3">Her şey yolunda.</td></tr>
		<?php endif; ?>
		</tbody>
	</table>
	<?php
}
