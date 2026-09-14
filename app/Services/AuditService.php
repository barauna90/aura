<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\SystemAlert;

/** AUDIT SERVICE — toda ação sensível passa por aqui. Registros nunca são removidos. */
class AuditService
{
    public function log(string $action, ?int $actorId = null, ?string $entityType = null, ?int $entityId = null, ?array $metadata = null, ?string $ip = null): AuditLog
    {
        return AuditLog::create([
            'actor_id' => $actorId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
            'ip' => $ip,
        ]);
    }

    public function alert(string $level, string $source, string $message, ?array $metadata = null): SystemAlert
    {
        return SystemAlert::create(compact('level', 'source', 'message', 'metadata'));
    }
}
