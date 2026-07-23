<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Kokonut\SwissCompanyRegistry\Console\LookupCommand;
use Kokonut\SwissCompanyRegistry\Console\SearchCommand;
use Kokonut\SwissCompanyRegistry\Values\UidFactory;

class SwissCompanyRegistryServiceProvider extends ServiceProvider
{
    /**
     * The manager is a singleton so the provider chain is built once per
     * request. The "SwissCompany" and "SwissUid" aliases that make the
     * facades reachable without an import are declared in composer.json
     * under "extra.laravel", where Laravel's package discovery picks
     * them up.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/swiss-company-registry.php', 'swiss-company-registry');

        $this->app->singleton(SwissCompanyRegistry::class, function (Application $app): SwissCompanyRegistry {
            /** @var array<string, mixed> $config */
            $config = $app->make('config')->get('swiss-company-registry', []);

            return new SwissCompanyRegistry($config);
        });

        $this->app->singleton(UidFactory::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/swiss-company-registry.php' => config_path('swiss-company-registry.php'),
            ], 'swiss-company-registry-config');

            $this->commands([
                SearchCommand::class,
                LookupCommand::class,
            ]);
        }
    }
}
