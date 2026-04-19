<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Tests;

use Illuminate\Support\Facades\File;
use SanderMuller\PackageBoost\Console\SyncReporter;

use function Orchestra\Testbench\package_path;

beforeEach(function (): void {
    File::deleteDirectory(package_path('tests/tmp'));
    File::ensureDirectoryExists(package_path('tests/tmp'));
});

afterEach(function (): void {
    File::deleteDirectory(package_path('tests/tmp'));
});

/**
 * @param  array<string, string>  $files  relative path => content
 */
function seedTree(string $dir, array $files): void
{
    File::ensureDirectoryExists($dir);

    foreach ($files as $rel => $content) {
        File::ensureDirectoryExists(dirname($dir . DIRECTORY_SEPARATOR . $rel));
        File::put($dir . DIRECTORY_SEPARATOR . $rel, $content);
    }
}

it('hashTree hashes every non-dotfile under the directory', function (): void {
    $dir = package_path('tests/tmp/tree');

    seedTree($dir, [
        'SKILL.md' => 'alpha',
        'rules/one.md' => 'uno',
        '.DS_Store' => 'noise',
        '.editorconfig' => 'noise',
    ]);

    $hashes = SyncReporter::hashTree($dir);

    expect(array_keys($hashes))->toBe(['SKILL.md', 'rules/one.md'])
        ->and($hashes['SKILL.md'])->toBeString()
        ->and($hashes['SKILL.md'])->not->toBe($hashes['rules/one.md']);
});

it('hashTree returns [] for a missing directory', function (): void {
    expect(SyncReporter::hashTree(package_path('tests/tmp/missing')))->toBe([]);
});

it('hashTree skips dotted subdirectories as well as dotfiles', function (): void {
    $dir = package_path('tests/tmp/dotted');

    seedTree($dir, [
        'SKILL.md' => 'alpha',
        '.git/HEAD' => 'ref: refs/heads/main',
        '.cache/payload' => 'noise',
    ]);

    expect(array_keys(SyncReporter::hashTree($dir)))->toBe(['SKILL.md']);
});

it('planSkillAction reports content drift on copied (non-symlink) dests', function (): void {
    $source = package_path('tests/tmp/source');
    $dest = package_path('tests/tmp/dest');

    seedTree($source, ['SKILL.md' => 'new content']);
    seedTree($dest, ['SKILL.md' => 'stale content']);

    [$action, $hint] = SyncReporter::planSkillAction($source, $dest);

    expect($action)->toBe('updated')
        ->and($hint)->toStartWith('content: ')
        ->and($hint)->toContain('SKILL.md differs');
});

it('planSkillAction reports unchanged when copied dests match the source tree', function (): void {
    $source = package_path('tests/tmp/source');
    $dest = package_path('tests/tmp/dest');

    seedTree($source, ['SKILL.md' => 'alpha', 'rules/a.md' => 'a']);
    seedTree($dest, ['SKILL.md' => 'alpha', 'rules/a.md' => 'a']);

    [$action, $hint] = SyncReporter::planSkillAction($source, $dest);

    expect($action)->toBe('unchanged')
        ->and($hint)->toBe('');
});

it('renderContentHint names files when the diff fits in 3', function (): void {
    $source = ['SKILL.md' => 'a', 'new.md' => 'b'];
    $dest = ['SKILL.md' => 'different', 'stale.md' => 'c'];

    expect(SyncReporter::renderContentHint($source, $dest))
        ->toBe('content: SKILL.md differs, new.md added, stale.md removed');
});

it('renderContentHint handles pure-remove (dest has files, source is empty)', function (): void {
    expect(SyncReporter::renderContentHint([], ['stale.md' => 'x']))
        ->toBe('content: stale.md removed');
});

it('renderContentHint handles pure-add (source has files, dest is empty)', function (): void {
    expect(SyncReporter::renderContentHint(['new.md' => 'x'], []))
        ->toBe('content: new.md added');
});

it('renderContentHint collapses to counts above 3 files', function (): void {
    $source = [
        'a.md' => 'a', 'b.md' => 'b', 'c.md' => 'c',
        'd.md' => 'd', 'e.md' => 'e',
    ];
    $dest = [
        'a.md' => 'different', 'b.md' => 'different',
        'c.md' => 'different', 'd.md' => 'different',
        'stale.md' => 'x',
    ];

    expect(SyncReporter::renderContentHint($source, $dest))
        ->toBe('content: 4 differ, 1 added, 1 removed');
});
