<?php

namespace Topoff\MailManager\Console;

use Illuminate\Console\Command;
use Throwable;
use Topoff\MailManager\Services\SesSns\SesSendingSetupService;
use Topoff\MailManager\Services\SesSns\SesSnsSetupService;

class SetupSesSnsAllCommand extends Command
{
    protected $signature = 'mail-manager:ses-sns:setup-all';

    protected $description = 'Provision SES sending + SES/SNS tracking (one-shot setup).';

    public function handle(SesSendingSetupService $sendingService, SesSnsSetupService $trackingService): int
    {
        try {
            $sendingResult = $sendingService->setup();
            $trackingResult = $trackingService->setup();

            $this->info('Sending setup');
            foreach ((array) ($sendingResult['steps'] ?? []) as $step) {
                $icon = (bool) ($step['ok'] ?? false) ? 'OK' : 'FAIL';
                $this->line(sprintf('[%s] %s - %s', $icon, (string) ($step['label'] ?? ''), (string) ($step['details'] ?? '')));
            }

            $this->line('');
            $this->info('Tracking setup');
            foreach ((array) ($trackingResult['steps'] ?? []) as $step) {
                $icon = (bool) ($step['ok'] ?? false) ? 'OK' : 'FAIL';
                $this->line(sprintf('[%s] %s - %s', $icon, (string) ($step['label'] ?? ''), (string) ($step['details'] ?? '')));
            }

            $ok = (bool) ($sendingResult['ok'] ?? false) && (bool) ($trackingResult['ok'] ?? false);
            if (! $ok) {
                $this->warn('Setup completed, but one or more checks are not fully green.');

                return self::FAILURE;
            }

            $this->info('SES/SNS one-shot setup completed.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('SES/SNS one-shot setup failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}

