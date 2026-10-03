<?php
/**
 * Pieza: Sin emoji de WordPress.
 * Qué hace: Quita wp-emoji (script y estilos de twemoji) y el dns-prefetch a s.w.org: ~23 peticiones menos.
 * Dónde se nota: En el <head> de todas las páginas. Solape conocido: LiteSpeed (optm-emoji_rm) hace lo mismo; no se pisan.
 * Cómo se apaga: wp option update lm_rendimiento_emoji_off 1.
 * Cómo se deshace: wp option delete lm_rendimiento_emoji_off.
 * Autor: Andrés Valencia Tobón.
 */

defined( 'ABSPATH' ) || exit;

// ── Quitar wp-emoji (twemoji desde s.w.org) ──────────────────────────────────
add_action( 'init', function () {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
} );
add_filter( 'tiny_mce_plugins', function ( $plugins ) {
	return is_array( $plugins ) ? array_diff( $plugins, array( 'wpemoji' ) ) : $plugins;
} );
add_filter( 'wp_resource_hints', function ( $urls, $relation ) {
	if ( 'dns-prefetch' === $relation ) {
		$urls = array_filter( $urls, function ( $u ) {
			return ! ( is_string( $u ) && false !== strpos( $u, 's.w.org' ) );
		} );
	}
	return $urls;
}, 10, 2 );
