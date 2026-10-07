<?php
declare(strict_types=1);
namespace JT\WPCareInstaller;

final class Screen
{
    private ?Installer $activeEngine = null;
    private string $phase = 'kontrola požadavku';

    public function register(): void
    {
        add_action('admin_menu', function (): void { add_management_page('JT WP Care – aktualizace', 'JT WP Care – aktualizace', 'update_plugins', 'jt-care-update', [$this, 'render']); });
        add_action('admin_post_jt_care_install', [$this, 'install']);
        add_action('admin_post_jt_care_rollback', [$this, 'rollback']);
    }

    private function authorize(): void
    {
        if (!current_user_can('manage_options') || !current_user_can('update_plugins')) { wp_die('Nemáš oprávnění aktualizovat JT WP Care.', '', ['response' => 403]); }
        if (is_multisite() || !wp_is_file_mod_allowed('jt_wp_care_update') || (defined('JT_WP_CARE_DISABLED') && JT_WP_CARE_DISABLED)
            || defined('JT_WP_CARE_PINNED_RELEASE')) { throw new \DomainException('Aktualizace je blokovaná nastavením webu (multisite, zákaz změn souborů, vypnutí JT WP Care nebo připnutá verze).'); }
    }

    private function engine(): Installer
    {
        $this->phase = 'kontrola instalace a hostingu';
        foreach (['StoredZip.php', 'loader.sha256'] as $file) {
            if (!is_file(__DIR__ . '/' . $file) || !is_readable(__DIR__ . '/' . $file)) { throw new \DomainException('Ve složce ' . basename(__DIR__) . ' chybí nebo nelze přečíst ' . $file . '. Nahraj úplný obsah stejné verze instalátoru.'); }
        }
        Installer::environment();
        require_once ABSPATH . 'wp-admin/includes/file.php';
        if (get_filesystem_method([], WPMU_PLUGIN_DIR . '/jt-wp-care') !== 'direct') { throw new \DomainException('Tento hosting neumožňuje přímý zápis. Instalátor nebude měnit oprávnění ani vyžadovat FTP heslo.'); }
        return $this->activeEngine = new Installer(WPMU_PLUGIN_DIR, trim((string) file_get_contents(__DIR__ . '/loader.sha256')), (string) $GLOBALS['wp_version']);
    }

    private function field(string $key): string { return isset($_POST[$key]) && is_string($_POST[$key]) ? trim(wp_unslash($_POST[$key])) : ''; }
    private function boundary(string $action): void
    {
        $this->authorize();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { wp_die('Použij formulář aktualizace.', '', ['response' => 405]); }
        check_admin_referer($action);
        if ($this->field('confirmed') !== 'yes') { throw new \DomainException('Potvrď zálohu a konkrétní změnu.'); }
    }

    public function install(): void
    {
        try {
            $this->boundary('jt_care_install');
            $upload = $_FILES['payload'] ?? [];
            if (($upload['error'] ?? -1) !== UPLOAD_ERR_OK || !is_string($upload['tmp_name'] ?? null) || !is_uploaded_file($upload['tmp_name'])) {
                throw new \DomainException('Nahrání selhalo. Vyber payload ZIP; případně ověř limit nahrávání hostingu.');
            }
            $engine = $this->engine();
            if (!defined('JT_WP_CARE_LOADED_RELEASE') || JT_WP_CARE_LOADED_RELEASE !== $this->field('expected')) { throw new \DomainException('Běžící vydání neodpovídá formuláři. Nejprve ověř stav instalace.'); }
            $next = $engine->install($upload['tmp_name'], strtolower($this->field('sha256')), $this->field('version'), $this->field('expected'));
            $this->finish('install', $next);
        } catch (\Throwable $error) { $this->fail($error); }
    }

    public function rollback(): void
    {
        try { $this->boundary('jt_care_rollback'); $this->finish('rollback', $this->engine()->rollback($this->field('expected'))); }
        catch (\Throwable $error) { $this->fail($error); }
    }

    private function finish(string $action, array $state): void
    {
        $this->phase = 'zápis výsledku po přepnutí';
        $this->activeEngine = null;
        update_option('jt_wp_care_last_update', ['action' => $action, 'version' => $state['active'], 'previous' => $state['previous'], 'at' => gmdate('c'), 'by' => get_current_user_id()], false);
        wp_safe_redirect(admin_url('tools.php?page=jt-care-update'));
        exit;
    }
    public static function diagnostic(\Throwable $error, string $phase): string
    {
        $reason = $error instanceof \DomainException ? $error->getMessage() : 'Neočekávaná chyba instalátoru.';
        // No raw runtime messages, absolute paths, traces, credentials or uploaded contents.
        $kind = $error instanceof \Error ? 'PHP-Error' : 'Exception';
        return $reason . ' Krok: ' . $phase . '. Kód: ' . $kind . '/' . basename($error->getFile()) . ':' . $error->getLine() . '. Instalátor 0.1.2-alpha, složka: ' . basename(__DIR__) . '. Obnov stránku aktualizací a ověř aktivní i načtenou verzi; tento výsledek sám nepotvrzuje, zda již došlo k přepnutí.';
    }

    private function fail(\Throwable $error): void
    {
        $message = self::diagnostic($error, $this->activeEngine ? $this->activeEngine->phase : $this->phase);
        wp_die(esc_html($message), 'JT WP Care', ['response' => 409, 'back_link' => true]);
    }

    public function render(): void
    {
        echo '<div class="wrap"><h1>JT WP Care – aktualizace</h1>';
        try { $this->authorize(); echo '<p><strong>Instalátor 0.1.2-alpha — aktivní složka: ' . esc_html(basename(__DIR__)) . '</strong></p>'; $engine = $this->engine(); $state = $engine->state(); }
        catch (\Throwable $error) { echo '<p>' . esc_html(self::diagnostic($error, $this->phase)) . '</p></div>'; return; }
        $loaded = defined('JT_WP_CARE_LOADED_RELEASE') ? JT_WP_CARE_LOADED_RELEASE : 'nenačteno';
        echo '<p>Aktivní verze: <strong>' . esc_html($state['active']) . '</strong>. V tomto požadavku načteno: <strong>' . esc_html($loaded) . '</strong>.</p>';
        $verified = $loaded === $state['active'] && class_exists('JT\\WPCare\\Core\\Release') && \JT\WPCare\Core\Release::VERSION === $state['active'];
        // Loading a class alone is not an end-to-end health check. Keep the claim narrow.
        echo '<p>' . ($verified ? 'Verze aplikace odpovídá ukazateli. Ještě ověř checklist, diagnostiku, report a důležité funkce webu.' : 'Načtení aplikace není potvrzené. Před další aktualizací ověř JT WP Care; případně vrať předchozí verzi.') . '</p>';
        echo '<p>Tento první instalátor používá ruční nahrání z GitHubu. Nestahuje kód automaticky a nepotřebuje GitHub token. Původní vydání i servisní data zachová.</p>';
        echo '<ol><li>Stáhni balíček <strong>payload.zip</strong> a soubor <strong>SHA256SUMS.txt</strong> z ověřeného vydání nebo úspěšného běhu <a href="https://github.com/jan-tomasek-designer/jt-wp-care/actions" target="_blank" rel="noopener noreferrer">našeho repozitáře</a>.</li><li>Zkopíruj 64 znaků SHA-256 z řádku pro přesný payload ZIP. Součet není podpis vydavatele; důvěřuj pouze svému repozitáři.</li><li>Ověř dokončenou zálohu souborů a DB a možnost obnovy. Po přepnutí ověř funkce webu.</li></ol>';
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('jt_care_install');
        echo '<input type="hidden" name="action" value="jt_care_install"><input type="hidden" name="expected" value="' . esc_attr($state['active']) . '">';
        echo '<p><label>Nová verze <input name="version" required placeholder="0.4.0-alpha" maxlength="80"></label></p><p><label>Payload ZIP <input type="file" name="payload" accept=".zip" required></label></p><p><label>SHA-256 <input name="sha256" required pattern="[a-fA-F0-9]{64}" maxlength="64" size="68"></label></p><p><label><input type="checkbox" name="confirmed" value="yes" required> Ověřil jsem zálohu a schvaluji instalaci zadané verze JT WP Care.</label></p>';
        submit_button('Nainstalovat zadanou verzi'); echo '</form>';
        if (!empty($state['previous'])) {
            echo '<h2>Návrat na ' . esc_html($state['previous']) . '</h2><p>Návrat mění pouze kód JT WP Care, nikoli databázi. Při fatální chybě bránící otevření administrace je nutná nouzová obnova přes FTP.</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('jt_care_rollback');
            echo '<input type="hidden" name="action" value="jt_care_rollback"><input type="hidden" name="expected" value="' . esc_attr($state['active']) . '"><p><label><input type="checkbox" name="confirmed" value="yes" required> Schvaluji návrat na ' . esc_html($state['previous']) . ' a mám dostupnou zálohu.</label></p>';
            submit_button('Vrátit předchozí verzi', 'secondary'); echo '</form>';
        }
        echo '</div>';
    }
}
