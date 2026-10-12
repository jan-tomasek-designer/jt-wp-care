<?php
declare(strict_types=1);
namespace JT\WPCare\Modules\Checklist;

use JT\WPCare\Contracts\ModuleInterface;
use JT\WPCare\Core\Access;
use JT\WPCare\Core\Store;

final class Module implements ModuleInterface
{
    private Store $store;
    public function __construct(Store $store) { $this->store = $store; }
    public function id(): string { return 'checklist'; }
    public function register(): void
    {
        add_action('jt_wp_care_sections', [$this, 'render'], 20);
        add_action('admin_post_jt_care_save', [$this, 'save']);
    }

    public function save(): void
    {
        Access::post('jt_care_save');
        try {
            $record = $this->store->load();
            $input = isset($_POST['items']) && is_array($_POST['items']) ? wp_unslash($_POST['items']) : [];
            $record['items'] = Catalog::normalize($input, $record['items'], $record['audit'] ?? null);
            $this->store->save($record, Access::field('revision'));
        } catch (\Throwable $error) { Access::fail($error); }
        Access::redirect();
    }

    public function render(): void
    {
        Access::authorize();
        $record = $this->store->load();
        echo '<section class="jt-card"><h2>Servisní checklist</h2><p>Jeden průběžně ukládaný servis. Diagnostika předvyplní zjištěné údaje u jednotlivých bodů. PHP a editor souborů vyhodnocuje automatika. Ručně doplníš jen skutečné funkční testy a výjimky. Poznámky neobsahují hesla ani osobní údaje zákazníků. Časy jsou v UTC.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('jt_care_save');
        echo '<input type="hidden" name="action" value="jt_care_save"><input type="hidden" name="revision" value="' . esc_attr($record['revision']) . '">';
        foreach (Catalog::groups() as $group => $items) {
            echo '<h3>' . esc_html($group) . '</h3>';
            foreach ($items as $id => [$label, $hint]) {
                $row = $record['items'][$id] ?? ['state' => 'unknown', 'note' => '', 'at' => ''];
                echo '<div class="jt-row"><div><label for="jt-' . esc_attr($id) . '"><strong>' . esc_html($label) . '</strong></label><p>' . esc_html($hint) . '</p></div><div>';
                if (is_array($record['audit'] ?? null)) {
                    $evidence = Evidence::forItem($id, $record['audit']);
                    if ($evidence !== '') { echo '<div class="jt-evidence"><strong>Zjištěno automaticky</strong><small> — snímek ' . esc_html($record['audit']['at'] ?? '') . '</small><pre>' . esc_html($evidence) . '</pre></div>'; }
                } else { echo '<p>Pro předvyplnění údajů nejprve spusť diagnostiku nahoře.</p>'; }
                $automatic = \JT\WPCare\Modules\Automatic\Evaluation::item($id, $record['audit'] ?? null);
                if ($automatic !== null) {
                    echo '<p><strong>Automaticky: ' . esc_html(Catalog::states()[$automatic['state']] ?? 'Neověřeno') . '</strong><br>' . esc_html($automatic['text']) . '</p>';
                    if ($row['note'] !== '' || ($row['state'] ?? 'unknown') !== 'unknown') { echo '<details><summary>Dřívější ruční záznam (zachován)</summary><p>' . esc_html(Catalog::states()[$row['state']] ?? 'Neověřeno') . ' — ' . esc_html($row['note']) . '</p></details>'; }
                    echo '</div></div>'; continue;
                }
                echo '<p><strong>Co ověřit:</strong> ' . esc_html(Evidence::instruction($id)) . '</p>';
                echo '<label for="jt-' . esc_attr($id) . '">Výsledek tvého ověření </label>';
                echo '<select id="jt-' . esc_attr($id) . '" name="items[' . esc_attr($id) . '][state]">';
                foreach (Catalog::states() as $value => $text) {
                    echo '<option value="' . esc_attr($value) . '" ' . selected($row['state'], $value, false) . '>' . esc_html($text) . '</option>';
                }
                echo '</select><label class="screen-reader-text" for="jt-note-' . esc_attr($id) . '">Vlastní poznámka: ' . esc_html($label) . '</label>';
                echo '<textarea id="jt-note-' . esc_attr($id) . '" name="items[' . esc_attr($id) . '][note]" rows="2" maxlength="2000" placeholder="Doplň pouze ruční ověření nebo důvod. Automatické údaje výše neopisuj.">' . esc_textarea($row['note']) . '</textarea>';
                echo '<small>' . esc_html($row['at'] ?? '') . '</small></div></div>';
            }
        }
        submit_button('Uložit checklist');
        echo '</form></section>';
    }
}
