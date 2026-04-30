<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console;

/**
 * @internal Migration helpers for the legacy `.github/copilot-instructions.md`
 * path that earlier package-boost versions wrote. Boost now treats the file
 * as a project-detection signal only and writes Copilot guidelines into
 * `AGENTS.md`; package-boost mirrors that. Detect / prune the leftover.
 */
final class LegacyCopilotInstructions
{
    public const PATH = '.github/copilot-instructions.md';

    public const TAG = '<package-boost-guidelines>';

    /**
     * Returns the file's content if it exists AND contains our wrapping
     * tag. Hand-authored Copilot files (no tag) return null so the
     * caller skips warnings/prune entirely.
     */
    public static function read(string $root): ?string
    {
        $path = self::pathFor($root);

        if (! is_file($path)) {
            return null;
        }

        $contents = (string) file_get_contents($path);

        return str_contains($contents, self::TAG) ? $contents : null;
    }

    /**
     * Returns true when:
     *   1. the file's only content is our tag block (no user content
     *      outside the block, modulo surrounding whitespace), AND
     *   2. the *inside* of that tag block matches the expected current
     *      output verbatim — so we never delete a file the user has
     *      hand-edited inside our block.
     *
     * Callers compose `$expectedBlock` from the same sources sync
     * uses (`SyncSources::guidelines()` wrapped in the tag), so a
     * fresh post-sync prune succeeds while a user-edited block
     * triggers refusal.
     */
    public static function isPrunable(string $contents, string $expectedBlock): bool
    {
        $stripped = trim((string) preg_replace(
            '/<package-boost-guidelines>.*?<\/package-boost-guidelines>/s',
            '',
            $contents,
        ));

        if ($stripped !== '') {
            return false;
        }

        return self::extractBlock($contents) === self::extractBlock($expectedBlock);
    }

    private static function extractBlock(string $haystack): ?string
    {
        if (preg_match('/<package-boost-guidelines>(.*?)<\/package-boost-guidelines>/s', $haystack, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }

    public static function delete(string $root): void
    {
        $path = self::pathFor($root);

        if (is_file($path)) {
            unlink($path);
        }
    }

    public static function pathFor(string $root): string
    {
        return $root . DIRECTORY_SEPARATOR . self::PATH;
    }
}
