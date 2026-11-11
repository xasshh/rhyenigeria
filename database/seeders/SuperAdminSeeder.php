<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Creates the super admin from environment variables, so that no credentials
 * live in the repository:
 *
 *   SUPER_ADMIN_EMAIL=... SUPER_ADMIN_PASSWORD=... php artisan db:seed --class=SuperAdminSeeder --force
 *
 * Safe to re-run: an existing account keeps its password.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = strtolower(trim((string) env('SUPER_ADMIN_EMAIL')));
        $password = (string) env('SUPER_ADMIN_PASSWORD');
        $name = trim((string) env('SUPER_ADMIN_NAME')) ?: 'Super Admin';

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Set SUPER_ADMIN_EMAIL to a valid email address.');
        }

        $user = User::where('email', $email)->first();

        if ($user === null) {
            if (strlen($password) < 12) {
                throw new RuntimeException('Set SUPER_ADMIN_PASSWORD to a password of at least 12 characters.');
            }

            $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
            $user->email_verified_at = now();
            $created = true;
        }

        $user->role = Role::SuperAdmin;
        $user->is_active = true;
        $user->save();

        $this->command?->info(isset($created) ? "Super admin {$email} created." : "Super admin {$email} already exists; password left unchanged.");
    }
}
