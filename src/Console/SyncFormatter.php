<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console;

/**
 * Renders {@see SyncPlan} instances to text output. JSON rendering lands in
 * 0.6.0 Phase 2 on the same formatter.
 */
final readonly class SyncFormatter
{
    public function __construct(
        private \Closure $writeLine,
        private \Closure $warn,
    ) {}

    public function renderText(string $category, SyncPlan $plan, bool $showUnchanged): void
    {
        if ($plan->skipped !== null) {
            ($this->warn)($this->skippedMessage($category, $plan->skipped));

            return;
        }

        $this->writeLine($this->categoryHeader($category));

        foreach ($plan->new as $action) {
            $this->writeLine('  ' . SyncReporter::glyph('new') . ' ' . $action->target . ($action->hint ?? ''));
        }

        foreach ($plan->updated as $action) {
            $this->writeLine('  ' . SyncReporter::glyph('updated') . ' ' . $action->target . ($action->hint ?? ''));
        }

        foreach ($plan->removed as $action) {
            $this->writeLine('  ' . SyncReporter::glyph('removed') . ' ' . $action->target);
        }

        if ($showUnchanged) {
            foreach ($plan->unchanged as $action) {
                $this->writeLine('  ' . SyncReporter::glyph('unchanged') . ' ' . $action->target);
            }
        }

        $this->writeLine('  ' . SyncReporter::summaryLine($plan->counts()));
    }

    /**
     * @param  array{skills?: SyncPlan, guidelines?: SyncPlan, mcp?: SyncPlan}  $plans
     */
    public static function renderJson(array $plans, bool $check, bool $showUnchanged): string
    {
        $doc = [
            'schema' => 1,
            'check' => $check,
            'drift' => false,
        ];

        foreach (['skills', 'guidelines', 'mcp'] as $category) {
            if (! isset($plans[$category])) {
                continue;
            }

            $plan = $plans[$category];
            $doc[$category] = $category === 'mcp'
                ? self::jsonMcpCategory($plan)
                : self::jsonCollectionCategory($plan, $showUnchanged);

            if ($plan->hasDrift()) {
                $doc['drift'] = true;
            }
        }

        return json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    /**
     * @return array<string, mixed>
     */
    private static function jsonCollectionCategory(SyncPlan $plan, bool $showUnchanged): array
    {
        if ($plan->skipped !== null) {
            return ['skipped' => $plan->skipped];
        }

        return [
            'new' => self::jsonActions(self::sortByTarget($plan->new)),
            'updated' => self::jsonActions(self::sortByTarget($plan->updated)),
            'removed' => self::jsonActions(self::sortByTarget($plan->removed)),
            'unchanged' => $showUnchanged
                ? self::jsonActions(self::sortByTarget($plan->unchanged))
                : count($plan->unchanged),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function jsonMcpCategory(SyncPlan $plan): array
    {
        if ($plan->skipped !== null) {
            return ['action' => 'skipped', 'reason' => $plan->skipped];
        }

        $action = match (true) {
            $plan->new !== [] => 'new',
            $plan->updated !== [] => 'updated',
            default => 'unchanged',
        };

        return ['action' => $action, 'target' => '.mcp.json'];
    }

    /**
     * @param  array<int, SyncAction>  $actions
     * @return array<int, array<string, mixed>>
     */
    private static function jsonActions(array $actions): array
    {
        return array_values(array_map(
            static function (SyncAction $action): array {
                $entry = ['target' => $action->target];

                if ($action->hint !== null) {
                    $entry['hint'] = trim($action->hint, ' ()');
                }

                if ($action->lineDelta !== null) {
                    $entry['line_delta'] = $action->lineDelta;
                }

                return $entry;
            },
            $actions,
        ));
    }

    /**
     * @param  array<int, SyncAction>  $actions
     * @return array<int, SyncAction>
     */
    private static function sortByTarget(array $actions): array
    {
        usort($actions, static fn (SyncAction $a, SyncAction $b): int => strcmp($a->target, $b->target));

        return $actions;
    }

    private function writeLine(string $line): void
    {
        ($this->writeLine)($line);
    }

    private function categoryHeader(string $category): string
    {
        return match ($category) {
            'skills' => 'Skills:',
            'guidelines' => 'Guidelines:',
            'mcp' => 'MCP:',
            default => ucfirst($category) . ':',
        };
    }

    private function skippedMessage(string $category, string $reason): string
    {
        return match (true) {
            $category === 'skills' && $reason === 'no-sources' => 'No skills found in .ai/skills/ or shipped package-boost skills.',
            $category === 'guidelines' && $reason === 'no-sources' => 'No guideline files found in .ai/guidelines/ or shipped package-boost guidelines.',
            $category === 'mcp' && $reason === 'laravel-boost-not-installed' => 'Laravel Boost is not installed — skipping MCP config.',
            default => ucfirst($category) . " skipped: {$reason}",
        };
    }
}
