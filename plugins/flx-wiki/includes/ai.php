<?php
/**
 * Flux AI ile "Wiki'ye sor": soruya en uygun sayfalar aranır, Markdown'ları modele verilir,
 * cevap yalnız bu sayfalardan ve kaynak bağlantılarıyla yazılır. Flux AI yoksa özellik görünmez.
 *
 * @package FLX_Wiki
 */

defined( 'ABSPATH' ) || exit;

/* Flux AI › Bağlı sistemler, kaynak adı, amaç etiketi. */
add_filter(
	'flxai_systems',
	function ( $s ) {
		$s['flx-wiki'] = array(
			'label'  => 'Flux Wiki',
			'plugin' => plugin_basename( FLXWK_FILE ),
			'page'   => 'edit.php?post_type=' . FLXWK_PT . '&page=flx-wiki',
		);
		return $s;
	}
);
add_filter(
	'flxai_sources',
	function ( $s ) {
		$s['flx-wiki'] = 'Flux Wiki';
		return $s;
	}
);
add_filter(
	'flxai_purpose_labels',
	function ( $l ) {
		$l['wiki_ask']   = __( 'Wiki\'ye sor', 'flx-wiki' );
		$l['wiki_draft'] = __( 'Wiki taslağı', 'flx-wiki' );
		return $l;
	}
);

/**
 * Soruyu wiki içeriğiyle yanıtlar.
 *
 * @param string $q       Soru.
 * @param int    $page_id Okunan sayfa (bağlam olarak öne alınır).
 * @return array|WP_Error { answer (Markdown), html, sources: [ {title, url} ] }
 */
function flxwk_ask( $q, $page_id = 0 ) {
	if ( ! flxwk_ai_ready() ) {
		return new WP_Error( 'flxwk_ai', __( 'Flux AI hazır değil.', 'flx-wiki' ), array( 'status' => 503 ) );
	}
	$ids = array();
	if ( $page_id && flxwk_readable( $page_id ) && 'publish' === get_post_status( $page_id ) ) {
		$ids[] = (int) $page_id;
	}
	foreach ( flxwk_search( $q, array( 'limit' => 6 ) )['results'] as $r ) {
		$ids[] = (int) $r['id'];
	}
	$ids = array_slice( array_values( array_unique( $ids ) ), 0, 5 );

	$ctx     = '';
	$sources = array();
	$budget  = 28000;
	foreach ( $ids as $id ) {
		$md = flxwk_page_markdown( $id );
		if ( mb_strlen( $md ) > $budget ) {
			$md = mb_substr( $md, 0, max( 2000, $budget ) ) . "\n…";
		}
		$budget -= mb_strlen( $md );
		$ctx    .= "\n\n<page url=\"" . get_permalink( $id ) . '">' . "\n" . $md . "\n</page>";
		$sources[] = array( 'title' => get_the_title( $id ), 'url' => get_permalink( $id ) );
		if ( $budget < 1500 ) {
			break;
		}
	}

	$system = array(
		array(
			'type'  => 'text',
			'cache' => true,
			'text'  => implode(
				"\n",
				array(
					'You answer questions about the "' . flxwk_title() . '" developer wiki of ' . get_bloginfo( 'name' ) . ' (FluxWP WordPress plugins and themes).',
					'Use ONLY the wiki pages given in <page> tags. If they do not contain the answer, say so briefly and suggest the closest page.',
					'Answer in the language of the question. Be concise: short paragraphs, lists and code blocks (```php …```) when useful.',
					'Link the pages you used inline as Markdown links with their exact url (you may append #heading anchors). Never invent URLs, function names or hooks.',
				)
			),
		),
		array(
			'type' => 'text',
			'text' => $ctx ? 'Wiki pages:' . $ctx : 'No wiki page matched the question.',
		),
	);
	$res = flxai_chat(
		array(
			array( 'role' => 'system', 'content' => $system ),
			array( 'role' => 'user', 'content' => $q ),
		),
		array(
			'source'     => 'flx-wiki',
			'purpose'    => 'wiki_ask',
			'max_tokens' => 1200,
			'timeout'    => 45,
		)
	);
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'flxwk_ai_fail', __( 'Cevap alınamadı. Biraz sonra tekrar deneyin.', 'flx-wiki' ), array( 'status' => 502, 'reason' => $res->get_error_code() ) );
	}
	$answer = trim( (string) ( $res['content'] ?? '' ) );
	$html   = flxwk_render( $answer );
	return array(
		'answer'  => $answer,
		'html'    => $html['html'],
		'sources' => $sources,
	);
}
