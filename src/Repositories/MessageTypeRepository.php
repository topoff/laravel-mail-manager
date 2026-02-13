<?php

namespace Topoff\MailManager\Repositories;

use Illuminate\Support\Facades\Cache;
use Topoff\MailManager\Models\MessageType;

class MessageTypeRepository
{
    /**
     * Get the MessageType ID by a type
     */
    public function getIdFromTypeAndCustomer(string $type): int
    {
        $messageTypeClass = config('mail-manager.models.message_type');

        return Cache::tags(config('mail-manager.cache.tag'))->remember(
            static::class.':'.__FUNCTION__.':'.$type,
            config('mail-manager.cache.ttl'),
            fn () => $messageTypeClass::where('mail_class', $type)->select('id')->first()->id
        );
    }

    /**
     * Get the MessageType by a type
     */
    public function getFromTypeAndCustomer(string $type): MessageType
    {
        $messageTypeClass = config('mail-manager.models.message_type');

        return Cache::tags(config('mail-manager.cache.tag'))->remember(
            static::class.':'.__FUNCTION__.':'.$type,
            config('mail-manager.cache.ttl'),
            fn () => $messageTypeClass::where('mail_class', $type)->first()
        );
    }

    /**
     * Get the MessageType by ID
     */
    public function getFromId(int $id): MessageType
    {
        $messageTypeClass = config('mail-manager.models.message_type');

        return Cache::tags(config('mail-manager.cache.tag'))->remember(
            static::class.':'.__FUNCTION__.':'.$id,
            config('mail-manager.cache.ttl'),
            fn () => $messageTypeClass::where('id', $id)->first()
        );
    }
}
