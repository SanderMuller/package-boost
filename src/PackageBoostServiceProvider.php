<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost;

use Illuminate\Config\Repository;
use Illuminate\Support\ServiceProvider;
use Laravel\Boost\BoostServiceProvider;
use SanderMuller\PackageBoost\Console\SyncCommand;

class PackageBoostServiceProvider extends ServiceProvider
{
    public const PUBLISH_TAG = 'package-boost-config';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/package-boost.php', 'package-boost');

        $this->commands([
            SyncCommand::class,
        ]);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/package-boost.php' => $this->resolvePublishDestination(),
            ], self::PUBLISH_TAG);
        }

        $this->mergeBoostGuidelineExcludes();
    }

    private function resolvePublishDestination(): string
    {
        if (function_exists('Orchestra\Testbench\workbench_path')) {
            return \Orchestra\Testbench\workbench_path('config/package-boost.php');
        }

        return $this->app->configPath('package-boost.php');
    }

    protected function boostIsInstalled(): bool
    {
        return class_exists(BoostServiceProvider::class, false);
    }

    private function mergeBoostGuidelineExcludes(): void
    {
        if (! $this->boostIsInstalled()) {
            return;
        }

        /** @var Repository $config */
        $config = $this->app->make('config');

        /** @var array<int, string> $packageExcludes */
        $packageExcludes = (array) $config->get('package-boost.excluded_boost_guidelines', []);

        if ($packageExcludes === []) {
            return;
        }

        /** @var array<int, string> $boostExcludes */
        $boostExcludes = (array) $config->get('boost.guidelines.exclude', []);

        $config->set(
            'boost.guidelines.exclude',
            array_values(array_unique([...$boostExcludes, ...$packageExcludes]))
        );
    }
}
