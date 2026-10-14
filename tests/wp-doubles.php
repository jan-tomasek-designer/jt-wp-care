<?php
declare(strict_types=1);
// Lightweight WordPress boundary doubles. Real WordPress staging is still required.
$GLOBALS['options'] = [];
$GLOBALS['authorized'] = true;
$GLOBALS['nonce_ok'] = true;
$GLOBALS['hooks'] = [];
function current_user_can($cap) { return $GLOBALS['authorized'] && $cap === 'manage_options'; }
function wp_die($message, $title = '', $args = []) { throw new RuntimeException('HTTP ' . ($args['response'] ?? 500)); }
function check_admin_referer($action) { if (!$GLOBALS['nonce_ok']) { throw new RuntimeException('Bad nonce'); } return 1; }
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : stripslashes($value); }
function sanitize_textarea_field($text) { return strip_tags($text); }
function get_current_user_id() { return 7; }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['options'][$key] = $value; return true; }
function delete_option($key) { unset($GLOBALS['options'][$key]); return true; }
function add_option($key, $value, $unused = '', $autoload = true) {
    if (isset($GLOBALS['options'][$key])) { return false; }
    if ($autoload !== false) { throw new RuntimeException('Record must not autoload.'); }
    $GLOBALS['options'][$key] = $value; return true;
}
function wp_generate_uuid4() { static $id = 0; return 'revision-' . ++$id; }
function maybe_serialize($value) { return serialize($value); }
function wp_cache_delete($key, $group) {}
function add_action($name, $callback, $priority = 10) { $GLOBALS['hooks'][$name][] = $callback; }
function do_action($name, ...$args) { foreach ($GLOBALS['hooks'][$name] ?? [] as $callback) { $callback(...$args); } }
function add_management_page($pageTitle, $menuTitle, $capability, $slug, $callback) { $GLOBALS['management_pages'][$slug] = $callback; }
function wp_enqueue_style($handle, $src = '', $deps = [], $version = false) { $GLOBALS['styles'][$handle] = compact('src', 'deps', 'version'); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_textarea($value) { return esc_html($value); }
function esc_url($value) { return esc_attr($value); }
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function home_url($path) { return 'https://example.test' . $path; }
function wp_nonce_field($action) { echo '<input name="_wpnonce" value="test">'; }
function selected($a, $b, $echo = true) { return $a === $b ? 'selected' : ''; }
function submit_button($label, $type = '') { echo '<button>' . esc_html($label) . '</button>'; }
function wp_enqueue_script($handle, $src = '', $deps = [], $version = false, $footer = false) { $GLOBALS['scripts'][$handle] = compact('src', 'deps', 'version', 'footer'); }
function plugins_url($path, $file = '') { return 'https://example.test/wp-content/mu-plugins/jt-wp-care/releases/test/' . ltrim($path, '/'); }
function get_bloginfo($field) { return '6.0'; }
function wp_strip_all_tags($text) { return strip_tags($text); }
function get_site_transient($key) { return $GLOBALS['updates'] ?? false; }
function get_plugins() { return $GLOBALS['plugins'] ?? []; }
function is_plugin_active($file) { return in_array($file, $GLOBALS['active'] ?? [], true); }
function is_plugin_active_for_network($file) { return in_array($file, $GLOBALS['network'] ?? [], true); }
function is_ssl() { return true; }
function is_user_logged_in() { return $GLOBALS['logged_in'] ?? true; }
function is_admin() { return $GLOBALS['is_admin'] ?? true; }
function get_posts($args) { return $GLOBALS['page_ids'] ?? []; }
function get_permalink($id) { return 'https://example.test/page-' . $id . '/'; }
function get_the_title($id) { return 'Page ' . $id; }
function wp_parse_url($url) { return parse_url($url); }
function untrailingslashit($value) { return rtrim($value, '/'); }
function wp_safe_remote_get($url, $args) { $GLOBALS['probed'][] = [$url, $args]; return $GLOBALS['http'][$url] ?? new WP_Error(); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code($response) { return $response['code'] ?? 0; }
function wp_remote_retrieve_header($response, $name) { return $response['headers'][$name] ?? ''; }
function wp_remote_retrieve_body($response) { return $response['body'] ?? ''; }
class WP_Error {}
function add_filter($hook, $callback) { $GLOBALS['filters'][$hook][] = $callback; }
function apply_filters($hook, $value) {
    if ($hook === 'xmlrpc_enabled') { return $GLOBALS['xmlrpc_auth'] ?? $value; }
    foreach ($GLOBALS['filters'][$hook] ?? [] as $callback) { $value = $callback($value); }
    return $value;
}
function wp_get_theme() { return new class { public function get($key) { return $key === 'Name' ? '<Test theme>' : '1.0'; } public function parent() { return false; } }; }
$GLOBALS['wpdb'] = new class {
    public string $options = 'wp_options';
    public array $args = [];
    public bool $fail = false;
    public bool $race = false;
    public function db_version() { return '8.0'; }
    public function prepare($sql, ...$args) { $this->args = $args; return $sql; }
    public function query($sql) {
        if ($this->fail) { return false; }
        [$new, $key, $expected] = $this->args;
        if ($this->race) { $GLOBALS['options'][$key]['revision'] = 'concurrent'; }
        if (serialize($GLOBALS['options'][$key]) !== $expected) { return 0; }
        $GLOBALS['options'][$key] = unserialize($new); return 1;
    }
};
