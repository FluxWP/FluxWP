<?php
/**
 * Wiki ekranlarının HTML'i: ana sayfa, arama sonuçları, bölüm, sayfa.
 *
 * @package FLX_Wiki
 */

defined( 'ABSPATH' ) || exit;

/** Küçük satır içi simgeler (Lucide, MIT). */
function flxwk_icon( $name ) {
	$p = array(
		'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
		'copy'   => '<rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
		'md'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13v4l2-2 2 2v-4"/><path d="M16 13v4m-1.5-1.5L16 17l1.5-1.5"/>',
		'spark'  => '<path d="M9.94 14.06 4 20M14.06 9.94 20 4M12 3v3M12 18v3M3 12h3M18 12h3"/><circle cx="12" cy="12" r="2"/>',
		'ext'    => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
		'edit'   => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
		'menu'   => '<path d="M4 6h16M4 12h16M4 18h16"/>',
		'book'   => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/>',
		'bot'    => '<path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2M20 14h2M15 13v2M9 13v2"/>',
		'left'   => '<path d="m15 18-6-6 6-6"/>',
		'right'  => '<path d="m9 18 6-6-6-6"/>',
		'list'   => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
		'down'   => '<path d="m6 9 6 6 6-6"/>',
		'clock'  => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
	);
	return '<svg class="flxwk-i" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $p[ $name ] ?? '' ) . '</svg>';
}

/** Blok / klasik şablonun içeriği. */
function flxwk_view_html() {
	ob_start();
	$accent = sanitize_hex_color( (string) flxwk_opt( 'accent' ) );
	$cls    = 'flxwk' . ( 'wide' === flxwk_opt( 'width' ) ? ' is-wide-layout' : '' ) . ( flxwk_opt( 'nav' ) ? '' : ' no-nav' ) . ( flxwk_opt( 'toc' ) ? '' : ' no-toc' );
	echo '<div class="' . esc_attr( $cls ) . '" id="flxwk"' . ( $accent ? ' style="--wk-accent:' . esc_attr( $accent ) . '"' : '' ) . '>';
	if ( is_singular( FLXWK_PT ) ) {
		flxwk_view_page( get_queried_object() );
	} elseif ( is_tax( FLXWK_TAX ) ) {
		flxwk_view_section( get_queried_object() );
	} elseif ( '' !== flxwk_query() ) {
		flxwk_view_search( flxwk_query() );
	} else {
		flxwk_view_home();
	}
	flxwk_view_modal();
	echo '</div>';
	return ob_get_clean();
}

/** Arama kutusu (JS'siz de çalışır: /wiki/?q=). */
function flxwk_search_form( $big = false, $value = '' ) {
	$k = '<kbd class="flxwk-kbd">' . ( isset( $_SERVER['HTTP_USER_AGENT'] ) && false !== stripos( (string) $_SERVER['HTTP_USER_AGENT'], 'Mac' ) ? '⌘' : 'Ctrl' ) . ' K</kbd>'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	?>
	<div class="flxwk-sbox<?php echo $big ? ' is-big' : ''; ?>" data-flxwk-live>
	<form class="flxwk-search<?php echo $big ? ' is-big' : ''; ?>" role="search" action="<?php echo esc_url( flxwk_home_url() ); ?>" method="get">
		<?php echo flxwk_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<label class="screen-reader-text" for="flxwk-q<?php echo $big ? '-big' : ''; ?>"><?php esc_html_e( 'Wiki\'de ara', 'flx-wiki' ); ?></label>
		<input id="flxwk-q<?php echo $big ? '-big' : ''; ?>" type="search" name="q" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $big ? __( 'Ne arıyorsunuz? Ör. MCP aracı, flx_settings_schema, tema…', 'flx-wiki' ) : __( 'Wiki\'de ara…', 'flx-wiki' ) ); ?>" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" data-flxwk-q>
		<?php echo $k; // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</form>
	<div class="flxwk-drop" data-flxwk-drop hidden></div>
	</div>
	<?php
}

/* ------------------------------------------------------------------ */
/* Ana sayfa                                                           */
/* ------------------------------------------------------------------ */

function flxwk_view_home() {
	$tree = flxwk_tree();
	$pages = flxwk_all_pages();
	$recent = $pages;
	usort( $recent, fn( $a, $b ) => strcmp( $b->post_modified_gmt, $a->post_modified_gmt ) );
	$recent = array_slice( $recent, 0, 5 );
	$start = $tree ? ( $tree[0]['items'][0]['post'] ?? null ) : null;
	?>
	<div class="flxwk-portal">
	<section class="flxwk-hero">
		<div class="flxwk-hero__copy">
			<p class="flxwk-eyebrow"><?php echo flxwk_icon( 'book' ); ?> <?php esc_html_e( 'Dokümantasyon', 'flx-wiki' ); ?></p>
			<h1 class="flxwk-hero__title"><?php echo esc_html( flxwk_title() ); ?></h1>
			<p class="flxwk-hero__lead"><?php echo esc_html( flxwk_intro() ); ?></p>
			<p class="flxwk-hero__meta">
				<?php echo esc_html( sprintf( _n( '%s sayfa', '%s sayfa', count( $pages ), 'flx-wiki' ), number_format_i18n( count( $pages ) ) ) ); ?>
				<?php if ( flxwk_can_ask() ) : ?>
					<span aria-hidden="true">/</span><button type="button" class="flxwk-link" data-flxwk-ask><?php echo flxwk_icon( 'spark' ); ?> <?php esc_html_e( 'Wiki\'ye sor', 'flx-wiki' ); ?></button>
				<?php endif; ?>
			</p>
		</div>
		<?php if ( $start ) : ?>
			<a class="flxwk-start" href="<?php echo esc_url( get_permalink( $start ) ); ?>">
				<span class="flxwk-start__label"><?php echo flxwk_icon( 'spark' ); ?> <?php esc_html_e( 'Buradan başlayın', 'flx-wiki' ); ?></span>
				<span class="flxwk-start__title"><?php echo esc_html( get_the_title( $start ) ); ?> <?php echo flxwk_icon( 'right' ); ?></span>
				<span class="flxwk-start__desc"><?php echo esc_html( flxwk_excerpt( $start, 180 ) ); ?></span>
			</a>
		<?php endif; ?>
	</section>
	<div class="flxwk-portal__search"><?php flxwk_search_form( true ); ?></div>
	<div class="flxwk-home">
		<div class="flxwk-home__heading"><h2><?php esc_html_e( 'Rehberleri keşfedin', 'flx-wiki' ); ?></h2><?php echo flxwk_icon( 'list' ); ?></div>
		<?php if ( ! $tree ) : ?><p class="flxwk-empty"><?php esc_html_e( 'Henüz yayımlanmış sayfa yok.', 'flx-wiki' ); ?></p><?php endif; ?>
		<div class="flxwk-cards">
		<?php foreach ( $tree as $i => $g ) : ?>
			<section class="flxwk-card">
				<div class="flxwk-card__header"><span class="flxwk-card__number" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $i + 1 ) ); ?></span>
				<h3 class="flxwk-card__title"><?php if ( $g['term'] ) : ?><a href="<?php echo esc_url( get_term_link( $g['term'] ) ); ?>"><?php echo esc_html( $g['term']->name ); ?></a><?php else : ?><?php esc_html_e( 'Diğer sayfalar', 'flx-wiki' ); ?><?php endif; ?></h3></div>
				<?php if ( $g['term'] && $g['term']->description ) : ?><p class="flxwk-card__desc"><?php echo esc_html( $g['term']->description ); ?></p><?php endif; ?>
				<ul class="flxwk-card__list">
				<?php foreach ( array_slice( $g['items'], 0, 6 ) as $n ) : ?>
					<li><a href="<?php echo esc_url( get_permalink( $n['post'] ) ); ?>"><span><?php echo esc_html( get_the_title( $n['post'] ) ); ?></span><?php if ( $n['children'] ) : ?><span class="flxwk-count"><?php echo esc_html( number_format_i18n( count( $n['children'] ) ) ); ?></span><?php endif; ?><?php echo flxwk_icon( 'right' ); ?></a></li>
				<?php endforeach; ?>
				</ul>
				<?php if ( $g['term'] ) : ?><a class="flxwk-more" href="<?php echo esc_url( get_term_link( $g['term'] ) ); ?>"><?php esc_html_e( 'Tümünü gör', 'flx-wiki' ); ?> <?php echo flxwk_icon( 'right' ); ?></a><?php endif; ?>
			</section>
		<?php endforeach; ?>
		</div>
		<div class="flxwk-home__row">
			<?php if ( $recent ) : ?>
			<section class="flxwk-recent"><h2><?php echo flxwk_icon( 'clock' ); ?> <?php esc_html_e( 'Son güncellenenler', 'flx-wiki' ); ?></h2><ul>
			<?php foreach ( $recent as $p ) : ?><li><a href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a><span><?php echo esc_html( flxwk_ago( get_post_modified_time( 'U', true, $p ) ) ); ?></span></li><?php endforeach; ?>
			</ul></section>
			<?php endif; ?>
			<section class="flxwk-llm"><span class="flxwk-llm__icon"><?php echo flxwk_icon( 'bot' ); ?></span><h2><?php esc_html_e( 'LLM ve AI ajanları için', 'flx-wiki' ); ?></h2>
			<p><?php esc_html_e( 'Her sayfa adresin sonuna .md eklenerek düz Markdown olarak açılır. Ajanlar dizini llms.txt\'den, tüm içeriği tek dosyada llms-full.txt\'den okur.', 'flx-wiki' ); ?></p>
			<ul class="flxwk-llm__links"><?php foreach ( array( 'llms.txt', 'llms-full.txt', 'index.md' ) as $file ) : ?><li><a href="<?php echo esc_url( flxwk_home_url( $file ) ); ?>"><code><?php echo esc_html( $file ); ?></code><?php echo flxwk_icon( 'right' ); ?></a></li><?php endforeach; ?></ul>
			</section>
		</div>
	</div>
	</div>
	<?php
}

/* ------------------------------------------------------------------ */
/* Arama sonuçları (JS'siz)                                            */
/* ------------------------------------------------------------------ */

function flxwk_view_search( $q ) {
	$r = flxwk_search( $q, array( 'limit' => 50 ) );
	?>
	<div class="flxwk-shell is-wide">
		<?php flxwk_view_nav( null ); ?>
		<main class="flxwk-main" id="wp--skip-link--target">
			<?php flxwk_view_crumbs(); ?>
			<h1 class="flxwk-title"><?php esc_html_e( 'Arama', 'flx-wiki' ); ?></h1>
			<?php flxwk_search_form( true, $q ); ?>
			<div class="flxwk-sres" data-flxwk-page-results aria-live="polite">
			<p class="flxwk-muted">
				<?php
				if ( ! empty( $r['fixed'] ) ) {
					/* translators: 1: aranan, 2: düzeltilmiş sorgu */
					echo esc_html( sprintf( __( '"%1$s" bulunamadı; "%2$s" için sonuçlar gösteriliyor.', 'flx-wiki' ), $q, $r['fixed'] ) );
				} else {
					/* translators: 1: sonuç sayısı, 2: sorgu */
					echo esc_html( sprintf( _n( '"%2$s" için %1$s sonuç', '"%2$s" için %1$s sonuç', $r['total'], 'flx-wiki' ), number_format_i18n( $r['total'] ), $q ) );
				}
				?>
			</p>
			<?php if ( ! $r['results'] ) : ?>
				<p class="flxwk-empty"><?php esc_html_e( 'Sonuç yok. Başka bir kelime deneyin.', 'flx-wiki' ); ?></p>
			<?php endif; ?>
			<ol class="flxwk-results">
				<?php foreach ( $r['results'] as $it ) : ?>
					<li>
						<a class="flxwk-results__t" href="<?php echo esc_url( $it['url'] ); ?>"><?php echo esc_html( $it['title'] ); ?><?php echo $it['heading'] ? ' <span>› ' . esc_html( $it['heading'] ) . '</span>' : ''; ?></a>
						<?php if ( $it['section'] ) : ?>
							<span class="flxwk-chip"><?php echo esc_html( $it['section'] ); ?></span>
						<?php endif; ?>
						<p><?php echo wp_kses( $it['snippet'], array( 'mark' => array() ) ); ?></p>
					</li>
				<?php endforeach; ?>
			</ol>
			</div>
		</main>
	</div>
	<?php
}

/* ------------------------------------------------------------------ */
/* Bölüm                                                               */
/* ------------------------------------------------------------------ */

function flxwk_view_section( $term ) {
	$group = null;
	foreach ( flxwk_tree() as $g ) {
		if ( $g['term'] && (int) $g['term']->term_id === (int) $term->term_id ) {
			$group = $g;
		}
	}
	?>
	<div class="flxwk-shell is-wide">
		<?php flxwk_view_nav( null ); ?>
		<main class="flxwk-main" id="wp--skip-link--target">
			<?php flxwk_view_crumbs(); ?>
			<h1 class="flxwk-title"><?php echo esc_html( $term->name ); ?></h1>
			<?php if ( $term->description ) : ?>
				<p class="flxwk-lead"><?php echo esc_html( $term->description ); ?></p>
			<?php endif; ?>
			<div class="flxwk-sec-list">
				<?php
				$walk = function ( $nodes, $depth ) use ( &$walk ) {
					foreach ( $nodes as $n ) {
						$p = $n['post'];
						echo '<a class="flxwk-sec-item depth-' . (int) min( $depth, 3 ) . '" href="' . esc_url( get_permalink( $p ) ) . '"><strong>' . esc_html( get_the_title( $p ) ) . '</strong>';
						$ex = flxwk_excerpt( $p, 150 );
						if ( $ex ) {
							echo '<span>' . esc_html( $ex ) . '</span>';
						}
						echo '</a>';
						$walk( $n['children'], $depth + 1 );
					}
				};
				$walk( $group ? $group['items'] : array(), 0 );
				?>
			</div>
		</main>
	</div>
	<?php
}

/* ------------------------------------------------------------------ */
/* Sayfa                                                               */
/* ------------------------------------------------------------------ */

function flxwk_view_page( $post ) {
	$r     = flxwk_page_html( $post );
	$flat  = flxwk_flat();
	$ids   = wp_list_pluck( $flat, 'ID' );
	$at    = array_search( $post->ID, $ids, true );
	$prev  = false !== $at && $at > 0 ? $flat[ $at - 1 ] : null;
	$next  = false !== $at && $at < count( $flat ) - 1 ? $flat[ $at + 1 ] : null;
	$md    = flxwk_md( $post );
	$mdurl = flxwk_md_url( $post );
	$html  = $r['html'];
	/* İçerik kendi H1'iyle başlıyorsa sayfa başlığı yerine o kullanılmaz; tekrar etmesin. */
	$html = preg_replace( '#^\s*<h1[^>]*>.*?</h1>#s', '', $html, 1 );
	$toc  = $r['toc'];
	?>
	<div class="flxwk-shell">
		<?php flxwk_view_nav( $post ); ?>
		<main class="flxwk-main" id="wp--skip-link--target">
			<?php flxwk_view_crumbs(); ?>
			<article class="flxwk-article" data-flxwk-article>
				<header class="flxwk-head">
					<div class="flxwk-head__text">
					<h1 class="flxwk-title"><?php echo esc_html( get_the_title( $post ) ); ?></h1>
					<p class="flxwk-meta">
						<span><?php echo flxwk_icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php
						$min = flxwk_read_minutes( $md );
						/* translators: %s: dakika */
						echo esc_html( sprintf( _n( '%s dk okuma', '%s dk okuma', $min, 'flx-wiki' ), number_format_i18n( $min ) ) );
						?>
						</span>
					</p>
					</div>
					<?php flxwk_view_actions( $post, $mdurl ); ?>
				</header>

				<?php if ( flxwk_opt( 'toc' ) && count( $toc ) > 1 ) : ?>
					<details class="flxwk-toc-m">
						<summary><?php echo flxwk_icon( 'list' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Bu sayfada', 'flx-wiki' ); ?></summary>
						<?php flxwk_toc_list( $toc ); ?>
					</details>
				<?php endif; ?>

				<div class="flxwk-content">
					<?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- flxwk_render içinde wp_kses ile temizlendi. ?>
				</div>

				<?php
				$kids = get_children( array( 'post_parent' => $post->ID, 'post_type' => FLXWK_PT, 'post_status' => 'publish', 'orderby' => 'menu_order title', 'order' => 'ASC' ) );
				if ( $kids ) :
					?>
					<section class="flxwk-children">
						<h2><?php esc_html_e( 'Alt sayfalar', 'flx-wiki' ); ?></h2>
						<div class="flxwk-sec-list">
							<?php foreach ( $kids as $k ) : ?>
								<a class="flxwk-sec-item" href="<?php echo esc_url( get_permalink( $k ) ); ?>"><strong><?php echo esc_html( get_the_title( $k ) ); ?></strong><span><?php echo esc_html( flxwk_excerpt( $k, 140 ) ); ?></span></a>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endif; ?>

				<p class="flxwk-meta flxwk-updated">
					<?php echo flxwk_icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php
					/* translators: %s: süre */
					echo esc_html( sprintf( __( 'Güncellendi: %s', 'flx-wiki' ), flxwk_ago( get_post_modified_time( 'U', true, $post ) ) ) );
					?>
				</p>

				<nav class="flxwk-pager" aria-label="<?php esc_attr_e( 'Önceki ve sonraki sayfa', 'flx-wiki' ); ?>">
					<?php if ( $prev ) : ?>
						<a class="is-prev" href="<?php echo esc_url( get_permalink( $prev ) ); ?>"><span><?php echo flxwk_icon( 'left' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Önceki', 'flx-wiki' ); ?></span><strong><?php echo esc_html( get_the_title( $prev ) ); ?></strong></a>
					<?php endif; ?>
					<?php if ( $next ) : ?>
						<a class="is-next" href="<?php echo esc_url( get_permalink( $next ) ); ?>"><span><?php esc_html_e( 'Sonraki', 'flx-wiki' ); ?> <?php echo flxwk_icon( 'right' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><strong><?php echo esc_html( get_the_title( $next ) ); ?></strong></a>
					<?php endif; ?>
				</nav>
			</article>
		</main>
		<?php if ( flxwk_opt( 'toc' ) ) : ?>
		<aside class="flxwk-toc" aria-label="<?php esc_attr_e( 'Bu sayfada', 'flx-wiki' ); ?>">
			<?php if ( $toc ) : ?>
				<p class="flxwk-toc__t"><?php esc_html_e( 'Bu sayfada', 'flx-wiki' ); ?></p>
				<?php flxwk_toc_list( $toc ); ?>
			<?php endif; ?>
			<div class="flxwk-toc__llm">
				<a href="<?php echo esc_url( $mdurl ); ?>"><?php echo flxwk_icon( 'md' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Markdown', 'flx-wiki' ); ?></a>
				<a href="<?php echo esc_url( home_url( '/' . flxwk_base() . '/llms.txt' ) ); ?>"><?php echo flxwk_icon( 'bot' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> llms.txt</a>
			</div>
		</aside>
		<?php endif; ?>
	</div>
	<?php
}

function flxwk_toc_list( $toc ) {
	echo '<ul class="flxwk-toc__list">';
	foreach ( $toc as $h ) {
		echo '<li class="lv-' . (int) $h['level'] . '"><a href="#' . esc_attr( $h['id'] ) . '">' . esc_html( $h['text'] ) . '</a></li>';
	}
	echo '</ul>';
}

/** Markdown / AI'a sor (Wiki'ye sor + dış servisler) / Düzenle. */
function flxwk_view_actions( $post, $mdurl ) {
	/* translators: %s: sayfanın Markdown adresi */
	$prompt = sprintf( __( '%s adresindeki belgeyi oku; sonra bu konuda sorularımı yanıtla.', 'flx-wiki' ), $mdurl );
	$ai     = array(
		'ChatGPT'    => 'https://chatgpt.com/?hints=search&q=' . rawurlencode( $prompt ),
		'Claude'     => 'https://claude.ai/new?q=' . rawurlencode( $prompt ),
		'Google AI'  => 'https://www.google.com/search?udm=50&q=' . rawurlencode( $prompt ),
		'Perplexity' => 'https://www.perplexity.ai/search?q=' . rawurlencode( $prompt ),
	);
	$ai     = (array) apply_filters( 'flxwk_ai_links', $ai, $post, $mdurl );
	$ico = function ( $n ) {
		return flxwk_icon( $n ); // phpcs:ignore WordPress.Security.EscapeOutput
	};
	?>
	<div class="flxwk-actions">
		<?php if ( $mdurl ) : ?>
		<details class="flxwk-menu">
			<summary class="flxwk-btn"><?php echo $ico( 'md' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <span><?php esc_html_e( 'Markdown', 'flx-wiki' ); ?></span> <?php echo $ico( 'down' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></summary>
			<div class="flxwk-menu__pop">
				<button type="button" data-flxwk-copy-md="<?php echo esc_url( $mdurl ); ?>"><?php echo $ico( 'copy' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <span><?php esc_html_e( 'Markdown\'u kopyala', 'flx-wiki' ); ?></span></button>
				<a href="<?php echo esc_url( $mdurl ); ?>" target="_blank" rel="noopener"><?php echo $ico( 'ext' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <span><?php esc_html_e( '.md olarak aç', 'flx-wiki' ); ?></span></a>
			</div>
		</details>
		<?php endif; ?>
		<?php if ( ( flxwk_opt( 'ai_links' ) && $ai ) || flxwk_can_ask() ) : ?>
			<details class="flxwk-menu">
				<summary class="flxwk-btn"><?php echo $ico( 'spark' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <span><?php esc_html_e( 'AI\'a sor', 'flx-wiki' ); ?></span> <?php echo $ico( 'down' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></summary>
				<div class="flxwk-menu__pop">
					<?php if ( flxwk_can_ask() ) : ?>
						<button type="button" class="is-accent" data-flxwk-ask><?php echo $ico( 'spark' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <span><?php esc_html_e( 'Wiki\'ye sor', 'flx-wiki' ); ?></span></button>
					<?php endif; ?>
					<?php foreach ( flxwk_opt( 'ai_links' ) ? $ai : array() as $label => $url ) : ?>
						<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener nofollow"><?php echo $ico( 'ext' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <span>
							<?php echo esc_html( $label ); ?>
						</span></a>
					<?php endforeach; ?>
				</div>
			</details>
		<?php endif; ?>
		<?php if ( flxwk_opt( 'edit_link' ) && current_user_can( 'edit_post', $post->ID ) ) : ?>
			<a class="flxwk-btn is-icon" href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>" title="<?php esc_attr_e( 'Düzenle', 'flx-wiki' ); ?>" aria-label="<?php esc_attr_e( 'Düzenle', 'flx-wiki' ); ?>"><?php echo $ico( 'edit' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Konum satırı. $bar: mobilde "Sayfalar" düğmesinin yanında, wiki adı olmadan.
 */
function flxwk_view_crumbs( $bar = false ) {
	$c = flxwk_breadcrumbs();
	if ( $bar ) {
		$c = array_slice( array_slice( $c, 1 ), -2 );
		if ( ! $c ) {
			return;
		}
	} elseif ( count( $c ) < 2 && ! is_post_type_archive( FLXWK_PT ) ) {
		return;
	}
	echo '<nav class="flxwk-crumbs' . ( $bar ? ' is-bar' : '' ) . '" aria-label="' . esc_attr__( 'Konum', 'flx-wiki' ) . '"' . '><ol>';
	$last = count( $c ) - 1;
	foreach ( $c as $i => $it ) {
		$url = is_wp_error( $it[1] ) ? '' : $it[1];
		echo '<li>' . ( $i < $last && $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $it[0] ) . '</a>' : '<span aria-current="page">' . esc_html( $it[0] ) . '</span>' ) . '</li>';
	}
	echo '</ol></nav>';
}

/** Sol gezinme: arama + bölümler ve sayfa ağacı (geçerli dal açık). */
function flxwk_view_nav( $current ) {
	if ( ! flxwk_opt( 'nav' ) ) {
		return;
	}
	$open = $current ? array_merge( array( $current->ID ), get_post_ancestors( $current ) ) : array();
	$walk = function ( $nodes ) use ( &$walk, $current, $open ) {
		echo '<ul>';
		foreach ( $nodes as $n ) {
			$p      = $n['post'];
			$is_cur = $current && $p->ID === $current->ID;
			$is_on  = in_array( $p->ID, $open, true );
			echo '<li class="' . ( $is_cur ? 'is-current ' : '' ) . ( $n['children'] ? 'has-kids ' : '' ) . ( $is_on ? 'is-open' : '' ) . '">';
			echo '<a href="' . esc_url( get_permalink( $p ) ) . '"' . ( $is_cur ? ' aria-current="page"' : '' ) . '>' . esc_html( get_the_title( $p ) ) . '</a>';
			if ( $n['children'] ) {
				$walk( $n['children'] );
			}
			echo '</li>';
		}
		echo '</ul>';
	};
	?>
	<button type="button" class="flxwk-navbtn" data-flxwk-nav aria-controls="flxwk-nav" aria-expanded="false"><?php echo flxwk_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Sayfalar', 'flx-wiki' ); ?></button>
	<?php flxwk_view_crumbs( true ); ?>
	<aside class="flxwk-nav" id="flxwk-nav" aria-label="<?php esc_attr_e( 'Wiki sayfaları', 'flx-wiki' ); ?>">
		<a class="flxwk-nav__home" href="<?php echo esc_url( flxwk_home_url() ); ?>"><?php echo flxwk_icon( 'book' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( flxwk_title() ); ?></a>
		<?php flxwk_search_form( false ); ?>
		<nav class="flxwk-tree">
			<?php foreach ( flxwk_tree() as $g ) : ?>
				<p class="flxwk-tree__sec"><?php echo esc_html( $g['term'] ? $g['term']->name : __( 'Diğer sayfalar', 'flx-wiki' ) ); ?></p>
				<?php $walk( $g['items'] ); ?>
			<?php endforeach; ?>
		</nav>
	</aside>
	<?php
}

/** Arama / soru penceresi (JS ile açılır). */
function flxwk_view_modal() {
	?>
	<div class="flxwk-modal" data-flxwk-modal hidden>
		<div class="flxwk-modal__bg" data-flxwk-close></div>
		<div class="flxwk-modal__box" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Wiki\'de ara', 'flx-wiki' ); ?>">
			<div class="flxwk-modal__tabs" role="tablist">
				<button type="button" role="tab" class="is-on" data-flxwk-tab="search"><?php echo flxwk_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Ara', 'flx-wiki' ); ?></button>
				<?php if ( flxwk_can_ask() ) : ?>
					<button type="button" role="tab" data-flxwk-tab="ask"><?php echo flxwk_icon( 'spark' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Wiki\'ye sor', 'flx-wiki' ); ?></button>
				<?php endif; ?>
				<button type="button" class="flxwk-modal__x" data-flxwk-close aria-label="<?php esc_attr_e( 'Kapat', 'flx-wiki' ); ?>">Esc</button>
			</div>
			<form class="flxwk-modal__f" data-flxwk-form>
				<?php echo flxwk_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<input type="search" data-flxwk-input autocomplete="off" placeholder="<?php esc_attr_e( 'Wiki\'de ara…', 'flx-wiki' ); ?>" aria-label="<?php esc_attr_e( 'Wiki\'de ara', 'flx-wiki' ); ?>">
			</form>
			<div class="flxwk-modal__out" data-flxwk-out aria-live="polite"></div>
			<div class="flxwk-modal__foot">
				<span><kbd class="flxwk-kbd">↑</kbd><kbd class="flxwk-kbd">↓</kbd> <?php esc_html_e( 'seç', 'flx-wiki' ); ?></span>
				<span><kbd class="flxwk-kbd">Enter</kbd> <?php esc_html_e( 'aç', 'flx-wiki' ); ?></span>
				<span><kbd class="flxwk-kbd">Esc</kbd> <?php esc_html_e( 'kapat', 'flx-wiki' ); ?></span>
			</div>
		</div>
	</div>
	<?php
}
