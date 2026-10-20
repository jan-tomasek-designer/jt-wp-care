<?php
declare(strict_types=1);
namespace JT\WPCareInstaller;

/** Public releases only. Trust is the fixed GitHub repository over HTTPS, not a signature. */
final class Repository
{
    public const REPO = 'jan-tomasek-designer/jt-wp-care';
    public const API = 'https://api.github.com/repos/' . self::REPO . '/releases?per_page=30';

    public static function allowed(string $url): bool
    {
        $p = parse_url($url);
        if (!$p || ($p['scheme'] ?? '') !== 'https' || isset($p['user'], $p['pass']) || isset($p['user']) || isset($p['fragment'])
            || (isset($p['port']) && $p['port'] !== 443)) { return false; }
        $host = strtolower($p['host'] ?? '');
        if ($host === 'api.github.com') { return $url === self::API; }
        if ($host === 'github.com') { return str_starts_with($p['path'] ?? '', '/' . self::REPO . '/releases/download/'); }
        return in_array($host, ['release-assets.githubusercontent.com', 'objects.githubusercontent.com'], true);
    }

    public function get(string $url, int $limit): string
    {
        for ($i = 0; $i < 5; ++$i) {
            if (!self::allowed($url) || !wp_http_validate_url($url)) { throw new \DomainException('Vydání odkazuje mimo povolený HTTPS zdroj.'); }
            $response = wp_safe_remote_get($url, ['timeout' => 30, 'redirection' => 0, 'limit_response_size' => $limit + 1,
                'headers' => ['Accept' => 'application/vnd.github+json', 'User-Agent' => 'JT-WP-Care-Installer/0.2.0']]);
            if (is_wp_error($response)) { throw new \DomainException('GitHub není dostupný. Běžící verze zůstává zachovaná.'); }
            $code = wp_remote_retrieve_response_code($response);
            if (in_array($code, [301, 302, 303, 307, 308], true)) { $url = (string) wp_remote_retrieve_header($response, 'location'); continue; }
            if ($code !== 200) { throw new \DomainException('GitHub vrátil HTTP ' . $code . '. Ověř veřejnost repozitáře, publikované vydání a limit požadavků.'); }
            $body = wp_remote_retrieve_body($response);
            if (!is_string($body) || strlen($body) > $limit) { throw new \DomainException('Stažený soubor překročil povolenou velikost.'); }
            return $body;
        }
        throw new \DomainException('Příliš mnoho přesměrování při stahování.');
    }

    public static function choose(array $releases, string $current, bool $alpha): ?array
    {
        $best = null;
        foreach ($releases as $release) {
            if (!is_array($release) || ($release['draft'] ?? true) !== false || empty($release['published_at'])) { continue; }
            $tag = $release['tag_name'] ?? '';
            if (!is_string($tag) || !str_starts_with($tag, 'v')) { continue; }
            $v = substr($tag, 1);
            if (!Package::version($v) || (!$alpha && (!empty($release['prerelease']) || str_contains($v, '-')))
                || !version_compare($v, $current, '>') || ($best && !version_compare($v, $best['version'], '>'))) { continue; }
            $assets = [];
            foreach ($release['assets'] ?? [] as $asset) {
                if (!is_array($asset) || !is_string($asset['name'] ?? null)) { continue; }
                $expected = 'https://github.com/' . self::REPO . '/releases/download/' . $tag . '/' . $asset['name'];
                if (($asset['state'] ?? '') === 'uploaded' && ($asset['browser_download_url'] ?? '') === $expected) { $assets[$asset['name']] = $expected; }
            }
            $payload = 'jt-wp-care-' . $v . '-payload.zip';
            if (!isset($assets['manifest.json'], $assets[$payload])) { continue; }
            $best = ['version' => $v, 'manifest_url' => $assets['manifest.json'], 'url' => $assets[$payload], 'filename' => $payload,
                'notes' => substr(wp_strip_all_tags((string) ($release['body'] ?? '')), 0, 6000), 'prerelease' => !empty($release['prerelease']) || str_contains($v, '-')];
        }
        return $best;
    }

    public function check(string $current, bool $alpha, string $wpVersion): ?array
    {
        $releases = json_decode($this->get(self::API, 524288), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($releases) || isset($releases['message'])) { throw new \DomainException('Neplatný seznam vydání.'); }
        $offer = self::choose($releases, $current, $alpha);
        if (!$offer) { return null; }
        $manifest = json_decode($this->get($offer['manifest_url'], 32768), true, 32, JSON_THROW_ON_ERROR);
        Package::metadata($manifest, $offer['version'], $wpVersion);
        $asset = $manifest['assets'][$offer['filename']] ?? [];
        if (!is_string($asset['sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $asset['sha256'])
            || !is_int($asset['bytes'] ?? null) || $asset['bytes'] < 1 || $asset['bytes'] > Package::MAX_ZIP) { throw new \DomainException('Manifest neobsahuje platnou velikost a kontrolní součet.'); }
        return $offer + ['sha256' => $asset['sha256'], 'bytes' => $asset['bytes'], 'expected' => $current, 'checked' => time()];
    }

    public function download(array $offer): string
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $bytes = $this->get($offer['url'], $offer['bytes']);
        if (strlen($bytes) !== $offer['bytes'] || !hash_equals($offer['sha256'], hash('sha256', $bytes))) { throw new \DomainException('Balíček neodpovídá schválenému vydání. Znovu načti nabídku.'); }
        $file = wp_tempnam('jt-care-payload.zip');
        if (!$file) { throw new \DomainException('Nelze vytvořit dočasný soubor.'); }
        if (file_put_contents($file, $bytes) !== strlen($bytes)) { unlink($file); throw new \DomainException('Stažení nelze uložit na disk.'); }
        return $file;
    }
}
