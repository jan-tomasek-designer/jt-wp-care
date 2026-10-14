<?php
declare(strict_types=1);
namespace JT\WPCare\Modules\Dashboard;

use JT\WPCare\Contracts\ModuleInterface;
use JT\WPCare\Core\Access;
use JT\WPCare\Core\AdminTabs;
use JT\WPCare\Core\Store;
use JT\WPCare\Modules\Automatic\Evaluation;
use JT\WPCare\Modules\Checklist\Catalog;

final class Module implements ModuleInterface
{
    private Store $store;
    public function __construct(Store $store) { $this->store = $store; }
    public function id(): string { return 'dashboard'; }
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
    }
    public function addMenu(): void
    {
        add_management_page('JT WP Care', 'JT WP Care', 'manage_options', 'jt-wp-care', [$this, 'render']);
    }
    public function assets(string $hook): void
    {
        if ($hook === 'tools_page_jt-wp-care') {
            wp_enqueue_style('jt-wp-care', plugins_url('assets/admin.css', dirname(__DIR__, 3) . '/bootstrap.php'), [], \JT\WPCare\Core\Release::VERSION);
        }
    }
    public function render(): void
    {
        Access::authorize();
        echo '<div class="wrap jt-care"><h1>JT WP Care <small>' . esc_html(\JT\WPCare\Core\Release::VERSION) . '</small></h1><p>Servis podle metodiky 0.1 · ' . esc_html(home_url('/')) . '</p>';
        echo '<div class="notice notice-warning inline"><p>Testovací verze. Vybrané aktualizace se spustí po schválení správcem; nejprve ověř zálohu a možnost obnovy. Nejde o úplný bezpečnostní audit.</p></div>';
        if (isset($_GET['saved']) && $_GET['saved'] === '1') {
            echo '<div class="notice notice-success inline"><p>Operace byla uložena.</p></div>';
        }
        echo '<nav class="nav-tab-wrapper jt-tabs" aria-label="Sekce JT WP Care">';
        foreach (AdminTabs::all() as $id => $label) {
            echo '<a class="nav-tab' . (AdminTabs::is($id) ? ' nav-tab-active' : '') . '" href="' . esc_url(AdminTabs::url($id)) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';
        if (AdminTabs::is('overview')) { $this->overview(); }
        try { do_action('jt_wp_care_sections'); }
        catch (\Throwable $error) { echo '<div class="notice notice-error inline"><p>Záznam nelze zobrazit. Zkontrolujte jeho formát a kompatibilitu verze pluginu.</p></div>'; }
        echo '</div>';
    }

    private function overview(): void
    {
        $record = $this->store->load(); $states = array_fill_keys(array_keys(Catalog::states()), 0);
        foreach (Catalog::items() as $id => $definition) {
            $manual = $record['items'][$id]['state'] ?? 'unknown';
            $auto = Evaluation::item($id, $record['audit'] ?? null);
            $state = $auto['state'] ?? $manual;
            ++$states[isset($states[$state]) ? $state : 'unknown'];
        }
        $automatic = Evaluation::results($record['audit'] ?? null);
        $attention = array_filter($automatic, static fn(array $row): bool => in_array($row['state'] ?? '', ['problem', 'unknown'], true));
        $plan = get_option(\JT\WPCare\Modules\Updates\Runner::OPTION, []);
        $planLabels = ['ready' => 'čeká na spuštění', 'running' => 'probíhá / výsledek neověřen', 'verifying' => 'čeká na post-test',
            'stopped' => 'zastaveno', 'complete' => 'dokončeno, čeká na ruční kontrolu', 'closed' => 'uzavřeno'];
        echo '<section class="jt-overview"><div class="jt-overview-grid">';
        $this->tile('Checklist', $states['done'] . ' z ' . count(Catalog::items()) . ' splněno', 'checklist', $states['problem'] . ' problémů · ' . $states['deferred'] . ' odloženo · ' . $states['unknown'] . ' neověřeno');
        $this->tile('Diagnostika', $attention ? count($attention) . ' položek vyžaduje pozornost' : ($automatic ? 'Bez zjištěného problému' : 'Dosud bez snímku'), 'diagnostics', 'Poslední snímek: ' . ($record['audit']['at'] ?? 'neproveden'));
        $this->tile('Aktualizace', $planLabels[$plan['state'] ?? ''] ?? 'Bez aktivního plánu', 'updates', !empty($plan['updated']) ? 'Poslední změna: ' . $plan['updated'] : 'Nejdříve ověř zálohu');
        $this->tile('Report', !empty($record['updated_at']) ? 'Záznam je uložen' : 'Záznam ještě nebyl uložen', 'report', 'TXT nebo tisková verze / PDF');
        echo '</div>';
        if ($attention) {
            echo '<div class="jt-card"><h2>Co vyžaduje pozornost</h2>';
            $labels = ['debug' => 'Debug režim', 'plugins' => 'Pluginy', 'theme' => 'Šablony', 'woocommerce' => 'WooCommerce', 'wc_database' => 'Databáze WooCommerce', 'wc_queue' => 'Naplánované úlohy', 'wordpress' => 'WordPress', 'frontoffice' => 'Front office', 'basic_pages' => 'Základní stránky'];
            foreach (array_slice($attention, 0, 6, true) as $id => $row) {
                $state = ($row['state'] ?? '') === 'problem' ? 'problem' : 'unknown';
                echo '<p class="jt-status jt-status-' . esc_attr($state) . '"><strong>' . esc_html($labels[$id] ?? $id) . '</strong><br>' . esc_html($row['text'] ?? '') . '</p>';
            }
            echo '<p><a class="button" href="' . esc_url(AdminTabs::url('diagnostics')) . '">Otevřít diagnostiku</a></p></div>';
        }
        echo '<div class="jt-card"><h2>Postup servisu</h2><p>Záloha → diagnostika → plán → WordPress → pluginy → šablony → WooCommerce → funkční test → bezpečnost → report</p></div></section>';
    }

    private function tile(string $title, string $value, string $tab, string $detail): void
    {
        echo '<a class="jt-overview-tile" href="' . esc_url(AdminTabs::url($tab)) . '"><strong>' . esc_html($title) . '</strong><span>' . esc_html($value) . '</span><small>' . esc_html($detail) . '</small></a>';
    }
}
