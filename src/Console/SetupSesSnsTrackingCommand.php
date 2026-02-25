<?php

namespace Topoff\MailManager\Console;

use Illuminate\Console\Command;
use Topoff\MailManager\Services\SesSns\SesSnsSetupService;
use Throwable;

class SetupSesSnsTrackingCommand extends Command
{
    protected $signature = 'mail-manager:ses-sns:setup';

    protected $description = 'Provision SES v2 configuration set + SNS destination + subscription for mail-manager tracking.';

    public function handle(SesSnsSetupService $service): int
    {
        try {
            $result = $service->setup();

            foreach ($result['steps'] as $step) {
                $icon = $step['ok'] ? 'OK' : 'FAIL';
                $this->line(sprintf('[%s] %s - %s', $icon, $step['label'], $step['details']));
            }

            if ($result['ok']) {
                $this->info('SES/SNS setup completed and checks are green.');

                return self::SUCCESS;
            }

            $this->warn('SES/SNS setup executed, but checks are not fully green.');

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('SES/SNS setup failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
