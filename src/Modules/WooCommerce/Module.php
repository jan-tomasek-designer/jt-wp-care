<?php
declare(strict_types=1);
namespace JT\WPCare\Modules\WooCommerce;

use JT\WPCare\Contracts\ModuleInterface;

/** Read-only integration; no queue execution or database migration is triggered. */
final class Module implements ModuleInterface
{
    public function id(): string { return 'woocommerce'; }
    public function register(): void { add_filter('jt_wp_care_automatic_results', [$this, 'collect']); }
    public function collect(array $results): array
    {
        return array_merge($results, (new Inspector())->collect(time()));
    }
}
