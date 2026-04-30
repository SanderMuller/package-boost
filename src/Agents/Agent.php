<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Agents;

/**
 * Immutable description of one AI agent's sync targets and detection
 * heuristics. Field values mirror the corresponding `laravel/boost`
 * agent class on `main` @ `8ed9f84` so a Boost selection can be
 * transplanted without translation.
 */
final readonly class Agent
{
    /**
     * @param  array<int, string>  $detectionMarkers  paths whose presence in the project root suggests the user has this agent installed; used as the default-checked set on first install
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $guidelinesPath,
        public string $skillsPath,
        public array $detectionMarkers,
    ) {}
}
