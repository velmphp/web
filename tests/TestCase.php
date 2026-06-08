<?php

declare(strict_types=1);

namespace Velm\Web\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Velm\Framework\Testing\InstallsVelmModules;
use Velm\Framework\Tests\TestCase as FrameworkTestCase;

abstract class TestCase extends FrameworkTestCase
{
    use InstallsVelmModules;
    use RefreshDatabase {
        InstallsVelmModules::migrateDatabases insteadof RefreshDatabase;
    }

    protected function refreshTestDatabase(): void
    {
        if (! RefreshDatabaseState::$migrated) {
            if (! static::velmTestingDatabaseIsReady()) {
                $this->migrateDatabases();

                $this->app->make(Kernel::class)->setArtisan(null);

                $this->updateLocalCacheOfInMemoryDatabases();
            } else {
                $this->bootstrapVelmForTests();
            }

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('velm.addon_paths', [
            dirname(__DIR__, 2).'/modules/modules',
            dirname(__DIR__, 2).'/modules/tests/fixtures',
        ]);

        $database = storage_path('framework/velm-testing-'.getmypid().'.sqlite');

        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => $database,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('velm.bootstrap_modules', ['base']);
        $app['config']->set('velm.geo_country', 'BE');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 2).'/modules/database/migrations');
    }

    /**
     * @return list<string>
     */
    protected function velmBootstrapModules(): array
    {
        return ['base', 'admin'];
    }
}
