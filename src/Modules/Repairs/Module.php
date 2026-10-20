<?php
declare(strict_types=1);
namespace JT\WPCare\Modules\Repairs;

use JT\WPCare\Contracts\ModuleInterface;
use JT\WPCare\Core\Access;
use JT\WPCare\Core\AdminTabs;
use JT\WPCare\Modules\Functional\Inspector;

final class Module implements ModuleInterface
{
    public const HISTORY = 'jt_wp_care_repair_history';
    public function id(): string { return 'repairs'; }
    public function register(): void
    {
        add_action('jt_wp_care_sections', [$this, 'render'], 15);
        add_action('admin_post_jt_care_repair_debug', [$this, 'repairDebug']);
    }

    public function repairDebug(): void
    {
        Access::post('jt_care_repair_debug');
        try {
            if (Access::field('backup_confirmed') !== '1' || Access::field('approve') !== '1') {
                throw new \DomainException('Před opravou potvrďte použitelnou zálohu webu i samotný zásah.');
            }
            $editor = new ConfigEditor();
            $operation = $editor->disableDebug();
            $tests = (new Inspector())->collect();
            $problems = array_filter($tests, static fn(array $row): bool => ($row['state'] ?? '') === 'problem');
            if ($problems !== []) {
                $editor->restore($operation);
                $this->remember('rolled_back', $tests, basename($operation['backup']));
                throw new \DomainException('Po opravě selhal technický test. Původní wp-config.php byl automaticky obnoven; zkontrolujte historii oprav.');
            }
            $this->remember(in_array('unknown', array_column($tests, 'state'), true) ? 'changed_limited' : 'changed', $tests, basename($operation['backup']));
            update_option(\JT\WPCare\Modules\Automatic\Module::FORCE_REFRESH, gmdate('c'), false);
        } catch (\Throwable $error) { Access::fail($error); }
        Access::redirect('diagnostics');
    }

    public function render(): void
    {
        if (!AdminTabs::is('diagnostics')) { return; }
        Access::authorize();
        $inspection = (new ConfigEditor())->inspect();
        echo '<section class="jt-card"><h2>Řízené opravy konfigurace</h2><p>JT WP Care umí vypnout WP_DEBUG, WP_DEBUG_DISPLAY a WP_DEBUG_LOG v běžném wp-config.php. Před zápisem vytvoří chráněnou datovanou zálohu, ověří PHP syntaxi a po zásahu spustí technický test webu. Hodnotu PHP display_errors na hostingu tato oprava nemění.</p>';
        $runtime = $inspection['runtime'];
        echo '<p><strong>Aktuální načtené hodnoty:</strong> WP_DEBUG ' . esc_html(!empty($runtime['WP_DEBUG']) ? 'zapnuto' : 'vypnuto')
            . ' · WP_DEBUG_DISPLAY ' . esc_html(!empty($runtime['WP_DEBUG_DISPLAY']) ? 'zapnuto' : 'vypnuto')
            . ' · WP_DEBUG_LOG ' . esc_html(!empty($runtime['WP_DEBUG_LOG']) ? 'zapnuto' : 'vypnuto') . '</p>';
        if (!$inspection['ready']) {
            echo '<p class="jt-status jt-status-unknown"><strong>Vyžaduje ruční řešení</strong><br>' . esc_html($inspection['reason']) . '</p>';
        } elseif (!$inspection['needed']) {
            echo '<p class="jt-status jt-status-done"><strong>✓ Oprava není potřeba</strong><br>WordPress má všechny tři debug hodnoty vypnuté; bezpečná konfigurace se kvůli formátování souboru nepřepisuje.</p>';
        } else {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('jt_care_repair_debug');
            echo '<input type="hidden" name="action" value="jt_care_repair_debug">';
            echo '<p><label><input type="checkbox" name="backup_confirmed" value="1" required> Ověřil jsem aktuální zálohu souborů a databáze a možnost obnovy.</label></p>';
            echo '<p><label><input type="checkbox" name="approve" value="1" required> Schvaluji vypnutí tří debug konstant ve wp-config.php.</label></p>';
            submit_button('Bezpečně vypnout WordPress debug', 'primary');
            echo '</form>';
        }
        $history = get_option(self::HISTORY, []);
        if (is_array($history) && $history !== []) {
            echo '<details><summary>Historie oprav konfigurace</summary><table class="jt-facts"><thead><tr><th>Čas UTC</th><th>Výsledek</th><th>Technický test</th><th>Záloha</th></tr></thead><tbody>';
            foreach (array_slice(array_reverse($history), 0, 10) as $row) {
                $labels = ['changed' => 'Provedeno a ověřeno', 'changed_limited' => 'Provedeno, část testu neověřena', 'rolled_back' => 'Vráceno po problému'];
                echo '<tr><td>' . esc_html($row['at'] ?? '') . '</td><td>' . esc_html($labels[$row['state'] ?? ''] ?? 'Neznámý') . '</td><td>' . esc_html($row['summary'] ?? '') . '</td><td>' . esc_html($row['backup'] ?? '') . '</td></tr>';
            }
            echo '</tbody></table></details>';
        }
        echo '</section>';
    }

    private function remember(string $state, array $tests, string $backup): void
    {
        $counts = array_count_values(array_column($tests, 'state'));
        $history = get_option(self::HISTORY, []); $history = is_array($history) ? $history : [];
        $history[] = ['at' => gmdate('c'), 'by' => get_current_user_id(), 'state' => $state, 'backup' => $backup,
            'summary' => ($counts['done'] ?? 0) . ' ověřeno, ' . ($counts['unknown'] ?? 0) . ' neověřeno, ' . ($counts['problem'] ?? 0) . ' problém'];
        $history = array_slice($history, -20);
        update_option(self::HISTORY, $history, false);
    }
}
