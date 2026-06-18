<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Notification;
use App\Models\Registration;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class LaravelMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_login_and_logout(): void
    {
        $this->post('/account/register/', [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'username' => 'ada',
            'email' => 'ada@example.com',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
        ])->assertRedirect('/eventi');

        $this->assertAuthenticated();
        $this->post('/account/logout/')->assertRedirect('/');
        $this->assertGuest();

        $this->post('/account/login/', [
            'username' => 'ada',
            'password' => 'password-123',
        ])->assertRedirect('/eventi');
        $this->assertAuthenticated();
    }

    public function test_public_event_api_keeps_response_shape(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $event = $this->event($staff, ['status' => Event::STATUS_PUBLISHED]);

        $this->getJson('/api/v1/events/')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.id', $event->id)
            ->assertJsonStructure(['success', 'message', 'data' => ['items'], 'errors']);
    }

    public function test_api_errors_keep_response_shape(): void
    {
        $this->postJson('/api/v1/events/', [])
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'data', 'errors']);

        $this->getJson('/api/v1/events/999999/')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'data', 'errors']);
    }

    public function test_api_staff_routes_reject_non_staff_users(): void
    {
        $user = User::factory()->create(['is_staff' => false]);

        $this->actingAs($user)
            ->postJson('/api/v1/events/', [])
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_notification_panel_for_guest_returns_unauthorized_without_crashing(): void
    {
        $this->get('/notifiche/pannello/', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(401)
            ->assertSee('Non ci sono notifiche da mostrare.');
    }

    public function test_created_notifications_are_unread_by_default(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $user = User::factory()->create();
        $event = $this->event($staff);

        $notification = app(NotificationService::class)->create(
            $user,
            Notification::TYPE_EVENT_UPDATED,
            'Messaggio di test',
            $event,
        );

        $this->assertFalse($notification->is_read);
        $this->assertDatabaseHas('notifiche', ['id' => $notification->id, 'is_read' => 0]);
    }

    public function test_registration_uses_active_then_waitlist_flow(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $event = $this->event($staff, ['max_participants' => 1, 'status' => Event::STATUS_PUBLISHED]);
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first)
            ->postJson("/eventi/{$event->slug}/iscrizione/", ['attendee_note' => 'Prima adesione'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', Registration::STATUS_ACTIVE);

        $this->actingAs($second)
            ->postJson("/eventi/{$event->slug}/iscrizione/", ['attendee_note' => 'Seconda adesione'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', Registration::STATUS_WAITLISTED);
    }

    public function test_feedback_rating_is_validated(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $user = User::factory()->create();
        $event = $this->event($staff, [
            'status' => Event::STATUS_COMPLETED,
            'start_datetime' => now()->subDays(2),
            'end_datetime' => now()->subDay(),
            'registration_deadline' => now()->subDays(3),
        ]);
        Registration::query()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => Registration::STATUS_ACTIVE,
            'attendee_note' => '',
        ]);

        $this->actingAs($user)
            ->postJson("/eventi/{$event->slug}/feedback/", ['rating' => 6, 'comment' => 'No'])
            ->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_staff_can_create_event_from_backoffice(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);

        $this->actingAs($staff)->post('/eventi/gestione/eventi/nuovo/', [
            'title' => 'Nuovo laboratorio',
            'description' => 'Descrizione evento',
            'venue_name' => 'Aula magna',
            'venue_address' => 'Via Roma 1',
            'notes' => '',
            'max_participants' => 20,
            'price' => 0,
            'start_datetime' => now()->addMonth()->format('Y-m-d\TH:i'),
            'end_datetime' => now()->addMonth()->addHours(2)->format('Y-m-d\TH:i'),
            'registration_deadline' => now()->addMonth()->subDay()->format('Y-m-d\TH:i'),
            'event_type' => Event::TYPE_FORMAZIONE,
            'status' => Event::STATUS_PUBLISHED,
            'custom_fields' => [
                [
                    'label' => 'Taglia maglietta',
                    'field_type' => 'select',
                    'display_order' => 1,
                    'is_required' => 0,
                    'options_text' => "S\nM\nL",
                ],
            ],
        ])->assertRedirect('/eventi/gestione/eventi');

        $this->assertDatabaseHas('eventi', ['title' => 'Nuovo laboratorio', 'status' => Event::STATUS_PUBLISHED]);
        $this->assertDatabaseHas('campi_evento', ['label' => 'Taglia maglietta']);
        $this->assertDatabaseHas('opzioni_campo', ['value' => 'M']);
    }

    public function test_staff_can_create_event_without_custom_fields(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);

        $this->actingAs($staff)->post('/eventi/gestione/eventi/nuovo/', [
            'title' => 'Evento senza campi custom',
            'description' => 'Descrizione evento',
            'venue_name' => 'Sala civica',
            'venue_address' => 'Via Roma 2',
            'notes' => '',
            'max_participants' => 10,
            'price' => 0,
            'start_datetime' => now()->addMonth()->format('Y-m-d\TH:i'),
            'end_datetime' => now()->addMonth()->addHours(2)->format('Y-m-d\TH:i'),
            'registration_deadline' => now()->addMonth()->subDay()->format('Y-m-d\TH:i'),
            'event_type' => Event::TYPE_SOCIALE,
            'status' => Event::STATUS_PUBLISHED,
            'custom_fields' => [
                [
                    'label' => '',
                    'field_type' => 'text',
                    'display_order' => 1,
                    'is_required' => 0,
                    'options_text' => '',
                ],
            ],
        ])->assertRedirect('/eventi/gestione/eventi');

        $event = Event::query()->where('title', 'Evento senza campi custom')->firstOrFail();
        $this->assertSame(0, $event->customFields()->count());
    }

    public function test_deadline_reminders_are_scheduled_and_idempotent(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $user = User::factory()->create();
        $event = $this->event($staff, [
            'status' => Event::STATUS_PUBLISHED,
            'start_datetime' => now()->addDays(2),
            'end_datetime' => now()->addDays(2)->addHours(2),
            'registration_deadline' => now()->addHours(12),
        ]);
        Registration::query()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => Registration::STATUS_ACTIVE,
            'attendee_note' => '',
        ]);

        Artisan::call('schedule:list');
        $this->assertStringContainsString('notifications:send-deadline-reminders', Artisan::output());

        $service = app(NotificationService::class);
        $this->assertSame(1, $service->sendDeadlineReminders());
        $this->assertSame(0, $service->sendDeadlineReminders());
        $this->assertSame(1, Notification::query()
            ->where('notification_type', Notification::TYPE_REGISTRATION_DEADLINE_REMINDER)
            ->where('event_id', $event->id)
            ->where('recipient_id', $user->id)
            ->count());
    }

    public function test_event_registration_counts_can_be_preloaded(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $event = $this->event($staff, ['max_participants' => 2]);
        $activeUser = User::factory()->create();
        $waitlistedUser = User::factory()->create();
        $cancelledUser = User::factory()->create();

        Registration::query()->create(['event_id' => $event->id, 'user_id' => $activeUser->id, 'status' => Registration::STATUS_ACTIVE, 'attendee_note' => '']);
        Registration::query()->create(['event_id' => $event->id, 'user_id' => $waitlistedUser->id, 'status' => Registration::STATUS_WAITLISTED, 'attendee_note' => '']);
        Registration::query()->create(['event_id' => $event->id, 'user_id' => $cancelledUser->id, 'status' => Registration::STATUS_CANCELLED, 'attendee_note' => '']);

        $loaded = Event::query()->withRegistrationCounts()->findOrFail($event->id);

        $this->assertSame(1, $loaded->active_registrations_count);
        $this->assertSame(1, $loaded->waitlisted_registrations_count);
        $this->assertSame(1, $loaded->remaining_seats);
    }

    private function event(User $staff, array $attributes = []): Event
    {
        return Event::query()->create(array_merge([
            'title' => 'Evento di test',
            'description' => 'Descrizione di test',
            'venue_name' => 'Sala civica',
            'venue_address' => 'Piazza Centrale 1',
            'notes' => '',
            'max_participants' => 10,
            'price' => 0,
            'start_datetime' => now()->addWeeks(2),
            'end_datetime' => now()->addWeeks(2)->addHours(2),
            'registration_deadline' => now()->addWeek(),
            'event_type' => Event::TYPE_SOCIALE,
            'status' => Event::STATUS_PUBLISHED,
            'created_by_id' => $staff->id,
        ], $attributes));
    }
}
