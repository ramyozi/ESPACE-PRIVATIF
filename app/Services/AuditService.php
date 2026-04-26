<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;
use DateTimeImmutable;

/**
 * Service d'audit avec hash chaine.
 * Chaque ligne contient le hash de la ligne precedente pour le tenant
 * concerne, ce qui permet de detecter une alteration ulterieure.
 */
final class AuditService
{
    public function __construct(private readonly AuditLogRepository $repository)
    {
    }

    public function log(
        int $tenantId,
        ?int $userId,
        string $action,
        ?string $targetType = null,
        ?string $targetId = null,
        ?string $ip = null,
        array $context = [],
    ): void {
        $prev = $this->repository->lastHashForTenant($tenantId);
        $createdAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s.v');

        $payload = [
            'tenant' => $tenantId,
            'user' => $userId,
            'action' => $action,
            'ttype' => $targetType,
            'tid' => $targetId,
            'ip' => $ip,
            'context' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'prev' => $prev,
            'created' => $createdAt,
        ];

        // Le hash inclut prev_hash : toute alteration casse la chaine
        $material = implode('|', [
            $tenantId,
            $userId ?? '',
            $action,
            $targetType ?? '',
            $targetId ?? '',
            $payload['context'],
            $prev ?? '',
            $createdAt,
        ]);
        $payload['row'] = hash('sha256', $material);

        $this->repository->insert($payload);
    }
}
