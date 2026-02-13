<?php

use Topoff\MailManager\Repositories\MessageTypeRepository;

it('registers the package config', function () {
    expect(config('mail-manager'))->toBeArray()
        ->and(config('mail-manager.models.message'))->toBe(\Topoff\MailManager\Models\Message::class)
        ->and(config('mail-manager.models.message_type'))->toBe(\Topoff\MailManager\Models\MessageType::class);
});

it('registers MessageTypeRepository as singleton', function () {
    $instance1 = app(MessageTypeRepository::class);
    $instance2 = app(MessageTypeRepository::class);

    expect($instance1)->toBeInstanceOf(MessageTypeRepository::class)
        ->and($instance1)->toBe($instance2);
});

it('registers package views', function () {
    $viewFactory = app('view');
    expect($viewFactory->exists('mail-manager::bulkMail'))->toBeTrue();
});

it('runs the migration and creates tables', function () {
    expect(\Illuminate\Support\Facades\Schema::hasTable('message_types'))->toBeTrue()
        ->and(\Illuminate\Support\Facades\Schema::hasTable('messages'))->toBeTrue();
});
