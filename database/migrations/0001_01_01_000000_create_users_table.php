<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('utenti', function (Blueprint $table) {
            $table->id();
            $table->string('nome_utente', 150)->unique();
            $table->string('nome', 150)->default('');
            $table->string('cognome', 150)->default('');
            $table->string('email', 254)->unique();
            $table->string('password');
            $table->boolean('attivo')->default(true);
            $table->dateTime('ultimo_accesso')->nullable();
            $table->dateTime('data_iscrizione')->useCurrent();
        });

        Schema::create('token_recupero_password', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessioni', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index()->constrained('utenti')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessioni');
        Schema::dropIfExists('token_recupero_password');
        Schema::dropIfExists('utenti');
    }
};
