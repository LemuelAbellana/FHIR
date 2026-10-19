<?php

namespace App\Livewire\Patients;

use App\Models\Patient;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public function render(): View
    {
        abort_unless(auth()->check(), 403);

        return view('livewire.patients.index', ['patients' => Patient::latest('id')->paginate(15)])->title('Patients');
    }
}
