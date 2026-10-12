<?php
declare(strict_types=1);
namespace JT\WPCareInstaller;

/** Separate from the MU namespace; only local direct filesystem updates, never an HTTP code fetcher. */
final class Installer
{
    public string $phase = 'kontrola prostředí';
    public static function environment(): void
    {
        foreach (['disk_free_space', 'flock', 'random_bytes', 'rename', 'mkdir', 'file_put_contents', 'hash_file', 'fopen', 'fclose', 'unlink', 'rmdir'] as $function) {
            if (!function_exists($function)) { throw new \DomainException('Hosting nemá dostupnou PHP funkci ' . $function . '. Aktualizace je zablokovaná; tuto informaci předej podpoře hostingu.'); }
        }
    }
    private string $base;
    private string $wpVersion;
    public function __construct(string $muDirectory, string $loaderHash, string $wpVersion)
    {
        self::environment();
        $mu = realpath($muDirectory);
        if ($mu === false || is_link($muDirectory)) { throw new \DomainException('MU adresář není dostupný nebo je odkazem.'); }
        $this->base = $mu . '/jt-wp-care'; $this->wpVersion = $wpVersion;
        foreach ([$this->base, $this->base . '/releases'] as $dir) {
            if (is_link($dir) || !is_dir($dir) || !is_writable($dir)) { throw new \DomainException('Hosting nedovoluje přímý zápis do instalace.'); }
        }
        $loader = $mu . '/jt-wp-care.php';
        if (is_link($loader) || !is_file($loader) || !hash_equals($loaderHash, (string) hash_file('sha256', $loader))) {
            throw new \DomainException('Loader se liší od podporovaného loaderu. Nejprve ověř instalaci 0.3.0 nebo novější.');
        }
    }

    public function state(): array
    {
        $file = $this->base . '/current.json';
        if (is_link($file) || !is_file($file) || filesize($file) > 4096) { throw new \DomainException('Neplatný ukazatel vydání.'); }
        $state = json_decode((string) file_get_contents($file), true);
        if (!is_array($state) || ($state['schema'] ?? null) !== 1 || !is_string($state['active'] ?? null) || !Package::version($state['active'])
            || (isset($state['previous']) && (!is_string($state['previous']) || !Package::version($state['previous'])))) { throw new \DomainException('Neplatný ukazatel vydání.'); }
        return $state;
    }

    public function install(string $file, string $digest, string $version, string $expected): array
    {
        $this->phase = 'ověření a čtení payload ZIPu';
        $files = Package::read($file, $digest, $version, $this->wpVersion);
        return $this->locked(function () use ($files, $version, $expected): array {
            $this->phase = 'kontrola stavu vydání';
            $state = $this->state();
            if ($state['active'] !== $expected || !version_compare($version, $state['active'], '>')) { throw new \DomainException('Aktivní verze se změnila nebo balíček není novější. Obnov stránku.'); }
            $this->compatible($state['active']);
            $target = $this->base . '/releases/' . $version;
            if (file_exists($target) || is_link($target)) { throw new \DomainException('Tato verze již na disku existuje. Nebude přepsána.'); }
            $this->phase = 'kontrola volného místa';
            $free = disk_free_space($this->base);
            if ($free === false || $free < 2 * array_sum(array_map('strlen', $files)) + 10485760) { throw new \DomainException('Nedostatek volného místa nebo nelze zjistit jeho velikost.'); }
            $this->phase = 'příprava nové složky';
            $stage = $this->base . '/releases/.stage-' . bin2hex(random_bytes(16));
            if (!mkdir($stage, 0755)) { throw new \DomainException('Nelze připravit novou verzi.'); }
            try {
                foreach ($files as $name => $bytes) {
                    $this->phase = 'zápis souborů nové verze';
                    $path = $stage . '/' . $name;
                    if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0755, true)) { throw new \DomainException('Nelze vytvořit podsložku nové verze. Ověř oprávnění a vlastníka adresáře releases.'); }
                    if (file_put_contents($path, $bytes, LOCK_EX) !== strlen($bytes) || !hash_equals(hash('sha256', $bytes), (string) hash_file('sha256', $path))) { throw new \DomainException('Zápis nebo následná kontrola souboru nové verze selhala. Ověř oprávnění a místo na disku.'); }
                }
                $this->phase = 'dokončení složky vydání';
                if (!rename($stage, $target)) { throw new \DomainException('Nelze přejmenovat připravenou složku na nové vydání.'); }
                $next = ['schema' => 1, 'active' => $version, 'previous' => $state['active']];
                $this->switchTo($next);
                return $next;
            } finally {
                // Remove only the random staging directory, never active/past releases.
                if (is_dir($stage) && !is_link($stage)) { $this->cleanStage($stage); }
            }
        });
    }

    public function rollback(string $expected): array
    {
        return $this->locked(function () use ($expected): array {
            $state = $this->state();
            if ($state['active'] !== $expected || empty($state['previous'])) { throw new \DomainException('Předchozí verze není dostupná nebo se stav změnil.'); }
            $this->compatible($state['previous']);
            $next = ['schema' => 1, 'active' => $state['previous'], 'previous' => $state['active']];
            $this->switchTo($next);
            return $next;
        });
    }

    private function compatible(string $version): void
    {
        $root = $this->base . '/releases/' . $version;
        if (is_link($root) || !is_dir($root)) { throw new \DomainException('Vydání není dostupné.'); }
        foreach (['bootstrap.php', 'autoload.php', 'release.json'] as $file) {
            if (is_link($root . '/' . $file) || !is_file($root . '/' . $file) || !is_readable($root . '/' . $file)) { throw new \DomainException('Vydání nemá potřebné vstupní soubory.'); }
        }
        if (filesize($root . '/release.json') > 4096) { throw new \DomainException('Neplatná metadata.'); }
        Package::metadata(json_decode((string) file_get_contents($root . '/release.json'), true), $version, $this->wpVersion);
    }

    private function locked(callable $operation): array
    {
        $this->phase = 'získání zámku aktualizace';
        $path = $this->base . '/.maintenance.lock';
        if (is_link($path)) { throw new \DomainException('Nepovolený odkaz zámku.'); }
        $lock = fopen($path, 'c');
        if ($lock === false) { throw new \DomainException('Nelze získat zámek aktualizace.'); }
        if (!flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); throw new \DomainException('Jiná aktualizace právě probíhá.'); }
        try { return $operation(); } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    private function switchTo(array $state): void
    {
        $this->phase = 'přepnutí aktivní verze';
        $temp = $this->base . '/.current-' . bin2hex(random_bytes(16)) . '.json';
        $bytes = json_encode($state, JSON_THROW_ON_ERROR) . "\n";
        try {
            if (file_put_contents($temp, $bytes, LOCK_EX) !== strlen($bytes)) { throw new \DomainException('Nelze zapsat nový ukazatel current.json. Původní ukazatel nebyl nahrazen.'); }
            // Same directory/filesystem. Never unlink the old pointer before rename.
            if (!rename($temp, $this->base . '/current.json')) { throw new \DomainException('Hosting nepovolil přepnutí. Původní ukazatel zůstal zachován.'); }
            clearstatcache();
        } finally { if (is_file($temp)) { unlink($temp); } }
    }

    private function cleanStage(string $stage): void
    {
        $parent = realpath($this->base . '/releases');
        if (realpath(dirname($stage)) !== $parent || !preg_match('/^\.stage-[a-f0-9]{32}$/D', basename($stage))) { throw new \RuntimeException('Unsafe cleanup path'); }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($stage, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) { if ($file->isDir() && !$file->isLink()) { rmdir($file->getPathname()); } else { unlink($file->getPathname()); } }
        rmdir($stage);
    }
}
