<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect([
            'books_read',
            'book_delete',
            'book_edit',
        ])->mapWithKeys(function (string $permission) {
            return [
                $permission => Permission::query()->firstOrCreate([
                    'name' => $permission,
                    'guard_name' => 'web',
                ]),
            ];
        });

        $adminRole = Role::query()->firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $userRole = Role::query()->firstOrCreate([
            'name' => 'user',
            'guard_name' => 'web',
        ]);

        $adminRole->syncPermissions($permissions->values());
        $userRole->syncPermissions([$permissions['books_read']]);

        $admin = User::query()->updateOrCreate([
            'email' => 'admin@example.com',
        ], [
            'nome_utente' => 'admin',
            'nome' => 'Test',
            'cognome' => 'Admin',
            'password' => Hash::make('password12345'),
            'attivo' => true,
            'data_iscrizione' => now(),
        ]);

        $testUser = User::query()->updateOrCreate([
            'email' => 'user@example.com',
        ], [
            'nome_utente' => 'user',
            'nome' => 'Test',
            'cognome' => 'User',
            'password' => Hash::make('password123'),
            'attivo' => true,
            'data_iscrizione' => now(),
        ]);

        $admin->syncRoles([$adminRole]);
        $testUser->syncRoles([$userRole]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
