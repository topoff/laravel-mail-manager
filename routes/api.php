<?php

use Illuminate\Support\Facades\Route;
use Topoff\MailManager\Http\Controllers\MailTrackingController;
use Topoff\MailManager\Http\Controllers\MailTrackingSnsController;

$routeConfig = config('mail-manager.tracking.route', []);
Route::group($routeConfig, function (): void {
    Route::get('t/{hash}', [MailTrackingController::class, 'open'])->name('mail-manager.tracking.open');
    Route::get('n', [MailTrackingController::class, 'click'])->name('mail-manager.tracking.click')->middleware('signed');
    Route::post('sns', [MailTrackingSnsController::class, 'callback'])->name('mail-manager.tracking.sns');
});
