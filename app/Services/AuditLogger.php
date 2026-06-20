<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * AuditLogger Service
 *
 * Per OpenAPI AuditLogEntry schema, logs admin actions with:
 * - action: string
 * - entity_type: string
 * - entity_id: integer
 * - details: object
 */
class AuditLogger
{
    /**
     * Log an admin action.
     *
     * @param  array<string, mixed>  $details
     */
    public function log(
        string $action,
        string $entityType,
        int $entityId,
        array $details = []
    ): AdminAuditLog {
        /** @var User $admin */
        $admin = Auth::user();

        return AdminAuditLog::logRaw($admin, $action, $entityType, $entityId, $details);
    }
}
