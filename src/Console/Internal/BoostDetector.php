<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost\Console\Internal;

use Illuminate\Contracts\Foundation\Application;
use Laravel\Boost\BoostServiceProvider;

/**
 * @internal
 *
 * Resolves whether `laravel/boost` is present in the consuming package's
 * dependency graph. The default check is `class_exists` with autoloading
 * disabled — a deliberate choice so a stub class loaded by the test
 * harness (`tests/Stubs/BoostServiceProvider.php`) doesn't mask a real
 * absence in production.
 *
 * The container-binding seam (`package-boost.boost-detector`) is honoured
 * only while running unit tests; in any other runtime the binding is
 * ignored so a stray (or hostile) service provider in a downstream
 * application can't flip MCP sync on or off out from under the package.
 */
final readonly class BoostDetector
{
    public function __construct(private Application $app) {}

    public function installed(): bool
    {
        $override = $this->testOverride();

        return $override !== null
            ? (bool) $override()
            : class_exists(BoostServiceProvider::class, false);
    }

    /**
     * @return callable(): bool|null
     */
    private function testOverride(): ?callable
    {
        if (! $this->app->runningUnitTests()) {
            return null;
        }

        if (! $this->app->bound('package-boost.boost-detector')) {
            return null;
        }

        $detector = $this->app->make('package-boost.boost-detector');

        return is_callable($detector) ? $detector : null;
    }
}
