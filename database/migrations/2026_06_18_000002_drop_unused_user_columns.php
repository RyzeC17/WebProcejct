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
        $columns = array_values(array_filter([
            'email_verified_at',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'two_factor_confirmed_at',
            'remember_token',
            'current_team_id',
            'profile_photo_path',
        ], static fn (string $column): bool => Schema::hasColumn('utenti', $column)));

        if ($columns === []) {
            return;
        }

        Schema::table('utenti', static function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('utenti', static function (Blueprint $table): void {
            if (! Schema::hasColumn('utenti', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('email');
            }

            if (! Schema::hasColumn('utenti', 'two_factor_secret')) {
                $table->text('two_factor_secret')->nullable()->after('password');
            }

            if (! Schema::hasColumn('utenti', 'two_factor_recovery_codes')) {
                $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            }

            if (! Schema::hasColumn('utenti', 'two_factor_confirmed_at')) {
                $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            }

            if (! Schema::hasColumn('utenti', 'remember_token')) {
                $table->rememberToken()->after('two_factor_confirmed_at');
            }

            if (! Schema::hasColumn('utenti', 'current_team_id')) {
                $table->foreignId('current_team_id')->nullable()->after('remember_token');
            }

            if (! Schema::hasColumn('utenti', 'profile_photo_path')) {
                $table->string('profile_photo_path', 2048)->nullable()->after('current_team_id');
            }
        });
    }
};
