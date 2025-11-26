<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Vérifie s’il n’existe pas déjà un admin
        if (!User::where('email', 'admin@gmail.com')->exists()) {
            User::create([
                'name' => 'TAKOUDA',
                'email' => 'admin@gmail.com',
                'password' => Hash::make('admin1234'),
                'role' => 'admin',
            ]);

            $this->command->info("Admin créé : admin@company.com / admin1234");
        } else {

            $this->command->warn("Un compte admin existe déjà.");
        }
    }
}
