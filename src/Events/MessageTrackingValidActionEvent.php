<?php

namespace Topoff\MailManager\Events;

use Topoff\MailManager\Models\Message;

class MessageTrackingValidActionEvent
{
    public bool $skip = false;

    public function __construct(public Message $message) {}
}
