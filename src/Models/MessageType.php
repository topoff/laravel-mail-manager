<?php

namespace Topoff\MailManager\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $mail_class
 * @property string|null $single_mail_handler
 * @property string|null $bulk_mail_handler
 * @property bool $direct
 * @property bool $dev_bcc
 * @property int $error_stop_send_minutes
 * @property bool $required_sender
 * @property bool $required_messagable
 * @property bool $required_company_id
 * @property bool $required_scheduled
 * @property bool $required_mail_text
 * @property bool $required_params
 * @property string|null $bulk_message_line
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class MessageType extends Model
{
    use SoftDeletes;

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
     * Scope a query to only include direct MessageTypes
     */
    public function scopeDirect(Builder $query): Builder
    {
        return $query->where('direct', true);
    }

    /**
     * Scope a query to only include direct MessageTypes
     */
    public function scopeCustomer(Builder $query): Builder
    {
        return $query->where('customer', true);
    }

    /**
     * Scope a query to only include direct MessageTypes
     */
    public function scopeCompany(Builder $query): Builder
    {
        return $query->where('customer', false);
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'dev_bcc' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
