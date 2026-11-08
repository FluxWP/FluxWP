<?php
/**
 * Site denetimi: PageSpeed Insights / Lighthouse kategorilerinin (Performans, Erişilebilirlik, En iyi uygulamalar,
 * SEO, Ajan uyumu) site içinden yapılabilen kontrolleri + isteğe bağlı PageSpeed Insights API ölçümü.
 * Bulgular önceliğe göre sıralanır (critical, high, medium, low); panel, Sistem Kontrolü, MCP ve Flux AI aynı sonucu kullanır.
 *
 * Kayıt: option `flxseo_audit` (autoload kapalı) = array(
 *   local => array( time, url, ms, findings ), psi => array( mobile|desktop => array( time, url, scores, metrics, findings, lighthouse ) ) ).
 * Filtreler: `flxseo_audit_findings` (birleşik liste), `flxseo_audit_local_checks` (yerel bulgular, HTML ve başlıklarla),
 * `flxseo_psi_categories` (PageSpeed kategorileri).
 *
 * @package flx-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FLXSEO_PSI_ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

/** Öncelik sırası (küçük = önemli). */
function flxseo_audit_priorities() {
	return array(
		'critical' => array( 0, __( 'Critical', 'flx-seo' ) ),
		'high'     => array( 1, __( 'High', 'flx-seo' ) ),
		'medium'   => array( 2, __( 'Medium', 'flx-seo' ) ),
		'low'      => array( 3, __( 'Low', 'flx-seo' ) ),
	);
}

function flxseo_audit_categories() {
	return array(
		'performance'    => __( 'Performance', 'flx-seo' ),
		'accessibility'  => __( 'Accessibility', 'flx-seo' ),
		'best-practices' => __( 'Best practices', 'flx-seo' ),
		'seo'            => __( 'SEO', 'flx-seo' ),
		'agentic'        => __( 'Agentic browsing', 'flx-seo' ),
	);
}

/** Düzeltmeyi kimin yapacağı (panelde etiket). */
function flxseo_audit_owners() {
	return array(
		'theme'   => __( 'Theme / preset', 'flx-seo' ),
		'content' => __( 'Content', 'flx-seo' ),
		'server'  => __( 'Server', 'flx-seo' ),
		'flx-seo' => __( 'Flux SEO (one click)', 'flx-seo' ),
		'flx-speed' => __( 'Flux Speed (one click)', 'flx-seo' ),
		'setting' => __( 'WordPress setting', 'flx-seo' ),
	);
}

/**
 * Bilinen bulgular: id => category, title, fix, owner, also (ek kategoriler), fix_id (tek tık düzeltme, flx_system_fixes).
 * Kimlikler Lighthouse denetim kimlikleriyle aynı tutulur; böylece yerel bulgu ile PageSpeed bulgusu birleşir.
 */
function flxseo_audit_catalog() {
	static $c = null;
	if ( null !== $c ) {
		return $c;
	}
	$c = array(
		/* SEO */
		'http-status-code'             => array( 'seo', __( 'Home page does not return a successful status code', 'flx-seo' ), __( 'The page must return 200. It may be maintenance mode, a redirect loop or a PHP error; check System Check and the error log.', 'flx-seo' ), 'server' ),
		'is-crawlable'                 => array( 'seo', __( 'Page is blocked from search engines', 'flx-seo' ), __( 'Untick Settings › Reading › "Discourage search engines from indexing this site" and turn off noindex in the page\'s own SEO panel.', 'flx-seo' ), 'setting' ),
		'robots-txt'                   => array( 'seo', __( 'robots.txt blocks the whole site', 'flx-seo' ), __( 'The robots.txt file in the site root must not have "Disallow: /" under "User-agent: *". Delete or fix the file; WordPress generates its own robots.txt.', 'flx-seo' ), 'server' ),
		'search-bots-blocked'          => array( 'seo', __( 'robots.txt blocks the Bing or Yandex crawler', 'flx-seo' ), __( 'Search crawlers such as Bingbot, YandexBot and DuckDuckBot must not have "Disallow: /"; otherwise the site only appears on Google. Fix the robots.txt file in the site root.', 'flx-seo' ), 'server' ),
		'robots-sitemap'               => array( 'seo', __( 'robots.txt has no Sitemap line', 'flx-seo' ), __( 'Bing and Yandex find the sitemap from the "Sitemap:" line in robots.txt. If a hand-written robots.txt sits in the root, delete it (Flux SEO adds the line) or add the line yourself.', 'flx-seo' ), 'server' ),
		'indexnow'                     => array( 'seo', __( 'IndexNow is off', 'flx-seo' ), __( 'IndexNow instantly notifies Bing, Yandex, Seznam and Naver about new and updated pages; it is the fastest way to show up outside Google. One click to turn it on.', 'flx-seo' ), 'flx-seo', array(), 'flxseo_indexnow' ),
		'indexnow-error'               => array( 'seo', __( 'IndexNow submission failed', 'flx-seo' ), __( 'The key file (/<key>.txt) must be reachable from outside. If a cache or security plugin blocks it, add an exception, then use SEO › General › IndexNow › "Submit the whole site now".', 'flx-seo' ), 'flx-seo' ),
		'search-verify'                => array( 'seo', __( 'No Bing / Yandex verification code', 'flx-seo' ), __( 'The site may not be added to Bing Webmaster Tools and Yandex Webmaster; without that it appears late or not at all there. On Bing, "Import from Google Search Console" needs no code; ignore this if you already imported. The codes are under SEO › General › Search engines.', 'flx-seo' ), 'setting' ),
		'php-unpatched'                => array( 'best-practices', __( 'PHP version is missing a security patch', 'flx-seo' ), __( 'The server\'s PHP is unpatched against a known hole or no longer supported. Upgrade PHP to the current patch release from the hosting panel.', 'flx-seo' ), 'server' ),
		'php-upload-progress'          => array( 'best-practices', __( 'session.upload_progress is on', 'flx-seo' ), __( 'WordPress does not use this PHP feature; it is the exploit path for holes such as PHP2Shell. Set session.upload_progress.enabled = Off in php.ini or the hosting panel.', 'flx-seo' ), 'server' ),
		'document-title'               => array( 'seo', __( 'Page has no <title>', 'flx-seo' ), __( 'The theme must declare add_theme_support( \'title-tag\' ); fill in the home title in SEO › General.', 'flx-seo' ), 'theme', array( 'accessibility' ) ),
		'title-length'                 => array( 'seo', __( 'Page title is long', 'flx-seo' ), __( 'Keep the title around 60 characters; Google cuts the rest.', 'flx-seo' ), 'content' ),
		'meta-description'             => array( 'seo', __( 'No meta description', 'flx-seo' ), __( 'Turn on SEO output and write the home description (150–160 characters).', 'flx-seo' ), 'flx-seo' ),
		'canonical'                    => array( 'seo', __( 'Canonical link missing or duplicated', 'flx-seo' ), __( 'Each page needs exactly one rel=canonical. If another plugin or the theme also adds one, turn one off.', 'flx-seo' ), 'theme' ),
		'hreflang'                     => array( 'seo', __( 'No hreflang on a multilingual site', 'flx-seo' ), __( 'With Flux Lang active, language versions must be linked with hreflang; check the language pairs in Flux Lang settings.', 'flx-seo' ), 'theme' ),
		'heading-h1'                   => array( 'seo', __( 'Main heading (H1) missing or repeated', 'flx-seo' ), __( 'Each page needs one H1 that names the topic; subheadings are H2/H3.', 'flx-seo' ), 'content', array( 'accessibility' ) ),
		'structured-data'              => array( 'seo', __( 'Structured data (JSON-LD) missing or broken', 'flx-seo' ), __( 'With Flux SEO output on, Organization, WebSite and Breadcrumb schema are added. Find the block or plugin that outputs broken JSON-LD.', 'flx-seo' ), 'flx-seo', array( 'agentic' ) ),
		'link-text'                    => array( 'seo', __( 'Link texts are not descriptive', 'flx-seo' ), __( 'Write text that names the target instead of "click here", "more", "here" (e.g. "See donation options").', 'flx-seo' ), 'content', array( 'accessibility', 'agentic' ) ),
		'duplicate-titles'             => array( 'seo', __( 'More than one post with the same title', 'flx-seo' ), __( 'Delete the duplicate (usually the address ending in -2) or redirect it to the original; give different titles if both are needed. When links with the same text go to different addresses, the "Identical links have the same purpose" audit also fails.', 'flx-seo' ), 'content', array( 'accessibility' ) ),
		'generator'                    => array( 'best-practices', __( 'WordPress version is visible on the page', 'flx-seo' ), __( 'The meta generator tag reveals the version. The theme should call remove_action( \'wp_head\', \'wp_generator\' ); Flux SEO removes it when security headers are on.', 'flx-seo' ), 'flx-seo', array(), 'flxseo_headers' ),

		/* Erişilebilirlik */
		'meta-viewport'                => array( 'accessibility', __( 'Zoom is blocked (user-scalable=no / maximum-scale)', 'flx-seo' ), __( 'Remove user-scalable=no and maximum-scale=1 from the viewport tag: <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">. Users with low vision cannot zoom; the Accessibility score drops directly.', 'flx-seo' ), 'theme' ),
		'viewport'                     => array( 'performance', __( 'No viewport tag', 'flx-seo' ), __( 'The theme must add <meta name="viewport" content="width=device-width, initial-scale=1"> to <head>; otherwise the page opens at desktop width on mobile.', 'flx-seo' ), 'theme', array( 'seo' ) ),
		'aria-required-children'       => array( 'accessibility', __( 'ARIA role is missing required children', 'flx-seo' ), __( 'E.g. role="tablist" may only contain role="tab" elements. Use <div role="tablist"><button role="tab"> instead of <ol role="tablist"><li><button role="tab">, or give the li elements role="presentation". The accessibility tree breaks and Lighthouse also lowers the Agentic Browsing score.', 'flx-seo' ), 'theme', array( 'agentic' ) ),
		'aria-required-parent'         => array( 'accessibility', __( 'ARIA role is not inside its required parent', 'flx-seo' ), __( 'role="tab" must sit directly inside role="tablist", role="option" inside role="listbox". Remove the li/div elements in between or give them role="presentation".', 'flx-seo' ), 'theme', array( 'agentic' ) ),
		'listitem'                     => array( 'accessibility', __( 'List items (<li>) are not inside a list', 'flx-seo' ), __( '<li> may only sit inside <ul>, <ol> or <menu>, and the parent list must not get a non-list role (e.g. <ol role="tablist">).', 'flx-seo' ), 'theme' ),
		'image-alt'                    => array( 'accessibility', __( 'Images without alt text', 'flx-seo' ), __( 'Give every meaningful image alt text that describes it; decorative images get alt="". Fill in alt text in the media library or turn on SEO › General › Images.', 'flx-seo' ), 'content', array( 'seo', 'agentic' ) ),
		'link-name'                    => array( 'accessibility', __( 'Links without a name', 'flx-seo' ), __( 'Add aria-label or hidden text (screen-reader-text) to icon-only links.', 'flx-seo' ), 'theme', array( 'agentic' ) ),
		'button-name'                  => array( 'accessibility', __( 'Buttons without a name', 'flx-seo' ), __( 'Give icon buttons an aria-label (e.g. "Open menu"). Agents and screen readers cannot tell what the button does.', 'flx-seo' ), 'theme', array( 'agentic' ) ),
		'label'                        => array( 'accessibility', __( 'Form fields without a label', 'flx-seo' ), __( 'Each field needs a name through <label for> or aria-label; a placeholder is not a label.', 'flx-seo' ), 'theme', array( 'agentic' ) ),
		'html-has-lang'                => array( 'accessibility', __( '<html> has no lang attribute', 'flx-seo' ), __( 'The theme should use <html <?php language_attributes(); ?>>.', 'flx-seo' ), 'theme', array( 'seo' ) ),
		'duplicate-id-aria'            => array( 'accessibility', __( 'Duplicate or missing id references', 'flx-seo' ), __( 'No id may be used twice; aria-controls, aria-labelledby and label for must point to an existing id.', 'flx-seo' ), 'theme', array( 'agentic' ) ),
		'heading-order'                => array( 'accessibility', __( 'Heading levels skip', 'flx-seo' ), __( 'Headings should step down in order (H2 → H3); do not skip levels for looks, size them with CSS.', 'flx-seo' ), 'content' ),
		'tabindex'                     => array( 'accessibility', __( 'tabindex greater than 0', 'flx-seo' ), __( 'Values like tabindex="1" break keyboard order; use 0 or -1.', 'flx-seo' ), 'theme' ),
		'identical-links-same-purpose' => array( 'accessibility', __( 'Links with the same text go to different addresses', 'flx-seo' ), __( 'Links with the same name should go to the same page. Remove the duplicate page or make the link texts different.', 'flx-seo' ), 'content', array( 'seo' ) ),
		'frame-title'                  => array( 'accessibility', __( 'iframe without a title', 'flx-seo' ), __( 'Every <iframe> needs a title (e.g. "Location map").', 'flx-seo' ), 'content' ),
		'landmark-one-main'            => array( 'accessibility', __( 'No <main> landmark on the page', 'flx-seo' ), __( 'Main content must be inside <main>; screen readers and agents find the main content this way.', 'flx-seo' ), 'theme', array( 'agentic' ) ),
		'color-contrast'               => array( 'accessibility', __( 'Insufficient text/background contrast', 'flx-seo' ), __( 'At least 4.5:1 for normal text and 3:1 for large text. Darken the pale colors in the theme palette.', 'flx-seo' ), 'theme' ),

		/* Performans */
		'render-blocking-insight'      => array( 'performance', __( 'Render-blocking requests', 'flx-seo' ), __( 'Inline the critical CSS for the first screen, load the rest with media="print" onload or at the end of the page, and defer JS. LiteSpeed Cache › Page Optimization › CSS/JS settings, or the theme\'s own combined CSS.', 'flx-seo' ), 'theme' ),
		'unused-css-rules'             => array( 'performance', __( 'Unused CSS', 'flx-seo' ), __( 'Split the one large CSS file by page type, or drop unused rules (LiteSpeed "Remove Unused CSS"). The gain usually shows up in LCP.', 'flx-seo' ), 'theme' ),
		'unminified-javascript'        => array( 'performance', __( 'Unminified JavaScript', 'flx-seo' ), __( 'Ship .min versions of theme/plugin JS or turn on LiteSpeed "JS Minify".', 'flx-seo' ), 'theme' ),
		'unminified-css'               => array( 'performance', __( 'Unminified CSS', 'flx-seo' ), __( 'Minify CSS (LiteSpeed "CSS Minify" or a build step).', 'flx-seo' ), 'theme' ),
		'non-composited-animations'    => array( 'performance', __( 'Non-composited animations', 'flx-seo' ), __( 'Animate with transform and opacity; properties like stroke-dashoffset, top/left and width repaint on every frame. Stop them under prefers-reduced-motion.', 'flx-seo' ), 'theme' ),
		'dom-size-insight'             => array( 'performance', __( 'DOM is too large', 'flx-seo' ), __( 'Aim for under 1,500 elements. Reduce copies in marquees, build hidden menus and long lists on demand, simplify nested wrappers.', 'flx-seo' ), 'theme' ),
		'lcp-breakdown-insight'        => array( 'performance', __( 'Largest content (LCP) paints late', 'flx-seo' ), __( 'If the LCP element is text, preload its font, use font-display: swap and do not hide it behind an entrance animation on the first frame; if it is an image, give it fetchpriority="high" and do not lazy load it.', 'flx-seo' ), 'theme' ),
		'cls-culprits-insight'         => array( 'performance', __( 'Layout shift (CLS) culprits', 'flx-seo' ), __( 'Give images width/height and late blocks a fixed height; use size-adjust/fallback metrics for web fonts; absolutely positioned animated layers must not push the layout.', 'flx-seo' ), 'theme', array( 'agentic' ) ),
		'font-display-insight'         => array( 'performance', __( 'Fonts delay text', 'flx-seo' ), __( 'Use font-display: swap in @font-face, preload the 1-2 fonts used on the first screen with <link rel="preload" as="font" crossorigin>, remove unused weights.', 'flx-seo' ), 'theme' ),
		'network-dependency-tree-insight' => array( 'performance', __( 'Critical request chain is long', 'flx-seo' ), __( 'Fonts loaded from CSS lengthen the chain; preload first-screen fonts and defer unneeded requests in the chain (e.g. guest.vary.php).', 'flx-seo' ), 'theme' ),
		'forced-reflow-insight'        => array( 'performance', __( 'Forced reflow', 'flx-seo' ), __( 'Do not read offsetWidth/getBoundingClientRect after changing styles in JS; read first, then write inside requestAnimationFrame.', 'flx-seo' ), 'theme' ),
		'long-tasks'                   => array( 'performance', __( 'Long main-thread tasks', 'flx-seo' ), __( 'Split JS that runs at startup and defer work for off-screen sections with IntersectionObserver.', 'flx-seo' ), 'theme' ),
		'image-delivery-insight'       => array( 'performance', __( 'Images are large or in an old format', 'flx-seo' ), __( 'Use WebP/AVIF, never send images larger than displayed (srcset), turn on image compression.', 'flx-seo' ), 'content' ),
		'unsized-images'               => array( 'performance', __( 'Images without width/height', 'flx-seo' ), __( 'Give <img> tags width and height; the browser reserves space and the page does not shift.', 'flx-seo' ), 'theme', array( 'agentic' ) ),
		'lcp-lazy-loaded'              => array( 'performance', __( 'First-screen image is lazy loaded', 'flx-seo' ), __( 'Do not give the page\'s first image loading="lazy"; give it fetchpriority="high". If LiteSpeed lazy load is on, exclude the first image (data-no-lazy or "Lazy Load Image Excludes").', 'flx-seo' ), 'theme' ),
		'server-response-time'         => array( 'performance', __( 'Slow server response', 'flx-seo' ), __( 'Turn on page cache (LiteSpeed Cache); check slow plugins and the cron report in Flux Speed.', 'flx-seo' ), 'server' ),
		'uses-text-compression'        => array( 'performance', __( 'No text compression (gzip/brotli)', 'flx-seo' ), __( 'Turn on gzip or brotli on the server (on by default on Hostinger/LiteSpeed; check the CDN settings).', 'flx-seo' ), 'server' ),
		'document-size'                => array( 'performance', __( 'HTML document is large', 'flx-seo' ), __( 'Move inline SVG icons into a sprite and large inline CSS/JSON into files.', 'flx-seo' ), 'theme' ),
		'emoji-script'                 => array( 'performance', __( 'WordPress emoji script is loaded', 'flx-seo' ), __( 'Modern browsers draw emoji themselves; the theme can remove print_emoji_detection_script.', 'flx-seo' ), 'theme' ),
		'largest-contentful-paint'     => array( 'performance', __( 'Largest Contentful Paint (LCP) is slow', 'flx-seo' ), __( 'Target under 2.5 s. Reduce render-blocking CSS, preload the LCP font/image, turn on the server cache.', 'flx-seo' ), 'theme' ),
		'cumulative-layout-shift'      => array( 'performance', __( 'Cumulative Layout Shift (CLS) is high', 'flx-seo' ), __( 'Target under 0.1. Size images, prevent font shifts, reserve space for elements added later.', 'flx-seo' ), 'theme', array( 'agentic' ) ),
		'total-blocking-time'          => array( 'performance', __( 'Total Blocking Time (TBT) is high', 'flx-seo' ), __( 'Target under 200 ms. Reduce startup JS and defer third-party scripts.', 'flx-seo' ), 'theme' ),
		'first-contentful-paint'       => array( 'performance', __( 'First Contentful Paint (FCP) is slow', 'flx-seo' ), __( 'Target under 1.8 s. Reduce render-blocking CSS/JS and server response time.', 'flx-seo' ), 'theme' ),
		'speed-index'                  => array( 'performance', __( 'Speed Index is slow', 'flx-seo' ), __( 'Prioritize critical CSS and fonts so the first screen fills quickly; shorten entrance animations.', 'flx-seo' ), 'theme' ),

		/* En iyi uygulamalar / güvenlik */
		'is-on-https'                  => array( 'best-practices', __( 'Site is not HTTPS or has mixed content', 'flx-seo' ), __( 'Set the addresses to https:// in Settings › General and fix images/scripts loaded over http://.', 'flx-seo' ), 'setting', array( 'seo' ) ),
		'has-hsts'                     => array( 'best-practices', __( 'Weak HSTS policy', 'flx-seo' ), __( 'Strict-Transport-Security: max-age=31536000; includeSubDomains. If every subdomain is HTTPS, you can also add "preload" and submit to hstspreload.org.', 'flx-seo' ), 'flx-seo', array(), 'flxseo_hsts' ),
		'csp-xss'                      => array( 'best-practices', __( 'No or incomplete Content Security Policy (CSP)', 'flx-seo' ), __( 'Flux SEO can add a basic CSP (object-src \'none\'; base-uri \'self\'; frame-ancestors \'self\'). A full script-src policy can break plugins on WordPress; try Content-Security-Policy-Report-Only first.', 'flx-seo' ), 'flx-seo', array(), 'flxseo_headers' ),
		'clickjacking-mitigation'      => array( 'best-practices', __( 'No clickjacking protection', 'flx-seo' ), __( 'Send X-Frame-Options: SAMEORIGIN and CSP frame-ancestors \'self\'. Do not turn on if your site is embedded in other sites with an iframe.', 'flx-seo' ), 'flx-seo', array(), 'flxseo_headers' ),
		'origin-isolation'             => array( 'best-practices', __( 'No origin isolation (COOP)', 'flx-seo' ), __( 'Send Cross-Origin-Opener-Policy: same-origin-allow-popups; payment and login popups keep working.', 'flx-seo' ), 'flx-seo', array(), 'flxseo_headers' ),
		'trusted-types-xss'            => array( 'best-practices', __( 'Trusted Types not set', 'flx-seo' ), __( 'Advanced protection against DOM-based XSS. Most WordPress plugins are not compatible yet; try it with Report-Only only on custom-theme sites with few plugins.', 'flx-seo' ), 'server' ),
		'x-content-type-options'       => array( 'best-practices', __( 'No X-Content-Type-Options header', 'flx-seo' ), __( 'X-Content-Type-Options: nosniff. Flux SEO sends it by default; the server or a cache plugin may be removing it.', 'flx-seo' ), 'server' ),
		'doctype'                      => array( 'best-practices', __( 'HTML doctype or charset missing', 'flx-seo' ), __( 'The page must start with <!DOCTYPE html> and have <meta charset="UTF-8"> in the first lines.', 'flx-seo' ), 'theme' ),

		/* Ajan uyumu */
		'llms-txt'                     => array( 'agentic', __( 'No llms.txt', 'flx-seo' ), __( 'AI agents summarize a site through /llms.txt. Flux SEO builds it from the site name, description, menu pages and recent content; one click to turn it on.', 'flx-seo' ), 'flx-seo', array(), 'flxseo_llms' ),
		'llms-txt-format'              => array( 'agentic', __( 'llms.txt is not in the recommended format', 'flx-seo' ), __( 'The first line must be "# Site name", then "> short summary" and link lists in [title](url) form. If an old hand-made file sits in the root, delete it; Flux SEO generates one.', 'flx-seo' ), 'flx-seo', array(), 'flxseo_llms' ),
		'webmcp-forms'                 => array( 'agentic', __( 'Forms are not declared as WebMCP tools', 'flx-seo' ), __( 'WebMCP lets browser agents call forms as tools (form toolname/tooldescription). Flux SEO adds it to the search form; add it to your own forms too.', 'flx-seo' ), 'flx-seo', array(), 'flxseo_webmcp' ),
		'ai-crawlers-blocked'          => array( 'agentic', __( 'robots.txt blocks AI crawlers', 'flx-seo' ), __( 'If crawlers like GPTBot, ClaudeBot, Google-Extended, PerplexityBot are blocked, your content does not show up in AI answers. Remove them from robots.txt unless you block them on purpose.', 'flx-seo' ), 'server' ),
	);
	return $c;
}

/**
 * Bulgu nesnesi. Katalogdaki metinler kullanılır; $extra ile detail, items, priority ve title ezilebilir.
 */
function flxseo_audit_finding( $id, $priority, $items = array(), $detail = '', $source = 'local' ) {
	$cat = flxseo_audit_catalog()[ $id ] ?? null;
	return array(
		'id'       => $id,
		'category' => $cat ? $cat[0] : 'seo',
		'also'     => $cat[4] ?? array(),
		'priority' => $priority,
		'title'    => $cat ? $cat[1] : $id,
		'detail'   => $detail,
		'fix'      => $cat ? $cat[2] : '',
		'owner'    => $cat ? $cat[3] : '',
		'fix_id'   => $cat[5] ?? '',
		'items'    => array_slice( array_values( array_unique( array_filter( array_map( 'strval', (array) $items ) ) ) ), 0, 8 ),
		'sources'  => array( $source ),
	);
}

/* ---------------------------------------------------------------------------
 * Yerel denetim
 * ------------------------------------------------------------------------- */

/** Sayfayı sunucu içinden ister (önbellek atlanmaz; ziyaretçinin gördüğü sayfa). */
function flxseo_audit_fetch( $url ) {
	$t0 = microtime( true );
	$r  = wp_remote_get(
		$url,
		array(
			'timeout'     => 20,
			'redirection' => 3,
			'sslverify'   => false,
			'user-agent'  => 'Mozilla/5.0 (compatible; FluxSEO-Audit/' . FLXSEO_VERSION . '; +' . home_url( '/' ) . ')',
			'headers'     => array( 'Accept' => 'text/html', 'Accept-Encoding' => 'gzip, deflate' ),
			'decompress'  => true,
		)
	);
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$headers = array();
	foreach ( (array) wp_remote_retrieve_headers( $r )->getAll() as $k => $v ) {
		$headers[ strtolower( $k ) ] = is_array( $v ) ? implode( ', ', $v ) : (string) $v;
	}
	return array(
		'code'    => (int) wp_remote_retrieve_response_code( $r ),
		'headers' => $headers,
		'body'    => (string) wp_remote_retrieve_body( $r ),
		'ms'      => (int) round( ( microtime( true ) - $t0 ) * 1000 ),
	);
}

/** Öğenin açılış etiketi (kısaltılmış), panelde ve MCP'de gösterilir. */
function flxseo_audit_snippet( DOMElement $el, $text = true ) {
	$s = '<' . $el->nodeName;
	foreach ( $el->attributes as $a ) {
		$v  = mb_substr( $a->value, 0, 60 );
		$s .= ' ' . $a->name . ( '' === $a->value ? '' : '="' . $v . '"' );
	}
	$s .= '>';
	if ( $text ) {
		$t = trim( preg_replace( '/\s+/u', ' ', $el->textContent ) );
		if ( '' !== $t ) {
			$s .= ' ' . mb_substr( $t, 0, 60 );
		}
	}
	return mb_substr( $s, 0, 220 );
}

/** Açık ya da örtük ARIA rolü. Rolsüz kap (div, span…) için ''. */
function flxseo_audit_role( DOMElement $el ) {
	$r = trim( strtolower( $el->getAttribute( 'role' ) ) );
	if ( '' !== $r ) {
		return strtok( $r, ' ' );
	}
	$map = array(
		'li'       => 'listitem',
		'ul'       => 'list',
		'ol'       => 'list',
		'menu'     => 'list',
		'button'   => 'button',
		'nav'      => 'navigation',
		'main'     => 'main',
		'table'    => 'table',
		'tr'       => 'row',
		'td'       => 'cell',
		'th'       => 'columnheader',
		'option'   => 'option',
		'select'   => 'combobox',
		'textarea' => 'textbox',
		'img'      => 'img',
		'h1'       => 'heading',
		'h2'       => 'heading',
		'h3'       => 'heading',
		'h4'       => 'heading',
		'h5'       => 'heading',
		'h6'       => 'heading',
		'p'        => 'paragraph',
		'article'  => 'article',
		'form'     => 'form',
	);
	$n = strtolower( $el->nodeName );
	if ( 'a' === $n ) {
		return $el->hasAttribute( 'href' ) ? 'link' : '';
	}
	if ( 'input' === $n ) {
		return 'hidden' === strtolower( $el->getAttribute( 'type' ) ) ? 'none' : 'textbox';
	}
	return $map[ $n ] ?? '';
}

/** Ad hesabı (kaba): görünür metin, aria-label, aria-labelledby, title, alt. */
function flxseo_audit_has_name( DOMElement $el, DOMXPath $xp ) {
	if ( '' !== trim( $el->getAttribute( 'aria-label' ) ) || '' !== trim( $el->getAttribute( 'title' ) ) ) {
		return true;
	}
	foreach ( preg_split( '/\s+/', trim( $el->getAttribute( 'aria-labelledby' ) ) ) as $id ) {
		if ( $id && $xp->query( '//*[@id="' . str_replace( '"', '', $id ) . '"]' )->length ) {
			return true;
		}
	}
	if ( '' !== trim( preg_replace( '/[\s\x{00a0}\x{200b}]+/u', '', $el->textContent ) ) ) {
		return true;
	}
	if ( $xp->query( './/img[normalize-space(@alt)!=""] | .//svg[title] | .//*[@aria-label!=""]', $el )->length ) {
		return true;
	}
	if ( 'input' === strtolower( $el->nodeName ) && '' !== trim( $el->getAttribute( 'value' ) ) ) {
		return true;
	}
	return false;
}

/** Erişilebilirlik ağacında bir öğenin "sahip olduğu" alt rolleri (rolsüz kaplar atlanır). */
function flxseo_audit_owned_roles( DOMElement $el, $depth = 0 ) {
	$roles = array();
	foreach ( $el->childNodes as $ch ) {
		if ( ! $ch instanceof DOMElement || in_array( strtolower( $ch->nodeName ), array( 'script', 'template', 'style' ), true ) || 'true' === $ch->getAttribute( 'aria-hidden' ) ) {
			continue;
		}
		$r = flxseo_audit_role( $ch );
		if ( '' === $r || in_array( $r, array( 'none', 'presentation', 'generic' ), true ) ) {
			if ( $depth < 6 ) {
				$roles = array_merge( $roles, flxseo_audit_owned_roles( $ch, $depth + 1 ) );
			}
			continue;
		}
		$roles[] = array( $r, $ch );
	}
	return $roles;
}

/** En yakın rollü üst öğe. */
function flxseo_audit_parent_role( DOMElement $el ) {
	$p = $el->parentNode;
	while ( $p instanceof DOMElement ) {
		$r = flxseo_audit_role( $p );
		if ( '' !== $r && ! in_array( $r, array( 'none', 'presentation', 'generic' ), true ) ) {
			return $r;
		}
		$p = $p->parentNode;
	}
	return '';
}

/**
 * HTML ve yanıt başlıklarından bulgular.
 *
 * @param array $page flxseo_audit_fetch sonucu.
 */
/**
 * Görsel lazy mi yükleniyor? loading="lazy" ya da lazy load eklentisi işareti (lazy/lazyload sınıfı, data-src).
 * "skip-lazy", "no-lazyload" sınıfları ve data-no-lazy hariç tutma işaretidir, lazy sayılmaz.
 */
function flxseo_audit_img_lazy( DOMElement $img ) {
	if ( 'lazy' === strtolower( $img->getAttribute( 'loading' ) ) ) {
		return true;
	}
	$cls = preg_split( '/\s+/', strtolower( $img->getAttribute( 'class' ) ), -1, PREG_SPLIT_NO_EMPTY );
	if ( $img->hasAttribute( 'data-no-lazy' ) || array_intersect( $cls, array( 'skip-lazy', 'no-lazy', 'no-lazyload', 'nolazy' ) ) ) {
		return false;
	}
	return (bool) array_intersect( $cls, array( 'lazy', 'lazyload', 'lazyloading', 'litespeed-lazy', 'lazyloaded' ) ) || $img->hasAttribute( 'data-src' ) || $img->hasAttribute( 'data-lazy-src' );
}

function flxseo_audit_analyze( array $page, $url ) {
	$f    = array();
	$html = $page['body'];
	$h    = $page['headers'];
	$add  = function ( $id, $prio, $items = array(), $detail = '' ) use ( &$f ) {
		$f[] = flxseo_audit_finding( $id, $prio, $items, $detail );
	};

	if ( 200 !== $page['code'] ) {
		/* translators: %d: HTTP durum kodu */
		$add( 'http-status-code', 'critical', array(), sprintf( __( 'Returned HTTP %d.', 'flx-seo' ), $page['code'] ) );
		if ( '' === $html ) {
			return $f;
		}
	}

	libxml_use_internal_errors( true );
	$doc = new DOMDocument();
	$doc->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_COMPACT );
	libxml_clear_errors();
	$xp = new DOMXPath( $doc );
	// <template> içeriği tarayıcıda çizilmez (ör. WooCommerce mini sepet şablonları); denetime girmesin.
	foreach ( iterator_to_array( $xp->query( '//template' ) ) as $tpl ) {
		if ( $tpl->parentNode ) {
			$tpl->parentNode->removeChild( $tpl );
		}
	}

	/* --- SEO --- */
	$robots_meta = strtolower( (string) ( $xp->evaluate( 'string(//meta[translate(@name,"ROBTS","robts")="robots"]/@content)' ) ) );
	if ( ! get_option( 'blog_public' ) ) {
		$add( 'is-crawlable', 'critical', array( __( 'Settings › Reading: search engines discouraged', 'flx-seo' ) ) );
	} elseif ( false !== strpos( $robots_meta, 'noindex' ) || false !== stripos( $h['x-robots-tag'] ?? '', 'noindex' ) ) {
		$add( 'is-crawlable', 'critical', array( 'robots: ' . $robots_meta . ' ' . ( $h['x-robots-tag'] ?? '' ) ) );
	}
	$title = trim( (string) $xp->evaluate( 'string(//head/title)' ) );
	if ( '' === $title ) {
		$add( 'document-title', 'critical' );
	} elseif ( mb_strlen( $title ) > 65 ) {
		/* translators: %d: karakter sayısı */
		$add( 'title-length', 'low', array( $title ), sprintf( __( '%d characters.', 'flx-seo' ), mb_strlen( $title ) ) );
	}
	if ( '' === trim( (string) $xp->evaluate( 'string(//meta[translate(@name,"DESCRIPTION","description")="description"]/@content)' ) ) ) {
		$add( 'meta-description', 'high' );
	}
	$canon = $xp->query( '//link[translate(@rel,"CANONICAL","canonical")="canonical"]' );
	if ( 0 === $canon->length ) {
		$add( 'canonical', 'medium' );
	} elseif ( $canon->length > 1 ) {
		$items = array();
		foreach ( $canon as $c ) {
			$items[] = $c->getAttribute( 'href' );
		}
		$add( 'canonical', 'high', $items );
	}
	if ( function_exists( 'flxl_languages' ) || defined( 'FLXL_VERSION' ) ) {
		if ( 0 === $xp->query( '//link[@hreflang]' )->length ) {
			$add( 'hreflang', 'medium' );
		}
	}
	$h1 = $xp->query( '//h1' );
	if ( 0 === $h1->length ) {
		$add( 'heading-h1', 'medium' );
	} elseif ( $h1->length > 1 ) {
		$items = array();
		foreach ( $h1 as $e ) {
			$items[] = flxseo_audit_snippet( $e );
		}
		/* translators: %d: H1 sayısı */
		$add( 'heading-h1', 'low', $items, sprintf( __( '%d H1 headings.', 'flx-seo' ), $h1->length ) );
	}
	$ld_ok = 0;
	$ld_bad = array();
	foreach ( $xp->query( '//script[@type="application/ld+json"]' ) as $s ) {
		json_decode( trim( $s->textContent ) );
		if ( JSON_ERROR_NONE === json_last_error() ) {
			++$ld_ok;
		} else {
			$ld_bad[] = mb_substr( trim( $s->textContent ), 0, 120 );
		}
	}
	if ( $ld_bad ) {
		$add( 'structured-data', 'high', $ld_bad, __( 'JSON-LD could not be parsed.', 'flx-seo' ) );
	} elseif ( ! $ld_ok ) {
		$add( 'structured-data', 'medium' );
	}
	if ( preg_match( '/<meta[^>]+name=["\']generator["\'][^>]+WordPress\s*[\d.]+/i', $html, $m ) ) {
		$add( 'generator', 'low', array( $m[0] ) );
	}

	/* --- Bağlantılar: ad, açıklayıcı metin, aynı metin farklı hedef --- */
	$generic  = array( 'tıklayın', 'tıkla', 'buraya', 'burada', 'devamı', 'devamını oku', 'daha fazla', 'click here', 'here', 'read more', 'more', 'learn more', 'link' );
	$noname   = array();
	$vague    = array();
	$by_text  = array();
	$home     = untrailingslashit( home_url() );
	foreach ( $xp->query( '//a[@href]' ) as $a ) {
		if ( 'true' === $a->getAttribute( 'aria-hidden' ) ) {
			continue;
		}
		if ( ! flxseo_audit_has_name( $a, $xp ) ) {
			$noname[] = flxseo_audit_snippet( $a, false );
			continue;
		}
		$text = mb_strtolower( trim( preg_replace( '/\s+/u', ' ', '' !== trim( $a->getAttribute( 'aria-label' ) ) ? $a->getAttribute( 'aria-label' ) : $a->textContent ) ) );
		if ( in_array( trim( $text, ' .…›»→' ), $generic, true ) ) {
			$vague[] = flxseo_audit_snippet( $a );
		}
		$href = $a->getAttribute( 'href' );
		if ( '' === $href || '#' === $href[0] || 0 === stripos( $href, 'javascript:' ) || 0 === stripos( $href, 'mailto:' ) || 0 === stripos( $href, 'tel:' ) || mb_strlen( $text ) < 3 ) {
			continue;
		}
		$norm                      = untrailingslashit( strtok( 0 === strpos( $href, '/' ) && 0 !== strpos( $href, '//' ) ? $home . $href : $href, '#' ) );
		$by_text[ $text ][ $norm ] = $href;
	}
	if ( $noname ) {
		$add( 'link-name', 'high', $noname );
	}
	if ( $vague ) {
		$add( 'link-text', 'medium', $vague );
	}
	$dupe_links = array();
	foreach ( $by_text as $text => $targets ) {
		if ( count( $targets ) > 1 ) {
			$dupe_links[] = mb_substr( $text, 0, 70 ) . ' → ' . implode( ' | ', array_values( $targets ) );
		}
	}
	if ( $dupe_links ) {
		$add( 'identical-links-same-purpose', 'medium', $dupe_links );
	}

	/* --- Erişilebilirlik --- */
	$vp = $xp->query( '//meta[translate(@name,"VIEWPORT","viewport")="viewport"]' );
	if ( 0 === $vp->length ) {
		$add( 'viewport', 'critical' );
	} else {
		$content = strtolower( preg_replace( '/\s+/', '', $vp->item( 0 )->getAttribute( 'content' ) ) );
		$max     = preg_match( '/maximum-scale=([\d.]+)/', $content, $mm ) ? (float) $mm[1] : 10;
		if ( false !== strpos( $content, 'user-scalable=no' ) || false !== strpos( $content, 'user-scalable=0' ) || $max < 5 ) {
			$add( 'meta-viewport', 'high', array( flxseo_audit_snippet( $vp->item( 0 ), false ) ) );
		}
	}
	if ( '' === trim( (string) $xp->evaluate( 'string(/html/@lang)' ) ) ) {
		$add( 'html-has-lang', 'high' );
	}

	$req_children = array(
		'list'       => array( 'listitem' ),
		'tablist'    => array( 'tab' ),
		'menu'       => array( 'menuitem', 'menuitemcheckbox', 'menuitemradio', 'group' ),
		'menubar'    => array( 'menuitem', 'menuitemcheckbox', 'menuitemradio', 'group' ),
		'radiogroup' => array( 'radio' ),
		'listbox'    => array( 'option', 'group' ),
		'tree'       => array( 'treeitem', 'group' ),
		'grid'       => array( 'row', 'rowgroup' ),
		'treegrid'   => array( 'row', 'rowgroup' ),
		'feed'       => array( 'article' ),
	);
	$req_parent = array(
		'tab'              => array( 'tablist' ),
		'listitem'         => array( 'list', 'directory' ),
		'option'           => array( 'listbox', 'group' ),
		'menuitem'         => array( 'menu', 'menubar', 'group' ),
		'menuitemcheckbox' => array( 'menu', 'menubar', 'group' ),
		'menuitemradio'    => array( 'menu', 'menubar', 'group' ),
		'treeitem'         => array( 'tree', 'group' ),
	);
	$bad_children = array();
	$bad_parent   = array();
	foreach ( $xp->query( '//*[@role]' ) as $el ) {
		$role = flxseo_audit_role( $el );
		if ( isset( $req_children[ $role ] ) && 'true' !== $el->getAttribute( 'aria-busy' ) && '' === $el->getAttribute( 'aria-owns' ) ) {
			$owned = flxseo_audit_owned_roles( $el );
			$ok    = (bool) $owned;
			foreach ( $owned as $o ) {
				if ( ! in_array( $o[0], $req_children[ $role ], true ) ) {
					$ok = false;
				}
			}
			if ( ! $ok ) {
				$bad_children[] = flxseo_audit_snippet( $el );
				foreach ( $owned as $o ) {
					if ( ! in_array( $o[0], $req_children[ $role ], true ) ) {
						$bad_children[] = '  ' . flxseo_audit_snippet( $o[1] );
					}
				}
			}
		}
		if ( isset( $req_parent[ $role ] ) && $el->hasAttribute( 'role' ) && ! in_array( flxseo_audit_parent_role( $el ), $req_parent[ $role ], true ) ) {
			$bad_parent[] = flxseo_audit_snippet( $el );
		}
	}
	if ( $bad_children ) {
		$add( 'aria-required-children', 'high', $bad_children );
	}
	if ( $bad_parent ) {
		$add( 'aria-required-parent', 'high', $bad_parent );
	}
	$orphan_li = array();
	foreach ( $xp->query( '//li' ) as $li ) {
		$p    = $li->parentNode;
		$pn   = $p instanceof DOMElement ? strtolower( $p->nodeName ) : '';
		$prol = $p instanceof DOMElement ? strtolower( trim( $p->getAttribute( 'role' ) ) ) : '';
		if ( '' !== trim( $li->getAttribute( 'role' ) ) ) {
			continue;
		}
		if ( ! in_array( $pn, array( 'ul', 'ol', 'menu' ), true ) || ( '' !== $prol && 'list' !== $prol ) ) {
			$orphan_li[] = flxseo_audit_snippet( $li );
		}
	}
	if ( $orphan_li ) {
		$add( 'listitem', 'medium', $orphan_li );
	}

	$noalt = array();
	foreach ( $xp->query( '//img[not(@alt)]' ) as $img ) {
		if ( 'true' !== $img->getAttribute( 'aria-hidden' ) && 'presentation' !== $img->getAttribute( 'role' ) ) {
			$noalt[] = flxseo_audit_snippet( $img, false );
		}
	}
	if ( $noalt ) {
		$add( 'image-alt', 'high', $noalt );
	}
	$nobtn = array();
	foreach ( $xp->query( '//button | //*[@role="button"] | //input[@type="submit" or @type="button"]' ) as $b ) {
		if ( 'true' !== $b->getAttribute( 'aria-hidden' ) && ! flxseo_audit_has_name( $b, $xp ) ) {
			$nobtn[] = flxseo_audit_snippet( $b, false );
		}
	}
	if ( $nobtn ) {
		$add( 'button-name', 'high', $nobtn );
	}
	$nolabel = array();
	foreach ( $xp->query( '//input[not(@type) or not(contains(" hidden submit button reset image ", concat(" ", translate(@type,"HIDENSUBMTRAG","hidensubmtrag"), " ")))] | //select | //textarea' ) as $in ) {
		$id = $in->getAttribute( 'id' );
		if ( '' !== trim( $in->getAttribute( 'aria-label' ) ) || '' !== trim( $in->getAttribute( 'aria-labelledby' ) ) || '' !== trim( $in->getAttribute( 'title' ) ) ) {
			continue;
		}
		if ( ( $id && $xp->query( '//label[@for="' . str_replace( '"', '', $id ) . '"]' )->length ) || $xp->query( 'ancestor::label', $in )->length ) {
			continue;
		}
		$nolabel[] = flxseo_audit_snippet( $in, false );
	}
	if ( $nolabel ) {
		$add( 'label', 'high', $nolabel );
	}
	$ids  = array();
	$dupe = array();
	foreach ( $xp->query( '//*[@id]' ) as $el ) {
		$id = $el->getAttribute( 'id' );
		if ( isset( $ids[ $id ] ) ) {
			$dupe[ $id ] = 'id="' . $id . '"';
		}
		$ids[ $id ] = true;
	}
	foreach ( $xp->query( '//*[@aria-controls or @aria-labelledby or @aria-describedby] | //label[@for]' ) as $el ) {
		foreach ( array( 'aria-controls', 'aria-labelledby', 'aria-describedby', 'for' ) as $attr ) {
			foreach ( preg_split( '/\s+/', trim( $el->getAttribute( $attr ) ) ) as $ref ) {
				if ( $ref && ! isset( $ids[ $ref ] ) ) {
					$dupe[ $attr . $ref ] = $attr . '="' . $ref . '" → ' . __( 'not found', 'flx-seo' );
				}
			}
		}
	}
	if ( $dupe ) {
		$add( 'duplicate-id-aria', 'medium', array_values( $dupe ) );
	}
	$last = 0;
	$skip = array();
	foreach ( $xp->query( '//h1|//h2|//h3|//h4|//h5|//h6' ) as $hd ) {
		$lvl = (int) substr( $hd->nodeName, 1 );
		if ( $last && $lvl > $last + 1 ) {
			$skip[] = 'H' . $last . ' → ' . flxseo_audit_snippet( $hd );
		}
		$last = $lvl;
	}
	if ( $skip ) {
		$add( 'heading-order', 'low', $skip );
	}
	$tab = array();
	foreach ( $xp->query( '//*[@tabindex]' ) as $el ) {
		if ( (int) $el->getAttribute( 'tabindex' ) > 0 ) {
			$tab[] = flxseo_audit_snippet( $el, false );
		}
	}
	if ( $tab ) {
		$add( 'tabindex', 'low', $tab );
	}
	$frames = array();
	foreach ( $xp->query( '//iframe[not(@title) or normalize-space(@title)=""]' ) as $el ) {
		$frames[] = flxseo_audit_snippet( $el, false );
	}
	if ( $frames ) {
		$add( 'frame-title', 'medium', $frames );
	}
	if ( 0 === $xp->query( '//main | //*[@role="main"]' )->length ) {
		$add( 'landmark-one-main', 'medium' );
	}

	/* --- Performans --- */
	$block = array();
	foreach ( $xp->query( '//head/link[translate(@rel,"STYLESHEET","stylesheet")="stylesheet"]' ) as $l ) {
		$media = strtolower( trim( $l->getAttribute( 'media' ) ) );
		if ( ( '' === $media || 'all' === $media || 'screen' === $media ) && ! $l->hasAttribute( 'disabled' ) ) {
			$block[] = $l->getAttribute( 'href' );
		}
	}
	foreach ( $xp->query( '//head/script[@src]' ) as $s ) {
		if ( ! $s->hasAttribute( 'async' ) && ! $s->hasAttribute( 'defer' ) && 'module' !== $s->getAttribute( 'type' ) ) {
			$block[] = $s->getAttribute( 'src' );
		}
	}
	if ( count( $block ) > 2 ) {
		/* translators: %d: istek sayısı */
		$add( 'render-blocking-insight', count( $block ) > 6 ? 'high' : 'medium', $block, sprintf( __( '%d render-blocking CSS/JS requests in <head>.', 'flx-seo' ), count( $block ) ) );
	} elseif ( $block ) {
		/* translators: %d: istek sayısı */
		$add( 'render-blocking-insight', 'low', $block, sprintf( __( '%d render-blocking CSS/JS requests in <head>. Measure the estimated saving with PageSpeed.', 'flx-seo' ), count( $block ) ) );
	}
	$nodes = $xp->query( '//body//*' )->length;
	if ( $nodes > 800 ) {
		/* translators: %s: öğe sayısı */
		$add( 'dom-size-insight', $nodes > 3000 ? 'high' : ( $nodes > 1500 ? 'medium' : 'low' ), array(), sprintf( __( 'The page has %s elements.', 'flx-seo' ), number_format_i18n( $nodes ) ) );
	}
	$unsized = array();
	$first   = null;
	foreach ( $xp->query( '//body//img' ) as $img ) {
		if ( null === $first ) {
			$first = $img;
		}
		if ( ( ! $img->hasAttribute( 'width' ) || ! $img->hasAttribute( 'height' ) ) && false === stripos( $img->getAttribute( 'style' ), 'aspect-ratio' ) ) {
			$unsized[] = flxseo_audit_snippet( $img, false );
		}
	}
	if ( $unsized ) {
		$add( 'unsized-images', count( $unsized ) > 3 ? 'medium' : 'low', $unsized );
	}
	if ( $first && flxseo_audit_img_lazy( $first ) ) {
		$add( 'lcp-lazy-loaded', 'medium', array( flxseo_audit_snippet( $first, false ) ) );
	}
	$fonts_no_swap = array();
	foreach ( $xp->query( '//style' ) as $st ) {
		if ( preg_match_all( '/@font-face\s*\{[^}]*\}/i', $st->textContent, $ff ) ) {
			foreach ( $ff[0] as $rule ) {
				if ( false === stripos( $rule, 'font-display' ) ) {
					$fonts_no_swap[] = mb_substr( preg_replace( '/\s+/', ' ', $rule ), 0, 140 );
				}
			}
		}
	}
	if ( $fonts_no_swap ) {
		$add( 'font-display-insight', 'medium', $fonts_no_swap );
	}
	if ( $page['ms'] > 600 ) {
		/* translators: %d: milisaniye */
		$add( 'server-response-time', $page['ms'] > 1800 ? 'high' : 'medium', array(), sprintf( __( 'Response measured from the server: %d ms (may bypass the cache).', 'flx-seo' ), $page['ms'] ) );
	}
	if ( strlen( $html ) > 2048 && empty( $h['content-encoding'] ) && ! preg_match( '/^(127\.|localhost)/', (string) wp_parse_url( $url, PHP_URL_HOST ) ) ) {
		$add( 'uses-text-compression', 'medium' );
	}
	if ( strlen( $html ) > 150 * 1024 ) {
		/* translators: %s: boyut */
		$add( 'document-size', strlen( $html ) > 400 * 1024 ? 'medium' : 'low', array(), sprintf( __( 'HTML is %s.', 'flx-seo' ), size_format( strlen( $html ) ) ) );
	}
	if ( false !== strpos( $html, 'wp-emoji-release.min.js' ) || false !== strpos( $html, '_wpemojiSettings' ) ) {
		$add( 'emoji-script', 'low' );
	}

	/* --- En iyi uygulamalar: HTTPS ve güvenlik başlıkları --- */
	$https = 0 === strpos( home_url(), 'https://' );
	$local = (bool) preg_match( '/^(localhost|127\.|10\.|192\.168\.)|\.(local|test)$/', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	if ( ! $https && ! $local ) {
		$add( 'is-on-https', 'high' );
	} elseif ( $https && preg_match_all( '/<(?:img|script|link|iframe|source)\b[^>]+(?:src|href)=["\']http:\/\/[^"\']+/i', $html, $mixed ) ) {
		$add( 'is-on-https', 'high', array_slice( $mixed[0], 0, 5 ), __( 'The HTTPS page loads content over http://.', 'flx-seo' ) );
	}
	if ( $https || $local ) {
		$hsts = strtolower( $h['strict-transport-security'] ?? '' );
		if ( '' === $hsts ) {
			$add( 'has-hsts', $local ? 'low' : 'medium' );
		} elseif ( false === strpos( $hsts, 'includesubdomains' ) || false === strpos( $hsts, 'preload' ) ) {
			$add( 'has-hsts', 'low', array( 'Strict-Transport-Security: ' . $h['strict-transport-security'] ) );
		}
	}
	$csp = strtolower( $h['content-security-policy'] ?? '' );
	if ( '' === $csp ) {
		$add( 'csp-xss', 'medium', array(), __( 'No Content-Security-Policy header.', 'flx-seo' ) );
	} else {
		$miss = array();
		foreach ( array( 'object-src', 'base-uri', 'script-src' ) as $d ) {
			if ( false === strpos( $csp, $d ) && ( 'script-src' !== $d || false === strpos( $csp, 'default-src' ) ) ) {
				$miss[] = $d;
			}
		}
		if ( $miss ) {
			/* translators: %s: eksik yönergeler */
			$add( 'csp-xss', 'low', array( 'Content-Security-Policy: ' . mb_substr( $h['content-security-policy'], 0, 160 ) ), sprintf( __( 'Missing directive: %s.', 'flx-seo' ), implode( ', ', $miss ) ) );
		}
	}
	if ( empty( $h['x-frame-options'] ) && false === strpos( $csp, 'frame-ancestors' ) ) {
		$add( 'clickjacking-mitigation', 'medium' );
	}
	if ( empty( $h['cross-origin-opener-policy'] ) ) {
		$add( 'origin-isolation', 'low' );
	}
	if ( false === strpos( $csp, 'require-trusted-types-for' ) ) {
		$add( 'trusted-types-xss', 'low' );
	}
	if ( empty( $h['x-content-type-options'] ) ) {
		$add( 'x-content-type-options', 'low' );
	}
	if ( 0 !== stripos( ltrim( $html ), '<!doctype html' ) || ! preg_match( '/<meta[^>]+charset=/i', substr( $html, 0, 2048 ) ) ) {
		$add( 'doctype', 'medium' );
	}

	/* --- Ajan uyumu: WebMCP --- */
	$forms   = $xp->query( '//form' );
	$no_tool = array();
	foreach ( $forms as $form ) {
		if ( ! $form->hasAttribute( 'toolname' ) && $xp->query( './/input[not(@type="hidden")] | .//textarea | .//select', $form )->length ) {
			$no_tool[] = flxseo_audit_snippet( $form, false );
		}
	}
	if ( $no_tool ) {
		$add( 'webmcp-forms', flxseo_opt( 'webmcp' ) ? 'low' : 'medium', $no_tool );
	}

	return (array) apply_filters( 'flxseo_audit_local_checks', $f, $html, $h, $url );
}

/** Site geneli bulgular (sayfaya bağlı değil): robots.txt, llms.txt, aynı başlıklı yayınlar. */
function flxseo_audit_site_checks() {
	global $wpdb;
	$f     = array();
	$robot = flxseo_audit_fetch( home_url( '/robots.txt' ) );
	if ( ! is_wp_error( $robot ) && 200 === $robot['code'] ) {
		$txt   = $robot['body'];
		$block = flxseo_robots_blocks( $txt );
		if ( in_array( '*', $block, true ) ) {
			$f[] = flxseo_audit_finding( 'robots-txt', 'critical', array( 'Disallow: /' ) );
		}
		$ai = array_values( array_intersect( $block, array( 'gptbot', 'claudebot', 'anthropic-ai', 'google-extended', 'perplexitybot', 'ccbot', 'oai-searchbot', 'chatgpt-user', 'claude-searchbot', 'applebot-extended' ) ) );
		if ( $ai && ! in_array( '*', $block, true ) ) {
			$f[] = flxseo_audit_finding( 'ai-crawlers-blocked', 'low', $ai );
		}
	}
	if ( ! is_wp_error( $robot ) && 200 === $robot['code'] ) {
		$se = array_values( array_intersect( $block, array( 'bingbot', 'msnbot', 'yandex', 'yandexbot', 'duckduckbot', 'slurp', 'seznambot', 'yeti', 'applebot' ) ) );
		if ( $se && ! in_array( '*', $block, true ) ) {
			$f[] = flxseo_audit_finding( 'search-bots-blocked', 'high', $se );
		}
		if ( ! preg_match( '/^\s*sitemap:\s*\S+/mi', $txt ) ) {
			$f[] = flxseo_audit_finding( 'robots-sitemap', 'low' );
		}
	}
	if ( get_option( 'blog_public' ) ) {
		if ( ! flxseo_opt( 'indexnow' ) ) {
			$f[] = flxseo_audit_finding( 'indexnow', 'medium' );
		} else {
			$last = (array) get_option( 'flxseo_indexnow_last', array() );
			if ( ! empty( $last['code'] ) && (int) $last['code'] >= 400 ) {
				$f[] = flxseo_audit_finding( 'indexnow-error', 'medium', array( flxseo_indexnow_last_text( $last ) ) );
			}
			$key = flxseo_audit_fetch( home_url( '/' . flxseo_indexnow_key() . '.txt' ) );
			if ( ! is_wp_error( $key ) && ( 200 !== $key['code'] || trim( $key['body'] ) !== flxseo_indexnow_key() ) ) {
				$f[] = flxseo_audit_finding( 'indexnow-error', 'medium', array( home_url( '/' . flxseo_indexnow_key() . '.txt' ) . ' → HTTP ' . (int) $key['code'] ) );
			}
		}
		$miss = array();
		foreach ( array( 'verify_bing' => 'Bing', 'verify_yandex' => 'Yandex' ) as $k => $label ) {
			if ( '' === trim( (string) flxseo_opt( $k ) ) ) {
				$miss[] = $label;
			}
		}
		if ( $miss ) {
			$f[] = flxseo_audit_finding( 'search-verify', 'low', $miss );
		}
	}
	$php = flxseo_php_security_status();
	if ( 'ok' !== $php['status'] ) {
		$f[] = flxseo_audit_finding( 'php-unpatched', 'critical', array( 'PHP ' . $php['version'] . ' → ' . $php['fixed'] ), flxseo_php_security_message( $php ) );
	}
	if ( flxseo_php_upload_progress_on() ) {
		$f[] = flxseo_audit_finding( 'php-upload-progress', 'ok' !== $php['status'] ? 'high' : 'medium', array( 'session.upload_progress.enabled = On' ) );
	}
	$llms = flxseo_audit_fetch( home_url( '/llms.txt' ) );
	if ( is_wp_error( $llms ) || 200 !== $llms['code'] || '' === trim( $llms['body'] ) || false !== stripos( $llms['body'], '<html' ) ) {
		$f[] = flxseo_audit_finding( 'llms-txt', 'medium' );
	} else {
		$body = ltrim( $llms['body'] );
		$miss = array();
		if ( 0 !== strpos( $body, '# ' ) ) {
			$miss[] = __( 'first line is not "# Title"', 'flx-seo' );
		}
		if ( ! preg_match( '/^>\s+\S/m', $body ) ) {
			$miss[] = __( 'no "> summary" line', 'flx-seo' );
		}
		if ( ! preg_match( '/\[[^\]]+\]\([^)]+\)/', $body ) ) {
			$miss[] = __( 'no link list', 'flx-seo' );
		}
		if ( $miss ) {
			$f[] = flxseo_audit_finding( 'llms-txt-format', 'low', $miss );
		}
	}

	$types = flxseo_post_types();
	$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	// Taşınabilir sorgu (HAVING bazı veritabanı katmanlarında çalışmıyor): başlığa göre sırala, ardışık aynıları topla.
	$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_type FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($in) AND post_title <> '' ORDER BY post_type, post_title, ID LIMIT 5000", $types ) );
	$groups = array();
	foreach ( (array) $rows as $r ) {
		$groups[ $r->post_type . "\0" . mb_strtolower( trim( $r->post_title ) ) ][] = $r;
	}
	$items = array();
	foreach ( $groups as $g ) {
		if ( count( $g ) > 1 && count( $items ) < 20 ) {
			$items[] = $g[0]->post_title . ' → ' . implode( ' | ', array_map( fn( $r ) => get_permalink( (int) $r->ID ), array_slice( $g, 0, 4 ) ) );
		}
	}
	if ( $items ) {
		$f[] = flxseo_audit_finding( 'duplicate-titles', 'medium', $items );
	}
	return $f;
}

/** robots.txt'de "Disallow: /" olan user-agent'lar (küçük harf). */
function flxseo_robots_blocks( $txt ) {
	$blocked = array();
	$agents  = array();
	$in_rule = false;
	foreach ( preg_split( '/\r?\n/', (string) $txt ) as $line ) {
		$line = trim( preg_replace( '/#.*$/', '', $line ) );
		if ( '' === $line ) {
			continue;
		}
		if ( preg_match( '/^user-agent:\s*(.+)$/i', $line, $m ) ) {
			if ( $in_rule ) {
				$agents  = array();
				$in_rule = false;
			}
			$agents[] = strtolower( trim( $m[1] ) );
			continue;
		}
		$in_rule = true;
		if ( preg_match( '/^disallow:\s*\/\s*$/i', $line ) ) {
			$blocked = array_merge( $blocked, $agents );
		}
	}
	return array_values( array_unique( $blocked ) );
}

/**
 * Yerel denetimi çalıştırır ve kaydeder.
 *
 * @return array|WP_Error
 */
function flxseo_audit_run_local( $url = '' ) {
	$url  = $url ? esc_url_raw( $url ) : home_url( '/' );
	$page = flxseo_audit_fetch( add_query_arg( 'flxseo_audit', time(), $url ) );
	if ( is_wp_error( $page ) ) {
		return $page;
	}
	$findings = array_merge( flxseo_audit_analyze( $page, $url ), flxseo_audit_site_checks() );
	$store    = flxseo_audit_store();
	$store['local'] = array(
		'time'     => time(),
		'url'      => $url,
		'ms'       => $page['ms'],
		'code'     => $page['code'],
		'findings' => $findings,
	);
	update_option( 'flxseo_audit', $store, false );
	return $store['local'];
}

function flxseo_audit_store() {
	$s = get_option( 'flxseo_audit', array() );
	return is_array( $s ) ? $s + array( 'local' => array(), 'psi' => array() ) : array( 'local' => array(), 'psi' => array() );
}

/* ---------------------------------------------------------------------------
 * PageSpeed Insights API
 * ------------------------------------------------------------------------- */

function flxseo_psi_key() {
	return (string) get_option( 'flxseo_psi_key', '' );
}

/**
 * PageSpeed Insights ölçümü (tek strateji ~15–40 sn). Anahtar isteğe bağlı; anahtarsız kota çok düşüktür.
 *
 * @param string $strategy mobile|desktop.
 * @return array|WP_Error
 */
function flxseo_psi_run( $strategy = 'mobile', $url = '' ) {
	$strategy = 'desktop' === $strategy ? 'desktop' : 'mobile';
	$url      = $url ? esc_url_raw( $url ) : home_url( '/' );
	if ( preg_match( '/^(localhost|127\.|10\.|192\.168\.)|\.(local|test)$/', (string) wp_parse_url( $url, PHP_URL_HOST ) ) ) {
		return new WP_Error( 'flxseo_psi_local', __( 'PageSpeed Insights can only measure addresses reachable from the internet.', 'flx-seo' ) );
	}
	$cats  = (array) apply_filters( 'flxseo_psi_categories', array( 'performance', 'accessibility', 'best-practices', 'seo' ) );
	$query = 'url=' . rawurlencode( $url ) . '&strategy=' . $strategy . '&locale=' . ( 0 === strpos( determine_locale(), 'tr' ) ? 'tr' : 'en' );
	foreach ( $cats as $c ) {
		$query .= '&category=' . rawurlencode( strtoupper( str_replace( '-', '_', $c ) ) );
	}
	if ( flxseo_psi_key() ) {
		$query .= '&key=' . rawurlencode( flxseo_psi_key() );
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	// Anahtar "Web siteleri" (HTTP referrer) kısıtlıysa Google boş Referer'ı reddeder; site adresini gönder.
	$r = wp_remote_get(
		FLXSEO_PSI_ENDPOINT . '?' . $query,
		array(
			'timeout' => 90,
			'headers' => array( 'Referer' => home_url( '/' ) ),
		)
	);
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$code = (int) wp_remote_retrieve_response_code( $r );
	$data = json_decode( (string) wp_remote_retrieve_body( $r ), true );
	if ( 200 !== $code || empty( $data['lighthouseResult'] ) ) {
		$msg = $data['error']['message'] ?? ( 'HTTP ' . $code );
		if ( 429 === $code ) {
			$msg = __( 'The PageSpeed daily quota is used up. Enter a free Google API key in SEO › Settings.', 'flx-seo' );
		}
		return new WP_Error( 'flxseo_psi', $msg );
	}
	$res              = flxseo_psi_parse( $data['lighthouseResult'], $strategy );
	$res['time']      = time();
	$res['url']       = $url;
	$store            = flxseo_audit_store();
	$store['psi'][ $strategy ] = $res;
	update_option( 'flxseo_audit', $store, false );
	return $res;
}

/** Lighthouse sonucunu sade bulgu listesine çevirir. */
function flxseo_psi_parse( array $lh, $strategy ) {
	$audits   = (array) ( $lh['audits'] ?? array() );
	$scores   = array();
	$findings = array();
	$cat_map  = array( 'best-practices' => 'best-practices', 'performance' => 'performance', 'accessibility' => 'accessibility', 'seo' => 'seo' );
	$metrics  = array();
	foreach ( array( 'first-contentful-paint', 'largest-contentful-paint', 'total-blocking-time', 'cumulative-layout-shift', 'speed-index' ) as $m ) {
		if ( isset( $audits[ $m ] ) ) {
			$metrics[ $m ] = array(
				'title' => (string) ( $audits[ $m ]['title'] ?? $m ),
				'value' => (string) ( $audits[ $m ]['displayValue'] ?? '' ),
				'score' => isset( $audits[ $m ]['score'] ) ? (float) $audits[ $m ]['score'] : null,
			);
		}
	}
	$source = 'psi-' . $strategy;
	foreach ( (array) ( $lh['categories'] ?? array() ) as $cid => $cat ) {
		$our    = $cat_map[ $cid ] ?? ( false !== strpos( $cid, 'agent' ) ? 'agentic' : $cid );
		$passed = 0;
		$total  = 0;
		foreach ( (array) ( $cat['auditRefs'] ?? array() ) as $ref ) {
			$a = $audits[ $ref['id'] ] ?? null;
			if ( ! $a ) {
				continue;
			}
			$mode   = (string) ( $a['scoreDisplayMode'] ?? '' );
			$score  = isset( $a['score'] ) ? (float) $a['score'] : null;
			$weight = (float) ( $ref['weight'] ?? 0 );
			if ( in_array( $mode, array( 'notApplicable', 'manual' ), true ) ) {
				continue;
			}
			if ( 'binary' === $mode || 'numeric' === $mode || 'metricSavings' === $mode ) {
				++$total;
				if ( null !== $score && $score >= 0.9 ) {
					++$passed;
				}
			}
			$prio = flxseo_psi_priority( $our, $ref['id'], $a, $weight );
			if ( ! $prio ) {
				continue;
			}
			$items = array();
			foreach ( array_slice( (array) ( $a['details']['items'] ?? array() ), 0, 8 ) as $it ) {
				if ( ! is_array( $it ) ) {
					continue;
				}
				if ( ! empty( $it['node']['snippet'] ) ) {
					$items[] = $it['node']['snippet'];
				} elseif ( ! empty( $it['url'] ) ) {
					$items[] = $it['url'] . ( isset( $it['wastedBytes'] ) ? ' (−' . size_format( (int) $it['wastedBytes'] ) . ')' : '' );
				} elseif ( ! empty( $it['description'] ) && is_string( $it['description'] ) ) {
					$items[] = $it['description'] . ( isset( $it['severity'] ) && is_string( $it['severity'] ) ? ' · ' . $it['severity'] : '' );
				} elseif ( ! empty( $it['directive'] ) && is_string( $it['directive'] ) ) {
					$items[] = $it['directive'];
				}
			}
			$known = isset( flxseo_audit_catalog()[ $ref['id'] ] );
			$fd    = flxseo_audit_finding( $ref['id'], $prio, $items, '', $source );
			if ( ! $known ) {
				$fd['category'] = $our;
				$fd['title']    = (string) ( $a['title'] ?? $ref['id'] );
				$fd['fix']      = flxseo_md_strip( (string) ( $a['description'] ?? '' ) );
			}
			$fd['detail'] = trim( (string) ( $a['displayValue'] ?? '' ) );
			$findings[]   = $fd;
		}
		$scores[ $our ] = array(
			'title'  => (string) ( $cat['title'] ?? $cid ),
			'score'  => isset( $cat['score'] ) ? (int) round( (float) $cat['score'] * 100 ) : null,
			'passed' => $passed,
			'total'  => $total,
		);
	}
	return array(
		'strategy'   => $strategy,
		'scores'     => $scores,
		'metrics'    => $metrics,
		'findings'   => $findings,
		'lighthouse' => (string) ( $lh['lighthouseVersion'] ?? '' ),
	);
}

/** PageSpeed denetiminin önceliği; geçen ya da önemsiz denetim için ''. */
function flxseo_psi_priority( $cat, $id, array $a, $weight ) {
	$mode  = (string) ( $a['scoreDisplayMode'] ?? '' );
	$score = isset( $a['score'] ) ? (float) $a['score'] : null;
	$core  = array( 'largest-contentful-paint', 'cumulative-layout-shift', 'total-blocking-time' );
	if ( 'performance' === $cat && $weight > 0 ) {
		if ( null === $score || $score >= 0.9 ) {
			return '';
		}
		if ( $score < 0.5 ) {
			return in_array( $id, $core, true ) ? 'critical' : 'high';
		}
		return in_array( $id, $core, true ) ? 'medium' : 'low';
	}
	if ( 'performance' === $cat ) {
		$sav   = (array) ( $a['metricSavings'] ?? array() );
		$ms    = max( (float) ( $sav['LCP'] ?? 0 ), (float) ( $sav['FCP'] ?? 0 ), (float) ( $sav['TBT'] ?? 0 ), (float) ( $a['details']['overallSavingsMs'] ?? 0 ) );
		$cls   = (float) ( $sav['CLS'] ?? 0 );
		$bytes = (float) ( $a['details']['overallSavingsBytes'] ?? 0 );
		if ( null !== $score && $score >= 0.9 && $ms < 50 && $cls < 0.01 ) {
			return '';
		}
		if ( $ms >= 1000 || $cls >= 0.1 ) {
			return 'high';
		}
		if ( $ms >= 150 || $cls >= 0.025 || $bytes >= 40 * 1024 ) {
			return 'medium';
		}
		if ( $ms > 0 || $cls > 0 || $bytes > 0 || ( null !== $score && $score < 0.9 && ( 'informative' !== $mode || ! empty( $a['details']['items'] ) ) ) ) {
			return 'low';
		}
		return '';
	}
	if ( 'informative' === $mode ) {
		// Güven ve güvenlik denetimleri (csp-xss, has-hsts …) puanlanmaz ama Lighthouse "Yüksek önem" der.
		if ( isset( flxseo_audit_catalog()[ $id ] ) && ! empty( $a['details']['items'] ) ) {
			return in_array( $id, array( 'csp-xss', 'clickjacking-mitigation' ), true ) ? 'medium' : 'low';
		}
		return '';
	}
	if ( null === $score || $score >= 0.9 ) {
		return '';
	}
	if ( 'seo' === $cat && in_array( $id, array( 'is-crawlable', 'http-status-code', 'document-title' ), true ) ) {
		return 'critical';
	}
	if ( 'agentic' === $cat ) {
		return 'high';
	}
	if ( $weight >= 7 ) {
		return 'high';
	}
	return $weight >= 3 ? 'medium' : 'low';
}

/** Lighthouse açıklamasındaki Markdown bağlantılarını düz metne çevirir. */
function flxseo_md_strip( $s ) {
	$s = preg_replace( '/\[([^\]]+)\]\((https?:[^)]+)\)/', '$1 ($2)', $s );
	return trim( str_replace( array( '`', '**' ), '', $s ) );
}

/* ---------------------------------------------------------------------------
 * Birleşik sonuç
 * ------------------------------------------------------------------------- */

/**
 * Yerel + PageSpeed (mobil, masaüstü) bulguları birleştirir, önceliğe göre sıralar.
 *
 * @return array{findings:array, counts:array, local:array, psi:array}
 */
function flxseo_audit_report() {
	$store = flxseo_audit_store();
	$all   = array();
	$lists = array( (array) ( $store['local']['findings'] ?? array() ) );
	foreach ( array( 'mobile', 'desktop' ) as $s ) {
		$lists[] = (array) ( $store['psi'][ $s ]['findings'] ?? array() );
	}
	$order = flxseo_audit_priorities();
	foreach ( $lists as $list ) {
		foreach ( $list as $fd ) {
			if ( empty( $fd['id'] ) ) {
				continue;
			}
			$k = $fd['id'];
			if ( ! isset( $all[ $k ] ) ) {
				$all[ $k ] = $fd;
				continue;
			}
			if ( ( $order[ $fd['priority'] ][0] ?? 9 ) < ( $order[ $all[ $k ]['priority'] ][0] ?? 9 ) ) {
				$all[ $k ]['priority'] = $fd['priority'];
			}
			$all[ $k ]['sources'] = array_values( array_unique( array_merge( $all[ $k ]['sources'], $fd['sources'] ) ) );
			$all[ $k ]['items']   = array_slice( array_values( array_unique( array_merge( $all[ $k ]['items'], $fd['items'] ) ) ), 0, 10 );
			if ( '' === $all[ $k ]['detail'] && '' !== $fd['detail'] ) {
				$all[ $k ]['detail'] = $fd['detail'];
			}
		}
	}
	// Metinler kayıt anındaki dilde; katalogdaki bulgular gösterilen dilde yenilenir.
	$cat = flxseo_audit_catalog();
	foreach ( $all as $k => $fd ) {
		if ( isset( $cat[ $k ] ) ) {
			$all[ $k ]['title'] = $cat[ $k ][1];
			$all[ $k ]['fix']   = $cat[ $k ][2];
		}
		// Tek tık düzeltme zaten açıksa düğmeyi gösterme.
		if ( ! empty( $fd['fix_id'] ) && flxseo_fix_is_on( $fd['fix_id'] ) ) {
			$all[ $k ]['fix_id'] = '';
		}
	}
	$all = array_values( (array) apply_filters( 'flxseo_audit_findings', $all ) );
	usort(
		$all,
		function ( $a, $b ) use ( $order ) {
			return array( $order[ $a['priority'] ][0] ?? 9, $a['category'] ) <=> array( $order[ $b['priority'] ][0] ?? 9, $b['category'] );
		}
	);
	$counts = array_fill_keys( array_keys( $order ), 0 );
	foreach ( $all as $fd ) {
		if ( isset( $counts[ $fd['priority'] ] ) ) {
			++$counts[ $fd['priority'] ];
		}
	}
	return array(
		'findings' => $all,
		'counts'   => $counts,
		'local'    => array_diff_key( (array) $store['local'], array( 'findings' => 1 ) ),
		'psi'      => array_map(
			fn( $p ) => array_diff_key( (array) $p, array( 'findings' => 1 ) ),
			(array) $store['psi']
		),
	);
}

/* Zamanlanmış denetim ve geçmiş: includes/denetim-gecmis.php */

/* Sistem Kontrolü satırı: kritik/yüksek bulgu varsa uyarı. */
add_filter(
	'flx_system_checks',
	function ( $checks ) {
		$r     = flxseo_audit_report();
		$label = __( 'Site audit', 'flx-seo' );
		$url   = flxseo_admin_url( 'denetim' );
		$base  = array(
			'id'           => 'flxseo.audit',
			'source'       => 'flx-seo',
			'group'        => 'SEO',
			'label'        => $label,
			'action_url'   => $url,
			'action_label' => __( 'Open audit', 'flx-seo' ),
		);
		if ( empty( $r['local']['time'] ) && empty( $r['psi'] ) ) {
			$checks[] = $base + array(
				'status'  => 'info',
				'message' => __( 'Not run yet. Check performance, accessibility, SEO, security and agent readiness.', 'flx-seo' ),
			);
			return $checks;
		}
		$c = $r['counts'];
		/* translators: 1: kritik, 2: yüksek, 3: orta, 4: düşük bulgu sayısı */
		$msg      = sprintf( __( 'Critical %1$d, high %2$d, medium %3$d, low %4$d recommendations.', 'flx-seo' ), $c['critical'], $c['high'], $c['medium'], $c['low'] );
		$top      = array_slice( array_filter( $r['findings'], fn( $f ) => in_array( $f['priority'], array( 'critical', 'high' ), true ) ), 0, 3 );
		if ( $top ) {
			$msg .= ' ' . implode( ' · ', wp_list_pluck( $top, 'title' ) );
		}
		$checks[] = $base + array(
			'status'  => $c['critical'] ? 'error' : ( $c['high'] ? 'warning' : 'ok' ),
			'message' => $msg,
		);
		return $checks;
	},
	21
);
