<?php
/**
 * Dile özgü görsel.
 *
 * Medya kütüphanesi dillerle ortaktır. Her görsel için dil başına:
 *  - alt metin ve açıklama (_flxl_alt_<dil>, _flxl_caption_<dil>),
 *  - "bu dilde yerine şu görsel" (_flxl_variant_<dil> = ek ID). Örn. üzerinde Türkçe yazı olan afişin İngilizcesi.
 * Ön yüzde istek dili varsayılan değilse uygulanır: öne çıkan görseller, wp_get_attachment_image ve içerik/şablon görselleri.
 * Çeviri içeriği zaten kendi gövdesindeki görselleri kullanabilir; bu katman ortak şablon ve pattern görselleri için.
 *
 * @package flx-lang
 */

defined( 'ABSPATH' ) || exit;

function flxl_image_variant( $attachment_id, $code = '' ) {
	$code = $code ? $code : flxl_current_language();
	if ( $code === flxl_default_language() ) {
		return 0;
	}
	$v = (int) get_post_meta( (int) $attachment_id, '_flxl_variant_' . $code, true );
	return $v && $v !== (int) $attachment_id && wp_attachment_is_image( $v ) ? $v : 0;
}

function flxl_image_alt( $attachment_id, $code = '' ) {
	$code = $code ? $code : flxl_current_language();
	return (string) get_post_meta( (int) $attachment_id, '_flxl_alt_' . $code, true );
}

function flxl_front_lang_active() {
	return ! flxl_is_backend() && flxl_current_language() !== flxl_default_language();
}

/**
 * wp_get_attachment_image (öne çıkan görsel dahil): varyant + alt.
 */
add_filter(
	'wp_get_attachment_image',
	function ( $html, $id, $size, $icon, $attr ) {
		static $busy = false;
		if ( $busy || ! flxl_front_lang_active() ) {
			return $html;
		}
		$v = flxl_image_variant( $id );
		if ( ! $v ) {
			return $html;
		}
		$busy = true;
		$attr = (array) $attr;
		$alt  = flxl_image_alt( $id );
		if ( '' !== $alt ) {
			$attr['alt'] = $alt;
		}
		$new  = wp_get_attachment_image( $v, $size, $icon, $attr );
		$busy = false;
		return $new ? $new : $html;
	},
	20,
	5
);

add_filter(
	'wp_get_attachment_image_attributes',
	function ( $attr, $attachment ) {
		if ( flxl_front_lang_active() ) {
			$alt = flxl_image_alt( $attachment->ID );
			if ( '' !== $alt ) {
				$attr['alt'] = $alt;
			}
		}
		return $attr;
	},
	20,
	2
);

add_filter(
	'wp_get_attachment_caption',
	function ( $caption, $id ) {
		if ( flxl_front_lang_active() ) {
			$c = (string) get_post_meta( (int) $id, '_flxl_caption_' . flxl_current_language(), true );
			if ( '' !== $c ) {
				return $c;
			}
		}
		return $caption;
	},
	20,
	2
);

/**
 * İçerik ve şablon görselleri (wp-image-<ID> sınıflı img; kapak bloğu dahil).
 */
add_filter(
	'wp_content_img_tag',
	function ( $img, $context, $id ) {
		if ( ! $id || ! flxl_front_lang_active() ) {
			return $img;
		}
		$alt = flxl_image_alt( $id );
		$v   = flxl_image_variant( $id );
		if ( $v ) {
			$size = preg_match( '/\bsize-([a-z0-9_-]+)/i', $img, $m ) ? $m[1] : 'full';
			$src  = wp_get_attachment_image_src( $v, $size );
			if ( $src ) {
				$img = preg_replace( '/\ssrc="[^"]*"/', ' src="' . esc_url( $src[0] ) . '"', $img, 1 );
				$img = preg_replace( '/\s(srcset|sizes)="[^"]*"/', '', $img );
				$set = wp_get_attachment_image_srcset( $v, $size );
				if ( $set ) {
					$img = str_replace( '<img ', '<img srcset="' . esc_attr( $set ) . '" sizes="' . esc_attr( (string) wp_get_attachment_image_sizes( $v, $size ) ) . '" ', $img );
				}
				if ( ! empty( $src[1] ) && ! empty( $src[2] ) ) {
					$img = preg_replace( '/\swidth="\d+"/', ' width="' . (int) $src[1] . '"', $img );
					$img = preg_replace( '/\sheight="\d+"/', ' height="' . (int) $src[2] . '"', $img );
				}
				$img = preg_replace( '/\bwp-image-' . (int) $id . '\b/', 'wp-image-' . (int) $v, $img );
			}
		}
		if ( '' !== $alt ) {
			$img = preg_match( '/\salt="[^"]*"/', $img )
				? preg_replace( '/\salt="[^"]*"/', ' alt="' . esc_attr( $alt ) . '"', $img, 1 )
				: str_replace( '<img ', '<img alt="' . esc_attr( $alt ) . '" ', $img );
		}
		return $img;
	},
	20,
	3
);

/**
 * Kapak (cover) bloğunda arka plan url() olarak verilmişse.
 */
add_filter(
	'render_block_core/cover',
	function ( $html, $block ) {
		$id = (int) ( $block['attrs']['id'] ?? 0 );
		if ( ! $id || ! flxl_front_lang_active() || ! empty( $block['attrs']['useFeaturedImage'] ) ) {
			return $html;
		}
		$v = flxl_image_variant( $id );
		if ( ! $v ) {
			return $html;
		}
		$old = wp_get_attachment_url( $id );
		$new = wp_get_attachment_url( $v );
		return ( $old && $new ) ? str_replace( $old, $new, $html ) : $html;
	},
	20,
	2
);

/* ---------------------------------------------------------------------------
 * Medya düzenleme alanları
 * ------------------------------------------------------------------------- */

add_filter(
	'attachment_fields_to_edit',
	function ( $fields, $post ) {
		if ( ! wp_attachment_is_image( $post ) ) {
			return $fields;
		}
		foreach ( flxl_languages() as $code => $l ) {
			if ( $code === flxl_default_language() ) {
				continue;
			}
			$fields[ 'flxl_alt_' . $code ] = array(
				/* translators: %s: language name */
				'label' => sprintf( __( 'Alternatif metin (%s)', 'flx-lang' ), $l['name'] ),
				'input' => 'text',
				'value' => flxl_image_alt( $post->ID, $code ),
			);
			$v    = (int) get_post_meta( $post->ID, '_flxl_variant_' . $code, true );
			$name = 'attachments[' . $post->ID . '][flxl_variant_' . $code . ']';
			$fields[ 'flxl_variant_' . $code ] = array(
				/* translators: %s: language name */
				'label' => sprintf( __( 'Görsel (%s)', 'flx-lang' ), $l['name'] ),
				'input' => 'html',
				'html'  => '<input type="number" min="0" class="small-text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $v ? (string) $v : '' ) . '"> '
					. ( $v ? wp_get_attachment_image( $v, array( 60, 60 ), false, array( 'style' => 'vertical-align:middle' ) ) : '' ),
				'helps' => __( 'Bu dilde bu görselin yerine gösterilecek görselin ID\'si (ör. üzerinde yazı olan afişin çevirisi). Boş: aynı görsel.', 'flx-lang' ),
			);
		}
		return $fields;
	},
	10,
	2
);

add_filter(
	'attachment_fields_to_save',
	function ( $post, $data ) {
		$id = (int) $post['ID'];
		foreach ( flxl_languages() as $code => $l ) {
			if ( $code === flxl_default_language() ) {
				continue;
			}
			if ( isset( $data[ 'flxl_alt_' . $code ] ) ) {
				flxl_update_or_delete_meta( $id, '_flxl_alt_' . $code, sanitize_text_field( $data[ 'flxl_alt_' . $code ] ) );
			}
			if ( isset( $data[ 'flxl_variant_' . $code ] ) ) {
				$v = absint( $data[ 'flxl_variant_' . $code ] );
				flxl_update_or_delete_meta( $id, '_flxl_variant_' . $code, $v && $v !== $id && wp_attachment_is_image( $v ) ? $v : '' );
			}
		}
		return $post;
	},
	10,
	2
);

function flxl_update_or_delete_meta( $id, $key, $value ) {
	if ( '' === $value || null === $value || 0 === $value ) {
		delete_post_meta( $id, $key );
	} else {
		update_post_meta( $id, $key, $value );
	}
}

/**
 * Görselin dil ayarı (MCP/AI ve REST için).
 *
 * @param array $data alt, caption, variant_id.
 */
function flxl_set_image_language_data( $attachment_id, $code, array $data ) {
	$attachment_id = (int) $attachment_id;
	if ( ! wp_attachment_is_image( $attachment_id ) || ! flxl_is_language( $code ) || $code === flxl_default_language() ) {
		return false;
	}
	if ( array_key_exists( 'alt', $data ) ) {
		flxl_update_or_delete_meta( $attachment_id, '_flxl_alt_' . $code, sanitize_text_field( (string) $data['alt'] ) );
	}
	if ( array_key_exists( 'caption', $data ) ) {
		flxl_update_or_delete_meta( $attachment_id, '_flxl_caption_' . $code, sanitize_text_field( (string) $data['caption'] ) );
	}
	if ( array_key_exists( 'variant_id', $data ) ) {
		$v = absint( $data['variant_id'] );
		flxl_update_or_delete_meta( $attachment_id, '_flxl_variant_' . $code, $v && $v !== $attachment_id && wp_attachment_is_image( $v ) ? $v : '' );
	}
	return true;
}
