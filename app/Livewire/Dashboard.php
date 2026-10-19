<?php

namespace App\Livewire;

use App\Models\AuditLog;
use App\Models\Patient;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    public function render(): View
    {
        abort_unless(auth()->check(), 403);

        return view('livewire.dashboard', [
            'patients' => Patient::count(),
            'successful' => AuditLog::where('action', 'FHIR_EXPORT_SUCCESS')->count(),
            'failed' => AuditLog::where('action', 'FHIR_EXPORT_FAILED')->count(),
            'latest' => AuditLog::whereIn('action', ['FHIR_EXPORT_SUCCESS', 'FHIR_EXPORT_FAILED'])->latest('id')->first(),
            'recentPatients' => Patient::latest('id')->limit(5)->get(),
        ])->title('Dashboard');
    }
}
