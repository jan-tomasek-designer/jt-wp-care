<?php
declare(strict_types=1);
namespace JT\WPCare\Modules\Automatic;

use JT\WPCare\Contracts\ModuleInterface;
use JT\WPCare\Core\Access;
use JT\WPCare\Core\Store;
use JT\WPCare\Modules\Security\Collector;

/** Automatic POST refresh only while an authorized administrator visits the Care page. */
final class Module implements ModuleInterface
{
    private Store $store;
    public function __construct(Store $store) { $this->store = $store; }
    public function id(): string { return 'automatic'; }
    public function register(): void
    {
        add_action('jt_wp_care_sections', [$this, 'render'], 5);
        add_action('admin_post_jt_care_auto_audit', [$this, 'run']);
    }
    public static function due(array $record, ?int $now = null): bool
    {
        $at = strtotime($record['audit']['at'] ?? ''); $now = $now ?? time();
        $plan = get_option('jt_wp_care_update_plan', []);
        if (in_array($plan['state'] ?? '', ['ready', 'running'], true)) { return false; }
        if (in_array($plan['state'] ?? '', ['complete', 'stopped', 'closed'], true) && $at < strtotime($plan['updated'] ?? '')) { return true; }
        return !isset($record['audit']['automatic']) || $at === false || $at > $now + 300 || $now - $at >= 3600;
    }
    public function refresh(string $revision): bool
    {
        $record = $this->store->load();
        if (!self::due($record)) { return false; }
        $record['audit'] = (new Collector())->collect($record['external_use'] ?? 'unknown');
        // Existing items, notes and human XML-RPC attestations are preserved verbatim.
        $this->store->save($record, $revision);
        return true;
    }
    public function run(): void
    {
        Access::post('jt_care_auto_audit');
        try { $this->refresh(Access::field('revision')); wp_send_json_success(); }
        catch (\Throwable $error) { wp_send_json_error(['message' => 'Automatická kontrola se neuložila. Obnov stránku nebo použij ruční diagnostiku.'], 409); }
    }
    public function render(): void
    {
        Access::authorize(); $record = $this->store->load();
        echo '<section class="jt-card"><h2>Automatické kontroly</h2><p>Kontrola se sama obnoví při otevření JT WP Care, nejvýše jednou za hodinu. Nemění nastavení webu ani neinstaluje aktualizace.</p>';
        $labels = ['php' => 'Podpora větve PHP', 'file_edit' => 'Editor souborů', 'debug' => 'Debug režim', 'wordpress' => 'WordPress', 'plugins' => 'Pluginy', 'theme' => 'Šablony', 'woocommerce' => 'WooCommerce', 'wc_database' => 'Databáze WooCommerce', 'wc_queue' => 'Naplánované úlohy', 'login_session' => 'Přihlášení správce', 'backoffice' => 'Backoffice', 'frontoffice' => 'Front office', 'basic_pages' => 'Základní stránky'];
        foreach (Evaluation::results($record['audit'] ?? null) as $id => $result) {
            $state = in_array($result['state'] ?? '', ['done', 'problem', 'deferred', 'unknown', 'na'], true) ? $result['state'] : 'unknown';
            echo '<p class="jt-status jt-status-' . esc_attr($state) . '"><strong>' . esc_html($labels[$id] ?? $id) . ' — ' . esc_html(['done' => '✓ Ověřeno', 'problem' => 'Vyžaduje pozornost', 'deferred' => 'Odloženo', 'na' => 'Netýká se', 'unknown' => 'Neověřeno'][$state]) . '</strong><br>' . esc_html($result['text']) . '</p>';
        }
        if (self::due($record)) {
            echo '<p id="jt-auto-status" data-url="' . esc_url(admin_url('admin-post.php')) . '" data-nonce="' . esc_attr(wp_create_nonce('jt_care_auto_audit')) . '" data-revision="' . esc_attr($record['revision']) . '">Čeká na automatické obnovení diagnostiky…</p>';
            wp_enqueue_script('jt-care-automatic', plugins_url('assets/automatic.js', dirname(__DIR__, 3) . '/bootstrap.php'), [], \JT\WPCare\Core\Release::VERSION, true);
        }
        echo '</section>';
    }
}
