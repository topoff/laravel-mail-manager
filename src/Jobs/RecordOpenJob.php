<?php

namespace Topoff\MailManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;
use Topoff\MailManager\Events\MessageOpenedEvent;
use Topoff\MailManager\Models\Message;

class RecordOpenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $maxExceptions = 3;

    public function __construct(
        public Message $message,
        public ?string $ipAddress
    ) {}

    public function retryUntil(): \Illuminate\Support\Carbon
    {
        return now()->addDays(5);
    }

    public function handle(): void
    {
        $this->message->increment('tracking_opens');

        Event::dispatch(new MessageOpenedEvent($this->message, $this->ipAddress));
    }
}
