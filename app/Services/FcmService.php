<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

/**
 * Push notifications to the Sale Agent app - admin approvals/rejections on
 * an agent's sales/payments, and (later) policy/commission announcements.
 * A missing token or a failed send is logged, never thrown - notifying an
 * agent is a nice-to-have on top of whatever admin action triggered it, not
 * something that should roll back or fail that action.
 */
class FcmService
{
    public function __construct(private Messaging $messaging)
    {
    }

    /**
     * `$data` values are read by the app to route a tapped notification
     * (see FcmService.dart's _handleTap) - keep keys/values in sync with
     * what that side expects, e.g. ['type' => 'sale_confirmed', 'sale_id' => $sale->id].
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        if (empty($user->fcm_token)) {
            return;
        }

        try {
            $message = CloudMessage::withTarget('token', $user->fcm_token)
                ->withNotification(Notification::create($title, $body))
                ->withData(array_map('strval', $data));

            $this->messaging->send($message);
        } catch (\Throwable $e) {
            Log::warning('FCM push failed', [
                'user_id' => $user->id,
                'title' => $title,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
