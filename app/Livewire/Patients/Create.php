<?php

namespace App\Livewire\Patients;

use App\Models\Patient;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Create extends Component
{
    public string $patient_number = '';

    public string $first_name = '';

    public string $middle_name = '';

    public string $last_name = '';

    public string $gender = '';

    public string $birth_date = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public bool $active = true;

    public bool $syntheticConfirmed = false;

    public function save(): void
    {
        abort_unless(auth()->check(), 403);
        $data = $this->validate([
            'patient_number' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:patients,patient_number'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', 'in:male,female,other,unknown'],
            'birth_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:1900-01-01'],
            'email' => ['nullable', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
            'active' => ['boolean'],
            'syntheticConfirmed' => ['accepted'],
        ], ['syntheticConfirmed.accepted' => 'Confirm that this record contains synthetic test data only.']);
        unset($data['syntheticConfirmed']);
        foreach (['middle_name', 'email', 'phone', 'address'] as $field) {
            $data[$field] = $data[$field] ?: null;
        }
        $patient = Patient::create($data);
        session()->flash('status', 'Synthetic patient created.');
        $this->redirectRoute('patients.show', ['patient' => $patient], navigate: true);
    }

    public function render(): View
    {
        abort_unless(auth()->check(), 403);

        return view('livewire.patients.create')->title('Add Patient');
    }
}
