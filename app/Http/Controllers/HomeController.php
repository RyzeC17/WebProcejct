<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventService;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(EventService $events): View
    {
        $events->syncExpiredEventsStatuses();

        return view('home.index', [
            'featuredEvents' => Event::query()
                ->where('status', Event::STATUS_PUBLISHED)
                ->withRegistrationCounts()
                ->orderBy('start_datetime')
                ->limit(3)
                ->get(),
        ]);
    }
}
