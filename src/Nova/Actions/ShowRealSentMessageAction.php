<?php

namespace Topoff\MailManager\Nova\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Topoff\MailManager\Models\Message;

class ShowRealSentMessageAction extends Action
{
    public $name = 'Show Real Sent Message';

    public function handle(ActionFields $fields, Collection $models): Action|ActionResponse|null
    {
        /** @var Message|null $message */
        $message = $models->first();
        if (! $message) {
            return Action::danger('No message selected.');
        }

        $url = URL::temporarySignedRoute(
            'mail-manager.tracking.nova.preview',
            now()->addMinutes(15),
            ['id' => $message->id]
        );

        return Action::openInNewTab($url);
    }

    /**
     * @return array<int, mixed>
     */
    public function fields(NovaRequest $request): array
    {
        return [];
    }
}
