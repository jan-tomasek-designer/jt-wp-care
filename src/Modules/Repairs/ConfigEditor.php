<?php
declare(strict_types=1);
namespace JT\WPCare\Modules\Repairs;

/**
 * Performs one narrowly scoped wp-config.php repair.
 *
 * The editor never evaluates the configuration. It accepts only conventional,
 * literal define() statements and refuses ambiguous or externally supplied
 * enabled values.
 */
final class ConfigEditor
{
    private const TARGETS = ['WP_DEBUG', 'WP_DEBUG_DISPLAY', 'WP_DEBUG_LOG'];
    private string $path;
    private string $backupDirectory;
    private array $runtime;

    public function __construct(?string $path = null, ?string $backupDirectory = null, ?array $runtime = null)
    {
        $this->path = $path ?? self::locate();
        $this->backupDirectory = $backupDirectory ?? dirname($this->path) . '/.jt-wp-care-backups';
        $this->runtime = $runtime ?? array_combine(self::TARGETS, array_map(
            static fn(string $name): bool => defined($name) && constant($name) !== false,
            self::TARGETS
        ));
    }

    public function inspect(): array
    {
        try {
            $source = $this->source();
            $planned = $this->plan($source);
            return [
                'ready' => true,
                'needed' => !hash_equals(hash('sha256', $source), hash('sha256', $planned)),
                'file' => basename($this->path),
                'runtime' => $this->runtime,
                'reason' => '',
            ];
        } catch (\DomainException $error) {
            return ['ready' => false, 'needed' => true, 'file' => basename($this->path), 'runtime' => $this->runtime, 'reason' => $error->getMessage()];
        }
    }

    /** @return array{backup:string,old_hash:string,new_hash:string,changed:string[]} */
    public function disableDebug(): array
    {
        $source = $this->source();
        $planned = $this->plan($source);
        if (hash_equals(hash('sha256', $source), hash('sha256', $planned))) {
            throw new \DomainException('Debug konstanty už mají bezpečné hodnoty; soubor nebyl změněn.');
        }
        $backup = $this->backup($source);
        try {
            $this->writeLocked($source, $planned);
        } catch (\Throwable $error) {
            $current = @file_get_contents($this->path);
            try {
                if (!is_string($current)) { throw new \RuntimeException('Unreadable configuration after failed write.'); }
                $this->writeLocked($current, $source);
            } catch (\Throwable $restoreError) {
                throw new \DomainException('Zápis wp-config.php selhal a automatický návrat se nepotvrdil. Použijte uloženou zálohu konfigurace a obnovu hostingu.');
            }
            throw new \DomainException('Zápis wp-config.php se nepodařil; původní obsah byl obnoven ze zálohy.');
        }
        return ['backup' => $backup, 'old_hash' => hash('sha256', $source), 'new_hash' => hash('sha256', $planned), 'changed' => self::TARGETS];
    }

    public function restore(array $operation): void
    {
        $backup = isset($operation['backup']) && is_string($operation['backup']) ? $operation['backup'] : '';
        $newHash = isset($operation['new_hash']) && is_string($operation['new_hash']) ? $operation['new_hash'] : '';
        if ($backup === '' || $newHash === '' || dirname($backup) !== $this->backupDirectory) {
            throw new \DomainException('Záloha konfigurace není platná pro tento web.');
        }
        $current = $this->source();
        if (!hash_equals($newHash, hash('sha256', $current))) {
            throw new \DomainException('wp-config.php byl po opravě dále změněn; automatický návrat byl z bezpečnostních důvodů zastaven.');
        }
        $encoded = @file_get_contents($backup);
        $marker = "__halt_compiler();\n";
        $offset = is_string($encoded) ? strpos($encoded, $marker) : false;
        $original = $offset === false ? false : base64_decode(substr($encoded, $offset + strlen($marker)), true);
        if (!is_string($original) || !hash_equals((string) ($operation['old_hash'] ?? ''), hash('sha256', $original))) {
            throw new \DomainException('Záložní kopie wp-config.php neprošla kontrolou integrity.');
        }
        $this->validatePhp($original);
        $this->writeLocked($current, $original);
    }

    public function plan(string $source): string
    {
        $this->validatePhp($source);
        $planned = $source;
        $definitions = $this->definitions($source);
        $missing = [];
        $replacements = [];
        foreach (self::TARGETS as $name) {
            $found = $definitions[$name] ?? [];
            if (count($found) > 1 || ($found !== [] && $found[0]['literal'] === false)) {
                throw new \DomainException($name . ' je ve wp-config.php zapsán nejednoznačně; automatická oprava byla odmítnuta.');
            }
            if ($found !== []) {
                if (($this->runtime[$name] ?? false) === true && $found[0]['value'] === false) {
                    throw new \DomainException($name . ' je za běhu aktivní, přestože nalezená definice je false; hodnotu zřejmě určuje jiný zdroj a oprava byla odmítnuta.');
                }
                $replacements[] = ['start' => $found[0]['start'], 'length' => $found[0]['length'], 'text' => "define('{$name}', false);"];
                continue;
            }
            if (($this->runtime[$name] ?? false) === true) {
                throw new \DomainException($name . ' je aktivní, ale jeho jednoznačná definice není ve wp-config.php; opravu je nutné provést ručně u zdroje hodnoty.');
            }
            $missing[] = $name;
        }
        usort($replacements, static fn(array $a, array $b): int => $b['start'] <=> $a['start']);
        foreach ($replacements as $replacement) {
            $planned = substr_replace($planned, $replacement['text'], $replacement['start'], $replacement['length']);
        }
        if ($missing !== []) {
            $lineBreak = str_contains($planned, "\r\n") ? "\r\n" : "\n";
            $definitions = $lineBreak . )l nalezen standardní řádek pro načtení WordPressu; chybějící konstanty nelze bezpečně vložit.');
            }
            $planned = (string) preg_replace($loader, $definitions . '$0', $planned, 1);
        }
        $this->validatePhp($planned);
        return $planned;
    }

    /** @return array<string,array<int,array{start:int,length:int,literal:bool,value:?bool}>> */
    private function definitions(string $source): array
    {
        $tokens = token_get_all($source, TOKEN_PARSE); $items = []; $offset = 0;
        foreach ($tokens as $token) {
            $text = is_array($token) ? $token[1] : $token;
            $items[] = ['id' => is_array($token) ? $token[0] : null, 'text' => $text, 'start' => $offset, 'end' => $offset + strlen($text)];
            $offset += strlen($text);
        }
        $significant = array_values(array_filter($items, static fn(array $item): bool => !in_array($item['id'], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
        $found = [];
        for ($i = 0, $count = count($significant); $i < $count; ++$i) {
            $item = $significant[$i];
            if ($item['id'] !== T_STRING || strtolower($item['text']) !== 'define' || ($significant[$i + 1]['text'] ?? '') !== '(') { continue; }
            $nameToken = $significant[$i + 2] ?? null;
            if (!is_array($nameToken) || $nameToken['id'] !== T_CONSTANT_ENCAPSED_STRING) { continue; }
            $name = $this->literalString($nameToken['text']);
            if (!in_array($name, self::TARGETS, true) || ($significant[$i + 3]['text'] ?? '') !== ',') { continue; }
            $value = $significant[$i + 4] ?? null; $close = $significant[$i + 5] ?? null; $semicolon = $significant[$i + 6] ?? null;
            $literal = is_array($value) && $value['id'] === T_STRING && in_array(strtolower($value['text']), ['true', 'false'], true)
                && ($close['text'] ?? '') === ')' && ($semicolon['text'] ?? '') === ';';
            $end = $literal ? $semicolon['end'] : ($nameToken['end'] ?? $item['end']);
            $found[$name][] = ['start' => $item['start'], 'length' => $end - $item['start'], 'literal' => $literal,
                'value' => $literal ? strtolower($value['text']) === 'true' : null];
        }
        return $found;
    }

    private function literalString(string $literal): string
    {
        $quote = $literal[0] ?? '';
        $body = strlen($literal) >= 2 ? substr($literal, 1, -1) : '';
        return $quote === "'" ? str_replace(["\\\\", "\\'"], ["\\", "'"], $body) : stripcslashes($body);
    }

    private static function locate(): string
    {
        $inside = defined('ABSPATH') ? rtrim((string) ABSPATH, '/\\') . '/wp-config.php' : '';
        if ($inside !== '' && is_file($inside)) { return $inside; }
        $above = defined('ABSPATH') ? dirname(rtrim((string) ABSPATH, '/\\')) . '/wp-config.php' : '';
        if ($above !== '' && is_file($above) && !is_file(dirname(rtrim((string) ABSPATH, '/\\')) . '/wp-settings.php')) { return $above; }
        throw new \DomainException('Aktivní wp-config.php nebyl nalezen v podporovaném umístění.');
    }

    private function source(): string
    {
        if (!is_file($this->path) || is_link($this->path) || !is_readable($this->path) || !is_writable($this->path)) {
            throw new \DomainException('wp-config.php není běžný čitelný a zapisovatelný soubor; automatická oprava není dostupná.');
        }
        $size = filesize($this->path);
        if (!is_int($size) || $size < 20 || $size > 2097152) {
            throw new \DomainException('wp-config.php má neočekávanou velikost; automatická oprava není dostupná.');
        }
        $source = file_get_contents($this->path);
        if (!is_string($source)) { throw new \DomainException('wp-config.php se nepodařilo bezpečně načíst.'); }
        $this->validatePhp($source);
        return $source;
    }

    private function validatePhp(string $source): void
    {
        if (!str_starts_with(ltrim($source), '<?php')) { throw new \DomainException('Konfigurační soubor nemá očekávaný PHP formát.'); }
        try { token_get_all($source, TOKEN_PARSE); }
        catch (\ParseError $error) { throw new \DomainException('PHP syntaxe konfiguračního souboru není platná; soubor nebyl změněn.'); }
    }

    private function backup(string $source): string
    {
        if (!is_dir($this->backupDirectory) && !@mkdir($this->backupDirectory, 0700, true) && !is_dir($this->backupDirectory)) {
            throw new \DomainException('Nelze vytvořit chráněnou složku pro zálohu wp-config.php; soubor nebyl změněn.');
        }
        @chmod($this->backupDirectory, 0700);
        foreach (['index.php' => "<?php http_response_code(404); exit;\n", '.htaccess' => "Require all denied\nDeny from all\n", 'web.config' => "<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n"] as $name => $body) {
            $control = $this->backupDirectory . '/' . $name;
            if (!file_exists($control)) { @file_put_contents($control, $body, LOCK_EX); }
        }
        try { $random = bin2hex(random_bytes(8)); }
        catch (\Throwable $error) { throw new \DomainException('Nelze vytvořit bezpečný název zálohy konfigurace.'); }
        $path = $this->backupDirectory . '/wp-config-' . gmdate('Ymd-His') . '-' . $random . '.php';
        $payload = "<?php\nhttp_response_code(404); exit;\n__halt_compiler();\n" . base64_encode($source);
        if (file_put_contents($path, $payload, LOCK_EX) !== strlen($payload)) {
            @unlink($path);
            throw new \DomainException('Záložní kopii wp-config.php se nepodařilo uložit; soubor nebyl změněn.');
        }
        @chmod($path, 0600);
        return $path;
    }

    private function writeLocked(string $expected, string $replacement): void
    {
        $this->validatePhp($replacement);
        $handle = @fopen($this->path, 'r+b');
        if (!is_resource($handle) || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) { fclose($handle); }
            throw new \RuntimeException('Configuration lock failed.');
        }
        try {
            rewind($handle);
            $current = stream_get_contents($handle);
            if (!is_string($current) || !hash_equals(hash('sha256', $expected), hash('sha256', $current))) {
                throw new \RuntimeException('Configuration changed concurrently.');
            }
            rewind($handle);
            if (!ftruncate($handle, 0)) { throw new \RuntimeException('Configuration truncate failed.'); }
            $length = strlen($replacement); $written = 0;
            while ($written < $length) {
                $part = fwrite($handle, substr($replacement, $written));
                if (!is_int($part) || $part < 1) { throw new \RuntimeException('Configuration write failed.'); }
                $written += $part;
            }
            if (!fflush($handle)) { throw new \RuntimeException('Configuration flush failed.'); }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
        $saved = @file_get_contents($this->path);
        if (!is_string($saved) || !hash_equals(hash('sha256', $replacement), hash('sha256', $saved))) {
            throw new \RuntimeException('Configuration verification failed.');
        }
    }
}
