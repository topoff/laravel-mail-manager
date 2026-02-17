<?php

namespace Topoff\MailManager\Events;

use Topoff\MailManager\Models\Message;

class MessageLinkClickedEvent
{
    public function __construct(public Message $message, public ?string $ipAddress, public string $url) {}
}
