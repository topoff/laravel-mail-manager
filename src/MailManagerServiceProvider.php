<?php

namespace Topoff\MailManager;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Topoff\MailManager\Observers\MessageTypeObserver;
use Topoff\MailManager\Repositories\MessageTypeRepository;

class MailManagerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-mail-manager')
            ->hasConfigFile()
            ->discoversMigrations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(MessageTypeRepository::class);
    }

    public function packageBooted(): void
    {
        $messageTypeClass = config('mail-manager.models.message_type');
        $messageTypeClass::observe(MessageTypeObserver::class);
    }
}
