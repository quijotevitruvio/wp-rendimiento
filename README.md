# wp-rendimiento

Paquete de *mu-plugins* para WordPress + WooCommerce que mejora la velocidad percibida sin quitar plugins: aplaza scripts, prioriza la imagen principal, quita el emoji de WordPress, precarga la página siguiente, reserva espacio para evitar saltos de diseño, limpia la base de datos cada semana y, si lo enciendes, deja la ficha de producto reducida.

> Versión 1.0.0 · GPL-2.0-or-later · [README in English](README.en.md)

## Para quién
Tiendas WooCommerce pequeñas o medianas (hosting compartido, LiteSpeed) cuyo dueño o técnico quiere ganancias de velocidad con interruptores por pieza y sin tocar el tema.

## Requisitos
- WordPress 6.0+ y PHP 7.4+ (usa tipos de retorno y `Throwable`). La precarga usa las Speculation Rules del núcleo (WordPress 6.8+; en versiones anteriores no hace nada).
- WooCommerce para las piezas de ficha, tienda y limpieza de sesiones.
- Opcional: LiteSpeed Cache (los comandos de purga lo suponen) y WP-CLI para los interruptores.

## Instalación
1. Copia el contenido de `mu-plugins/` (`wp-rendimiento.php` y la carpeta `wp-rendimiento/`) a `wp-content/mu-plugins/`.
2. No hay que activar nada: los mu-plugins cargan solos.
3. Purga la caché de página.

## Piezas e interruptores
Cada pieza se apaga con una opción: `wp option update <nombre> 1`. Se deshace con `wp option delete <nombre>`. Cualquier valor distinto de vacío o `0` apaga.

| Pieza | Qué hace | Interruptor |
|---|---|---|
| `principal` | Aplaza scripts del `<head>`; retira la librería de bloques de GTM Kit en la ficha si no se usa; `fetchpriority=high` y sin lazy en la imagen principal (ficha, 2 primeras de la tienda, y opcionalmente la 1ª de portada) | `lm_rendimiento_principal_off` (o `lm_rendimiento_off`) |
| sub-piezas de `principal` | (1) diferir, (2) editor, (3) LCP | `lm_rendimiento_off_diferir`, `_editor`, `_lcp` |
| `emoji` | Quita wp-emoji y el dns-prefetch a s.w.org | `lm_rendimiento_emoji_off` |
| `precarga` | Speculation Rules de `conservative` a `moderate` (prefetch al pasar el cursor o tocar); excluye carrito, pago, mi cuenta y add-to-cart | `lm_rendimiento_precarga_off` |
| `sinbrincos` | Reserva la proporción de una imagen (p. ej. el logo) para evitar saltos | `lm_rendimiento_sinbrincos_off` |
| `dbclean` | Cada semana borra transients y sesiones de WooCommerce **vencidos** | `lm_rendimiento_dbclean_off` |
| `pdp` | Ficha reducida: solo las pestañas elegidas y sin bloques extra. **Apagada por defecto** | se enciende con `lm_rendimiento_pdp_on`; se apaga con `lm_rendimiento_pdp_off` o `lm_pdp_solo_tres_off` |
| todo | Apaga el paquete entero | `lm_rendimiento_todo_off` |

Nota: `lm_pdp_solo_tres_off` apaga la pieza con solo existir (aunque valga `0`); bórrala para reactivarla.

`principal` y `pdp` no actúan en carrito, pago ni mi cuenta (`principal` tampoco en admin, AJAX, cron ni REST).

## Ajustes
Cada ajuste se lee primero de una constante (p. ej. en `wp-config.php`: `define( 'LM_REND_DIFERIR_HANDLES', 'a,b' );`) y, si no existe, de la opción (`wp option update lm_rendimiento_<nombre> <valor>`). Las listas van separadas por comas.

| Ajuste | Opción / constante | Por defecto | Qué hace |
|---|---|---|---|
| Scripts a aplazar | `lm_rendimiento_diferir_handles` / `LM_REND_DIFERIR_HANDLES` | `react,react-jsx-runtime,react-dom,wp-escape-html,wp-element` | Handles de script a los que se pide `defer` (jQuery nunca se aplaza) |
| Scripts al pie | `lm_rendimiento_diferir_pie` / `LM_REND_DIFERIR_PIE` | vacío | Handles que ya son `defer`/`async` y se mueven al pie |
| Librerías del editor | `lm_rendimiento_editor_handles` / `LM_REND_EDITOR_HANDLES` | `gtmkit-woocommerce-blocks` | Handles que se retiran de la ficha si la página no lleva bloques de WooCommerce que los usen |
| Bloque de portada | `lm_rendimiento_portada_bloque` / `LM_REND_PORTADA_BLOQUE` | vacío (no se toca) | Nombre del bloque (`espacio/nombre`) cuya primera imagen lleva prioridad alta en la portada |
| Bloque de banners | `lm_rendimiento_portada_banner_bloque` / `LM_REND_PORTADA_BANNER_BLOQUE` | vacío | Bloque que deja de pedir prioridad alta en la portada |
| Rutas sin precarga | `lm_rendimiento_precarga_excluir` / `LM_REND_PRECARGA_EXCLUIR` | vacío | Rutas extra a excluir (p. ej. `/area-privada/*`); carrito, pago y cuenta se excluyen siempre con su ruta real |
| Selector del logo | `lm_rendimiento_sinbrincos_selector` / `LM_REND_SINBRINCOS_SELECTOR` | vacío | Selector CSS de la imagen a la que se reserva proporción (p. ej. `.custom-logo`) |
| Proporción | `lm_rendimiento_sinbrincos_ratio` / `LM_REND_SINBRINCOS_RATIO` | `1 / 1` | Proporción CSS de esa imagen |
| Optimizar `wp_options` | `lm_rendimiento_dbclean_optimizar` / `LM_REND_DBCLEAN_OPTIMIZAR` | `0` | `1` hace `OPTIMIZE TABLE` de `wp_options` tras la limpieza |
| Retención de Action Scheduler | `lm_rendimiento_dbclean_as_dias` / `LM_REND_DBCLEAN_AS_DIAS` | `0` (no se toca) | Días de retención de Action Scheduler (entero > 0) |
| Encender ficha reducida | `lm_rendimiento_pdp_on` / `LM_REND_PDP_ON` | `0` (apagada) | `1` enciende la pieza `pdp` |
| Pestañas que quedan | `lm_rendimiento_pdp_tabs` / `LM_REND_PDP_TABS` | `description,reviews` | Pestañas de WooCommerce que se conservan |
| Bloques que se conservan | `lm_rendimiento_pdp_conservar` / `LM_REND_PDP_CONSERVAR` | vacío | Nombres de archivo PHP (p. ej. `mi-plugin.php`) cuyos closures colgados de `woocommerce_after_single_product_summary` no se quitan |
| Selectores de los bloques | `lm_rendimiento_pdp_selectores` / `LM_REND_PDP_SELECTORES` | `section.up-sells, section.related` | Debajo del último de ellos se muestra la reseña a demanda |

## Limpieza de la base de datos (`dbclean`)
- Por defecto **solo** borra transients (y sus *timeouts*) y sesiones de WooCommerce cuya fecha de vencimiento ya pasó: datos que WordPress y WooCommerce ya consideran muertos. Todo el SQL usa `$wpdb->prepare`.
- `OPTIMIZE TABLE` y el cambio de retención de Action Scheduler están **apagados** hasta que los pidas (ver Ajustes).
- Corre una vez por semana con WP-Cron (evento `lm_db_cleanup_event`); el último informe queda en la opción `lm_db_autoclean_last`. Haz una copia de la base antes de activar el paquete por primera vez.

## Cómo deshacer
- Una pieza: `wp option delete <interruptor>`. Todo: borra el archivo y la carpeta.
- `dbclean` solo deja su evento programado: `wp cron event delete lm_db_cleanup_event`. Lo que borró (transients y sesiones vencidos) no se recupera.
- Tras cualquier cambio, purga la caché: `wp eval "do_action('litespeed_purge_all');"`.

## FAQ
**¿Rompe el carrito o el pago?** Está pensado para no tocarlos (zona protegida) y jQuery nunca se aplaza. Aun así, prueba antes en un staging.
**¿Y si no tengo GTM Kit?** Esa ruta no encuentra nada y no hace nada.
**¿Choca con LiteSpeed?** El quitado de emoji coincide con el de LiteSpeed; no se pisan.
**¿Cómo detecto la pieza que falla?** Apágalas una a una. Un error dentro de una pieza se registra con `error_log` y no tumba las demás.

## Limitaciones
- Los prefijos `lm_` (funciones, opciones) se conservan por compatibilidad.
- `pdp` quita por defecto todos los bloques de `woocommerce_after_single_product_summary` salvo las pestañas; úsala solo si sabes qué cuelga ahí.
- Sin pruebas automatizadas; medido a mano con Lighthouse.

## Licencia
GPL-2.0-or-later. Ver `LICENSE`.

## Autor y apoyo
Andrés Valencia Tobón · [GitHub](https://github.com/quijotevitruvio) · [LinkedIn](https://www.linkedin.com/in/andr%C3%A9s-valencia-tob%C3%B3n/)
Apoya / Support: https://paypal.me/bibliotecologo
Desarrollado para librosmedellin.com.
