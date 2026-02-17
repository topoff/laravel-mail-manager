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
        // Signature: fn(MessageReceiverInterface $receiver, Collection $messages): string
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

    'tracking' => [
        // To disable the pixel injection, set this to false.
        'inject_pixel' => false,

        // To disable injecting tracking links, set this to false.
        'track_links' => false,

        // Where should the pingback URL route be?
        'route' => [
            'prefix' => 'email',
            'middleware' => ['api'],
        ],

        // Nova integration for browsing/troubleshooting tracked messages.
        'nova' => [
            // Set to false to disable package Nova integration.
            'enabled' => true,

            // Automatically register the configured Nova resource when Nova is installed.
            'register_resource' => false,

            // Override with your own resource class if needed.
            'resource' => \Topoff\MailManager\Nova\Resources\Message::class,

            // Signed preview route used by the Nova action.
            'preview_route' => [
                'prefix' => 'email-manager/nova',
                'middleware' => ['web', 'signed'],
            ],
        ],

        // If we get a link click without a URL, where should we send it to?
        'redirect_missing_links_to' => '/',

        // Determines whether the body of the email is logged in the messages table.
        'log_content' => true,

        // Can be either 'database' or 'filesystem'.
        'log_content_strategy' => 'database',

        // Filesystem disk used when log_content_strategy is filesystem.
        'tracker_filesystem' => null,
        'tracker_filesystem_folder' => 'mail-manager-tracker',

        // Queue used for tracking jobs. Null uses default queue.
        'tracker_queue' => null,

        // Max size for content when stored in database.
        'content_max_size' => 65535,

        // Optional: restrict SNS notifications to this topic ARN.
        'sns_topic' => null,
    ],
];
