<?php
declare(strict_types=1);
namespace JT\WPCare\Modules\Functional;

use JT\WPCare\Contracts\ModuleInterface;

/** Adds bounded read-only reachability checks to the authenticated audit request. */
final class Module implements ModuleInterface
{
    public function id(): string { return 'functional-tests'; }
    public function register(): void { add_filter('jt_wp_care_automatic_results', [$this, 'collect'], 20); }
    public function collect(array $results): array { return array_merge($results, (new Inspector())->collect()); }
}
