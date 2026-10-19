<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('action', 64)->index();
            $table->string('resource_type', 64);
            $table->unsignedBigInteger('resource_id')->nullable()->index();
            $table->text('destination')->nullable();
            $table->char('payload_hash', 64)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('result', 32);
            $table->string('remote_resource_id')->nullable();
            $table->string('message')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        foreach (['UPDATE', 'DELETE'] as $operation) {
            $name = 'audit_logs_no_'.strtolower($operation);
            if (DB::getDriverName() === 'mysql') {
                DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit logs are immutable'");
            } elseif (DB::getDriverName() === 'sqlite') {
                DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON audit_logs BEGIN SELECT RAISE(ABORT, 'Audit logs are immutable'); END");
            } else {
                throw new RuntimeException('Audit immutability requires MySQL or SQLite.');
            }
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete');
        Schema::dropIfExists('audit_logs');
    }
};
