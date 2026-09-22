<?php
/**
 * Plugin Name: PowerPress Cookie Guard
 * Description: Temporary compatibility guard for PowerPress pp_event_* cookies that can accumulate and cause HTTP 400 request-header errors.
 * Version: 1.0.0
 * Author: Andrew Rhynes
 * Author URI: https://github.com/eagle4life69
 * Plugin URI: https://github.com/eagle4life69/powerpress-cookie-guard/
 * GitHub Plugin URI: https://github.com/eagle4life69/powerpress-cookie-guard/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: powerpress-cookie-guard
 */

if (!defined('ABSPATH')) exit;

define('PPCG_VERSION', '1.0.0');
define('PPCG_PLUGIN_FILE', __FILE__);
define('PPCG_COOKIE_PREFIX', 'pp_event_');
define('PPCG_MAX_EVENT_COOKIES', 6);

require_once __DIR__ . '/github-updater.php';

/**
 * Delete a PowerPress event cookie using the same site-wide path PowerPress uses.
 */
function ppcg_expire_cookie($name) {
    if (headers_sent()) {
        return false;
    }

    setcookie($name, '', time() - YEAR_IN_SECONDS, '/', '', is_ssl(), false);
    unset($_COOKIE[$name]);
    return true;
}

/**
 * Prevent PowerPress pp_event_* cookies from growing without bound.
 *
 * We deliberately keep the newest PPCG_MAX_EVENT_COOKIES cookies. PowerPress
 * uses these cookies to pass save success/error notices to its editor UI, so
 * deleting every event cookie immediately could suppress a fresh notice.
 * PHP preserves the Cookie header order in $_COOKIE; browsers normally append
 * newly-created cookies after existing cookies with the same path, making the
 * end of this list the safest events to preserve.
 */
function ppcg_guard_powerpress_event_cookies() {
    if (empty($_COOKIE) || !is_array($_COOKIE)) {
        return;
    }

    $event_names = [];
    foreach (array_keys($_COOKIE) as $name) {
        if (strpos($name, PPCG_COOKIE_PREFIX) === 0) {
            $event_names[] = $name;
        }
    }

    $count = count($event_names);
    if ($count <= PPCG_MAX_EVENT_COOKIES) {
        return;
    }

    $remove_count = $count - PPCG_MAX_EVENT_COOKIES;
    $to_remove = array_slice($event_names, 0, $remove_count);
    $removed = 0;

    foreach ($to_remove as $name) {
        if (ppcg_expire_cookie($name)) {
            $removed++;
        }
    }

    if ($removed > 0) {
        $total = (int) get_option('ppcg_total_cleaned', 0);
        update_option('ppcg_total_cleaned', $total + $removed, false);
        update_option('ppcg_last_cleaned', current_time('mysql'), false);
        update_option('ppcg_last_cleaned_count', $removed, false);
    }
}
add_action('plugins_loaded', 'ppcg_guard_powerpress_event_cookies', 1);

/**
 * Admin notice/status so we can confirm the workaround is active.
 */
function ppcg_admin_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->id !== 'plugins') {
        return;
    }

    $current = 0;
    foreach (array_keys($_COOKIE) as $name) {
        if (strpos($name, PPCG_COOKIE_PREFIX) === 0) {
            $current++;
        }
    }

    $total = (int) get_option('ppcg_total_cleaned', 0);
    $last = get_option('ppcg_last_cleaned', 'Never');

    echo '<div class="notice notice-info"><p><strong>PowerPress Cookie Guard:</strong> Active. ';
    echo 'Current PowerPress event cookies: ' . esc_html((string) $current) . ' (limit ' . esc_html((string) PPCG_MAX_EVENT_COOKIES) . '). ';
    echo 'Cookies cleaned since activation: ' . esc_html((string) $total) . '. Last cleanup: ' . esc_html($last) . '.</p></div>';
}
add_action('admin_notices', 'ppcg_admin_notice');
