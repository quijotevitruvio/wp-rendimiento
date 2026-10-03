<?php
/**
 * Pieza: Limpieza semanal de la base de datos.
 * Qué hace: Cada semana borra SOLO transients y sesiones de WooCommerce que ya vencieron (datos que WordPress/WooCommerce ya darían por muertos).
 *   Opcional y apagado por defecto: OPTIMIZE TABLE de wp_options y reducir la retención de Action Scheduler.
 * Dónde se nota: No se ve: corre por WP-Cron (evento lm_db_cleanup_event); último informe en la opción lm_db_autoclean_last.
 * Ajustes (opción lm_rendimiento_<nombre> o constante LM_REND_<NOMBRE>):
 *   dbclean_optimizar — 1 para hacer OPTIMIZE TABLE de wp_options tras limpiar. Por defecto 0 (no se hace).
 *   dbclean_as_dias   — días de retención de Action Scheduler (entero > 0). Por defecto 0 = no se toca la retención de WooCommerce.
 * Cómo se apaga: wp option update lm_rendimiento_dbclean_off 1. Cómo se deshace: wp option delete lm_rendimiento_dbclean_off
 *   y wp cron event delete lm_db_cleanup_event.
 * Autor: Andrés Valencia Tobón.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 1) Retención de Action Scheduler: solo si el dueño la fija (por defecto no se cambia).
 */
add_filter( 'action_scheduler_retention_period', function ( $actual ) {
	$dias = (int) lm_rend_cfg( 'dbclean_as_dias', 0 );
	return $dias > 0 ? $dias * DAY_IN_SECONDS : $actual;
} );

/**
 * 2) Asegurar que exista un intervalo cron semanal.
 */
add_filter( 'cron_schedules', function ( $schedules ) {
	if ( ! isset( $schedules['lm_weekly'] ) ) {
		$schedules['lm_weekly'] = array(
			'interval' => 7 * DAY_IN_SECONDS,
			'display'  => 'Once a week',
		);
	}
	return $schedules;
} );

/**
 * 3) Programar el evento de limpieza si no está agendado.
 */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'lm_db_cleanup_event' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'lm_weekly', 'lm_db_cleanup_event' );
	}
} );

/**
 * 4) La limpieza en sí. Solo borra datos EXPIRADOS/VENCIDOS; nunca toca datos vivos.
 */
add_action( 'lm_db_cleanup_event', 'lm_db_autoclean_run' );

function lm_db_autoclean_run() {
	global $wpdb;

	$report = array( 'ts' => current_time( 'mysql' ) );
	$now    = time();

	// a) Transients expirados (método nativo de WordPress: borra valor + timeout).
	if ( function_exists( 'delete_expired_transients' ) ) {
		delete_expired_transients( true );
	}

	// b) Red de seguridad: pares transient/timeout expirados que el método nativo no alcance.
	$report['transients'] = (int) $wpdb->query(
		$wpdb->prepare(
			"DELETE a, b FROM {$wpdb->options} a
			 JOIN {$wpdb->options} b ON b.option_name = CONCAT( '_transient_', SUBSTRING( a.option_name, 20 ) )
			 WHERE a.option_name LIKE %s AND a.option_value < %d",
			$wpdb->esc_like( '_transient_timeout_' ) . '%',
			$now
		)
	);

	$report['site_transients'] = (int) $wpdb->query(
		$wpdb->prepare(
			"DELETE a, b FROM {$wpdb->options} a
			 JOIN {$wpdb->options} b ON b.option_name = CONCAT( '_site_transient_', SUBSTRING( a.option_name, 25 ) )
			 WHERE a.option_name LIKE %s AND a.option_value < %d",
			$wpdb->esc_like( '_site_transient_timeout_' ) . '%',
			$now
		)
	);

	// c) Sesiones de WooCommerce ya vencidas.
	$sessions = $wpdb->prefix . 'woocommerce_sessions';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $sessions ) ) ) === $sessions ) {
		$report['wc_sessions'] = (int) $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$sessions} WHERE session_expiry < %d", $now ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla propio ($wpdb->prefix).
		);
	}

	// d) Compactar wp_options tras los borrados: solo si se pidió.
	if ( lm_rend_cfg( 'dbclean_optimizar', 0 ) ) {
		$wpdb->query( "OPTIMIZE TABLE {$wpdb->options}" ); // phpcs:ignore WordPress.DB -- tabla propia, sin datos externos.
		$report['optimize'] = 1;
	}

	// Guardar un pequeño registro de la última ejecución (sin autoload).
	update_option( 'lm_db_autoclean_last', $report, false );
}
