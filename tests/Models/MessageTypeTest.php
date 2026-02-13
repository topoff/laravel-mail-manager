<?php

use Topoff\MailManager\Models\MessageType;

it('can create a message type', function () {
    $messageType = createMessageType();

    expect($messageType)->toBeInstanceOf(MessageType::class)
        ->and($messageType->id)->toBeInt()
        ->and($messageType->mail_class)->toBe(\Workbench\App\Mail\TestMail::class)
        ->and($messageType->direct)->toBe(true)
        ->and($messageType->dev_bcc)->toBe(true);
});

it('uses the configured database connection', function () {
    config()->set('mail-manager.database.connection', 'custom');
    $messageType = new MessageType;

    expect($messageType->getConnectionName())->toBe('custom');
});

it('uses default connection when config is null', function () {
    config()->set('mail-manager.database.connection');
    $messageType = new MessageType;

    expect($messageType->getConnectionName())->toBeNull();
});

it('casts dev_bcc to boolean', function () {
    $messageType = createMessageType(['dev_bcc' => 1]);

    expect($messageType->dev_bcc)->toBeBool()->toBeTrue();

    $messageType = createMessageType(['dev_bcc' => 0]);

    expect($messageType->dev_bcc)->toBeBool()->toBeFalse();
});

it('scopes direct message types', function () {
    createMessageType(['mail_class' => 'Direct\\Mail', 'direct' => true]);
    createMessageType(['mail_class' => 'Indirect\\Mail', 'direct' => false]);

    $directTypes = MessageType::direct()->get();

    expect($directTypes)->toHaveCount(1)
        ->and($directTypes->first()->mail_class)->toBe('Direct\\Mail');
});

it('scopes customer message types', function () {
    createMessageType(['mail_class' => 'Customer\\Mail', 'customer' => true]);
    createMessageType(['mail_class' => 'Company\\Mail', 'customer' => false]);

    $customerTypes = MessageType::customer()->get();

    expect($customerTypes)->toHaveCount(1)
        ->and($customerTypes->first()->mail_class)->toBe('Customer\\Mail');
});

it('scopes company message types', function () {
    createMessageType(['mail_class' => 'Customer\\Mail', 'customer' => true]);
    createMessageType(['mail_class' => 'Company\\Mail', 'customer' => false]);

    $companyTypes = MessageType::company()->get();

    expect($companyTypes)->toHaveCount(1)
        ->and($companyTypes->first()->mail_class)->toBe('Company\\Mail');
});

it('supports soft deletes', function () {
    $messageType = createMessageType();
    $id = $messageType->id;

    $messageType->delete();

    expect(MessageType::find($id))->toBeNull()
        ->and(MessageType::withTrashed()->find($id))->not->toBeNull();
});
