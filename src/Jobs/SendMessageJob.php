<?php

namespace Topoff\MailManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Sleep;
use Throwable;
use Topoff\MailManager\MailHandler\MainBulkMailHandler;
use Topoff\MailManager\MailHandler\MainMailHandler;
use Topoff\MailManager\Models\Message;

class SendMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        /**
         * Only retry Messages with Error on this call
         */
        protected bool $isRetryCallForMessagesWithError = false
    ) {}

    /**
     * Execute the job.
     *
     * @throws Throwable
     */
    public function handle(): void
    {
        if ($this->isRetryCallForMessagesWithError) {
            $this->retryDirectMessages();
        } else {
            $this->sendDirectMessages();
            $this->sendIndirectMessages();
        }
    }

    protected function messageModel(): string
    {
        return config('mail-manager.models.message');
    }

    /**
     * Call the MailHandler for a single message
     */
    protected function callMailHandlerWithSingleMessage(Message $message): void
    {
        /** @var MainMailHandler $mailHandler or one of its child classes */
        $mailHandler = (new $message->messageType->single_mail_handler($message));
        $mailHandler->send();
    }

    /**
     * Send all direct messages
     */
    protected function sendDirectMessages(): void
    {
        $messageClass = $this->messageModel();
        $directMessages =
            $messageClass::with('messageType')
                ->has('directMessageTypes')
                ->whereNull('sent_at')
                ->whereNull('reserved_at')
                ->whereNull('error_at')
                ->where(fn ($query) => $query->whereNull('scheduled_at')->orWhere('scheduled_at', '<', Date::now()))
                ->get();

        foreach ($directMessages as $message) {
            $this->callMailHandlerWithSingleMessage($message);

            if (App::environment('staging')) {
                Sleep::sleep(1); // we can't send too many emails to mailtrap.io - 10 emails / 10 seconds
            }
        }
    }

    /**
     * Retry all direct messages, with were previously set to error or are
     * stuck in scheduled mode
     */
    protected function retryDirectMessages(): void
    {
        $messageClass = $this->messageModel();
        $directMessages =
            $messageClass::with('messageType')
                ->select('messages.*') // necessary because of join, otherwise it overwrites the id with the id from message_types
                ->join('message_types', 'messages.message_type_id', '=', 'message_types.id')
                ->has('directMessageTypes')
                ->whereNull('sent_at')
                ->where(fn ($query) => $query->whereNull('scheduled_at')->orWhere('scheduled_at', '<', Date::now()))
                ->where(fn ($query) => $query->whereNull('reserved_at')->orWhere('reserved_at', '<', Date::now()->subHour()))
                ->where(fn ($query) => $query->whereNull('error_at')->orWhere('error_at', '<', Date::now()->subHour()))
                ->whereRaw('messages.created_at > DATE_SUB(NOW(), INTERVAL message_types.error_stop_send_minutes MINUTE)');

        $directMessages->get()->each(fn (Message $message) => $this->callMailHandlerWithSingleMessage($message));
    }

    /**
     * Send all other messages, which or not of type direct
     * -> single & groupable|bulk messages
     */
    protected function sendIndirectMessages(): void
    {
        $messageClass = $this->messageModel();

        /**
         * Data Structure:
         * Collection(
         *          ['User::class' => Collection(
         *              ['783' => Collection(
         *                  ['MainBulkMailHandler' => Collection(
         *                      [1 => Collection(
         *                          [0 => Message(),
         *                           1 => Message(),
         *                           2 => Message(),
         *                           3 => Message(),
         *                      ]
         *                  ]
         *              ]
         *          ],
         *          ['Employee::class' => Collection(
         *              ['113' => Collection(
         *                  ['MainBulkMailHandler' => Collection(
         *                      [1 => Collection(
         *                          [0 => Message(),
         *                           1 => Message(),
         *                      ]
         *                  ]
         *              ]
         *          ]
         */
        if ($this->isRetryCallForMessagesWithError) {
            $individual =
                $messageClass::select('messages.*') // necessary because of join, otherwise it overwrites the id with the id from message_types
                    ->join('message_types', 'messages.message_type_id', '=', 'message_types.id')
                    ->where(fn ($query) => $query->whereNull('scheduled_at')->orWhere('scheduled_at', '<', Date::now()))
                    ->where(fn ($query) => $query->whereNull('reserved_at')->orWhere('reserved_at', '<', Date::now()->subHour()))
                    ->where(fn ($query) => $query->whereNull('error_at')->orWhere('error_at', '<', Date::now()->subHour()))
                    ->whereRaw('messages.created_at > DATE_SUB(NOW(), INTERVAL message_types.error_stop_send_minutes MINUTE)');
        } else {
            $individual =
                $messageClass::whereNull('reserved_at')
                    ->whereNull('error_at')
                    ->where(fn ($query) => $query->whereNull('scheduled_at')->orWhere('scheduled_at', '<', Date::now()));
        }

        $messageGroupsGroupedByReceiverType =
            $individual->with('messageType')
                ->doesntHave('directMessageTypes')
                ->whereNull('sent_at')
                ->get()
                ->groupBy(['receiver_type', 'receiver_id', 'messageType.bulk_mail_handler']);

        $messageGroupsGroupedByReceiverType->each(function (\Illuminate\Support\Collection $messageGroupsByReceiverId, $receiverType): void {
            $messageGroupsByReceiverId->each(function (Collection $messageGroupsByBulkMailHandler, $receiverId): void {
                $messageGroupsByBulkMailHandler->each(function (Collection $messageGroup, $bulkMailHandler) use ($receiverId): void {
                    if ($bulkMailHandler && $receiverId && $messageGroup->count() > 1) {
                        // in case an account meanwhile is deleted
                        if (! $messageGroup->first()->receiver) {
                            $messageGroup->each(function (Message $message): void {
                                $message->delete();
                            });
                        } else {
                            /** @var MainBulkMailHandler $bulkMailHandler */
                            (new $bulkMailHandler($messageGroup->first()->receiver, $messageGroup))->send();
                        }
                    } else {
                        $messageGroup->each(fn (Message $message) => $this->callMailHandlerWithSingleMessage($message));
                    }

                    if (App::environment('staging')) {
                        Sleep::sleep(1); // we can't send too many emails to mailtrip.io - 10 emails / 10 seconds
                    }
                });
            });
        });
    }
}
