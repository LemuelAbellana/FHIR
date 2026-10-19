<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$patient = App\Models\Patient::where('patient_number', 'PT-DEMO-001')->firstOrFail();
$json = $app->make(App\Services\FhirPatientService::class)->json($patient);
$path = __DIR__.'/patient-PT-DEMO-001.fhir.json';
if (file_put_contents($path, $json.PHP_EOL) === false) {
    throw new RuntimeException('Could not save the synthetic patient JSON.');
}
echo 'Saved synthetic PT-DEMO-001 FHIR Patient JSON.'.PHP_EOL;
