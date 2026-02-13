<?php

namespace Topoff\MailManager\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Topoff\MailManager\MailManagerServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            MailManagerServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
    }
}
