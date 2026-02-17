<?php

namespace Topoff\MailManager\Nova\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Topoff\MailManager\Models\Message;
use Topoff\MailManager\Tracking\MessageResender;

class ResendMessageAction extends Action
{
    public $name = 'Resend Tracked Email';

    public function handle(ActionFields $fields, Collection $models): Action|ActionResponse|null
    {
        $queued = 0;
        $resender = app(MessageResender::class);

        foreach ($models as $message) {
            /** @var Message $message */
            $resender->resend($message);
            $queued++;
        }

        return Action::message(sprintf('%d tracked resend(s) queued.', $queued));
    }

    /**
     * @return array<int, mixed>
     */
    public function fields(NovaRequest $request): array
    {
        return [];
    }
}
