<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console;

/**
 * Immutable description of what a single category ({@see SyncCommand::runSkills}
 * / guidelines / mcp) would do on the next sync. Produced by the `plan*`
 * methods on {@see SyncCommand}, consumed by {@see SyncFormatter} for output
 * and by the `apply*` methods for writes.
 *
 * A plan is either drifted (the four action buckets describe changes) or
 * skipped (no sources or a precondition failed, e.g. Laravel Boost absent
 * for the MCP category).
 */
final readonly class SyncPlan
{
    /**
     * @param  array<int, SyncAction>  $new
     * @param  array<int, SyncAction>  $updated
     * @param  array<int, SyncAction>  $unchanged
     * @param  array<int, SyncAction>  $removed
     * @param  ?string  $skipped  reason code when the category was not evaluated
     */
    public function __construct(
        public array $new = [],
        public array $updated = [],
        public array $unchanged = [],
        public array $removed = [],
        public ?string $skipped = null,
    ) {}

    public static function skipped(string $reason): self
    {
        return new self(skipped: $reason);
    }

    public function hasDrift(): bool
    {
        return $this->skipped === null && ($this->new !== [] || $this->updated !== [] || $this->removed !== []);
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return [
            'new' => count($this->new),
            'updated' => count($this->updated),
            'unchanged' => count($this->unchanged),
            'removed' => count($this->removed),
        ];
    }
}
