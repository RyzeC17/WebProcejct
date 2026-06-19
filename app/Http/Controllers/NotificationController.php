<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\ApiResponse;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request, NotificationService $notifications): View
    {
        $items = Notification::query()
            ->where('destinatario_id', $request->user()->id)
            ->with('event', 'registration')
            ->latest('creato_il')
            ->latest('id')
            ->paginate(20);

        $items->getCollection()->each(function (Notification $notification) use ($notifications, $request) {
            $notification->target_url = $notifications->targetUrl($notification, $request->user());
        });

        return view('notifications.index', [
            'notifications' => $items,
            'unreadCount' => Notification::query()->where('destinatario_id', $request->user()->id)->where('letta', false)->count(),
        ]);
    }

    public function panel(Request $request, NotificationService $notifications): View|Response
    {
        if (! $request->user()) {
            return response()->view('notifications.panel', ['notifications' => collect(), 'unreadCount' => 0], 401);
        }

        $items = Notification::query()
            ->where('destinatario_id', $request->user()->id)
            ->with('event', 'registration')
            ->latest('creato_il')
            ->limit(8)
            ->get();

        $items->each(function (Notification $notification) use ($notifications, $request) {
            $notification->target_url = $notifications->targetUrl($notification, $request->user());
        });

        return view('notifications.panel', [
            'notifications' => $items,
            'unreadCount' => Notification::query()->where('destinatario_id', $request->user()->id)->where('letta', false)->count(),
        ]);
    }

    public function summary(Request $request, NotificationService $notifications): JsonResponse
    {
        if (! $request->user()) {
            return ApiResponse::json('Autenticazione richiesta.', false, [], [], 401);
        }

        $query = Notification::query()->where('destinatario_id', $request->user()->id)->with('event', 'registration');
        $latest = (clone $query)->latest('creato_il')->limit(5)->get();

        return ApiResponse::json('Riepilogo notifiche recuperato.', true, [
            'unread_count' => (clone $query)->where('letta', false)->count(),
            'latest' => $latest->map(fn (Notification $notification) => $notifications->serialize($notification, $request->user()))->all(),
        ]);
    }

    public function markRead(int $id, Request $request): JsonResponse
    {
        $notification = Notification::query()
            ->where('destinatario_id', $request->user()->id)
            ->findOrFail($id);
        $notification->markAsRead();

        return ApiResponse::json('Notifica segnata come letta.', true, [
            'id' => $notification->id,
            'unread_count' => Notification::query()->where('destinatario_id', $request->user()->id)->where('letta', false)->count(),
        ]);
    }

    public function markAllRead(Request $request, NotificationService $notifications): JsonResponse
    {
        return ApiResponse::json('Tutte le notifiche sono state segnate come lette.', true, [
            'updated_count' => $notifications->markAllAsRead($request->user()),
            'unread_count' => 0,
        ]);
    }
}
