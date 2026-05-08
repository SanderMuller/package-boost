<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console\Internal;

/**
 * @internal Strongly-typed payload built by `package-boost:doctor`.
 *
 * Shape mirrors the JSON schema documented for downstream CI parsing.
 * Keeping it as a dedicated value object lets renderText / renderJson
 * trust the field types instead of re-asserting on `mixed` access.
 *
 * @phpstan-type FrontmatterIssue array{name: string, path: string, problems: array<int, string>}
 * @phpstan-type SkillCollision array{name: string, sources: array<int, string>, winner: string}
 * @phpstan-type DriftSummary array{skills: int, guidelines: int, mcp: string}
 * @phpstan-type GitAttributesStatus array{exists: bool, managed_block_current: bool}
 */
final readonly class DoctorReport
{
    /** Status reported in `drift.mcp` when MCP planning would write the file. */
    public const MCP_STATUS_NEW = 'new';

    public const MCP_STATUS_UPDATED = 'updated';

    /** Reported when MCP is up to date OR intentionally skipped (Boost absent / Claude deselected). */
    public const MCP_STATUS_UNCHANGED = 'unchanged';

    public const MCP_STATUS_BOOST_ABSENT = 'laravel-boost-not-installed';

    public const MCP_STATUS_CLAUDE_NOT_SELECTED = 'claude-not-selected';

    /**
     * @param  ?array<int, string>  $configuredAgents
     * @param  array<int, string>  $effectiveAgents
     * @param  array<int, string>  $unknownAgents
     * @param  DriftSummary  $drift
     * @param  array<int, FrontmatterIssue>  $frontmatterIssues
     * @param  array<int, string>  $orphans
     * @param  array<int, SkillCollision>  $skillCollisions
     * @param  GitAttributesStatus  $gitAttributes
     */
    public function __construct(
        public ?array $configuredAgents,
        public array $effectiveAgents,
        public array $unknownAgents,
        public array $drift,
        public array $frontmatterIssues,
        public array $orphans,
        public array $skillCollisions,
        public bool $boostInstalled,
        public bool $legacyCopilotInstructions,
        public array $gitAttributes,
    ) {}

    public function hasIssues(): bool
    {
        if ($this->unknownAgents !== []) {
            return true;
        }

        if ($this->drift['skills'] > 0 || $this->drift['guidelines'] > 0) {
            return true;
        }

        // `laravel-boost-not-installed` and `claude-not-selected` are
        // status reports, not drift — sync intentionally skips MCP in
        // those cases, so doctor must agree or every framework-agnostic
        // package fails CI.
        if (in_array($this->drift['mcp'], [self::MCP_STATUS_NEW, self::MCP_STATUS_UPDATED], true)) {
            return true;
        }

        if ($this->frontmatterIssues !== [] || $this->orphans !== []) {
            return true;
        }

        if ($this->legacyCopilotInstructions) {
            return true;
        }

        // `skillCollisions` is intentionally **not** a failure: host
        // overriding shipped or vendor is a documented feature (later
        // wins). Doctor surfaces collisions as advisory output so the
        // user can spot unintended shadowing, but keeping it
        // exit-zero preserves the override-as-feature workflow.

        return ! $this->gitAttributes['exists'] || ! $this->gitAttributes['managed_block_current'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schema' => 1,
            'agents' => [
                'configured' => $this->configuredAgents,
                'effective' => $this->effectiveAgents,
                'unknown' => $this->unknownAgents,
            ],
            'drift' => $this->drift,
            'frontmatter_issues' => $this->frontmatterIssues,
            'orphans' => $this->orphans,
            'skill_collisions' => $this->skillCollisions,
            'boost_installed' => $this->boostInstalled,
            'legacy_copilot_instructions' => $this->legacyCopilotInstructions,
            'gitattributes' => $this->gitAttributes,
        ];
    }
}
