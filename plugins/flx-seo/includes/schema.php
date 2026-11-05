<?php
/**
 * JSON-LD: Organization (NGO), WebSite (Google'daki site adı + arama kutusu), WebPage,
 * BreadcrumbList, Article. Tek bir @graph olarak yazılır.
 *
 * @package flx-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function flxseo_breadcrumbs() {
	$items = array( array( 'name' => 'Ana Sayfa', 'url' => home_url( '/' ) ) );
	if ( is_front_page() ) {
		return $items;
	}
	if ( is_singular() ) {
		$post = get_queried_object();
		if ( 'page' === $post->post_type ) {
			foreach ( array_reverse( get_post_ancestors( $post ) ) as $pid ) {
				$items[] = array( 'name' => get_the_title( $pid ), 'url' => get_permalink( $pid ) );
			}
		} elseif ( 'post' === $post->post_type ) {
			$blog = (int) get_option( 'page_for_posts' );
			$cats = get_the_category( $post->ID );
			if ( $cats ) {
				$items[] = array( 'name' => $cats[0]->name, 'url' => get_category_link( $cats[0] ) );
			} elseif ( $blog ) {
				$items[] = array( 'name' => get_the_title( $blog ), 'url' => get_permalink( $blog ) );
			}
		} else {
			$pto = get_post_type_object( $post->post_type );
			$url = get_post_type_archive_link( $post->post_type );
			if ( $pto && $url ) {
				$items[] = array( 'name' => $pto->labels->name, 'url' => $url );
			}
		}
		$items[] = array( 'name' => single_post_title( '', false ), 'url' => get_permalink( $post ) );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$t = get_queried_object();
		foreach ( array_reverse( get_ancestors( $t->term_id, $t->taxonomy ) ) as $aid ) {
			$a       = get_term( $aid, $t->taxonomy );
			$items[] = array( 'name' => $a->name, 'url' => get_term_link( $a ) );
		}
		$items[] = array( 'name' => $t->name, 'url' => get_term_link( $t ) );
	} elseif ( is_post_type_archive() ) {
		$items[] = array( 'name' => post_type_archive_title( '', false ), 'url' => get_post_type_archive_link( get_query_var( 'post_type' ) ) );
	} elseif ( is_home() ) {
		$id      = (int) get_option( 'page_for_posts' );
		$items[] = array( 'name' => $id ? get_the_title( $id ) : 'Haberler', 'url' => $id ? get_permalink( $id ) : home_url( '/' ) );
	} else {
		return array();
	}
	return $items;
}

function flxseo_schema_graph() {
	$home  = home_url( '/' );
	$org   = $home . '#organization';
	$site  = $home . '#website';
	$lang  = str_replace( '_', '-', determine_locale() );
	$graph = array();

	$o = array(
		'@type'   => flxseo_opt( 'org_type' ) ? flxseo_opt( 'org_type' ) : 'NGO',
		'@id'     => $org,
		'name'    => flxseo_opt( 'org_name' ),
		'alternateName' => flxseo_opt( 'site_name' ),
		'url'     => $home,
		'logo'    => array(
			'@type'      => 'ImageObject',
			'@id'        => $home . '#logo',
			'url'        => flxseo_logo_url(),
			'contentUrl' => flxseo_logo_url(),
			'width'      => 512,
			'height'     => 512,
			'caption'    => flxseo_opt( 'site_name' ),
		),
		'image'   => array( '@id' => $home . '#logo' ),
	);
	if ( flxseo_opt( 'founding' ) ) {
		$o['foundingDate'] = flxseo_opt( 'founding' );
	}
	if ( flxseo_opt( 'street' ) || flxseo_opt( 'locality' ) ) {
		$o['address'] = array_filter(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => flxseo_opt( 'street' ),
				'addressLocality' => flxseo_opt( 'locality' ),
				'postalCode'      => flxseo_opt( 'postal' ),
				'addressCountry'  => 'TR',
			)
		);
	}
	if ( flxseo_opt( 'phone' ) ) {
		$o['telephone'] = flxseo_opt( 'phone' );
	}
	if ( flxseo_opt( 'email' ) ) {
		$o['email'] = flxseo_opt( 'email' );
	}
	$same = array_values( array_filter( array_map( 'esc_url_raw', preg_split( '/\s+/', (string) flxseo_opt( 'same_as' ) ) ) ) );
	if ( $same ) {
		$o['sameAs'] = $same;
	}
	$graph[] = $o;

	$graph[] = array(
		'@type'           => 'WebSite',
		'@id'             => $site,
		'url'             => $home,
		'name'            => flxseo_opt( 'site_name' ),
		// Alan adı alternatif ad olarak verilmez: Google onu site adı diye seçebiliyor.
		'alternateName'   => array_values( array_unique( array_filter( array( flxseo_opt( 'alt_name' ), flxseo_opt( 'org_name' ) ) ) ) ),
		'description'     => flxseo_opt( 'home_desc' ),
		'publisher'       => array( '@id' => $org ),
		'inLanguage'      => $lang,
		'potentialAction' => array(
			'@type'       => 'SearchAction',
			'target'      => array(
				'@type'       => 'EntryPoint',
				'urlTemplate' => $home . '?s={search_term_string}',
			),
			'query-input' => 'required name=search_term_string',
		),
	);

	$canon = flxseo_canonical();
	if ( ! $canon || is_404() ) {
		return $graph;
	}
	$page = array(
		'@type'      => is_search() ? 'SearchResultsPage' : ( is_singular() || is_front_page() ? 'WebPage' : 'CollectionPage' ),
		'@id'        => $canon . '#webpage',
		'url'        => $canon,
		'name'       => wp_get_document_title(),
		'isPartOf'   => array( '@id' => $site ),
		'inLanguage' => $lang,
	);
	$desc = flxseo_description();
	if ( $desc ) {
		$page['description'] = $desc;
	}
	if ( is_front_page() ) {
		$page['about'] = array( '@id' => $org );
	}
	$img = flxseo_image();
	if ( is_singular() ) {
		$page['primaryImageOfPage'] = array( '@type' => 'ImageObject', 'url' => $img['url'] );
		$page['datePublished']      = get_the_date( 'c', get_queried_object() );
		$page['dateModified']       = get_the_modified_date( 'c', get_queried_object() );
	}
	$crumbs = flxseo_breadcrumbs();
	if ( count( $crumbs ) > 1 ) {
		$list = array();
		foreach ( $crumbs as $i => $c ) {
			$list[] = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => wp_strip_all_tags( $c['name'] ),
				'item'     => $c['url'],
			);
		}
		$graph[]            = array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $canon . '#breadcrumb',
			'itemListElement' => $list,
		);
		$page['breadcrumb'] = array( '@id' => $canon . '#breadcrumb' );
	}
	$graph[] = $page;

	if ( is_singular( flxseo_article_types() ) ) {
		$post    = get_queried_object();
		$graph[] = array(
			'@type'            => 'post' === $post->post_type ? 'NewsArticle' : 'Article',
			'@id'              => $canon . '#article',
			'headline'         => flxseo_clean( get_the_title( $post ), 110 ),
			'description'      => $desc,
			'image'            => array( $img['url'] ),
			'datePublished'    => get_the_date( 'c', $post ),
			'dateModified'     => get_the_modified_date( 'c', $post ),
			'author'           => array( '@id' => $org ),
			'publisher'        => array( '@id' => $org ),
			'mainEntityOfPage' => array( '@id' => $canon . '#webpage' ),
			'isPartOf'         => array( '@id' => $canon . '#webpage' ),
			'inLanguage'       => $lang,
			'articleSection'   => wp_list_pluck( get_the_category( $post->ID ), 'name' ),
		);
	}
	return $graph;
}

add_action(
	'wp_head',
	function () {
		if ( ! flxseo_output_enabled() ) {
			return;
		}
		$data = array(
			'@context' => 'https://schema.org',
			'@graph'   => apply_filters( 'flxseo_schema_graph', flxseo_schema_graph() ),
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
	},
	4
);
