<?php

namespace Tests\Feature;

use App\Livewire\Patients\Create;
use App\Livewire\Patients\Show;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class IntegrationAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_credentials_allow_access_to_the_complete_demo(): void
    {
        $this->seed();

        $this->post(route('login.store'), [
            'email' => 'admin@example.com',
            'password' => 'Demo1234!',
        ])->assertSessionHasNoErrors()->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs(User::where('email', 'admin@example.com')->firstOrFail());
        $patient = Patient::where('patient_number', 'PT-DEMO-001')->firstOrFail();

        foreach (['/dashboard', '/patients', '/patients/create', '/patients/'.$patient->id, '/audit-logs'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_unknown_patient_details_and_download_return_not_found(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/patients/999999')->assertNotFound();
        $this->get('/patients/999999/fhir')->assertNotFound();
    }

    public function test_download_reflects_database_changes_instead_of_the_seed_sample(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());
        $patient = Patient::where('patient_number', 'PT-DEMO-001')->firstOrFail();
        $url = route('patients.fhir.download', $patient);
        $before = json_decode($this->get($url)->assertOk()->streamedContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['Juan'], $before['name'][0]['given']);

        $patient->update(['first_name' => 'Updated Synthetic', 'gender' => 'other', 'active' => false]);
        $after = json_decode($this->get($url)->assertOk()->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(['Updated Synthetic'], $after['name'][0]['given']);
        $this->assertSame('other', $after['gender']);
        $this->assertFalse($after['active']);
    }

    public function test_invalid_patient_input_is_rejected_without_creating_a_record(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Create::class)
            ->set('patient_number', '../unsafe')
            ->set('first_name', 'Synthetic')
            ->set('last_name', 'Example')
            ->set('gender', 'invalid')
            ->set('birth_date', now()->addDay()->format('Y-m-d'))
            ->set('email', 'invalid-email')
            ->set('syntheticConfirmed', true)
            ->call('save')
            ->assertHasErrors(['patient_number', 'gender', 'birth_date', 'email']);

        $this->assertDatabaseCount('patients', 0);
    }

    public function test_transmission_metadata_is_visible_in_the_read_only_audit_screen(): void
    {
        $this->seed();
        $user = User::where('email', 'admin@example.com')->firstOrFail();
        $patient = Patient::where('patient_number', 'PT-DEMO-001')->firstOrFail();
        config(['fhir.base_url' => 'https://example.test/baseR4']);
        Http::fake(['https://example.test/baseR4/Patient' => Http::response(['resourceType' => 'Patient', 'id' => 'accepted-42'], 201)]);

        Livewire::actingAs($user)->test(Show::class, ['patient' => $patient])->call('send')->assertHasNoErrors();

        $result = AuditLog::where('action', 'FHIR_EXPORT_SUCCESS')->firstOrFail();
        $this->get(route('audit-logs.index'))->assertOk()
            ->assertSee('Read-only')->assertSee('FHIR_EXPORT_ATTEMPT')->assertSee('FHIR_EXPORT_SUCCESS')
            ->assertSee('201')->assertSee('accepted-42')->assertSee('https://example.test/baseR4/Patient')
            ->assertSee($result->payload_hash)->assertDontSee('juan.demo@example.com')
            ->assertDontSee('wire:click="delete', false)->assertDontSee('wire:click="edit', false);

        $this->assertDatabaseCount('audit_logs', 2);
    }
}
