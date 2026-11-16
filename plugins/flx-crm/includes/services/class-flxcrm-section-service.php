<?php
/**
 * Person-card sections other packages add. Classic pages still print through the
 * flxcrm_person_card_* actions. GraphQL reads flxcrm_register_person_section() and, for a hook nobody
 * has replaced yet, the HTML that hook prints.
 *
 * HTML blocks are not trusted. block() runs kind "html" (including the legacy:<hook> fallback)
 * through wp_kses_post before it leaves this class.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type PersonItem array{label:string,value:string,url:string,meta:string,primary:bool}
 * @phpstan-type PersonRow array{cells:list<string>,url:string,label:string,meta:string}
 * @phpstan-type PersonBlock array{kind:string,text:string,columns:list<string>,items:list<PersonItem>,rows:list<PersonRow>}
 * @phpstan-type PersonSection array{id:string,title:string,placement:string,package:string,blocks:list<PersonBlock>}
 */
final class FLXCRM_Section_Service {

	/** @var array<string,string> */
	private const HOOKS = array(
		'flxcrm_person_card_actions'     => 'actions',
		'flxcrm_person_card_main_before' => 'main_before',
		'flxcrm_person_card_main'        => 'main',
		'flxcrm_person_card_side'        => 'side',
	);

	/**
	 * Sections for one contact.
	 *
	 * @param string $placement Empty = every placement.
	 * @param string $context   Caller context ('' on the CRM card). Passed to resolvers and legacy hooks.
	 * @return list<PersonSection>
	 */
	public static function for_contact( FLXCRM_Contact $contact, string $placement = '', string $context = '' ): array {
		$placement = self::placement( $placement );
		$replaced  = array();
		$out       = array();
		foreach ( self::registered() as $section ) {
			$place = self::placement( FLXCRM_Val::str( $section['placement'] ?? 'side', 'side' ) );
			if ( '' !== $placement && $place !== $placement ) {
				continue;
			}
			if ( ! self::allowed( $section, $contact ) ) {
				continue;
			}
			$hook = FLXCRM_Val::str( $section['replaces'] ?? '' );
			if ( '' !== $hook ) {
				$replaced[ $hook ] = true;
			}
			$built = self::build( $section, $contact, $context, $place );
			if ( null !== $built ) {
				$out[] = $built;
			}
		}
		$row = FLXCRM_Contacts::get( $contact->id );
		if ( ! is_array( $row ) ) {
			return $out;
		}
		foreach ( self::HOOKS as $hook => $place ) {
			if ( '' !== $placement && $place !== $placement ) {
				continue;
			}
			if ( isset( $replaced[ $hook ] ) || ! has_action( $hook ) ) {
				continue;
			}
			$ok  = false;
			$raw = '';
			ob_start();
			try {
				do_action( $hook, $row, $context );
				$ok = true;
			} catch ( Throwable $e ) {
				unset( $e );
			} finally {
				$raw = (string) ob_get_clean();
			}
			if ( ! $ok ) {
				continue;
			}
			$html = trim( $raw );
			if ( '' === $html ) {
				continue;
			}
			$out[] = array(
				'id'        => 'legacy:' . $hook,
				'title'     => '',
				'placement' => $place,
				'package'   => '',
				'blocks'    => array( self::block( array( 'kind' => 'html', 'text' => $html ) ) ),
			);
		}
		return $out;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private static function registered(): array {
		$out = array();
		foreach ( (array) apply_filters( 'flxcrm_gql_person_sections', array() ) as $section ) {
			if ( is_array( $section ) && is_callable( $section['resolve'] ?? null ) ) {
				$out[] = FLXCRM_Val::keyed( $section );
			}
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $section Section.
	 */
	private static function allowed( array $section, FLXCRM_Contact $contact ): bool {
		$cap = $section['cap'] ?? FLXCRM_CAP_VIEW;
		if ( is_string( $cap ) && '' !== $cap ) {
			return current_user_can( 'manage_options' ) || current_user_can( $cap ) || ( FLXCRM_CAP_VIEW === $cap && flxcrm_can() );
		}
		if ( is_callable( $cap ) ) {
			return FLXCRM_Val::bool( call_user_func( $cap, $contact ) );
		}
		return FLXCRM_Val::bool( flxcrm_can() );
	}

	/**
	 * @param array<string,mixed> $section Section.
	 * @return PersonSection|null
	 */
	private static function build( array $section, FLXCRM_Contact $contact, string $context, string $place ): ?array {
		$resolve = $section['resolve'];
		if ( ! is_callable( $resolve ) ) {
			return null;
		}
		try {
			$built = call_user_func( $resolve, $contact, $context );
		} catch ( Throwable $e ) {
			unset( $e );
			return null;
		}
		if ( null === $built || false === $built ) {
			return null;
		}
		$built  = is_array( $built ) && isset( $built['blocks'] ) ? $built : array( 'blocks' => $built );
		$blocks = array();
		foreach ( FLXCRM_Val::arr( $built['blocks'] ?? array() ) as $block ) {
			if ( is_array( $block ) ) {
				$blocks[] = self::block( FLXCRM_Val::keyed( $block ) );
			}
		}
		if ( ! $blocks ) {
			return null;
		}
		$title = array_key_exists( 'title', $built ) ? FLXCRM_Val::str( $built['title'] ) : FLXCRM_Val::str( $section['title'] ?? '' );
		return array(
			'id'        => FLXCRM_Val::str( $section['id'] ?? '' ),
			'title'     => $title,
			'placement' => $place,
			'package'   => FLXCRM_Val::str( $section['package'] ?? '' ),
			'blocks'    => $blocks,
		);
	}

	/**
	 * @param array<string,mixed> $block Block.
	 * @return PersonBlock
	 */
	private static function block( array $block ): array {
		$kind  = FLXCRM_Val::str( $block['kind'] ?? 'note', 'note' );
		$kinds = array( 'stats', 'links', 'list', 'table', 'note', 'html', 'actions' );
		$kind  = in_array( $kind, $kinds, true ) ? $kind : 'note';
		$text  = FLXCRM_Val::str( $block['text'] ?? '' );
		// Packages do not get to put raw HTML on the wire. The registry sanitises; the client does not.
		// wp_kses_post drops the tags but keeps the script's text, so the element is removed first.
		if ( 'html' === $kind ) {
			$text = self::kses_html( $text );
		}
		$items = array();
		foreach ( FLXCRM_Val::arr( $block['items'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$items[] = array(
				'label'   => FLXCRM_Val::str( $item['label'] ?? '' ),
				'value'   => FLXCRM_Val::str( $item['value'] ?? '' ),
				'url'     => self::safe_url( FLXCRM_Val::str( $item['url'] ?? '' ) ),
				'meta'    => FLXCRM_Val::str( $item['meta'] ?? '' ),
				'primary' => ! empty( $item['primary'] ),
			);
		}
		$rows = array();
		foreach ( FLXCRM_Val::arr( $block['rows'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$rows[] = array(
				'cells' => FLXCRM_Val::strings( $row['cells'] ?? array() ),
				'url'   => self::safe_url( FLXCRM_Val::str( $row['url'] ?? '' ) ),
				'label' => FLXCRM_Val::str( $row['label'] ?? '' ),
				'meta'  => FLXCRM_Val::str( $row['meta'] ?? '' ),
			);
		}
		return array(
			'kind'    => $kind,
			'text'    => $text,
			'columns' => FLXCRM_Val::strings( $block['columns'] ?? array() ),
			'items'   => $items,
			'rows'    => $rows,
		);
	}

	private static function safe_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		return esc_url_raw( $url, array( 'http', 'https', 'mailto', 'tel' ) );
	}

	private static function kses_html( string $html ): string {
		$html = preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', '', $html );
		return wp_kses_post( is_string( $html ) ? $html : '' );
	}

	private static function placement( string $placement ): string {
		if ( '' === $placement ) {
			return '';
		}
		return in_array( $placement, self::HOOKS, true ) ? $placement : 'side';
	}
}
