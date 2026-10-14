<?php
declare(strict_types=1);
require __DIR__ . '/wp-doubles.php';
require dirname(__DIR__) . '/autoload.php';
use JT\WPCare\Core\AdminTabs;
function verify($ok, $why) { if (!$ok) { throw new RuntimeException($why); } }
unset($_GET['jt_tab']);
verify(AdminTabs::current() === 'overview', 'Default overview');
foreach (AdminTabs::all() as $id => $label) {
    $_GET['jt_tab'] = $id;
    verify(AdminTabs::current() === $id && AdminTabs::is($id), 'Known tab ' . $id);
    verify(str_contains(AdminTabs::url($id), 'jt_tab=' . $id), 'Stable URL ' . $id);
}
$_GET['jt_tab'] = '../updates';
verify(AdminTabs::current() === 'overview', 'Unknown tab fallback');
verify(count(AdminTabs::all()) === 5, 'Expected navigation');
echo "Admin tab routing tests passed.\n";
