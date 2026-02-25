<?php

namespace Topoff\MailManager\Nova\Resources;

use Illuminate\Support\Str;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;
use Topoff\MailManager\Models\MessageType as MessageTypeModel;
use Topoff\MailManager\Nova\Actions\CheckSesSnsTrackingAction;
use Topoff\MailManager\Nova\Actions\PreviewMessageTypeInBrowserAction;
use Topoff\MailManager\Nova\Actions\SetupSesSnsTrackingAction;

class MessageType extends Resource
{
    public static $model = MessageTypeModel::class;

    public static $title = 'id';

    public static $group = 'Mail';

    public static $search = [
        'id',
        'mail_class',
    ];

    public static function label(): string
    {
        return 'Message Types';
    }

    public static function singularLabel(): string
    {
        return 'Message Type';
    }

    public function title(): string
    {
        return $this->id.' '.$this->mail_class;
    }

    /**
     * @return array<int, mixed>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Mail Class', 'mail_class')->sortable()->rules('required'),
            Text::make('Single Mail Handler', 'single_mail_handler')->nullable()->sortable(),
            Text::make('Bulk Mail Handler', 'bulk_mail_handler')->nullable()->sortable(),
            Boolean::make('Direct', 'direct')->sortable(),
            Boolean::make('Dev BCC', 'dev_bcc')->sortable(),
            Number::make('Error Stop Send Minutes', 'error_stop_send_minutes')->sortable(),
            Boolean::make('Required Sender', 'required_sender')->sortable(),
            Boolean::make('Required Messagable', 'required_messagable')->sortable(),
            Boolean::make('Required Company Id', 'required_company_id')->sortable(),
            Boolean::make('Required Scheduled', 'required_scheduled')->sortable(),
            Boolean::make('Required Mail Text', 'required_mail_text')->sortable(),
            Boolean::make('Required Params', 'required_params')->sortable(),
            Text::make('Bulk Message Line', 'bulk_message_line')
                ->displayUsing(fn (?string $text): string => Str::limit((string) $text, 120))
                ->hideFromIndex(),
            DateTime::make('Created At', 'created_at')->sortable()->hideFromIndex(),
            DateTime::make('Updated At', 'updated_at')->nullable()->hideFromIndex(),
            DateTime::make('Deleted At', 'deleted_at')->nullable()->hideFromIndex(),

            HasMany::make('Messages', 'messages', Message::class),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public function cards(NovaRequest $request): array
    {
        return [];
    }

    /**
     * @return array<int, mixed>
     */
    public function filters(NovaRequest $request): array
    {
        return [];
    }

    /**
     * @return array<int, mixed>
     */
    public function lenses(NovaRequest $request): array
    {
        return [];
    }

    /**
     * @return array<int, mixed>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            (new PreviewMessageTypeInBrowserAction)->sole()->confirmText('')->confirmButtonText('Preview'),
            (new CheckSesSnsTrackingAction)->standalone()->confirmText('Run SES/SNS status checks?')->confirmButtonText('Check'),
            (new SetupSesSnsTrackingAction)->standalone()->confirmText('Provision SES/SNS resources via AWS API and open status page?')->confirmButtonText('Setup'),
        ];
    }
}
