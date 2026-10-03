<?php
/**
 * Plugin Name: WP Rendimiento
 * Description: Velocidad y orden de carga para WordPress + WooCommerce: scripts aplazados, imagen principal, precarga, sin emoji, sin saltos de diseño, limpieza semanal de la base y ficha de producto reducida. Cada pieza tiene su interruptor.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Andrés Valencia Tobón
 * Author URI: https://github.com/quijotevitruvio
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-rendimiento
 *
 * LinkedIn: https://www.linkedin.com/in/andr%C3%A9s-valencia-tob%C3%B3n/
 * Donate link: https://paypal.me/bibliotecologo
 * Desarrollado para librosmedellin.com.
 *
 * Piezas (cada una con su interruptor; forma: wp option update <nombre> 1, deshacer: wp option delete <nombre>):
 *   lm_rendimiento_dbclean_off · lm_rendimiento_emoji_off · lm_rendimiento_precarga_off
 *   lm_rendimiento_principal_off (o lm_rendimiento_off y lm_rendimiento_off_<función>)
 *   lm_rendimiento_sinbrincos_off
 *   pdp: apagada por defecto; se enciende con lm_rendimiento_pdp_on = 1
 *   lm_rendimiento_todo_off → apaga todo el paquete.
 * Tras cambiar uno, purga la caché de página (con LiteSpeed: wp eval "do_action('litespeed_purge_all');").
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'LM_RENDIMIENTO_PAQUETE' ) ) {
	return;
}
define( 'LM_RENDIMIENTO_PAQUETE', '1.0.0' );

// ── Ayudas comunes (se definen una sola vez) ─────────────────────────────────
if ( ! function_exists( 'lm_pieza_apagada' ) ) {
	/** ¿Está apagada la pieza? Mira lm_<paquete>_<pieza>_off y lm_<paquete>_todo_off (una sola consulta por carga). */
	function lm_pieza_apagada( $paquete, $pieza ) {
		static $apagados = null;
		if ( null === $apagados ) {
			global $wpdb;
			$apagados = array();
			// Consulta fija, sin datos externos.
			$filas = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'lm\\_%\\_off'", ARRAY_A ); // phpcs:ignore WordPress.DB
			foreach ( (array) $filas as $f ) {
				if ( '' !== $f['option_value'] && '0' !== $f['option_value'] ) {
					$apagados[ $f['option_name'] ] = true;
				}
			}
		}
		return isset( $apagados[ "lm_{$paquete}_todo_off" ] ) || isset( $apagados[ "lm_{$paquete}_{$pieza}_off" ] );
	}
}
if ( ! function_exists( 'lm_paquete_cargar' ) ) {
	/**
	 * Carga las piezas de un paquete. $piezas = pieza => array( archivo, función_centinela ).
	 * Se salta la pieza si está apagada, si el archivo no existe o si su función ya está definida.
	 */
	function lm_paquete_cargar( $carpeta, $paquete, $piezas ) {
		foreach ( $piezas as $pieza => $d ) {
			if ( lm_pieza_apagada( $paquete, $pieza ) || ( $d[1] && function_exists( $d[1] ) ) ) {
				continue;
			}
			$ruta = $carpeta . '/' . $d[0];
			if ( ! is_readable( $ruta ) ) {
				continue;
			}
			try {
				require_once $ruta;
			} catch ( Throwable $e ) {
				error_log( "WP Rendimiento ({$paquete}): la pieza {$pieza} falló: " . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			}
		}
	}
}

if ( ! function_exists( 'lm_rend_cfg' ) ) {
	/**
	 * Lee un ajuste: primero la constante LM_REND_<NOMBRE> (p. ej. en wp-config.php), luego la opción
	 * lm_rendimiento_<nombre>, y si no existe devuelve $defecto.
	 */
	function lm_rend_cfg( $nombre, $defecto = '' ) {
		$const = 'LM_REND_' . strtoupper( $nombre );
		if ( defined( $const ) ) {
			return constant( $const );
		}
		return get_option( 'lm_rendimiento_' . $nombre, $defecto );
	}
}
if ( ! function_exists( 'lm_rend_cfg_lista' ) ) {
	/** Ajuste de tipo lista separada por comas → array de textos limpios. */
	function lm_rend_cfg_lista( $nombre, $defecto = '' ) {
		$v = lm_rend_cfg( $nombre, $defecto );
		if ( is_array( $v ) ) {
			$v = implode( ',', $v );
		}
		$partes = array_map( 'trim', explode( ',', (string) $v ) );
		return array_values( array_filter( $partes, 'strlen' ) );
	}
}

lm_paquete_cargar(
	__DIR__ . '/wp-rendimiento',
	'rendimiento',
	array(
		'dbclean'    => array( 'db-autoclean.php', 'lm_db_autoclean_run' ),
		'pdp'        => array( 'ficha-reducida.php', 'lm_pdp3_activo' ),
		'emoji'      => array( 'sin-emoji.php', '' ),
		'precarga'   => array( 'precarga.php', '' ),
		'principal'  => array( 'principal.php', 'lm_rend_activo' ),
		'sinbrincos' => array( 'sin-brincos.php', '' ),
	)
);
