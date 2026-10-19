<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Services\FhirPatientService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadFhirPatientController extends Controller
{
    public function __invoke(Patient $patient, FhirPatientService $fhir): StreamedResponse
    {
        $json = $fhir->json($patient);
        $number = preg_replace('/[^A-Za-z0-9_-]/', '-', $patient->patient_number);

        return response()->streamDownload(
            static function () use ($json): void {
                echo $json;
            },
            'patient-'.$number.'.fhir.json',
            ['Content-Type' => 'application/fhir+json; charset=UTF-8'],
        );
    }
}
