<?php
/**
 * Pieza: Sin brincos (reserva de espacio).
 * Qué hace: Reserva la proporción de una imagen (p. ej. el logo) antes de que cargue, para que la página no se desplace; y evita que las imágenes de bloque con ancho pero sin alto colapsen.
 * Dónde se nota: En todas las páginas (estilo #lm-sin-brincos en el <head>).
 * Ajustes (opción lm_rendimiento_<nombre> o constante LM_REND_<NOMBRE>):
 *   sinbrincos_selector — selector CSS de la imagen a reservar, p. ej. «.custom-logo». Por defecto vacío (solo la red de seguridad).
 *   sinbrincos_ratio    — proporción CSS, p. ej. «1 / 1» o «3 / 1». Por defecto «1 / 1».
 * Cómo se apaga: wp option update lm_rendimiento_sinbrincos_off 1. Cómo se deshace: wp option delete lm_rendimiento_sinbrincos_off.
 * Autor: Andrés Valencia Tobón.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_head',
	function () {
		$sel   = trim( (string) lm_rend_cfg( 'sinbrincos_selector', '' ) );
		$ratio = trim( (string) lm_rend_cfg( 'sinbrincos_ratio', '1 / 1' ) );
		// Lista blanca de caracteres: nada de llaves, ni «<», ni «;» (no se puede salir del bloque CSS).
		$sel_ok = '' !== $sel && (bool) preg_match( '/^[A-Za-z0-9_.#\[\]=\*\^\$\|~:>+, \-]+$/', $sel );
		if ( ! preg_match( '/^[0-9.]+\s*(\/\s*[0-9.]+)?$/', $ratio ) ) {
			$ratio = '1 / 1';
		}
		?>
<style id="lm-sin-brincos">
<?php if ( $sel_ok ) : ?>
/* Reserva el hueco de la imagen antes de que llegue. */
<?php echo $sel; // phpcs:ignore WordPress.Security.EscapeOutput -- lista blanca de caracteres validada arriba. ?> {
	aspect-ratio: <?php echo esc_html( $ratio ); ?>;
	height: auto;
}
<?php endif; ?>
/* Red de seguridad: cualquier imagen de bloque con ancho pero sin alto
   conserva su proporción en vez de colapsar a cero. */
figure.wp-block-image img[style*="width:"]:not([height]) {
	height: auto;
}
</style>
		<?php
	},
	5
);
