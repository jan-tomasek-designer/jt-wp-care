<?php
declare(strict_types=1);
namespace JT\WPCare\Modules\Dashboard;

use JT\WPCare\Contracts\ModuleInterface;
use JT\WPCare\Core\Access;

final class Module implements ModuleInterface
{
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
            wp_enqueue_style('jt-wp-care', plugins_url('assets/admin.css', dirname(__DIR__, 3) . '/bootstrap.php'), [], '0.2.0-alpha');
        }
    }
    public function render(): void
    {
        Access::authorize();
        echo '<div class="wrap jt-care"><h1>JT WP Care <small>0.2.0-alpha</small></h1><p>Servis podle metodiky 0.1 · ' . esc_html(home_url('/')) . '</p>';
        echo '<div class="notice notice-warning inline"><p>Testovací verze pro staging. Aktualizace i změny zabezpečení provádí správce ručně. Nejde o úplný bezpečnostní audit.</p></div>';
        if (isset($_GET['saved']) && $_GET['saved'] === '1') {
            echo '<div class="notice notice-success inline"><p>Operace byla uložena.</p></div>';
        }
        echo '<p>Záloha → diagnostika → plán → WordPress → pluginy → šablony → WooCommerce → funkční test → bezpečnost → report</p>';
        try { do_action('jt_wp_care_sections'); }
        catch (\Throwable $error) { echo '<div class="notice notice-error inline"><p>Záznam nelze zobrazit. Zkontrolujte jeho formát a kompatibilitu verze pluginu.</p></div>'; }
        echo '</div>';
    }
}
