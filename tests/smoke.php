<?php
declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use JT\WPCare\Contracts\ModuleInterface;
use JT\WPCare\Core\Kernel;

function verify(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
$enabled = new class implements ModuleInterface {
    public int $calls = 0;
    public function id(): string { return 'enabled'; }
    public function register(): void { ++$this->calls; }
};
$disabled = new class implements ModuleInterface {
    public int $calls = 0;
    public function id(): string { return 'disabled'; }
    public function register(): void { ++$this->calls; }
};
$kernel = new Kernel([$enabled, $disabled], ['disabled']);
$kernel->boot();
$kernel->boot();
verify($enabled->calls === 1, 'Enabled module must register exactly once.');
verify($disabled->calls === 0, 'Disabled module must never register.');
$rejected = false;
try {
    new Kernel([$enabled, $enabled]);
} catch (InvalidArgumentException $exception) {
    $rejected = true;
}
verify($rejected, 'Duplicate module IDs must be rejected.');
verify(class_exists(\JT\WPCare\Modules\Dashboard\Module::class), 'Nested PSR-4 loading failed.');
verify(!class_exists('OtherVendor\\Missing'), 'Foreign namespaces must be ignored.');
echo "Smoke tests passed.\n";
