<?php
declare(strict_types=1);
namespace JT\WPCare\Modules\Updates;

use JT\WPCare\Contracts\ModuleInterface;
use JT\WPCare\Core\Access;
use JT\WPCare\Core\AdminTabs;

final class Module implements ModuleInterface
{
    public function id(): string { return 'updates'; }
    public function register(): void
    {
        add_action('jt_wp_care_sections', [$this, 'render'], 8);
        foreach (['refresh', 'start', 'step', 'verify', 'close', 'delete'] as $method) { add_action('admin_post_jt_care_updates_' . $method, [$this, $method]); }
        // Native scheduled auto-updates must not compete with a Care batch.
        foreach (['plugin', 'theme'] as $kind) {
            add_filter('auto_update_' . $kind, static function ($update) {
                return in_array(get_option(Runner::OPTION, [])['state'] ?? '', ['ready', 'running', 'verifying'], true) ? false : $update;
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
            Access::redirect('updates');
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
            Access::redirect('updates');
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
    public function verify(): void
    {
        try {
            $this->boundary('verify');
            $index = Access::field('index');
            if (!ctype_digit($index)) { throw new \DomainException('Neplatný krok post-testu.'); }
            $plan = (new Runner())->verify(Access::field('plan'), (int) $index);
            wp_send_json_success(['state' => $plan['state'], 'index' => $plan['index']]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e instanceof \DomainException ? $e->getMessage() : 'Post-test se nezdařil. Ověř stav webu.'], 409);
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
            Access::redirect('updates');
        } catch (\Throwable $e) { Access::fail($e); }
    }
    public function delete(): void
    {
        try {
            Access::post('jt_care_updates_delete'); self::authorize();
            if (!current_user_can('delete_plugins')) { throw new \DomainException('Nemáš oprávnění odstraňovat pluginy.'); }
            if (Access::field('backup') !== 'yes' || Access::field('confirmed') !== 'yes') {
                throw new \DomainException('Potvrď zálohu a to, že vybraný plugin web nepotřebuje.');
            }
            $plan = get_option(Runner::OPTION, []);
            if (in_array($plan['state'] ?? '', ['ready', 'running', 'verifying'], true)) {
                throw new \DomainException('Nejprve dokonči nebo uzavři aktualizační plán.');
            }
            (new Cleanup())->remove(Access::field('plugin'), Access::field('fingerprint'));
            Access::redirect('updates');
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
        if (!AdminTabs::is('updates')) { return; }
        try { self::authorize(); } catch (\Throwable $e) { return; }
        echo '<section class="jt-card"><h2>Řízené aktualizace</h2><p>Vyber a schval konkrétní verze. Běží postupně: pluginy → šablony → WooCommerce. Po každé položce proběhne technický test administrace a základních stránek; při problému nebo neověřeném výsledku se seznam zastaví. Záloha musí zahrnovat soubory i databázi.</p>';
        $plan = get_option(Runner::OPTION, []);
        if ($plan) {
            $labels = ['ready' => 'Připraveno', 'running' => 'Krok spuštěn – dokončení zatím nepotvrzeno', 'verifying' => 'Verze nainstalována – čeká na technický post-test', 'stopped' => 'Zastaveno při chybě', 'complete' => 'Aktualizace i technické post-testy dokončeny; proveď ruční funkční kontrolu', 'closed' => 'Plán uzavřen správcem'];
            echo '<p><strong>' . esc_html($labels[$plan['state']] ?? 'Neznámý stav') . '</strong></p>';
            foreach ($plan['results'] ?? [] as $row) {
                $state = ($row['state'] ?? '') === 'done' ? 'done' : (($row['state'] ?? '') === 'verifying' ? 'unknown' : 'problem');
                echo '<p class="jt-status jt-status-' . esc_attr($state) . '"><strong>' . esc_html($row['name'] . ' ' . $row['from'] . ' → ' . $row['to']) . '</strong><br>' . esc_html($row['message']) . '</p>';
            }
        }
        if (in_array($plan['state'] ?? '', ['ready', 'running', 'verifying'], true)) {
            $expired = time() - (int) ($plan['created'] ?? 0) > 3600;
            if (in_array($plan['state'], ['ready', 'verifying'], true) && !$expired && $plan['by'] === get_current_user_id()) {
                echo '<p id="jt-updates-run" data-url="' . esc_url(admin_url('admin-post.php')) . '" data-nonce="' . esc_attr(wp_create_nonce('jt_care_updates_step'))
                    . '" data-verify-nonce="' . esc_attr(wp_create_nonce('jt_care_updates_verify')) . '" data-state="' . esc_attr($plan['state'])
                    . '" data-plan="' . esc_attr($plan['id']) . '" data-index="' . esc_attr((string) $plan['index']) . '">Připraveno pokračovat ve schváleném seznamu.</p><button type="button" class="button button-primary" id="jt-updates-go">'
                    . ($plan['state'] === 'verifying' ? 'Spustit bezpečný post-test' : 'Spustit schválený seznam') . '</button>';
                wp_enqueue_script('jt-care-updates', plugins_url('assets/updates.js', dirname(__DIR__, 3) . '/bootstrap.php'), [], \JT\WPCare\Core\Release::VERSION, true);
            }
            if ($expired) { echo '<p class="jt-status jt-status-unknown"><strong>Platnost plánu vypršela.</strong><br>Uzavři jej, načti aktuální nabídky a připrav nový seznam. Starý instalační krok se nespustí.</p>'; }
            echo '<p>Při přerušení ověř skutečnou verzi a funkce webu. Rozpracovaný krok se automaticky neopakuje.</p>';
            $this->form('close');
            echo '<input type="hidden" name="plan" value="' . esc_attr($plan['id']) . '"><label><input type="checkbox" name="reviewed" value="yes" required> Ověřil jsem stav webu a chci uzavřít tento plán bez dalších aktualizací.</label>';
            submit_button('Uzavřít plán', 'secondary'); echo '</form></section>'; return;
        }
        $this->form('refresh'); submit_button('Načíst aktuální nabídky', 'secondary'); echo '</form>';
        $offers = Catalog::offers();
        if (!$offers) { echo '<p>Nejsou dostupné nabídky s balíčkem z poslední hodiny. Načti aktuální nabídky; prázdný seznam nepotvrzuje aktuálnost všech položek.</p>'; }
        else {
        $this->form('start');
        foreach ($offers as $key => $row) {
            $recommended = !$row['blocked'] && !$row['critical'];
            echo '<p class="jt-update-offer"><label><input type="checkbox" name="selected[' . esc_attr($key) . ']" value="' . esc_attr($row['fingerprint']) . '"'
                . ($row['blocked'] ? ' disabled' : ($recommended ? ' checked' : '')) . '> '
                . esc_html($row['name'] . ' ' . $row['from'] . ' → ' . $row['to']) . '</label> '
                . ($row['blocked'] ? '<span class="jt-badge jt-badge-na">Blokováno</span>'
                    : ($row['critical'] ? '<span class="jt-badge jt-badge-deferred">Vyžaduje schválení</span>' : '<span class="jt-badge jt-badge-done">Doporučeno</span>'));
            if ($row['blocked']) { echo '<br>' . esc_html($row['blocked']); }
            elseif ($row['critical']) { echo '<br><label><input type="checkbox" name="approved[' . esc_attr($key) . ']" value="yes"> Výslovně schvaluji tuto citlivou změnu po ověření kompatibility.</label>'; }
            echo '</p>';
        }
        echo '<p>Nabídka vydavatele nezaručuje kompatibilitu s tímto webem. Vlastní úpravy souborů mohou být přepsány. WooCommerce může po aktualizaci spustit vlastní migrace.</p><p><label><input type="checkbox" name="backup" value="yes" required> Ověřil jsem dokončenou zálohu souborů a DB, možnost obnovy a schvaluji vybrané verze.</label></p>';
        submit_button('Připravit schválený seznam'); echo '</form>'; }
        $this->cleanup();
        echo '</section>';
    }

    private function cleanup(): void
    {
        $rows = Catalog::inactivePlugins();
        echo '<hr class="jt-divider"><h2>Úklid neaktivních pluginů</h2><p>JT WP Care umí odstranit soubory jednoho neaktivního pluginu. Neumí s jistotou určit, zda jsou jeho data nebo funkce stále potřeba; rozhodnutí proto vždy potvrzuje správce. Po odstranění automaticky otestuje administraci a základní stránky.</p>';
        $history = get_option(Cleanup::HISTORY, []);
        if (is_array($history) && $history) {
            $last = end($history); $state = $last['verification']['state'] ?? 'unknown';
            echo '<p class="jt-status jt-status-' . esc_attr($state === 'done' ? 'done' : ($state === 'problem' ? 'problem' : 'unknown')) . '"><strong>Poslední úklid: ' . esc_html(($last['name'] ?? '') . ' ' . ($last['version'] ?? '')) . '</strong><br>' . esc_html($last['verification']['text'] ?? 'Výsledek post-testu není dostupný.') . '</p>';
        }
        if (!$rows) { echo '<p class="jt-empty">Nebyly nalezeny žádné neaktivní běžné pluginy.</p>'; return; }
        echo '<div class="jt-cleanup-list">';
        foreach ($rows as $row) {
            echo '<div class="jt-cleanup-item' . ($row['blocked'] ? ' is-blocked' : '') . '"><div><strong>' . esc_html($row['name']) . '</strong> <span class="jt-version">' . esc_html($row['version']) . '</span><br><code>' . esc_html($row['id']) . '</code>';
            if ($row['blocked']) { echo '<p class="description">' . esc_html($row['blocked']) . '</p></div><span class="jt-badge jt-badge-na">Chráněno</span>'; }
            else {
                echo '<p class="description">Neaktivní plugin. Před odstraněním ověř, že ho web ani budoucí servis nepotřebuje.</p></div>';
                $this->form('delete');
                echo '<input type="hidden" name="plugin" value="' . esc_attr($row['id']) . '"><input type="hidden" name="fingerprint" value="' . esc_attr($row['fingerprint']) . '">';
                echo '<label><input type="checkbox" name="confirmed" value="yes" required> Plugin není potřeba</label><br><label><input type="checkbox" name="backup" value="yes" required> Záloha je ověřená</label>';
                submit_button('Odstranit plugin a otestovat web', 'delete small'); echo '</form>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
}
