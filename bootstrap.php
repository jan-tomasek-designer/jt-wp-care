<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { return; }
require_once __DIR__ . '/autoload.php';

// Integrations are inspected only after normal plugins are loaded.
add_action('plugins_loaded', static function (): void {
    if (!is_admin()) { return; }
    $disabled = defined('JT_WP_CARE_DISABLED_MODULES') ? JT_WP_CARE_DISABLED_MODULES : [];
    $disabled = is_array($disabled) ? $disabled : [];
    // Alpha supports single-site only. MU plugins otherwise load network-wide.
    if (is_multisite()) {
        add_action('admin_notices', static function (): void {
            if (current_user_can('manage_options')) {
                echo '<div class="notice notice-warning"><p>JT WP Care: multisite zatím není podporováno. Moduly nebyly spuštěny.</p></div>';
            }
        });
        return;
    }
    $store = new \JT\WPCare\Core\Store();
    $modules = [
        new \JT\WPCare\Modules\Dashboard\Module($store),
        new \JT\WPCare\Modules\Checklist\Module($store),
        new \JT\WPCare\Modules\Security\Module($store),
        new \JT\WPCare\Modules\Reports\Module($store),
        new \JT\WPCare\Modules\WooCommerce\Module(),
        new \JT\WPCare\Modules\Updates\Module(),
        new \JT\WPCare\Modules\Functional\Module(),
    ];
    if (!in_array('security', $disabled, true)) { $modules[] = new \JT\WPCare\Modules\Automatic\Module($store); }
    (new \JT\WPCare\Core\Kernel($modules, $disabled))->boot();
}, 20);
