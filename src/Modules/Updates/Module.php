<?php
declare(strict_types=1);
namespace JT\WPCare\Modules\Updates;

use JT\WPCare\Contracts\ModuleInterface;
use JT\WPCare\Core\Access;

final class Module implements ModuleInterface
{
    public function id(): string { return 'updates'; }
    public function register(): void
    {
        add_action('jt_wp_care_sections', [$this, 'render'], 8);
        foreach (['refresh', 'start', 'step', 'close'] as $method) { add_action('admin_post_jt_care_updates_' . $method, [$this, $method]); }
        // Native scheduled auto-updates must not compete with a Care batch.
        foreach (['plugin', 'theme'] as $kind) {
            add_filter('auto_update_' . $kind, static function ($update) {
                return in_array(get_option(Runner::OPTION, [])['state'] ?? '', ['ready', 'running'], true) ? false : $update;
            });
        }
    }
    public static function authorize(): void
    {
        Access::authorize();
        if (!current_user_can('update_plugins') || !current_user_can('update_themes') || is_multisite()
            || !wp_is_file_mod_allowed('jt_wp_care_updates')) { throw new \DomainException('Aktualizace nejsou povolené oprávněním nebo nastavením webu.'); }
    }
    private function boundary(string $action): void { Access::post('jt_care_updates_' . $action); self::authorize(); }
    public function refresh(): void
    {
        try {
            $this->boundary('refresh');
            require_once ABSPATH . 'wp-admin/includes/update.php';
            wp_clean_plugins_cache(true); wp_clean_themes_cache(true);
            wp_update_plugins(); wp_update_themes();
            Access::redirect();
        } catch (\Throwable $e) { Access::fail($e); }
    }
    public function start(): void
    {
        try {
            $this->boundary('start');
            if (Access::field('backup') !== 'yes') { throw new \DomainException('Potvrď dokončenou zálohu souborů a databáze a možnost obnovy.'); }
            $selected = isset($_POST['selected']) && is_array($_POST['selected']) ? wp_unslash($_POST['selected']) : [];
            $approved = isset($_POST['approved']) && is_array($_POST['approved']) ? wp_unslash($_POST['approved']) : [];
            (new Runner())->locked(static function () use ($selected, $approved): void {
                $old = get_option(Runner::OPTION, []);
                if (in_array($old['state'] ?? '', ['ready', 'running'], true)) { throw new \DomainException('Nejprve uzavři předchozí plán a ověř výsledek rozpracovaného kroku.'); }
                $rows = Catalog::select($selected, $approved, Catalog::offers());
                Runner::save(['id' => wp_generate_uuid4(), 'by' => get_current_user_id(), 'created' => time(), 'updated' => gmdate('c'),
                    'backup_confirmed' => true, 'state' => 'ready', 'index' => 0, 'items' => $rows, 'results' => []]);
            });
            Access::redirect();
        } catch (\Throwable $e) { Access::fail($e); }
    }
    public function step(): void
    {
        try {
            $this->boundary('step');
            $index = Access::field('index');
            if (!ctype_digit($index)) { throw new \DomainException('Neplatný krok aktualizace.'); }
            $plan = (new Runner())->step(Access::field('plan'), (int) $index);
            wp_send_json_success(['state' => $plan['state'], 'index' => $plan['index']]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e instanceof \DomainException ? $e->getMessage() : 'Krok se nezdařil. Ověř stav webu.'], 409);
        }
    }
    public function close(): void
    {
        try {
            $this->boundary('close');
            if (Access::field('reviewed') !== 'yes') { throw new \DomainException('Potvrď ověření skutečného stavu webu.'); }
            (new Runner())->locked(static function (): void {
                $plan = get_option(Runner::OPTION, []);
                if (($plan['id'] ?? '') !== Access::field('plan')) { throw new \DomainException('Plán se změnil.'); }
                $plan['state'] = 'closed'; $plan['updated'] = gmdate('c'); $plan['closed_by'] = get_current_user_id(); Runner::save($plan);
            });
            Access::redirect();
        } catch (\Throwable $e) { Access::fail($e); }
    }
    private function form(string $action): void
    {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('jt_care_updates_' . $action);
        echo '<input type="hidden" name="action" value="jt_care_updates_' . esc_attr($action) . '">';
    }
    public function render(): void
    {
        try { self::authorize(); } catch (\Throwable $e) { return; }
        echo '<section class="jt-card"><h2>Řízené aktualizace</h2><p>Vyber a schval konkrétní verze. Běží postupně: pluginy → šablony → WooCommerce. Při chybě se seznam zastaví. Záloha musí zahrnovat soubory i databázi; automatický návrat databáze není dostupný.</p>';
        $plan = get_option(Runner::OPTION, []);
        if ($plan) {
            $labels = ['ready' => 'Připraveno', 'running' => 'Krok spuštěn – dokončení zatím nepotvrzeno', 'stopped' => 'Zastaveno při chybě', 'complete' => 'Aktualizace dokončeny; ověř funkce webu', 'closed' => 'Plán uzavřen správcem'];
            echo '<p><strong>' . esc_html($labels[$plan['state']] ?? 'Neznámý stav') . '</strong></p>';
            foreach ($plan['results'] ?? [] as $row) { echo '<p>' . esc_html($row['name'] . ' ' . $row['from'] . ' → ' . $row['to'] . ': ' . $row['message']) . '</p>'; }
        }
        if (in_array($plan['state'] ?? '', ['ready', 'running'], true)) {
            if ($plan['state'] === 'ready' && $plan['by'] === get_current_user_id()) {
                echo '<p id="jt-updates-run" data-url="' . esc_url(admin_url('admin-post.php')) . '" data-nonce="' . esc_attr(wp_create_nonce('jt_care_updates_step'))
                    . '" data-plan="' . esc_attr($plan['id']) . '" data-index="' . esc_attr((string) $plan['index']) . '">Připraveno pokračovat ve schváleném seznamu.</p><button type="button" class="button button-primary" id="jt-updates-go">Spustit schválený seznam</button>';
                wp_enqueue_script('jt-care-updates', plugins_url('assets/updates.js', dirname(__DIR__, 3) . '/bootstrap.php'), [], \JT\WPCare\Core\Release::VERSION, true);
            }
            echo '<p>Při přerušení ověř skutečnou verzi a funkce webu. Rozpracovaný krok se automaticky neopakuje.</p>';
            $this->form('close');
            echo '<input type="hidden" name="plan" value="' . esc_attr($plan['id']) . '"><label><input type="checkbox" name="reviewed" value="yes" required> Ověřil jsem stav webu a chci uzavřít tento plán bez dalších aktualizací.</label>';
            submit_button('Uzavřít plán', 'secondary'); echo '</form></section>'; return;
        }
        $this->form('refresh'); submit_button('Načíst aktuální nabídky', 'secondary'); echo '</form>';
        $offers = Catalog::offers();
        if (!$offers) { echo '<p>Nejsou dostupné nabídky s balíčkem z poslední hodiny. Načti aktuální nabídky; prázdný seznam nepotvrzuje aktuálnost všech položek.</p></section>'; return; }
        $this->form('start');
        foreach ($offers as $key => $row) {
            echo '<p><label><input type="checkbox" name="selected[' . esc_attr($key) . ']" value="' . esc_attr($row['fingerprint']) . '"' . ($row['blocked'] ? ' disabled' : '') . '> '
                . esc_html($row['name'] . ' ' . $row['from'] . ' → ' . $row['to']) . '</label>';
            if ($row['blocked']) { echo '<br>' . esc_html($row['blocked']); }
            elseif ($row['critical']) { echo '<br><label><input type="checkbox" name="approved[' . esc_attr($key) . ']" value="yes"> Výslovně schvaluji tuto citlivou změnu po ověření kompatibility.</label>'; }
            echo '</p>';
        }
        echo '<p>Nabídka vydavatele nezaručuje kompatibilitu s tímto webem. Vlastní úpravy souborů mohou být přepsány. WooCommerce může po aktualizaci spustit vlastní migrace.</p><p><label><input type="checkbox" name="backup" value="yes" required> Ověřil jsem dokončenou zálohu souborů a DB, možnost obnovy a schvaluji vybrané verze.</label></p>';
        submit_button('Připravit schválený seznam'); echo '</form></section>';
    }
}
