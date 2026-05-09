<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Tests;

use Illuminate\Support\Facades\File;
use SanderMuller\PackageBoost\Console\Internal\SkillFrontmatter;

use function Orchestra\Testbench\package_path;

beforeEach(function (): void {
    File::deleteDirectory(package_path('tests/tmp-skills'));
    File::ensureDirectoryExists(package_path('tests/tmp-skills'));
});

afterEach(function (): void {
    File::deleteDirectory(package_path('tests/tmp-skills'));
});

function seedSkill(string $name, string $body): string
{
    $dir = package_path('tests/tmp-skills/' . $name);
    File::ensureDirectoryExists($dir);
    File::put($dir . '/SKILL.md', $body);

    return $dir;
}

it('passes a SKILL.md with required keys', function (): void {
    $dir = seedSkill('valid', "---\nname: valid\ndescription: Sample.\n---\n\n# body\n");

    expect(SkillFrontmatter::lint(['valid' => $dir]))->toBe([]);
});

it('flags a SKILL.md with no frontmatter at all', function (): void {
    $dir = seedSkill('bare', "# Just a body\n");

    $issues = SkillFrontmatter::lint(['bare' => $dir]);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]['name'])->toBe('bare')
        ->and($issues[0]['problems'])->toContain('frontmatter block missing (expected YAML between `---` fences at the top of the file)');
});

it('flags missing required keys', function (): void {
    $dir = seedSkill('no-desc', "---\nname: no-desc\n---\n");

    $issues = SkillFrontmatter::lint(['no-desc' => $dir]);

    expect($issues[0]['problems'])->toContain('missing required key: description');
});

it('flags name/directory mismatch', function (): void {
    $dir = seedSkill('correct-dir', "---\nname: wrong-name\ndescription: Mismatch.\n---\n");

    $issues = SkillFrontmatter::lint(['correct-dir' => $dir]);

    expect($issues[0]['problems'])->toContain("name mismatch: frontmatter says 'wrong-name', directory is 'correct-dir'");
});

it('flags a missing SKILL.md file', function (): void {
    $dir = package_path('tests/tmp-skills/empty-dir');
    File::ensureDirectoryExists($dir);

    $issues = SkillFrontmatter::lint(['empty-dir' => $dir]);

    expect($issues[0]['problems'])->toBe(['SKILL.md missing']);
});

it('strips quoted scalar values', function (): void {
    $dir = seedSkill('quoted', "---\nname: \"quoted\"\ndescription: 'Quoted desc.'\n---\n");

    expect(SkillFrontmatter::lint(['quoted' => $dir]))->toBe([]);
});

it('filterBlocking keeps host .ai/skills/ issues', function (): void {
    $root = '/abs/host';
    $issue = ['name' => 'broken', 'path' => '/abs/host/.ai/skills/broken/SKILL.md', 'problems' => ['x']];

    expect(SkillFrontmatter::filterBlocking([$issue], $root))->toBe([$issue]);
});

it('filterBlocking drops shipped resources/boost/skills/ issues', function (): void {
    // Host-only rule: package-shipped frontmatter is non-blocking even
    // inside the dogfood repo. Dogfooding our own shipped skills is
    // enforced separately, not via the doctor exit code.
    $root = '/abs/host';
    $issue = ['name' => 'shipped', 'path' => '/abs/host/resources/boost/skills/shipped/SKILL.md', 'problems' => ['x']];

    expect(SkillFrontmatter::filterBlocking([$issue], $root))->toBe([]);
});

it('filterBlocking drops third-party vendor issues', function (): void {
    $root = '/abs/host';
    $issue = ['name' => 'vendor', 'path' => '/abs/host/vendor/foo/bar/resources/boost/skills/vendor/SKILL.md', 'problems' => ['x']];

    expect(SkillFrontmatter::filterBlocking([$issue], $root))->toBe([]);
});

it("dogfood: this package's own shipped resources/boost/skills/ pass lint", function (): void {
    // Companion gate to filterBlocking's host-only rule. Doctor exit no
    // longer fails on shipped frontmatter (host-only by design — keeps
    // downstream consumers from failing on vendor-owned content), so
    // this assertion is the package's own pre-release safety net for
    // bundled SKILL.md files. Catches a malformed shipped skill before
    // it ships.
    $skills = [];

    foreach ((array) glob(package_path('resources/boost/skills/*'), GLOB_ONLYDIR) as $dir) {
        $skills[basename((string) $dir)] = (string) $dir;
    }

    expect($skills)->not->toBeEmpty('no shipped skills found — glob path stale?');
    expect(SkillFrontmatter::lint($skills))->toBe([]);
});
