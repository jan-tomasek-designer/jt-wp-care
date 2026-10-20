<?php
declare(strict_types=1);
namespace JT\WPCare\Core;

/** Stable admin navigation. Unknown input always falls back to the overview. */
final class AdminTabs
{
    public static function all(): array
    {
        return ['overview' => 'Přehled', 'checklist' => 'Checklist', 'diagnostics' => 'Diagnostika a bezpečnost',
            'updates' => 'Aktualizace', 'report' => 'Report'];
    }

    public static function current(): string
    {
        $tab = isset($_GET['jt_tab']) && is_string($_GET['jt_tab']) ? wp_unslash($_GET['jt_tab']) : 'overview';
        return isset(self::all()[$tab]) ? $tab : 'overview';
    }

    public static function is(string $tab): bool { return self::current() === $tab; }

    public static function url(string $tab): string
    {
        if (!isset(self::all()[$tab])) { $tab = 'overview'; }
        return admin_url('tools.php?page=jt-wp-care&jt_tab=' . rawurlencode($tab));
    }
}
