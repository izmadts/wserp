<?php

namespace App\Helpers;

use App\Models\CustomerNotification;

class NotificationHelper
{
    public static function send($customerId, $title, $message, $type = null, $link = null)
    {
        return CustomerNotification::create([
            'customer_id' => $customerId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'link' => $link,
        ]);
    }

    public static function sendBulk($customerIds, $title, $message, $type = null, $link = null)
    {
        $notifications = [];
        foreach ($customerIds as $customerId) {
            $notifications[] = [
                'customer_id' => $customerId,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'link' => $link,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        return CustomerNotification::insert($notifications);
    }

    public static function markAsRead($notificationId)
    {
        $notification = CustomerNotification::find($notificationId);
        if ($notification) {
            $notification->markAsRead();
        }
        return $notification;
    }

    public static function getUnreadCount($customerId)
    {
        return CustomerNotification::where('customer_id', $customerId)
            ->where('is_read', false)
            ->count();
    }
}
