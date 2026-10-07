<?php
declare(strict_types=1);

namespace JT\WPCare\Core;

use InvalidArgumentException;
use JT\WPCare\Contracts\ModuleInterface;

final class Kernel
{
    /** @var ModuleInterface[] */
    private array $modules;
    private array $disabled;
    private bool $booted = false;

    /** @param ModuleInterface[] $modules @param string[] $disabled */
    public function __construct(array $modules, array $disabled = [])
    {
        $seen = [];
        foreach ($modules as $module) {
            if (!$module instanceof ModuleInterface || !preg_match('/^[a-z][a-z0-9_-]*$/D', $module->id())) {
                throw new InvalidArgumentException('Invalid module.');
            }
            if (isset($seen[$module->id()])) {
                throw new InvalidArgumentException('Duplicate module ID.');
            }
            $seen[$module->id()] = true;
        }
        $this->modules = $modules;
        $this->disabled = $disabled;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;
        foreach ($this->modules as $module) {
            if (!in_array($module->id(), $this->disabled, true)) {
                $module->register();
            }
        }
    }
}
