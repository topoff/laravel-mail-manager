<?php

use Illuminate\Support\Facades\URL;

it('executes an allowed ses sns dashboard command and flashes the result', function () {
    config()->set('mail-manager.ses_sns.enabled', false);

    $url = URL::temporarySignedRoute('mail-manager.ses-sns.dashboard.command', now()->addMinutes(10), ['command' => 'check-tracking']);

    $response = $this->post($url);

    $response
        ->assertRedirect()
        ->assertSessionHas('mail_manager_ses_sns_command_result.command_key', 'check-tracking')
        ->assertSessionHas('mail_manager_ses_sns_command_result.command', 'mail-manager:ses-sns:check-tracking');
});

it('forbids unknown ses sns dashboard commands', function () {
    $url = URL::temporarySignedRoute('mail-manager.ses-sns.dashboard.command', now()->addMinutes(10), ['command' => 'unknown-command']);

    $this->post($url)->assertForbidden();
});
