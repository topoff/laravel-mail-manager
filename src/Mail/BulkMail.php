<?php

namespace Topoff\MailManager\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Topoff\MailManager\Contracts\MessageReceiverInterface;

class BulkMail extends Mailable
{
    use Queueable, SerializesModels;

    public Collection $messages;

    public ?string $url = null;

    public function __construct(protected MessageReceiverInterface $messageReceiver, Collection $messages)
    {
        $this->messages = $messages;
    }

    public function build(): static
    {
        $urlResolver = config('mail-manager.mail.bulk_mail_url');
        if (is_callable($urlResolver)) {
            $this->url = $urlResolver($this->messageReceiver);
        }

        $subjectResolver = config('mail-manager.mail.bulk_mail_subject');
        if (is_callable($subjectResolver)) {
            $this->subject($subjectResolver($this->messageReceiver, $this->messages));
        } else {
            $this->subject($this->messages->count().' messages');
        }

        $view = config('mail-manager.mail.bulk_mail_view', 'mail-manager::bulkMail');

        return $this->markdown($view);
    }
}
