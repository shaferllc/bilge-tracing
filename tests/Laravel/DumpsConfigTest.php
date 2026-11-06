<?php

declare(strict_types=1);

namespace Bilge\Tracing\Tests\Laravel;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * The dumps signal is opt-in but, unlike every other signal, used to be a hardcoded `false` with no
 * env toggle — so it could only be enabled by publishing the config. It now reads BILGE_DUMPS_ENABLED.
 */
final class DumpsConfigTest extends TestCase
{
    private string $configPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configPath = dirname(__DIR__, 2).'/src/Laravel/config/bilge-tracing.php';
        // The config file calls config('services.bilge.url'); bind a minimal container so it resolves.
        $app = new Container;
        $app->instance('config', new Repository([]));
        Container::setInstance($app);
        $this->clearEnv();
    }

    protected function tearDown(): void
    {
        $this->clearEnv();
        Container::setInstance(null);
        parent::tearDown();
    }

    private function clearEnv(): void
    {
        putenv('BILGE_DUMPS_ENABLED');
        unset($_ENV['BILGE_DUMPS_ENABLED'], $_SERVER['BILGE_DUMPS_ENABLED']);
    }

    public function test_dumps_are_disabled_by_default(): void
    {
        $config = require $this->configPath;

        $this->assertFalse($config['dumps']['enabled']);
    }

    public function test_dumps_can_be_enabled_via_env(): void
    {
        putenv('BILGE_DUMPS_ENABLED=true');
        $_ENV['BILGE_DUMPS_ENABLED'] = 'true';
        $_SERVER['BILGE_DUMPS_ENABLED'] = 'true';

        $config = require $this->configPath;

        $this->assertTrue($config['dumps']['enabled']);
    }
}
