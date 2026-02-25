<?php

use Illuminate\Support\Facades\Route;
use Topoff\MailManager\Http\Controllers\MailTrackingNovaController;
use Topoff\MailManager\Http\Controllers\NovaCustomMessagePreviewController;
use Topoff\MailManager\Http\Controllers\NovaMailPreviewController;

$novaConfig = array_replace_recursive([
    'enabled' => true,
    'preview_route' => [
        'prefix' => 'email-manager/nova',
        'middleware' => ['web', 'signed'],
    ],
], (array) config('mail-manager.tracking.nova', []));

if ((bool) ($novaConfig['enabled'] ?? true)) {
    $previewRoute = $novaConfig['preview_route'] ?? [];
    Route::group($previewRoute, function (): void {
        Route::get('preview/{id}', [MailTrackingNovaController::class, 'preview'])->name('mail-manager.tracking.nova.preview');
        Route::get('preview-message/{message}', [NovaMailPreviewController::class, 'show'])->name('mail-manager.tracking.nova.preview-message');
    });
}

$customPreviewRoute = config('mail-manager.tracking.custom_preview_route', [
    'prefix' => 'email-manager/nova',
    'middleware' => ['web', 'signed'],
]);

Route::group($customPreviewRoute, function (): void {
    Route::get('custom-preview', [NovaCustomMessagePreviewController::class, 'show'])->name('mail-manager.tracking.nova.custom-preview');
});
