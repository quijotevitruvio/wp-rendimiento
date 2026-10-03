# wp-rendimiento

A bundle of *mu-plugins* for WordPress + WooCommerce that improves perceived speed without removing plugins: defers scripts, prioritises the main image, removes WordPress emoji, prefetches the next page, reserves layout space, cleans the database weekly and, if you turn it on, trims the product page.

> Version 1.0.0 · GPL-2.0-or-later · [README en español](README.md) (code comments are in Spanish)

## Who it is for
Small and mid-size WooCommerce shops (shared hosting, LiteSpeed) whose owner or developer wants speed gains with per-piece switches and no theme changes.

## Requirements
- WordPress 6.0+ and PHP 7.4+ (uses return types and `Throwable`). Prefetch relies on core Speculation Rules (WordPress 6.8+; it does nothing on older versions).
- WooCommerce for the product, shop and session-cleanup pieces.
- Optional: LiteSpeed Cache (purge commands assume it) and WP-CLI for the switches.

## Installation
1. Copy the contents of `mu-plugins/` (`wp-rendimiento.php` and the `wp-rendimiento/` folder) to `wp-content/mu-plugins/`.
2. Nothing to activate: mu-plugins load automatically.
3. Purge the page cache.

## Pieces and switches
Each piece is turned off with an option: `wp option update <name> 1`. Undo with `wp option delete <name>`. Any value other than empty or `0` turns it off.

| Piece | What it does | Switch |
|---|---|---|
| `principal` | Defers `<head>` scripts; drops GTM Kit's blocks library on product pages when unused; `fetchpriority=high` and no lazy-loading on the main image (product page, first 2 shop items, optionally the first home item) | `lm_rendimiento_principal_off` (or `lm_rendimiento_off`) |
| `principal` sub-pieces | (1) defer, (2) editor libs, (3) LCP | `lm_rendimiento_off_diferir`, `_editor`, `_lcp` |
| `emoji` | Removes wp-emoji and the dns-prefetch to s.w.org | `lm_rendimiento_emoji_off` |
| `precarga` | Speculation Rules from `conservative` to `moderate` (prefetch on hover/tap); excludes cart, checkout, account and add-to-cart | `lm_rendimiento_precarga_off` |
| `sinbrincos` | Reserves an image's aspect ratio (e.g. the logo) to avoid layout shift | `lm_rendimiento_sinbrincos_off` |
| `dbclean` | Weekly: deletes **expired** transients and WooCommerce sessions | `lm_rendimiento_dbclean_off` |
| `pdp` | Trimmed product page: only the chosen tabs and no extra blocks. **Off by default** | turn on with `lm_rendimiento_pdp_on`; off with `lm_rendimiento_pdp_off` or `lm_pdp_solo_tres_off` |
| all | Turns the whole bundle off | `lm_rendimiento_todo_off` |

Note: `lm_pdp_solo_tres_off` turns the piece off by merely existing (even if `0`); delete it to re-enable.

`principal` and `pdp` never act on cart, checkout or account pages (`principal` also skips admin, AJAX, cron and REST).

## Settings
Each setting is read first from a constant (e.g. in `wp-config.php`: `define( 'LM_REND_DIFERIR_HANDLES', 'a,b' );`) and, if absent, from the option (`wp option update lm_rendimiento_<name> <value>`). Lists are comma-separated.

| Setting | Option / constant | Default | What it does |
|---|---|---|---|
| Scripts to defer | `lm_rendimiento_diferir_handles` / `LM_REND_DIFERIR_HANDLES` | `react,react-jsx-runtime,react-dom,wp-escape-html,wp-element` | Script handles that get `defer` (jQuery is never deferred) |
| Scripts to footer | `lm_rendimiento_diferir_pie` / `LM_REND_DIFERIR_PIE` | empty | Handles that are already `defer`/`async` and are moved to the footer |
| Editor libraries | `lm_rendimiento_editor_handles` / `LM_REND_EDITOR_HANDLES` | `gtmkit-woocommerce-blocks` | Handles dequeued on product pages that carry no WooCommerce block needing them |
| Home block | `lm_rendimiento_portada_bloque` / `LM_REND_PORTADA_BLOQUE` | empty (untouched) | Block name (`namespace/name`) whose first image gets high priority on the front page |
| Banner block | `lm_rendimiento_portada_banner_bloque` / `LM_REND_PORTADA_BANNER_BLOQUE` | empty | Block that stops requesting high priority on the front page |
| Paths without prefetch | `lm_rendimiento_precarga_excluir` / `LM_REND_PRECARGA_EXCLUIR` | empty | Extra paths to exclude (e.g. `/members/*`); cart, checkout and account are always excluded using their real path |
| Logo selector | `lm_rendimiento_sinbrincos_selector` / `LM_REND_SINBRINCOS_SELECTOR` | empty | CSS selector of the image whose ratio is reserved (e.g. `.custom-logo`) |
| Aspect ratio | `lm_rendimiento_sinbrincos_ratio` / `LM_REND_SINBRINCOS_RATIO` | `1 / 1` | CSS aspect ratio for that image |
| Optimize `wp_options` | `lm_rendimiento_dbclean_optimizar` / `LM_REND_DBCLEAN_OPTIMIZAR` | `0` | `1` runs `OPTIMIZE TABLE` on `wp_options` after cleaning |
| Action Scheduler retention | `lm_rendimiento_dbclean_as_dias` / `LM_REND_DBCLEAN_AS_DIAS` | `0` (untouched) | Action Scheduler retention in days (integer > 0) |
| Turn on trimmed product page | `lm_rendimiento_pdp_on` / `LM_REND_PDP_ON` | `0` (off) | `1` turns the `pdp` piece on |
| Tabs kept | `lm_rendimiento_pdp_tabs` / `LM_REND_PDP_TABS` | `description,reviews` | WooCommerce tabs that are kept |
| Blocks kept | `lm_rendimiento_pdp_conservar` / `LM_REND_PDP_CONSERVAR` | empty | PHP file names (e.g. `my-plugin.php`) whose closures hooked to `woocommerce_after_single_product_summary` are not removed |
| Block selectors | `lm_rendimiento_pdp_selectores` / `LM_REND_PDP_SELECTORES` | `section.up-sells, section.related` | The on-demand reviews panel is shown below the last of them |

## Database cleanup (`dbclean`)
- By default it **only** deletes transients (and their timeouts) and WooCommerce sessions whose expiry has already passed: data WordPress and WooCommerce already consider dead. All SQL goes through `$wpdb->prepare`.
- `OPTIMIZE TABLE` and the Action Scheduler retention change are **off** until you ask for them (see Settings).
- It runs once a week via WP-Cron (event `lm_db_cleanup_event`); the last report is kept in option `lm_db_autoclean_last`. Back up the database before enabling the bundle for the first time.

## How to undo
- One piece: `wp option delete <switch>`. Everything: delete the file and the folder.
- `dbclean` only leaves its scheduled event: `wp cron event delete lm_db_cleanup_event`. What it deleted (expired transients and sessions) cannot be recovered.
- After any change, purge the cache: `wp eval "do_action('litespeed_purge_all');"`.

## FAQ
**Will it break cart or checkout?** It is designed not to touch them (protected zone) and never defers jQuery. Still, test on staging first.
**What if I lack GTM Kit?** That code path finds nothing and does nothing.
**Does it clash with LiteSpeed?** Emoji removal overlaps with LiteSpeed's; they do not conflict.
**How do I find a failing piece?** Switch pieces off one by one. An error inside a piece is logged with `error_log` and does not take the others down.

## Limitations
- The `lm_` prefixes (functions, options) are kept for compatibility.
- By default `pdp` removes every block hooked to `woocommerce_after_single_product_summary` except the tabs; use it only if you know what hangs there.
- No automated tests; measured by hand with Lighthouse.

## License
GPL-2.0-or-later. See `LICENSE`.

## Author and support
Andrés Valencia Tobón · [GitHub](https://github.com/quijotevitruvio) · [LinkedIn](https://www.linkedin.com/in/andr%C3%A9s-valencia-tob%C3%B3n/)
Apoya / Support: https://paypal.me/bibliotecologo
Developed for librosmedellin.com.
