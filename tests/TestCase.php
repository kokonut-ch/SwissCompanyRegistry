<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Tests;

use Kokonut\SwissCompanyRegistry\Facades\SwissCompany;
use Kokonut\SwissCompanyRegistry\Facades\SwissUid;
use Kokonut\SwissCompanyRegistry\SwissCompanyRegistryServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            SwissCompanyRegistryServiceProvider::class,
        ];
    }

    /**
     * Testbench does not read "extra.laravel" from composer.json, so the
     * alias an application gets from package discovery has to be declared
     * here for the test suite to exercise the same thing.
     */
    protected function getPackageAliases($app): array
    {
        return [
            'SwissCompany' => SwissCompany::class,
            'SwissUid' => SwissUid::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // The skeleton's default cache store is "database"; tests have no
        // cache table, and the cache behavior under test is store-agnostic.
        $app['config']->set('cache.default', 'array');

        $app['config']->set('swiss-company-registry.providers.zefix.username', 'zefix-user');
        $app['config']->set('swiss-company-registry.providers.zefix.password', 'zefix-secret');
    }
}
