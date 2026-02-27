<?php

it('has default model classes configured', function () {
    expect(config('mail-manager.models.message'))->toBe(\Topoff\MailManager\Models\Message::class)
        ->and(config('mail-manager.models.message_type'))->toBe(\Topoff\MailManager\Models\MessageType::class);
});

it('allows overriding model classes', function () {
    config()->set('mail-manager.models.message', 'App\\Models\\CustomMessage');

    expect(config('mail-manager.models.message'))->toBe('App\\Models\\CustomMessage');
});

it('has default cache settings', function () {
    expect(config('mail-manager.cache.tag'))->toBe('messageType')
        ->and(config('mail-manager.cache.ttl'))->toBe(60 * 60 * 24 * 30);
});

it('has default bulk mail class configured', function () {
    expect(config('mail-manager.mail.default_bulk_mail_class'))->toBe(\Topoff\MailManager\Mail\BulkMail::class);
});

it('has default bulk mail view configured', function () {
    expect(config('mail-manager.mail.bulk_mail_view'))->toBe('mail-manager::bulkMail');
});

it('has null defaults for callable configs', function () {
    expect(config('mail-manager.mail.bulk_mail_subject'))->toBeNull()
        ->and(config('mail-manager.mail.bulk_mail_url'))->toBeNull()
        ->and(config('mail-manager.sending.check_should_send'))->toBeNull()
        ->and(config('mail-manager.sending.prevent_create_message'))->toBeNull()
        ->and(config('mail-manager.bcc.check_should_add_bcc'))->toBeNull();
});

it('has nova tracking defaults configured', function () {
    expect(config('mail-manager.tracking.nova.enabled'))->toBeTrue()
        ->and(config('mail-manager.tracking.nova.register_resource'))->toBeFalse()
        ->and(config('mail-manager.tracking.nova.resource'))->toBe(\Topoff\MailManager\Nova\Resources\Message::class)
        ->and(config('mail-manager.tracking.nova.preview_route.prefix'))->toBe('email-manager/nova');
});

it('has ses sns setup defaults configured', function () {
    expect(config('mail-manager.ses_sns.configuration_set'))->toBe('mail-manager-tracking')
        ->and(config('mail-manager.ses_sns.event_destination'))->toBe('mail-manager-sns')
        ->and(config('mail-manager.ses_sns.topic_name'))->toBe('mail-manager-ses-events')
        ->and(config('mail-manager.ses_sns.event_types'))->toBe(['SEND', 'REJECT', 'BOUNCE', 'COMPLAINT', 'DELIVERY'])
        ->and(config('mail-manager.ses_sns.tenant.name'))->toBeNull();
});
