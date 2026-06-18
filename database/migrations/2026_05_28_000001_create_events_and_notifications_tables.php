<?php

use App\Models\Event;
use App\Models\EventCustomField;
use App\Models\Notification;
use App\Models\Registration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventi', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('slug', 60)->unique();
            $table->text('description');
            $table->string('venue_name', 255);
            $table->text('venue_address');
            $table->text('notes')->default('');
            $table->unsignedInteger('max_participants');
            $table->decimal('price', 8, 2)->default(0);
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime');
            $table->dateTime('registration_deadline');
            $table->string('event_type', 30);
            $table->string('status', 20)->default(Event::STATUS_DRAFT);
            $table->foreignId('created_by_id')->constrained('utenti')->restrictOnDelete();
            $table->timestamps();
            $table->index(['status', 'start_datetime']);
        });

        Schema::create('iscrizioni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('eventi')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('utenti')->cascadeOnDelete();
            $table->string('status', 20)->default(Registration::STATUS_ACTIVE);
            $table->text('attendee_note')->default('');
            $table->timestamps();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('promoted_at')->nullable();
            $table->unique(['event_id', 'user_id'], 'unique_registration_per_event_user');
        });

        Schema::create('campi_evento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('eventi')->cascadeOnDelete();
            $table->string('label', 120);
            $table->string('field_type', 20)->default(EventCustomField::TYPE_TEXT);
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('display_order');
            $table->timestamps();
            $table->unique(['event_id', 'display_order'], 'unique_custom_field_order_per_event');
        });

        Schema::create('opzioni_campo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained('campi_evento')->cascadeOnDelete();
            $table->string('value', 120);
            $table->unsignedInteger('display_order')->default(1);
            $table->unique(['field_id', 'display_order'], 'unique_option_order_per_field');
            $table->unique(['field_id', 'value'], 'unique_option_value_per_field');
        });

        Schema::create('risposte_iscrizione', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('iscrizioni')->cascadeOnDelete();
            $table->foreignId('field_id')->constrained('campi_evento')->cascadeOnDelete();
            $table->text('text_value')->nullable();
            $table->decimal('number_value', 12, 4)->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->foreignId('selected_option_id')->nullable()->constrained('opzioni_campo')->nullOnDelete();
            $table->timestamps();
            $table->unique(['registration_id', 'field_id'], 'unique_custom_answer_per_registration_field');
        });

        Schema::create('modifiche_evento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('eventi')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('utenti')->nullOnDelete();
            $table->json('changed_fields');
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('feedback_evento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('eventi')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('utenti')->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained('iscrizioni')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->default('');
            $table->timestamps();
            $table->unique(['event_id', 'user_id'], 'unique_feedback_per_event_user');
        });

        Schema::create('notifiche', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_id')->constrained('utenti')->cascadeOnDelete();
            $table->string('notification_type', 40)->default(Notification::TYPE_REGISTRATION_CONFIRMED);
            $table->text('text');
            $table->foreignId('event_id')->nullable()->constrained('eventi')->nullOnDelete();
            $table->foreignId('registration_id')->nullable()->constrained('iscrizioni')->nullOnDelete();
            $table->boolean('is_read')->default(false);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('read_at')->nullable();
            $table->index(['recipient_id', 'is_read', 'created_at'], 'notification_summary_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifiche');
        Schema::dropIfExists('feedback_evento');
        Schema::dropIfExists('modifiche_evento');
        Schema::dropIfExists('risposte_iscrizione');
        Schema::dropIfExists('opzioni_campo');
        Schema::dropIfExists('campi_evento');
        Schema::dropIfExists('iscrizioni');
        Schema::dropIfExists('eventi');
    }
};
