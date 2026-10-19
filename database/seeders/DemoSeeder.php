<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Demo Admin',
            'password' => 'Demo1234!',
            'email_verified_at' => now(),
        ]);

        Patient::firstOrCreate(['patient_number' => 'PT-DEMO-001'], [
            'first_name' => 'Juan',
            'middle_name' => null,
            'last_name' => 'Dela Cruz',
            'gender' => 'male',
            'birth_date' => '2001-07-16',
            'email' => 'juan.demo@example.com',
            'phone' => '09170000000',
            'address' => 'SYNTHETIC / TEST DATA ONLY',
            'active' => true,
        ]);
    }
}
