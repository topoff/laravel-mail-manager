<?php

namespace Topoff\MailManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Topoff\MailManager\MailHandler\MainMailHandler;

class ResendMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $messageId) {}

    public function handle(): void
    {
        $messageClass = config('mail-manager.models.message');
        $message = $messageClass::query()->with('messageType')->find($this->messageId);
        if (! $message) {
            return;
        }

        $handlerClass = $message->messageType?->single_mail_handler;
        if (! $handlerClass) {
            return;
        }

        /** @var MainMailHandler $mailHandler */
        $mailHandler = new $handlerClass($message);
        $mailHandler->send();
    }
}
