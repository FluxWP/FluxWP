<?php

/**
 * Schema updater for Flux Block Editor.
 *
 * @package Flux Block Editor.
 */
class Flx_Blocks_Schema_Updater {

	/**
	 * Instance Control
	 *
	 * @var null
	 */
	private static $instance = null;

	/**
	 * The target schema version.
	 *
	 * @var int
	 */
	const TARGET_VERSION = 1;

	/**
	 * The option key for the schema version.
	 */
	const OPTION_KEY = 'flxbk_schema_version';

	/**
	 * The current version of the schema.
	 *
	 * @var int
	 */
	public $current_version = 0;

	/**
	 * Instance Control
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		$this->current_version = get_option( self::OPTION_KEY, 0 );

		if ( $this->needs_update() ) {
			$this->update( $this->current_version + 1 );
		}
	}

	public function needs_update() {
		return $this->current_version < self::TARGET_VERSION;
	}

	private function update( $version ) {
		switch ( $version ) {
			case 1:
				$this->update_0_to_1();
				break;
		}

		update_option( self::OPTION_KEY, $version );
		$this->current_version = $version;

		// Recursively update until we're done.
		if ( $this->needs_update() ) {
			$this->update( $this->current_version + 1 );
		}
	}

	/**
	 * Created July 17, 2023. The "google" attribute had a type of string instead of boolean.
	 * Released in Blocks Beta 3.1.3 Beta Version 2
	 *
	 * @return void
	 */
	private function update_0_to_1() {
		$form_posts = get_posts( array(
			'post_type'   => 'flxbk_form',
			'numberposts' => - 1,
		) );

		$meta_to_update = array(
			'_flxbk_form_inputFont',
			'_flxbk_form_labelFont',
			'_flxbk_form_radioLabelFont',
			'_flxbk_form_helpFont',
			'_flxbk_form_messageFont'
		);

		foreach ( $form_posts as $post ) {
			foreach ( $meta_to_update as $meta_key ) {
				$meta_value = get_post_meta( $post->ID, $meta_key, true );

				if ( isset( $meta_value['google'] ) && gettype( $meta_value['google'] ) === 'string' ) {
					// Saving didn't work previously, so we can safely set all existing values to false
					$meta_value['google'] = false;
					update_post_meta( $post->ID, $meta_key, $meta_value );
				}
			}
		}
	}
}

Flx_Blocks_Schema_Updater::get_instance();
