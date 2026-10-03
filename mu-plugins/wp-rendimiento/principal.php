<?php
/**
 * Pieza: Rendimiento principal.
 * Qué hace: acelera portada, tienda y ficha sin quitar plugins: aplaza scripts del <head>, retira librerías
 *   del editor donde nada las usa y prioriza la imagen principal (LCP).
 * Dónde se nota: portada, tienda, categorías y ficha. Nunca en carrito, pago ni mi cuenta.
 * Criterio: ante la duda no se difiere (jQuery no se toca).
 * Cómo se apaga (forma: wp option update <nombre> 1):
 *   lm_rendimiento_principal_off o lm_rendimiento_off → toda esta pieza
 *   lm_rendimiento_off_diferir   → (1) scripts del <head>
 *   lm_rendimiento_off_editor    → (2) librerías del editor en la ficha
 *   lm_rendimiento_off_lcp       → (3) imagen principal con prioridad alta
 * Cómo se deshace: wp option delete <nombre>.
 * Ajustes (opción lm_rendimiento_<nombre> o constante LM_REND_<NOMBRE>; listas separadas por comas):
 *   diferir_handles        — handles de script a aplazar (defer). Por defecto: react, react-jsx-runtime, react-dom, wp-escape-html, wp-element.
 *   diferir_pie            — handles que ya son defer/async y se mueven al pie. Por defecto vacío.
 *   editor_handles         — handles que se retiran de la ficha si la página no tiene bloques de WooCommerce que los usen. Por defecto: gtmkit-woocommerce-blocks.
 *   portada_bloque         — nombre del bloque de portada cuya primera imagen lleva prioridad alta (p. ej. «mi-tema/productos»). Por defecto vacío (no se toca la portada).
 *   portada_banner_bloque  — bloque de banners que deja de pedir prioridad alta en la portada. Por defecto vacío.
 * Autor: Andrés Valencia Tobón.
 */

defined( 'ABSPATH' ) || exit;

define( 'LM_REND_VERSION', '1.0.0' );

/** ¿Está activa la función $f? (interruptor general + el de la función). */
function lm_rend_activo( string $f ): bool {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return false;
	}
	if ( get_option( 'lm_rendimiento_off' ) || get_option( 'lm_rendimiento_off_' . $f ) ) {
		return false;
	}
	return true;
}

/** Páginas donde se cobra o se gestiona la cuenta: aquí no se toca nada. */
function lm_rend_zona_protegida(): bool {
	if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
		return true;
	}
	return is_customize_preview() || is_preview() || is_embed();
}

/* =========================================================================
 * (1) DIFERIR LO QUE FRENA EL PRIMER PINTADO
 * -------------------------------------------------------------------------
 *  a) Se pide 'defer' para los handles de «diferir_handles». WordPress decide
 *     si es seguro según las dependencias (si algo que no es defer los
 *     necesita, no se aplaza).
 *  b) Los handles de «diferir_pie» que YA eran defer/async pasan al pie: se
 *     ejecutan en el mismo momento, pero ya no bloquean el pintado por sus
 *     dependencias.
 * ========================================================================= */
add_action( 'wp_print_scripts', 'lm_rend_diferir', 999 );
function lm_rend_diferir(): void {
	if ( ! lm_rend_activo( 'diferir' ) || lm_rend_zona_protegida() ) {
		return;
	}
	$ws      = wp_scripts();
	$aplazar = lm_rend_cfg_lista( 'diferir_handles', 'react,react-jsx-runtime,react-dom,wp-escape-html,wp-element' );
	foreach ( $aplazar as $h ) {
		if ( 'jquery' === $h || 'jquery-core' === $h ) {
			continue; // jQuery nunca se aplaza.
		}
		if ( isset( $ws->registered[ $h ] ) && ! $ws->get_data( $h, 'strategy' ) ) {
			$ws->add_data( $h, 'strategy', 'defer' );
		}
	}
	// Solo se mueven al pie si YA son defer/async (si un plugin lo cambia, no se tocan).
	foreach ( lm_rend_cfg_lista( 'diferir_pie', '' ) as $h ) {
		if ( isset( $ws->registered[ $h ] ) && in_array( $ws->get_data( $h, 'strategy' ), array( 'defer', 'async' ), true ) ) {
			$ws->add_data( $h, 'group', 1 );
		}
	}
}

/* =========================================================================
 * (2) LIBRERÍAS DEL EDITOR SOLO DONDE SE USAN
 * -------------------------------------------------------------------------
 * Algunos plugins (p. ej. GTM Kit) cargan en la ficha un script de bloques que
 * arrastra la cadena wp-data (~12 archivos). Ese script solo hace algo si la
 * página lleva alguno de los bloques de WooCommerce que vigila. Se cuenta lo
 * que de verdad sale en la página y, si no hay ninguno, se retira.
 * ========================================================================= */
$GLOBALS['lm_rend_bloque_usa_libs'] = false;
// Prioridad máxima: cuenta lo que de verdad sale en la página.
add_filter( 'render_block', 'lm_rend_vigila_bloques', PHP_INT_MAX, 2 );
function lm_rend_vigila_bloques( $html, $block ) {
	$n = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
	if ( '' !== $n && 0 === strpos( $n, 'woocommerce/' ) && '' !== trim( (string) $html ) ) {
		$usa = array(
			'woocommerce/cart', 'woocommerce/checkout', 'woocommerce/mini-cart', 'woocommerce/all-products',
			'woocommerce/product-collection', 'woocommerce/single-product', 'woocommerce/related-products',
			'woocommerce/add-to-cart-with-options', 'woocommerce/product-search',
		);
		if ( in_array( $n, $usa, true ) || 0 === strpos( $n, 'woocommerce/product-filter' ) ) {
			$GLOBALS['lm_rend_bloque_usa_libs'] = true;
		}
	}
	return $html;
}
add_action( 'wp_print_scripts', 'lm_rend_editor', 998 );
function lm_rend_editor(): void {
	if ( ! lm_rend_activo( 'editor' ) || lm_rend_zona_protegida() ) {
		return;
	}
	if ( ! function_exists( 'is_product' ) || ! is_product() || ! empty( $GLOBALS['lm_rend_bloque_usa_libs'] ) ) {
		return;
	}
	foreach ( lm_rend_cfg_lista( 'editor_handles', 'gtmkit-woocommerce-blocks' ) as $h ) {
		wp_dequeue_script( $h );
	}
}

/* =========================================================================
 * (3) IMAGEN PRINCIPAL (LCP): fetchpriority=high y sin lazy
 * -------------------------------------------------------------------------
 *  - Ficha: la imagen principal de la galería.
 *  - Tienda/categorías: las 2 primeras portadas de la rejilla; la 1ª con
 *    prioridad alta. Se les quita el lazy de WP y el de LiteSpeed
 *    (data-no-lazy) y se fija un «sizes» real.
 *  - Portada: la 1ª imagen del bloque indicado en «portada_bloque» (opcional).
 * ========================================================================= */
add_filter( 'woocommerce_gallery_image_html_attachment_image_params', 'lm_rend_lcp_ficha', 20, 4 );
function lm_rend_lcp_ficha( $params, $attachment_id = 0, $size = '', $main = false ) {
	static $hecho = false;
	if ( $hecho || ! $main || ! is_array( $params ) || ! lm_rend_activo( 'lcp' ) || ! function_exists( 'is_product' ) || ! is_product() ) {
		return $params;
	}
	$hecho                   = true;
	$params['fetchpriority'] = 'high';
	return $params;
}

/** Quita el lazy (WP y LiteSpeed) de una etiqueta <img> y, si se pide, le da prioridad alta. */
function lm_rend_img_sin_lazy( string $img, bool $alta, string $sizes = '' ): string {
	$img = preg_replace( '/\sloading="lazy"/', '', $img );
	$img = preg_replace( '/\sfetchpriority="[^"]*"/', '', $img );
	if ( '' !== $sizes ) {
		$img = preg_replace( '/\ssizes="[^"]*"/', ' sizes="' . esc_attr( $sizes ) . '"', $img );
	} else {
		$img = preg_replace( '/\ssizes="auto,\s*/', ' sizes="', $img );
	}
	$extra = ' data-no-lazy="1"' . ( $alta ? ' fetchpriority="high"' : '' );
	return (string) preg_replace( '/^<img\b/', '<img' . $extra, $img );
}

add_filter( 'render_block_woocommerce/product-image', 'lm_rend_lcp_tienda', 20, 2 );
function lm_rend_lcp_tienda( $html, $block ) {
	static $n = 0;
	if ( $n >= 2 || ! lm_rend_activo( 'lcp' ) || ! function_exists( 'is_shop' ) || ! ( is_shop() || is_product_taxonomy() ) ) {
		return $html;
	}
	if ( false === strpos( (string) $html, '<img' ) ) {
		return $html;
	}
	$n++;
	$alta = ( 1 === $n );
	return preg_replace_callback(
		'/<img\b[^>]*>/',
		function ( $m ) use ( $alta ) {
			return lm_rend_img_sin_lazy( $m[0], $alta, '(max-width: 781px) 46vw, 300px' );
		},
		(string) $html,
		1
	);
}

$GLOBALS['lm_rend_lcp_portada'] = false;
add_filter( 'render_block', 'lm_rend_lcp_portada', 20, 2 );
function lm_rend_lcp_portada( $html, $block ) {
	$nombre = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
	if ( '' === $nombre ) {
		return $html;
	}
	$portada = trim( (string) lm_rend_cfg( 'portada_bloque', '' ) );
	$banner  = trim( (string) lm_rend_cfg( 'portada_banner_bloque', '' ) );
	if ( '' !== $portada && $nombre === $portada && empty( $GLOBALS['lm_rend_lcp_portada'] ) && lm_rend_activo( 'lcp' ) && is_front_page() && false !== strpos( (string) $html, '<img' ) ) {
		$GLOBALS['lm_rend_lcp_portada'] = true;
		return preg_replace_callback(
			'/<img\b[^>]*>/',
			function ( $m ) {
				return lm_rend_img_sin_lazy( $m[0], true );
			},
			(string) $html,
			1
		);
	}
	// El bloque de banners, más abajo, deja de pedir prioridad alta para no competir con la portada.
	if ( '' !== $banner && $nombre === $banner && ! empty( $GLOBALS['lm_rend_lcp_portada'] ) && lm_rend_activo( 'lcp' ) && is_front_page() ) {
		return str_replace( ' fetchpriority="high"', '', (string) $html );
	}
	return $html;
}
