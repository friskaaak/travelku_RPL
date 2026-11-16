<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminEnv = env('SEED_ADMIN_PASSWORD');
        $demoEnv = env('SEED_DEMO_PASSWORD');
        $adminPw = $adminEnv ?: Str::password(16);
        $demoPw = $demoEnv ?: Str::password(14);

        $accounts = [
            ['Admin TravelKita', 'admin@travelku.test', 'admin', $adminPw, (bool) $adminEnv, '081200000001'],
            ['Budi Santoso', 'budi@travelku.test', 'customer', $demoPw, (bool) $demoEnv, '081200000002'],
            ['Siti Rahayu', 'siti@travelku.test', 'customer', $demoPw, (bool) $demoEnv, '081200000003'],
            ['Agus Wijaya', 'agus@travelku.test', 'customer', $demoPw, (bool) $demoEnv, '081200000004'],
        ];

        $generated = [];

        foreach ($accounts as [$name, $email, $role, $password, $fromEnv, $phone]) {
            // Akun yang sudah ada tidak diubah (kata sandi tidak direset).
            if (User::where('email', $email)->exists()) {
                continue;
            }

            $user = new User();
            // Kata sandi di-hash otomatis oleh cast 'hashed' pada model User.
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'role' => $role,
                'password' => $password,
                'email_verified_at' => now(),
            ])->save();

            if (! $fromEnv) {
                $generated[$role] = $password;
            }
        }

        foreach ($generated as $role => $password) {
            $this->command?->warn("Kata sandi akun {$role} dibuat acak (hanya tampil sekali): {$password}");
        }

        $this->command?->info('Akun demo siap: admin@travelku.test, budi@, siti@, agus@ (domain travelku.test).');
    }
}