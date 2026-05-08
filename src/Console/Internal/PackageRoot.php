<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console\Internal;

/**
 * @internal Resolves the package root the consuming console command
 * should operate on. Prefers Testbench's `package_path()` (the canonical
 * answer when the host has Testbench installed); falls back to the
 * process CWD for hosts that haven't pulled Testbench in yet.
 */
final class PackageRoot
{
    public static function resolve(): string
    {
        if (function_exists('Orchestra\Testbench\package_path')) {
            return \Orchestra\Testbench\package_path();
        }

        return (string) getcwd();
    }
}
