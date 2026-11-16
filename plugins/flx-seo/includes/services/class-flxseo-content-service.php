<?php
/**
 * SEO fields of posts and terms (the editor panel, MCP and GraphQL `flxSeo` read and write the same meta).
 * Permission checks stay with the caller; set_post() checks the post.
 *
 * @package flx-seo
 */

defined( 'ABSPATH' ) || exit;

final class FLXSEO_Content_Service {

	public static function cache_tag( int $post_id ): string {
		return 'flx-seo:content:' . $post_id;
	}

	public static function for_post( int $post_id ): FLXSEO_Meta {
		$title = FLXSEO_Util::str( flxseo_get( $post_id, 'title' ) );
		$sep   = FLXSEO_Util::str( flxseo_opt( 'sep' ) );
		$alt   = FLXSEO_Util::str( flxseo_opt( 'alt_name' ) );
		return new FLXSEO_Meta(
			$title,
			FLXSEO_Util::str( flxseo_get( $post_id, 'desc' ) ),
			FLXSEO_Util::str( flxseo_get( $post_id, 'focus' ) ),
			(bool) flxseo_get( $post_id, 'noindex' ),
			FLXSEO_Util::str( flxseo_get( $post_id, 'image' ) ),
			'' !== $title ? $title : get_the_title( $post_id ) . ' ' . $sep . ' ' . $alt,
			FLXSEO_Util::str( flxseo_post_description( $post_id ) ),
		);
	}

	public static function for_term( int $term_id ): FLXSEO_Meta {
		$term  = get_term( $term_id );
		$name  = $term instanceof WP_Term ? $term->name : '';
		$title = FLXSEO_Util::str( get_term_meta( $term_id, '_flxseo_title', true ) );
		$desc  = FLXSEO_Util::str( get_term_meta( $term_id, '_flxseo_desc', true ) );
		$auto  = $term instanceof WP_Term ? flxseo_clean( $term->description ) : '';
		return new FLXSEO_Meta(
			$title,
			$desc,
			'',
			false,
			'',
			'' !== $title ? $title : $name . ' ' . FLXSEO_Util::str( flxseo_opt( 'sep' ) ) . ' ' . FLXSEO_Util::str( flxseo_opt( 'alt_name' ) ),
			'' !== $desc ? $desc : FLXSEO_Util::str( $auto ),
		);
	}

	/**
	 * Saves the SEO fields of a post. Fields that are not in $in stay as they are.
	 *
	 * @param int                                                                                          $post_id Post ID.
	 * @param array{title?: string|null, description?: string|null, focus_keyword?: string|null, noindex?: bool|null, og_image?: string|null} $in Fields.
	 * @return FLXSEO_Meta|WP_Error
	 */
	public static function set_post( int $post_id, array $in ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'flxseo_not_found', __( 'Content not found.', 'flx-seo' ), array( 'graphql_code' => 'NOT_FOUND' ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'flxseo_forbidden', __( 'You are not allowed to edit this content.', 'flx-seo' ), array( 'graphql_code' => 'FORBIDDEN' ) );
		}
		$seo = array();
		foreach ( array( 'title', 'description', 'focus_keyword', 'noindex', 'og_image' ) as $k ) {
			if ( array_key_exists( $k, $in ) && null !== $in[ $k ] ) {
				$seo[ $k ] = $in[ $k ];
			}
		}
		flxseo_mcp_save_seo( $post_id, $seo );
		flxseo_gql_purge( self::cache_tag( $post_id ) );
		return self::for_post( $post_id );
	}
}
