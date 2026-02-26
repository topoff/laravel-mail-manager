<?php

use Illuminate\Support\Facades\Route;
use Topoff\MailManager\Http\Controllers\MailTrackingNovaController;
use Topoff\MailManager\Http\Controllers\NovaCustomMessagePreviewController;
use Topoff\MailManager\Http\Controllers\NovaMailPreviewController;
use Topoff\MailManager\Http\Controllers\SesSnsNovaCustomMailActionController;
use Topoff\MailManager\Http\Controllers\SesSnsNovaSiteCommandController;
use Topoff\MailManager\Http\Controllers\SesSnsNovaSiteController;
use Topoff\MailManager\Http\Controllers\SesSnsSetupStatusController;

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
        Route::get('ses-sns-status', SesSnsSetupStatusController::class)->name('mail-manager.ses-sns.status');
        Route::get('ses-sns-site', SesSnsNovaSiteController::class)->name('mail-manager.ses-sns.site');
        Route::post('ses-sns-site/commands/{command}', SesSnsNovaSiteCommandController::class)->name('mail-manager.ses-sns.site.command');
        Route::get('ses-sns-site/custom-mail-action', [SesSnsNovaCustomMailActionController::class, 'show'])->name('mail-manager.ses-sns.site.custom-mail');
        Route::post('ses-sns-site/custom-mail-action/send', [SesSnsNovaCustomMailActionController::class, 'send'])->name('mail-manager.ses-sns.site.custom-mail.send');
        Route::post('ses-sns-site/custom-mail-action/preview', [SesSnsNovaCustomMailActionController::class, 'preview'])->name('mail-manager.ses-sns.site.custom-mail.preview');
    });
}

$customPreviewRoute = config('mail-manager.tracking.custom_preview_route', [
    'prefix' => 'email-manager/nova',
    'middleware' => ['web', 'signed'],
]);

Route::group($customPreviewRoute, function (): void {
    Route::get('custom-preview', [NovaCustomMessagePreviewController::class, 'show'])->name('mail-manager.tracking.nova.custom-preview');
});
