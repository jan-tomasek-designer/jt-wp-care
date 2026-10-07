<?php
declare(strict_types=1);

namespace JT\WPCare\Contracts;

/** Modules register hooks here; constructors must not perform audits or writes. */
interface ModuleInterface
{
    public function id(): string;

    public function register(): void;
}
