<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['user_id', 'action', 'resource_type', 'resource_id', 'destination', 'payload_hash', 'http_status', 'result', 'remote_resource_id', 'message'])]
class AuditLog extends Model
{
    public const FHIR_EXPORT_ATTEMPT = 'FHIR_EXPORT_ATTEMPT';

    public const FHIR_EXPORT_SUCCESS = 'FHIR_EXPORT_SUCCESS';

    public const FHIR_EXPORT_FAILED = 'FHIR_EXPORT_FAILED';

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::saving(function (self $audit): void {
            if ($audit->exists) {
                throw new LogicException('Audit logs are immutable.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Audit logs are immutable.');
        });
    }

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'http_status' => 'integer'];
    }
}
