<?php

namespace App\Http\Controllers;

use App\Models\ActivityNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $section = $request->query('section');

        $notifications = ActivityNotification::query()
            ->where('user_id', Auth::id())
            ->when($section, fn ($q) => $q->where('section', $section))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('notifications/index', [
            'notifications' => $notifications,
            'section' => $section,
        ]);
    }

    public function open(ActivityNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === Auth::id(), 403);

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return redirect($notification->url ?: '/notifications');
    }

    public function readAll(): RedirectResponse
    {
        ActivityNotification::query()
            ->where('user_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }
}
