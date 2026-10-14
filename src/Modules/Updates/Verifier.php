<?php
declare(strict_types=1);
namespace JT\WPCare\Modules\Updates;

use JT\WPCare\Modules\Functional\Inspector;

/** Runs the bounded functional inspector after one installed update. */
final class Verifier
{
    public function run(): array
    {
        $checks = (new Inspector())->collect();
        $failed = []; $unknown = [];
        foreach (['login_session', 'backoffice', 'frontoffice', 'basic_pages'] as $id) {
            $state = $checks[$id]['state'] ?? 'unknown';
            if ($state === 'problem') { $failed[] = $id; }
            elseif ($state !== 'done') { $unknown[] = $id; }
        }
        $state = $failed ? 'problem' : ($unknown ? 'unknown' : 'done');
        $labels = ['login_session' => 'přihlášená relace', 'backoffice' => 'administrace',
            'frontoffice' => 'úvodní stránka', 'basic_pages' => 'základní stránky'];
        $names = static fn(array $ids): string => implode(', ', array_map(static fn(string $id): string => $labels[$id] ?? $id, $ids));
        $text = $state === 'done'
            ? 'Technický post-test prošel: přihlášená relace, administrace, úvodní a základní stránky odpovídají.'
            : ($state === 'problem' ? 'Technický post-test našel problém: ' . $names($failed) . '.'
                : 'Technický post-test nebylo možné dokončit: ' . $names($unknown) . '.');
        return ['state' => $state, 'at' => gmdate('c'), 'text' => $text, 'checks' => $checks];
    }
}
