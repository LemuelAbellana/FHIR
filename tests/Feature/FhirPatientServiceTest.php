<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Services\FhirPatientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class FhirPatientServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('fhir.base_url', 'https://fhir.example.test/baseR4/');
        config()->set('fhir.bearer_token', null);
        Http::preventStrayRequests();
    }

    private function patient(array $attributes = []): Patient
    {
        return Patient::create(array_replace([
            'patient_number' => 'PT-TEST-042',
            'first_name' => 'Maria',
            'middle_name' => 'Luisa',
            'last_name' => 'Santos',
            'gender' => 'female',
            'birth_date' => '1998-02-03',
            'phone' => '09181112222',
            'email' => 'maria.synthetic@example.test',
            'address' => '42 Synthetic Street',
            'active' => true,
        ], $attributes))->fresh();
    }

    public function test_maps_the_selected_database_patient_to_fhir_r4(): void
    {
        $patient = $this->patient();
        $service = app(FhirPatientService::class);

        $expected = [
            'resourceType' => 'Patient',
            'identifier' => [['system' => 'urn:fhir-patient-demo:patient-number', 'value' => 'PT-TEST-042']],
            'active' => true,
            'name' => [['use' => 'official', 'family' => 'Santos', 'given' => ['Maria', 'Luisa']]],
            'gender' => 'female',
            'birthDate' => '1998-02-03',
            'telecom' => [
                ['system' => 'phone', 'value' => '09181112222', 'use' => 'mobile'],
                ['system' => 'email', 'value' => 'maria.synthetic@example.test'],
            ],
            'address' => [['text' => '42 Synthetic Street']],
        ];

        $this->assertSame($expected, $service->payload($patient));
        $this->assertSame($expected, json_decode($service->json($patient), true, flags: JSON_THROW_ON_ERROR));
        $this->assertStringContainsString("\n    \"resourceType\"", $service->json($patient));
        $this->assertSame($service->json($patient), $service->json($patient));
    }

    public function test_omits_missing_optional_fields_and_preserves_false_active(): void
    {
        $payload = app(FhirPatientService::class)->payload($this->patient([
            'middle_name' => null, 'phone' => null, 'email' => null, 'address' => null, 'active' => false,
        ]));

        $this->assertSame(['Maria'], $payload['name'][0]['given']);
        $this->assertFalse($payload['active']);
        $this->assertArrayNotHasKey('telecom', $payload);
        $this->assertArrayNotHasKey('address', $payload);
    }

    public function test_posts_exact_preview_json_and_appends_safe_success_audit_metadata(): void
    {
        $patient = $this->patient();
        $user = User::factory()->create();
        $service = app(FhirPatientService::class);
        $json = $service->json($patient);
        $this->freezeTime();
        Http::fake(function (Request $request, array $options) {
            $this->assertDatabaseHas('audit_logs', ['action' => 'FHIR_EXPORT_ATTEMPT', 'result' => 'attempt']);
            $this->assertFalse($options['allow_redirects']);
            $this->assertTrue($options['verify']);
            $this->assertSame(5, $options['connect_timeout']);
            $this->assertSame(15, $options['timeout']);

            return Http::response(['resourceType' => 'Patient', 'id' => 'remote-42', 'secret' => 'response-secret'], 201);
        });

        $result = $service->send($patient, $user->id);

        $this->assertTrue($result['success']);
        $this->assertSame(201, $result['http_status']);
        $this->assertSame('remote-42', $result['remote_resource_id']);
        $this->assertSame(now()->toIso8601String(), $result['timestamp']);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://fhir.example.test/baseR4/Patient'
            && $request->hasHeader('Content-Type', 'application/fhir+json')
            && $request->hasHeader('Accept', 'application/fhir+json')
            && ! $request->hasHeader('Authorization')
            && $request->body() === $json);
        Http::assertSentCount(1);

        $events = AuditLog::orderBy('id')->get();
        $this->assertSame(['FHIR_EXPORT_ATTEMPT', 'FHIR_EXPORT_SUCCESS'], $events->pluck('action')->all());
        $this->assertSame(['attempt', 'success'], $events->pluck('result')->all());
        foreach ($events as $event) {
            $this->assertSame($user->id, $event->user_id);
            $this->assertSame('Patient', $event->resource_type);
            $this->assertEquals($patient->id, $event->resource_id);
            $this->assertSame('https://fhir.example.test/baseR4/Patient', $event->destination);
            $this->assertSame(hash('sha256', $json), $event->payload_hash);
        }
        $this->assertNull($events[0]->http_status);
        $this->assertSame(201, $events[1]->http_status);
        $this->assertSame('remote-42', $events[1]->remote_resource_id);
        $stored = $events->toJson();
        foreach (['Maria', 'Santos', '09181112222', 'response-secret', 'resourceType'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $stored);
        }
    }

    public function test_attaches_bearer_token_only_when_configured_and_keeps_it_out_of_audits(): void
    {
        config()->set('fhir.bearer_token', 'demo-secret-token');
        Http::fake(['*' => Http::response(['id' => 'remote-1'], 201)]);

        app(FhirPatientService::class)->send($this->patient(), null);

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer demo-secret-token'));
        $this->assertStringNotContainsString('demo-secret-token', AuditLog::all()->toJson());
    }

    #[DataProvider('failedStatuses')]
    public function test_http_errors_and_redirects_append_failure_without_retrying(int $status): void
    {
        Http::fake(['*' => Http::response('private response body', $status, ['Location' => 'http://unsafe.test/Patient'])]);

        $result = app(FhirPatientService::class)->send($this->patient(), null);

        $this->assertFalse($result['success']);
        $this->assertSame($status, $result['http_status']);
        $this->assertNull($result['remote_resource_id']);
        Http::assertSentCount(1);
        $this->assertSame(['attempt', 'failed'], AuditLog::orderBy('id')->pluck('result')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'FHIR_EXPORT_FAILED', 'http_status' => $status]);
        $this->assertStringNotContainsString('private response body', json_encode($result).AuditLog::all()->toJson());
    }

    public static function failedStatuses(): array
    {
        return [[302], [400], [500]];
    }

    public function test_network_failure_is_safe_and_audited_without_retrying(): void
    {
        $calls = 0;
        Http::fake(function (Request $request) use (&$calls) {
            $calls++;

            return Http::failedConnection('private connection details token=secret')($request);
        });

        $result = app(FhirPatientService::class)->send($this->patient(), null);

        $this->assertFalse($result['success']);
        $this->assertNull($result['http_status']);
        $this->assertNull($result['remote_resource_id']);
        $this->assertSame(1, $calls);
        $this->assertSame(['attempt', 'failed'], AuditLog::orderBy('id')->pluck('result')->all());
        $this->assertStringNotContainsString('private connection', json_encode($result).AuditLog::all()->toJson());
    }

    #[DataProvider('invalidDestinations')]
    public function test_rejects_unsafe_destinations_without_sending_or_logging_url_secrets(string $url): void
    {
        config()->set('fhir.base_url', $url);
        Http::fake();

        $result = app(FhirPatientService::class)->send($this->patient(), null);

        $this->assertFalse($result['success']);
        $this->assertNull($result['http_status']);
        Http::assertNothingSent();
        $events = AuditLog::orderBy('id')->get();
        $this->assertSame(['attempt', 'failed'], $events->pluck('result')->all());
        $this->assertSame([null, null], $events->pluck('destination')->all());
        $this->assertStringNotContainsString('secret', json_encode($result).$events->toJson());
    }

    public static function invalidDestinations(): array
    {
        return [
            ['http://fhir.example.test/baseR4'],
            ['https://user:secret@fhir.example.test/baseR4'],
            ['https://fhir.example.test/baseR4?token=secret'],
            ['https://fhir.example.test/baseR4#secret'],
            ['https:///secret'],
            ['https://fhir.example.test/secret path'],
            [''],
        ];
    }

    public function test_does_not_accept_untrusted_response_text_as_a_remote_id(): void
    {
        Http::fake(['*' => Http::response(['id' => 'patient private details token=secret'], 201)]);

        $result = app(FhirPatientService::class)->send($this->patient(), null);

        $this->assertTrue($result['success']);
        $this->assertNull($result['remote_resource_id']);
        $this->assertStringNotContainsString('secret', AuditLog::all()->toJson());
    }

    public function test_reads_created_patient_id_from_fhir_location_header_when_body_is_empty(): void
    {
        Http::fake(['*' => Http::response('', 201, [
            'Location' => 'https://fhir.example.test/baseR4/Patient/remote-42/_history/1',
        ])]);

        $result = app(FhirPatientService::class)->send($this->patient(), null);

        $this->assertTrue($result['success']);
        $this->assertSame('remote-42', $result['remote_resource_id']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'FHIR_EXPORT_SUCCESS', 'remote_resource_id' => 'remote-42']);
    }

    public function test_http_transport_exceptions_are_safe_and_audited(): void
    {
        Http::fake(function () {
            throw Http::failedRequest('private response details', 502);
        });

        $result = app(FhirPatientService::class)->send($this->patient(), null);

        $this->assertFalse($result['success']);
        $this->assertSame(502, $result['http_status']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'FHIR_EXPORT_FAILED', 'http_status' => 502]);
        $this->assertStringNotContainsString('private response', json_encode($result).AuditLog::all()->toJson());
    }

    #[DataProvider('auditWriteFailures')]
    public function test_audit_write_errors_never_return_a_successful_transmission(string $action, int $sent): void
    {
        $patient = $this->patient();
        Http::fake(['*' => Http::response(['id' => 'remote-1'], 201)]);
        $dispatcher = AuditLog::getEventDispatcher();
        AuditLog::setEventDispatcher(clone $dispatcher);
        AuditLog::creating(function (AuditLog $event) use ($action) {
            if ($event->action === $action) {
                throw new RuntimeException('Audit write failed.');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Audit write failed.');
            app(FhirPatientService::class)->send($patient, null);
        } finally {
            AuditLog::setEventDispatcher($dispatcher);
            Http::assertSentCount($sent);
            $this->assertDatabaseCount('audit_logs', $sent);
        }
    }

    public static function auditWriteFailures(): array
    {
        return [['FHIR_EXPORT_ATTEMPT', 0], ['FHIR_EXPORT_SUCCESS', 1]];
    }
}
