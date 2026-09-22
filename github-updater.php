<?php
/**
 * Native GitHub updater for PowerPress Cookie Guard.
 * Uses the latest published GitHub Release as the production update source.
 */

if (!defined('ABSPATH')) exit;

function ppcg_github_get_latest_release() {
    $cache_key = 'ppcg_github_latest_release';
    $cached = get_transient($cache_key);
    if ($cached !== false) return $cached;

    $response = wp_remote_get('https://api.github.com/repos/eagle4life69/powerpress-cookie-guard/releases/latest', [
        'timeout' => 10,
        'headers' => [
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'PowerPress-Cookie-Guard/' . PPCG_VERSION,
        ],
    ]);

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) return false;

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($data) || empty($data['tag_name']) || empty($data['zipball_url'])) return false;

    $release = [
        'version' => ltrim(trim($data['tag_name']), 'vV'),
        'package' => $data['zipball_url'],
        'url' => !empty($data['html_url']) ? $data['html_url'] : 'https://github.com/eagle4life69/powerpress-cookie-guard',
        'body' => !empty($data['body']) ? $data['body'] : '',
    ];

    set_transient($cache_key, $release, 15 * MINUTE_IN_SECONDS);
    return $release;
}

function ppcg_github_build_update_object($release, $plugin_file) {
    $update = new stdClass();
    $update->id = 'https://github.com/eagle4life69/powerpress-cookie-guard';
    $update->slug = 'powerpress-cookie-guard';
    $update->plugin = $plugin_file;
    $update->new_version = $release['version'];
    $update->url = $release['url'];
    $update->package = $release['package'];
    $update->requires_php = '7.2';
    return $update;
}

function ppcg_github_check_for_update($transient) {
    if (!is_object($transient)) $transient = new stdClass();
    if (empty($transient->response) || !is_array($transient->response)) $transient->response = [];
    if (empty($transient->no_update) || !is_array($transient->no_update)) $transient->no_update = [];

    $plugin_file = plugin_basename(PPCG_PLUGIN_FILE);
    $release = ppcg_github_get_latest_release();
    if (!$release) return $transient;

    $update = ppcg_github_build_update_object($release, $plugin_file);
    if (version_compare(PPCG_VERSION, $release['version'], '<')) {
        $transient->response[$plugin_file] = $update;
        unset($transient->no_update[$plugin_file]);
    } else {
        $transient->no_update[$plugin_file] = $update;
        unset($transient->response[$plugin_file]);
    }
    return $transient;
}
add_filter('site_transient_update_plugins', 'ppcg_github_check_for_update');

function ppcg_github_plugin_information($result, $action, $args) {
    if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== 'powerpress-cookie-guard') return $result;

    $release = ppcg_github_get_latest_release();
    $info = new stdClass();
    $info->name = 'PowerPress Cookie Guard';
    $info->slug = 'powerpress-cookie-guard';
    $info->version = $release ? $release['version'] : PPCG_VERSION;
    $info->author = '<a href="https://github.com/eagle4life69">Andrew Rhynes</a>';
    $info->homepage = 'https://github.com/eagle4life69/powerpress-cookie-guard';
    $info->requires = '5.0';
    $info->requires_php = '7.2';
    $info->download_link = $release ? $release['package'] : '';
    $info->sections = [
        'description' => 'Temporary compatibility guard that prevents PowerPress pp_event_* cookies from accumulating until requests fail with HTTP 400 header-size errors.',
        'changelog' => $release && !empty($release['body']) ? nl2br(esc_html($release['body'])) : 'See readme.txt in the GitHub repository for the current changelog.',
    ];
    return $info;
}
add_filter('plugins_api', 'ppcg_github_plugin_information', 20, 3);

function ppcg_github_fix_source_folder($source, $remote_source, $upgrader, $hook_extra) {
    if (empty($hook_extra['plugin']) || $hook_extra['plugin'] !== plugin_basename(PPCG_PLUGIN_FILE)) return $source;

    $desired_source = trailingslashit($remote_source) . 'powerpress-cookie-guard/';
    if (untrailingslashit($source) === untrailingslashit($desired_source)) return $source;
    if (file_exists($desired_source)) return $source;

    if (@rename(untrailingslashit($source), untrailingslashit($desired_source))) return $desired_source;
    return new WP_Error('ppcg_github_rename_failed', 'Unable to prepare the GitHub update package.');
}
add_filter('upgrader_source_selection', 'ppcg_github_fix_source_folder', 10, 4);

function ppcg_github_clear_update_cache($upgrader, $options) {
    if (!empty($options['action']) && $options['action'] === 'update' && !empty($options['type']) && $options['type'] === 'plugin') {
        delete_transient('ppcg_github_latest_release');
    }
}
add_action('upgrader_process_complete', 'ppcg_github_clear_update_cache', 10, 2);
