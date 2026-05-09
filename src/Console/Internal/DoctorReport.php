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
 * @phpstan-type CountFixOutcome array{attempted: int, resolved: int}
 * @phpstan-type BoolFixOutcome array{attempted: bool, resolved: bool}
 * @phpstan-type McpFixOutcome array{attempted: string, resolved: string}
 * @phpstan-type FixOutcome array{
 *     skills: CountFixOutcome,
 *     guidelines: CountFixOutcome,
 *     mcp: McpFixOutcome,
 *     orphans: CountFixOutcome,
 *     legacy_copilot: BoolFixOutcome,
 *     gitattributes: BoolFixOutcome,
 * }
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

    /** Per-category keys in the JSON `fix` object. Stable contract — bump `schema` if any change. */
    public const FIX_CATEGORY_SKILLS = 'skills';

    public const FIX_CATEGORY_GUIDELINES = 'guidelines';

    public const FIX_CATEGORY_MCP = 'mcp';

    public const FIX_CATEGORY_ORPHANS = 'orphans';

    public const FIX_CATEGORY_LEGACY_COPILOT = 'legacy_copilot';

    public const FIX_CATEGORY_GITATTRIBUTES = 'gitattributes';

    /**
     * @param  ?array<int, string>  $configuredAgents
     * @param  array<int, string>  $effectiveAgents
     * @param  array<int, string>  $unknownAgents
     * @param  DriftSummary  $drift
     * @param  array<int, FrontmatterIssue>  $frontmatterIssues  full list across host + vendor + shipped (rendered as warnings)
     * @param  array<int, FrontmatterIssue>  $hostFrontmatterIssues  subset under host `.ai/skills/` — only these flip exit code
     * @param  array<int, string>  $orphans
     * @param  array<int, SkillCollision>  $skillCollisions
     * @param  GitAttributesStatus  $gitAttributes
     * @param  ?FixOutcome  $fix  populated only after `--fix` ran; per-category attempted/resolved diff
     */
    public function __construct(
        public ?array $configuredAgents,
        public array $effectiveAgents,
        public array $unknownAgents,
        public array $drift,
        public array $frontmatterIssues,
        public array $hostFrontmatterIssues,
        public array $orphans,
        public array $skillCollisions,
        public bool $boostInstalled,
        public bool $legacyCopilotInstructions,
        public array $gitAttributes,
        public ?array $fix = null,
    ) {}

    /**
     * Return a clone with the `fix` field swapped. Used by
     * `DoctorCommand::applyFixes()` to attach a computed fix outcome
     * to the same post-fix snapshot the diff was computed from —
     * avoids re-walking the filesystem and removes a TOCTOU window
     * where a second `buildReport` could disagree with the first.
     *
     * Lives here (not on the call site) because the readonly contract
     * means mutating `fix` in-place would require breaking the class
     * open; PHP 8.2 has no native `clone with` semantics.
     *
     * @param  ?FixOutcome  $fix
     */
    public function withFix(?array $fix): self
    {
        return new self(
            configuredAgents: $this->configuredAgents,
            effectiveAgents: $this->effectiveAgents,
            unknownAgents: $this->unknownAgents,
            drift: $this->drift,
            frontmatterIssues: $this->frontmatterIssues,
            hostFrontmatterIssues: $this->hostFrontmatterIssues,
            orphans: $this->orphans,
            skillCollisions: $this->skillCollisions,
            boostInstalled: $this->boostInstalled,
            legacyCopilotInstructions: $this->legacyCopilotInstructions,
            gitAttributes: $this->gitAttributes,
            fix: $fix,
        );
    }

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

        // Only host-owned (`.ai/skills/`) frontmatter issues flip exit
        // code, mirroring `SyncCommand::hasHostFrontmatterIssues`.
        // Vendor / shipped malformed SKILL.md cannot be fixed by `--fix`
        // or by the operator, so we surface them as warnings via
        // `frontmatterIssues` but do not permablock CI on them.
        if ($this->hostFrontmatterIssues !== [] || $this->orphans !== []) {
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
        $payload = [
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

        if ($this->fix !== null) {
            $payload['fix'] = $this->fix;
        }

        return $payload;
    }
}
