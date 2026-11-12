<?php
/**
 * Class to Build the Table Data Block.
 *
 * @package Flux Block Editor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to Build the Table Data Block.
 *
 * @category class
 */
class Flx_Blocks_Table_Data_Block extends Flx_Blocks_Abstract_Block {

	/**
	 * Instance of this class
	 *
	 * @var null
	 */
	private static $instance = null;

	/**
	 * Block name within this namespace.
	 *
	 * @var string
	 */
	protected $block_name = 'data';

	/**
	 * Block determines in scripts need to be loaded for block.
	 *
	 * @var string
	 */
	protected $has_script = false;

	protected $has_style = false;


	/**
	 * Instance Control
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * On init startup register the block.
	 */
	public function on_init() {
		register_block_type(
			FLXBK_PATH . 'build/blocks/table/children/' . $this->block_name . '/block.json',
			array(
				'render_callback' => array( $this, 'render_css' ),
			)
		);
	}

	/**
	 * Builds CSS for block.
	 *
	 * @param array $attributes the blocks attributes.
	 * @param Flx_Blocks_CSS $css the css class for blocks.
	 * @param string $unique_id the blocks attr ID.
	 * @param string $unique_style_id the blocks alternate ID for queries.
	 */
	public function build_css( $attributes, $css, $unique_id, $unique_style_id ) {

		$css->set_style_id( 'fxb-' . $this->block_name . $unique_style_id );

		$css->set_selector( '.fxb-table-container tr td.fxb-table-data' . $unique_id . ', .fxb-table-container tr th.fxb-table-data' . $unique_id );
		$css->render_measure_output( $attributes, 'padding', 'padding' );

		return $css->css_output();
	}

	/**
	 * Return dynamically generated HTML for block
	 *
	 * @param $attributes
	 * @param $unique_id
	 * @param $content
	 * @param WP_Block $block_instance The instance of the WP_Block class that represents the block being rendered.
	 *
	 * @return mixed
	 */
	public function build_html( $attributes, $unique_id, $content, $block_instance ) {
		$scope = $this->get_block_header_scope( $attributes, $block_instance->context );
		$tag   = $scope ? 'th' : 'td';

		$scope_attr = $scope ? sprintf( ' scope="%s"', esc_attr( $scope ) ) : '';

		return sprintf(
			'<%1$s %2$s class="fxb-table-data fxb-table-data%3$s">%4$s</%1$s>',
			$tag,
			$scope_attr,
			esc_attr( $unique_id ),
			$content
		);
	}

	/**
	 * Get the header scope for a table data cell.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $context    Block context.
	 *
	 *
	 * @return string|false 'col', 'row', or false when the cell is not a header.
	 */
	private function get_block_header_scope( $attributes, $context ) {
		$is_first_row_header    = ! empty( $context['flx/table/isFirstRowHeader'] );
		$is_first_column_header = ! empty( $context['flx/table/isFirstColumnHeader'] );

		$column = $attributes['column'] ?? -1;
		$row    = $context['flx/table/parentRow'] ?? -1;

		if ( 0 === $row && $is_first_row_header ) {
			/**
			 * Filter the scope for the table data cell.
			 * Scope is 'row', 'col', or false when the cell is not a header.
			 * https://www.w3schools.com/tags/att_th_scope.asp
			 *
			 * @param string $scope The scope.
			 * @param array $attributes The block attributes.
			 * @param array $context The block context.
			 * @return string The scope.
			 * 
			 */
			return apply_filters( 'flxbk_table_data_scope_attributes', 'col', $attributes, $context );
		}

		if ( 0 === $column && $is_first_column_header ) {
			return apply_filters( 'flxbk_table_data_scope_attributes', 'row', $attributes, $context );
		}

		return apply_filters( 'flxbk_table_data_scope_attributes', false, $attributes, $context );
	}

}

Flx_Blocks_Table_Data_Block::get_instance();
