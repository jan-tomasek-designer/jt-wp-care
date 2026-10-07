<?php
declare(strict_types=1);
namespace JT\WPCare\Modules\Security;

use JT\WPCare\Contracts\ModuleInterface;
use JT\WPCare\Core\Access;
use JT\WPCare\Core\Store;

final class Module implements ModuleInterface
{
    private Store $store;
    public function __construct(Store $store) { $this->store = $store; }
    public function id(): string { return 'security'; }
    public function register(): void
    {
        add_action('jt_wp_care_sections', [$this, 'render'], 10);
        add_action('admin_post_jt_care_audit', [$this, 'run']);
    }
    public function run(): void
    {
        Access::post('jt_care_audit');
        try {
            $record = $this->store->load();
            $use = Access::field('external_use');
            $note = trim(sanitize_textarea_field(Access::field('external_note')));
            if (!in_array($use, ['unknown', 'used', 'unused'], true) || strlen($note) > 3000 || ($use !== 'unknown' && $note === '')) {
                throw new \DomainException('Doplňte platný stav externího použití a poznámku, jak byl ověřen.');
            }
            $record['external_use'] = $use;
            $record['external_note'] = $note;
            $record['external_at'] = gmdate('c');
            $record['external_by'] = get_current_user_id();
            $record['audit'] = (new Collector())->collect($use);
            $this->store->save($record, Access::field('revision'));
        } catch (\Throwable $error) { Access::fail($error); }
        Access::redirect();
    }
    public function render(): void
    {
        Access::authorize();
        $record = $this->store->load();
        echo '<section class="jt-card"><h2>Diagnostika a Security Audit</h2><p>Kontrola proběhne až po stisku tlačítka. Výsledek je uložený snímek, nikoli živý monitoring. Nejdříve uložte případné změny checklistu níže.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('jt_care_audit');
        echo '<input type="hidden" name="action" value="jt_care_audit"><input type="hidden" name="revision" value="' . esc_attr($record['revision']) . '">';
        echo '<p><label for="jt-external">Používá web XML-RPC pro vzdálené publikování, mobilní klienty nebo vlastní integrace?</label></p><select id="jt-external" name="external_use">';
        foreach (['unknown' => 'Nevím / neověřeno', 'used' => 'Ano, používá', 'unused' => 'Správce ověřil, že nepoužívá'] as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($record['external_use'] ?? 'unknown', $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select><p><label for="jt-external-note">Jak a kdy bylo použití ověřeno</label></p><textarea class="large-text" id="jt-external-note" name="external_note" rows="2" maxlength="1000">' . esc_textarea($record['external_note'] ?? '') . '</textarea>';
        submit_button('Spustit a uložit lokální diagnostiku', 'secondary');
        echo '</form>';
        if (is_array($record['audit'] ?? null)) {
            $audit = $record['audit'];
            echo '<h3>Poslední snímek: ' . esc_html($audit['at']) . '</h3><p>' . esc_html($audit['source']) . '</p><table class="jt-facts"><tbody>';
            foreach ($audit['facts'] as $label => $value) { echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . esc_html($value) . '</td></tr>'; }
            echo '</tbody></table><details><summary>Seznam běžných pluginů a uložené nabídky aktualizací</summary><table class="jt-facts"><thead><tr><th>Plugin</th><th>Verze</th><th>Stav</th><th>Aktualizace</th></tr></thead><tbody>';
            foreach ($audit['plugins'] as $plugin) {
                echo '<tr><td>' . esc_html($plugin['name']) . '</td><td>' . esc_html($plugin['version']) . '</td><td>' . ($plugin['active'] ? 'Aktivní' : 'Neaktivní') . '</td><td>' . esc_html($plugin['update']) . '</td></tr>';
            }
            echo '</tbody></table></details>';
        } else { echo '<p>Diagnostika zatím nebyla spuštěna.</p>'; }
        echo '</section>';
    }
}
