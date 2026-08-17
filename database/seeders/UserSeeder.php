<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'botsekolahku@gmail.com'],
            [
                'name' => 'Admin Filament',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
            ]
        );
    }
}
