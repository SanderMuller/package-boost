<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console;

use Illuminate\Console\Command;
use SanderMuller\PackageBoost\Agents\Agent;
use SanderMuller\PackageBoost\Agents\Registry;
use SanderMuller\PackageBoost\Console\Internal\BoostDetector;
use SanderMuller\PackageBoost\Console\Internal\DeselectedAgentArtifacts;
use SanderMuller\PackageBoost\Console\Internal\DoctorReport;
use SanderMuller\PackageBoost\Console\Internal\FormatOption;
use SanderMuller\PackageBoost\Console\Internal\LegacyCopilotInstructions;
use SanderMuller\PackageBoost\Console\Internal\PackageRoot;
use SanderMuller\PackageBoost\Console\Internal\SkillFrontmatter;
use SanderMuller\PackageBoost\Console\Internal\SyncPlanner;
use SanderMuller\PackageBoost\Console\Internal\SyncReporter;
use SanderMuller\PackageBoost\Console\Internal\SyncSources;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Single-shot diagnostic that fans out the checks scattered across
 * `package-boost:sync --check`, `package-boost:install`, and
 * `package-boost:lean`. Reports configured agents, sync drift, SKILL.md
 * frontmatter issues, deselected-agent orphans, vendor skill collisions,
 * MCP detection state, the legacy Copilot file, and the `.gitattributes`
 * managed block — exit-non-zero when any check fails.
 *
 * @phpstan-import-type FixOutcome from DoctorReport
 */
final class DoctorCommand extends Command
{
    protected $signature = 'package-boost:doctor
        {--format=text : Output format — "text" (default) or "json"}
        {--fix : Auto-resolve mechanically-safe drift (re-sync, prune orphans, prune legacy Copilot file, rewrite .gitattributes block). Vendor/shipped frontmatter, unknown agents, and skill collisions stay report-only.}';

    protected $description = 'Diagnose package-boost configuration, drift, and skill hygiene';

    public function handle(): int
    {
        $format = FormatOption::read($this);

        if (! FormatOption::isSupported($format)) {
            $this->components->error(FormatOption::invalidMessage($format));

            return self::FAILURE;
        }

        $root = PackageRoot::resolve();
        $report = $this->buildReport($root);

        if ($this->option('fix') === true) {
            $report = $this->applyFixes($root, $report, $format);
        }

        if ($format === 'json') {
            $encoded = json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $this->output->writeln($encoded === false ? '{}' : $encoded);
        } else {
            $this->renderText($report);
        }

        return $report->hasIssues() ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Delegate the fix-eligible categories to existing commands and
     * rebuild the report from post-fix state. We never propagate
     * `--format=json` to the inner sync: `SyncCommand::renderPostCategoryOutput`
     * returns early on JSON, skipping both prune blocks. When the outer
     * format is JSON we route inner output to `NullOutput` via
     * `runCommand()` so stdout stays a single JSON document — using
     * `Artisan::call(..., $buffer)` would clobber the application's
     * `lastOutput` pointer and break parent-side `Artisan::output()`.
     * The pre-fix report is accepted now so Phase 3 can derive
     * `fix.attempted/resolved` pairs from a pre/post diff.
     */
    private function applyFixes(string $root, DoctorReport $preFix, string $format): DoctorReport
    {
        if ($format === 'json') {
            $innerOutput = new NullOutput();
            $this->runCommand('package-boost:sync', [
                '--prune' => true,
                '--prune-orphans' => true,
            ], $innerOutput);
            $this->runCommand('package-boost:lean', [], $innerOutput);
        } else {
            $this->line('Fixing drift…');
            $this->call('package-boost:sync', [
                '--prune' => true,
                '--prune-orphans' => true,
            ]);
            $this->line('Fixing .gitattributes…');
            $this->call('package-boost:lean');
            $this->line('');
            $this->line('Doctor (post-fix):');
        }

        // Build the post-fix report once and attach the computed `fix`
        // payload to the same snapshot via `withFix`. A second
        // `buildReport` call would re-walk the filesystem and could
        // disagree with the diff used to compute `fix.*`.
        $postFix = $this->buildReport($root);

        return $postFix->withFix($this->computeFix($preFix, $postFix));
    }

    /**
     * Build the per-category `attempted`/`resolved` outcome for the
     * JSON `fix` field. `attempted` reflects what doctor saw before
     * delegating; `resolved` reflects what actually changed (post-fix
     * vs pre-fix). A refusal — sync warning but no remediation — shows
     * as `attempted: true` / `resolved: false`, distinct from a noop
     * (`attempted: false` / `resolved: false`).
     *
     * @return FixOutcome
     */
    private function computeFix(DoctorReport $pre, DoctorReport $post): array
    {
        $preGitAttrAttempted = ! $pre->gitAttributes['exists'] || ! $pre->gitAttributes['managed_block_current'];
        $postGitAttrClean = $post->gitAttributes['exists'] && $post->gitAttributes['managed_block_current'];

        return [
            DoctorReport::FIX_CATEGORY_SKILLS => [
                'attempted' => $pre->drift['skills'],
                'resolved' => max(0, $pre->drift['skills'] - $post->drift['skills']),
            ],
            DoctorReport::FIX_CATEGORY_GUIDELINES => [
                'attempted' => $pre->drift['guidelines'],
                'resolved' => max(0, $pre->drift['guidelines'] - $post->drift['guidelines']),
            ],
            DoctorReport::FIX_CATEGORY_MCP => [
                'attempted' => $pre->drift['mcp'],
                'resolved' => $post->drift['mcp'],
            ],
            DoctorReport::FIX_CATEGORY_ORPHANS => [
                'attempted' => count($pre->orphans),
                'resolved' => max(0, count($pre->orphans) - count($post->orphans)),
            ],
            DoctorReport::FIX_CATEGORY_LEGACY_COPILOT => [
                'attempted' => $pre->legacyCopilotInstructions,
                'resolved' => $pre->legacyCopilotInstructions && ! $post->legacyCopilotInstructions,
            ],
            DoctorReport::FIX_CATEGORY_GITATTRIBUTES => [
                'attempted' => $preGitAttrAttempted,
                'resolved' => $preGitAttrAttempted && $postGitAttrClean,
            ],
        ];
    }

    /**
     * @param  ?FixOutcome  $fix  populated only after `--fix` ran
     */
    private function buildReport(string $root, ?array $fix = null): DoctorReport
    {
        $configuredNames = Registry::configuredNames();
        $unknown = $configuredNames === null ? [] : Registry::unknownNames($configuredNames);
        $selected = Registry::forSelection($configuredNames);

        $skills = SyncSources::skills($root);
        $trace = SyncSources::traceSkills($root);
        $boostInstalled = (new BoostDetector($this->getLaravel()))->installed();

        $frontmatterIssues = SkillFrontmatter::lint($skills);
        $hostFrontmatterIssues = SkillFrontmatter::filterBlocking($frontmatterIssues, $root);

        return new DoctorReport(
            configuredAgents: $configuredNames,
            effectiveAgents: array_map(static fn (Agent $a): string => $a->name, $selected),
            unknownAgents: $unknown,
            drift: $this->driftSummary($root, $selected, $skills, $boostInstalled),
            frontmatterIssues: $frontmatterIssues,
            hostFrontmatterIssues: $hostFrontmatterIssues,
            orphans: DeselectedAgentArtifacts::locate($root, $selected),
            skillCollisions: $this->collisions($trace, $root),
            boostInstalled: $boostInstalled,
            // Only flag when our wrapping tag is present, mirroring
            // `SyncCommand::warnAboutLegacyCopilotInstructions`. A
            // hand-authored Copilot file without our tag is not a
            // package-boost concern; flagging it would leave `--fix`
            // permanently red since sync's `--prune` only acts on
            // tagged files.
            legacyCopilotInstructions: LegacyCopilotInstructions::read($root) !== null,
            gitAttributes: $this->gitAttributesStatus($root),
            fix: $fix,
        );
    }

    /**
     * @param  array<int, Agent>  $selected
     * @param  array<string, string>  $skills
     * @return array{skills: int, guidelines: int, mcp: string}
     */
    private function driftSummary(string $root, array $selected, array $skills, bool $boostInstalled): array
    {
        return [
            'skills' => $this->countSkillDrift($root, $selected, $skills),
            'guidelines' => $this->countGuidelineDrift($root, $selected),
            'mcp' => $this->mcpStatus($root, $selected, $boostInstalled),
        ];
    }

    /**
     * Reuses `SyncPlanner::planSkills` so doctor sees the same set of
     * actions `--check` would (including `removed` for stale generated
     * dirs whose source skill was deleted or renamed).
     *
     * @param  array<int, Agent>  $selected
     * @param  array<string, string>  $skills
     */
    private function countSkillDrift(string $root, array $selected, array $skills): int
    {
        $plan = SyncPlanner::planSkills($root, $skills, Registry::skillTargets($selected));

        return count($plan->new) + count($plan->updated) + count($plan->removed);
    }

    /**
     * @param  array<int, Agent>  $selected
     */
    private function countGuidelineDrift(string $root, array $selected): int
    {
        [$plan] = SyncPlanner::planGuidelines($root, Registry::guidelineTargets($selected));

        return count($plan->new) + count($plan->updated) + count($plan->removed);
    }

    /**
     * Mirrors `SyncCommand::categoriseMcp` so doctor and `sync --check`
     * agree: a framework-agnostic package without `laravel/boost`
     * legitimately has no `.mcp.json`, and a Boost-less host that
     * deselects `claude_code` shouldn't be flagged either.
     *
     * @param  array<int, Agent>  $selected
     */
    private function mcpStatus(string $root, array $selected, bool $boostInstalled): string
    {
        foreach ($selected as $agent) {
            if ($agent->name !== 'claude_code') {
                continue;
            }

            if (! $boostInstalled) {
                return DoctorReport::MCP_STATUS_BOOST_ABSENT;
            }

            $path = $root . DIRECTORY_SEPARATOR . '.mcp.json';
            [$action] = SyncReporter::planMcpAction($path, SyncSources::mcpConfig($path));

            return $action;
        }

        return DoctorReport::MCP_STATUS_CLAUDE_NOT_SELECTED;
    }

    /**
     * @param  array<string, array<int, string>>  $trace
     * @return array<int, array{name: string, sources: array<int, string>, winner: string}>
     */
    private function collisions(array $trace, string $root): array
    {
        $collisions = [];

        foreach ($trace as $name => $sources) {
            if (count($sources) < 2) {
                continue;
            }

            $collisions[] = [
                'name' => $name,
                'sources' => array_map(static fn (string $src): string => self::labelSource($src, $root), $sources),
                'winner' => self::labelSource($sources[count($sources) - 1], $root),
            ];
        }

        return $collisions;
    }

    private static function labelSource(string $path, string $root): string
    {
        $hostPrefix = $root . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR;

        if (str_starts_with($path, $hostPrefix)) {
            return 'host:' . substr($path, strlen($root) + 1);
        }

        $vendorPrefix = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR;

        if (str_starts_with($path, $vendorPrefix)) {
            $relative = substr($path, strlen($vendorPrefix));
            $segments = explode(DIRECTORY_SEPARATOR, $relative);
            $package = ($segments[0] ?? '') . '/' . ($segments[1] ?? '');

            return 'vendor:' . $package;
        }

        return 'shipped';
    }

    /**
     * @return array{exists: bool, managed_block_current: bool}
     */
    private function gitAttributesStatus(string $root): array
    {
        $path = $root . DIRECTORY_SEPARATOR . '.gitattributes';

        if (! is_file($path)) {
            return ['exists' => false, 'managed_block_current' => false];
        }

        $current = (string) file_get_contents($path);
        $desired = LeanCommand::renderUpdated($current);

        return [
            'exists' => true,
            'managed_block_current' => $current === $desired,
        ];
    }

    private function renderText(DoctorReport $report): void
    {
        $this->line('Agents: ' . ($report->effectiveAgents === [] ? '(none)' : implode(', ', $report->effectiveAgents)));

        if ($report->unknownAgents !== []) {
            $this->components->warn('Unknown agent name(s) in config: ' . implode(', ', $report->unknownAgents));
        }

        $this->line(sprintf(
            'Drift: skills=%d, guidelines=%d, mcp=%s',
            $report->drift['skills'],
            $report->drift['guidelines'],
            $report->drift['mcp'],
        ));

        $this->renderFrontmatterIssues($report);
        $this->renderOrphans($report);
        $this->renderCollisions($report);
        $this->renderGitAttributesAdvisory($report);

        if ($report->legacyCopilotInstructions) {
            $this->components->warn('Legacy .github/copilot-instructions.md detected — run `package-boost:sync --prune`.');
        }

        $this->line('Laravel Boost installed: ' . ($report->boostInstalled ? 'yes' : 'no'));
    }

    private function renderFrontmatterIssues(DoctorReport $report): void
    {
        if ($report->frontmatterIssues === []) {
            return;
        }

        $this->components->warn('SKILL.md frontmatter issues:');

        foreach ($report->frontmatterIssues as $issue) {
            $this->line('  - ' . $issue['name'] . ': ' . implode('; ', $issue['problems']));
        }
    }

    private function renderOrphans(DoctorReport $report): void
    {
        if ($report->orphans === []) {
            return;
        }

        $this->components->warn(
            'Deselected-agent orphans (run `package-boost:sync --prune-orphans` to remove):'
        );

        foreach ($report->orphans as $orphan) {
            $this->line('  - ' . $orphan);
        }
    }

    private function renderCollisions(DoctorReport $report): void
    {
        if ($report->skillCollisions === []) {
            return;
        }

        $this->components->warn('Skill name collisions (later source wins):');

        foreach ($report->skillCollisions as $collision) {
            $this->line(sprintf(
                '  - %s: %s (winner: %s)',
                $collision['name'],
                implode(' → ', $collision['sources']),
                $collision['winner'],
            ));
        }
    }

    private function renderGitAttributesAdvisory(DoctorReport $report): void
    {
        if (! $report->gitAttributes['exists']) {
            $this->components->warn('.gitattributes is missing — run `package-boost:lean` to generate it.');

            return;
        }

        if (! $report->gitAttributes['managed_block_current']) {
            $this->components->warn('.gitattributes managed block is out of date — run `package-boost:lean`.');
        }
    }
}
