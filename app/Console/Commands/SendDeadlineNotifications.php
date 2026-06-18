<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendDeadlineNotifications extends Command
{
    protected $signature = 'notifications:send-deadline-reminders';

    protected $description = 'Invia promemoria per le scadenze iscrizioni imminenti.';

    public function handle(NotificationService $notifications): int
    {
        $count = $notifications->sendDeadlineReminders();
        $this->info("Notifiche inviate: {$count}");

        return self::SUCCESS;
    }
}
