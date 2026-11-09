<?php
/** Add language subtypes to the existing posts provider without changing native content queries. */
defined( 'ABSPATH' ) || exit;

class FLXL_Posts_Sitemaps extends WP_Sitemaps_Posts {
	private $native;
	private $translations;

	public function __construct( $native ) {
		parent::__construct();
		$this->native = $native;
		$this->translations = new FLXL_Sitemaps();
	}

	public function get_object_subtypes() {
		$native = $this->native->get_object_subtypes();
		$extra = array_filter( $this->translations->get_object_subtypes(), function ( $data ) use ( $native ) {
			return isset( $native[ $data['type'] ] );
		} );
		// A real post type always wins over a coincidentally matching language subtype.
		return $native + $extra;
	}

	public function is_translation_subtype( $subtype ) {
		return ! isset( $this->native->get_object_subtypes()[ $subtype ] ) && isset( $this->get_object_subtypes()[ $subtype ] );
	}

	public function get_url_list( $page_num, $object_subtype = '' ) {
		if ( isset( $this->native->get_object_subtypes()[ $object_subtype ] ) ) {
			return $this->native->get_url_list( $page_num, $object_subtype );
		}
		return $this->is_translation_subtype( $object_subtype ) ? $this->translations->get_url_list( $page_num, $object_subtype ) : array();
	}

	public function get_max_num_pages( $object_subtype = '' ) {
		if ( isset( $this->native->get_object_subtypes()[ $object_subtype ] ) ) {
			return $this->native->get_max_num_pages( $object_subtype );
		}
		return $this->is_translation_subtype( $object_subtype ) ? $this->translations->get_max_num_pages( $object_subtype ) : 0;
	}
}
