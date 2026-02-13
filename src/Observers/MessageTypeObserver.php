<?php

namespace Topoff\MailManager\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class MessageTypeObserver
{
    /**
     * Handle the MessageType "created" event.
     */
    public function created(Model $messageType): void
    {
        $this->clearCachedMessageTypes();
    }

    /**
     * Handle the MessageType "updated" event.
     */
    public function updated(Model $messageType): void
    {
        $this->clearCachedMessageTypes();
    }

    /**
     * Handle the MessageType "deleted" event.
     */
    public function deleted(Model $messageType): void
    {
        $this->clearCachedMessageTypes();
    }

    /**
     * Handle the MessageType "restored" event.
     */
    public function restored(Model $messageType): void
    {
        $this->clearCachedMessageTypes();
    }

    /**
     * Handle the MessageType "force deleted" event.
     */
    public function forceDeleted(Model $messageType): void
    {
        $this->clearCachedMessageTypes();
    }

    /**
     * Removes all Cache entries with the MessageType Tag.
     */
    private function clearCachedMessageTypes(): void
    {
        $store = Cache::getStore();

        if ($store instanceof \Illuminate\Cache\TaggableStore) {
            Cache::tags([config('mail-manager.cache.tag')])->flush();
        } else {
            Cache::flush();
        }
    }
}
