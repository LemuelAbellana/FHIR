<?php

namespace Tests\Feature;

use App\Livewire\Patients\Create;
use App\Livewire\Patients\Show;
use App\Models\Patient;
use App\Models\User;
use App\Services\FhirPatientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class PatientUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_screens_render_with_application_navigation(): void
    {
        $this->actingAs(User::factory()->create());
        $patient = Patient::create(['patient_number' => 'PT-TEST-001', 'first_name' => 'Synthetic', 'last_name' => 'Example', 'gender' => 'unknown', 'birth_date' => '2000-01-01', 'active' => true]);

        foreach (['dashboard', 'patients.index', 'patients.create', 'audit-logs.index'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('FHIR Patient Demo')->assertSee('Log out');
        }

        $this->get(route('patients.show', $patient))->assertOk()->assertSee('Send to FHIR Server');
    }

    public function test_creation_requires_synthetic_acknowledgement(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Create::class)
            ->set('patient_number', 'PT-TEST-002')->set('first_name', 'Synthetic')
            ->set('last_name', 'Patient')->set('gender', 'unknown')->set('birth_date', '2000-01-01')
            ->call('save')->assertHasErrors(['syntheticConfirmed' => 'accepted'])
            ->set('syntheticConfirmed', true)->call('save')->assertHasNoErrors();

        $this->assertDatabaseHas('patients', ['patient_number' => 'PT-TEST-002']);
    }

    public function test_details_preview_and_transmission_use_selected_record(): void
    {
        Http::fake(['*' => Http::response(['resourceType' => 'Patient', 'id' => 'remote-123'], 201)]);
        $patient = Patient::create(['patient_number' => 'PT-TEST-003', 'first_name' => 'Synthetic', 'last_name' => 'Example', 'gender' => 'female', 'birth_date' => '2000-01-01', 'active' => true]);

        Livewire::actingAs(User::factory()->create())->test(Show::class, ['patient' => $patient])
            ->assertSee('Synthetic Test Data')->assertDontSee('"resourceType"')
            ->call('togglePreview')->assertSee('PT-TEST-003')->assertSee('resourceType')
            ->call('send')->assertSee('Transmission successful')->assertSee('remote-123');
    }

    public function test_selected_patient_cannot_be_changed_from_the_client(): void
    {
        $patient = Patient::create(['patient_number' => 'PT-TEST-004', 'first_name' => 'Synthetic', 'last_name' => 'Example', 'gender' => 'unknown', 'birth_date' => '2000-01-01', 'active' => true]);
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs(User::factory()->create())->test(Show::class, ['patient' => $patient])
            ->set('patientId', $patient->id + 1);
    }

    public function test_failed_transmission_is_visible_without_remote_response_details(): void
    {
        Http::fake(['*' => Http::response('SECRET REMOTE RESPONSE', 500)]);
        $patient = Patient::create(['patient_number' => 'PT-TEST-005', 'first_name' => 'Synthetic', 'last_name' => 'Example', 'gender' => 'unknown', 'birth_date' => '2000-01-01', 'active' => true]);

        Livewire::actingAs(User::factory()->create())->test(Show::class, ['patient' => $patient])
            ->call('send')->assertSee('Transmission failed')->assertSee('500')->assertDontSee('SECRET REMOTE RESPONSE');
    }

    public function test_unexpected_transmission_failure_shows_safe_feedback(): void
    {
        $patient = Patient::create(['patient_number' => 'PT-TEST-006', 'first_name' => 'Synthetic', 'last_name' => 'Example', 'gender' => 'unknown', 'birth_date' => '2000-01-01', 'active' => true]);
        $this->mock(FhirPatientService::class, function ($mock): void {
            $mock->shouldReceive('send')->once()->andThrow(new \RuntimeException('SECRET DATABASE DETAIL'));
        });

        Livewire::actingAs(User::factory()->create())->test(Show::class, ['patient' => $patient])
            ->call('send')->assertSee('Transmission failed')->assertSee('could not be confirmed')
            ->assertDontSee('SECRET DATABASE DETAIL');
    }
}
