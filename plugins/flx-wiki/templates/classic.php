<?php
/**
 * Klasik (blok olmayan) temalar için wiki şablonu.
 *
 * @package FLX_Wiki
 */

defined( 'ABSPATH' ) || exit;

get_header();
echo flxwk_view_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- flxwk_view_html kendi çıktısını kaçışlar.
get_footer();
