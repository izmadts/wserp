<?php

namespace App\Http\Controllers\Api\Agent;

use App\Models\AgentNotification;
use App\Http\Resources\Api\AgentNotificationResource;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    public function index(Request $request)
    {
        $query = AgentNotification::where('user_id', $this->agent()->id)->orderByDesc('created_at');
        $notifications = $query->paginate($request->input('per_page', 20));

        $unreadCount = AgentNotification::where('user_id', $this->agent()->id)->whereNull('read_at')->count();

        $items = $notifications->getCollection()->map(fn ($n) => new AgentNotificationResource($n))->values();

        return $this->success($items, 'OK', 200, [
            'current_page' => $notifications->currentPage(),
            'last_page' => $notifications->lastPage(),
            'per_page' => $notifications->perPage(),
            'total' => $notifications->total(),
            'summary' => ['unread_count' => $unreadCount],
        ]);
    }

    public function unreadCount()
    {
        $count = AgentNotification::where('user_id', $this->agent()->id)->whereNull('read_at')->count();

        return $this->success(['unread_count' => $count]);
    }

    public function markRead(AgentNotification $notification)
    {
        if ($notification->user_id !== $this->agent()->id) {
            return $this->error('Not found', 404);
        }

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return $this->success(null, 'Marked as read.');
    }

    public function markAllRead()
    {
        AgentNotification::where('user_id', $this->agent()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return $this->success(null, 'All notifications marked as read.');
    }
}
