<?php

namespace Topoff\MailManager\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
    #[Scope]
    protected function direct(Builder $query): Builder
    {
        return $query->where('direct', true);
    }

    /**
     * Scope a query to only include direct MessageTypes
     */
    #[Scope]
    protected function customer(Builder $query): Builder
    {
        return $query->where('customer', true);
    }

    /**
     * Scope a query to only include direct MessageTypes
     */
    #[Scope]
    protected function company(Builder $query): Builder
    {
        return $query->where('customer', false);
    }

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
