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

    public function test_user_can_login_with_email(): void
    {
        User::factory()->create([
            'email' => 'grace@example.com',
            'nome_utente' => 'grace',
        ]);

        $this->post('/account/login/', [
            'username' => 'grace@example.com',
            'password' => 'password',
        ])->assertRedirect('/eventi');

        $this->assertAuthenticated();
    }

    public function test_public_event_api_keeps_response_shape(): void
    {
        $admin = User::factory()->admin()->create();
        $event = $this->event($admin, ['status' => Event::STATUS_PUBLISHED]);

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

    public function test_api_admin_routes_reject_regular_users(): void
    {
        $user = User::factory()->create();

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
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $event = $this->event($admin);

        $notification = app(NotificationService::class)->create(
            $user,
            Notification::TYPE_EVENT_UPDATED,
            'Messaggio di test',
            $event,
        );

        $this->assertFalse($notification->letta);
        $this->assertDatabaseHas('notifiche', ['id' => $notification->id, 'letta' => 0]);
    }

    public function test_registration_uses_active_then_waitlist_flow(): void
    {
        $admin = User::factory()->admin()->create();
        $event = $this->event($admin, ['max_participants' => 1, 'status' => Event::STATUS_PUBLISHED]);
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
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $event = $this->event($admin, [
            'status' => Event::STATUS_COMPLETED,
            'start_datetime' => now()->subDays(2),
            'end_datetime' => now()->subDay(),
            'registration_deadline' => now()->subDays(3),
        ]);
        Registration::query()->create([
            'evento_id' => $event->id,
            'utente_id' => $user->id,
            'stato' => Registration::STATUS_ACTIVE,
            'nota_partecipante' => '',
        ]);

        $this->actingAs($user)
            ->postJson("/eventi/{$event->slug}/feedback/", ['rating' => 6, 'comment' => 'No'])
            ->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_create_event_from_backoffice(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/eventi/gestione/eventi/nuovo/', [
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

        $this->assertDatabaseHas('eventi', ['titolo' => 'Nuovo laboratorio', 'stato' => Event::STATUS_PUBLISHED]);
        $this->assertDatabaseHas('campi_evento', ['etichetta' => 'Taglia maglietta']);
        $this->assertDatabaseHas('opzioni_campo', ['valore' => 'M']);
    }

    public function test_admin_can_create_event_without_custom_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/eventi/gestione/eventi/nuovo/', [
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

        $event = Event::query()->where('titolo', 'Evento senza campi custom')->firstOrFail();
        $this->assertSame(0, $event->customFields()->count());
    }

    public function test_deadline_reminders_are_scheduled_and_idempotent(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $event = $this->event($admin, [
            'status' => Event::STATUS_PUBLISHED,
            'start_datetime' => now()->addDays(2),
            'end_datetime' => now()->addDays(2)->addHours(2),
            'registration_deadline' => now()->addHours(12),
        ]);
        Registration::query()->create([
            'evento_id' => $event->id,
            'utente_id' => $user->id,
            'stato' => Registration::STATUS_ACTIVE,
            'nota_partecipante' => '',
        ]);

        Artisan::call('schedule:list');
        $this->assertStringContainsString('notifications:send-deadline-reminders', Artisan::output());

        $service = app(NotificationService::class);
        $this->assertSame(1, $service->sendDeadlineReminders());
        $this->assertSame(0, $service->sendDeadlineReminders());
        $this->assertSame(1, Notification::query()
            ->where('tipo_notifica', Notification::TYPE_REGISTRATION_DEADLINE_REMINDER)
            ->where('evento_id', $event->id)
            ->where('destinatario_id', $user->id)
            ->count());
    }

    public function test_event_registration_counts_can_be_preloaded(): void
    {
        $admin = User::factory()->admin()->create();
        $event = $this->event($admin, ['max_participants' => 2]);
        $activeUser = User::factory()->create();
        $waitlistedUser = User::factory()->create();
        $cancelledUser = User::factory()->create();

        Registration::query()->create(['evento_id' => $event->id, 'utente_id' => $activeUser->id, 'stato' => Registration::STATUS_ACTIVE, 'nota_partecipante' => '']);
        Registration::query()->create(['evento_id' => $event->id, 'utente_id' => $waitlistedUser->id, 'stato' => Registration::STATUS_WAITLISTED, 'nota_partecipante' => '']);
        Registration::query()->create(['evento_id' => $event->id, 'utente_id' => $cancelledUser->id, 'stato' => Registration::STATUS_CANCELLED, 'nota_partecipante' => '']);

        $loaded = Event::query()->withRegistrationCounts()->findOrFail($event->id);

        $this->assertSame(1, $loaded->active_registrations_count);
        $this->assertSame(1, $loaded->waitlisted_registrations_count);
        $this->assertSame(1, $loaded->remaining_seats);
    }

    private function event(User $admin, array $attributes = []): Event
    {
        return Event::query()->create(array_merge([
            'titolo' => 'Evento di test',
            'descrizione' => 'Descrizione di test',
            'nome_luogo' => 'Sala civica',
            'indirizzo_luogo' => 'Piazza Centrale 1',
            'note' => '',
            'max_partecipanti' => 10,
            'prezzo' => 0,
            'inizio_il' => now()->addWeeks(2),
            'fine_il' => now()->addWeeks(2)->addHours(2),
            'scadenza_iscrizioni' => now()->addWeek(),
            'tipo_evento' => Event::TYPE_SOCIALE,
            'stato' => Event::STATUS_PUBLISHED,
            'creato_da_id' => $admin->id,
        ], $this->eventColumns($attributes)));
    }

    private function eventColumns(array $attributes): array
    {
        $map = [
            'title' => 'titolo',
            'description' => 'descrizione',
            'venue_name' => 'nome_luogo',
            'venue_address' => 'indirizzo_luogo',
            'notes' => 'note',
            'max_participants' => 'max_partecipanti',
            'price' => 'prezzo',
            'start_datetime' => 'inizio_il',
            'end_datetime' => 'fine_il',
            'registration_deadline' => 'scadenza_iscrizioni',
            'event_type' => 'tipo_evento',
            'status' => 'stato',
            'created_by_id' => 'creato_da_id',
        ];

        foreach ($map as $english => $italian) {
            if (array_key_exists($english, $attributes)) {
                $attributes[$italian] = $attributes[$english];
                unset($attributes[$english]);
            }
        }

        return $attributes;
    }
}
