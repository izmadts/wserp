<?php

namespace App\Services;

use App\Models\AgentNotification;
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
    /**
     * `$data` values are read by the app to route a tapped notification
     * (see FcmService.dart's _handleTap) - keep keys/values in sync with
     * what that side expects, e.g. ['type' => 'sale_confirmed', 'sale_id' => $sale->id].
     *
     * Always writes an [AgentNotification] row first - the in-app bell/
     * history list must show every admin action regardless of whether this
     * device ever had a token or the push actually reached it (offline,
     * uninstalled, etc.). The push itself is best-effort on top of that.
     *
     * Messaging is resolved from the container here rather than injected via
     * the constructor - a misconfigured/missing Firebase credential must
     * only skip the push (caught below), never break the admin action that
     * triggered it. Constructor injection would throw as soon as any
     * controller depending on this service is instantiated, before this
     * try/catch even runs.
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        try {
            AgentNotification::create([
                'user_id' => $user->id,
                'title' => $title,
                'body' => $body,
                'type' => $data['type'] ?? null,
                'sale_id' => $data['sale_id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('AgentNotification record failed', [
                'user_id' => $user->id,
                'title' => $title,
                'error' => $e->getMessage(),
            ]);
        }

        if (empty($user->fcm_token)) {
            return;
        }

        try {
            $message = CloudMessage::withTarget('token', $user->fcm_token)
                ->withNotification(Notification::create($title, $body))
                ->withData(array_map('strval', $data));

            app(Messaging::class)->send($message);
        } catch (\Throwable $e) {
            Log::warning('FCM push failed', [
                'user_id' => $user->id,
                'title' => $title,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
