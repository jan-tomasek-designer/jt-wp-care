<?php
declare(strict_types=1);
namespace JT\WPCare\Modules\Reports;

use JT\WPCare\Contracts\ModuleInterface;
use JT\WPCare\Core\Access;
use JT\WPCare\Core\AdminTabs;
use JT\WPCare\Core\Store;

final class Module implements ModuleInterface
{
    private Store $store;
    public function __construct(Store $store) { $this->store = $store; }
    public function id(): string { return 'reports'; }
    public function register(): void
    {
        add_action('jt_wp_care_sections', [$this, 'render'], 30);
        add_action('admin_post_jt_care_report', [$this, 'export']);
    }
    public function render(): void
    {
        if (!AdminTabs::is('report')) { return; }
        Access::authorize();
        $record = $this->store->load();
        echo '<section class="jt-card"><h2>Report</h2><p>Exportuje pouze uložený stav. Nejdříve uložte checklist. PDF vytvoříte z tiskové verze funkcí prohlížeče Tisk → Uložit jako PDF.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('jt_care_report');
        echo '<input type="hidden" name="action" value="jt_care_report"><input type="hidden" name="revision" value="' . esc_attr($record['revision']) . '">';
        echo '<button class="button" type="submit" name="format" value="txt">Stáhnout TXT</button> <button class="button" type="submit" name="format" value="print" formtarget="_blank">Tisková verze / PDF</button></form></section>';
    }
    public function export(): void
    {
        Access::post('jt_care_report');
        try {
            $record = $this->store->load();
            if (!hash_equals($record['revision'], Access::field('revision'))) { throw new \DomainException('Záznam se změnil. Obnovte stránku před exportem.'); }
            $format = Access::field('format');
            if (!in_array($format, ['txt', 'print'], true)) { throw new \DomainException('Neznámý formát exportu.'); }
            $record['update_history'] = get_option('jt_wp_care_update_history', []);
            $record['cleanup_history'] = get_option('jt_wp_care_cleanup_history', []);
            $record['repair_history'] = get_option(\JT\WPCare\Modules\Repairs\Module::HISTORY, []);
            $text = Formatter::text($record, home_url('/'));
        } catch (\Throwable $error) { Access::fail($error); }
        nocache_headers();
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, nofollow');
        if ($format === 'txt') {
            header('Content-Type: text/plain; charset=UTF-8');
            header('Content-Disposition: attachment; filename="jt-wp-care-report.txt"');
            echo $text;
        } else {
            header('Content-Type: text/html; charset=UTF-8');
            header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'self'");
            echo '<!doctype html><html lang="cs"><meta charset="utf-8"><title>JT WP Care — report</title><style>body{font:13px/1.5 sans-serif;max-width:900px;margin:30px auto;padding:16px}pre{white-space:pre-wrap;overflow-wrap:anywhere;font:inherit}@media print{.hint{display:none}body{margin:0;padding:0}@page{margin:18mm}}</style><body><p class="hint">Tisk → Uložit jako PDF. Před uložením zkontrolujte náhled.</p><pre>' . esc_html($text) . '</pre></body></html>';
        }
        exit;
    }
}
