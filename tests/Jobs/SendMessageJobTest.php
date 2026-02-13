<?php

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;
use Topoff\MailManager\Jobs\SendMessageJob;
use Topoff\MailManager\Models\Message;
use Workbench\App\Models\TestMessagable;
use Workbench\App\Models\TestReceiver;

beforeEach(function () {
    Mail::fake();
    config()->set('mail-manager.sending.check_should_send', fn () => true);

    $this->receiver = createReceiver();
    $this->messagable = createMessagable();
});

it('sends direct messages that are ready', function () {
    $messageType = createMessageType(['direct' => true]);

    createMessage([
        'receiver_type' => TestReceiver::class,
        'receiver_id' => $this->receiver->id,
        'message_type_id' => $messageType->id,
        'messagable_type' => TestMessagable::class,
        'messagable_id' => $this->messagable->id,
    ]);

    $job = new SendMessageJob;
    $job->handle();

    $message = Message::first();
    expect($message->sent_at)->not->toBeNull();

    Mail::assertSent(\Workbench\App\Mail\TestMail::class);
});

it('does not send direct messages that are already sent', function () {
    $messageType = createMessageType(['direct' => true]);

    createMessage([
        'receiver_type' => TestReceiver::class,
        'receiver_id' => $this->receiver->id,
        'message_type_id' => $messageType->id,
        'messagable_type' => TestMessagable::class,
        'messagable_id' => $this->messagable->id,
        'sent_at' => now(),
    ]);

    $job = new SendMessageJob;
    $job->handle();

    Mail::assertNothingSent();
});

it('does not send direct messages that are reserved', function () {
    $messageType = createMessageType(['direct' => true]);

    createMessage([
        'receiver_type' => TestReceiver::class,
        'receiver_id' => $this->receiver->id,
        'message_type_id' => $messageType->id,
        'messagable_type' => TestMessagable::class,
        'messagable_id' => $this->messagable->id,
        'reserved_at' => now(),
    ]);

    $job = new SendMessageJob;
    $job->handle();

    Mail::assertNothingSent();
});

it('does not send direct messages that have errors', function () {
    $messageType = createMessageType(['direct' => true]);

    createMessage([
        'receiver_type' => TestReceiver::class,
        'receiver_id' => $this->receiver->id,
        'message_type_id' => $messageType->id,
        'messagable_type' => TestMessagable::class,
        'messagable_id' => $this->messagable->id,
        'error_at' => now(),
    ]);

    $job = new SendMessageJob;
    $job->handle();

    Mail::assertNothingSent();
});

it('does not send scheduled messages before their time', function () {
    $messageType = createMessageType(['direct' => true]);

    createMessage([
        'receiver_type' => TestReceiver::class,
        'receiver_id' => $this->receiver->id,
        'message_type_id' => $messageType->id,
        'messagable_type' => TestMessagable::class,
        'messagable_id' => $this->messagable->id,
        'scheduled_at' => now()->addHour(),
    ]);

    $job = new SendMessageJob;
    $job->handle();

    Mail::assertNothingSent();
});

it('sends scheduled messages that are due', function () {
    Date::setTestNow('2025-06-15 12:00:00');

    $messageType = createMessageType(['direct' => true]);

    createMessage([
        'receiver_type' => TestReceiver::class,
        'receiver_id' => $this->receiver->id,
        'message_type_id' => $messageType->id,
        'messagable_type' => TestMessagable::class,
        'messagable_id' => $this->messagable->id,
        'scheduled_at' => '2025-06-15 11:00:00',
    ]);

    $job = new SendMessageJob;
    $job->handle();

    Mail::assertSent(\Workbench\App\Mail\TestMail::class);
});

it('retries error messages when isRetryCall is true', function () {
    Date::setTestNow('2025-06-15 12:00:00');

    $messageType = createMessageType([
        'direct' => true,
        'error_stop_send_minutes' => 60 * 24,
    ]);

    createMessage([
        'receiver_type' => TestReceiver::class,
        'receiver_id' => $this->receiver->id,
        'message_type_id' => $messageType->id,
        'messagable_type' => TestMessagable::class,
        'messagable_id' => $this->messagable->id,
        'error_at' => now()->subHours(2),
        'reserved_at' => now()->subHours(2),
        'scheduled_at' => now()->subHours(1),
        'created_at' => now()->subMinutes(30),
    ]);

    $job = new SendMessageJob(true);
    $job->handle();

    Mail::assertSent(\Workbench\App\Mail\TestMail::class);
});

it('sends indirect messages as single when only one message per group', function () {
    $messageType = createMessageType(['direct' => false]);

    createMessage([
        'receiver_type' => TestReceiver::class,
        'receiver_id' => $this->receiver->id,
        'message_type_id' => $messageType->id,
        'messagable_type' => TestMessagable::class,
        'messagable_id' => $this->messagable->id,
    ]);

    $job = new SendMessageJob;
    $job->handle();

    // Single message goes through individual handler, not bulk
    Mail::assertSent(\Workbench\App\Mail\TestMail::class);
});

it('sends indirect messages as bulk when multiple messages per group', function () {
    $messageType = createMessageType(['direct' => false]);

    createMessage([
        'receiver_type' => TestReceiver::class,
        'receiver_id' => $this->receiver->id,
        'message_type_id' => $messageType->id,
        'messagable_type' => TestMessagable::class,
        'messagable_id' => $this->messagable->id,
    ]);

    createMessage([
        'receiver_type' => TestReceiver::class,
        'receiver_id' => $this->receiver->id,
        'message_type_id' => $messageType->id,
        'messagable_type' => TestMessagable::class,
        'messagable_id' => createMessagable(['title' => 'Second'])->id,
    ]);

    $job = new SendMessageJob;
    $job->handle();

    // Multiple messages for same receiver — should use BulkMail
    Mail::assertSent(\Topoff\MailManager\Mail\BulkMail::class);
});

it('has tries set to 1', function () {
    $job = new SendMessageJob;

    expect($job->tries)->toBe(1);
});

it('deletes indirect messages when receiver no longer exists', function () {
    $messageType = createMessageType(['direct' => false]);

    $receiver = createReceiver(['email' => 'gone@example.com']);

    $m1 = createMessage([
        'receiver_type' => TestReceiver::class,
        'receiver_id' => $receiver->id,
        'message_type_id' => $messageType->id,
        'messagable_type' => TestMessagable::class,
        'messagable_id' => $this->messagable->id,
    ]);

    $m2 = createMessage([
        'receiver_type' => TestReceiver::class,
        'receiver_id' => $receiver->id,
        'message_type_id' => $messageType->id,
        'messagable_type' => TestMessagable::class,
        'messagable_id' => createMessagable(['title' => 'Another'])->id,
    ]);

    // Delete the receiver
    $receiver->delete();

    $job = new SendMessageJob;
    $job->handle();

    expect(Message::withTrashed()->find($m1->id)->trashed())->toBeTrue()
        ->and(Message::withTrashed()->find($m2->id)->trashed())->toBeTrue();

    Mail::assertNothingSent();
});
