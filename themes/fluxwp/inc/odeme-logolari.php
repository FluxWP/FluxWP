<?php
/**
 * Ödeme logoları (0.7.7): footer'daki ödeme/güven logoları Tema Ayarları'ndan seçilir.
 *
 * - Hazır logolar: iyzico bandı (tema) ve eklentilerin eklediği logolar (`flx_pay_logos_builtin` filtresi;
 *   Flux Donate kurulu sanal POS eklentilerinin logolarını ekler, ör. EsnekPOS).
 * - Ortam kütüphanesinden istenen kadar logo yüklenir, kaldırılır, sıralanır.
 * - Hiç değiştirilmediyse otomatik: "default" işaretli hazır logolar (iyzico bandı + etkin POS'lar).
 * - Footer'daki `flx-pay-logos` sınıflı paragraf bu listeyle değiştirilir; başka yerde [flx_odeme_logolari].
 *
 * Filtre: flx_pay_logos_builtin( array $list ) → key => [ label, url, default (bool), chip (bool: açık zemin) ].
 *
 * @package fluxwp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Hazır logolar. */
function flx_pay_logos_builtin() {
	$list = array(
		'iyzico' => array(
			'label'   => __( 'iyzico ile Öde · Mastercard · Visa · American Express · Troy', 'fluxwp' ),
			'url'     => get_theme_file_uri( 'assets/images/odeme/iyzico-logo-band-white.svg' ),
			'default' => true,
			'chip'    => false,
			'band'    => true,
		),
	);
	$list = (array) apply_filters( 'flx_pay_logos_builtin', $list );
	foreach ( $list as $k => $v ) {
		if ( ! is_array( $v ) || empty( $v['url'] ) ) {
			unset( $list[ $k ] );
			continue;
		}
		$list[ $k ] = $v + array( 'label' => (string) $k, 'default' => false, 'chip' => true, 'band' => false );
	}
	return $list;
}

/** Kayıtlı seçim: null = otomatik, dizi = elle seçilmiş anahtarlar ("iyzico", "a:123"). */
function flx_pay_logos_saved() {
	$v = get_option( 'flx_pay_logos', null );
	return is_array( $v ) && isset( $v['list'] ) && is_array( $v['list'] ) ? array_values( $v['list'] ) : null;
}

/**
 * Gösterilecek logolar.
 *
 * @return array<int,array{key:string,label:string,url:string,chip:bool,band:bool}>
 */
function flx_pay_logos() {
	$built = flx_pay_logos_builtin();
	$keys  = flx_pay_logos_saved();
	if ( null === $keys ) {
		$keys = array_keys( array_filter( $built, static fn( $b ) => ! empty( $b['default'] ) ) );
	}
	$out = array();
	foreach ( $keys as $k ) {
		$k = (string) $k;
		if ( preg_match( '/^a:(\d+)$/', $k, $m ) ) {
			$url = wp_get_attachment_image_url( (int) $m[1], 'medium' );
			if ( $url ) {
				$alt   = trim( (string) get_post_meta( (int) $m[1], '_wp_attachment_image_alt', true ) );
				$out[] = array( 'key' => $k, 'label' => '' !== $alt ? $alt : get_the_title( (int) $m[1] ), 'url' => $url, 'chip' => true, 'band' => false );
			}
		} elseif ( isset( $built[ $k ] ) ) {
			$out[] = array( 'key' => $k, 'label' => (string) $built[ $k ]['label'], 'url' => (string) $built[ $k ]['url'], 'chip' => (bool) $built[ $k ]['chip'], 'band' => (bool) $built[ $k ]['band'] );
		}
	}
	return $out;
}

/**
 * Logonun gerçek boyutu (width/height öznitelikleri; yerleşim kaymasını önler, CSS boyutu yine belirler).
 * Yerel dosya (tema, eklenti, uploads) PNG/JPG/WebP ise getimagesize, SVG ise width/height veya viewBox.
 */
function flx_pay_logo_size_attr( $url ) {
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	$base = (string) wp_parse_url( content_url(), PHP_URL_PATH );
	if ( '' === $path || '' === $base || 0 !== strpos( $path, $base . '/' ) ) {
		return '';
	}
	$file = WP_CONTENT_DIR . substr( $path, strlen( $base ) );
	if ( false !== strpos( $file, '..' ) || ! is_file( $file ) ) {
		return '';
	}
	$w = 0;
	$h = 0;
	if ( preg_match( '/\.svg$/i', $file ) ) {
		$svg = (string) file_get_contents( $file, false, null, 0, 2048 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( preg_match( '/<svg\b[^>]*>/i', $svg, $m ) ) {
			if ( preg_match( '/\swidth="([\d.]+)(px)?"/i', $m[0], $a ) && preg_match( '/\sheight="([\d.]+)(px)?"/i', $m[0], $b ) ) {
				$w = (int) round( (float) $a[1] );
				$h = (int) round( (float) $b[1] );
			} elseif ( preg_match( '/\sviewBox="[\d.\-]+[\s,]+[\d.\-]+[\s,]+([\d.]+)[\s,]+([\d.]+)"/i', $m[0], $v ) ) {
				$w = (int) round( (float) $v[1] );
				$h = (int) round( (float) $v[2] );
			}
		}
	} else {
		$size = @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( $size ) {
			$w = (int) $size[0];
			$h = (int) $size[1];
		}
	}
	return $w > 0 && $h > 0 ? ' width="' . $w . '" height="' . $h . '"' : '';
}

function flx_pay_logos_html( $class = '' ) {
	$logos = flx_pay_logos();
	if ( ! $logos ) {
		return '';
	}
	$h = '<div class="flx-pay-logos flx-pay-logos--list' . ( '' !== $class ? ' ' . esc_attr( $class ) : '' ) . '" role="list" aria-label="' . esc_attr__( 'Ödeme yöntemleri', 'fluxwp' ) . '">';
	foreach ( $logos as $l ) {
		$h .= '<span role="listitem" class="flx-pay-logo' . ( $l['chip'] ? ' flx-pay-logo--chip' : '' ) . ( $l['band'] ? ' flx-pay-logo--band' : '' ) . '"><img src="' . esc_url( $l['url'] ) . '" alt="' . esc_attr( $l['label'] ) . '"' . flx_pay_logo_size_attr( $l['url'] ) . ' loading="lazy" decoding="async"></span>';
	}
	return $h . '</div>';
}

/* Footer: "flx-pay-logos" sınıflı paragraf seçili logolarla değişir (boşsa kaldırılır). */
add_filter(
	'render_block',
	function ( $html, $block ) {
		if ( 'core/paragraph' !== ( $block['blockName'] ?? '' ) || false === strpos( (string) ( $block['attrs']['className'] ?? '' ), 'flx-pay-logos' ) ) {
			return $html;
		}
		return flx_pay_logos_html();
	},
	10,
	2
);

add_shortcode(
	'flx_odeme_logolari',
	static function () {
		return flx_pay_logos_html();
	}
);

/* ---------- Tema Ayarları ---------- */

add_action(
	'admin_init',
	function () {
		register_setting(
			'flx_ayarlar',
			'flx_pay_logos',
			array(
				'type'              => 'array',
				'sanitize_callback' => 'flx_pay_logos_sanitize',
				'default'           => null,
			)
		);
	}
);

function flx_pay_logos_sanitize( $in ) {
	if ( ! is_array( $in ) || ! empty( $in['reset'] ) || 'auto' === ( $in['mode'] ?? '' ) ) {
		return null;
	}
	$built = flx_pay_logos_builtin();
	$list  = array();
	$raw   = $in['list'] ?? '';
	$raw   = is_array( $raw ) ? $raw : explode( ',', (string) $raw ); // already sanitised (add_option runs it twice) or from the form
	foreach ( $raw as $k ) {
		$k = trim( (string) $k );
		if ( preg_match( '/^a:(\d+)$/', $k, $m ) && wp_attachment_is_image( (int) $m[1] ) ) {
			$list[] = 'a:' . (int) $m[1];
		} elseif ( '' !== $k && preg_match( '/^[a-z0-9_-]+$/', $k ) && ( isset( $built[ $k ] ) || in_array( $k, (array) flx_pay_logos_saved(), true ) ) ) {
			$list[] = $k; // eklentisi şu an kapalı olsa da seçim korunur
		}
	}
	if ( function_exists( 'flx_purge_page_cache' ) ) {
		flx_purge_page_cache();
	}
	return array( 'list' => array_values( array_unique( $list ) ) );
}

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'appearance_page_flx-ayarlar' === $hook ) {
			wp_enqueue_media();
		}
	}
);

/** Tema Ayarları › Genel formundaki "Ödeme logoları" bölümü. */
function flx_pay_logos_admin() {
	$built = flx_pay_logos_builtin();
	$auto  = null === flx_pay_logos_saved();
	$cur   = flx_pay_logos();
	$data  = array(
		'builtin' => array_map(
			static fn( $k, $b ) => array( 'key' => $k, 'label' => (string) $b['label'], 'url' => (string) $b['url'], 'chip' => (bool) $b['chip'] ),
			array_keys( $built ),
			$built
		),
		'i18n'    => array(
			'remove' => __( 'Kaldır', 'fluxwp' ),
			'left'   => __( 'Sola al', 'fluxwp' ),
			'pick'   => __( 'Ödeme logoları', 'fluxwp' ),
			'use'    => __( 'Logoları ekle', 'fluxwp' ),
			'empty'  => __( 'Logo yok: footer\'da ödeme logosu gösterilmez.', 'fluxwp' ),
		),
	);
	?>
	<h2 class="title" id="flx-odeme-logolari"><?php esc_html_e( 'Ödeme logoları', 'fluxwp' ); ?></h2>
	<p><?php esc_html_e( 'Sanal POS sağlayıcılarının (iyzico, EsnekPOS vb.) sitede görünmesini istediği logolar. Footer\'da gösterilir; başka bir yere [flx_odeme_logolari] kısa koduyla eklenir.', 'fluxwp' ); ?></p>
	<table class="form-table" role="presentation">
		<tr><th scope="row"><?php esc_html_e( 'Logolar', 'fluxwp' ); ?></th><td>
			<input type="hidden" id="flx-pl-list" name="flx_pay_logos[list]" value="<?php echo esc_attr( implode( ',', wp_list_pluck( $cur, 'key' ) ) ); ?>">
			<input type="hidden" id="flx-pl-mode" name="flx_pay_logos[mode]" value="<?php echo $auto ? 'auto' : 'custom'; ?>">
			<div id="flx-pl" class="flx-pl" aria-live="polite"></div>
			<p class="flx-pl-actions">
				<button type="button" class="button" id="flx-pl-upload"><?php esc_html_e( 'Logo yükle', 'fluxwp' ); ?></button>
				<select id="flx-pl-builtin" aria-label="<?php esc_attr_e( 'Hazır logo', 'fluxwp' ); ?>"><option value=""><?php esc_html_e( 'Hazır logo ekle…', 'fluxwp' ); ?></option>
					<?php foreach ( $built as $k => $b ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $b['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<label><input type="checkbox" name="flx_pay_logos[reset]" value="1" id="flx-pl-reset"> <?php esc_html_e( 'Otomatiğe dön', 'fluxwp' ); ?></label>
			</p>
			<p class="description"><?php echo $auto ? esc_html__( 'Şu an otomatik: iyzico bandı ve etkin sanal POS eklentilerinin logoları. Bir logo ekleyip kaldırdığınızda liste sizin seçiminiz olur.', 'fluxwp' ) : esc_html__( 'Elle seçilmiş liste. "Otomatiğe dön" ile iyzico bandı ve etkin sanal POS logolarına döner.', 'fluxwp' ); ?> <?php esc_html_e( 'SVG ya da şeffaf PNG önerilir; renkli logolar koyu footer\'da beyaz kutu içinde gösterilir.', 'fluxwp' ); ?></p>
		</td></tr>
	</table>
	<style>
		.flx-pl{display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:14px;border-radius:10px;background:#1f2b2c;min-height:44px;max-width:720px}
		.flx-pl__item{position:relative;display:inline-flex;align-items:center;gap:6px}
		.flx-pl__item img{display:block;height:24px;width:auto;max-width:342px}
		.flx-pl__item.is-chip img{height:22px;max-width:140px;padding:5px 10px;border-radius:6px;background:#fff}
		.flx-pl__item button{border:0;border-radius:50%;width:22px;height:22px;line-height:20px;padding:0;cursor:pointer;background:#ffffff26;color:#fff;font-size:14px}
		.flx-pl__item button:hover,.flx-pl__item button:focus-visible{background:#d63638}
		.flx-pl__empty{color:#cdd5d6;font-style:italic}
		.flx-pl-actions{display:flex;flex-wrap:wrap;gap:8px 14px;align-items:center}
	</style>
	<script>
	(function(){
		var D=<?php echo wp_json_encode( $data ); ?>,cur=<?php echo wp_json_encode( array_map( static fn( $l ) => array( 'key' => $l['key'], 'label' => $l['label'], 'url' => $l['url'], 'chip' => $l['chip'] ), $cur ) ); ?>;
		var box=document.getElementById('flx-pl'),inp=document.getElementById('flx-pl-list'),mode=document.getElementById('flx-pl-mode'),sel=document.getElementById('flx-pl-builtin'),reset=document.getElementById('flx-pl-reset');
		function draw(){
			box.textContent='';
			if(!cur.length){var e=document.createElement('span');e.className='flx-pl__empty';e.textContent=D.i18n.empty;box.appendChild(e);}
			cur.forEach(function(l,i){
				var s=document.createElement('span');s.className='flx-pl__item'+(l.chip?' is-chip':'');
				var im=document.createElement('img');im.src=l.url;im.alt=l.label;im.title=l.label;s.appendChild(im);
				if(i>0){var u=document.createElement('button');u.type='button';u.textContent='‹';u.setAttribute('aria-label',D.i18n.left+': '+l.label);u.onclick=function(){var t=cur[i-1];cur[i-1]=cur[i];cur[i]=t;changed();};s.appendChild(u);}
				var x=document.createElement('button');x.type='button';x.textContent='×';x.setAttribute('aria-label',D.i18n.remove+': '+l.label);x.onclick=function(){cur.splice(i,1);changed();};s.appendChild(x);
				box.appendChild(s);
			});
			inp.value=cur.map(function(l){return l.key;}).join(',');
		}
		function changed(){mode.value='custom';reset.checked=false;draw();}
		function add(l){if(!cur.some(function(c){return c.key===l.key;}))cur.push(l);changed();}
		sel.onchange=function(){var k=sel.value;sel.value='';D.builtin.forEach(function(b){if(b.key===k)add(b);});};
		var frame;
		document.getElementById('flx-pl-upload').onclick=function(){
			if(!window.wp||!wp.media)return;
			frame=frame||wp.media({title:D.i18n.pick,button:{text:D.i18n.use},library:{type:'image'},multiple:true});
			frame.off('select').on('select',function(){frame.state().get('selection').each(function(a){a=a.toJSON();var u=(a.sizes&&a.sizes.medium?a.sizes.medium.url:a.url);add({key:'a:'+a.id,label:a.alt||a.title||'',url:u,chip:true});});});
			frame.open();
		};
		draw();
	})();
	</script>
	<?php
}
