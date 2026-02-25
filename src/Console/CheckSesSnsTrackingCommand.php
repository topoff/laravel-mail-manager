<?php

namespace Topoff\MailManager\Console;

use Illuminate\Console\Command;
use Topoff\MailManager\Services\SesSns\SesSnsSetupService;
use Throwable;

class CheckSesSnsTrackingCommand extends Command
{
    protected $signature = 'mail-manager:ses-sns:check-tracking';

    protected $aliases = ['mail-manager:ses-sns:check'];

    protected $description = 'Check SES/SNS tracking provisioning state via AWS API.';

    public function handle(SesSnsSetupService $service): int
    {
        try {
            $status = $service->check();

            foreach ($status['checks'] as $check) {
                $icon = $check['ok'] ? 'OK' : 'FAIL';
                $this->line(sprintf('[%s] %s - %s', $icon, $check['label'], $check['details']));
            }

            if ($status['ok']) {
                $this->info('SES/SNS setup is valid.');

                return self::SUCCESS;
            }

            $this->warn('SES/SNS setup is incomplete.');

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('SES/SNS check failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
