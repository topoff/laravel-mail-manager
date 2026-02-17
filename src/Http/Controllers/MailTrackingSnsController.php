<?php

namespace Topoff\MailManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Topoff\MailManager\Jobs\RecordBounceJob;
use Topoff\MailManager\Jobs\RecordComplaintJob;
use Topoff\MailManager\Jobs\RecordDeliveryJob;

class MailTrackingSnsController extends Controller
{
    public function callback(Request $request): string
    {
        $payload = $request->json()->all();
        if ($payload === []) {
            $payload = json_decode((string) $request->getContent(), true) ?: [];
        }

        $type = $payload['Type'] ?? null;

        if ($type === 'SubscriptionConfirmation') {
            $subscribeUrl = $payload['SubscribeURL'] ?? null;
            if ($subscribeUrl) {
                Http::get($subscribeUrl);
            }

            return 'subscription confirmed';
        }

        if ($type !== 'Notification' || ! isset($payload['Message'])) {
            return 'invalid payload';
        }

        $message = is_array($payload['Message']) ? $payload['Message'] : (json_decode((string) $payload['Message'], true) ?: []);
        if (config('mail-manager.tracking.sns_topic') && ($payload['TopicArn'] ?? null) !== config('mail-manager.tracking.sns_topic')) {
            return 'invalid topic ARN';
        }

        $notificationType = $message['notificationType'] ?? null;
        match ($notificationType) {
            'Delivery' => RecordDeliveryJob::dispatch($message)->onQueue(config('mail-manager.tracking.tracker_queue')),
            'Bounce' => RecordBounceJob::dispatch($message)->onQueue(config('mail-manager.tracking.tracker_queue')),
            'Complaint' => RecordComplaintJob::dispatch($message)->onQueue(config('mail-manager.tracking.tracker_queue')),
            default => null,
        };

        return 'notification processed';
    }
}
