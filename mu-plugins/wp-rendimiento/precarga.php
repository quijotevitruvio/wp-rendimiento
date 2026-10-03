<?php
/**
 * Pieza: Precarga de navegación.
 * Qué hace: Sube las Speculation Rules de WordPress de «conservative» a «moderate»: la página siguiente empieza a cargar al pasar el cursor o tocar.
 * Dónde se nota: En el <head> (script speculationrules). Excluye carrito, pago, mi cuenta y añadir al carrito.
 * Ajuste: lm_rendimiento_precarga_excluir (constante LM_REND_PRECARGA_EXCLUIR) — rutas extra a excluir, separadas por comas, p. ej. «/area-privada/*». Por defecto vacío.
 * Cómo se apaga: wp option update lm_rendimiento_precarga_off 1. Cómo se deshace: wp option delete lm_rendimiento_precarga_off.
 * Autor: Andrés Valencia Tobón.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'wp_speculation_rules_configuration',
	function ( $config ) {
		if ( ! is_array( $config ) ) {
			return $config;
		}
		$config['mode']      = 'prefetch';   // Descarga el HTML, no ejecuta la página.
		$config['eagerness'] = 'moderate';   // Al pasar el cursor / tocar.
		return $config;
	}
);

/** Rutas que nunca se deben precargar: las páginas de WooCommerce (con su ruta real) más las habituales. */
add_filter(
	'wp_speculation_rules_href_exclude_paths',
	function ( $paths ) {
		$extra = array( '/cart/*', '/checkout/*', '/my-account/*', '/*add-to-cart=*', '/*wc-ajax=*' );
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			foreach ( array( 'cart', 'checkout', 'myaccount' ) as $p ) {
				$ruta = wp_parse_url( (string) wc_get_page_permalink( $p ), PHP_URL_PATH );
				if ( is_string( $ruta ) && '' !== trim( $ruta, '/' ) ) {
					$extra[] = '/' . trim( $ruta, '/' ) . '/*';
				}
			}
		}
		foreach ( lm_rend_cfg_lista( 'precarga_excluir', '' ) as $r ) {
			$extra[] = $r;
		}
		return array_values( array_unique( array_merge( (array) $paths, $extra ) ) );
	}
);
