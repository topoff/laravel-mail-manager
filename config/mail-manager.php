<?php

return [
    'models' => [
        'message' => \Topoff\MailManager\Models\Message::class,
        'message_type' => \Topoff\MailManager\Models\MessageType::class,
    ],

    'database' => [
        'connection' => 'mysql',
    ],

    'cache' => [
        'tag' => 'messageType',
        'ttl' => 60 * 60 * 24 * 30,
    ],

    'mail' => [
        'default_bulk_mail_class' => \Topoff\MailManager\Mail\BulkMail::class,

        // View used by BulkMail. Override to use your own Blade template.
        'bulk_mail_view' => 'mail-manager::bulkMail',

        // Callable or null. Resolves the subject line for bulk mails.
        // Signature: fn(MessageReceiverInterface $receiver, Collection $messageGroup): string
        'bulk_mail_subject' => null,

        // Callable or null. Resolves the URL shown in bulk mails.
        // Signature: fn(MessageReceiverInterface $receiver): ?string
        'bulk_mail_url' => null,
    ],

    'sending' => [
        // Callable or null. When null, only sends in 'production'.
        // Signature: fn(): bool
        'check_should_send' => null,

        // Callable or null. When null, message creation is never prevented.
        // Signature: fn(string $receiverClass, int $receiverId): bool
        'prevent_create_message' => null,
    ],

    'bcc' => [
        // Callable or null. When null, BCC is always added when provided.
        // Signature: fn(): bool
        'check_should_add_bcc' => null,
    ],
];
