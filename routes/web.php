<?php

use Illuminate\Support\Facades\Route;
use Topoff\MailManager\Http\Controllers\MailTrackingNovaController;
use Topoff\MailManager\Http\Controllers\NovaCustomMessagePreviewController;
use Topoff\MailManager\Http\Controllers\NovaMailPreviewController;
use Topoff\MailManager\Http\Controllers\SesSnsDashboardCommandController;
use Topoff\MailManager\Http\Controllers\SesSnsDashboardController;
use Topoff\MailManager\Http\Controllers\SesSnsDashboardCustomMailController;
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
        Route::get('ses-sns-dashboard', SesSnsDashboardController::class)->name('mail-manager.ses-sns.dashboard');
        Route::post('ses-sns-dashboard/commands/{command}', SesSnsDashboardCommandController::class)->name('mail-manager.ses-sns.dashboard.command');
        Route::get('ses-sns-dashboard/custom-mail-action', [SesSnsDashboardCustomMailController::class, 'show'])->name('mail-manager.ses-sns.dashboard.custom-mail');
        Route::post('ses-sns-dashboard/custom-mail-action/send', [SesSnsDashboardCustomMailController::class, 'send'])->name('mail-manager.ses-sns.dashboard.custom-mail.send');
        Route::post('ses-sns-dashboard/custom-mail-action/preview', [SesSnsDashboardCustomMailController::class, 'preview'])->name('mail-manager.ses-sns.dashboard.custom-mail.preview');
    });
}

$customPreviewRoute = config('mail-manager.tracking.custom_preview_route', [
    'prefix' => 'email-manager/nova',
    'middleware' => ['web', 'signed'],
]);

Route::group($customPreviewRoute, function (): void {
    Route::get('custom-preview', [NovaCustomMessagePreviewController::class, 'show'])->name('mail-manager.tracking.nova.custom-preview');
});
