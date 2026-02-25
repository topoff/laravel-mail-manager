<?php

namespace Topoff\MailManager;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Laravel\Nova\Nova;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Topoff\MailManager\Http\Controllers\MailTrackingController;
use Topoff\MailManager\Http\Controllers\NovaMailPreviewController;
use Topoff\MailManager\Http\Controllers\MailTrackingNovaController;
use Topoff\MailManager\Http\Controllers\MailTrackingSnsController;
use Topoff\MailManager\Http\Controllers\NovaCustomMessagePreviewController;
use Topoff\MailManager\Listeners\AddBccToEmailsListener;
use Topoff\MailManager\Listeners\LogEmailsListener;
use Topoff\MailManager\Listeners\LogNotificationListener;
use Topoff\MailManager\Nova\Resources\EmailLog as EmailLogResource;
use Topoff\MailManager\Nova\Resources\Message;
use Topoff\MailManager\Nova\Resources\MessageType as MessageTypeResource;
use Topoff\MailManager\Nova\Resources\NotificationLog as NotificationLogResource;
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
        Event::listen(MessageSent::class, LogEmailsListener::class);
        Event::listen(NotificationSent::class, LogNotificationListener::class);

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
                Route::get('preview-message/{message}', [NovaMailPreviewController::class, 'show'])->name('mail-manager.tracking.nova.preview-message');
            });
        }

        $customPreviewRoute = config('mail-manager.tracking.custom_preview_route', [
            'prefix' => 'email-manager/nova',
            'middleware' => ['web', 'signed'],
        ]);
        Route::group($customPreviewRoute, function (): void {
            Route::get('custom-preview', [NovaCustomMessagePreviewController::class, 'show'])->name('mail-manager.tracking.nova.custom-preview');
        });

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
                $messageTypeModelClass = config('mail-manager.models.message_type');
                if (is_string($messageTypeModelClass) && is_subclass_of($messageTypeModelClass, Model::class)) {
                    MessageTypeResource::$model = $messageTypeModelClass;
                }

                $emailLogModelClass = config('mail-manager.models.email_log');
                if (is_string($emailLogModelClass) && is_subclass_of($emailLogModelClass, Model::class)) {
                    EmailLogResource::$model = $emailLogModelClass;
                }

                $notificationLogModelClass = config('mail-manager.models.notification_log');
                if (is_string($notificationLogModelClass) && is_subclass_of($notificationLogModelClass, Model::class)) {
                    NotificationLogResource::$model = $notificationLogModelClass;
                }

                Nova::resources([
                    $resourceClass,
                    MessageTypeResource::class,
                    EmailLogResource::class,
                    NotificationLogResource::class,
                ]);
            }
        }
    }
}
