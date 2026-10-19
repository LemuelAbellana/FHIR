<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Tests\TestCase;

class DataPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);
    }

    public function test_patient_sensitive_fields_are_encrypted_and_decrypt_correctly(): void
    {
        $patient = Patient::factory()->create([
            'first_name' => 'Juan', 'middle_name' => 'Demo', 'last_name' => 'Dela Cruz',
            'gender' => 'male', 'birth_date' => '2001-07-16', 'email' => 'juan.demo@example.com',
            'phone' => '09170000000', 'address' => 'Synthetic test address',
        ]);

        $raw = DB::table('patients')->find($patient->id);
        foreach (['first_name', 'middle_name', 'last_name', 'gender', 'birth_date', 'email', 'phone', 'address'] as $field) {
            $this->assertNotSame($patient->$field, $raw->$field);
            $this->assertSame($patient->$field, $patient->fresh()->$field);
        }
        $this->assertSame($patient->patient_number, $raw->patient_number);
        $this->assertIsBool($patient->fresh()->active);
    }

    public function test_demo_seed_is_idempotent_and_preserves_existing_changes(): void
    {
        $this->seed();
        $user = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('Demo1234!', $user->password));
        $user->update(['password' => 'ChangedPassword123!']);
        Patient::where('patient_number', 'PT-DEMO-001')->firstOrFail()->update(['first_name' => 'Changed']);
        $this->seed();

        $this->assertSame(1, User::where('email', 'admin@example.com')->count());
        $this->assertTrue(Hash::check('ChangedPassword123!', $user->fresh()->password));
        $this->assertSame('Changed', Patient::where('patient_number', 'PT-DEMO-001')->firstOrFail()->first_name);
        $this->assertSame(1, Patient::count());
    }

    public function test_audit_model_rejects_updates(): void
    {
        $audit = $this->audit();
        $this->expectException(LogicException::class);
        $audit->update(['result' => 'success']);
    }

    public function test_audit_model_rejects_deletes(): void
    {
        $audit = $this->audit();
        $this->expectException(LogicException::class);
        $audit->delete();
    }

    public function test_database_rejects_bulk_audit_updates(): void
    {
        $audit = $this->audit();
        $this->expectException(QueryException::class);
        DB::table('audit_logs')->where('id', $audit->id)->update(['result' => 'success']);
    }

    public function test_database_rejects_bulk_audit_deletes(): void
    {
        $audit = $this->audit();
        $this->expectException(QueryException::class);
        DB::table('audit_logs')->where('id', $audit->id)->delete();
    }

    public function test_prepare_command_is_repeatable_without_wiping_records(): void
    {
        $this->artisan('demo:prepare')->assertSuccessful();
        Patient::where('patient_number', 'PT-DEMO-001')->firstOrFail()->update(['first_name' => 'Preserved']);
        $this->artisan('demo:prepare')->assertSuccessful();
        $this->assertSame('Preserved', Patient::firstOrFail()->first_name);
    }

    public function test_prepare_restores_an_existing_environment_key_without_replacing_it(): void
    {
        $directory = sys_get_temp_dir().'/fhir-key-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $key = 'base64:'.base64_encode(str_repeat('p', 32));
        $contents = "APP_KEY={$key}\nDB_PASSWORD=preserve-this\n";
        file_put_contents($directory.'/.env', $contents);
        $this->app->useEnvironmentPath($directory);
        config(['app.key' => '']);

        try {
            $this->artisan('demo:prepare')->assertSuccessful();
            $this->assertSame($key, config('app.key'));
            $this->assertSame($contents, file_get_contents($directory.'/.env'));
            $this->assertSame('Juan', Patient::firstOrFail()->first_name);
        } finally {
            unlink($directory.'/.env');
            rmdir($directory);
        }
    }

    public function test_prepare_generates_only_a_missing_key_and_uses_it_for_encryption(): void
    {
        $directory = sys_get_temp_dir().'/fhir-key-'.bin2hex(random_bytes(8));
        mkdir($directory);
        file_put_contents($directory.'/.env', "APP_KEY=\nDB_PASSWORD=preserve-this\n");
        $this->app->useEnvironmentPath($directory);
        app('encrypter');
        config(['app.key' => '']);

        try {
            $this->artisan('demo:prepare')->assertSuccessful();
            $this->assertNotEmpty(config('app.key'));
            $this->assertStringContainsString('DB_PASSWORD=preserve-this', file_get_contents($directory.'/.env'));
            $raw = DB::table('patients')->first();
            $key = base64_decode(substr(config('app.key'), 7));
            $encrypter = new Encrypter($key, config('app.cipher'));
            $this->assertSame('Juan', $encrypter->decrypt($raw->first_name, false));
        } finally {
            unlink($directory.'/.env');
            rmdir($directory);
        }
    }

    public function test_prepare_reports_a_safe_database_failure(): void
    {
        $connection = config('database.default');
        config(['database.default' => 'missing-demo-connection']);

        try {
            $this->artisan('demo:prepare')
                ->expectsOutputToContain('Demo preparation failed. Check APP_KEY, MySQL DB_* settings, and database permissions.')
                ->assertFailed();
        } finally {
            config(['database.default' => $connection]);
        }
    }

    private function audit(): AuditLog
    {
        return AuditLog::create([
            'action' => AuditLog::FHIR_EXPORT_ATTEMPT,
            'resource_type' => 'Patient',
            'result' => 'attempt',
            'payload_hash' => hash('sha256', 'synthetic payload'),
        ]);
    }
}
