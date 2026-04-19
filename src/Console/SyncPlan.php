<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console;

/**
 * Immutable description of what a single sync category (skills, guidelines,
 * mcp) would do on the next run. Either populated with four action buckets,
 * or skipped with a reason code (no sources, Laravel Boost absent, etc.).
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
