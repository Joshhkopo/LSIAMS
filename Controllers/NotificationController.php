<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Helpers\Auth;
use App\Models\Notification;

final class NotificationController extends Controller
{
    public function data(Request $request): never
    {
        $notifications = new Notification();
        $userId = (int) Auth::id();
        $this->ok('OK', [
            'rows'   => $notifications->forUser($userId),
            'unread' => $notifications->unreadCount($userId),
        ]);
    }

    public function markRead(Request $request): never
    {
        (new Notification())->markAllRead((int) Auth::id());
        $this->ok('Notifications marked as read.');
    }
}
