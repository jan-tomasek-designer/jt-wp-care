<?php
declare(strict_types=1);
require __DIR__ . '/wp-doubles.php';
require dirname(__DIR__) . '/autoload.php';
use JT\WPCare\Modules\Updates\Verifier;
function verify($ok, $why) { if (!$ok) { throw new RuntimeException($why); } }
$GLOBALS['options']['page_on_front'] = 10;
$GLOBALS['page_ids'] = [10, 11, 12, 13, 14];
$urls = ['https://example.test/', 'https://example.test/page-10/', 'https://example.test/page-11/', 'https://example.test/page-12/', 'https://example.test/page-13/'];
foreach ($urls as $url) { $GLOBALS['http'][$url] = ['code' => 200, 'headers' => ['content-type' => 'text/html'], 'body' => '<html>ok</html>']; }
$done = (new Verifier())->run();
verify($done['state'] === 'done' && count($done['checks']) === 4, 'Successful post-update gate');
$GLOBALS['http']['https://example.test/'] = ['code' => 503, 'headers' => ['content-type' => 'text/html'], 'body' => 'maintenance'];
$problem = (new Verifier())->run();
verify($problem['state'] === 'problem' && str_contains($problem['text'], 'úvodní stránka'), 'HTTP failure stops gate');
$GLOBALS['http']['https://example.test/'] = new WP_Error();
$unknown = (new Verifier())->run();
verify($unknown['state'] === 'unknown' && str_contains($unknown['text'], 'úvodní stránka'), 'Inconclusive probe stops gate');
echo "Post-update functional gate tests passed.\n";
