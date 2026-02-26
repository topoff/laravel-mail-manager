<?php

namespace Topoff\MailManager\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\URL;
use Throwable;

class SesSnsNovaSiteCommandController extends Controller
{
    public function __invoke(Request $request, string $command): RedirectResponse
    {
        $commands = $this->commands();
        if (! array_key_exists($command, $commands)) {
            abort(403);
        }

        $definition = $commands[$command];

        try {
            $exitCode = Artisan::call($definition['command'], $definition['parameters']);
            $output = trim(Artisan::output());

            $result = [
                'command_key' => $command,
                'label' => $definition['label'],
                'command' => $definition['command'],
                'exit_code' => $exitCode,
                'ok' => $exitCode === 0,
                'output' => $output !== '' ? $output : '(no output)',
            ];
        } catch (Throwable $e) {
            $result = [
                'command_key' => $command,
                'label' => $definition['label'],
                'command' => $definition['command'],
                'exit_code' => 1,
                'ok' => false,
                'output' => $e->getMessage(),
            ];
        }

        return redirect()->to(URL::temporarySignedRoute('mail-manager.ses-sns.site', now()->addMinutes(30)))
            ->with('mail_manager_ses_sns_command_result', $result);
    }

    /**
     * @return array<string, array{
     *     label: string,
     *     command: string,
     *     parameters: array<string, bool>
     * }>
     */
    protected function commands(): array
    {
        return [
            'setup-sending' => [
                'label' => 'Setup SES Sending',
                'command' => 'mail-manager:ses-sns:setup-sending',
                'parameters' => ['--no-interaction' => true],
            ],
            'check-sending' => [
                'label' => 'Check SES Sending',
                'command' => 'mail-manager:ses-sns:check-sending',
                'parameters' => ['--no-interaction' => true],
            ],
            'setup-tracking' => [
                'label' => 'Setup SES/SNS Tracking',
                'command' => 'mail-manager:ses-sns:setup-tracking',
                'parameters' => ['--no-interaction' => true],
            ],
            'check-tracking' => [
                'label' => 'Check SES/SNS Tracking',
                'command' => 'mail-manager:ses-sns:check-tracking',
                'parameters' => ['--no-interaction' => true],
            ],
            'teardown' => [
                'label' => 'Teardown SES/SNS',
                'command' => 'mail-manager:ses-sns:teardown',
                'parameters' => ['--force' => true, '--no-interaction' => true],
            ],
        ];
    }
}
