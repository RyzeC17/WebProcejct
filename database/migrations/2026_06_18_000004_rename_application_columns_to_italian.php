<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->renameMany('utenti', [
            'username' => 'nome_utente',
            'first_name' => 'nome',
            'last_name' => 'cognome',
            'is_active' => 'attivo',
            'last_login' => 'ultimo_accesso',
            'date_joined' => 'data_iscrizione',
        ]);

        $this->renameMany('eventi', [
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
            'created_at' => 'creato_il',
            'updated_at' => 'aggiornato_il',
        ]);

        $this->renameMany('iscrizioni', [
            'event_id' => 'evento_id',
            'user_id' => 'utente_id',
            'status' => 'stato',
            'attendee_note' => 'nota_partecipante',
            'created_at' => 'creato_il',
            'updated_at' => 'aggiornato_il',
            'cancelled_at' => 'annullata_il',
            'promoted_at' => 'promossa_il',
        ]);

        $this->renameMany('campi_evento', [
            'event_id' => 'evento_id',
            'label' => 'etichetta',
            'field_type' => 'tipo_campo',
            'is_required' => 'obbligatorio',
            'display_order' => 'ordine_visualizzazione',
            'created_at' => 'creato_il',
            'updated_at' => 'aggiornato_il',
        ]);

        $this->renameMany('opzioni_campo', [
            'field_id' => 'campo_id',
            'value' => 'valore',
            'display_order' => 'ordine_visualizzazione',
        ]);

        $this->renameMany('risposte_iscrizione', [
            'registration_id' => 'iscrizione_id',
            'field_id' => 'campo_id',
            'text_value' => 'valore_testo',
            'number_value' => 'valore_numero',
            'boolean_value' => 'valore_booleano',
            'selected_option_id' => 'opzione_selezionata_id',
            'created_at' => 'creato_il',
            'updated_at' => 'aggiornato_il',
        ]);

        $this->renameMany('modifiche_evento', [
            'event_id' => 'evento_id',
            'actor_id' => 'autore_id',
            'changed_fields' => 'campi_modificati',
            'created_at' => 'creato_il',
        ]);

        $this->renameMany('feedback_evento', [
            'event_id' => 'evento_id',
            'user_id' => 'utente_id',
            'registration_id' => 'iscrizione_id',
            'rating' => 'valutazione',
            'comment' => 'commento',
            'created_at' => 'creato_il',
            'updated_at' => 'aggiornato_il',
        ]);

        $this->renameMany('notifiche', [
            'recipient_id' => 'destinatario_id',
            'notification_type' => 'tipo_notifica',
            'text' => 'testo',
            'event_id' => 'evento_id',
            'registration_id' => 'iscrizione_id',
            'is_read' => 'letta',
            'created_at' => 'creato_il',
            'read_at' => 'letta_il',
        ]);
    }

    public function down(): void
    {
        $this->renameMany('notifiche', [
            'destinatario_id' => 'recipient_id',
            'tipo_notifica' => 'notification_type',
            'testo' => 'text',
            'evento_id' => 'event_id',
            'iscrizione_id' => 'registration_id',
            'letta' => 'is_read',
            'creato_il' => 'created_at',
            'letta_il' => 'read_at',
        ]);

        $this->renameMany('feedback_evento', [
            'evento_id' => 'event_id',
            'utente_id' => 'user_id',
            'iscrizione_id' => 'registration_id',
            'valutazione' => 'rating',
            'commento' => 'comment',
            'creato_il' => 'created_at',
            'aggiornato_il' => 'updated_at',
        ]);

        $this->renameMany('modifiche_evento', [
            'evento_id' => 'event_id',
            'autore_id' => 'actor_id',
            'campi_modificati' => 'changed_fields',
            'creato_il' => 'created_at',
        ]);

        $this->renameMany('risposte_iscrizione', [
            'iscrizione_id' => 'registration_id',
            'campo_id' => 'field_id',
            'valore_testo' => 'text_value',
            'valore_numero' => 'number_value',
            'valore_booleano' => 'boolean_value',
            'opzione_selezionata_id' => 'selected_option_id',
            'creato_il' => 'created_at',
            'aggiornato_il' => 'updated_at',
        ]);

        $this->renameMany('opzioni_campo', [
            'campo_id' => 'field_id',
            'valore' => 'value',
            'ordine_visualizzazione' => 'display_order',
        ]);

        $this->renameMany('campi_evento', [
            'evento_id' => 'event_id',
            'etichetta' => 'label',
            'tipo_campo' => 'field_type',
            'obbligatorio' => 'is_required',
            'ordine_visualizzazione' => 'display_order',
            'creato_il' => 'created_at',
            'aggiornato_il' => 'updated_at',
        ]);

        $this->renameMany('iscrizioni', [
            'evento_id' => 'event_id',
            'utente_id' => 'user_id',
            'stato' => 'status',
            'nota_partecipante' => 'attendee_note',
            'creato_il' => 'created_at',
            'aggiornato_il' => 'updated_at',
            'annullata_il' => 'cancelled_at',
            'promossa_il' => 'promoted_at',
        ]);

        $this->renameMany('eventi', [
            'titolo' => 'title',
            'descrizione' => 'description',
            'nome_luogo' => 'venue_name',
            'indirizzo_luogo' => 'venue_address',
            'note' => 'notes',
            'max_partecipanti' => 'max_participants',
            'prezzo' => 'price',
            'inizio_il' => 'start_datetime',
            'fine_il' => 'end_datetime',
            'scadenza_iscrizioni' => 'registration_deadline',
            'tipo_evento' => 'event_type',
            'stato' => 'status',
            'creato_da_id' => 'created_by_id',
            'creato_il' => 'created_at',
            'aggiornato_il' => 'updated_at',
        ]);

        $this->renameMany('utenti', [
            'nome_utente' => 'username',
            'nome' => 'first_name',
            'cognome' => 'last_name',
            'attivo' => 'is_active',
            'ultimo_accesso' => 'last_login',
            'data_iscrizione' => 'date_joined',
        ]);
    }

    /**
     * @param  array<string, string>  $columns
     */
    private function renameMany(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $from => $to) {
            if (Schema::hasColumn($table, $from) && ! Schema::hasColumn($table, $to)) {
                Schema::table($table, fn ($blueprint) => $blueprint->renameColumn($from, $to));
            }
        }
    }
};
