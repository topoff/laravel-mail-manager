<?php

namespace Topoff\MailManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Topoff\MailManager\Events\MessageComplaintEvent;

class RecordComplaintJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $maxExceptions = 3;

    public function __construct(public array $message) {}

    public function retryUntil(): \Illuminate\Support\Carbon
    {
        return now()->addDays(5);
    }

    public function handle(): void
    {
        $messageId = data_get($this->message, 'mail.messageId');
        if (! $messageId) {
            Log::warning('RecordComplaintJob: Missing mail.messageId in SNS payload', ['payload_keys' => array_keys($this->message)]);

            return;
        }

        if (! data_get($this->message, 'complaint')) {
            Log::warning('RecordComplaintJob: Missing complaint data in SNS payload', ['messageId' => $messageId]);

            return;
        }

        $messageClass = config('mail-manager.models.message');
        $trackedMessages = $messageClass::query()->where('tracking_message_id', $messageId)->get();
        if ($trackedMessages->isEmpty()) {
            return;
        }

        $trackedMessages->each(function ($trackedMessage): void {
            $meta = collect($trackedMessage->tracking_meta ?: []);
            $meta->put('complaint', true);
            $meta->put('success', false);
            $meta->put('complaint_time', data_get($this->message, 'complaint.timestamp'));

            $feedbackType = data_get($this->message, 'complaint.complaintFeedbackType');
            if ($feedbackType) {
                $meta->put('complaint_type', $feedbackType);
            }

            $meta->put('sns_message_complaint', $this->message);
            $trackedMessage->tracking_meta = $meta->toArray();
            $trackedMessage->save();

            foreach ((array) data_get($this->message, 'complaint.complainedRecipients', []) as $recipient) {
                Event::dispatch(new MessageComplaintEvent((string) data_get($recipient, 'emailAddress', ''), $trackedMessage));
            }
        });
    }
}
