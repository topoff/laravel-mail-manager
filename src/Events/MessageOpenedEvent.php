<?php

namespace Topoff\MailManager\Events;

use Topoff\MailManager\Models\Message;

class MessageOpenedEvent
{
    public function __construct(public Message $message, public ?string $ipAddress) {}
}
