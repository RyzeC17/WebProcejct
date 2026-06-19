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
            $table->string('titolo', 255);
            $table->string('slug', 60)->unique();
            $table->text('descrizione');
            $table->string('nome_luogo', 255);
            $table->text('indirizzo_luogo');
            $table->text('note')->default('');
            $table->unsignedInteger('max_partecipanti');
            $table->decimal('prezzo', 8, 2)->default(0);
            $table->dateTime('inizio_il');
            $table->dateTime('fine_il');
            $table->dateTime('scadenza_iscrizioni');
            $table->string('tipo_evento', 30);
            $table->string('stato', 20)->default(Event::STATUS_DRAFT);
            $table->foreignId('creato_da_id')->constrained('utenti')->restrictOnDelete();
            $table->timestamp('creato_il')->nullable();
            $table->timestamp('aggiornato_il')->nullable();
            $table->index(['stato', 'inizio_il']);
        });

        Schema::create('iscrizioni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventi')->cascadeOnDelete();
            $table->foreignId('utente_id')->constrained('utenti')->cascadeOnDelete();
            $table->string('stato', 20)->default(Registration::STATUS_ACTIVE);
            $table->text('nota_partecipante')->default('');
            $table->timestamp('creato_il')->nullable();
            $table->timestamp('aggiornato_il')->nullable();
            $table->dateTime('annullata_il')->nullable();
            $table->dateTime('promossa_il')->nullable();
            $table->unique(['evento_id', 'utente_id'], 'unique_registration_per_event_user');
        });

        Schema::create('campi_evento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventi')->cascadeOnDelete();
            $table->string('etichetta', 120);
            $table->string('tipo_campo', 20)->default(EventCustomField::TYPE_TEXT);
            $table->boolean('obbligatorio')->default(false);
            $table->unsignedInteger('ordine_visualizzazione');
            $table->timestamp('creato_il')->nullable();
            $table->timestamp('aggiornato_il')->nullable();
            $table->unique(['evento_id', 'ordine_visualizzazione'], 'unique_custom_field_order_per_event');
        });

        Schema::create('opzioni_campo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campo_id')->constrained('campi_evento')->cascadeOnDelete();
            $table->string('valore', 120);
            $table->unsignedInteger('ordine_visualizzazione')->default(1);
            $table->unique(['campo_id', 'ordine_visualizzazione'], 'unique_option_order_per_field');
            $table->unique(['campo_id', 'valore'], 'unique_option_value_per_field');
        });

        Schema::create('risposte_iscrizione', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iscrizione_id')->constrained('iscrizioni')->cascadeOnDelete();
            $table->foreignId('campo_id')->constrained('campi_evento')->cascadeOnDelete();
            $table->text('valore_testo')->nullable();
            $table->decimal('valore_numero', 12, 4)->nullable();
            $table->boolean('valore_booleano')->nullable();
            $table->foreignId('opzione_selezionata_id')->nullable()->constrained('opzioni_campo')->nullOnDelete();
            $table->timestamp('creato_il')->nullable();
            $table->timestamp('aggiornato_il')->nullable();
            $table->unique(['iscrizione_id', 'campo_id'], 'unique_custom_answer_per_registration_field');
        });

        Schema::create('modifiche_evento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventi')->cascadeOnDelete();
            $table->foreignId('autore_id')->nullable()->constrained('utenti')->nullOnDelete();
            $table->json('campi_modificati');
            $table->dateTime('creato_il')->useCurrent();
        });

        Schema::create('feedback_evento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventi')->cascadeOnDelete();
            $table->foreignId('utente_id')->constrained('utenti')->cascadeOnDelete();
            $table->foreignId('iscrizione_id')->constrained('iscrizioni')->cascadeOnDelete();
            $table->unsignedTinyInteger('valutazione');
            $table->text('commento')->default('');
            $table->timestamp('creato_il')->nullable();
            $table->timestamp('aggiornato_il')->nullable();
            $table->unique(['evento_id', 'utente_id'], 'unique_feedback_per_event_user');
        });

        Schema::create('notifiche', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destinatario_id')->constrained('utenti')->cascadeOnDelete();
            $table->string('tipo_notifica', 40)->default(Notification::TYPE_REGISTRATION_CONFIRMED);
            $table->text('testo');
            $table->foreignId('evento_id')->nullable()->constrained('eventi')->nullOnDelete();
            $table->foreignId('iscrizione_id')->nullable()->constrained('iscrizioni')->nullOnDelete();
            $table->boolean('letta')->default(false);
            $table->dateTime('creato_il')->useCurrent();
            $table->dateTime('letta_il')->nullable();
            $table->index(['destinatario_id', 'letta', 'creato_il'], 'notification_summary_idx');
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
