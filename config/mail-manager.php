<?php

return [
    'models' => [
        'message' => \Topoff\MailManager\Models\Message::class,
        'message_type' => \Topoff\MailManager\Models\MessageType::class,
    ],

    'database' => [
        'connection' => null,
    ],

    'cache' => [
        'tag' => 'messageType',
        'ttl' => 60 * 60 * 24 * 30,
    ],

    'mail' => [
        'default_bulk_mail_class' => null,
    ],

    'sending' => [
        // Callable or null. When null, only sends in 'production'.
        // Signature: fn(): bool
        'check_should_send' => null,
    ],

    'bcc' => [
        // Callable or null. When null, BCC is always added.
        // Signature: fn(): bool
        'check_should_add_bcc' => null,
    ],
];
