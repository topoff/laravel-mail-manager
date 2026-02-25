<?php

namespace Topoff\MailManager\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Topoff\MailManager\Services\SesSns\SesSnsSetupService;
use Throwable;

class SetupSesSnsTrackingAction extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = 'Setup SES/SNS Tracking';

    public function handle(ActionFields $fields, Collection $models): mixed
    {
        try {
            if (! (bool) config('mail-manager.ses_sns.enabled', false)) {
                return Action::danger('SES/SNS setup is disabled. Set mail-manager.ses_sns.enabled=true and try again.');
            }

            $statusUrl = URL::temporarySignedRoute(
                'mail-manager.ses-sns.status',
                now()->addMinutes(30)
            );

            $service = app(SesSnsSetupService::class);
            $result = $service->setup();
            if (! (bool) ($result['ok'] ?? false)) {
                Log::warning('mail-manager SES/SNS setup finished with failing checks.', [
                    'status' => $result['status'] ?? null,
                ]);

                return Action::danger('Setup executed but checks are not fully green. Status URL: '.$statusUrl);
            }

            Log::info('mail-manager SES/SNS setup succeeded.');

            return Action::message('SES/SNS setup completed. Status URL: '.$statusUrl);
        } catch (Throwable $e) {
            Log::error('mail-manager SES/SNS setup failed.', [
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return Action::danger('SES/SNS setup failed: '.$e->getMessage());
        }
    }

    public function fields(NovaRequest $request): array
    {
        return [];
    }
}
