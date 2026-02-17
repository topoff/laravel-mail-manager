<?php

namespace Topoff\MailManager\Events;

use Topoff\MailManager\Models\Message;

class MessageTransientBouncedEvent
{
    public function __construct(
        public string $recipientEmail,
        public string $bounceSubType,
        public string $diagnosticCode,
        public Message $message
    ) {}
}
