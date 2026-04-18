<?php declare(strict_types=1);

namespace SanderMuller\PackageBoost;

use Illuminate\Config\Repository;
use Illuminate\Support\ServiceProvider;
use Laravel\Boost\BoostServiceProvider;
use SanderMuller\PackageBoost\Console\SyncCommand;

class PackageBoostServiceProvider extends ServiceProvider
{
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
                __DIR__ . '/../config/package-boost.php' => $this->app->configPath('package-boost.php'),
            ], 'package-boost-config');
        }

        $this->mergeBoostGuidelineExcludes();
    }

    private function mergeBoostGuidelineExcludes(): void
    {
        if (! class_exists(BoostServiceProvider::class)) {
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
