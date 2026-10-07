<?php
declare(strict_types=1);
namespace JT\WPCareInstaller;

/** Manual, authenticated-operator trust: expected digest comes from the repository, not the ZIP. */
final class Package
{
    public const MAX_ZIP = 8388608;
    public static function version(string $version): bool
    {
        return strlen($version) <= 80 && (bool) preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:-[a-zA-Z0-9]+(?:[.-][a-zA-Z0-9]+)*)?$/D', $version);
    }

    public static function read(string $file, string $digest, string $version, string $wpVersion): array
    {
        if (!class_exists(\ZipArchive::class)) { throw new \DomainException('Hosting nemá rozšíření PHP zip. Aktualizace nebyla spuštěna.'); }
        if (!self::version($version) || !preg_match('/^[a-f0-9]{64}$/D', $digest) || !is_file($file) || is_link($file)
            || filesize($file) > self::MAX_ZIP || !hash_equals($digest, (string) hash_file('sha256', $file))) {
            throw new \DomainException('Nesouhlasí verze, velikost nebo SHA-256 balíčku. Použij payload ZIP a jeho kontrolní součet z GitHubu.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($file, \ZipArchive::RDONLY) !== true) { throw new \DomainException('ZIP nelze přečíst.'); }
        $files = []; $seen = []; $total = 0;
        try {
            if ($zip->numFiles < 3 || $zip->numFiles > 1500) { throw new \DomainException('Nepovolený počet souborů.'); }
            $prefix = 'jt-wp-care/releases/' . $version . '/';
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $stat = $zip->statIndex($i);
                if (!$stat || !str_starts_with($stat['name'], $prefix)) { throw new \DomainException('ZIP obsahuje soubory mimo očekávané vydání.'); }
                $name = substr($stat['name'], strlen($prefix));
                $allowed = in_array($name, ['bootstrap.php', 'autoload.php', 'release.json'], true)
                    || preg_match('~^src/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+\.php$~D', $name)
                    || preg_match('~^assets/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+\.(?:css|js|png|jpg|svg|woff2)$~D', $name);
                $os = 0; $attr = 0;
                if (!$allowed || isset($seen[strtolower($name)]) || ($stat['encryption_method'] ?? 0) !== 0
                    || !$zip->getExternalAttributesIndex($i, $os, $attr)
                    || ($os === \ZipArchive::OPSYS_UNIX && (($attr >> 16) & 0170000) !== 0100000)) {
                    throw new \DomainException('ZIP obsahuje nepovolenou cestu, odkaz, duplicitu nebo typ souboru.');
                }
                $seen[strtolower($name)] = true;
                $total += $stat['size'];
                if ($stat['size'] > 4194304 || $total > 33554432) { throw new \DomainException('Rozbalený balíček překračuje limit.'); }
                $bytes = $zip->getFromIndex($i, $stat['size'] + 1);
                if (!is_string($bytes) || strlen($bytes) !== $stat['size']) { throw new \DomainException('Soubor v ZIPu je poškozený.'); }
                $files[$name] = $bytes;
            }
        } finally { $zip->close(); }
        foreach (['bootstrap.php', 'autoload.php', 'release.json', 'src/Core/Release.php'] as $required) {
            if (!isset($files[$required])) { throw new \DomainException('Balíček není kompletní.'); }
        }
        $meta = json_decode($files['release.json'], true);
        self::metadata($meta, $version, $wpVersion);
        return $files;
    }

    public static function metadata($meta, string $version, string $wpVersion): void
    {
        if (!is_array($meta) || ($meta['schema'] ?? null) !== 1 || ($meta['version'] ?? null) !== $version
            || ($meta['loader_protocol'] ?? null) !== 1 || ($meta['data_schema'] ?? null) !== 1) {
            throw new \DomainException('Nepodporovaný protokol nebo datové schéma vydání.');
        }
        foreach (['php_min' => PHP_VERSION, 'wp_min' => $wpVersion] as $key => $actual) {
            if (!is_string($meta[$key] ?? null) || !preg_match('/^[0-9]+\.[0-9]+(?:\.[0-9]+)?$/D', $meta[$key]) || version_compare($actual, $meta[$key], '<')) {
                throw new \DomainException('Vydání vyžaduje novější PHP nebo WordPress.');
            }
        }
    }
}
