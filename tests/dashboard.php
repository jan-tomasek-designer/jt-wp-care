<?php
declare(strict_types=1);
require __DIR__ . '/wp-doubles.php';
require dirname(__DIR__) . '/autoload.php';
use JT\WPCare\Core\Store;
use JT\WPCare\Modules\Dashboard\Module;
function verify($ok, $why) { if (!$ok) { throw new RuntimeException($why); } }
$module = new Module(new Store());
$_GET['jt_tab'] = 'overview';
ob_start(); $module->render(); $overview = ob_get_clean();
foreach (['Přehled', 'Checklist', 'Diagnostika a bezpečnost', 'Aktualizace', 'Report', 'Postup servisu'] as $label) {
    verify(str_contains($overview, $label), 'Overview contains ' . $label);
}
verify(substr_count($overview, 'nav-tab-active') === 1 && str_contains($overview, 'jt-overview-grid'), 'One active tab and overview cards');
$_GET['jt_tab'] = 'report';
ob_start(); $module->render(); $reportTab = ob_get_clean();
verify(!str_contains($reportTab, 'jt-overview-grid') && substr_count($reportTab, 'nav-tab-active') === 1, 'Section tab hides dashboard cards');
echo "Dashboard overview and tab navigation tests passed.\n";
