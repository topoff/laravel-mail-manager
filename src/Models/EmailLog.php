<?php

namespace Topoff\MailManager\Models;

use Illuminate\Database\Eloquent\Model;
use Topoff\MailManager\Models\Traits\DateScopesTrait;

class EmailLog extends Model
{
    use DateScopesTrait;

    public $timestamps = false;

    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->table = (string) config('mail-manager.logs.email_log_table', 'email_log');

        $connection = config('mail-manager.logs.connection');
        if (is_string($connection) && $connection !== '') {
            $this->connection = $connection;
        }
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
