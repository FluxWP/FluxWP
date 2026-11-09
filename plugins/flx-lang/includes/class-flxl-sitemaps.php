<?php
/** Additional language URLs; physical translations remain in the core posts provider. */
defined( 'ABSPATH' ) || exit;

class FLXL_Sitemaps extends WP_Sitemaps_Posts {
	public function __construct() {
		parent::__construct();
		$this->name = 'posts';
	}

	public function get_object_subtypes() {
		$out = array();
		foreach ( parent::get_object_subtypes() as $type => $object ) {
			foreach ( flxl_languages() as $code => $language ) {
				if ( $code !== flxl_default_language() ) {
					$out[ $type . '-' . $code ] = array( 'type' => $type, 'language' => $code );
				}
			}
		}
		return $out;
	}

	/** Use core eligibility filters, including Flux SEO noindex and shop exclusions. */
	private function query_args( $subtype ) {
		$subtypes = $this->get_object_subtypes();
		if ( ! isset( $subtypes[ $subtype ] ) ) {
			return array();
		}
		$data = $subtypes[ $subtype ];
		$ids  = array_filter( flxl_shared_ids(), 'flxl_is_shared' );
		$front = (int) flxl_raw( function () {
			return 'page' === get_option( 'show_on_front' ) ? get_option( 'page_on_front' ) : 0;
		} );
		// Language home roots exist even when the home page has no separate translation.
		if ( 'page' === $data['type'] && $front && ! flxl_get_translation( $front, $data['language'] ) ) {
			$ids[] = $front;
		}
		$ids = array_filter( $ids, function ( $id ) use ( $data ) {
			return $data['type'] === get_post_type( $id );
		} );
		if ( ! $ids ) {
			return array();
		}
		$args = parent::get_posts_query_args( $data['type'] );
		if ( ! empty( $args['post__in'] ) ) {
			$ids = array_intersect( $ids, array_map( 'intval', $args['post__in'] ) );
		}
		if ( ! empty( $args['post__not_in'] ) ) {
			$ids = array_diff( $ids, array_map( 'intval', $args['post__not_in'] ) );
		}
		// WP_Query ignores an empty post__in, and prioritizes it over post__not_in.
		$args['post__in'] = $ids ? array_values( array_unique( array_map( 'intval', $ids ) ) ) : array( 0 );
		unset( $args['post__not_in'] );
		$args['has_password'] = false;
		$args['post_status'] = 'publish';
		return $args;
	}

	public function get_url_list( $page_num, $object_subtype = '' ) {
		$args = $this->query_args( $object_subtype );
		if ( ! $args || $page_num < 1 ) {
			return array();
		}
		$args['paged'] = (int) $page_num;
		$data  = $this->get_object_subtypes()[ $object_subtype ];
		$query = new WP_Query( $args );
		$out   = array();
		foreach ( $query->posts as $post ) {
			$entry = flxl_strict_links( function () use ( $post, $data ) {
				return apply_filters( 'wp_sitemaps_posts_entry', array(
					'loc' => get_permalink( $post ),
					'lastmod' => get_post_modified_time( 'c', true, $post ),
				), $post, $data['type'] );
			} );
			if ( ! empty( $entry['loc'] ) ) {
				$entry['loc'] = flxl_url_for_language( $entry['loc'], $data['language'] );
				$out[] = $entry;
			}
		}
		return $out;
	}

	public function get_max_num_pages( $object_subtype = '' ) {
		$args = $this->query_args( $object_subtype );
		if ( ! $args ) {
			return 0;
		}
		$args['fields'] = 'ids';
		$args['no_found_rows'] = false;
		$query = new WP_Query( $args );
		return (int) $query->max_num_pages;
	}
}
