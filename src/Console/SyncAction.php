<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console;

/**
 * A single target entry in a {@see SyncPlan}.
 */
final readonly class SyncAction
{
    public function __construct(
        public string $target,
        public ?string $hint = null,
        public ?int $lineDelta = null,
    ) {}
}
