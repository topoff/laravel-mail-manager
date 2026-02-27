<?php

namespace Topoff\MailManager\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Notifications\NovaNotification;
use Throwable;
use Topoff\MailManager\Services\SesSns\SesSnsSetupService;

class CheckSesSnsTrackingAction extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = 'Check SES/SNS Tracking';

    public function handle(ActionFields $fields, Collection $models): mixed
    {
        try {
            Log::info('mail-manager SES/SNS check action invoked.', [
                'models_count' => $models->count(),
            ]);

            $statusUrl = URL::temporarySignedRoute(
                'mail-manager.ses-sns.site',
                now()->addMinutes(30)
            );

            $service = app(SesSnsSetupService::class);
            $status = $service->check();

            if (! $status['ok']) {
                Log::warning('mail-manager SES/SNS check returned failing checks.', ['status' => $status]);
                request()->user()?->notify(
                    NovaNotification::make()
                        ->message('SES/SNS check failed. Open status page via provided URL.')
                        ->type('warning')
                );

                return ActionResponse::danger('SES/SNS check failed. Status URL: '.$statusUrl);
            }

            request()->user()?->notify(
                NovaNotification::make()
                    ->message('SES/SNS check is green.')
                    ->type('success')
            );

            return ActionResponse::message('SES/SNS check is green. Status URL: '.$statusUrl);
        } catch (Throwable $e) {
            Log::error('mail-manager SES/SNS check failed.', [
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);
            request()->user()?->notify(
                NovaNotification::make()
                    ->message('SES/SNS check failed: '.$e->getMessage())
                    ->type('error')
            );

            return ActionResponse::danger('SES/SNS check failed: '.$e->getMessage());
        }
    }

    public function fields(NovaRequest $request): array
    {
        return [];
    }
}
