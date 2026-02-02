<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'hodo@gmail.com';
        $password = 'hodo1234';

        // On cherche l'utilisateur par son email
        $user = User::where('email', $email)->first();

        if (!$user) {
            // Création si l'utilisateur n'existe pas
            User::create([
                'name' => 'TAKOUDA',
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'admin', // Assurez-vous que la colonne 'role' existe dans votre table users
            ]);
            $this->command->info("Admin créé avec succès : {$email}");
        } else {
            // Mise à jour du mot de passe si l'utilisateur existe déjà
            $user->update([
                'password' => Hash::make($password),
                'name' => 'TAKOUDA',
                'role' => 'admin',
            ]);
            $this->command->info("Le compte {$email} existait déjà. Le mot de passe a été mis à jour.");
        }
    }
}