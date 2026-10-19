<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Patient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class FhirPatientService
{
    /**
     * @return array{
     *   resourceType: 'Patient',
     *   identifier: list<array{system: string, value: string}>,
     *   active: bool,
     *   name: list<array{use: string, family: string, given: list<string>}>,
     *   gender: string,
     *   birthDate: string,
     *   telecom?: list<array{system: string, value: string, use?: string}>,
     *   address?: list<array{text: string}>
     * }
     */
    public function payload(Patient $patient): array
    {
        $given = [$patient->first_name];

        if (filled($patient->middle_name)) {
            $given[] = $patient->middle_name;
        }

        $payload = [
            'resourceType' => 'Patient',
            'identifier' => [[
                'system' => 'urn:fhir-patient-demo:patient-number',
                'value' => $patient->patient_number,
            ]],
            'active' => $patient->active,
            'name' => [['use' => 'official', 'family' => $patient->last_name, 'given' => $given]],
            'gender' => $patient->gender,
            'birthDate' => $patient->birth_date,
        ];

        if (filled($patient->phone)) {
            $payload['telecom'][] = ['system' => 'phone', 'value' => $patient->phone, 'use' => 'mobile'];
        }

        if (filled($patient->email)) {
            $payload['telecom'][] = ['system' => 'email', 'value' => $patient->email];
        }

        if (filled($patient->address)) {
            $payload['address'] = [['text' => $patient->address]];
        }

        return $payload;
    }

    public function json(Patient $patient): string
    {
        return json_encode($this->payload($patient), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** @return array{success: bool, http_status: int|null, remote_resource_id: string|null, timestamp: string, message: string} */
    public function send(Patient $patient, ?int $userId): array
    {
        $json = $this->json($patient);
        $destination = $this->destination();
        $metadata = [
            'user_id' => $userId,
            'resource_type' => 'Patient',
            'resource_id' => $patient->id,
            'destination' => $destination,
            'payload_hash' => hash('sha256', $json),
        ];

        AuditLog::create($metadata + [
            'action' => 'FHIR_EXPORT_ATTEMPT',
            'result' => 'attempt',
            'message' => 'FHIR transmission requested.',
        ]);

        $success = false;
        $httpStatus = null;
        $remoteId = null;
        $message = 'FHIR destination must be a valid HTTPS base URL without credentials, query, or fragment.';

        if ($destination !== null) {
            $request = Http::accept('application/fhir+json')
                ->withBody($json, 'application/fhir+json')
                ->withoutRedirecting()
                ->withOptions(['verify' => true])
                ->connectTimeout(config('fhir.connect_timeout', 5))
                ->timeout(config('fhir.timeout', 15));

            if (filled(config('fhir.bearer_token'))) {
                $request->withToken(config('fhir.bearer_token'));
            }

            try {
                $response = $request->post($destination);
                $httpStatus = $response->status();
                $success = $response->successful();
                $message = $success ? 'Transmission successful.' : 'FHIR server rejected the transmission. Please try again later.';

                if ($success) {
                    $remoteId = $this->remoteId($response);
                }
            } catch (ConnectionException) {
                $message = 'FHIR server could not be reached. Please try again later.';
            } catch (RequestException $exception) {
                $httpStatus = $exception->response->status();
                $message = 'FHIR server rejected the transmission. Please try again later.';
            }
        }

        // Audit failures must propagate: an unrecorded transmission cannot be reported as success.
        AuditLog::create($metadata + [
            'action' => $success ? 'FHIR_EXPORT_SUCCESS' : 'FHIR_EXPORT_FAILED',
            'result' => $success ? 'success' : 'failed',
            'http_status' => $httpStatus,
            'remote_resource_id' => $remoteId,
            'message' => $message,
        ]);

        return [
            'success' => $success,
            'http_status' => $httpStatus,
            'remote_resource_id' => $remoteId,
            'timestamp' => now()->toIso8601String(),
            'message' => $message,
        ];
    }

    private function remoteId(Response $response): ?string
    {
        $id = $response->json('id');

        if (is_string($id) && preg_match('/^[A-Za-z0-9\-.]{1,64}$/D', $id)) {
            return $id;
        }

        $path = parse_url($response->header('Location'), PHP_URL_PATH);

        return is_string($path) && preg_match('~/Patient/([A-Za-z0-9\-.]{1,64})(?:/_history/[A-Za-z0-9\-.]{1,64})?/?$~D', $path, $matches)
            ? $matches[1] : null;
    }

    private function destination(): ?string
    {
        $baseUrl = config('fhir.base_url');

        if (! is_string($baseUrl) || ! filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($baseUrl);

        if ($parts === false || strtolower($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
            || str_contains($baseUrl, '\\')) {
            return null;
        }

        return rtrim($baseUrl, '/').'/Patient';
    }
}
