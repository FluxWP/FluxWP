<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Core;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * DOM based SVG sanitizer: keeps an allow-list of elements/attributes, drops scripts,
 * event handlers, javascript:/data: links and (optionally) remote references.
 */
class Svg_Sanitizer {

	private const ELEMENTS = [ 'svg', 'g', 'path', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'rect', 'text', 'tspan', 'textpath', 'defs', 'symbol', 'use', 'title', 'desc', 'lineargradient', 'radialgradient', 'stop', 'clippath', 'mask', 'pattern', 'filter', 'fegaussianblur', 'feoffset', 'feblend', 'fecolormatrix', 'fecomposite', 'feflood', 'femerge', 'femergenode', 'femorphology', 'fedropshadow', 'feturbulence', 'fedisplacementmap', 'image', 'marker', 'style', 'switch', 'metadata', 'view' ];

	/** @var string[] */
	private array $attributes = [];

	private bool $remove_remote = false;

	public function removeRemoteReferences( bool $remove ): void {
		$this->remove_remote = $remove;
	}

	/**
	 * @param string|object $allowed Class with static getAttributes() or array.
	 */
	public function setAllowedAttrs( $allowed ): void {
		$list             = is_array( $allowed ) ? $allowed : $allowed::getAttributes();
		$this->attributes = array_map( 'strtolower', $list );
	}

	/**
	 * @return string|false
	 */
	public function sanitize( string $svg ) {
		$svg = trim( $svg );
		if ( '' === $svg || false !== stripos( $svg, '<!ENTITY' ) ) {
			return false;
		}

		$dom      = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$loaded   = $dom->loadXML( $svg, LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded || ! $dom->documentElement || 'svg' !== strtolower( $dom->documentElement->localName ) ) {
			return false;
		}

		$this->clean( $dom->documentElement );

		return $dom->saveXML( $dom->documentElement );
	}

	private function clean( DOMNode $node ): void {
		for ( $i = $node->childNodes->length - 1; $i >= 0; $i-- ) {
			$child = $node->childNodes->item( $i );
			if ( $child instanceof DOMElement ) {
				if ( ! in_array( strtolower( $child->localName ), self::ELEMENTS, true ) ) {
					$node->removeChild( $child );
					continue;
				}
				$this->clean( $child );
			} elseif ( XML_PI_NODE === $child->nodeType ) {
				$node->removeChild( $child );
			}
		}

		if ( ! $node instanceof DOMElement ) {
			return;
		}

		for ( $i = $node->attributes->length - 1; $i >= 0; $i-- ) {
			$attr  = $node->attributes->item( $i );
			$name  = strtolower( $attr->nodeName );
			$local = strtolower( $attr->localName );
			$value = preg_replace( '/[\x00-\x20]+/', '', strtolower( (string) $attr->value ) );

			$allowed = ( $this->attributes ? in_array( $local, $this->attributes, true ) || in_array( $name, $this->attributes, true ) : true )
				|| 'xmlns' === $name || 0 === strpos( $name, 'xmlns:' ) || 'xlink:href' === $name;

			$bad = 0 === strpos( $local, 'on' )
				|| preg_match( '/^(javascript|vbscript|data:(?!image\/(png|jpe?g|gif|webp)))/', $value )
				|| false !== strpos( $value, 'expression(' );

			if ( ! $bad && $this->remove_remote && in_array( $local, [ 'href', 'src' ], true ) && preg_match( '#^(https?:)?//#', $value ) ) {
				$bad = true;
			}

			if ( ! $allowed || $bad ) {
				$node->removeAttributeNode( $attr );
			}
		}
	}
}
