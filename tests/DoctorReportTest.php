<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Tests;

use SanderMuller\PackageBoost\Console\Internal\DoctorReport;

/**
 * Unit tests for `DoctorReport`. Two responsibilities split out from
 * the Artisan-driven feature suite: (1) `hasIssues()` only flips on
 * **host** frontmatter issues, mirroring `SyncCommand::hasHostFrontmatterIssues`;
 * (2) the optional `fix` field round-trips through `toArray()` only
 * when populated.
 */

/**
 * @param  array<int, array{name: string, path: string, problems: array<int, string>}>  $frontmatterIssues
 * @param  array<int, array{name: string, path: string, problems: array<int, string>}>  $hostFrontmatterIssues
 * @param  ?array{
 *     skills: array{attempted: int, resolved: int},
 *     guidelines: array{attempted: int, resolved: int},
 *     mcp: array{attempted: string, resolved: string},
 *     orphans: array{attempted: int, resolved: int},
 *     legacy_copilot: array{attempted: bool, resolved: bool},
 *     gitattributes: array{attempted: bool, resolved: bool},
 * }  $fix
 */
function makeReport(
    array $frontmatterIssues = [],
    array $hostFrontmatterIssues = [],
    ?array $fix = null,
): DoctorReport {
    return new DoctorReport(
        configuredAgents: null,
        effectiveAgents: [],
        unknownAgents: [],
        drift: ['skills' => 0, 'guidelines' => 0, 'mcp' => DoctorReport::MCP_STATUS_UNCHANGED],
        frontmatterIssues: $frontmatterIssues,
        hostFrontmatterIssues: $hostFrontmatterIssues,
        orphans: [],
        skillCollisions: [],
        boostInstalled: true,
        legacyCopilotInstructions: false,
        gitAttributes: ['exists' => true, 'managed_block_current' => true],
        fix: $fix,
    );
}

it('hasIssues stays false when only vendor/shipped frontmatter issues exist', function (): void {
    $report = makeReport(
        frontmatterIssues: [[
            'name' => 'shipped-skill',
            'path' => '/abs/vendor/foo/bar/resources/boost/skills/shipped-skill/SKILL.md',
            'problems' => ['frontmatter block missing'],
        ]],
        hostFrontmatterIssues: [],
    );

    expect($report->hasIssues())->toBeFalse();
});

it('hasIssues is true when host frontmatter issues are present', function (): void {
    $hostIssue = [
        'name' => 'host-skill',
        'path' => '/abs/.ai/skills/host-skill/SKILL.md',
        'problems' => ['frontmatter block missing'],
    ];

    $report = makeReport(
        frontmatterIssues: [$hostIssue],
        hostFrontmatterIssues: [$hostIssue],
    );

    expect($report->hasIssues())->toBeTrue();
});

it('toArray omits the fix key when fix is null', function (): void {
    $report = makeReport(fix: null);

    expect($report->toArray())->not->toHaveKey('fix');
});

it('toArray includes the fix key when populated', function (): void {
    $fix = [
        'skills' => ['attempted' => 2, 'resolved' => 2],
        'guidelines' => ['attempted' => 0, 'resolved' => 0],
        'mcp' => ['attempted' => 'updated', 'resolved' => 'unchanged'],
        'orphans' => ['attempted' => 0, 'resolved' => 0],
        'legacy_copilot' => ['attempted' => false, 'resolved' => false],
        'gitattributes' => ['attempted' => true, 'resolved' => true],
    ];

    $report = makeReport(fix: $fix);
    $payload = $report->toArray();

    expect($payload)->toHaveKey('fix');
    expect(is_array($payload['fix'] ?? null) ? $payload['fix'] : [])->toBe($fix);
    expect($payload['schema'] ?? null)->toBe(1);
});
