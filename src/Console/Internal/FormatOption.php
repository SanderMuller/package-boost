<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console\Internal;

use Illuminate\Console\Command;

/**
 * @internal Read and validate the `--format` option shared by
 * `package-boost:sync` and `package-boost:doctor`. Centralises the
 * `is_string` narrowing, the supported-set, and the error wording —
 * caller still renders the error so `components->error` styling stays
 * intact (the factory backing it is protected on `Command`).
 */
final class FormatOption
{
    public const SUPPORTED = ['text', 'json'];

    public static function read(Command $command): string
    {
        $value = $command->option('format');

        return is_string($value) ? $value : 'text';
    }

    public static function isSupported(string $format): bool
    {
        return in_array($format, self::SUPPORTED, true);
    }

    public static function invalidMessage(string $format): string
    {
        return "Invalid --format value '{$format}'; expected 'text' or 'json'.";
    }
}
