<?php

namespace Topoff\MailManager;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Laravel\Nova\Nova;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Topoff\MailManager\Http\Controllers\MailTrackingController;
use Topoff\MailManager\Http\Controllers\MailTrackingNovaController;
use Topoff\MailManager\Http\Controllers\MailTrackingSnsController;
use Topoff\MailManager\Listeners\AddBccToEmailsListener;
use Topoff\MailManager\Nova\Resources\Message;
use Topoff\MailManager\Observers\MessageTypeObserver;
use Topoff\MailManager\Repositories\MessageTypeRepository;
use Topoff\MailManager\Tracking\MailTracker;

class MailManagerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-mail-manager')
            ->hasConfigFile()
            ->hasViews()
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

        Event::listen(MessageSending::class, AddBccToEmailsListener::class);
        Event::listen(MessageSending::class, fn (MessageSending $event) => app(MailTracker::class)->messageSending($event));
        Event::listen(MessageSent::class, fn (MessageSent $event) => app(MailTracker::class)->messageSent($event));

        $routeConfig = config('mail-manager.tracking.route', []);
        Route::group($routeConfig, function (): void {
            Route::get('t/{hash}', [MailTrackingController::class, 'open'])->name('mail-manager.tracking.open');
            Route::get('n', [MailTrackingController::class, 'click'])->name('mail-manager.tracking.click')->middleware('signed');
            Route::post('sns', [MailTrackingSnsController::class, 'callback'])->name('mail-manager.tracking.sns');
        });

        $novaConfig = array_replace_recursive([
            'enabled' => true,
            'register_resource' => false,
            'resource' => Message::class,
            'preview_route' => [
                'prefix' => 'email-manager/nova',
                'middleware' => ['web', 'signed'],
            ],
        ], (array) config('mail-manager.tracking.nova', []));
        if ((bool) ($novaConfig['enabled'] ?? true)) {
            $previewRoute = $novaConfig['preview_route'] ?? [];
            Route::group($previewRoute, function (): void {
                Route::get('preview/{id}', [MailTrackingNovaController::class, 'preview'])->name('mail-manager.tracking.nova.preview');
            });
        }

        if (class_exists(Nova::class) && (bool) ($novaConfig['enabled'] ?? true)) {
            $resourceClass = $novaConfig['resource'] ?? Message::class;
            if (is_string($resourceClass) && class_exists($resourceClass)) {
                $modelClass = config('mail-manager.models.message');
                if (is_string($modelClass) && is_subclass_of($modelClass, Model::class)) {
                    $resourceClass::$model = $modelClass;
                }
            }

            if (
                (bool) ($novaConfig['register_resource'] ?? false)
                && is_string($resourceClass)
                && class_exists($resourceClass)
            ) {
                Nova::resources([$resourceClass]);
            }
        }
    }
}
