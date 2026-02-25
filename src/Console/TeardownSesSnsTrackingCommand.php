<?php

namespace Topoff\MailManager\Console;

use Illuminate\Console\Command;
use Topoff\MailManager\Services\SesSns\SesSnsSetupService;
use Throwable;

class TeardownSesSnsTrackingCommand extends Command
{
    protected $signature = 'mail-manager:ses-sns:teardown {--force : Skip confirmation prompt}';

    protected $description = 'Remove SES/SNS tracking resources created by mail-manager setup.';

    public function handle(SesSnsSetupService $service): int
    {
        if (! $this->option('force')) {
            $confirmed = $this->confirm('This will remove SES event destination/configuration set and SNS topic/subscription. Continue?', false);
            if (! $confirmed) {
                $this->warn('Aborted.');

                return self::SUCCESS;
            }
        }

        try {
            $result = $service->teardown();

            foreach ($result['steps'] as $step) {
                $icon = $step['ok'] ? 'OK' : 'FAIL';
                $this->line(sprintf('[%s] %s - %s', $icon, $step['label'], $step['details']));
            }

            $this->info('SES/SNS teardown completed.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('SES/SNS teardown failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}

