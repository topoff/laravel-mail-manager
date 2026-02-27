<?php

namespace Topoff\MailManager\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Throwable;
use Topoff\MailManager\Services\SesSns\SesSendingSetupService;
use Topoff\MailManager\Services\SesSns\SesSnsSetupService;

class SesSnsNovaSiteController extends Controller
{
    public function __invoke()
    {
        $tracking = $this->resolveTrackingStatus();
        $sending = $this->resolveSendingStatus();

        return view('mail-manager::ses-sns-site', [
            'tracking' => $tracking,
            'sending' => $sending,
            'routes' => [
                'tracking_open' => Route::has('mail-manager.tracking.open') ? route('mail-manager.tracking.open', ['hash' => 'tracking_hash']) : null,
                'tracking_click' => Route::has('mail-manager.tracking.click') ? route('mail-manager.tracking.click', ['l' => '{signed_target_url}', 'h' => '{tracking_hash}']) : null,
                'sns_callback' => Route::has('mail-manager.tracking.sns') ? route('mail-manager.tracking.sns') : null,
            ],
            'commands' => [
                'php artisan mail-manager:ses-sns:setup-all',
                'php artisan mail-manager:ses-sns:setup-sending',
                'php artisan mail-manager:ses-sns:check-sending',
                'php artisan mail-manager:ses-sns:setup-tracking',
                'php artisan mail-manager:ses-sns:check-tracking',
                'php artisan mail-manager:ses-sns:test-events --scenario=delivery --wait=0',
                'php artisan mail-manager:ses-sns:test-events --scenario=bounce --wait=0',
                'php artisan mail-manager:ses-sns:test-events --scenario=complaint --wait=0',
                'php artisan mail-manager:ses-sns:test-events --scenario=delivery --create-message-record --wait=180 --poll-interval=3',
                'php artisan mail-manager:ses-sns:test-events --scenario=bounce --create-message-record --wait=180 --poll-interval=3',
                'php artisan mail-manager:ses-sns:test-events --scenario=complaint --create-message-record --wait=180 --poll-interval=3',
                'php artisan mail-manager:ses-sns:teardown --force',
            ],
            'command_buttons' => [
                [
                    'label' => 'Setup SES/SNS All',
                    'description' => 'One-shot setup for SES sending, tracking, and tenant associations.',
                    'url' => URL::temporarySignedRoute('mail-manager.ses-sns.site.command', now()->addMinutes(30), ['command' => 'setup-all']),
                ],
                [
                    'label' => 'Setup SES Sending',
                    'description' => 'Create/check SES identity and expected DNS records.',
                    'url' => URL::temporarySignedRoute('mail-manager.ses-sns.site.command', now()->addMinutes(30), ['command' => 'setup-sending']),
                ],
                [
                    'label' => 'Check SES Sending',
                    'description' => 'Validate SES sending identity and verification state.',
                    'url' => URL::temporarySignedRoute('mail-manager.ses-sns.site.command', now()->addMinutes(30), ['command' => 'check-sending']),
                ],
                [
                    'label' => 'Setup SES/SNS Tracking',
                    'description' => 'Provision SES configuration set + SNS destination/subscription.',
                    'url' => URL::temporarySignedRoute('mail-manager.ses-sns.site.command', now()->addMinutes(30), ['command' => 'setup-tracking']),
                ],
                [
                    'label' => 'Check SES/SNS Tracking',
                    'description' => 'Validate current SES/SNS tracking setup status.',
                    'url' => URL::temporarySignedRoute('mail-manager.ses-sns.site.command', now()->addMinutes(30), ['command' => 'check-tracking']),
                ],
                [
                    'label' => 'Test Delivery Event',
                    'description' => 'Send SES simulator delivery event (success@simulator.amazonses.com).',
                    'url' => URL::temporarySignedRoute('mail-manager.ses-sns.site.command', now()->addMinutes(30), ['command' => 'test-delivery']),
                ],
                [
                    'label' => 'Test Bounce Event',
                    'description' => 'Send SES simulator bounce event (bounce@simulator.amazonses.com).',
                    'url' => URL::temporarySignedRoute('mail-manager.ses-sns.site.command', now()->addMinutes(30), ['command' => 'test-bounce']),
                ],
                [
                    'label' => 'Test Complaint Event',
                    'description' => 'Send SES simulator complaint event (complaint@simulator.amazonses.com).',
                    'url' => URL::temporarySignedRoute('mail-manager.ses-sns.site.command', now()->addMinutes(30), ['command' => 'test-complaint']),
                ],
                [
                    'label' => 'Test Delivery Event + DB Verify',
                    'description' => 'Send delivery simulator event and verify tracking_meta updates in messages table.',
                    'url' => URL::temporarySignedRoute('mail-manager.ses-sns.site.command', now()->addMinutes(30), ['command' => 'test-delivery-db']),
                ],
                [
                    'label' => 'Test Bounce Event + DB Verify',
                    'description' => 'Send bounce simulator event and verify tracking_meta updates in messages table.',
                    'url' => URL::temporarySignedRoute('mail-manager.ses-sns.site.command', now()->addMinutes(30), ['command' => 'test-bounce-db']),
                ],
                [
                    'label' => 'Test Complaint Event + DB Verify',
                    'description' => 'Send complaint simulator event and verify tracking_meta updates in messages table.',
                    'url' => URL::temporarySignedRoute('mail-manager.ses-sns.site.command', now()->addMinutes(30), ['command' => 'test-complaint-db']),
                ],
                [
                    'label' => 'Teardown SES/SNS',
                    'description' => 'Remove SES/SNS tracking resources for cleanup.',
                    'url' => URL::temporarySignedRoute('mail-manager.ses-sns.site.command', now()->addMinutes(30), ['command' => 'teardown']),
                ],
            ],
            'custom_mail_action_url' => URL::temporarySignedRoute('mail-manager.ses-sns.site.custom-mail', now()->addMinutes(30)),
            'app_config' => [
                'aws_region' => (string) config('mail-manager.ses_sns.aws.region', ''),
                'aws_profile' => (string) config('mail-manager.ses_sns.aws.profile', ''),
                'sending_identity_domain' => (string) config('mail-manager.ses_sns.sending.identity_domain', ''),
                'sending_identity_email' => (string) config('mail-manager.ses_sns.sending.identity_email', ''),
                'sending_mail_from_domain' => (string) config('mail-manager.ses_sns.sending.mail_from_domain', ''),
                'tracking_configuration_set' => (string) config('mail-manager.ses_sns.configuration_set', ''),
                'tracking_event_destination' => (string) config('mail-manager.ses_sns.event_destination', ''),
                'tracking_topic_name' => (string) config('mail-manager.ses_sns.topic_name', ''),
                'tracking_topic_arn' => (string) config('mail-manager.ses_sns.topic_arn', ''),
                'tracking_tenant_name' => (string) config('mail-manager.ses_sns.tenant.name', ''),
                'tracking_callback_endpoint' => (string) config('mail-manager.ses_sns.callback_endpoint', ''),
                'tracking_event_types' => (array) config('mail-manager.ses_sns.event_types', []),
                'mail_default_mailer' => (string) config('mail.default', ''),
                'mail_from_address' => (string) config('mail.from.address', ''),
                'mail_from_name' => (string) config('mail.from.name', ''),
                'track_links' => (bool) config('mail-manager.tracking.track_links', false),
                'inject_pixel' => (bool) config('mail-manager.tracking.inject_pixel', false),
            ],
            'required_env' => [
                'AWS_DEFAULT_REGION',
                'AWS_ACCESS_KEY_ID',
                'AWS_SECRET_ACCESS_KEY',
                'MAIL_MAILER',
                'MAIL_FROM_ADDRESS',
                'MAIL_FROM_NAME',
            ],
        ]);
    }

    /**
     * @return array{
     *     enabled: bool,
     *     ok: bool|null,
     *     error: string|null,
     *     checks: array<int, array{key: string, label: string, ok: bool, details: string}>,
     *     dns_records: array<int, array{name: string, type: string, values: array<int, string>}>
     * }
     */
    protected function resolveSendingStatus(): array
    {
        try {
            $service = app(SesSendingSetupService::class);
            $status = $service->check();

            return [
                'enabled' => true,
                'ok' => (bool) ($status['ok'] ?? false),
                'error' => null,
                'checks' => (array) ($status['checks'] ?? []),
                'dns_records' => (array) ($status['dns_records'] ?? []),
            ];
        } catch (Throwable $e) {
            return [
                'enabled' => true,
                'ok' => false,
                'error' => $e->getMessage(),
                'checks' => [],
                'dns_records' => [],
            ];
        }
    }

    /**
     * @return array{
     *     enabled: bool,
     *     ok: bool|null,
     *     error: string|null,
     *     configuration: array<string, mixed>,
     *     checks: array<int, array{key: string, label: string, ok: bool, details: string}>,
     *     aws_console: array<string, string>
     * }
     */
    protected function resolveTrackingStatus(): array
    {
        try {
            $service = app(SesSnsSetupService::class);
            $status = $service->check();

            return [
                'enabled' => true,
                'ok' => (bool) ($status['ok'] ?? false),
                'error' => null,
                'configuration' => (array) ($status['configuration'] ?? []),
                'checks' => (array) ($status['checks'] ?? []),
                'aws_console' => (array) ($status['aws_console'] ?? []),
            ];
        } catch (Throwable $e) {
            return [
                'enabled' => true,
                'ok' => false,
                'error' => $e->getMessage(),
                'configuration' => [],
                'checks' => [],
                'aws_console' => [],
            ];
        }
    }
}
