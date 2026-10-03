# Changelog

## 1.0.0
- Primera versión pública: piezas `principal`, `emoji`, `precarga`, `sinbrincos`, `dbclean` y `pdp`, con interruptor por pieza y `lm_rendimiento_todo_off`.
- Ajustes por opción o constante (`lm_rendimiento_*` / `LM_REND_*`) en lugar de nombres y slugs fijos del sitio de origen.
- Se retiran las sub-piezas `envio` y `escuchar` (dependían de plugins privados).
- `dbclean`: `OPTIMIZE TABLE` y la retención de Action Scheduler pasan a ser opcionales y están apagados por defecto.
- `pdp`: apagada por defecto; pestañas, bloques conservados y selectores configurables.
- `precarga`: las rutas de carrito, pago y cuenta se obtienen de WooCommerce.
