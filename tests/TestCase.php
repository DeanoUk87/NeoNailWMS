<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Set up the test environment.
     *
     * On Windows, the git-tracked project directory may not allow tempnam() in
     * storage/framework/views. Redirect compiled view cache to the system temp
     * directory so Blade compilation succeeds in CI/local test runs.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $viewCacheDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'neonailwms_views';
        if (! is_dir($viewCacheDir)) {
            mkdir($viewCacheDir, 0755, true);
        }
        $this->app['config']->set('view.compiled', $viewCacheDir);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
