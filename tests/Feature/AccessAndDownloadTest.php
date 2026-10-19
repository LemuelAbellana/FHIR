<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use App\Services\FhirPatientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessAndDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_every_patient_and_audit_route(): void
    {
        foreach (['/', '/dashboard', '/patients', '/patients/create', '/patients/1', '/patients/1/fhir', '/audit-logs'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
    }

    public function test_authenticated_download_uses_selected_record_and_serializer(): void
    {
        $this->seed();
        $patient = Patient::where('patient_number', 'PT-DEMO-001')->firstOrFail();
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());

        $response = $this->get('/patients/'.$patient->id.'/fhir');

        $response->assertOk()->assertDownload('patient-PT-DEMO-001.fhir.json');
        $this->assertSame(app(FhirPatientService::class)->json($patient), $response->streamedContent());
        $this->assertStringContainsString('application/fhir+json', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }
}
