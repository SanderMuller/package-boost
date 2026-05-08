<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use SanderMuller\PackageBoost\Console\LeanCommand;

use function Orchestra\Testbench\package_path;

/**
 * Real `.gitattributes` at the package root is committed, so back it up,
 * exercise the command on a copy, then restore it. Avoids polluting the
 * repo while still hitting the actual filesystem path the command writes
 * to (no need to thread a virtual root through the command's surface).
 *
 * Pest test isolation makes accessing `$this->backup` ergonomic-ish, but
 * the `TestCall` shape phpstan resolves to doesn't expose dynamic
 * properties, so a static at file scope is the cleanest typed option.
 */
$leanBackup = null;

beforeEach(function () use (&$leanBackup): void {
    $leanBackup = File::exists(package_path('.gitattributes'))
        ? (string) file_get_contents(package_path('.gitattributes'))
        : null;
});

afterEach(function () use (&$leanBackup): void {
    if ($leanBackup === null) {
        File::delete(package_path('.gitattributes'));

        return;
    }

    File::put(package_path('.gitattributes'), $leanBackup);
});

it('creates .gitattributes with the managed block when none exists', function (): void {
    File::delete(package_path('.gitattributes'));

    $exit = Artisan::call('package-boost:lean');

    expect($exit)->toBe(0);

    $contents = (string) file_get_contents(package_path('.gitattributes'));

    expect($contents)
        ->toContain(LeanCommand::MARKER_START)
        ->toContain(LeanCommand::MARKER_END)
        ->toContain('AGENTS.md export-ignore')
        ->toContain('.ai/ export-ignore');
});

it('is idempotent on a second run', function (): void {
    File::delete(package_path('.gitattributes'));

    Artisan::call('package-boost:lean');
    $first = (string) file_get_contents(package_path('.gitattributes'));

    $exit = Artisan::call('package-boost:lean');
    $second = (string) file_get_contents(package_path('.gitattributes'));

    expect($exit)->toBe(0)
        ->and($second)->toBe($first);
});

it('preserves user-authored entries outside the managed block', function (): void {
    File::put(package_path('.gitattributes'), "* text=auto eol=lf\n\nspecs/ export-ignore\nphpunit.xml.dist export-ignore\n");

    Artisan::call('package-boost:lean');
    $contents = (string) file_get_contents(package_path('.gitattributes'));

    expect($contents)
        ->toContain('specs/ export-ignore')
        ->toContain('phpunit.xml.dist export-ignore')
        ->toContain(LeanCommand::MARKER_START);
});

it('--check exits non-zero when the file is out of date', function (): void {
    File::put(package_path('.gitattributes'), "* text=auto eol=lf\n");

    $exit = Artisan::call('package-boost:lean', ['--check' => true]);

    expect($exit)->toBe(1);
    // --check did not mutate
    expect((string) file_get_contents(package_path('.gitattributes')))->toBe("* text=auto eol=lf\n");
});

it('--check exits zero when the managed block is current', function (): void {
    File::delete(package_path('.gitattributes'));
    Artisan::call('package-boost:lean');

    $exit = Artisan::call('package-boost:lean', ['--check' => true]);

    expect($exit)->toBe(0);
});

it('renderUpdated rewrites only the managed block on subsequent runs', function (): void {
    $original = "# user header\n" . LeanCommand::MARKER_START . "\nstale-entry\n" . LeanCommand::MARKER_END . "\n# user footer\n";

    $rewritten = LeanCommand::renderUpdated($original);

    expect($rewritten)->toContain('# user header');
    expect($rewritten)->toContain('# user footer');
    expect($rewritten)->toContain('AGENTS.md export-ignore');
    expect(str_contains($rewritten, 'stale-entry'))->toBeFalse();
});
