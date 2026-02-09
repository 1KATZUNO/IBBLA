<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Usuario Admin
        User::create([
            'name' => 'Administrador',
            'email' => 'admin@ibbsc.com',
            'password' => Hash::make('admin123'),
            'rol' => 'admin',
        ]);

        // Usuario Tesorero
        User::create([
            'name' => 'Tesorero',
            'email' => 'tesorero@ibbsc.com',
            'password' => Hash::make('tesorero123'),
            'rol' => 'tesorero',
        ]);

        // Usuario General
        User::create([
            'name' => 'Usuario General',
            'email' => 'general@ibbsc.com',
            'password' => Hash::make('general123'),
            'rol' => 'general',
        ]);
    }
}
