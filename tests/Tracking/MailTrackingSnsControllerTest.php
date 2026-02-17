<?php

it('records delivery notifications via sns callback', function () {
    $message = createMessage([
        'tracking_message_id' => 'delivery-mid-1',
        'tracking_meta' => [],
    ]);

    $payload = [
        'Type' => 'Notification',
        'Message' => json_encode([
            'notificationType' => 'Delivery',
            'mail' => ['messageId' => 'delivery-mid-1'],
            'delivery' => [
                'smtpResponse' => '250 Ok',
                'timestamp' => '2026-01-01T00:00:00Z',
                'recipients' => ['receiver@example.com'],
            ],
        ]),
    ];

    $this->postJson(route('mail-manager.tracking.sns'), $payload)->assertOk();

    $message->refresh();

    expect(data_get($message->tracking_meta, 'success'))->toBeTrue()
        ->and(data_get($message->tracking_meta, 'smtpResponse'))->toBe('250 Ok');
});

it('records bounce notifications via sns callback', function () {
    $message = createMessage([
        'tracking_message_id' => 'bounce-mid-1',
        'tracking_meta' => [],
    ]);

    $payload = [
        'Type' => 'Notification',
        'Message' => json_encode([
            'notificationType' => 'Bounce',
            'mail' => ['messageId' => 'bounce-mid-1'],
            'bounce' => [
                'bounceType' => 'Permanent',
                'bounceSubType' => 'General',
                'bouncedRecipients' => [
                    ['emailAddress' => 'receiver@example.com', 'diagnosticCode' => '550 No such user'],
                ],
            ],
        ]),
    ];

    $this->postJson(route('mail-manager.tracking.sns'), $payload)->assertOk();

    $message->refresh();

    expect(data_get($message->tracking_meta, 'success'))->toBeFalse()
        ->and(data_get($message->tracking_meta, 'failures.0.emailAddress'))->toBe('receiver@example.com');
});

it('records complaint notifications via sns callback', function () {
    $message = createMessage([
        'tracking_message_id' => 'complaint-mid-1',
        'tracking_meta' => [],
    ]);

    $payload = [
        'Type' => 'Notification',
        'Message' => json_encode([
            'notificationType' => 'Complaint',
            'mail' => ['messageId' => 'complaint-mid-1'],
            'complaint' => [
                'timestamp' => '2026-01-01T01:00:00Z',
                'complaintFeedbackType' => 'abuse',
                'complainedRecipients' => [
                    ['emailAddress' => 'receiver@example.com'],
                ],
            ],
        ]),
    ];

    $this->postJson(route('mail-manager.tracking.sns'), $payload)->assertOk();

    $message->refresh();

    expect(data_get($message->tracking_meta, 'complaint'))->toBeTrue()
        ->and(data_get($message->tracking_meta, 'complaint_type'))->toBe('abuse');
});
