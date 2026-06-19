<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->renameIfNeeded('password_reset_tokens', 'token_recupero_password');
        $this->renameIfNeeded('sessions', 'sessioni');
        $this->renameIfNeeded('cache', 'cache_applicazione');
        $this->renameIfNeeded('cache_locks', 'blocchi_cache');
        $this->renameIfNeeded('jobs', 'lavori');
        $this->renameIfNeeded('job_batches', 'lotti_lavori');
        $this->renameIfNeeded('failed_jobs', 'lavori_falliti');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->renameIfNeeded('lavori_falliti', 'failed_jobs');
        $this->renameIfNeeded('lotti_lavori', 'job_batches');
        $this->renameIfNeeded('lavori', 'jobs');
        $this->renameIfNeeded('blocchi_cache', 'cache_locks');
        $this->renameIfNeeded('cache_applicazione', 'cache');
        $this->renameIfNeeded('sessioni', 'sessions');
        $this->renameIfNeeded('token_recupero_password', 'password_reset_tokens');
    }

    private function renameIfNeeded(string $from, string $to): void
    {
        if (Schema::hasTable($from) && ! Schema::hasTable($to)) {
            Schema::rename($from, $to);
        }
    }
};
