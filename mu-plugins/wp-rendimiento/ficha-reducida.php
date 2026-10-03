<?php
/**
 * Pieza: Ficha de producto reducida (antes «solo tres bloques»).
 * Qué hace: En la página de producto deja solo las pestañas que elijas (por defecto «Descripción» y «Valoraciones») y quita los bloques
 *   que WooCommerce cuelga de `woocommerce_after_single_product_summary` (relacionados, upsells, etc.), salvo los de los archivos que
 *   indiques en «pdp_conservar». Las valoraciones se imprimen ocultas y salen solo con #reviews. No borra reseñas ni datos.
 * APAGADA POR DEFECTO: cambia el aspecto de la ficha. Se enciende con: wp option update lm_rendimiento_pdp_on 1
 * Dónde se nota: En la página de producto (nunca en carrito, pago ni mi cuenta).
 * Ajustes (opción lm_rendimiento_<nombre> o constante LM_REND_<NOMBRE>; listas separadas por comas):
 *   pdp_on          — 1 para encender la pieza. Por defecto 0 (apagada).
 *   pdp_tabs        — pestañas que se conservan. Por defecto: description,reviews.
 *   pdp_conservar   — nombres de archivo PHP (p. ej. «mi-plugin.php») cuyos bloques (closures) colgados de after_single_product_summary se conservan. Por defecto vacío.
 *   pdp_selectores  — selectores CSS de los bloques que quedan, debajo de los cuales se muestra la reseña a demanda. Por defecto: section.up-sells, section.related.
 * Cómo se apaga: wp option delete lm_rendimiento_pdp_on (o wp option update lm_rendimiento_pdp_off 1; o la heredada lm_pdp_solo_tres_off).
 * Autor: Andrés Valencia Tobón.
 */

defined( 'ABSPATH' ) || exit;

/** ¿Está activa la pieza? Hay que encenderla y no estar apagada con la opción heredada. */
function lm_pdp3_activo() {
	if ( ! lm_rend_cfg( 'pdp_on', 0 ) ) {
		return false;
	}
	return '__no_existe__' === get_option( 'lm_pdp_solo_tres_off', '__no_existe__' );
}

/** ¿Estamos en la ficha de un producto (fuera de carrito, pago y cuenta)? */
function lm_pdp3_es_ficha() {
	return function_exists( 'is_product' ) && is_product();
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. Pestañas de WooCommerce: se conservan solo las indicadas en «pdp_tabs».
// ─────────────────────────────────────────────────────────────────────────────
add_filter( 'woocommerce_product_tabs', function ( $tabs ) {
	if ( ! lm_pdp3_activo() || ! lm_pdp3_es_ficha() || ! is_array( $tabs ) ) {
		return $tabs;
	}
	$quedan = array_fill_keys( lm_rend_cfg_lista( 'pdp_tabs', 'description,reviews' ), true );
	return array_intersect_key( $tabs, $quedan );
}, 100 );

// ─────────────────────────────────────────────────────────────────────────────
// 2. Bloques colgados de `woocommerce_after_single_product_summary`. Se quitan todos los
//    callbacks salvo las pestañas y los closures definidos en los archivos de «pdp_conservar».
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'template_redirect', function () {
	if ( ! lm_pdp3_activo() || ! lm_pdp3_es_ficha() ) {
		return;
	}
	global $wp_filter;
	$hook = 'woocommerce_after_single_product_summary';
	if ( empty( $wp_filter[ $hook ] ) || empty( $wp_filter[ $hook ]->callbacks ) ) {
		return;
	}
	$conservar = lm_rend_cfg_lista( 'pdp_conservar', '' );
	$quitar    = array();
	foreach ( $wp_filter[ $hook ]->callbacks as $prio => $cbs ) {
		foreach ( $cbs as $cb ) {
			$fn = $cb['function'];
			if ( 'woocommerce_output_product_data_tabs' === $fn ) {
				continue; // las pestañas (ya filtradas).
			}
			if ( $conservar && $fn instanceof Closure ) {
				try {
					$ref = new ReflectionFunction( $fn );
					if ( in_array( basename( (string) $ref->getFileName() ), $conservar, true ) ) {
						continue; // bloque permitido.
					}
				} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
					// Si no se puede inspeccionar, se trata como ajeno y se quita.
				}
			}
			$quitar[] = array( $fn, (int) $prio );
		}
	}
	foreach ( $quitar as $q ) {
		remove_action( $hook, $q[0], $q[1] );
	}
}, 100 );

// ─────────────────────────────────────────────────────────────────────────────
// 3. Bloques de plantilla que podrían colarse bajo la ficha.
// ─────────────────────────────────────────────────────────────────────────────
add_filter( 'render_block', function ( $contenido, $bloque ) {
	if ( empty( $bloque['blockName'] ) || ! lm_pdp3_activo() || ! lm_pdp3_es_ficha() ) {
		return $contenido;
	}
	$fuera = array(
		'woocommerce/related-products',
		'woocommerce/product-reviews',
		'woocommerce/product-review-form',
		'woocommerce/product-review-template',
	);
	return in_array( $bloque['blockName'], $fuera, true ) ? '' : $contenido;
}, 20, 2 );

// ─────────────────────────────────────────────────────────────────────────────
// 4. Reseñas «a demanda». Por defecto el panel de valoraciones va oculto y el menú
//    de pestañas también. Si la URL trae #tab-reviews, #reviews o #comment-…, o se
//    toca un enlace a esos fragmentos, un script mínimo mueve el panel debajo de los
//    bloques que quedan, lo muestra y hace scroll hasta él. El formulario no se toca.
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_head', function () {
	if ( ! lm_pdp3_activo() || ! lm_pdp3_es_ficha() ) {
		return;
	}
	?>
<style id="lm-pdp3-css">
html body.single-product .woocommerce-tabs > ul.tabs{display:none !important}
html body.single-product .woocommerce-tabs #tab-description{display:block !important}
html body.single-product #tab-reviews{display:none !important}
html body.single-product.lm-ver-resenas #tab-reviews{display:block !important;margin:34px auto;padding-top:22px;border-top:1px solid rgba(0,0,0,.08)}
</style>
<noscript><style>html body.single-product #tab-reviews{display:block !important}</style></noscript>
	<?php
}, 99 );

add_action( 'wp_footer', function () {
	if ( ! lm_pdp3_activo() || ! lm_pdp3_es_ficha() ) {
		return;
	}
	// Selectores: solo caracteres de selector; se pasan al script ya codificados.
	$sel = array();
	foreach ( lm_rend_cfg_lista( 'pdp_selectores', 'section.up-sells, section.related' ) as $s ) {
		if ( preg_match( '/^[A-Za-z0-9_.#\-: >]+$/', $s ) ) {
			$sel[] = $s;
		}
	}
	$selector = implode( ',', $sel );
	?>
<script id="lm-pdp3-js">
(function () {
	var SEL = <?php echo wp_json_encode( $selector ); ?>;
	// Fragmentos que piden ver las valoraciones.
	var RE = /^#(tab-reviews|reviews)$|^#comment-/;
	function ver() {
		var p = document.getElementById('tab-reviews');
		if (!p) { return; }
		document.body.classList.add('lm-ver-resenas');
		if (!p.getAttribute('data-lm-movido')) {
			// Debajo del último de los bloques que quedan.
			var s = SEL ? document.querySelectorAll(SEL) : [];
			var ult = s.length ? s[s.length - 1] : null;
			if (ult && ult.parentNode) { ult.parentNode.insertBefore(p, ult.nextSibling); }
			p.classList.add('alignwide');
			p.setAttribute('data-lm-movido', '1');
		}
		p.scrollIntoView();
	}
	function porHash() { if (RE.test(location.hash)) { ver(); } }
	porHash();
	// Tras cargar todo (imágenes perezosas mueven el diseño), se repite el scroll.
	window.addEventListener('load', function () { setTimeout(porHash, 300); });
	window.addEventListener('hashchange', porHash);
	document.addEventListener('click', function (e) {
		var a = e.target && e.target.closest ? e.target.closest('a[href*="#"]') : null;
		if (a && a.host === location.host && a.pathname === location.pathname && RE.test(a.hash)) { ver(); }
	}, true);
})();
</script>
	<?php
}, 99 );
