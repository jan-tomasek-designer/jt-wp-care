<?php
declare(strict_types=1);
require __DIR__ . '/wp-doubles.php';
require dirname(__DIR__) . '/autoload.php';
use JT\WPCare\Modules\Functional\Inspector;
use JT\WPCare\Modules\Functional\Module;
function verify($ok, $why) { if (!$ok) { throw new RuntimeException($why); } }
$GLOBALS['options']['page_on_front'] = 10;
$GLOBALS['page_ids'] = [10, 11, 12, 13, 14, 15, 16];
$urls = ['https://example.test/', 'https://example.test/page-10/', 'https://example.test/page-11/', 'https://example.test/page-12/', 'https://example.test/page-13/'];
foreach ($urls as $url) { $GLOBALS['http'][$url] = ['code' => 200, 'headers' => ['content-type' => 'text/html; charset=UTF-8'], 'body' => '<html>ok</html>']; }
$results = (new Inspector())->collect();
verify($results['login_session']['state'] === 'done' && $results['backoffice']['state'] === 'done', 'Authenticated admin request');
verify($results['frontoffice']['state'] === 'done' && $results['basic_pages']['state'] === 'done', 'HTML reachability');
verify(count($GLOBALS['probed']) === 5, 'Bounded target count');
foreach ($GLOBALS['probed'] as [$url, $args]) { verify($args['timeout'] === 3 && $args['redirection'] === 3 && $args['limit_response_size'] === 262144, 'Bounded request'); }
$GLOBALS['http']['https://example.test/'] = ['code' => 503, 'headers' => ['content-type' => 'text/html'], 'body' => 'down'];
verify((new Inspector())->collect()['frontoffice']['state'] === 'problem', 'HTTP error detected');
$GLOBALS['http']['https://example.test/'] = new WP_Error();
verify((new Inspector())->collect()['frontoffice']['state'] === 'unknown', 'Transport failure is unknown');
$GLOBALS['http']['https://example.test/'] = ['code' => 200, 'headers' => ['content-type' => 'application/json'], 'body' => '{}'];
verify((new Inspector())->collect()['frontoffice']['state'] === 'unknown', 'Non-HTML not overclaimed');
$GLOBALS['logged_in'] = false;
verify((new Inspector())->collect()['login_session']['state'] === 'problem', 'Missing session detected');
$GLOBALS['logged_in'] = true;
(new Module())->register();
$merged = apply_filters('jt_wp_care_automatic_results', ['php' => ['state' => 'done', 'text' => 'ok']]);
verify(isset($merged['php'], $merged['frontoffice']), 'Filter preserves existing results');
echo "Functional session, admin and bounded same-site HTML checks passed.\n";
