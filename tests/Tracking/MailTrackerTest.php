<?php

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage as IlluminateSentMessage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Topoff\MailManager\Tracking\MailTracker;

it('injects tracking pixel and links and persists tracking metadata on messageSending', function () {
    config()->set('mail-manager.tracking.inject_pixel', true);
    config()->set('mail-manager.tracking.track_links', true);
    config()->set('mail-manager.tracking.log_content', true);
    config()->set('mail-manager.tracking.log_content_strategy', 'database');

    $messageModel = createMessage();

    $email = (new Email)
        ->from(new Address('sender@example.com', 'Sender Name'))
        ->to(new Address('receiver@example.com', 'Receiver Name'))
        ->subject('Tracking Subject')
        ->html('<html><body><a href="https://example.com/path?x=1&amp;y=2">Test</a></body></html>');

    $event = new MessageSending($email, ['messageModel' => $messageModel]);

    app(MailTracker::class)->messageSending($event);

    $messageModel->refresh();

    expect($messageModel->tracking_hash)->not->toBeNull()
        ->and($messageModel->tracking_sender_email)->toBe('sender@example.com')
        ->and($messageModel->tracking_recipient_email)->toBe('receiver@example.com')
        ->and($messageModel->tracking_subject)->toBe('Tracking Subject')
        ->and($messageModel->tracking_opens)->toBe(0)
        ->and($messageModel->tracking_clicks)->toBe(0)
        ->and($messageModel->tracking_content)->toContain('https://example.com/path?x=1&amp;y=2');

    $body = $email->getBody()->getBody() ?? '';
    $expectedTrackedUrl = URL::signedRoute('mail-manager.tracking.click', [
        'l' => 'https://example.com/path?x=1&y=2',
        'h' => $messageModel->tracking_hash,
    ]);

    expect($body)
        ->toContain('/email/t/'.$messageModel->tracking_hash)
        ->toContain('/email/n?')
        ->toContain('h='.$messageModel->tracking_hash)
        ->toContain($expectedTrackedUrl)
        ->not->toContain('href="https://example.com/path?x=1&amp;y=2"');
});

it('writes tracking message id when message is sent', function () {
    $messageModel = createMessage(['tracking_hash' => 'testhash123']);

    $email = (new Email)
        ->from(new Address('sender@example.com', 'Sender Name'))
        ->to(new Address('receiver@example.com', 'Receiver Name'))
        ->subject('Tracking Subject')
        ->text('Plain text');

    $email->getHeaders()->addTextHeader('X-Mailer-Hash', 'testhash123');
    $email->getHeaders()->addTextHeader('X-SES-Message-ID', 'ses-message-id-123');

    $symfonySent = new SymfonySentMessage(
        $email,
        new Envelope(new Address('sender@example.com'), [new Address('receiver@example.com')])
    );

    $event = new MessageSent(new IlluminateSentMessage($symfonySent), []);

    app(MailTracker::class)->messageSent($event);

    $messageModel->refresh();

    expect($messageModel->tracking_message_id)->toBe('ses-message-id-123');
});

it('applies the same tracking hash to all grouped messages for bulk sends', function () {
    config()->set('mail-manager.tracking.inject_pixel', true);
    config()->set('mail-manager.tracking.track_links', true);

    $m1 = createMessage();
    $m2 = createMessage([
        'receiver_type' => $m1->receiver_type,
        'receiver_id' => $m1->receiver_id,
    ]);

    $email = (new Email)
        ->from(new Address('sender@example.com', 'Sender Name'))
        ->to(new Address('receiver@example.com', 'Receiver Name'))
        ->subject('Bulk Tracking Subject')
        ->html('<html><body><a href="https://example.com/bulk">Bulk</a></body></html>');

    $event = new MessageSending($email, ['messages' => collect([$m1, $m2])]);
    app(MailTracker::class)->messageSending($event);

    $m1->refresh();
    $m2->refresh();

    expect($m1->tracking_hash)->not->toBeNull()
        ->and($m2->tracking_hash)->toBe($m1->tracking_hash)
        ->and($m1->tracking_subject)->toBe('Bulk Tracking Subject')
        ->and($m2->tracking_subject)->toBe('Bulk Tracking Subject');
});
