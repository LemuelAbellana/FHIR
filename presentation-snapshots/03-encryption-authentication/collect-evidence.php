<?php

declare(strict_types=1);

use App\Models\Patient;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Features;

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (DB::getDriverName() !== 'mysql') {
    throw new RuntimeException('Evidence collection requires the temporary MySQL database.');
}

$patient = Patient::where('patient_number', 'PT-DEMO-001')->sole();
$stored = DB::table('patients')->where('id', $patient->id)->first(['first_name', 'email']);
$encryption = [
    'ENCRYPTION AT REST | Synthetic patient PT-DEMO-001',
    'Database driver: '.DB::getDriverName(),
];
foreach (['first_name', 'email'] as $field) {
    $plaintext = (string) $patient->getAttribute($field);
    $ciphertext = (string) $stored->{$field};
    if ($plaintext === '' || $ciphertext === $plaintext || decrypt($ciphertext, false) !== $plaintext) {
        throw new RuntimeException('Encrypted storage verification failed for '.$field.'.');
    }
    $encryption[] = '';
    $encryption[] = $field.' through Eloquent: '.$plaintext;
    $encryption[] = $field.' raw database: '.substr($ciphertext, 0, 80).'... [truncated to 80 characters]';
    $encryption[] = 'PASS: stored value differs from plaintext and decrypts to the model value.';
}
$encryption[] = '';
$encryption[] = 'Public registration enabled: '.(Features::enabled(Features::registration()) ? 'yes' : 'no');
$encryption[] = 'Authenticated route middleware: see routes/web.php:13-20.';
file_put_contents(__DIR__.'/evidence.txt', implode(PHP_EOL, $encryption).PHP_EOL);

$triggers = DB::select("SHOW TRIGGERS WHERE `Table` = 'audit_logs'");
foreach (['UPDATE', 'DELETE'] as $operation) {
    $matches = array_filter($triggers, static fn (object $trigger): bool =>
        $trigger->Event === $operation && $trigger->Timing === 'BEFORE'
        && str_contains($trigger->Statement, '45000')
        && str_contains($trigger->Statement, 'Audit logs are immutable')
    );
    if ($matches === []) {
        throw new RuntimeException('Required immutable '.$operation.' trigger missing; no mutation attempted.');
    }
}

$audit = DB::table('audit_logs')->where('resource_type', 'Patient')
    ->where('resource_id', $patient->id)->orderByDesc('id')->first();
if ($audit === null) {
    throw new RuntimeException('Create a real synthetic-patient export event before collecting audit evidence.');
}
$immutability = [
    'IMMUTABLE AUDIT LOG | Synthetic patient PT-DEMO-001',
    'Database driver: mysql',
    'Verified BEFORE UPDATE and BEFORE DELETE immutability triggers.',
    'Existing event: #'.$audit->id.' | '.$audit->action.' | '.$audit->result,
    '',
];
DB::beginTransaction();
try {
    foreach (['UPDATE', 'DELETE'] as $operation) {
        try {
            $query = DB::table('audit_logs')->where('id', $audit->id);
            $operation === 'UPDATE' ? $query->update(['message' => 'Synthetic immutability probe']) : $query->delete();
            throw new RuntimeException($operation.' unexpectedly succeeded; transaction will be rolled back.');
        } catch (QueryException $error) {
            if (($error->errorInfo[0] ?? null) !== '45000'
                || !str_contains((string) ($error->errorInfo[2] ?? ''), 'Audit logs are immutable')) {
                throw new RuntimeException('Unexpected database error during '.$operation.' probe.');
            }
            $immutability[] = $operation.' blocked: SQLSTATE 45000 | Audit logs are immutable';
        }
    }
    $after = DB::table('audit_logs')->where('id', $audit->id)->first();
    if ($after != $audit) {
        throw new RuntimeException('Audit row verification failed; transaction will be rolled back.');
    }
    $immutability[] = 'PASS: original event still exists and every stored column is unchanged.';
} finally {
    DB::rollBack();
}
$immutability[] = 'Probe transaction rolled back.';
$auditDir = dirname(__DIR__).'/06-immutable-audit-logging';
file_put_contents($auditDir.'/evidence.txt', implode(PHP_EOL, $immutability).PHP_EOL);
$metadata = DB::table('audit_logs')->where('resource_type', 'Patient')
    ->where('resource_id', $patient->id)->orderBy('id')
    ->get(['id', 'action', 'resource_type', 'resource_id', 'payload_hash', 'http_status', 'result', 'remote_resource_id', 'created_at']);
file_put_contents($auditDir.'/audit-events.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
echo 'Evidence saved for encryption and immutable audit logging.'.PHP_EOL;
