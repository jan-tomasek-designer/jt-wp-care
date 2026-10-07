<?php
declare(strict_types=1);
namespace JT\WPCare\Core;

/** Shared request boundary; a nonce never replaces the capability check. */
final class Access
{
    public static function authorize(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění k JT WP Care.', '', ['response' => 403]);
            exit;
        }
    }

    public static function post(string $action): void
    {
        self::authorize();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            wp_die('Použijte formulář v administraci.', '', ['response' => 405]);
            exit;
        }
        check_admin_referer($action);
    }

    public static function field(string $name): string
    {
        return isset($_POST[$name]) && is_string($_POST[$name]) ? wp_unslash($_POST[$name]) : '';
    }

    public static function redirect(): void
    {
        wp_safe_redirect(admin_url('tools.php?page=jt-wp-care&saved=1'));
        exit;
    }

    public static function fail(\Throwable $error): void
    {
        // Only domain errors have messages safe to expose; no paths/SQL traces.
        $message = $error instanceof \DomainException ? $error->getMessage() : 'Operace se nezdařila. Obnovte stránku a ověřte uložený stav.';
        wp_die(esc_html($message), 'JT WP Care', ['response' => 409, 'back_link' => true]);
        exit;
    }
}
