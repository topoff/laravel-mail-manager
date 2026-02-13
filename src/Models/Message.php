<?php

namespace Topoff\MailManager\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Date;
use Topoff\MailManager\Contracts\MessageReceiverInterface;
use Topoff\MailManager\Models\Traits\DateScopesTrait;

class Message extends Model
{
    use DateScopesTrait, SoftDeletes;

    public $timestamps = false;

    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if ($connection = config('mail-manager.database.connection')) {
            $this->connection = $connection;
        }
    }

    /**
     * Only MessageTypes with @see \Topoff\MailManager\Models\MessageType::scopeDirect()
     */
    public function directMessageTypes()
    {
        return $this->messageType()->direct();
    }

    /**
     * Only MessageTypes with @see \Topoff\MailManager\Models\MessageType::scopeCustomer()
     */
    public function customerMessageTypes()
    {
        return $this->messageType()->customer();
    }

    /**
     * Only MessageTypes with @see \Topoff\MailManager\Models\MessageType::scopeCompany()
     */
    public function companyMessageTypes()
    {
        return $this->messageType()->company();
    }

    public function messagable(): MorphTo
    {
        return $this->morphTo();
    }

    public function messageType(): BelongsTo
    {
        return $this->belongsTo(config('mail-manager.models.message_type'));
    }

    /**
     * @return MorphTo|Model|MessageReceiverInterface
     */
    public function receiver(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'receiver_type', 'receiver_id');
    }

    public function sender(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'sender_type', 'sender_id');
    }

    /**
     * Only Messages with real problems which couldn't be sent
     */
    #[Scope]
    protected function hasErrorAndIsNotSent(Builder $query): Builder
    {
        return $query->whereNotNull('error_at')->whereNull('sent_at');
    }

    /**
     * Only Messages with real problems which couldn't be sent
     */
    #[Scope]
    protected function isScheduledButNotSent(Builder $query): Builder
    {
        return $query->whereNotNull('scheduled_at')->whereNull('error_at')->whereNull('reserved_at');
    }

    /**
     * Get the date in the defined format according to the current language.
     */
    protected function dateFormated(): Attribute
    {
        return Attribute::make(get: fn (): string => ($this->created_at) ? Date::make($this->created_at)->isoFormat('LL') : '');
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'company_id' => 'integer',
            'email' => 'string',
            'message_type_id' => 'integer',
            'messagable_type' => 'string',
            'messagable_id' => 'integer',
            'params' => 'array',
            'text' => 'string',
            'deleted_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'reserved_at' => 'datetime',
            'error_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }
}
