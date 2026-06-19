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
                ->where('stato', Event::STATUS_PUBLISHED)
                ->withRegistrationCounts()
                ->orderBy('inizio_il')
                ->limit(3)
                ->get(),
        ]);
    }
}
