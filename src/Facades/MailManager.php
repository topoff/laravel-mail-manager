<?php

namespace Topoff\MailManager\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Topoff\MailManager\MailManager
 */
class MailManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Topoff\MailManager\MailManager::class;
    }
}
