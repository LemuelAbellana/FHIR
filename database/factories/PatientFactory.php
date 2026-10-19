<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Patient> */
class PatientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_number' => 'PT-TEST-'.fake()->unique()->numerify('########'),
            'first_name' => 'Synthetic',
            'middle_name' => null,
            'last_name' => 'Test Patient',
            'gender' => 'unknown',
            'birth_date' => '2001-07-16',
            'email' => 'synthetic@example.com',
            'phone' => '09170000000',
            'address' => 'SYNTHETIC / TEST DATA ONLY',
            'active' => true,
        ];
    }
}
