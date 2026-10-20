<?php
declare(strict_types=1);
namespace JT\WPCareInstaller;

/** Narrow fallback for our CM_STORE release format. No decompression or filesystem extraction. */
final class StoredZip
{
    public int $numFiles = 0;
    private array $entries = [];

    public function open(string $file, int $flags = 0): bool
    {
        $data = file_get_contents($file);
        if (!is_string($data) || strlen($data) < 22 || strlen($data) > Package::MAX_ZIP) { return false; }
        $end = strlen($data) - 22;
        if (substr($data, $end, 4) !== "PK\x05\x06") { return false; }
        $e = unpack('vdisk/vstart/vlocal/vcount/Vsize/Voffset/vcomment', substr($data, $end + 4, 18));
        if ($e['disk'] !== 0 || $e['start'] !== 0 || $e['comment'] !== 0 || $e['local'] !== $e['count']
            || $e['count'] < 3 || $e['count'] > 1500 || $e['offset'] + $e['size'] !== $end) { return false; }
        $pos = $e['offset']; $localEnd = 0;
        for ($i = 0; $i < $e['count']; ++$i) {
            if ($pos + 46 > $end || substr($data, $pos, 4) !== "PK\x01\x02") { return false; }
            $h = unpack('vmade/vneeded/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vsize/vname/vextra/vcomment/vdisk/vinternal/Vexternal/Voffset', substr($data, $pos + 4, 42));
            $next = $pos + 46 + $h['name'] + $h['extra'] + $h['comment'];
            // Reject ZIP64, encryption, data descriptors, compression and split archives.
            if ($next > $end || $h['needed'] > 20 || ($h['flags'] & ~0x0800) !== 0 || $h['method'] !== 0
                || $h['disk'] !== 0 || $h['compressed'] !== $h['size'] || $h['size'] > 4194304 || $h['offset'] !== $localEnd) { return false; }
            $name = substr($data, $pos + 46, $h['name']);
            $offset = $h['offset'];
            if ($offset + 30 > $e['offset'] || substr($data, $offset, 4) !== "PK\x03\x04") { return false; }
            $local = unpack('vneeded/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vsize/vname/vextra', substr($data, $offset + 4, 26));
            foreach (['needed', 'flags', 'method', 'crc', 'compressed', 'size', 'name'] as $key) {
                if ($local[$key] !== $h[$key]) { return false; }
            }
            $start = $offset + 30 + $local['name'] + $local['extra'];
            $localEnd = $start + $h['size'];
            if ($localEnd > $e['offset'] || substr($data, $offset + 30, $local['name']) !== $name) { return false; }
            $bytes = substr($data, $start, $h['size']);
            if (hash('crc32b', $bytes) !== sprintf('%08x', $h['crc'])) { return false; }
            $this->entries[] = ['name' => $name, 'size' => $h['size'], 'encryption_method' => 0,
                'os' => $h['made'] >> 8, 'attr' => $h['external'], 'bytes' => $bytes];
            $pos = $next;
        }
        if ($pos !== $end || $localEnd !== $e['offset']) { $this->entries = []; return false; }
        $this->numFiles = count($this->entries);
        return true;
    }
    public function statIndex(int $index): array { return $this->entries[$index]; }
    public function getExternalAttributesIndex(int $index, int &$os, int &$attr): bool
    {
        $os = $this->entries[$index]['os']; $attr = $this->entries[$index]['attr']; return true;
    }
    public function getFromIndex(int $index, int $length): string { return $this->entries[$index]['bytes']; }
    public function close(): void { $this->entries = []; }
}
