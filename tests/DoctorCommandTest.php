<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

use function Orchestra\Testbench\package_path;

const DOCTOR_AGENT_DIRS = [
    '.claude/skills',
    '.cursor/skills',
    '.agents/skills',
    '.github/skills',
    '.junie/skills',
    '.kiro/skills',
];

/**
 * Real `.gitattributes` is committed; back it up around tests that
 * exercise the lean-driven path so `.gitattributes` ends up untouched
 * regardless of pass/fail.
 */
$doctorGitAttributes = null;

beforeEach(function () use (&$doctorGitAttributes): void {
    foreach (DOCTOR_AGENT_DIRS as $dir) {
        File::deleteDirectory(package_path($dir));
    }

    File::delete(package_path('CLAUDE.md'));
    File::delete(package_path('AGENTS.md'));
    File::delete(package_path('GEMINI.md'));
    File::delete(package_path('.mcp.json'));

    File::deleteDirectory(package_path('.ai/skills/bad-frontmatter-skill'));

    $doctorGitAttributes = File::exists(package_path('.gitattributes'))
        ? (string) file_get_contents(package_path('.gitattributes'))
        : null;
});

/**
 * Seed `.github/copilot-instructions.md` with exactly the freshly-synced
 * `<package-boost-guidelines>` block, so `sync --prune` will treat it as
 * prunable. Caller is responsible for sync-ing first; helper is purely
 * the file-shaping step. Caller is also responsible for cleanup.
 */
function seedPrunableLegacyCopilotFile(): void
{
    $claudeMd = (string) file_get_contents(package_path('CLAUDE.md'));

    if (preg_match('/<package-boost-guidelines>.*?<\/package-boost-guidelines>/s', $claudeMd, $match) !== 1) {
        throw new \RuntimeException('Could not extract guidelines block from CLAUDE.md');
    }

    File::ensureDirectoryExists(package_path('.github'));
    File::put(package_path('.github/copilot-instructions.md'), $match[0] . "\n");
}

afterEach(function () use (&$doctorGitAttributes): void {
    foreach (DOCTOR_AGENT_DIRS as $dir) {
        File::deleteDirectory(package_path($dir));
    }

    File::delete(package_path('CLAUDE.md'));
    File::delete(package_path('AGENTS.md'));
    File::delete(package_path('GEMINI.md'));
    File::delete(package_path('.mcp.json'));

    File::deleteDirectory(package_path('.ai/skills/bad-frontmatter-skill'));

    if ($doctorGitAttributes === null) {
        File::delete(package_path('.gitattributes'));
    } else {
        File::put(package_path('.gitattributes'), $doctorGitAttributes);
    }
});

it('exits non-zero when sync drift is present', function (): void {
    $exit = Artisan::call('package-boost:doctor');

    expect($exit)->toBe(1);
});

it('reports unknown agents in config', function (): void {
    config()->set('package-boost.agents', ['claude_code', 'made_up_agent']);

    Artisan::call('package-boost:doctor');
    $output = Artisan::output();

    expect($output)
        ->toContain('Unknown agent name')
        ->toContain('made_up_agent');
});

it('reports SKILL.md frontmatter issues', function (): void {
    File::ensureDirectoryExists(package_path('.ai/skills/bad-frontmatter-skill'));
    File::put(
        package_path('.ai/skills/bad-frontmatter-skill/SKILL.md'),
        "no frontmatter here\n",
    );

    Artisan::call('package-boost:doctor');
    $output = Artisan::output();

    expect($output)
        ->toContain('SKILL.md frontmatter issues')
        ->toContain('bad-frontmatter-skill');
});

it('emits machine-readable JSON when --format=json', function (): void {
    Artisan::call('package-boost:doctor', ['--format' => 'json']);
    $output = Artisan::output();

    $decoded = json_decode($output, true);

    expect($decoded)->toBeArray();
    expect(is_array($decoded) ? $decoded : [])->toMatchArray([
        'schema' => 1,
    ]);
    expect(is_array($decoded) && isset($decoded['drift']) && is_array($decoded['drift']))->toBeTrue();
    expect(is_array($decoded) && isset($decoded['agents']) && is_array($decoded['agents']))->toBeTrue();
});

it('exits zero on a fully synced package', function (): void {
    Artisan::call('package-boost:sync');
    Artisan::call('package-boost:lean');

    $exit = Artisan::call('package-boost:doctor');
    $output = Artisan::output();

    expect($output)->toContain('Drift: skills=0');
    expect($exit)->toBe(0);
});

it('reports `laravel-boost-not-installed` as MCP status, not drift, on framework-agnostic hosts', function (): void {
    app()->bind('package-boost.boost-detector', static fn (): callable => static fn (): bool => false);

    Artisan::call('package-boost:sync');
    Artisan::call('package-boost:lean');

    $exit = Artisan::call('package-boost:doctor', ['--format' => 'json']);
    $output = Artisan::output();
    $decoded = json_decode($output, true);

    $mcp = is_array($decoded) && is_array($decoded['drift'] ?? null) && is_string($decoded['drift']['mcp'] ?? null)
        ? $decoded['drift']['mcp']
        : '';

    expect($mcp)->toBe('laravel-boost-not-installed');
    expect($exit)->toBe(0);
});

it('counts a stale skill dir (host source removed) as drift', function (): void {
    Artisan::call('package-boost:sync', ['--skills' => true]);

    // Plant a stale skill dir under one of the agent skill targets.
    File::ensureDirectoryExists(package_path('.claude/skills/orphaned-skill-dir'));
    File::put(package_path('.claude/skills/orphaned-skill-dir/SKILL.md'), "---\nname: orphaned-skill-dir\ndescription: Stale.\n---\n");

    $exit = Artisan::call('package-boost:doctor');
    $output = Artisan::output();

    expect($exit)->toBe(1);
    expect($output)->not->toContain('Drift: skills=0');
});

it('accepts --fix without erroring out', function (): void {
    Artisan::call('package-boost:sync');
    Artisan::call('package-boost:lean');

    // On a clean repo --fix is a noop and exits 0.
    $exit = Artisan::call('package-boost:doctor', ['--fix' => true]);

    expect($exit)->toBe(0);
});

it('accepts --fix alongside --format=json', function (): void {
    Artisan::call('package-boost:sync');
    Artisan::call('package-boost:lean');

    $exit = Artisan::call('package-boost:doctor', ['--fix' => true, '--format' => 'json']);
    $output = Artisan::output();

    expect($exit)->toBe(0);
    expect(json_decode($output, true))->toBeArray();
});

it('--fix resolves sync drift in a single invocation', function (): void {
    // Pre-state: no generated files at all, so doctor reports drift.
    expect(Artisan::call('package-boost:doctor'))->toBe(1);

    $exit = Artisan::call('package-boost:doctor', ['--fix' => true]);

    expect($exit)->toBe(0)
        ->and(File::exists(package_path('CLAUDE.md')))->toBeTrue()
        ->and(File::exists(package_path('AGENTS.md')))->toBeTrue();
});

it('--fix prunes orphans for deselected agents', function (): void {
    Artisan::call('package-boost:sync');
    Artisan::call('package-boost:lean');
    expect(is_dir(package_path('.cursor/skills')))->toBeTrue();

    config()->set('package-boost.agents', ['claude_code']);

    $exit = Artisan::call('package-boost:doctor', ['--fix' => true]);

    expect($exit)->toBe(0)
        ->and(is_dir(package_path('.cursor/skills')))->toBeFalse()
        ->and(is_dir(package_path('.claude/skills')))->toBeTrue();
});

it('--fix removes a prunable legacy .github/copilot-instructions.md', function (): void {
    Artisan::call('package-boost:sync');
    Artisan::call('package-boost:lean');
    seedPrunableLegacyCopilotFile();

    try {
        $exit = Artisan::call('package-boost:doctor', ['--fix' => true]);

        expect($exit)->toBe(0)
            ->and(File::exists(package_path('.github/copilot-instructions.md')))->toBeFalse();
    } finally {
        File::delete(package_path('.github/copilot-instructions.md'));
    }
});

it('--fix exits non-zero when sync refuses to prune a legacy Copilot file with user content', function (): void {
    Artisan::call('package-boost:sync');
    Artisan::call('package-boost:lean');

    File::ensureDirectoryExists(package_path('.github'));
    File::put(
        package_path('.github/copilot-instructions.md'),
        "# User notes\nKeep this around.\n\n<package-boost-guidelines>\nours\n</package-boost-guidelines>\n",
    );

    try {
        $exit = Artisan::call('package-boost:doctor', ['--fix' => true]);

        expect($exit)->toBe(1)
            ->and(File::exists(package_path('.github/copilot-instructions.md')))->toBeTrue()
            ->and((string) file_get_contents(package_path('.github/copilot-instructions.md')))
            ->toContain('Keep this around.');
    } finally {
        File::delete(package_path('.github/copilot-instructions.md'));
    }
});

it('--fix recreates a missing .gitattributes managed block', function (): void {
    Artisan::call('package-boost:sync');
    File::delete(package_path('.gitattributes'));

    $exit = Artisan::call('package-boost:doctor', ['--fix' => true]);

    expect($exit)->toBe(0)
        ->and(File::exists(package_path('.gitattributes')))->toBeTrue()
        ->and((string) file_get_contents(package_path('.gitattributes')))
        ->toContain('package-boost (managed)');
});

it('--fix --format=json emits a single parseable JSON document', function (): void {
    // Drift is present (no sync ran), so applyFixes will write through
    // sync + lean. Buffer suppression must keep stdout JSON-only.
    $exit = Artisan::call('package-boost:doctor', ['--fix' => true, '--format' => 'json']);
    $output = Artisan::output();

    expect($exit)->toBe(0);

    $decoded = json_decode($output, true);
    expect($decoded)->toBeArray();
    expect(is_array($decoded) ? $decoded : [])->toMatchArray(['schema' => 1]);
});

it('--fix --format=json includes per-category attempted/resolved outcomes', function (): void {
    // Pre-state: drift present (no generated files), .gitattributes also
    // not yet up-to-date, no orphans, no legacy Copilot file.
    File::delete(package_path('.gitattributes'));

    $exit = Artisan::call('package-boost:doctor', ['--fix' => true, '--format' => 'json']);
    $output = Artisan::output();

    expect($exit)->toBe(0);

    $decoded = json_decode($output, true);
    expect($decoded)->toBeArray();

    $payload = is_array($decoded) ? $decoded : [];
    expect($payload)->toHaveKey('fix');

    $fix = is_array($payload['fix'] ?? null) ? $payload['fix'] : [];

    expect($fix)->toHaveKeys([
        'skills', 'guidelines', 'mcp', 'orphans', 'legacy_copilot', 'gitattributes',
    ]);

    // Skills/guidelines: attempted > 0 (drift was present), all resolved.
    $skills = is_array($fix['skills'] ?? null) ? $fix['skills'] : [];
    expect($skills)->toMatchArray(['resolved' => $skills['attempted'] ?? null]);
    expect($skills['attempted'] ?? 0)->toBeGreaterThan(0);

    // .gitattributes: attempted (was missing), resolved.
    expect($fix['gitattributes'])->toBe(['attempted' => true, 'resolved' => true]);

    // No legacy Copilot file pre-fix → noop, attempted=false, resolved=false.
    expect($fix['legacy_copilot'])->toBe(['attempted' => false, 'resolved' => false]);

    // No orphans pre-fix → noop.
    expect($fix['orphans'])->toBe(['attempted' => 0, 'resolved' => 0]);
});

it('omits the fix key in JSON when --fix was not passed', function (): void {
    Artisan::call('package-boost:sync');
    Artisan::call('package-boost:lean');

    Artisan::call('package-boost:doctor', ['--format' => 'json']);
    $output = Artisan::output();

    $decoded = json_decode($output, true);
    expect($decoded)->toBeArray();
    expect(is_array($decoded) ? $decoded : [])->not->toHaveKey('fix');
});

it('--fix legacy_copilot.resolved is false when sync refuses the prune', function (): void {
    Artisan::call('package-boost:sync');
    Artisan::call('package-boost:lean');

    File::ensureDirectoryExists(package_path('.github'));
    File::put(
        package_path('.github/copilot-instructions.md'),
        "# User notes\nKeep this around.\n\n<package-boost-guidelines>\nours\n</package-boost-guidelines>\n",
    );

    try {
        $exit = Artisan::call('package-boost:doctor', ['--fix' => true, '--format' => 'json']);
        $output = Artisan::output();

        expect($exit)->toBe(1);

        $decoded = json_decode($output, true);
        $fix = is_array($decoded) && is_array($decoded['fix'] ?? null) ? $decoded['fix'] : [];

        // Doctor saw the file (attempted=true) but sync refused
        // (resolved=false) — distinguishable from a noop.
        expect($fix['legacy_copilot'])->toBe(['attempted' => true, 'resolved' => false]);
    } finally {
        File::delete(package_path('.github/copilot-instructions.md'));
    }
});

it('--fix resolves drift + orphans + legacy Copilot + .gitattributes in one invocation', function (): void {
    // Seed: full sync first to get baseline files, then deselect an
    // agent (creates orphans), seed a prunable legacy Copilot file,
    // and delete .gitattributes.
    Artisan::call('package-boost:sync');
    Artisan::call('package-boost:lean');
    seedPrunableLegacyCopilotFile();

    File::delete(package_path('.gitattributes'));
    config()->set('package-boost.agents', ['claude_code']);

    // Force drift on the now-deselected agents by removing their files.
    File::delete(package_path('AGENTS.md'));
    File::delete(package_path('GEMINI.md'));

    try {
        $exit = Artisan::call('package-boost:doctor', ['--fix' => true, '--format' => 'json']);
        $output = Artisan::output();

        expect($exit)->toBe(0);

        $decoded = json_decode($output, true);
        $payload = is_array($decoded) ? $decoded : [];
        $fix = is_array($payload['fix'] ?? null) ? $payload['fix'] : [];

        expect(File::exists(package_path('.github/copilot-instructions.md')))->toBeFalse()
            ->and(File::exists(package_path('.gitattributes')))->toBeTrue()
            ->and(is_dir(package_path('.cursor/skills')))->toBeFalse();

        expect($fix['legacy_copilot'])->toBe(['attempted' => true, 'resolved' => true]);
        expect($fix['gitattributes'])->toBe(['attempted' => true, 'resolved' => true]);

        $orphans = is_array($fix['orphans'] ?? null) ? $fix['orphans'] : [];
        expect($orphans['attempted'] ?? 0)->toBeGreaterThan(0);
        expect($orphans['resolved'] ?? null)->toBe($orphans['attempted'] ?? null);
    } finally {
        File::delete(package_path('.github/copilot-instructions.md'));
    }
});

it('--fix resolves drift but exits non-zero when host frontmatter issues persist', function (): void {
    File::ensureDirectoryExists(package_path('.ai/skills/bad-frontmatter-skill'));
    File::put(
        package_path('.ai/skills/bad-frontmatter-skill/SKILL.md'),
        "no frontmatter here\n",
    );

    $exit = Artisan::call('package-boost:doctor', ['--fix' => true, '--format' => 'json']);
    $output = Artisan::output();

    // Drift gets resolved, but the host SKILL.md has malformed
    // frontmatter — host issues block exit code by design.
    expect($exit)->toBe(1);

    $decoded = json_decode($output, true);
    $payload = is_array($decoded) ? $decoded : [];

    // The frontmatter issue persists in the post-fix report.
    expect($payload['frontmatter_issues'] ?? [])->not->toBe([]);

    // Skills drift attempted > 0 but the malformed skill itself can
    // resolve at least into a target dir entry (sync writes whatever
    // it lints), so we only assert attempted > 0 rather than full
    // resolved-equality.
    $fix = is_array($payload['fix'] ?? null) ? $payload['fix'] : [];
    $skills = is_array($fix['skills'] ?? null) ? $fix['skills'] : [];
    expect($skills['attempted'] ?? 0)->toBeGreaterThan(0);
});

it('--fix exits non-zero and reports refusal when the legacy Copilot block has been edited', function (): void {
    Artisan::call('package-boost:sync');
    Artisan::call('package-boost:lean');

    // File contains our tag block (so it's detected) but the inside
    // is hand-edited / out of sync with `.ai/`. Sync's prune will
    // refuse — `LegacyCopilotInstructions::isPrunable` compares the
    // block contents against the freshly-synced expected block.
    File::ensureDirectoryExists(package_path('.github'));
    File::put(
        package_path('.github/copilot-instructions.md'),
        "<package-boost-guidelines>\nhand-edited stale content\n</package-boost-guidelines>\n",
    );

    try {
        $exit = Artisan::call('package-boost:doctor', ['--fix' => true, '--format' => 'json']);
        $output = Artisan::output();

        expect($exit)->toBe(1)
            ->and(File::exists(package_path('.github/copilot-instructions.md')))->toBeTrue()
            ->and((string) file_get_contents(package_path('.github/copilot-instructions.md')))
            ->toContain('hand-edited stale content');

        $decoded = json_decode($output, true);
        $fix = is_array($decoded) && is_array($decoded['fix'] ?? null) ? $decoded['fix'] : [];
        expect($fix['legacy_copilot'])->toBe(['attempted' => true, 'resolved' => false]);
    } finally {
        File::delete(package_path('.github/copilot-instructions.md'));
    }
});

it('--fix on a Boost-less host emits mcp.attempted=mcp.resolved=laravel-boost-not-installed', function (): void {
    app()->bind('package-boost.boost-detector', static fn (): callable => static fn (): bool => false);

    $exit = Artisan::call('package-boost:doctor', ['--fix' => true, '--format' => 'json']);
    $output = Artisan::output();

    expect($exit)->toBe(0);

    $decoded = json_decode($output, true);
    $fix = is_array($decoded) && is_array($decoded['fix'] ?? null) ? $decoded['fix'] : [];
    $mcp = is_array($fix['mcp'] ?? null) ? $fix['mcp'] : [];

    expect($mcp['attempted'] ?? null)->toBe('laravel-boost-not-installed');
    expect($mcp['resolved'] ?? null)->toBe('laravel-boost-not-installed');
});

it('--fix when claude_code is deselected emits mcp.attempted=mcp.resolved=claude-not-selected', function (): void {
    config()->set('package-boost.agents', ['cursor']);

    $exit = Artisan::call('package-boost:doctor', ['--fix' => true, '--format' => 'json']);
    $output = Artisan::output();

    expect($exit)->toBe(0);

    $decoded = json_decode($output, true);
    $fix = is_array($decoded) && is_array($decoded['fix'] ?? null) ? $decoded['fix'] : [];
    $mcp = is_array($fix['mcp'] ?? null) ? $fix['mcp'] : [];

    expect($mcp['attempted'] ?? null)->toBe('claude-not-selected');
    expect($mcp['resolved'] ?? null)->toBe('claude-not-selected');
});

it('does not flag a hand-authored .github/copilot-instructions.md without our tag', function (): void {
    Artisan::call('package-boost:sync');
    Artisan::call('package-boost:lean');

    File::ensureDirectoryExists(package_path('.github'));
    File::put(
        package_path('.github/copilot-instructions.md'),
        "# My hand-authored Copilot rules\n\n- prefer descriptive names\n",
    );

    try {
        $exit = Artisan::call('package-boost:doctor', ['--format' => 'json']);
        $output = Artisan::output();

        // Hand-authored Copilot file has no `<package-boost-guidelines>` tag —
        // doctor must not flag it, otherwise `--fix` would loop forever
        // (sync's prune only acts on tagged files).
        expect($exit)->toBe(0);

        $decoded = json_decode($output, true);
        $payload = is_array($decoded) ? $decoded : [];
        expect($payload['legacy_copilot_instructions'] ?? null)->toBeFalse();
    } finally {
        File::delete(package_path('.github/copilot-instructions.md'));
    }
});

it('--fix is idempotent — second run is a noop with attempted=0/false/unchanged', function (): void {
    // First run resolves all drift.
    Artisan::call('package-boost:doctor', ['--fix' => true]);

    // Second run: nothing to do.
    $exit = Artisan::call('package-boost:doctor', ['--fix' => true, '--format' => 'json']);
    $output = Artisan::output();

    expect($exit)->toBe(0);

    $decoded = json_decode($output, true);
    $fix = is_array($decoded) && is_array($decoded['fix'] ?? null) ? $decoded['fix'] : [];

    expect($fix['skills'])->toBe(['attempted' => 0, 'resolved' => 0]);
    expect($fix['guidelines'])->toBe(['attempted' => 0, 'resolved' => 0]);
    expect($fix['orphans'])->toBe(['attempted' => 0, 'resolved' => 0]);
    expect($fix['legacy_copilot'])->toBe(['attempted' => false, 'resolved' => false]);
    expect($fix['gitattributes'])->toBe(['attempted' => false, 'resolved' => false]);

    $mcp = is_array($fix['mcp'] ?? null) ? $fix['mcp'] : [];
    expect($mcp['attempted'] ?? null)->toBe('unchanged');
    expect($mcp['resolved'] ?? null)->toBe('unchanged');
});
