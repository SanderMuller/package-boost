<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use SanderMuller\PackageBoost\Console\Internal\PackageRoot;
use SanderMuller\PackageBoost\Console\Internal\SyncReporter;

/**
 * Ensures the host's `.gitattributes` carries the AI-era `export-ignore`
 * entries that `composer archive` / Packagist `--prefer-dist` tarballs
 * would otherwise ship to consumers. The managed entries live inside a
 * `# >>> package-boost (managed) >>>` / `# <<< package-boost (managed) <<<`
 * marker block so re-runs are idempotent and user-authored entries
 * outside the block are never touched.
 *
 * Pairs with the shipped `lean-dist` skill (which on-ramps users to
 * `stolt/lean-package-validator` for validation); this command handles
 * the write side.
 */
final class LeanCommand extends Command
{
    protected $signature = 'package-boost:lean
        {--check : Report whether the managed block is up to date; exits non-zero if a write would be needed}';

    protected $description = 'Ensure .gitattributes carries AI-era export-ignore entries';

    public const MARKER_START = '# >>> package-boost (managed) >>>';

    public const MARKER_END = '# <<< package-boost (managed) <<<';

    /** @var array<int, string>  paths kept in sync inside the managed block */
    public const MANAGED_ENTRIES = [
        '.agents/ export-ignore',
        '.ai/ export-ignore',
        '.claude/ export-ignore',
        '.cursor/ export-ignore',
        '.cursorrules export-ignore',
        '.github/ export-ignore',
        '.junie/ export-ignore',
        '.kiro/ export-ignore',
        '.windsurfrules export-ignore',
        'AGENTS.md export-ignore',
        'CLAUDE.md export-ignore',
        'GEMINI.md export-ignore',
    ];

    public function handle(): int
    {
        $root = $this->resolvePackageRoot();
        $path = $root . DIRECTORY_SEPARATOR . '.gitattributes';
        $current = is_file($path) ? (string) file_get_contents($path) : '';
        $desired = self::renderUpdated($current);
        $check = $this->option('check') === true;

        if ($desired === $current) {
            $this->components->info('.gitattributes already carries the managed package-boost block.');

            return self::SUCCESS;
        }

        if ($check) {
            $this->components->error(
                '.gitattributes is missing or out of date. Run `package-boost:lean` (without --check) to update.'
            );

            return self::FAILURE;
        }

        File::put($path, $desired);

        $this->components->info(
            is_file($path) && $current === ''
                ? '.gitattributes created with the package-boost managed block.'
                : '.gitattributes managed block updated.'
        );

        return self::SUCCESS;
    }

    /**
     * Pure transform exposed for tests. Either inserts a fresh managed
     * block at the bottom of the file, or rewrites the existing block in
     * place. User-authored entries outside the markers are preserved
     * verbatim.
     */
    public static function renderUpdated(string $current): string
    {
        $block = self::MARKER_START . "\n" . implode("\n", self::MANAGED_ENTRIES) . "\n" . self::MARKER_END;

        if ($current === '') {
            return "* text=auto eol=lf\n\n" . $block . "\n";
        }

        $pattern = '/' . preg_quote(self::MARKER_START, '/') . '.*?' . preg_quote(self::MARKER_END, '/') . '/s';

        if (preg_match($pattern, $current) === 1) {
            return (string) preg_replace($pattern, SyncReporter::escapeReplacement($block), $current, 1);
        }

        return rtrim($current, "\n") . "\n\n" . $block . "\n";
    }

    private function resolvePackageRoot(): string
    {
        return PackageRoot::resolve();
    }
}
