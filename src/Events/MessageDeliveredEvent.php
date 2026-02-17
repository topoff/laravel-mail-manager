<?php

namespace Topoff\MailManager\Events;

use Topoff\MailManager\Models\Message;

class MessageDeliveredEvent
{
    public function __construct(public string $recipientEmail, public Message $message) {}
}
