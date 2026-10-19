<?php

namespace App\Livewire\Patients;

use App\Models\Patient;
use App\Models\User;
use App\Services\FhirPatientService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

class Show extends Component
{
    #[Locked]
    public int $patientId;

    public bool $showPreview = false;

    /** @var array{success: bool, http_status: int|null, remote_resource_id: string|null, timestamp: string, message: string}|null */
    #[Locked]
    public ?array $transmission = null;

    public function mount(Patient $patient): void
    {
        abort_unless(auth()->check(), 403);
        $this->patientId = $patient->id;
    }

    public function togglePreview(): void
    {
        abort_unless(auth()->check(), 403);
        $this->showPreview = ! $this->showPreview;
    }

    public function send(FhirPatientService $service): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        $patient = Patient::findOrFail($this->patientId);
        try {
            $this->transmission = $service->send($patient, $user->id);
        } catch (Throwable $exception) {
            report($exception);
            $this->transmission = [
                'success' => false,
                'http_status' => null,
                'remote_resource_id' => null,
                'timestamp' => now()->toIso8601String(),
                'message' => 'The transmission could not be confirmed. Review the audit logs before trying again.',
            ];
        }
    }

    public function render(): View
    {
        abort_unless(auth()->check(), 403);
        $patient = Patient::findOrFail($this->patientId);

        return view('livewire.patients.show', [
            'patient' => $patient,
            'json' => $this->showPreview ? app(FhirPatientService::class)->json($patient) : null,
        ])->title('Patient Details / FHIR');
    }
}
