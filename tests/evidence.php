<?php
declare(strict_types=1);
require __DIR__ . '/wp-doubles.php';
require dirname(__DIR__) . '/autoload.php';
use JT\WPCare\Modules\Checklist\Evidence;
use JT\WPCare\Modules\Checklist\Catalog;
use JT\WPCare\Modules\Checklist\Module;
use JT\WPCare\Modules\Reports\Formatter;
use JT\WPCare\Core\Store;

function expect(bool $condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } }
$audit = ['at' => '2026-10-07T12:00:00Z', 'source' => 'Test', 'facts' => ['WordPress' => '6.0', 'PHP' => '8.3', 'DISALLOW_FILE_EDIT' => 'Zapnuto'], 'plugins' => [], 'checks' => ['file_edit_disabled' => true]];
expect(Evidence::forItem('backup', $audit) === '', 'No inferred hosting backup');
expect(Evidence::forItem('wordpress', $audit) === 'WordPress: 6.0', 'Version is prefilled');
expect(Evidence::forItem('wordpress', null) === '', 'Missing snapshot is not evidence');
foreach (Catalog::items() as $id => $definition) { expect(Evidence::instruction($id) !== '', 'Every item has an instruction'); }
$input = [];
foreach (Catalog::items() as $id => $definition) { $input[$id] = ['state' => 'unknown', 'note' => '']; }
$input['file_edit']['state'] = 'done';
$rows = Catalog::normalize($input, [], $audit);
expect($rows['file_edit']['note'] === '', 'No need to copy machine evidence');
foreach ([null, [], ['checks' => ['file_edit_disabled' => false]], ['checks' => ['file_edit_disabled' => 'true']]] as $invalid) {
    try { Catalog::normalize($input, [], $invalid); throw new RuntimeException('Unproved completion accepted'); } catch (DomainException $e) {}
}
$input['backup']['state'] = 'done';
try { Catalog::normalize($input, [], $audit); throw new RuntimeException('Backup auto-completed'); } catch (DomainException $e) {}
$input['backup'] = ['state' => 'done', 'note' => 'Ruční důkaz, zachovat'];
$rows = Catalog::normalize($input, [], $audit);
$record = ['schema' => 1, 'revision' => 'test', 'items' => $rows, 'audit' => $audit];
$GLOBALS['options'][Store::OPTION] = $record;
ob_start(); (new Module(new Store()))->render(); $html = ob_get_clean();
expect(str_contains($html, 'Zjištěno automaticky') && str_contains($html, 'Co ověřit:'), 'Guided view');
expect(str_contains($html, 'Ruční důkaz, zachovat'), 'Manual notes preserved');
expect($GLOBALS['options'][Store::OPTION] === $record, 'Rendering never writes');
$text = Formatter::text($record, 'test');
expect(str_contains($text, 'WordPress: 6.0') && str_contains($text, $audit['at']), 'Report carries dated evidence');
$record['audit']['facts']['WordPress'] = '<script>alert(1)</script>';
$GLOBALS['options'][Store::OPTION] = $record;
ob_start(); (new Module(new Store()))->render(); $html = ob_get_clean();
expect(!str_contains($html, '<script>'), 'Automatic evidence is escaped');
echo "Assisted checklist tests passed.\n";
