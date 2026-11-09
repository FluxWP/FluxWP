<?php
/** Small, read-only package introductions for the Panel. No provider or download credentials are sent to the browser. */
defined( 'ABSPATH' ) || exit;

final class FLXU_Previews {
	/** Locale preference is native WordPress (including the administrator's profile), independent of Flux Lang. */
	private static function languages(): array {
		$locale = strtolower( str_replace( '_', '-', determine_locale() ) );
		$base   = explode( '-', $locale )[0];
		if ( 'pt' === $base ) { $locale = 'pt-br'; }
		return array_values( array_unique( array( $locale, $base, 'en', 'tr' ) ) );
	}

	private static function text( $texts ): string {
		if ( ! is_array( $texts ) ) { return is_string( $texts ) ? $texts : ''; }
		foreach ( self::languages() as $language ) {
			if ( isset( $texts[ $language ] ) && is_string( $texts[ $language ] ) && '' !== trim( $texts[ $language ] ) ) { return $texts[ $language ]; }
		}
		return '';
	}

	/** HTTPS resources only. Reject URL credentials and script/data URLs even for custom metadata. */
	private static function url( $value ): string {
		$url = is_string( $value ) ? esc_url_raw( $value, array( 'https' ) ) : '';
		$parts = wp_parse_url( $url );
		return is_array( $parts ) && 'https' === ( $parts['scheme'] ?? '' ) && ! empty( $parts['host'] ) && empty( $parts['user'] ) && empty( $parts['pass'] ) ? $url : '';
	}

	/** @return array{name:string,description:string,images:array,website:string} */
	public static function data( string $slug, array $row, array $package ): array {
		$description = self::text( $package['desc'] ?? array() );
		$images = array();
		foreach ( self::languages() as $language ) {
			if ( ! empty( $package['gallery'][ $language ] ) && is_array( $package['gallery'][ $language ] ) ) { $images = $package['gallery'][ $language ]; break; }
		}
		if ( ! $images ) { $remote = self::text( $package['shots'] ?? array() ); if ( '' !== $remote ) { $images[] = $remote; } }
		$website = self::text( $package['website'] ?? array() );

		/** Customize a package's brief preview; return only name, description, images and website. */
		$preview = (array) apply_filters( 'flxu_package_preview', array( 'name' => $row['name'], 'description' => $description, 'images' => $images, 'website' => $website ), $slug, $package );
		$images = array_values( array_unique( array_filter( array_map( array( self::class, 'url' ), (array) ( $preview['images'] ?? array() ) ) ) ) );
		return array(
			'name' => sanitize_text_field( (string) ( $preview['name'] ?? $row['name'] ) ),
			'description' => wp_html_excerpt( wp_strip_all_tags( (string) ( $preview['description'] ?? '' ) ), 420, '…' ),
			'images' => array_slice( $images, 0, 12 ),
			'website' => self::url( $preview['website'] ?? '' ),
		);
	}
}
