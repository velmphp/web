<?php

declare(strict_types=1);

namespace Velm\Web\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Velm\Framework\Tests\TestCase as FrameworkTestCase;
use Velm\Framework\VelmManager;

abstract class TestCase extends FrameworkTestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('velm.addon_paths', [
            dirname(__DIR__, 2).'/modules/modules',
            dirname(__DIR__, 2).'/modules/tests/fixtures',
        ]);

        $app['config']->set('velm.bootstrap_modules', ['base']);
        $app['config']->set('velm.geo_country', 'BE');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 2).'/modules/database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $manager = $this->app->make(VelmManager::class);
        $manager->installBootstrap(['base', 'admin']);
        $manager->install('partners');
    }
}
